<?php
defined('ABSPATH') || exit;

add_action('init', function () {
    register_taxonomy('popcorn_genre', ['popcorn_movie', 'popcorn_series'], ['labels' => ['name' => 'ژانرها', 'singular_name' => 'ژانر'], 'public' => true, 'show_in_rest' => true, 'hierarchical' => true, 'rewrite' => ['slug' => 'genre']]);
}, 6);

function popcorn_core_get_by_post($type, $post_id) {
    global $wpdb;
    if (!in_array($type, ['movie', 'series', 'person'], true)) {
        return null;
    }
    $table = popcorn_core_table($type);
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE post_id = %d", $post_id));
}

function popcorn_core_profession_labels() {
    return ['' => 'نامشخص', 'Acting' => 'بازیگر', 'Directing' => 'کارگردان', 'Writing' => 'نویسنده', 'Production' => 'تهیه‌کننده', 'Camera' => 'فیلم‌بردار', 'Editing' => 'تدوین‌گر', 'Sound' => 'صدا', 'Art' => 'طراحی صحنه', 'Costume & Make-Up' => 'طراحی لباس و گریم', 'Visual Effects' => 'جلوه‌های ویژه', 'Lighting' => 'نورپردازی', 'Creator' => 'خالق اثر', 'Crew' => 'سایر عوامل'];
}

function popcorn_core_gender_labels() {
    return ['0' => 'نامشخص', '1' => 'زن', '2' => 'مرد'];
}

function popcorn_core_profession_label($value) {
    $labels = popcorn_core_profession_labels();
    return $labels[$value] ?? $value;
}

function popcorn_core_migrate_person_posts() {
    if (get_option('popcorn_core_person_post_version') === '1') {
        return;
    }
    global $wpdb;
    $table = popcorn_core_table('person');
    $cursor = absint(get_option('popcorn_core_person_post_cursor', 0));
    $people = $wpdb->get_results($wpdb->prepare("SELECT i.id,i.post_id,i.name,i.persian_name,i.biography,i.biography_fa,p.post_title,p.post_content FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE i.id>%d AND i.tmdb_id IS NOT NULL ORDER BY i.id LIMIT 100", $cursor));
    foreach ($people as $person) {
        $changes = [];
        if ($person->name && $person->post_title !== $person->name && (!$person->post_title || ($person->persian_name && $person->post_title === $person->persian_name))) {
            $changes['post_title'] = $person->name;
        }
        if ($person->biography && $person->post_content !== $person->biography && (!$person->post_content || ($person->biography_fa && trim($person->post_content) === trim($person->biography_fa)))) {
            $changes['post_content'] = $person->biography;
        }
        if ($changes) {
            $changes['post_modified'] = current_time('mysql');
            $changes['post_modified_gmt'] = current_time('mysql', true);
            $wpdb->update($wpdb->posts, $changes, ['ID' => $person->post_id]);
            clean_post_cache($person->post_id);
        }
        $cursor = (int) $person->id;
    }
    if (count($people) < 100) {
        update_option('popcorn_core_person_post_version', '1', false);
        delete_option('popcorn_core_person_post_cursor');
    } else {
        update_option('popcorn_core_person_post_cursor', $cursor, false);
        if (!wp_next_scheduled('popcorn_core_migrate_person_posts')) {
            wp_schedule_single_event(time() + 5, 'popcorn_core_migrate_person_posts');
        }
    }
}
add_action('admin_init', 'popcorn_core_migrate_person_posts');
add_action('popcorn_core_migrate_person_posts', 'popcorn_core_migrate_person_posts');

function popcorn_core_migrate_content_language() {
    if (get_option('popcorn_core_content_language_version') === '1') {
        return;
    }
    global $wpdb;
    $cursor = absint(get_option('popcorn_core_content_language_cursor', 0));
    $items = $wpdb->get_results($wpdb->prepare("SELECT p.ID,p.post_type,p.post_title,p.post_content FROM {$wpdb->posts} p WHERE p.ID>%d AND p.post_type IN ('popcorn_movie','popcorn_series') ORDER BY p.ID LIMIT 100", $cursor));
    foreach ($items as $post) {
        $type = $post->post_type === 'popcorn_movie' ? 'movie' : 'series';
        $table = popcorn_core_table($type);
        $item = $wpdb->get_row($wpdb->prepare("SELECT id,title,original_title,overview,overview_en FROM $table WHERE post_id=%d", $post->ID));
        if ($item) {
            $post_changes = [];
            $item_changes = [];
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $post->post_title) && $item->original_title && !preg_match('/[\x{0600}-\x{06FF}]/u', $item->original_title)) {
                $post_changes['post_title'] = $item->original_title;
                $item_changes['title'] = $item->original_title;
                $item_changes['original_title'] = $post->post_title;
            }
            if (preg_match('/[\x{0600}-\x{06FF}]/u', $post->post_content) && $item->overview_en && !preg_match('/[\x{0600}-\x{06FF}]/u', $item->overview_en)) {
                $post_changes['post_content'] = $item->overview_en;
                $item_changes['overview'] = $item->overview_en;
                $item_changes['overview_en'] = preg_match('/[\x{0600}-\x{06FF}]/u', $item->overview) ? $item->overview : sanitize_textarea_field(wp_strip_all_tags($post->post_content));
            }
            if ($item_changes) {
                $item_changes['updated_at'] = current_time('mysql');
                $wpdb->update($table, $item_changes, ['id' => $item->id]);
            }
            if ($post_changes) {
                $post_changes['post_modified'] = current_time('mysql');
                $post_changes['post_modified_gmt'] = current_time('mysql', true);
                $wpdb->update($wpdb->posts, $post_changes, ['ID' => $post->ID]);
                clean_post_cache($post->ID);
            }
        }
        $cursor = (int) $post->ID;
    }
    if (count($items) < 100) {
        update_option('popcorn_core_content_language_version', '1', false);
        delete_option('popcorn_core_content_language_cursor');
    } else {
        update_option('popcorn_core_content_language_cursor', $cursor, false);
        if (!wp_next_scheduled('popcorn_core_migrate_content_language')) {
            wp_schedule_single_event(time() + 5, 'popcorn_core_migrate_content_language');
        }
    }
}
add_action('admin_init', 'popcorn_core_migrate_content_language');
add_action('popcorn_core_migrate_content_language', 'popcorn_core_migrate_content_language');

function popcorn_core_migrate_persian_metadata() {
    if (get_option('popcorn_core_persian_metadata_version') === '1') {
        return;
    }
    global $wpdb;
    $complete = true;
    foreach (['movie', 'series'] as $type) {
        $table = popcorn_core_table($type);
        $cursor_key = 'popcorn_core_persian_metadata_' . $type . '_cursor';
        $cursor = absint(get_option($cursor_key, 0));
        $items = $wpdb->get_results($wpdb->prepare("SELECT id,country,language,production_status FROM $table WHERE id>%d ORDER BY id LIMIT 100", $cursor));
        foreach ($items as $item) {
            $changes = [];
            foreach (['country' => 'popcorn_core_persian_country', 'language' => 'popcorn_core_persian_language', 'production_status' => 'popcorn_core_persian_production_status'] as $field => $normalizer) {
                $value = $field === 'production_status' ? $normalizer($item->$field, $type) : $normalizer($item->$field);
                if ($value !== $item->$field) {
                    $changes[$field] = $value;
                }
            }
            if ($changes) {
                $changes['updated_at'] = current_time('mysql');
                $wpdb->update($table, $changes, ['id' => $item->id]);
            }
            $cursor = (int) $item->id;
        }
        if (count($items) < 100) {
            delete_option($cursor_key);
        } else {
            update_option($cursor_key, $cursor, false);
            $complete = false;
        }
    }
    if ($complete) {
        update_option('popcorn_core_persian_metadata_version', '1', false);
    } elseif (!wp_next_scheduled('popcorn_core_migrate_persian_metadata')) {
        wp_schedule_single_event(time() + 5, 'popcorn_core_migrate_persian_metadata');
    }
}
add_action('admin_init', 'popcorn_core_migrate_persian_metadata');
add_action('popcorn_core_migrate_persian_metadata', 'popcorn_core_migrate_persian_metadata');

add_filter('enter_title_here', function ($placeholder, $post) {
    return in_array($post->post_type ?? '', ['popcorn_movie', 'popcorn_series'], true) ? 'عنوان اصلی به زبان اصلی اثر' : $placeholder;
}, 10, 2);

function popcorn_core_editor_fields($type) {
    if ($type === 'person') {
        return ['persian_name' => ['نام فارسی', 'text'], 'biography_fa' => ['زندگی‌نامهٔ فارسی', 'textarea'], 'birth_date' => ['تاریخ تولد', 'date'], 'death_date' => ['تاریخ درگذشت', 'date'], 'gender' => ['جنسیت', 'select'], 'place_of_birth' => ['محل تولد', 'text'], 'profession' => ['حرفه', 'select'], 'popularity' => ['محبوبیت TMDb', 'number'], 'imdb_id' => ['شناسهٔ IMDb', 'text']];
    }
    $fields = ['english_title' => ['عنوان انگلیسی', 'text'], 'original_title' => ['عنوان فارسی', 'text'], 'overview_en' => ['خلاصهٔ فارسی', 'textarea'], 'tagline' => ['شعار اثر', 'text'], 'release_date' => ['تاریخ انتشار', 'date'], 'runtime' => ['مدت (دقیقه)', 'number'], 'is_featured' => ['ویژه', 'checkbox'], 'show_in_slider' => ['نمایش در اسلایدر', 'checkbox'], 'is_coming_soon' => ['به‌زودی', 'checkbox'], 'rating' => ['امتیاز سایت', 'number'], 'imdb_rating' => ['امتیاز IMDb', 'number'], 'imdb_votes' => ['تعداد رأی IMDb', 'number'], 'metacritic_rating' => ['امتیاز Metacritic', 'number'], 'rotten_tomatoes_rating' => ['امتیاز Rotten Tomatoes', 'number'], 'vote_average' => ['امتیاز TMDb', 'number'], 'vote_count' => ['تعداد رأی TMDb', 'number'], 'popularity' => ['محبوبیت TMDb', 'number'], 'genres' => ['ژانرها', 'text'], 'imdb_id' => ['شناسهٔ IMDb', 'text'], 'certification' => ['رده‌بندی سنی', 'text'], 'country' => ['کشور', 'text'], 'language' => ['زبان', 'text'], 'studio' => ['استودیو', 'text'], 'production_status' => ['وضعیت تولید', 'select'], 'trailer_url' => ['نشانی تریلر', 'url'], 'keywords' => ['کلیدواژه‌ها', 'text']];
    if ($type === 'series') {
        $fields['season_count'] = ['تعداد فصل', 'number'];
        $fields['episode_count'] = ['تعداد قسمت', 'number'];
        $fields['network'] = ['شبکه', 'text'];
        $fields['next_episode_date'] = ['تاریخ قسمت بعدی', 'date'];
    } else {
        $fields['budget'] = ['بودجه', 'number'];
        $fields['revenue'] = ['فروش', 'number'];
    }
    return $fields;
}

add_action('add_meta_boxes', function () {
    foreach (['movie', 'series', 'person'] as $type) {
        remove_meta_box('popcorn_tmdb_info', 'popcorn_' . $type, 'side');
        add_meta_box('popcorn_tmdb_info', 'پیوند TMDb', function ($post) use ($type) {
            $item = popcorn_core_get_by_post($type, $post->ID);
            $id = absint($item->tmdb_id ?? 0);
            echo '<p><a class="button" href="' . esc_url(popcorn_import_admin_url($type) . '&post_id=' . $post->ID) . '">جست‌وجو و دریافت از TMDb</a></p>';
            if ($id) {
                $url = wp_nonce_url(admin_url('admin-post.php?action=popcorn_import&type=' . $type . '&tmdb_id=' . $id . '&post_id=' . $post->ID), 'popcorn_import');
                echo '<p><a class="button" href="' . esc_url($url) . '">به‌روزرسانی از TMDb</a></p>';
            }
        }, 'popcorn_' . $type, 'side');
        add_meta_box('popcorn_structured_fields', 'اطلاعات پاپکورن', function ($post) use ($type) {
            popcorn_core_render_editor_tabs($post, $type);
        }, 'popcorn_' . $type, 'normal');
    }
}, 20);

function popcorn_core_clean_editor_fields($type, $input) {
    $output = [];
    foreach (popcorn_core_editor_fields($type) as $key => $details) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $value = $input[$key];
        if ($details[1] === 'date') {
            $output[$key] = popcorn_tmdb_date($value);
        } elseif ($details[1] === 'number') {
            $output[$key] = in_array($key, ['rating', 'imdb_rating', 'vote_average'], true) ? min(10, max(0, (float) $value)) : (in_array($key, ['popularity'], true) ? max(0, (float) $value) : (in_array($key, ['metacritic_rating', 'rotten_tomatoes_rating'], true) ? min(100, absint($value)) : absint($value)));
        } elseif ($details[1] === 'checkbox') {
            $output[$key] = empty($value) ? 0 : 1;
        } elseif ($details[1] === 'textarea') {
            $output[$key] = sanitize_textarea_field($value);
        } elseif ($details[1] === 'url') {
            $output[$key] = esc_url_raw($value, ['http', 'https']);
        } elseif ($details[1] === 'select' && $type === 'person' && $key === 'gender') {
            $gender = absint($value);
            $output[$key] = in_array($gender, [1, 2], true) ? $gender : 0;
        } elseif ($details[1] === 'select' && $type === 'person' && $key === 'profession') {
            $output[$key] = array_key_exists((string) $value, popcorn_core_profession_labels()) ? (string) $value : sanitize_text_field($value);
        } elseif ($type !== 'person' && in_array($key, ['country', 'language', 'production_status'], true)) {
            $normalizer = ['country' => 'popcorn_core_persian_country', 'language' => 'popcorn_core_persian_language', 'production_status' => 'popcorn_core_persian_production_status'][$key];
            $output[$key] = $key === 'production_status' ? $normalizer($value, $type) : $normalizer($value);
        } else {
            $output[$key] = sanitize_text_field($value);
        }
    }
    return $output;
}

function popcorn_core_field_value_changed($type, $key, $before, $after) {
    $fields = popcorn_core_editor_fields($type);
    $field_type = $fields[$key][1] ?? 'text';
    if (in_array($field_type, ['number', 'checkbox'], true)) {
        return (float) $before !== (float) $after;
    }
    return (string) $before !== (string) $after;
}

function popcorn_core_save_editor_post($post_id, $post) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_status === 'auto-draft' || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $type = str_replace('popcorn_', '', $post->post_type);
    if (!in_array($type, ['movie', 'series', 'person'], true)) {
        return;
    }
    global $wpdb;
    $table = popcorn_core_table($type);
    $existing = popcorn_core_get_by_post($type, $post_id);
    $row = ['post_id' => $post_id, 'updated_at' => current_time('mysql')];
    if ($type === 'person') {
        $row['name'] = sanitize_text_field($post->post_title);
        $row['biography'] = sanitize_textarea_field(wp_strip_all_tags($post->post_content));
        $row['profile_id'] = get_post_thumbnail_id($post_id);
    } else {
        $row['title'] = sanitize_text_field($post->post_title);
        $row['overview'] = sanitize_textarea_field(wp_strip_all_tags($post->post_content));
        $row['tagline'] = sanitize_text_field($post->post_excerpt);
        $row['poster_id'] = get_post_thumbnail_id($post_id);
    }
    if (isset($_POST['popcorn_fields_nonce'], $_POST['popcorn_fields']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['popcorn_fields_nonce'])), 'popcorn_fields_' . $post_id) && is_array($_POST['popcorn_fields'])) {
        $clean = popcorn_core_clean_editor_fields($type, wp_unslash($_POST['popcorn_fields']));
        if ($type !== 'person' && popcorn_core_rating_vote_count($post_id) > 0) {
            unset($clean['rating']);
        }
        $overrides = get_post_meta($post_id, '_popcorn_field_overrides', true);
        $overrides = is_array($overrides) ? $overrides : [];
        foreach ($clean as $key => $value) {
            if (popcorn_core_field_value_changed($type, $key, $existing->$key ?? null, $value)) {
                $overrides[$key] = true;
            }
        }
        if ($overrides) {
            update_post_meta($post_id, '_popcorn_field_overrides', $overrides);
        }
        $row = array_merge($row, $clean);
    }
    if ($existing) {
        $wpdb->update($table, $row, ['id' => $existing->id]);
        $id = (int) $existing->id;
    } else {
        if ($type === 'person') {
            $row = array_merge(['tmdb_id' => null, 'persian_name' => '', 'biography' => '', 'birth_date' => null, 'death_date' => null, 'gender' => 0, 'place_of_birth' => '', 'profession' => '', 'popularity' => 0, 'imdb_id' => '', 'profile_path' => '', 'created_at' => current_time('mysql')], $row);
        } else {
            $row = array_merge(['tmdb_id' => null, 'english_title' => '', 'original_title' => '', 'overview_en' => '', 'genres' => '', 'gallery' => '[]', 'created_at' => current_time('mysql')], $row);
        }
        $wpdb->insert($table, $row);
        $id = (int) $wpdb->insert_id;
    }
    if ($id) {
        update_post_meta($post_id, '_popcorn_item_id', $id);
    }
}

function popcorn_core_sync_thumbnail($meta_id, $post_id, $meta_key, $value) {
    if ($meta_key !== '_thumbnail_id') {
        return;
    }
    $post_type = get_post_type($post_id);
    $type = $post_type ? str_replace('popcorn_', '', $post_type) : '';
    if (!in_array($type, ['movie', 'series', 'person'], true)) {
        return;
    }
    global $wpdb;
    $wpdb->update(popcorn_core_table($type), [$type === 'person' ? 'profile_id' : 'poster_id' => absint($value), 'updated_at' => current_time('mysql')], ['post_id' => $post_id]);
    if ($value && get_post_status($post_id) === 'publish') {
        popcorn_core_prepare_webp(absint($value));
    }
}
add_action('added_post_meta', 'popcorn_core_sync_thumbnail', 10, 4);
add_action('updated_post_meta', 'popcorn_core_sync_thumbnail', 10, 4);
add_action('deleted_post_meta', function ($meta_id, $post_id, $meta_key) {
    popcorn_core_sync_thumbnail($meta_id, $post_id, $meta_key, 0);
}, 10, 3);

add_action('set_object_terms', function ($post_id, $terms, $term_taxonomy_ids, $taxonomy, $append, $old_term_taxonomy_ids) {
    if ($taxonomy !== 'popcorn_genre' || !in_array(get_post_type($post_id), ['popcorn_movie', 'popcorn_series'], true)) {
        return;
    }
    $selected = wp_get_post_terms($post_id, 'popcorn_genre');
    if (is_wp_error($selected)) {
        return;
    }
    $names = wp_list_pluck($selected, 'name');
    global $wpdb;
    $type = str_replace('popcorn_', '', get_post_type($post_id));
    $wpdb->update(popcorn_core_table($type), ['genres' => implode('، ', $names), 'updated_at' => current_time('mysql')], ['post_id' => $post_id]);
    $current_ids = array_map('intval', wp_list_pluck($selected, 'term_taxonomy_id'));
    $previous_ids = array_map('intval', (array) $old_term_taxonomy_ids);
    sort($current_ids);
    sort($previous_ids);
    if ($current_ids !== $previous_ids && empty($GLOBALS['popcorn_tmdb_syncing_genres'])) {
        update_post_meta($post_id, '_popcorn_taxonomy_customized', 1);
    }
}, 10, 6);

foreach (['movie', 'series', 'person'] as $popcorn_editor_type) {
    add_action('save_post_popcorn_' . $popcorn_editor_type, 'popcorn_core_save_editor_post', 20, 2);
    add_action('save_post_popcorn_' . $popcorn_editor_type, 'popcorn_tmdb_materialize_images', 40);
}

add_action('before_delete_post', function ($post_id, $post) {
    $type = str_replace('popcorn_', '', $post->post_type);
    if (!in_array($type, ['movie', 'series', 'person'], true)) {
        return;
    }
    global $wpdb;
    $item = popcorn_core_get_by_post($type, $post_id);
    if (!$item) {
        return;
    }
    if ($type === 'person') {
        $wpdb->delete(popcorn_core_table('credit'), ['person_id' => $item->id]);
    } else {
        $wpdb->delete(popcorn_core_table('credit'), ['content_type' => $type, 'content_id' => $item->id]);
    }
    $wpdb->delete(popcorn_core_table($type), ['id' => $item->id]);
}, 10, 2);

function popcorn_core_get_credits($type, $content_id, $role = '') {
    global $wpdb;
    if (!in_array($type, ['movie', 'series'], true)) {
        return [];
    }
    $credits = popcorn_core_table('credit');
    $people = popcorn_core_table('person');
    $sql = "SELECT c.role,c.character_name,c.job,c.sort_order,p.id,p.post_id,p.name,p.persian_name,p.profession,p.death_date,p.profile_id,p.profile_path FROM $credits c INNER JOIN $people p ON p.id=c.person_id INNER JOIN {$wpdb->posts} wp ON wp.ID=p.post_id AND wp.post_status='publish' WHERE c.content_type=%s AND c.content_id=%d";
    if ($role) {
        $sql .= ' AND c.role=%s';
        $args = [$type, $content_id, $role];
    } else {
        $args = [$type, $content_id];
    }
    $rows = $wpdb->get_results($wpdb->prepare($sql . ' ORDER BY c.role,c.sort_order LIMIT 30', ...$args));
    popcorn_core_person_work_counts(wp_list_pluck($rows, 'id'));
    return $rows;
}

function popcorn_core_person_work_counts($person_ids) {
    static $counts = [];
    $ids = array_values(array_unique(array_filter(array_map('absint', (array) $person_ids))));
    $missing = array_values(array_diff($ids, array_keys($counts)));
    if ($missing) {
        global $wpdb;
        $credits = popcorn_core_table('credit');
        $movies = popcorn_core_table('movie');
        $series = popcorn_core_table('series');
        $placeholders = implode(',', array_fill(0, count($missing), '%d'));
        $sql = "SELECT works.person_id,COUNT(*) AS work_count FROM (SELECT DISTINCT c.person_id,c.content_type,c.content_id FROM $credits c INNER JOIN $movies i ON i.id=c.content_id INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish' WHERE c.content_type='movie' AND c.person_id IN ($placeholders) UNION SELECT DISTINCT c.person_id,c.content_type,c.content_id FROM $credits c INNER JOIN $series i ON i.id=c.content_id INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish' WHERE c.content_type='series' AND c.person_id IN ($placeholders)) works GROUP BY works.person_id";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($missing, $missing)));
        foreach ($missing as $id) {
            $counts[$id] = 0;
        }
        foreach ($rows as $row) {
            $counts[(int) $row->person_id] = (int) $row->work_count;
        }
    }
    return array_intersect_key($counts, array_flip($ids));
}

function popcorn_core_person_works($person_id, $limit = 12, $order = 'release_date') {
    global $wpdb;
    $credits = popcorn_core_table('credit');
    $movies = popcorn_core_table('movie');
    $series = popcorn_core_table('series');
    $limit = min(100, max(1, absint($limit)));
    $ordering = $order === 'popularity' ? 'popularity DESC, release_date DESC, post_id DESC' : 'release_date DESC';
    $sql = "SELECT DISTINCT i.*,'movie' AS popcorn_type FROM $credits c INNER JOIN $movies i ON i.id=c.content_id INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish' WHERE c.content_type='movie' AND c.person_id=%d UNION SELECT DISTINCT i.*,'series' AS popcorn_type FROM $credits c INNER JOIN $series i ON i.id=c.content_id INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish' WHERE c.content_type='series' AND c.person_id=%d ORDER BY $ordering LIMIT %d";
    return $wpdb->get_results($wpdb->prepare($sql, $person_id, $person_id, $limit));
}

function popcorn_core_person_work_credits($person_id, $works) {
    global $wpdb;
    $ids = ['movie' => [], 'series' => []];
    foreach ($works as $work) {
        $type = $work->popcorn_type ?? '';
        if (isset($ids[$type])) {
            $ids[$type][(int) $work->id] = (int) $work->id;
        }
    }
    $conditions = [];
    $values = [absint($person_id)];
    foreach ($ids as $type => $content_ids) {
        if (!$content_ids) {
            continue;
        }
        $conditions[] = '(content_type=%s AND content_id IN (' . implode(',', array_fill(0, count($content_ids), '%d')) . '))';
        array_push($values, $type, ...array_values($content_ids));
    }
    if (!$conditions) {
        return [];
    }
    $table = popcorn_core_table('credit');
    $sql = "SELECT content_type,content_id,role,character_name,job FROM $table WHERE person_id=%d AND (" . implode(' OR ', $conditions) . ') ORDER BY sort_order';
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values));
    $credits = [];
    foreach ($rows as $row) {
        $credits[$row->content_type . ':' . $row->content_id][] = $row;
    }
    return $credits;
}

function popcorn_core_collection_items($type, $collection_id, $exclude_id = 0, $limit = 12) {
    if (!in_array($type, ['movie', 'series'], true) || !$collection_id) {
        return [null, []];
    }
    global $wpdb;
    $collections = popcorn_core_table('collection');
    $content = popcorn_core_table($type);
    $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $collections WHERE id=%d AND content_type=%s", $collection_id, $type));
    if (!$name) {
        return [null, []];
    }
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT i.* FROM $content i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish'
         LEFT JOIN $content current_item ON current_item.id=%d AND current_item.collection_id=i.collection_id
         WHERE i.collection_id=%d AND i.id<>%d
         ORDER BY CASE WHEN current_item.id IS NOT NULL AND (
             COALESCE(i.release_date,'') > COALESCE(current_item.release_date,'') OR
             (COALESCE(i.release_date,'') = COALESCE(current_item.release_date,'') AND i.id > current_item.id)
         ) THEN 1 ELSE 0 END, COALESCE(i.release_date,'') DESC, i.id DESC LIMIT %d",
        absint($exclude_id), $collection_id, absint($exclude_id), min(24, max(1, absint($limit)))
    ));
    foreach ($items as $item) {
        $item->popcorn_type = $type;
    }
    return [$name, $items];
}

function popcorn_core_search_people($search, $limit = 12) {
    global $wpdb;
    $table = popcorn_core_table('person');
    $like = '%' . $wpdb->esc_like($search) . '%';
    return $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish' WHERE i.name LIKE %s OR i.persian_name LIKE %s OR p.post_title LIKE %s OR p.post_content LIKE %s ORDER BY i.popularity DESC LIMIT %d", $like, $like, $like, $like, min(50, max(1, absint($limit)))));
}
