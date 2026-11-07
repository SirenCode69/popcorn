<?php
defined('ABSPATH') || exit;

function popcorn_tmdb_date($value) {
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function popcorn_tmdb_text($value) {
    return sanitize_text_field(is_scalar($value) ? (string) $value : '');
}

function popcorn_tmdb_trailer($videos) {
    $chosen = '';
    foreach (($videos['results'] ?? []) as $video) {
        if (($video['site'] ?? '') !== 'YouTube' || !in_array(($video['type'] ?? ''), ['Trailer', 'Teaser'], true) || !preg_match('/^[a-zA-Z0-9_-]{11}$/', $video['key'] ?? '')) {
            continue;
        }
        $chosen = 'https://www.youtube.com/watch?v=' . $video['key'];
        if ($video['type'] === 'Trailer') {
            break;
        }
    }
    return $chosen;
}

function popcorn_tmdb_certification($type, $data) {
    if ($type === 'series') {
        foreach (($data['content_ratings']['results'] ?? []) as $rating) {
            if (($rating['iso_3166_1'] ?? '') === 'US') {
                return popcorn_tmdb_text($rating['rating'] ?? '');
            }
        }
        return '';
    }
    foreach (($data['release_dates']['results'] ?? []) as $country) {
        if (($country['iso_3166_1'] ?? '') === 'US') {
            foreach (($country['release_dates'] ?? []) as $release) {
                if (!empty($release['certification'])) {
                    return popcorn_tmdb_text($release['certification']);
                }
            }
        }
    }
    return '';
}

function popcorn_tmdb_existing($type, $tmdb_id, $post_id = 0) {
    global $wpdb;
    $table = popcorn_core_table($type);
    if ($post_id) {
        $by_post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE post_id = %d", $post_id));
        if ($by_post) {
            return $by_post;
        }
    }
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE tmdb_id = %d", $tmdb_id));
}

function popcorn_tmdb_slug($tmdb_id, $english_name) {
    $name = sanitize_title(remove_accents(wp_strip_all_tags((string) $english_name)));
    return absint($tmdb_id) . ($name !== '' ? '-' . $name : '');
}

function popcorn_tmdb_write_post($data, $update = false) {
    $previous = $GLOBALS['popcorn_tmdb_importing_post'] ?? false;
    $GLOBALS['popcorn_tmdb_importing_post'] = true;
    try {
        return $update ? wp_update_post($data, true) : wp_insert_post($data, true);
    } finally {
        $GLOBALS['popcorn_tmdb_importing_post'] = $previous;
    }
}

function popcorn_tmdb_post($type, $existing, $title, $content, $excerpt, $tmdb_id, $english_name, $post_id = 0, $as_draft = false) {
    $post_type = 'popcorn_' . $type;
    $post_id = $post_id ?: (int) ($existing->post_id ?? 0);
    $slug = popcorn_tmdb_slug($tmdb_id, $english_name);
    if ($post_id && get_post_type($post_id) === $post_type) {
        if (get_current_user_id() && !current_user_can('edit_post', $post_id)) {
            return new WP_Error('forbidden', 'اجازهٔ ویرایش این نوشته را ندارید.');
        }
        $post = get_post($post_id);
        $changes = ['ID' => $post_id];
        if ($post->post_name !== $slug) {
            $changes['post_name'] = $slug;
        }
        $tracked_title = $type === 'person' ? ($existing->name ?? '') : (($existing->english_title ?? '') ?: ($existing->title ?? ''));
        if ($post->post_title !== $title && (!$post->post_title || ($type !== 'person' && $tracked_title && $post->post_title === $tracked_title) || ($type === 'person' && !empty($existing->persian_name) && $post->post_title === $existing->persian_name))) {
            $changes['post_title'] = $title;
        }
        $tracked_content = $type === 'person' ? ($existing->biography ?? '') : ($existing->overview ?? '');
        if ($post->post_content !== $content && (!$post->post_content || ($type !== 'person' && $tracked_content && preg_match('/[\x{0600}-\x{06FF}]/u', $tracked_content) && trim($post->post_content) === trim($tracked_content)) || ($type === 'person' && !empty($existing->biography_fa) && trim($post->post_content) === trim($existing->biography_fa)))) {
            $changes['post_content'] = $content;
        }
        if (!$post->post_excerpt) {
            $changes['post_excerpt'] = $excerpt;
        }
        return count($changes) > 1 ? popcorn_tmdb_write_post($changes, true) : $post_id;
    }
    return popcorn_tmdb_write_post(['post_type' => $post_type, 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content, 'post_excerpt' => $excerpt, 'post_status' => !$as_draft && current_user_can('publish_posts') ? 'publish' : 'draft']);
}

function popcorn_tmdb_upsert($type, $tmdb_id, $row, $post_id, $existing) {
    global $wpdb;
    $table = popcorn_core_table($type);
    $existing = $existing ?: popcorn_core_get_by_post($type, $post_id);
    $row['tmdb_id'] = $tmdb_id;
    $row['post_id'] = $post_id;
    $row['updated_at'] = current_time('mysql');
    if ($existing) {
        $ok = $wpdb->update($table, $row, ['id' => $existing->id]);
        $id = (int) $existing->id;
    } else {
        $row['created_at'] = current_time('mysql');
        $ok = $wpdb->insert($table, $row);
        $id = (int) $wpdb->insert_id;
    }
    if ($ok === false) {
        return new WP_Error('database', 'ذخیرهٔ اطلاعات ناموفق بود.');
    }
    update_post_meta($post_id, '_popcorn_item_id', $id);
    do_action('popcorn_core_item_saved', $post_id);
    return $id;
}

function popcorn_tmdb_asset($path, $old_path, $old_id, $title, $download = true) {
    $path = popcorn_tmdb_text($path);
    if ($path && $path === $old_path && wp_attachment_is_image($old_id)) {
        return [(int) $old_id, $old_path];
    }
    if (!$download) {
        return [$path ? 0 : (int) $old_id, $path ?: (string) $old_path];
    }
    $id = $path ? popcorn_tmdb_image($path, $title) : 0;
    return $id ? [$id, $path] : [(int) $old_id, (string) $old_path];
}

function popcorn_tmdb_collection($type, $data, $existing_id = 0) {
    if ($type !== 'movie') {
        return absint($existing_id);
    }
    $collection = $data['belongs_to_collection'] ?? null;
    if (!is_array($collection) || empty($collection['id']) || empty($collection['name'])) {
        return absint($existing_id);
    }
    global $wpdb;
    $table = popcorn_core_table('collection');
    $tmdb_id = absint($collection['id']);
    $name = popcorn_tmdb_text($collection['name']);
    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE content_type = %s AND tmdb_id = %d", $type, $tmdb_id));
    if ($id) {
        return (int) $id;
    }
    $wpdb->insert($table, ['content_type' => $type, 'tmdb_id' => $tmdb_id, 'name' => $name, 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')]);
    return (int) $wpdb->insert_id;
}

function popcorn_tmdb_keep_field_overrides($row, $existing, $post_id) {
    if (!$existing) {
        return $row;
    }
    $overrides = get_post_meta($post_id, '_popcorn_field_overrides', true);
    if (!is_array($overrides)) {
        return $row;
    }
    foreach (array_keys($overrides) as $key) {
        if (array_key_exists($key, $row) && property_exists($existing, $key)) {
            $row[$key] = $existing->$key;
        }
    }
    return $row;
}

function popcorn_tmdb_gallery($paths, $previous) {
    $saved = [];
    $manual = [];
    foreach (json_decode((string) $previous, true) ?: [] as $entry) {
        if (is_array($entry) && !empty($entry['path'])) {
            $saved[$entry['path']] = $entry;
        } elseif (is_array($entry) && !empty($entry['attachment_id'])) {
            $manual[] = $entry;
        }
    }
    $gallery = [];
    foreach ($paths as $path) {
        $old = $saved[$path] ?? [];
        $gallery[] = ['path' => $path, 'attachment_id' => absint($old['attachment_id'] ?? 0), 'attempts' => absint($old['attempts'] ?? 0)];
    }
    return array_merge($gallery, $manual);
}

function popcorn_tmdb_schedule_gallery($type, $content_id, $gallery) {
    $pending = false;
    foreach ($gallery as $image) {
        if (!empty($image['path']) && strpos($image['path'], '/') === 0 && !$image['attachment_id'] && $image['attempts'] < 3) {
            $pending = true;
            break;
        }
    }
    if ($pending && !wp_next_scheduled('popcorn_import_gallery', [$type, $content_id])) {
        wp_schedule_single_event(time() + 45, 'popcorn_import_gallery', [$type, $content_id]);
    }
}

function popcorn_import_tmdb($type, $tmdb_id, $post_id = 0, $as_draft = false) {
    $tmdb_id = absint($tmdb_id);
    $post_id = absint($post_id);
    if (!in_array($type, ['movie', 'series', 'person'], true) || !$tmdb_id || ($post_id && get_post_type($post_id) !== 'popcorn_' . $type)) {
        return new WP_Error('invalid_item', 'نوع یا شناسه معتبر نیست.');
    }
    $path = ($type === 'series' ? 'tv' : $type) . '/' . $tmdb_id;
    $append = $type === 'person' ? 'external_ids' : ($type === 'movie' ? 'credits,videos,release_dates,keywords,images,external_ids' : 'credits,videos,content_ratings,keywords,images,external_ids');
    $en = popcorn_tmdb_request($path, ['language' => 'en-US', 'append_to_response' => $append]);
    if (is_wp_error($en)) {
        return $en;
    }
    $fa = popcorn_tmdb_request($path, ['language' => 'fa-IR']);
    if (is_wp_error($fa)) {
        $fa = [];
    }
    $existing = popcorn_tmdb_existing($type, $tmdb_id, $post_id);
    if ($existing && $existing->tmdb_id && (int) $existing->tmdb_id !== $tmdb_id) {
        return new WP_Error('tmdb_conflict', 'این نوشته به شناسهٔ دیگری از TMDb متصل است.');
    }
    global $wpdb;
    $table = popcorn_core_table($type);
    $other = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE tmdb_id = %d", $tmdb_id));
    if ($other && (!$existing || (int) $other !== (int) $existing->id)) {
        return new WP_Error('tmdb_conflict', 'این شناسهٔ TMDb پیش‌تر به نوشتهٔ دیگری متصل شده است.');
    }
    if ($type === 'person') {
        return popcorn_tmdb_import_person($tmdb_id, $en, $fa, $existing, $post_id, $as_draft);
    }
    return popcorn_tmdb_import_title($type, $tmdb_id, $en, $fa, $existing, $post_id, $as_draft);
}

function popcorn_tmdb_import_person($tmdb_id, $en, $fa, $existing, $post_id, $as_draft = false) {
    $name = popcorn_tmdb_text($en['name'] ?? '');
    if (!$name) {
        return new WP_Error('missing_name', 'نام هنرمند در TMDb پیدا نشد.');
    }
    $persian = popcorn_tmdb_text($fa['name'] ?? '');
    $persian = preg_match('/[\x{0600}-\x{06FF}]/u', $persian) ? $persian : '';
    $biography_fa = sanitize_textarea_field($fa['biography'] ?? '');
    $biography_fa = preg_match('/[\x{0600}-\x{06FF}]/u', $biography_fa) ? $biography_fa : '';
    $post = popcorn_tmdb_post('person', $existing, $name, sanitize_textarea_field($en['biography'] ?? ''), '', $tmdb_id, $name, $post_id, $as_draft);
    if (is_wp_error($post)) {
        return $post;
    }
    $published = get_post_status($post) === 'publish';
    [$profile_id, $profile_path] = $existing && get_post_meta($post, '_popcorn_media_customized', true) ? [(int) $existing->profile_id, (string) $existing->profile_path] : popcorn_tmdb_asset($en['profile_path'] ?? '', $existing->profile_path ?? '', (int) ($existing->profile_id ?? 0), $name, $published);
    $row = [
        'name' => sanitize_text_field(get_the_title($post)),
        'persian_name' => $persian,
        'biography' => sanitize_textarea_field(wp_strip_all_tags(get_post_field('post_content', $post))),
        'biography_fa' => $biography_fa,
        'birth_date' => popcorn_tmdb_date($en['birthday'] ?? ''),
        'death_date' => popcorn_tmdb_date($en['deathday'] ?? ''),
        'gender' => min(3, absint($en['gender'] ?? 0)),
        'place_of_birth' => popcorn_tmdb_text($en['place_of_birth'] ?? ''),
        'profession' => popcorn_tmdb_text($en['known_for_department'] ?? ''),
        'popularity' => max(0, (float) ($en['popularity'] ?? 0)),
        'imdb_id' => popcorn_tmdb_text($en['imdb_id'] ?? ($en['external_ids']['imdb_id'] ?? '')),
        'profile_id' => $profile_id,
        'profile_path' => $profile_path,
    ];
    $row = popcorn_tmdb_keep_field_overrides($row, $existing, $post);
    $id = popcorn_tmdb_upsert('person', $tmdb_id, $row, $post, $existing);
    if (!is_wp_error($id) && $profile_id) {
        set_post_thumbnail($post, $profile_id);
    }
    return $id;
}

function popcorn_tmdb_import_title($type, $tmdb_id, $en, $fa, $existing, $post_id, $as_draft = false) {
    $title_key = $type === 'series' ? 'name' : 'title';
    $original_key = $type === 'series' ? 'original_name' : 'original_title';
    $date_key = $type === 'series' ? 'first_air_date' : 'release_date';
    $english_title = popcorn_tmdb_text($en[$title_key] ?? '');
    $title = popcorn_tmdb_text($en[$original_key] ?? '') ?: $english_title;
    if (!$title) {
        return new WP_Error('missing_title', 'عنوان اثر در TMDb پیدا نشد.');
    }
    $localized = popcorn_tmdb_text($fa[$title_key] ?? '');
    $localized = preg_match('/[\x{0600}-\x{06FF}]/u', $localized) ? $localized : '';
    $overview_fa = sanitize_textarea_field($fa['overview'] ?? '');
    $overview_fa = preg_match('/[\x{0600}-\x{06FF}]/u', $overview_fa) ? $overview_fa : '';
    $overview_en = sanitize_textarea_field($en['overview'] ?? '');
    $overview = $overview_en;
    $tagline = popcorn_tmdb_text($fa['tagline'] ?? '') ?: popcorn_tmdb_text($en['tagline'] ?? '');
    $post = popcorn_tmdb_post($type, $existing, $title, $overview, $tagline, $tmdb_id, $english_title ?: $title, $post_id, $as_draft);
    if (is_wp_error($post)) {
        return $post;
    }
    $published = get_post_status($post) === 'publish';
    $custom_media = $existing && get_post_meta($post, '_popcorn_media_customized', true);
    [$poster_id, $poster_path] = $custom_media ? [(int) $existing->poster_id, (string) $existing->poster_path] : popcorn_tmdb_asset($en['poster_path'] ?? '', $existing->poster_path ?? '', (int) ($existing->poster_id ?? 0), $title . ' - پوستر', $published);
    [$backdrop_id, $backdrop_path] = $custom_media ? [(int) $existing->backdrop_id, (string) $existing->backdrop_path] : popcorn_tmdb_asset($en['backdrop_path'] ?? '', $existing->backdrop_path ?? '', (int) ($existing->backdrop_id ?? 0), $title . ' - پس‌زمینه', $published);
    $genre_names = [];
    foreach (($fa['genres'] ?? $en['genres'] ?? []) as $genre) {
        $name = popcorn_tmdb_text($genre['name'] ?? '');
        if ($name) {
            $genre_names[] = $name;
        }
    }
    $country = $en['production_countries'][0] ?? [];
    $country_code = popcorn_tmdb_text($country['iso_3166_1'] ?? '') ?: (popcorn_tmdb_text($en['origin_country'][0] ?? '') ?: popcorn_tmdb_text($country['name'] ?? ''));
    $country_fa = popcorn_tmdb_text($fa['production_countries'][0]['name'] ?? '');
    $language = $en['spoken_languages'][0] ?? [];
    $language_code = popcorn_tmdb_text($language['iso_639_1'] ?? '') ?: (popcorn_tmdb_text($en['original_language'] ?? '') ?: popcorn_tmdb_text($language['english_name'] ?? ''));
    $language_fa = popcorn_tmdb_text($fa['spoken_languages'][0]['name'] ?? '');
    $status_fa = popcorn_tmdb_text($fa['status'] ?? '');
    $keyword_names = [];
    $keywords = $en['keywords']['keywords'] ?? $en['keywords']['results'] ?? [];
    foreach ($keywords as $keyword) {
        $name = popcorn_tmdb_text($keyword['name'] ?? '');
        if ($name) {
            $keyword_names[] = $name;
        }
    }
    $gallery = [];
    foreach (array_slice($en['images']['backdrops'] ?? [], 0, 10) as $image) {
        $path = popcorn_tmdb_text($image['file_path'] ?? '');
        if (preg_match('~^/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~', $path)) {
            $gallery[] = $path;
        }
    }
    $gallery = $existing && get_post_meta($post, '_popcorn_gallery_customized', true) ? (json_decode((string) $existing->gallery, true) ?: []) : popcorn_tmdb_gallery($gallery, $existing->gallery ?? '[]');
    $row = [
        'title' => sanitize_text_field(get_the_title($post)),
        'english_title' => $english_title,
        'original_title' => $localized,
        'overview' => $overview,
        'overview_en' => $overview_fa,
        'tagline' => $tagline,
        'release_date' => popcorn_tmdb_date($en[$date_key] ?? ''),
        'vote_average' => min(10, max(0, (float) ($en['vote_average'] ?? 0))),
        'vote_count' => absint($en['vote_count'] ?? 0),
        'popularity' => max(0, (float) ($en['popularity'] ?? 0)),
        'genres' => $existing && get_post_meta($post, '_popcorn_taxonomy_customized', true) ? (string) $existing->genres : implode('، ', $genre_names),
        'runtime' => $type === 'movie' ? absint($en['runtime'] ?? 0) : (absint($en['last_episode_to_air']['runtime'] ?? 0) ?: absint($en['episode_run_time'][0] ?? 0)),
        'season_count' => $type === 'series' ? absint($en['number_of_seasons'] ?? 0) : 0,
        'episode_count' => $type === 'series' ? absint($en['number_of_episodes'] ?? 0) : 0,
        'imdb_id' => popcorn_tmdb_text($en['imdb_id'] ?? $en['external_ids']['imdb_id'] ?? ''),
        'certification' => popcorn_tmdb_certification($type, $en),
        'country' => popcorn_core_persian_country($country_code, $country_fa),
        'language' => popcorn_core_persian_language($language_code, $language_fa),
        'studio' => popcorn_tmdb_text($en['production_companies'][0]['name'] ?? ''),
        'network' => $type === 'series' ? popcorn_tmdb_text($en['networks'][0]['name'] ?? '') : '',
        'production_status' => $type === 'series' && in_array(strtolower(popcorn_tmdb_text($en['status'] ?? '')), ['canceled', 'cancelled'], true) ? 'کنسل شده' : (popcorn_core_has_persian_text($status_fa) ? $status_fa : popcorn_core_persian_production_status($en['status'] ?? '', $type)),
        'next_episode_date' => $type === 'series' ? popcorn_tmdb_date($en['next_episode_to_air']['air_date'] ?? '') : null,
        'trailer_url' => popcorn_tmdb_trailer($en['videos'] ?? []),
        'budget' => $type === 'movie' ? absint($en['budget'] ?? 0) : 0,
        'revenue' => $type === 'movie' ? absint($en['revenue'] ?? 0) : 0,
        'keywords' => implode('، ', $keyword_names),
        'gallery' => wp_json_encode($gallery),
        'collection_id' => $existing && get_post_meta($post, '_popcorn_collection_customized', true) ? (int) $existing->collection_id : popcorn_tmdb_collection($type, $en, $existing->collection_id ?? 0),
        'poster_id' => $poster_id,
        'backdrop_id' => $backdrop_id,
        'poster_path' => $poster_path,
        'backdrop_path' => $backdrop_path,
    ];
    $row = popcorn_tmdb_keep_field_overrides($row, $existing, $post);
    $id = popcorn_tmdb_upsert($type, $tmdb_id, $row, $post, $existing);
    if (is_wp_error($id)) {
        return $id;
    }
    if ($poster_id) {
        set_post_thumbnail($post, $poster_id);
    }
    if ($genre_names && !get_post_meta($post, '_popcorn_taxonomy_customized', true)) {
        $GLOBALS['popcorn_tmdb_syncing_genres'] = true;
        try {
            wp_set_object_terms($post, $genre_names, 'popcorn_genre');
        } finally {
            unset($GLOBALS['popcorn_tmdb_syncing_genres']);
        }
    }
    if (!get_post_meta($post, '_popcorn_credits_customized', true)) {
        popcorn_tmdb_sync_credits($type, $id, $en['credits'] ?? [], $published);
    }
    if ($published) {
        popcorn_tmdb_schedule_gallery($type, $id, $gallery);
    }
    do_action('popcorn_core_item_saved', $post);
    return $id;
}

function popcorn_tmdb_credit_person($credit, $published = true) {
    global $wpdb;
    $tmdb_id = absint($credit['id'] ?? 0);
    $name = popcorn_tmdb_text($credit['name'] ?? '');
    if (!$tmdb_id || !$name) {
        return 0;
    }
    $existing = popcorn_tmdb_existing('person', $tmdb_id);
    if ($existing) {
        return (int) $existing->id;
    }
    $post = wp_insert_post(['post_type' => 'popcorn_person', 'post_title' => $name, 'post_name' => popcorn_tmdb_slug($tmdb_id, $name), 'post_status' => $published && current_user_can('publish_posts') ? 'publish' : 'draft'], true);
    if (is_wp_error($post)) {
        return 0;
    }
    $row = ['name' => $name, 'persian_name' => '', 'biography' => '', 'biography_fa' => '', 'birth_date' => null, 'death_date' => null, 'gender' => min(3, absint($credit['gender'] ?? 0)), 'place_of_birth' => '', 'profession' => popcorn_tmdb_text($credit['known_for_department'] ?? $credit['department'] ?? ''), 'popularity' => max(0, (float) ($credit['popularity'] ?? 0)), 'imdb_id' => '', 'profile_id' => 0, 'profile_path' => popcorn_tmdb_text($credit['profile_path'] ?? '')];
    $id = popcorn_tmdb_upsert('person', $tmdb_id, $row, $post, null);
    if (!is_wp_error($id) && !$published) {
        update_post_meta($post, '_popcorn_deferred_credit', 1);
    }
    if (!is_wp_error($id) && $published && !wp_next_scheduled('popcorn_enrich_person', [$tmdb_id])) {
        wp_schedule_single_event(time() + 30 + ($tmdb_id % 10) * 15, 'popcorn_enrich_person', [$tmdb_id]);
    }
    return is_wp_error($id) ? 0 : $id;
}

function popcorn_tmdb_sync_credits($type, $content_id, $credits, $published = true) {
    global $wpdb;
    $table = popcorn_core_table('credit');
    $entries = [];
    foreach (array_slice($credits['cast'] ?? [], 0, 10) as $index => $cast) {
        $entries[] = [$cast, 'cast', popcorn_tmdb_text($cast['character'] ?? ''), '', $index];
    }
    $crew_count = 0;
    foreach (($credits['crew'] ?? []) as $crew) {
        $job = popcorn_tmdb_text($crew['job'] ?? '');
        $role = $job === 'Director' ? 'director' : (in_array($job, ['Writer', 'Screenplay', 'Story'], true) ? 'writer' : '');
        if ($role && $crew_count < 10) {
            $entries[] = [$crew, $role, '', $job, $crew_count++];
        }
    }
    $wpdb->delete($table, ['content_type' => $type, 'content_id' => $content_id]);
    foreach ($entries as [$credit, $role, $character, $job, $order]) {
        $person_id = popcorn_tmdb_credit_person($credit, $published);
        if ($person_id) {
            $wpdb->insert($table, ['content_type' => $type, 'content_id' => $content_id, 'person_id' => $person_id, 'role' => $role, 'character_name' => $character, 'job' => $job, 'sort_order' => $order]);
        }
    }
}

function popcorn_tmdb_materialize_images($post_id) {
    if (!empty($GLOBALS['popcorn_tmdb_importing_post']) || get_post_status($post_id) !== 'publish') {
        return;
    }
    $type = str_replace('popcorn_', '', get_post_type($post_id));
    if (!in_array($type, ['movie', 'series', 'person'], true)) {
        return;
    }
    $item = popcorn_core_get_by_post($type, $post_id);
    if (!$item) {
        return;
    }
    global $wpdb;
    $changes = [];
    $primary = $type === 'person' ? 'profile' : 'poster';
    foreach ($type === 'person' ? ['profile', 'backdrop'] : ['poster', 'backdrop'] as $asset) {
        $path = (string) ($item->{$asset . '_path'} ?? '');
        $old_id = absint($item->{$asset . '_id'} ?? 0);
        if (!$path || strpos($path, '/') !== 0 || wp_attachment_is_image($old_id)) {
            continue;
        }
        [$new_id] = popcorn_tmdb_asset($path, $path, $old_id, $item->{$type === 'person' ? 'name' : 'title'} . ' - ' . $asset);
        if ($new_id) {
            $changes[$asset . '_id'] = $new_id;
            if ($asset === $primary) {
                set_post_thumbnail($post_id, $new_id);
            }
        }
    }
    if ($changes) {
        $changes['updated_at'] = current_time('mysql');
        $wpdb->update(popcorn_core_table($type), $changes, ['id' => $item->id]);
    }
    if ($type === 'person') {
        return;
    }
    $gallery = json_decode((string) $item->gallery, true);
    if (is_array($gallery)) {
        popcorn_tmdb_schedule_gallery($type, (int) $item->id, $gallery);
    }
    $credits = popcorn_core_table('credit');
    $people = popcorn_core_table('person');
    $deferred = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT p.post_id,p.tmdb_id FROM $credits c INNER JOIN $people p ON p.id=c.person_id INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.post_id AND m.meta_key='_popcorn_deferred_credit' WHERE c.content_type=%s AND c.content_id=%d", $type, $item->id));
    foreach ($deferred as $person) {
        if (get_post_status($person->post_id) === 'draft') {
            wp_update_post(['ID' => (int) $person->post_id, 'post_status' => 'publish']);
        }
        delete_post_meta($person->post_id, '_popcorn_deferred_credit');
        if ($person->tmdb_id && !wp_next_scheduled('popcorn_enrich_person', [(int) $person->tmdb_id])) {
            wp_schedule_single_event(time() + 30 + ((int) $person->tmdb_id % 10) * 15, 'popcorn_enrich_person', [(int) $person->tmdb_id]);
        }
    }
}

add_action('popcorn_enrich_person', function ($tmdb_id) {
    $person = popcorn_tmdb_existing('person', $tmdb_id);
    if (!$person || get_post_status($person->post_id) !== 'publish') {
        return;
    }
    $result = popcorn_import_tmdb('person', $tmdb_id);
    if (!is_wp_error($result)) {
        delete_post_meta($person->post_id, '_popcorn_sync_attempts');
        return;
    }
    $attempts = (int) get_post_meta($person->post_id, '_popcorn_sync_attempts', true) + 1;
    update_post_meta($person->post_id, '_popcorn_sync_attempts', $attempts);
    if ($attempts < 3 && $result->get_error_code() !== 'missing_token') {
        wp_schedule_single_event(time() + $attempts * 600, 'popcorn_enrich_person', [$tmdb_id]);
    }
});

add_action('popcorn_import_gallery', function ($type, $content_id) {
    if (!in_array($type, ['movie', 'series'], true)) {
        return;
    }
    global $wpdb;
    $table = popcorn_core_table($type);
    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", absint($content_id)));
    if (!$item) {
        return;
    }
    if (get_post_status($item->post_id) !== 'publish') {
        return;
    }
    $gallery = json_decode($item->gallery, true);
    if (!is_array($gallery)) {
        return;
    }
    $processed = 0;
    foreach ($gallery as &$image) {
        if (!is_array($image) || empty($image['path']) || strpos($image['path'], '/') !== 0 || !empty($image['attachment_id']) || absint($image['attempts'] ?? 0) >= 3) {
            continue;
        }
        $image['attachment_id'] = popcorn_tmdb_image($image['path'] ?? '', $item->title . ' - گالری');
        $image['attempts'] = absint($image['attempts'] ?? 0) + 1;
        if (++$processed >= 2) {
            break;
        }
    }
    unset($image);
    $wpdb->update($table, ['gallery' => wp_json_encode($gallery)], ['id' => $item->id]);
    popcorn_tmdb_schedule_gallery($type, (int) $item->id, $gallery);
}, 10, 2);
