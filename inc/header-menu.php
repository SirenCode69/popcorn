<?php
defined('ABSPATH') || exit;

function popcorn_seed_header_menu() {
    if (get_option('popcorn_header_menu_seeded')) {
        return;
    }
    if (wp_get_nav_menus()) {
        update_option('popcorn_header_menu_seeded', 1, false);
        return;
    }
    $menu_id = wp_create_nav_menu('منوی اصلی پاپکورن');
    if (is_wp_error($menu_id)) {
        return;
    }
    $categories = wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => 'دسته‌بندی‌ها', 'menu-item-url' => home_url('/films/'), 'menu-item-status' => 'publish']);
    if (!is_wp_error($categories)) {
        update_post_meta($categories, '_popcorn_mega_menu', '1');
        wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => 'فیلم‌ها', 'menu-item-url' => home_url('/films/'), 'menu-item-parent-id' => $categories, 'menu-item-status' => 'publish']);
        wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => 'سریال‌ها', 'menu-item-url' => home_url('/series/'), 'menu-item-parent-id' => $categories, 'menu-item-status' => 'publish']);
    }
    wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => 'هنرمندان', 'menu-item-url' => get_post_type_archive_link('popcorn_person') ?: home_url('/artists/'), 'menu-item-status' => 'publish']);
    $app_page = get_page_by_path('application') ?: get_page_by_path('app');
    if ($app_page && $app_page->post_status === 'publish') {
        wp_update_nav_menu_item($menu_id, 0, ['menu-item-title' => 'اپلیکیشن', 'menu-item-object-id' => $app_page->ID, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish']);
    }
    $locations = get_theme_mod('nav_menu_locations', []);
    $locations['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
    update_option('popcorn_header_menu_seeded', 1, false);
}
add_action('init', 'popcorn_seed_header_menu', 30);

add_action('wp_nav_menu_item_custom_fields', function ($item_id) {
    wp_nonce_field('popcorn_menu_mega', 'popcorn_menu_mega_nonce');
    echo '<p class="field-popcorn-mega description description-wide"><label><input type="checkbox" name="popcorn_menu_mega[' . absint($item_id) . ']" value="1"' . checked(get_post_meta($item_id, '_popcorn_mega_menu', true), '1', false) . '> نمایش مگامنو برای این آیتم</label><br><small>ژانرها و زیرآیتم‌های فهرست می‌توانند داخل مگامنوی بلوکی نمایش داده شوند.</small></p>';
    if (post_type_exists('popcorn_mega_menu')) {
        $selected = absint(get_post_meta($item_id, '_popcorn_mega_content_id', true));
        $panels = get_posts(['post_type' => 'popcorn_mega_menu', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        echo '<p class="field-popcorn-mega-panel description description-wide"><label for="popcorn-menu-block-' . absint($item_id) . '">چیدمان بلوکی مگامنو</label><select id="popcorn-menu-block-' . absint($item_id) . '" name="popcorn_menu_block[' . absint($item_id) . ']"><option value="0">چیدمان پیش‌فرض ژانرها</option>';
        foreach ($panels as $panel) {
            echo '<option value="' . absint($panel->ID) . '"' . selected($selected, $panel->ID, false) . '>' . esc_html($panel->post_title) . '</option>';
        }
        echo '</select><br><small><a href="' . esc_url(admin_url('edit.php?post_type=popcorn_mega_menu')) . '">ساخت و ویرایش مگامنوها در ویرایشگر بلوکی</a></small></p>';
    }
}, 10, 1);

add_action('wp_update_nav_menu_item', function ($menu_id, $item_id) {
    $nonce = isset($_POST['popcorn_menu_mega_nonce']) ? sanitize_text_field(wp_unslash($_POST['popcorn_menu_mega_nonce'])) : '';
    if (!current_user_can('edit_theme_options') || !wp_verify_nonce($nonce, 'popcorn_menu_mega')) {
        return;
    }
    $block_id = isset($_POST['popcorn_menu_block']) && is_array($_POST['popcorn_menu_block']) ? absint($_POST['popcorn_menu_block'][$item_id] ?? 0) : 0;
    $block_post = $block_id ? get_post($block_id) : null;
    if ($block_post && $block_post->post_type === 'popcorn_mega_menu' && $block_post->post_status === 'publish') {
        update_post_meta($item_id, '_popcorn_mega_content_id', $block_id);
    } else {
        delete_post_meta($item_id, '_popcorn_mega_content_id');
        $block_post = null;
    }
    $selected = $block_post || (isset($_POST['popcorn_menu_mega']) && is_array($_POST['popcorn_menu_mega']) && isset($_POST['popcorn_menu_mega'][$item_id]));
    if ($selected) {
        update_post_meta($item_id, '_popcorn_mega_menu', '1');
    } else {
        delete_post_meta($item_id, '_popcorn_mega_menu');
    }
}, 10, 2);

function popcorn_header_menu_items() {
    static $items = null;
    if ($items === null) {
        $locations = get_nav_menu_locations();
        $items = !empty($locations['primary']) ? (wp_get_nav_menu_items($locations['primary']) ?: []) : [];
    }
    return $items;
}

function popcorn_menu_block_post($item_id) {
    $block_id = absint(get_post_meta($item_id, '_popcorn_mega_content_id', true));
    if (!$block_id) {
        return null;
    }
    $post = get_post($block_id);
    return $post && $post->post_type === 'popcorn_mega_menu' && $post->post_status === 'publish' ? $post : null;
}

add_filter('nav_menu_css_class', function ($classes, $item, $args) {
    if (($args->theme_location ?? '') !== 'primary') {
        return $classes;
    }
    if (get_post_meta($item->ID, '_popcorn_mega_menu', true) === '1') {
        $classes[] = 'pc-menu-item--mega';
        if (popcorn_menu_block_post($item->ID)) {
            $classes[] = 'pc-menu-item--block';
        } else {
            foreach (popcorn_header_menu_items() as $child) {
                if ((int) $child->menu_item_parent === (int) $item->ID && $child->object !== 'popcorn_genre') {
                    $classes[] = 'pc-menu-item--has-sections';
                    break;
                }
            }
        }
    } elseif ($item->object === 'popcorn_genre' && $item->menu_item_parent && get_post_meta($item->menu_item_parent, '_popcorn_mega_menu', true) === '1') {
        $classes[] = 'pc-mega-genre-item';
    }
    return $classes;
}, 10, 3);

function popcorn_menu_genres_data($item_id) {
    if (!taxonomy_exists('popcorn_genre')) {
        return [];
    }
    $selected = [];
    foreach (popcorn_header_menu_items() as $child) {
        if ((int) $child->menu_item_parent === (int) $item_id && $child->object === 'popcorn_genre') {
            $term = get_term(absint($child->object_id), 'popcorn_genre');
            if ($term && !is_wp_error($term)) {
                $selected[] = ['term' => $term, 'title' => $child->title, 'url' => $child->url];
            }
        }
    }
    if ($selected) {
        return $selected;
    }
    $terms = get_terms(['taxonomy' => 'popcorn_genre', 'hide_empty' => true, 'number' => 30, 'orderby' => 'count', 'order' => 'DESC']);
    return is_wp_error($terms) ? [] : array_map(function ($term) {
        return ['term' => $term, 'title' => $term->name, 'url' => get_term_link($term)];
    }, $terms);
}

function popcorn_menu_genres_html($item_id) {
    $genres = popcorn_menu_genres_data($item_id);
    $html = '<div class="pc-mega__genres" id="pc-menu-panel-' . absint($item_id) . '"><strong class="pc-mega__heading">ژانرها</strong><div class="pc-mega__grid">';
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

function popcorn_menu_block_html($item_id, $post) {
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
    return '<div class="pc-mega__genres pc-mega__panel" id="pc-menu-panel-' . absint($item_id) . '">' . $content . '</div>';
}

add_filter('walker_nav_menu_start_el', function ($output, $item, $depth, $args) {
    if (($args->theme_location ?? '') !== 'primary' || $depth !== 0) {
        return $output;
    }
    $mega = get_post_meta($item->ID, '_popcorn_mega_menu', true) === '1';
    $children = in_array('menu-item-has-children', (array) $item->classes, true);
    if (!$mega && !$children) {
        return $output;
    }
    $icon = function_exists('popcorn_icon') ? popcorn_icon('down') : '';
    $output .= '<button type="button" class="pc-menu-toggle" aria-label="باز کردن زیرمنوی ' . esc_attr($item->title) . '" aria-expanded="false"' . ($mega ? ' aria-controls="pc-menu-panel-' . absint($item->ID) . '"' : '') . '>' . $icon . '</button>';
    if (!$mega) {
        return $output;
    }
    $block_post = popcorn_menu_block_post($item->ID);
    return $output . ($block_post ? popcorn_menu_block_html($item->ID, $block_post) : popcorn_menu_genres_html($item->ID));
}, 10, 4);
