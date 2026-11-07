<?php
defined('ABSPATH') || exit;

function popcorn_public_auth_enabled() {
    return function_exists('popcorn_auth_enabled') && popcorn_auth_enabled();
}

function popcorn_public_page_unavailable($page_id) {
    $post = get_post($page_id);
    if (!$post || $post->post_type !== 'page') {
        return false;
    }
    $auth = (array) get_option('popcorn_auth_options', []);
    $subscription = (array) get_option('popcorn_subscription_options', []);
    $auth_page = in_array((int) $page_id, array_map('absint', [$auth['login_page_id'] ?? 0, $auth['register_page_id'] ?? 0, $auth['account_page_id'] ?? 0]), true) || preg_match('/\[(?:popcorn_auth|popcorn_register|popcorn_account)(?:\s|\])/', $post->post_content);
    $subscription_page = (int) $page_id === absint($subscription['page_id'] ?? 0) || preg_match('/\[popcorn_subscription(?:\s|\])/', $post->post_content);
    return ($auth_page && !popcorn_public_auth_enabled()) || ($subscription_page && (!function_exists('popcorn_subscription_enabled') || !popcorn_subscription_enabled()));
}

function popcorn_public_link_unavailable($url, $page_id = 0) {
    if ($page_id && popcorn_public_page_unavailable($page_id)) {
        return true;
    }
    $parts = wp_parse_url(html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8'));
    if (!$parts || (!empty($parts['host']) && (strtolower($parts['host']) !== strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST)) || ($parts['port'] ?? null) !== wp_parse_url(home_url('/'), PHP_URL_PORT)))) {
        return false;
    }
    $path = rawurldecode($parts['path'] ?? '');
    if (!popcorn_public_auth_enabled() && preg_match('~/(?:wp-login\.php|wp-admin)(?:/|$)~i', $path)) {
        return true;
    }
    $query = [];
    wp_parse_str($parts['query'] ?? '', $query);
    if (!empty($query['page_id']) && popcorn_public_page_unavailable(absint($query['page_id']))) {
        return true;
    }
    $base = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
    $relative = trim(str_starts_with($path, $base) ? substr($path, strlen($base)) : $path, '/');
    $page = $relative ? get_page_by_path($relative) : null;
    return $page ? popcorn_public_page_unavailable($page->ID) : false;
}

add_filter('wp_get_nav_menu_items', function ($items) {
    if (is_admin()) {
        return $items;
    }
    $hidden = [];
    foreach ($items as $item) {
        $page_id = $item->type === 'post_type' && $item->object === 'page' ? absint($item->object_id) : 0;
        if (popcorn_public_link_unavailable($item->url, $page_id)) {
            $hidden[] = (int) $item->ID;
        }
    }
    do {
        $count = count($hidden);
        foreach ($items as $item) {
            if (in_array((int) $item->menu_item_parent, $hidden, true) && !in_array((int) $item->ID, $hidden, true)) {
                $hidden[] = (int) $item->ID;
            }
        }
    } while (count($hidden) > $count);
    return array_values(array_filter($items, static function ($item) use ($hidden) {
        return !in_array((int) $item->ID, $hidden, true);
    }));
}, 20);

add_filter('render_block', function ($html, $block) {
    if (is_admin()) {
        return $html;
    }
    if ($block['blockName'] === 'core/loginout' && !popcorn_public_auth_enabled()) {
        return '';
    }
    if (in_array($block['blockName'], ['core/navigation-link', 'core/navigation-submenu', 'core/button'], true)) {
        $url = $block['attrs']['url'] ?? '';
        if (!$url && class_exists('WP_HTML_Tag_Processor')) {
            $tags = new WP_HTML_Tag_Processor($html);
            if ($tags->next_tag('A')) {
                $url = $tags->get_attribute('href');
            }
        }
        $page_id = ($block['attrs']['kind'] ?? '') === 'post-type' && ($block['attrs']['type'] ?? '') === 'page' ? absint($block['attrs']['id'] ?? 0) : 0;
        if (popcorn_public_link_unavailable($url, $page_id)) {
            return '';
        }
    }
    return $html;
}, 20, 2);

add_filter('wp_list_pages_excludes', function ($excluded) {
    if (!is_admin()) {
        foreach (get_pages(['post_status' => 'publish']) as $page) {
            if (popcorn_public_page_unavailable($page->ID)) {
                $excluded[] = $page->ID;
            }
        }
    }
    return array_unique($excluded);
});

add_filter('widget_display_callback', function ($instance, $widget) {
    return !is_admin() && $widget instanceof WP_Widget_Meta && !popcorn_public_auth_enabled() ? false : $instance;
}, 20, 2);

add_action('template_redirect', function () {
    if (is_singular('page') && popcorn_public_page_unavailable(get_queried_object_id())) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}, 1);
