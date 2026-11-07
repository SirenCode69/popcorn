<?php
/* Block-based Popcorn mega menus by SirenCode. */
defined('ABSPATH') || exit;

function popcorn_mega_default_content() {
    return '<!-- wp:group {"className":"pc-mega__layout"} --><div class="wp-block-group pc-mega__layout"><!-- wp:popcorn/menu-links /--><!-- wp:popcorn/genre-grid /--></div><!-- /wp:group -->';
}

add_action('init', function () {
    register_post_type('popcorn_mega_menu', [
        'labels' => [
            'name' => 'مگامنوها',
            'singular_name' => 'مگامنو',
            'add_new_item' => 'افزودن مگامنو',
            'edit_item' => 'ویرایش مگامنو',
            'all_items' => 'مگامنوها',
            'menu_name' => 'مگامنوهای پاپکورن',
        ],
        'public' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_ui' => true,
        'show_in_menu' => 'themes.php',
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'revisions'],
        'template' => [['core/group', ['className' => 'pc-mega__layout'], [['popcorn/menu-links'], ['popcorn/genre-grid']]]],
        'map_meta_cap' => true,
    ]);
    register_block_type('popcorn/menu-links', [
        'api_version' => 3,
        'title' => 'بخش‌های فهرست پاپکورن',
        'category' => 'widgets',
        'supports' => ['html' => false],
        'render_callback' => 'popcorn_mega_render_links',
    ]);
    register_block_type('popcorn/genre-grid', [
        'api_version' => 3,
        'title' => 'ژانرهای پاپکورن',
        'category' => 'widgets',
        'supports' => ['html' => false],
        'render_callback' => 'popcorn_mega_render_genres',
    ]);
    register_block_type('popcorn/mega-menu-item', [
        'api_version' => 3,
        'title' => 'مگامنوی پاپکورن',
        'category' => 'design',
        'parent' => ['core/navigation'],
        'supports' => ['html' => false],
        'attributes' => [
            'label' => ['type' => 'string', 'default' => 'مگامنو'],
            'menuSlug' => ['type' => 'string', 'default' => ''],
        ],
        'render_callback' => 'popcorn_mega_render_navigation_item',
    ]);
}, 20);

function popcorn_mega_render_navigation_item($attributes) {
    $label = sanitize_text_field($attributes['label'] ?? 'مگامنو');
    $label = $label !== '' ? $label : 'مگامنو';
    $slug = sanitize_title($attributes['menuSlug'] ?? '');
    $post = $slug ? get_page_by_path($slug, OBJECT, 'popcorn_mega_menu') : null;
    $post = $post && $post->post_status === 'publish' ? $post : null;
    if (!$post) {
        return '<li class="wp-block-navigation-item"><span class="pc-block-nav__trigger">' . esc_html($label) . '</span></li>';
    }
    $panel_id = wp_unique_id('pc-menu-panel-');
    $item_id = 0;
    if ($post && function_exists('popcorn_header_menu_items')) {
        foreach (popcorn_header_menu_items() as $item) {
            if (absint(get_post_meta($item->ID, '_popcorn_mega_content_id', true)) === (int) $post->ID) {
                $item_id = $item->ID;
                break;
            }
        }
    }
    $icon = function_exists('popcorn_icon') ? popcorn_icon('down') : '';
    $html = '<li class="wp-block-navigation-item pc-menu-item--mega pc-menu-item--block"><button type="button" class="pc-menu-toggle pc-block-nav__trigger" aria-label="باز کردن زیرمنوی ' . esc_attr($label) . '" aria-expanded="false" aria-controls="' . esc_attr($panel_id) . '"><span>' . esc_html($label) . '</span>' . $icon . '</button>';
    $previous = $GLOBALS['popcorn_mega_current_item'] ?? null;
    $GLOBALS['popcorn_mega_current_item'] = $item_id;
    try {
        $content = do_blocks($post->post_content);
    } finally {
        if ($previous === null) {
            unset($GLOBALS['popcorn_mega_current_item']);
        } else {
            $GLOBALS['popcorn_mega_current_item'] = $previous;
        }
    }
    $html .= '<div class="pc-mega__genres pc-mega__panel" id="' . esc_attr($panel_id) . '">' . $content . '</div>';
    return $html . '</li>';
}

function popcorn_mega_render_links() {
    $item_id = absint($GLOBALS['popcorn_mega_current_item'] ?? 0);
    if (!$item_id || !function_exists('popcorn_header_menu_items')) {
        return is_admin() ? '<p>در سایت، زیرآیتم‌های این گزینه از فهرست اصلی نمایش داده می‌شوند.</p>' : '';
    }
    $links = [];
    foreach (popcorn_header_menu_items() as $child) {
        if ((int) $child->menu_item_parent === $item_id && $child->object !== 'popcorn_genre' && $child->url) {
            $links[] = '<a href="' . esc_url($child->url) . '">' . esc_html($child->title) . '</a>';
        }
    }
    return $links ? '<div class="pc-mega__section-links"><strong class="pc-mega__heading">بخش‌ها</strong>' . implode('', $links) . '</div>' : '';
}

function popcorn_mega_render_genres() {
    if (!function_exists('popcorn_menu_genres_data')) {
        return '';
    }
    $genres = popcorn_menu_genres_data(absint($GLOBALS['popcorn_mega_current_item'] ?? 0));
    $html = '<div class="pc-mega__genre-block"><strong class="pc-mega__heading">ژانرها</strong><div class="pc-mega__grid">';
    foreach ($genres as $genre) {
        if (!is_wp_error($genre['url'])) {
            $html .= '<a href="' . esc_url($genre['url']) . '"><span>' . esc_html($genre['title']) . '</span><small>' . esc_html(number_format_i18n($genre['term']->count)) . '</small></a>';
        }
    }
    if (!$genres) {
        $html .= '<p>ژانرهای آثار منتشرشده اینجا نمایش داده می‌شوند.</p>';
    }
    return $html . '</div></div>';
}

add_action('enqueue_block_editor_assets', function () {
    $screen = get_current_screen();
    if (!$screen || ($screen->post_type !== 'popcorn_mega_menu' && $screen->id !== 'site-editor')) {
        return;
    }
    wp_add_inline_script('wp-block-library', 'wp.hooks.addFilter("blocks.registerBlockType","popcorn/mega-navigation",function(settings,name){if(name!=="core/navigation")return settings;return Object.assign({},settings,{allowedBlocks:Array.from(new Set((settings.allowedBlocks||[]).concat("popcorn/mega-menu-item")))});});', 'before');
    $path = dirname(__DIR__) . '/assets/js/mega-menu-editor.js';
    wp_enqueue_script('popcorn-mega-editor', plugins_url('assets/js/mega-menu-editor.js', dirname(__DIR__) . '/popcorn-core.php'), ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data'], filemtime($path), true);
});

add_action('init', function () {
    if (get_option('popcorn_mega_menu_seeded') || !function_exists('popcorn_header_menu_items')) {
        return;
    }
    $items = popcorn_header_menu_items();
    if (!$items) {
        return;
    }
    $parent = null;
    foreach ($items as $item) {
        if (!$item->menu_item_parent && get_post_meta($item->ID, '_popcorn_mega_menu', true) === '1') {
            $parent = $item;
            break;
        }
    }
    if (!$parent) {
        update_option('popcorn_mega_menu_seeded', 1, false);
        return;
    }
    $existing_id = absint(get_post_meta($parent->ID, '_popcorn_mega_content_id', true));
    if (!$existing_id) {
        $existing = get_page_by_path('popcorn-main-mega', OBJECT, 'popcorn_mega_menu');
        $existing_id = $existing ? $existing->ID : wp_insert_post([
            'post_type' => 'popcorn_mega_menu',
            'post_status' => 'publish',
            'post_title' => 'مگامنوی دسته‌بندی‌ها',
            'post_name' => 'popcorn-main-mega',
            'post_content' => popcorn_mega_default_content(),
        ], true);
        if (is_wp_error($existing_id)) {
            return;
        }
        update_post_meta($parent->ID, '_popcorn_mega_content_id', absint($existing_id));
    }
    update_option('popcorn_mega_menu_seeded', 1, false);
}, 50);
