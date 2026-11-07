<?php
defined('ABSPATH') || exit;

function popcorn_title_next_episode_time($date, $today = null) {
    $scheduled = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date, wp_timezone());
    if (!$scheduled || $scheduled->format('Y-m-d') !== $date) { return ''; }
    $today = ($today instanceof DateTimeImmutable ? $today : current_datetime())->setTime(0, 0);
    $days = (int) $today->diff($scheduled)->format('%r%a');
    if ($days === 0) { return 'امروز'; }
    if ($days === 1) { return 'فردا'; }
    return number_format_i18n(abs($days)) . ' روز ' . ($days > 0 ? 'بعد' : 'پیش');
}

add_action('rest_api_init', function () {
    register_rest_route('popcorn/v1', '/title-gallery/(?P<post_id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => function ($request) {
            $post = get_post(absint($request['post_id']));
            return $post && $post->post_status === 'publish' && in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true) && !post_password_required($post) ? true : new WP_Error('gallery_unavailable', 'گالری در دسترس نیست.', ['status' => 404]);
        },
        'callback' => 'popcorn_title_gallery_rest',
    ]);
});

function popcorn_title_gallery_rest($request) {
    $post_id = absint($request['post_id']);
    $type = get_post_type($post_id) === 'popcorn_series' ? 'series' : 'movie';
    $item = popcorn_core_get_by_post($type, $post_id);
    if (!$item) { return new WP_Error('gallery_unavailable', 'گالری در دسترس نیست.', ['status' => 404]); }
    $gallery = json_decode((string) $item->gallery, true);
    $images = [];
    $alt = popcorn_item_seo_image_alt($item) . ' - تصویر گالری';
    foreach (is_array($gallery) ? $gallery : [] as $image) {
        if (!is_array($image)) { continue; }
        $html = popcorn_content_image(absint($image['attachment_id'] ?? 0), $image['path'] ?? '', 'full', ['alt' => $alt, 'loading' => 'eager']);
        if ($html) { $images[] = $html; }
    }
    $response = new WP_REST_Response(['images' => $images]);
    foreach (wp_get_nocache_headers() as $name => $value) {
        if ($value !== false) { $response->header($name, $value); }
    }
    return $response;
}

function popcorn_title_trailer($url) {
    $urls = $url && function_exists('popcorn_core_url_candidates') ? popcorn_core_url_candidates($url) : [];
    if (!$urls) {
        return [];
    }
    $parts = wp_parse_url($urls['primary']);
    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';
    $id = '';
    if ($host === 'youtu.be') {
        $id = trim($path, '/');
    } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
        parse_str($parts['query'] ?? '', $query);
        $id = preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]+)~', $path, $matches) ? $matches[1] : ($query['v'] ?? '');
    }
    if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
        return ['kind' => 'embed', 'src' => 'https://www.youtube-nocookie.com/embed/' . $id, 'url' => $urls['primary']];
    }
    return ['kind' => 'video', 'src' => $urls['primary'], 'url' => $urls['primary']];
}

function popcorn_title_status_variant($status) {
    $status = strtolower(trim((string) $status));
    $groups = [
        'canceled' => ['canceled', 'cancelled', 'لغوشده', 'لغو شده', 'کنسل شده', 'کنسل‌شده'],
        'airing' => ['returning series', 'در حال پخش'],
        'released' => ['released', 'منتشرشده', 'منتشر شده'],
        'ended' => ['ended', 'پایان‌یافته', 'پایان یافته'],
        'production' => ['in production', 'در حال تولید'],
        'post-production' => ['post production', 'پس‌تولید', 'پس تولید'],
        'planned' => ['planned', 'برنامه‌ریزی‌شده', 'برنامه ریزی شده'],
        'rumored' => ['rumored', 'در حد شایعه'],
        'pilot' => ['pilot', 'قسمت آزمایشی'],
    ];
    foreach ($groups as $variant => $values) {
        if (in_array($status, $values, true)) { return $variant; }
    }
    return 'unknown';
}

function popcorn_title_trailer_player($url) {
    $type = wp_check_filetype($url, wp_get_mime_types());
    $shortcode_url = in_array(strtolower((string) $type['ext']), wp_get_video_extensions(), true) ? $url : home_url('/popcorn-trailer.mp4');
    $html = wp_video_shortcode(['src' => $shortcode_url, 'preload' => 'none', 'width' => 960, 'height' => 540]);
    $processor = new WP_HTML_Tag_Processor($html);
    while ($processor->next_tag()) {
        if ($processor->get_tag() === 'VIDEO') { $processor->set_attribute('playsinline', true); }
        if ($processor->get_tag() === 'SOURCE') {
            $processor->set_attribute('src', $url);
            if ($type['type']) { $processor->set_attribute('type', $type['type']); }
            else { $processor->remove_attribute('type'); }
        }
        if ($processor->get_tag() === 'A') { $processor->set_attribute('href', $url); }
    }
    return $processor->get_updated_html();
}
