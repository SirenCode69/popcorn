<?php
defined('ABSPATH') || exit;

function popcorn_core_protected_link_enabled() {
    return (bool) popcorn_core_protected_download_options()['enabled'];
}

function popcorn_core_protected_link_auth_required() {
    return function_exists('popcorn_auth_enabled') && popcorn_auth_enabled();
}

function popcorn_core_protected_link_signature($link, $post_id, $variant, $expires, $user_id) {
    $mode = 'download';
    $payload = implode('|', [absint($post_id), absint($link['id']), $mode, $variant, absint($expires), absint($user_id), hash('sha256', (string) $link['url'])]);
    return hash_hmac('sha256', $payload, wp_salt('auth'));
}

function popcorn_core_protected_link_url($link, $post_id, $variant = 'primary') {
    if (!popcorn_core_protected_link_enabled() || (popcorn_core_protected_link_auth_required() && !is_user_logged_in()) || !in_array($variant, ['primary', 'fallback'], true)) {
        return '';
    }
    $mode = 'download';
    $options = popcorn_core_protected_download_options();
    $expires = time() + $options[$mode . '_minutes'] * MINUTE_IN_SECONDS;
    $user_id = popcorn_core_protected_link_auth_required() ? get_current_user_id() : 0;
    return add_query_arg([
        'action' => 'popcorn_protected_link',
        'post' => absint($post_id),
        'id' => absint($link['id']),
        'mode' => $mode,
        'variant' => $variant,
        'expires' => $expires,
        'sig' => popcorn_core_protected_link_signature($link, $post_id, $variant, $expires, $user_id),
    ], admin_url('admin-post.php'));
}

function popcorn_core_protected_link_resolve($input) {
    if (!popcorn_core_protected_link_enabled()) {
        return new WP_Error('disabled', 'محافظت از لینک‌ها غیرفعال است.');
    }
    $post_id = absint($input['post'] ?? 0);
    $link_id = absint($input['id'] ?? 0);
    $mode = isset($input['mode']) && is_string($input['mode']) ? sanitize_key($input['mode']) : '';
    $variant = isset($input['variant']) && is_string($input['variant']) ? sanitize_key($input['variant']) : '';
    $expires = absint($input['expires'] ?? 0);
    $signature = isset($input['sig']) && is_string($input['sig']) ? strtolower(sanitize_text_field($input['sig'])) : '';
    if (!$post_id || !$link_id || $mode !== 'download' || !in_array($variant, ['primary', 'fallback'], true) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
        return new WP_Error('invalid', 'لینک معتبر نیست.');
    }
    $options = popcorn_core_protected_download_options();
    if ($expires <= time() || $expires > time() + $options[$mode . '_minutes'] * MINUTE_IN_SECONDS) {
        return new WP_Error('expired', 'اعتبار لینک تمام شده است. صفحهٔ اثر را دوباره باز کنید.');
    }
    if (popcorn_core_protected_link_auth_required() && !is_user_logged_in()) {
        return new WP_Error('login', 'برای دریافت این لینک وارد حساب کاربری شوید.');
    }
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish' || !in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        return new WP_Error('unavailable', 'این اثر در دسترس نیست.');
    }
    global $wpdb;
    $links = popcorn_core_table('download_link');
    $groups = popcorn_core_table('download_group');
    $link = $wpdb->get_row($wpdb->prepare("SELECT l.* FROM $links l INNER JOIN $groups g ON g.id=l.group_id WHERE l.id=%d AND g.post_id=%d AND g.content_type=%s AND l.is_active=1 LIMIT 1", $link_id, $post_id, $post->post_type === 'popcorn_series' ? 'series' : 'movie'), ARRAY_A);
    if (!$link) {
        return new WP_Error('unavailable', 'این لینک در دسترس نیست.');
    }
    $user_id = popcorn_core_protected_link_auth_required() ? get_current_user_id() : 0;
    if (!hash_equals(popcorn_core_protected_link_signature($link, $post_id, $variant, $expires, $user_id), $signature)) {
        return new WP_Error('invalid', 'امضای لینک معتبر نیست.');
    }
    if (!apply_filters('popcorn_download_links_visible', true, $post_id)) {
        return new WP_Error('access', 'برای دسترسی به این لینک اشتراک فعال لازم است.');
    }
    $candidates = popcorn_core_url_candidates($link['url']);
    if (empty($candidates[$variant])) {
        return new WP_Error('unavailable', 'نشانی لینک در دسترس نیست.');
    }
    return $candidates[$variant];
}

function popcorn_core_protected_link_redirect() {
    $input = wp_unslash($_GET);
    $target = popcorn_core_protected_link_resolve($input);
    if (is_wp_error($target)) {
        if ($target->get_error_code() === 'login') {
            $post_id = absint($input['post'] ?? 0);
            $return_url = $post_id && get_post_status($post_id) === 'publish' ? get_permalink($post_id) . '#popcorn-downloads' : home_url('/');
            $login_url = popcorn_core_public_login_url($return_url);
            wp_safe_redirect($login_url ?: home_url('/'), 303);
            exit;
        }
        wp_die(esc_html($target->get_error_message()), 'لینک دانلود', ['response' => $target->get_error_code() === 'expired' ? 410 : 403]);
    }
    nocache_headers();
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    wp_redirect($target, 302, 'Popcorn');
    exit;
}
add_action('admin_post_popcorn_protected_link', 'popcorn_core_protected_link_redirect');
add_action('admin_post_nopriv_popcorn_protected_link', 'popcorn_core_protected_link_redirect');

add_action('template_redirect', function () {
    if (popcorn_core_protected_link_enabled() && is_singular(['popcorn_movie', 'popcorn_series'])) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
    }
}, 1);
