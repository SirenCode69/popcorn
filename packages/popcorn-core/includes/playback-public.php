<?php
defined('ABSPATH') || exit;

function popcorn_core_playback_raw_source($row, $source) {
    if ($source === 'main' || $source === 'mp4') {
        return $source === 'main' ? $row->video_url : $row->mp4_url;
    }
    if (!preg_match('/^([qs])([0-9]{1,2})$/', $source, $match)) {
        return '';
    }
    $rows = popcorn_core_playback_rows($row, $match[1] === 'q' ? 'direct_qualities' : 'subtitles');
    return $rows[(int) $match[2]]['url'] ?? '';
}

function popcorn_core_playback_signature($row, $source, $variant, $expires, $user_id) {
    $url = popcorn_core_playback_raw_source($row, $source);
    $payload = implode('|', [absint($row->post_id), absint($row->content_post_id), $source, $variant, absint($expires), absint($user_id), hash('sha256', (string) $url)]);
    return hash_hmac('sha256', $payload, wp_salt('auth'));
}

function popcorn_core_playback_source_url($row, $source = 'main', $variant = 'primary') {
    if (is_wp_error(popcorn_core_playback_access($row)) || !popcorn_core_playback_raw_source($row, $source) || !in_array($variant, ['primary', 'fallback'], true)) {
        return '';
    }
    $urls = popcorn_core_url_candidates(popcorn_core_playback_raw_source($row, $source));
    if (empty($urls[$variant])) {
        return '';
    }
    if (!popcorn_core_protected_link_enabled() && $variant === 'primary' && strpos($source, 's') !== 0) {
        return $urls['primary'];
    }
    $expires = time() + popcorn_core_protected_download_options()['watch_minutes'] * MINUTE_IN_SECONDS;
    $user_id = popcorn_core_protected_link_enabled() && popcorn_core_protected_link_auth_required() ? get_current_user_id() : 0;
    return add_query_arg(['action' => strpos($source, 's') === 0 ? 'popcorn_playback_subtitle' : 'popcorn_playback_media', 'playback' => absint($row->post_id), 'source' => $source, 'variant' => $variant, 'expires' => $expires, 'sig' => popcorn_core_playback_signature($row, $source, $variant, $expires, $user_id)], admin_url('admin-post.php'));
}

function popcorn_core_playback_media_resolve($input) {
    $post_id = absint($input['playback'] ?? 0);
    $source = isset($input['source']) && is_string($input['source']) ? sanitize_key($input['source']) : '';
    $variant = isset($input['variant']) && is_string($input['variant']) ? sanitize_key($input['variant']) : '';
    $expires = absint($input['expires'] ?? 0);
    $sig = isset($input['sig']) && is_string($input['sig']) ? sanitize_text_field($input['sig']) : '';
    if (!$post_id || !preg_match('/^(main|mp4|[qs][0-9]{1,2})$/', $source) || !in_array($variant, ['primary', 'fallback'], true) || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
        return new WP_Error('invalid', 'لینک پخش معتبر نیست.');
    }
    $row = popcorn_core_get_playback($post_id);
    $access = popcorn_core_playback_access($row);
    if (is_wp_error($access)) {
        return $access;
    }
    $minutes = popcorn_core_protected_download_options()['watch_minutes'];
    if ($expires <= time() || $expires > time() + $minutes * MINUTE_IN_SECONDS) {
        return new WP_Error('expired', 'اعتبار لینک پخش تمام شده است. صفحه را دوباره باز کنید.');
    }
    $user_id = popcorn_core_protected_link_enabled() && popcorn_core_protected_link_auth_required() ? get_current_user_id() : 0;
    if (!hash_equals(popcorn_core_playback_signature($row, $source, $variant, $expires, $user_id), $sig)) {
        return new WP_Error('invalid', 'امضای لینک پخش معتبر نیست.');
    }
    $urls = popcorn_core_url_candidates(popcorn_core_playback_raw_source($row, $source));
    if (empty($urls[$variant])) {
        return new WP_Error('unavailable', 'فایل پخش در دسترس نیست.');
    }
    if ($variant === 'fallback') {
        $response = wp_safe_remote_head($urls['primary'], ['timeout' => 8, 'redirection' => 3]);
        if (!is_wp_error($response) || $response->get_error_code() !== 'http_request_failed') {
            return new WP_Error('https', 'مسیر HTTP فقط هنگام برقرار نشدن اتصال HTTPS قابل استفاده است.');
        }
    }
    return $urls[$variant];
}

function popcorn_core_playback_media_redirect() {
    $target = popcorn_core_playback_media_resolve(wp_unslash($_GET));
    nocache_headers();
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    if (is_wp_error($target)) {
        wp_die(esc_html($target->get_error_message()), 'پخش آنلاین', ['response' => $target->get_error_code() === 'expired' ? 410 : 403]);
    }
    if (strpos(sanitize_key($_GET['source'] ?? ''), 's') === 0) {
        wp_die('منبع ویدیو معتبر نیست.', '', ['response' => 403]);
    }
    wp_redirect($target, 302, 'Popcorn');
    exit;
}
add_action('admin_post_popcorn_playback_media', 'popcorn_core_playback_media_redirect');
add_action('admin_post_nopriv_popcorn_playback_media', 'popcorn_core_playback_media_redirect');

function popcorn_core_playback_sources($row) {
    if (is_wp_error(popcorn_core_playback_access($row))) {
        return [];
    }
    $items = ['main' => ['url' => $row->video_url, 'label' => $row->quality ?: 'کیفیت اصلی'], 'mp4' => ['url' => $row->mp4_url, 'label' => ($row->quality ?: 'اصلی') . ' · MP4 جایگزین']];
    foreach (popcorn_core_playback_rows($row, 'direct_qualities') as $index => $item) {
        $items['q' . $index] = $item;
    }
    $sources = [];
    foreach ($items as $source => $item) {
        $src = popcorn_core_playback_source_url($row, $source);
        if ($src) {
            $sources[] = ['url' => $src, 'httpFallback' => popcorn_core_playback_source_url($row, $source, 'fallback'), 'format' => popcorn_core_playback_format($item['url']), 'label' => $item['label']];
        }
    }
    return $sources;
}

function popcorn_core_playback_subtitles($row) {
    $tracks = [];
    foreach (popcorn_core_playback_rows($row, 'subtitles') as $index => $item) {
        $url = popcorn_core_playback_source_url($row, 's' . $index);
        if ($url) {
            $tracks[] = ['url' => $url, 'label' => $item['label'], 'language' => $item['language']];
        }
    }
    return $tracks;
}

function popcorn_core_playback_subtitle_response() {
    nocache_headers();
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    $input = wp_unslash($_GET);
    if (!isset($input['source']) || !is_string($input['source']) || !preg_match('/^s[0-9]{1,2}$/', $input['source'])) {
        wp_die('زیرنویس معتبر نیست.', '', ['response' => 403]);
    }
    $target = popcorn_core_playback_media_resolve($input);
    if (is_wp_error($target)) {
        wp_die(esc_html($target->get_error_message()), '', ['response' => 403]);
    }
    $response = wp_safe_remote_get($target, ['timeout' => 10, 'redirection' => 0, 'limit_response_size' => 2 * MB_IN_BYTES]);
    if (is_wp_error($response) && $response->get_error_code() === 'http_request_failed' && ($input['variant'] ?? '') === 'primary') {
        $row = popcorn_core_get_playback(absint($input['playback']));
        $urls = popcorn_core_url_candidates(popcorn_core_playback_raw_source($row, $input['source']));
        if (!empty($urls['fallback'])) {
            $response = wp_safe_remote_get($urls['fallback'], ['timeout' => 10, 'redirection' => 0, 'limit_response_size' => 2 * MB_IN_BYTES]);
        }
    }
    $body = is_wp_error($response) ? '' : wp_remote_retrieve_body($response);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 || $body === '' || strlen($body) >= 2 * MB_IN_BYTES || (!preg_match('/^\s*WEBVTT/', $body) && !preg_match('/[0-9]{2}:[0-9]{2}:[0-9]{2}[,.][0-9]{3}\s*-->/', $body))) {
        wp_die('دریافت زیرنویس انجام نشد.', '', ['response' => 502]);
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo $body;
    exit;
}
add_action('admin_post_popcorn_playback_subtitle', 'popcorn_core_playback_subtitle_response');
add_action('admin_post_nopriv_popcorn_playback_subtitle', 'popcorn_core_playback_subtitle_response');

function popcorn_core_playback_navigation($row) {
    if (get_post_type($row->content_post_id) !== 'popcorn_series') {
        return ['episodes' => [], 'previous' => '', 'next' => ''];
    }
    $rows = popcorn_core_content_playbacks($row->content_post_id);
    $episodes = [];
    $current = 0;
    foreach ($rows as $index => $item) {
        if ((int) $item->post_id === (int) $row->post_id) {
            $current = $index;
        }
        $episodes[] = ['id' => (int) $item->post_id, 'season' => (int) $item->season_number, 'episode' => (int) $item->episode_number, 'label' => $item->post_title, 'url' => get_permalink($item->post_id)];
    }
    return ['episodes' => $episodes, 'previous' => $episodes[$current - 1]['url'] ?? '', 'next' => $episodes[$current + 1]['url'] ?? ''];
}

function popcorn_core_playback_episode_label($row) {
    return get_post_type($row->content_post_id) === 'popcorn_series' ? 'فصل ' . number_format_i18n($row->season_number) . ' · قسمت ' . number_format_i18n($row->episode_number) : '';
}

function popcorn_core_playback_start_url($post_id) {
    $rows = popcorn_core_content_playbacks($post_id);
    if (!$rows) {
        return '';
    }
    $target = $rows[0]->post_id;
    if (is_user_logged_in()) {
        global $wpdb;
        $progress = popcorn_core_table('watch_progress');
        $ids = array_map(static function ($row) { return absint($row->post_id); }, $rows);
        $recent = $wpdb->get_var($wpdb->prepare("SELECT playback_post_id FROM $progress WHERE user_id=%d AND playback_post_id IN (" . implode(',', $ids) . ") AND position_seconds>=5 AND position_seconds<duration_seconds*0.95 ORDER BY updated_at DESC LIMIT 1", get_current_user_id()));
        if ($recent) {
            $target = (int) $recent;
        }
    }
    return get_permalink($target);
}

function popcorn_core_render_playbacks($post_id) {
    $url = popcorn_core_playback_start_url($post_id);
    if (!$url) {
        return '';
    }
    return '<section id="popcorn-playbacks" class="pc-section pc-playbacks"><a class="pc-button" href="' . esc_url($url) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/></svg> پخش آنلاین</a></section>';
}
add_shortcode('popcorn_playbacks', function ($atts) {
    $atts = shortcode_atts(['post_id' => 0], $atts, 'popcorn_playbacks');
    return popcorn_core_render_playbacks(absint($atts['post_id']) ?: get_the_ID());
});

add_action('template_redirect', function () {
    if (!is_singular('popcorn_playback')) {
        return;
    }
    $row = popcorn_core_get_playback(get_queried_object_id());
    if (!popcorn_core_playback_available($row)) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        return;
    }
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    nocache_headers();
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
    remove_action('wp_print_styles', 'print_emoji_styles');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
}, 1);

add_filter('wp_robots', function ($robots) {
    if (is_singular('popcorn_playback')) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
        unset($robots['index'], $robots['follow']);
    }
    return $robots;
});
function popcorn_core_playback_seo_robots($robots) {
    if (is_singular('popcorn_playback')) {
        $robots['index'] = 'noindex';
        $robots['follow'] = 'nofollow';
    }
    return $robots;
}
add_filter('wpseo_robots_array', 'popcorn_core_playback_seo_robots');
add_filter('rank_math/frontend/robots', 'popcorn_core_playback_seo_robots');

add_action('wp_enqueue_scripts', function () {
    $watch = is_singular('popcorn_playback');
    $related = is_singular(['popcorn_movie', 'popcorn_series']) && popcorn_core_content_playbacks(get_queried_object_id());
    if (!popcorn_core_playback_enabled() || (!$watch && !$related)) {
        return;
    }
    $base = dirname(__DIR__);
    $url = plugin_dir_url($base . '/popcorn-core.php');
    wp_enqueue_style('popcorn-playback', $url . 'assets/css/playback.css', [], filemtime($base . '/assets/css/playback.css'));
    if ($watch && !is_wp_error(popcorn_core_playback_access(popcorn_core_get_playback(get_queried_object_id())))) {
        wp_enqueue_script('popcorn-playback', $url . 'assets/js/playback.js', [], filemtime($base . '/assets/js/playback.js'), true);
    }
});

add_filter('show_admin_bar', function ($show) { return is_singular('popcorn_playback') ? false : $show; });
