<?php
defined('ABSPATH') || exit;

function popcorn_core_playback_enabled() {
    $options = get_option('popcorn_playback_options', ['enabled' => 1]);
    return is_array($options) && !empty($options['enabled']);
}

function popcorn_core_register_playback_type() {
    $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="3" fill="#fff"/><path d="m10 8 6 4-6 4V8Z" fill="#000" stroke="#000"/></svg>';
    register_post_type('popcorn_playback', [
        'labels' => ['name' => 'پخش آنلاین', 'singular_name' => 'پخش آنلاین', 'menu_name' => 'پخش آنلاین', 'all_items' => 'همهٔ پخش‌ها', 'add_new' => 'افزودن', 'add_new_item' => 'افزودن پخش آنلاین', 'edit_item' => 'ویرایش پخش آنلاین', 'view_item' => 'مشاهدهٔ پخش', 'search_items' => 'جست‌وجوی پخش‌ها', 'not_found' => 'پخشی پیدا نشد'],
        'public' => true,
        'exclude_from_search' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'author', 'revisions'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'watch', 'with_front' => false],
        'menu_position' => 29,
        'menu_icon' => 'data:image/svg+xml;base64,' . base64_encode($icon),
    ]);
}
add_action('init', 'popcorn_core_register_playback_type', 5);

function popcorn_core_playback_slug($post_id = 0) {
    global $wpdb;
    $post_id = absint($post_id);
    $current = $post_id ? (string) $wpdb->get_var($wpdb->prepare("SELECT post_name FROM {$wpdb->posts} WHERE ID=%d AND post_type='popcorn_playback'", $post_id)) : '';
    $exists = static function ($slug) use ($wpdb, $post_id) {
        return $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('popcorn_playback','attachment') AND post_name IN (%s,%s) AND ID<>%d LIMIT 1", $slug, $slug . '__trashed', $post_id));
    };
    if (preg_match('/^([1-9][0-9]{6})(?:__trashed)?$/D', $current, $match) && !$exists($match[1])) {
        return $match[1];
    }
    do {
        $slug = (string) wp_rand(1000000, 9999999);
    } while ($exists($slug));
    return $slug;
}

add_filter('wp_insert_post_data', function ($data, $postarr) {
    if ($data['post_type'] === 'popcorn_playback') {
        $data['post_name'] = popcorn_core_playback_slug($postarr['ID'] ?? 0);
    }
    return $data;
}, PHP_INT_MAX, 2);

add_filter('pre_wp_unique_post_slug', function ($override, $slug, $post_id, $status, $type) {
    return $type === 'popcorn_playback' && $post_id ? popcorn_core_playback_slug($post_id) : $override;
}, PHP_INT_MAX, 5);

function popcorn_core_migrate_playback_slugs() {
    if (get_option('popcorn_playback_slug_version') === '1') {
        return;
    }
    global $wpdb;
    $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='popcorn_playback' AND post_name NOT REGEXP '^[1-9][0-9]{6}$' ORDER BY ID LIMIT 100");
    foreach ($ids as $post_id) {
        $saved = wp_update_post(['ID' => (int) $post_id, 'post_name' => ''], true);
        if (is_wp_error($saved)) {
            continue;
        }
        $row = popcorn_core_get_playback($post_id);
        popcorn_core_purge_playback_content((int) ($row->content_post_id ?? 0));
    }
    $remaining = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type='popcorn_playback' AND post_name NOT REGEXP '^[1-9][0-9]{6}$' LIMIT 1");
    if (!$remaining) {
        update_option('popcorn_playback_slug_version', '1', false);
    }
}
add_action('admin_init', 'popcorn_core_migrate_playback_slugs', 5);

function popcorn_core_get_playback($post_id) {
    global $wpdb;
    $table = popcorn_core_table('playback');
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE post_id=%d LIMIT 1", absint($post_id)));
}

function popcorn_core_playback_format($url) {
    $path = wp_parse_url((string) $url, PHP_URL_PATH);
    $extension = strtolower(pathinfo(rawurldecode((string) $path), PATHINFO_EXTENSION));
    return in_array($extension, ['mp4', 'mkv'], true) ? $extension : '';
}

function popcorn_core_clean_playback($input) {
    if (!is_array($input)) {
        return new WP_Error('invalid', 'اطلاعات پخش معتبر نیست.');
    }
    foreach ($input as $key => $value) {
        if (!in_array($key, ['direct_qualities', 'subtitles'], true) && !is_scalar($value)) {
            return new WP_Error('invalid', 'مقدار یکی از فیلدها معتبر نیست.');
        }
    }
    $parent_id = absint($input['content_post_id'] ?? 0);
    $parent = get_post($parent_id);
    if (!$parent || !in_array($parent->post_type, ['popcorn_movie', 'popcorn_series'], true) || in_array($parent->post_status, ['trash', 'auto-draft'], true) || !current_user_can('edit_post', $parent_id)) {
        return new WP_Error('parent', 'یک فیلم یا سریال معتبر انتخاب کنید.');
    }
    $url = trim((string) ($input['video_url'] ?? ''));
    $mp4 = trim((string) ($input['mp4_url'] ?? ''));
    if (!popcorn_core_url_candidates($url) || !popcorn_core_playback_format($url)) {
        return new WP_Error('video', 'لینک مستقیم یک فایل MP4 یا MKV را وارد کنید.');
    }
    if ($mp4 !== '' && (!popcorn_core_url_candidates($mp4) || popcorn_core_playback_format($mp4) !== 'mp4')) {
        return new WP_Error('fallback', 'نسخهٔ جایگزین باید لینک مستقیم فایل MP4 باشد.');
    }
    $qualities = popcorn_core_clean_playback_rows($input['direct_qualities'] ?? [], 'quality');
    $subtitles = popcorn_core_clean_playback_rows($input['subtitles'] ?? [], 'subtitle');
    if (is_wp_error($qualities) || is_wp_error($subtitles)) {
        return is_wp_error($qualities) ? $qualities : $subtitles;
    }
    $quality = sanitize_text_field((string) ($input['quality'] ?? ''));
    return [
        'content_post_id' => $parent_id,
        'season_number' => $parent->post_type === 'popcorn_series' ? min(1000, absint($input['season_number'] ?? 0)) : 0,
        'episode_number' => $parent->post_type === 'popcorn_series' ? min(10000, absint($input['episode_number'] ?? 0)) : 0,
        'quality' => function_exists('mb_substr') ? mb_substr($quality, 0, 80) : substr($quality, 0, 80),
        'video_url' => esc_url_raw($url, ['http', 'https']),
        'mp4_url' => $mp4 !== $url ? esc_url_raw($mp4, ['http', 'https']) : '',
        'direct_qualities' => wp_json_encode($qualities),
        'subtitles' => wp_json_encode($subtitles),
        'is_active' => empty($input['is_active']) ? 0 : 1,
    ];
}

function popcorn_core_playback_rows($row, $field) {
    $rows = json_decode((string) ($row->$field ?? ''), true);
    return is_array($rows) ? array_values($rows) : [];
}

function popcorn_core_clean_playback_rows($input, $type) {
    if (!is_array($input) || count($input) > ($type === 'quality' ? 20 : 10)) {
        return new WP_Error('rows', 'تعداد یا ساختار ردیف‌های پخش معتبر نیست.');
    }
    $rows = [];
    foreach ($input as $item) {
        if (!is_array($item) || array_filter($item, static function ($value) { return !is_scalar($value); })) {
            return new WP_Error('rows', 'یکی از ردیف‌ها معتبر نیست.');
        }
        $url = trim((string) ($item['url'] ?? ''));
        $label = sanitize_text_field((string) ($item['label'] ?? ''));
        if ($url === '' && $label === '') {
            continue;
        }
        $extension = strtolower(pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if ($label === '' || !popcorn_core_url_candidates($url) || ($type === 'quality' ? !popcorn_core_playback_format($url) : !in_array($extension, ['srt', 'vtt'], true))) {
            return new WP_Error('rows', $type === 'quality' ? 'برای هر کیفیت، عنوان و لینک مستقیم MP4 یا MKV وارد کنید.' : 'برای هر زیرنویس، عنوان و لینک SRT یا VTT وارد کنید.');
        }
        $item = ['label' => function_exists('mb_substr') ? mb_substr($label, 0, 80) : substr($label, 0, 80), 'url' => esc_url_raw($url, ['http', 'https'])] + ($type === 'subtitle' ? ['language' => in_array($item['language'] ?? '', ['fa', 'en'], true) ? $item['language'] : 'fa'] : []);
        $rows[] = $item;
    }
    return $rows;
}

function popcorn_core_playback_available($playback) {
    return $playback && popcorn_core_playback_enabled() && !empty($playback->is_active)
        && get_post_type($playback->post_id) === 'popcorn_playback' && get_post_status($playback->post_id) === 'publish'
        && in_array(get_post_type($playback->content_post_id), ['popcorn_movie', 'popcorn_series'], true) && get_post_status($playback->content_post_id) === 'publish';
}

function popcorn_core_content_playbacks($post_id) {
    static $cache = [];
    $post_id = absint($post_id);
    if (!popcorn_core_playback_enabled() || get_post_status($post_id) !== 'publish') {
        return [];
    }
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }
    global $wpdb;
    $table = popcorn_core_table('playback');
    $rows = $wpdb->get_results($wpdb->prepare("SELECT v.id,v.post_id,v.content_post_id,v.season_number,v.episode_number,v.quality,p.post_title FROM $table v INNER JOIN {$wpdb->posts} p ON p.ID=v.post_id AND p.post_type='popcorn_playback' AND p.post_status='publish' WHERE v.content_post_id=%d AND v.is_active=1 ORDER BY v.season_number,v.episode_number,v.id", $post_id));
    if ($rows) {
        get_posts(['post_type' => 'popcorn_playback', 'post__in' => array_map(static function ($row) { return (int) $row->post_id; }, $rows), 'posts_per_page' => count($rows), 'update_post_meta_cache' => false, 'update_post_term_cache' => false]);
    }
    return $cache[$post_id] = $rows;
}

function popcorn_core_playback_access($playback) {
    if (!popcorn_core_playback_available($playback)) {
        return new WP_Error('unavailable', 'این پخش در دسترس نیست.');
    }
    if (popcorn_core_protected_link_enabled() && popcorn_core_protected_link_auth_required() && !is_user_logged_in()) {
        return new WP_Error('login', 'برای تماشای این اثر وارد حساب کاربری شوید.');
    }
    if (!apply_filters('popcorn_download_links_visible', true, $playback->content_post_id)) {
        return new WP_Error('subscription', 'برای تماشای این اثر اشتراک فعال لازم است.');
    }
    return true;
}

function popcorn_core_purge_playback_content($post_id) {
    if (!$post_id) { return; }
    clean_post_cache($post_id);
    do_action('litespeed_purge_post', $post_id);
    if (function_exists('rocket_clean_post')) { rocket_clean_post($post_id); }
}

add_action('before_delete_post', function ($post_id, $post) {
    global $wpdb;
    $table = popcorn_core_table('playback');
    if ($post->post_type === 'popcorn_playback') {
        $row = popcorn_core_get_playback($post_id);
        popcorn_core_purge_playback_content((int) ($row->content_post_id ?? 0));
        $wpdb->delete($table, ['post_id' => $post_id], ['%d']);
        $wpdb->delete(popcorn_core_table('watch_progress'), ['playback_post_id' => $post_id], ['%d']);
    } elseif (in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        $wpdb->update($table, ['content_post_id' => 0], ['content_post_id' => $post_id], ['%d'], ['%d']);
    }
}, 9, 2);
