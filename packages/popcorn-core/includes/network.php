<?php
defined('ABSPATH') || exit;

function popcorn_core_cache_plugin_active() {
    $active = (defined('WP_CACHE') && WP_CACHE && is_file(WP_CONTENT_DIR . '/advanced-cache.php')) || defined('LSCWP_V');
    return (bool) apply_filters('popcorn_core_cache_plugin_active', $active);
}

add_action('template_redirect', function () {
    if (!popcorn_core_cache_plugin_active()) {
        nocache_headers();
    }
});

function popcorn_core_url_candidates($input) {
    $url = is_string($input) ? trim($input) : '';
    if ($url === '' || strlen($url) > 2048 || !preg_match('~^https?://~i', $url)) {
        return [];
    }
    $url = esc_url_raw($url, ['http', 'https']);
    $parts = wp_parse_url($url);
    if (!$url || !is_array($parts) || empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass'])) {
        return [];
    }
    $site = wp_parse_url(home_url('/'));
    if (is_array($site) && strcasecmp($parts['host'], $site['host'] ?? '') === 0 && (!isset($site['port']) || (int) ($parts['port'] ?? 0) === (int) $site['port'])) {
        $scheme = ($site['scheme'] ?? '') === 'https' ? 'https://' : 'http://';
        return ['primary' => preg_replace('~^https?://~i', $scheme, $url), 'fallback' => ''];
    }
    return ['primary' => preg_replace('~^https?://~i', 'https://', $url), 'fallback' => empty($parts['query']) ? preg_replace('~^https?://~i', 'http://', $url) : ''];
}

function popcorn_core_image_fallback_attr($url) {
    $urls = popcorn_core_url_candidates($url);
    if (empty($urls['fallback']) || $urls['primary'] === $urls['fallback']) {
        return '';
    }
    popcorn_core_enqueue_image_fallback();
    $fallback = $urls['fallback'];
    $fallback = add_query_arg(['action' => 'popcorn_image_http_fallback', 'url' => $fallback, 'sig' => hash_hmac('sha256', $fallback, wp_salt('auth'))], admin_url('admin-ajax.php'));
    return ' data-http-fallback="' . esc_attr($fallback) . '"';
}

function popcorn_core_enqueue_image_fallback() {
    $base = dirname(__DIR__);
    wp_enqueue_script('popcorn-image-fallback', plugin_dir_url($base . '/popcorn-core.php') . 'assets/js/image-fallback.js', [], filemtime($base . '/assets/js/image-fallback.js'), true);
}

function popcorn_core_image_http_fallback() {
    $url = isset($_GET['url']) && is_string($_GET['url']) ? trim(wp_unslash($_GET['url'])) : '';
    $signature = isset($_GET['sig']) && is_string($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';
    $nonce = isset($_GET['nonce']) && is_string($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    $urls = popcorn_core_url_candidates($url);
    $authorized = hash_equals(hash_hmac('sha256', $url, wp_salt('auth')), $signature) || (current_user_can('edit_posts') && wp_verify_nonce($nonce, 'popcorn_image_preview'));
    if (!$urls || empty($urls['fallback']) || $urls['fallback'] !== $url || !$authorized) {
        status_header(403);
        exit;
    }
    $request = ['timeout' => 10, 'redirection' => 0, 'limit_response_size' => 5 * MB_IN_BYTES];
    $response = wp_safe_remote_get($urls['primary'], $request);
    if (is_wp_error($response)) {
        $response = wp_safe_remote_get($url, $request);
    }
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        status_header(404);
        exit;
    }
    $mime = strtolower(trim(explode(';', (string) wp_remote_retrieve_header($response, 'content-type'))[0]));
    $body = wp_remote_retrieve_body($response);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || $body === '' || strlen($body) >= 5 * MB_IN_BYTES) {
        status_header(415);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($body));
    header('X-Content-Type-Options: nosniff');
    if (!popcorn_core_cache_plugin_active()) {
        nocache_headers();
    }
    echo $body;
    exit;
}
add_action('wp_ajax_popcorn_image_http_fallback', 'popcorn_core_image_http_fallback');
add_action('wp_ajax_nopriv_popcorn_image_http_fallback', 'popcorn_core_image_http_fallback');
