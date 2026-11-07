<?php
defined('ABSPATH') || exit;

add_action('after_setup_theme', function () {
    load_theme_textdomain('popcorn', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height' => 72, 'width' => 220, 'flex-height' => true, 'flex-width' => true]);
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('automatic-feed-links');
    add_theme_support('block-template-parts');
    register_nav_menus(['primary' => 'منوی اصلی پاپکورن', 'footer' => 'منوی فوتر پاپکورن', 'footer_secondary' => 'منوی دوم فوتر پاپکورن']);
});

require_once get_template_directory() . '/inc/public-account.php';
require_once get_template_directory() . '/inc/header-menu.php';
require_once get_template_directory() . '/inc/genres.php';
require_once get_template_directory() . '/inc/homepage.php';
require_once get_template_directory() . '/inc/comments.php';
require_once get_template_directory() . '/inc/title-detail.php';
require_once get_template_directory() . '/inc/blog.php';

if (is_admin()) {
    require_once get_template_directory() . '/inc/admin-plugins.php';
}

add_action('elementor/theme/register_locations', function ($manager) {
    $manager->register_all_core_location();
});

add_action('wp_enqueue_scripts', function () {
    $uri = get_template_directory_uri();
    $path = get_template_directory();
    wp_enqueue_style('popcorn-base', $uri . '/style.css', [], filemtime($path . '/style.css'));
    wp_enqueue_script('popcorn-theme', $uri . '/assets/js/theme.js', [], filemtime($path . '/assets/js/theme.js'), false);
    wp_add_inline_script('popcorn-theme', 'document.documentElement.dataset.pcThemeMode=' . wp_json_encode(popcorn_theme_mode()) . ';', 'before');
    if (function_exists('popcorn_auth_page_kind') && popcorn_auth_enabled() && popcorn_auth_page_kind()) {
        return;
    }
    if (is_404()) {
        wp_enqueue_style('popcorn-error-page', $uri . '/assets/css/error-page.css', ['popcorn-base'], filemtime($path . '/assets/css/error-page.css'));
        return;
    }
    $view = get_query_var('popcorn_view');
    $notification_page = (bool) get_query_var('popcorn_notifications');
    if (is_page_template('page-schedule.php')) {
        wp_enqueue_style('popcorn-content-card', $uri . '/assets/css/content-card.css', ['popcorn-base'], filemtime($path . '/assets/css/content-card.css'));
        wp_enqueue_style('popcorn-schedule', $uri . '/assets/css/schedule.css', ['popcorn-content-card'], filemtime($path . '/assets/css/schedule.css'));
        wp_enqueue_script('popcorn-schedule', $uri . '/assets/js/schedule.js', [], filemtime($path . '/assets/js/schedule.js'), true);
    }
    $catalog_archive = function_exists('popcorn_core_is_catalog_archive') && popcorn_core_is_catalog_archive();
    if ($catalog_archive || in_array($view, ['movie', 'series'], true) || is_search() || is_post_type_archive('popcorn_person')) {
        wp_enqueue_style('popcorn-catalog', $uri . '/assets/css/catalog.css', ['popcorn-base'], filemtime($path . '/assets/css/catalog.css'));
        if (is_search()) {
            wp_enqueue_style('popcorn-search-results', $uri . '/assets/css/search.css', ['popcorn-catalog'], filemtime($path . '/assets/css/search.css'));
        }
        if ($catalog_archive || in_array($view, ['movie', 'series'], true) || is_post_type_archive('popcorn_person') || is_search()) {
            wp_enqueue_style('popcorn-archive-filters', $uri . '/assets/css/archive-filters.css', ['popcorn-catalog'], filemtime($path . '/assets/css/archive-filters.css'));
            wp_enqueue_script('popcorn-archive-filters', $uri . '/assets/js/archive-filters.js', [], filemtime($path . '/assets/js/archive-filters.js'), true);
        }
        if (is_search()) {
            popcorn_enqueue_person_card_style();
            popcorn_enqueue_slider_assets();
        }
        if (is_post_type_archive('popcorn_person')) {
            popcorn_enqueue_person_card_style();
            wp_enqueue_style('popcorn-person-archive', $uri . '/assets/css/person-archive.css', ['popcorn-person-card', 'popcorn-catalog'], filemtime($path . '/assets/css/person-archive.css'));
        }
        if ($catalog_archive && in_array('popcorn_person', (array) $GLOBALS['wp_query']->get('post_type'), true)) {
            popcorn_enqueue_person_card_style();
        }
    } elseif (($view === 'title' && function_exists('popcorn_get_item') && popcorn_get_item(get_query_var('popcorn_type'), absint(get_query_var('popcorn_id')))) || is_singular(['popcorn_movie', 'popcorn_series', 'popcorn_person'])) {
        wp_enqueue_style('popcorn-detail', $uri . '/assets/css/detail.css', ['popcorn-base'], filemtime($path . '/assets/css/detail.css'));
        wp_enqueue_style('popcorn-expandable-text', $uri . '/assets/css/expandable-text.css', ['popcorn-detail'], filemtime($path . '/assets/css/expandable-text.css'));
        wp_enqueue_script('popcorn-expandable-text', $uri . '/assets/js/expandable-text.js', [], filemtime($path . '/assets/js/expandable-text.js'), true);
        if (!is_singular('popcorn_person')) {
            wp_enqueue_style('popcorn-title-detail', $uri . '/assets/css/title-detail.css', ['popcorn-detail'], filemtime($path . '/assets/css/title-detail.css'));
            $title_item = is_singular(['popcorn_movie', 'popcorn_series']) && function_exists('popcorn_core_get_by_post') ? popcorn_core_get_by_post(is_singular('popcorn_series') ? 'series' : 'movie', get_queried_object_id()) : (function_exists('popcorn_get_item') ? popcorn_get_item(get_query_var('popcorn_type'), absint(get_query_var('popcorn_id'))) : null);
            $trailer = $title_item ? popcorn_title_trailer($title_item->trailer_url) : [];
            $trailer_dependencies = [];
            if ($trailer && $trailer['kind'] === 'video') {
                wp_enqueue_style('wp-mediaelement');
                wp_enqueue_script('wp-mediaelement');
                $trailer_dependencies[] = 'wp-mediaelement';
            }
            wp_enqueue_script('popcorn-title-detail', $uri . '/assets/js/title-detail.js', $trailer_dependencies, filemtime($path . '/assets/js/title-detail.js'), true);
        }
        if (is_singular('popcorn_person')) {
            $person = function_exists('popcorn_core_get_by_post') ? popcorn_core_get_by_post('person', get_queried_object_id()) : null;
            if ($person && popcorn_core_person_works($person->id, 1)) {
                popcorn_enqueue_slider_assets();
                wp_enqueue_style('popcorn-content-card', $uri . '/assets/css/content-card.css', ['popcorn-base'], filemtime($path . '/assets/css/content-card.css'));
                wp_enqueue_style('popcorn-person-works', $uri . '/assets/css/person-works.css', ['popcorn-detail', 'popcorn-slider'], filemtime($path . '/assets/css/person-works.css'));
                wp_enqueue_script('popcorn-person-works', $uri . '/assets/js/person-works.js', [], filemtime($path . '/assets/js/person-works.js'), true);
            }
        }
    } elseif (is_front_page() && !$notification_page) {
        wp_enqueue_style('popcorn-home', $uri . '/assets/css/home.css', ['popcorn-base'], filemtime($path . '/assets/css/home.css'));
        popcorn_enqueue_slider_assets();
        wp_enqueue_style('popcorn-genre-card', $uri . '/assets/css/genre-card.css', ['popcorn-slider'], filemtime($path . '/assets/css/genre-card.css'));
        popcorn_enqueue_person_card_style();
    }
    if (!$notification_page && ($catalog_archive || is_front_page() || is_search() || in_array($view, ['movie', 'series', 'title'], true) || is_singular(['popcorn_movie', 'popcorn_series']))) {
        wp_enqueue_style('popcorn-content-card', $uri . '/assets/css/content-card.css', ['popcorn-base'], filemtime($path . '/assets/css/content-card.css'));
    }
    if (is_singular('popcorn_playback')) {
        return;
    }
    wp_enqueue_style('popcorn-header', $uri . '/assets/css/header.css', ['popcorn-base'], filemtime($path . '/assets/css/header.css'));
    wp_enqueue_script('popcorn-header', $uri . '/assets/js/header.js', ['popcorn-theme'], filemtime($path . '/assets/js/header.js'), false);
});

add_filter('body_class', function ($classes) {
    if (get_query_var('popcorn_notifications')) {
        $classes = array_values(array_diff($classes, ['home', 'blog']));
        $classes[] = 'pc-notifications-page';
    }
    if ((is_front_page() && !get_query_var('popcorn_notifications') && function_exists('popcorn_get_slider_items') && popcorn_get_slider_items()) || is_singular(['popcorn_person', 'popcorn_movie', 'popcorn_series'])) {
        $classes[] = 'pc-has-hero';
    }
    return $classes;
});

add_action('wp_enqueue_scripts', function () {
    if (!is_404()) {
        return;
    }
    wp_dequeue_style('post-views-counter-frontend');
    if (!is_admin_bar_showing()) {
        wp_dequeue_style('dashicons');
    }
}, 100);

add_action('template_redirect', function () {
    if (is_404()) {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
    }
});

function popcorn_enqueue_person_card_style() {
    $card_path = get_template_directory() . '/assets/css/content-card.css';
    wp_enqueue_style('popcorn-content-card', get_template_directory_uri() . '/assets/css/content-card.css', ['popcorn-base'], filemtime($card_path));
    $path = get_template_directory() . '/assets/css/person-card.css';
    wp_enqueue_style('popcorn-person-card', get_template_directory_uri() . '/assets/css/person-card.css', ['popcorn-content-card'], filemtime($path));
}

function popcorn_enqueue_slider_assets($interactive = true) {
    $path = get_template_directory();
    $uri = get_template_directory_uri();
    wp_enqueue_style('popcorn-slider', $uri . '/assets/css/slider.css', ['popcorn-base'], filemtime($path . '/assets/css/slider.css'));
    if ($interactive) {
        wp_enqueue_style('popcorn-swiper', $uri . '/assets/vendor/swiper/swiper-bundle.min.css', [], '12.2.0');
        wp_enqueue_script('popcorn-swiper', $uri . '/assets/vendor/swiper/swiper-bundle.min.js', [], '12.2.0', true);
        wp_enqueue_script('popcorn-slider', $uri . '/assets/js/slider.js', ['popcorn-swiper'], filemtime($path . '/assets/js/slider.js'), true);
    }
}

add_filter('wp_script_attributes', function ($attributes) {
    if (in_array($attributes['id'] ?? '', ['popcorn-theme-js', 'popcorn-swiper-js', 'popcorn-slider-js'], true)) {
        $attributes['data-nowprocket'] = '';
        $attributes['data-no-defer'] = '1';
    }
    return $attributes;
});

add_filter('litespeed_optm_js_defer_exc', function ($list) {
    $list[] = '/assets/js/theme.js';
    $list[] = '/assets/vendor/swiper/swiper-bundle.min.js';
    $list[] = '/assets/js/slider.js';
    return $list;
});

function popcorn_theme_option($key, $default = '') {
    $options = get_option('popcorn_theme_options', []);
    return isset($options[$key]) && $options[$key] !== '' ? $options[$key] : $default;
}

function popcorn_theme_mode() {
    $mode = popcorn_theme_option('theme_mode', 'both');
    return in_array($mode, ['light', 'dark', 'both'], true) ? $mode : 'both';
}

add_filter('wp_inline_script_attributes', function ($attributes) {
    if (($attributes['id'] ?? '') === 'popcorn-theme-js-before') {
        $attributes['data-nowprocket'] = '';
        $attributes['data-no-defer'] = '1';
    }
    return $attributes;
});

add_filter('rocket_delay_js_exclusions', function ($list) {
    $list[] = '/assets/js/theme.js';
    $list[] = 'document.documentElement.dataset.pcThemeMode';
    return $list;
});

add_action('update_option_popcorn_theme_options', function ($old, $new) {
    if (($old['theme_mode'] ?? 'both') === ($new['theme_mode'] ?? 'both')) {
        return;
    }
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }
    do_action('litespeed_purge_all');
}, 10, 2);

function popcorn_footer_text() {
    $text = popcorn_theme_option('footer_text');
    return $text && $text !== 'همهٔ حقوق برای پاپکورن محفوظ است.' ? $text : sprintf('همهٔ حقوق برای %s محفوظ است.', wp_get_theme(get_template())->get('Name'));
}

function popcorn_theme_color() {
    return strtoupper(sanitize_hex_color(popcorn_theme_option('accent', '#6C52EE')) ?: '#6C52EE');
}

function popcorn_card_image_size() {
    return in_array('popcorn_poster', get_intermediate_image_sizes(), true) ? 'popcorn_poster' : 'medium_large';
}

function popcorn_compact_image_size() {
    return in_array('popcorn_compact', get_intermediate_image_sizes(), true) ? 'popcorn_compact' : 'thumbnail';
}

function popcorn_person_image_size() {
    return in_array('popcorn_person', get_intermediate_image_sizes(), true) ? 'popcorn_person' : 'medium';
}

function popcorn_person_is_deceased($person) {
    $value = trim((string) ($person->death_date ?? ''));
    $date = $value !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone()) : false;
    return $date && $date->format('Y-m-d') === $value && $date <= current_datetime();
}

function popcorn_item_seo_image_alt($item) {
    $fallback = popcorn_display_title($item);
    $post_id = absint($item->post_id ?? 0);
    if (!$post_id) {
        return $fallback;
    }
    $post = get_post($post_id);
    $keys = defined('WPSEO_VERSION') && !defined('RANK_MATH_VERSION') ? ['_yoast_wpseo_title', 'rank_math_title'] : ['rank_math_title', '_yoast_wpseo_title'];
    foreach ($keys as $key) {
        $title = trim((string) get_post_meta($post_id, $key, true));
        if ($title === '') {
            continue;
        }
        if ($key === 'rank_math_title' && $post && function_exists('rank_math') && isset(rank_math()->variables) && class_exists('RankMath\\Helper') && method_exists('RankMath\\Helper', 'replace_vars')) {
            $title = \RankMath\Helper::replace_vars($title, $post);
        } else {
            $title = strtr($title, [
                '%%title%%' => $fallback,
                '%%postname%%' => $fallback,
                '%%sitename%%' => get_bloginfo('name'),
                '%%sep%%' => ' | ',
                '%%currentyear%%' => wp_date('Y'),
                '%title%' => $fallback,
                '%sitename%' => get_bloginfo('name'),
                '%sep%' => ' | ',
                '%currentyear%' => wp_date('Y'),
            ]);
        }
        $title = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $title)));
        if ($title !== '' && !preg_match('/%{1,2}[a-z_]+%{1,2}/i', $title)) {
            return $title;
        }
    }
    return $fallback;
}

function popcorn_icon($name, $class = '') {
    $rating_icons = [
        'imdb-circle' => '<circle cx="16" cy="16" r="15" fill="#f5c518"/><text x="16" y="19.5" text-anchor="middle" font-family="Vazirmatn,sans-serif" font-size="9" font-weight="900" fill="#111">IMDb</text>',
        'tmdb-circle' => '<circle cx="16" cy="16" r="15" fill="#0d253f"/><image href="' . esc_url(get_template_directory_uri() . '/assets/icons/tmdb.svg') . '" x="4" y="11" width="24" height="10"/>',
        'rotten-tomatoes-circle' => '<circle cx="16" cy="17" r="12" fill="#fa320a"/><path d="M11.5 8c1.2-1.8 3-2.8 4.5-2.8s3.3 1 4.5 2.8" stroke="#3ea23e" stroke-width="2" fill="none" stroke-linecap="round"/><circle cx="11.5" cy="13.5" r="1.6" fill="#fff" fill-opacity=".35"/>',
        'metacritic-circle' => '<circle cx="16" cy="16" r="15" fill="#ffcb00"/><circle cx="16" cy="16" r="11.5" fill="#111"/><path fill="#fff" d="M-10.713,40.702h4.423v-8.518c0-0.359,0.03-0.777,0.179-1.136 c0.269-0.747,0.956-1.644,2.122-1.644c1.435,0,2.092,1.255,2.092,3.108v8.189h4.423v-8.548c0-0.359,0.06-0.837,0.179-1.166 c0.299-0.867,1.046-1.584,2.092-1.584c1.464,0,2.152,1.225,2.152,3.347v7.95h4.423v-8.607c0-4.363-2.122-6.336-4.931-6.336 c-1.076,0-1.973,0.239-2.779,0.717c-0.687,0.418-1.345,0.986-1.883,1.763H1.72c-0.628-1.524-2.122-2.481-4.005-2.481 c-2.51,0-3.796,1.375-4.423,2.301h-0.09l-0.209-1.973h-3.825c0.06,1.285,0.12,2.839,0.12,4.662L-10.713,40.702L-10.713,40.702z" transform="translate(1 1) scale(.625) rotate(-45)"/>',
    ];
    if (isset($rating_icons[$name])) {
        return '<svg class="pc-icon ' . esc_attr($class) . '" viewBox="0 0 32 32" aria-hidden="true" focusable="false">' . $rating_icons[$name] . '</svg>';
    }
    if ($name === 'imdb') {
        return '<svg class="pc-icon ' . esc_attr($class) . '" viewBox="0 0 42 20" aria-hidden="true" focusable="false"><rect width="42" height="20" rx="3" fill="#f5c518"/><text x="21" y="14.5" text-anchor="middle" font-family="Vazirmatn,sans-serif" font-size="13" font-weight="900" fill="#111">IMDb</text></svg>';
    }
    if ($name === 'tmdb') {
        return '<img class="pc-icon ' . esc_attr($class) . '" src="' . esc_url(get_template_directory_uri() . '/assets/icons/tmdb.svg') . '" alt="" width="30" height="15" loading="lazy" decoding="async" aria-hidden="true">';
    }
    $paths = [
        'filter' => '<path d="M4 7h16M7 12h10M10 17h4"/>',
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>',
        'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/>',
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V10Z"/><path d="M9 21v-8h6v8"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 10.5v6M12 7.5h.01"/>',
        'microphone' => '<rect x="9" y="3" width="6" height="12" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21M8.5 21h7"/>',
        'arrow' => '<path d="M17 17 7 7M7 7h9M7 7v9"/>',
        'left' => '<path d="m15 5-7 7 7 7"/>',
        'right' => '<path d="m9 5 7 7-7 7"/>',
        'star' => '<path d="m12 2.5 2.9 6 6.6.9-4.8 4.7 1.2 6.6-5.9-3.1-5.9 3.1 1.2-6.6-4.8-4.7 6.6-.9z"/>',
        'spark' => '<path d="m12 2 2.2 7.8L22 12l-7.8 2.2L12 22l-2.2-7.8L2 12l7.8-2.2z"/>',
        'film' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/>',
        'tv' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M8 22h8M9 2l3 3 3-3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
        'moon' => '<path d="M20.3 15.5A8.5 8.5 0 0 1 8.5 3.7 8.5 8.5 0 1 0 20.3 15.5Z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/>',
        'newspaper' => '<path d="M4 3h14v15a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3"/><path d="M18 7h3v11a3 3 0 0 1-3 3M7 7h8M7 11h8M7 15h5"/>',
        'file' => '<path d="M7 2h7l5 5v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h5M9 12h6M9 16h6"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m4 17 5-5 3 3 3-4 5 6"/>',
        'translate' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9M12 3c-2.5 2.5-3.5 5.5-3.5 9s1 6.5 3.5 9"/>',
        'popcorn' => '<path d="M5 10h14l-2 11H7L5 10Z"/><path d="M8 10a3 3 0 0 1-1-5 3 3 0 0 1 4-1 3 3 0 0 1 5 0 3 3 0 0 1 3 5"/><path d="M10 11v9M14 11v9"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'play' => '<circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/>',
        'pause' => '<path d="M8 5v14M16 5v14"/>',
        'rewind' => '<path d="m10 4-5 4 5 4M5 8h8a7 7 0 1 1-6.5 9.5"/><path d="M11 12v5M14 12h3v5h-3z"/>',
        'forward' => '<path d="m14 4 5 4-5 4M19 8h-8a7 7 0 1 0 6.5 9.5"/><path d="M7 12v5M10 12h3v5h-3z"/>',
        'volume' => '<path d="M3 9h4l5-4v14l-5-4H3zM16 8a6 6 0 0 1 0 8M19 5a10 10 0 0 1 0 14"/>',
        'mute' => '<path d="M3 9h4l5-4v14l-5-4H3zM16 9l5 6M21 9l-5 6"/>',
        'fullscreen' => '<path d="M8 3H3v5M16 3h5v5M3 16v5h5M21 16v5h-5"/>',
        'pip' => '<rect x="2" y="4" width="20" height="16" rx="2"/><rect x="12" y="11" width="8" height="7" rx="1"/>',
        'quality' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 9v6M10 9v6M6 12h4M14 9h1a3 3 0 0 1 0 6h-1V9Z"/>',
        'subtitles' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M5 11h5M14 11h5M5 15h8M16 15h3"/>',
        'settings' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/>',
        'next' => '<path d="m5 5 10 7-10 7V5ZM19 5v14"/>',
        'previous' => '<path d="m19 5-10 7 10 7V5ZM5 5v14"/>',
        'exit' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
        'reply' => '<path d="m9 10-5 4 5 4"/><path d="M4 14h9a7 7 0 0 0 7-7V4"/>',
        'thumb-up' => '<path d="M7 10v11H3V10h4ZM7 10l5-8a3 3 0 0 1 2 3v5h5a2 2 0 0 1 2 2l-1 7a2 2 0 0 1-2 2H7"/>',
        'thumb-down' => '<path d="M7 14V3H3v11h4ZM7 14l5 8a3 3 0 0 0 2-3v-5h5a2 2 0 0 0 2-2l-1-7a2 2 0 0 0-2-2H7"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="pc-icon ' . esc_attr($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

function popcorn_rating_score($item, $source = 'site', $class = '', $show_empty = false, $compact = false, $bare = false) {
    $source = in_array($source, ['site', 'imdb', 'tmdb'], true) ? $source : 'site';
    $value = (float) ($source === 'site' ? ($item->rating ?? 0) : ($source === 'imdb' ? ($item->imdb_rating ?? 0) : ($item->vote_average ?? 0)));
    if (!$show_empty && $value <= 0) {
        return '';
    }
    $label = ['site' => 'سایت', 'imdb' => 'IMDb', 'tmdb' => 'TMDb'][$source];
    $attribute = $source === 'site' ? ' data-site-rating-post="' . absint($item->post_id ?? 0) . '"' : '';
    $number = $value > 0 ? ($bare ? number_format($value, 1, '.', '') : number_format_i18n($value, 1)) : '—';
    $value_attribute = $source === 'site' ? ' data-site-rating-value' : '';
    return '<span class="' . esc_attr($class) . '"' . $attribute . '>' . popcorn_icon($bare && $source !== 'site' ? $source : 'star') . ($bare ? ' <strong' . $value_attribute . '>' . esc_html($number) . '</strong> <small>/10</small>' : ' ' . esc_html($label) . ' <strong' . $value_attribute . '>' . esc_html($number) . '</strong>' . ($compact ? '' : ' / ۱۰')) . '</span>';
}

function popcorn_card_features($post_id, $items = []) {
    static $cache = [];
    $ids = [];
    foreach ($items as $item) {
        $id = absint(is_object($item) ? ($item->post_id ?? 0) : $item);
        if ($id && !array_key_exists($id, $cache)) {
            $ids[$id] = $id;
        }
    }
    $post_id = absint($post_id);
    if ($post_id && !array_key_exists($post_id, $cache)) {
        $ids[$post_id] = $post_id;
    }
    if ($ids) {
        foreach ($ids as $id) {
            $cache[$id] = ['play' => false, 'dubbed' => false, 'subtitle' => false];
        }
        if (function_exists('popcorn_core_table')) {
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $links = popcorn_core_table('download_link');
            $groups = popcorn_core_table('download_group');
            $download_rows = $wpdb->get_results($wpdb->prepare("SELECT g.post_id,MAX(l.is_dubbed) AS dubbed,MAX(l.has_subtitle) AS subtitle FROM $groups g INNER JOIN $links l ON l.group_id=g.id AND l.is_active=1 WHERE g.post_id IN ($placeholders) GROUP BY g.post_id", array_values($ids)));
            foreach ($download_rows as $row) {
                $id = (int) $row->post_id;
                $cache[$id]['dubbed'] = (bool) $row->dubbed;
                $cache[$id]['subtitle'] = (bool) $row->subtitle;
            }
            if (function_exists('popcorn_core_playback_enabled') && popcorn_core_playback_enabled()) {
                $playbacks = popcorn_core_table('playback');
                $playback_rows = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT v.content_post_id FROM $playbacks v INNER JOIN {$wpdb->posts} p ON p.ID=v.post_id AND p.post_type='popcorn_playback' AND p.post_status='publish' WHERE v.content_post_id IN ($placeholders) AND v.is_active=1", array_values($ids)));
                foreach ($playback_rows as $id) {
                    $cache[(int) $id]['play'] = true;
                }
            }
        }
    }
    return $post_id ? ($cache[$post_id] ?? ['play' => false, 'dubbed' => false, 'subtitle' => false]) : [];
}

function popcorn_webp_src($attachment_id, $size = 'large') {
    static $cache = [];
    $quality = function_exists('popcorn_core_media_quality') ? popcorn_core_media_quality() : 82;
    $key = absint($attachment_id) . ':' . $size . ':' . $quality;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    if (get_post_mime_type($attachment_id) === 'image/gif') {
        return $cache[$key] = wp_get_attachment_image_src($attachment_id, 'full');
    }
    $image = wp_get_attachment_image_src($attachment_id, $size);
    $original = get_attached_file($attachment_id);
    if (!$image || !$original) {
        return $cache[$key] = null;
    }
    $metadata = wp_get_attachment_metadata($attachment_id);
    $source = isset($metadata['sizes'][$size]['file']) ? dirname($original) . '/' . $metadata['sizes'][$size]['file'] : $original;
    $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
    if ($extension === 'gif') {
        return $cache[$key] = wp_get_attachment_image_src($attachment_id, 'full');
    }
    if ($extension === 'webp' || $extension === 'svg') {
        return $cache[$key] = $image;
    }
    if (!in_array($extension, ['jpg', 'jpeg', 'png'], true) || !is_file($source)) {
        return $cache[$key] = null;
    }
    $uploads = wp_get_upload_dir();
    $uploads_root = realpath($uploads['basedir']);
    $source_real = realpath($source);
    if (!$uploads_root || !$source_real || strpos($source_real, $uploads_root . DIRECTORY_SEPARATOR) !== 0) {
        return $cache[$key] = null;
    }
    $suffix = $quality === 82 ? '.webp' : '-q' . $quality . '.webp';
    $target = preg_replace('/\.(jpe?g|png)$/i', $suffix, $source);
    if (!is_file($target) || filemtime($target) < filemtime($source)) {
        $editor = wp_get_image_editor($source);
        if (is_wp_error($editor)) {
            return $cache[$key] = null;
        }
        $editor->set_quality($quality);
        $saved = $editor->save($target, 'image/webp');
        if (is_wp_error($saved) || !is_file($target)) {
            return $cache[$key] = null;
        }
    }
    $url = preg_replace('/\.(jpe?g|png)$/i', $suffix, $image[0]);
    return $cache[$key] = [$url, $image[1], $image[2], $image[3]];
}

function popcorn_image($attachment_id, $size = 'large', $attributes = []) {
    $attachment_id = absint($attachment_id);
    return $attachment_id ? wp_get_attachment_image($attachment_id, $size, false, $attributes) : '';
}

function popcorn_external_image_url($path) {
    $url = is_string($path) ? trim($path) : '';
    if (strlen($url) > 2048 || !preg_match('~^https?://~i', $url)) {
        return '';
    }
    $url = esc_url_raw($url, ['http', 'https']);
    $parts = wp_parse_url($url);
    if (!$url || !is_array($parts) || empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass'])) {
        return '';
    }
    $candidates = function_exists('popcorn_core_url_candidates') ? popcorn_core_url_candidates($url) : [];
    return $candidates['primary'] ?? $url;
}

function popcorn_content_image($attachment_id, $path, $size = 'large', $attributes = []) {
    $image = popcorn_image($attachment_id, $size, $attributes);
    if ($image) {
        return $image;
    }
    $external = popcorn_external_image_url($path);
    if (!$external) {
        return '';
    }
    $attributes = array_merge(['alt' => '', 'loading' => 'lazy', 'decoding' => 'async'], $attributes);
    $fallback = function_exists('popcorn_core_image_fallback_attr') ? popcorn_core_image_fallback_attr($external) : '';
    $html = '<img src="' . esc_url($external) . '"' . $fallback;
    foreach ($attributes as $name => $value) {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $name) && is_scalar($value)) {
            $html .= ' ' . esc_attr($name) . '="' . esc_attr((string) $value) . '"';
        }
    }
    return $html . '>';
}

add_filter('wp_get_attachment_image_attributes', function ($attributes, $attachment, $size) {
    if (is_admin() && get_post_mime_type($attachment->ID) !== 'image/gif') {
        return $attributes;
    }
    $webp = popcorn_webp_src($attachment->ID, is_string($size) ? $size : 'full');
    if ($webp) {
        $attributes['src'] = $webp[0];
        unset($attributes['srcset'], $attributes['sizes']);
    }
    return $attributes;
}, 10, 3);

add_filter('wp_content_img_tag', function ($html, $context, $attachment_id) {
    if (!$attachment_id) {
        return $html;
    }
    $size = preg_match('/\bsize-(thumbnail|medium|medium_large|large|full)\b/', $html, $matches) ? $matches[1] : 'large';
    $webp = popcorn_webp_src($attachment_id, $size);
    if (!$webp) {
        return $html;
    }
    $tag = new WP_HTML_Tag_Processor($html);
    if ($tag->next_tag('img')) {
        $tag->set_attribute('src', $webp[0]);
        $tag->remove_attribute('srcset');
        $tag->remove_attribute('sizes');
        return $tag->get_updated_html();
    }
    return $html;
}, 10, 3);

add_action('admin_menu', function () {
    $icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 10h14l-1.5 10h-11L5 10Z" fill="#fff"/><path d="M9 10V7a2.5 2.5 0 0 1-1.5-4.5A2.5 2.5 0 0 1 11 3a2.5 2.5 0 0 1 4 0 2.5 2.5 0 0 1 3.5 4A2.5 2.5 0 0 1 17 10"/><path d="M10 13l1 7M14 13l-1 7" stroke="#000"/></svg>');
    add_menu_page('تنظیمات پاپکورن', 'پاپکورن', 'manage_options', 'popcorn-settings', 'popcorn_theme_settings_page', $icon, 25);
});

add_action('admin_enqueue_scripts', function ($hook) {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    $screen = get_current_screen();
    if (($screen && in_array($screen->post_type, ['popcorn_movie', 'popcorn_series', 'popcorn_person', 'popcorn_playback', 'popcorn_schedule'], true)) || strpos($page, 'popcorn-') === 0) {
        wp_register_style('popcorn-admin-colors', false);
        wp_enqueue_style('popcorn-admin-colors');
        wp_add_inline_style('popcorn-admin-colors', ':root{--pc-admin-accent-color:' . popcorn_theme_color() . ';--pc-editor-accent:' . popcorn_theme_color() . '}');
    }
    if (in_array($hook, ['post.php', 'post-new.php'], true) && $screen && in_array($screen->post_type, ['popcorn_movie', 'popcorn_series', 'popcorn_person', 'popcorn_schedule'], true)) {
        $editor_css = get_template_directory() . '/assets/css/editor.css';
        wp_enqueue_style('popcorn-editor', get_template_directory_uri() . '/assets/css/editor.css', [], filemtime($editor_css));
    }
    if ($hook !== 'toplevel_page_popcorn-settings' && !in_array($page, ['popcorn-import-movie', 'popcorn-import-series', 'popcorn-import-person'], true)) {
        return;
    }
    $path = get_template_directory();
    $uri = get_template_directory_uri();
    wp_enqueue_style('popcorn-admin', $uri . '/assets/css/admin.css', [], filemtime($path . '/assets/css/admin.css'));
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
    if ($hook === 'toplevel_page_popcorn-settings' && $tab === 'general') {
        wp_enqueue_script('popcorn-admin', $uri . '/assets/js/admin.js', [], filemtime($path . '/assets/js/admin.js'), true);
    }
});

add_action('admin_init', function () {
    register_setting('popcorn_theme', 'popcorn_theme_options', [
        'type' => 'array',
        'sanitize_callback' => function ($input) {
            $input = is_array($input) ? $input : [];
            $accent = sanitize_hex_color($input['accent'] ?? '');
            return [
                'accent' => strtoupper($accent ?: '#6C52EE'),
                'theme_mode' => in_array($input['theme_mode'] ?? 'both', ['light', 'dark', 'both'], true) ? ($input['theme_mode'] ?? 'both') : 'both',
                'hero_title' => sanitize_text_field($input['hero_title'] ?? ''),
                'hero_text' => sanitize_textarea_field($input['hero_text'] ?? ''),
                'footer_text' => sanitize_text_field($input['footer_text'] ?? ''),
            ];
        },
        'default' => [],
    ]);
});

function popcorn_theme_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $tabs = ['general' => ['label' => 'عمومی', 'icon' => 'popcorn'], 'media' => ['label' => 'مدیا', 'icon' => 'image'], 'downloads' => ['label' => 'لینک‌های دانلود', 'icon' => 'arrow'], 'playback' => ['label' => 'پخش آنلاین', 'icon' => 'play'], 'engagement' => ['label' => 'امتیاز و علاقه‌مندی', 'icon' => 'star'], 'notifications' => ['label' => 'اعلان‌ها و تلگرام', 'icon' => 'bell'], 'account' => ['label' => 'حساب کاربری', 'icon' => 'user'], 'subscription' => ['label' => 'اشتراک', 'icon' => 'star'], 'search' => ['label' => 'جست‌وجو', 'icon' => 'search'], 'tmdb' => ['label' => 'TMDb', 'icon' => 'film'], 'translation' => ['label' => 'ترجمه', 'icon' => 'translate'], 'ads' => ['label' => 'تبلیغات', 'icon' => 'spark'], 'about' => ['label' => 'دربارهٔ قالب', 'icon' => 'star']];
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
    $tab = isset($tabs[$tab]) ? $tab : 'general';
    echo '<div class="wrap pc-admin" style="--pc-admin-accent:' . esc_attr(popcorn_theme_color()) . '"><div class="pc-admin__shell"><aside class="pc-admin__sidebar"><div class="pc-admin__logo">' . popcorn_icon('popcorn') . '<span>پاپکورن<small>ساختهٔ SirenCode</small></span></div><nav aria-label="بخش‌های تنظیمات">';
    foreach ($tabs as $slug => $data) {
        $url = add_query_arg(['page' => 'popcorn-settings', 'tab' => $slug], admin_url('admin.php'));
        echo '<a class="pc-admin__tab' . ($tab === $slug ? ' is-active' : '') . '" href="' . esc_url($url) . '">' . popcorn_icon($data['icon']) . '<span>' . esc_html($data['label']) . '</span></a>';
    }
    $body_class = in_array($tab, ['general', 'media', 'account', 'tmdb', 'ads'], true) ? ' pc-admin__body--split' : (in_array($tab, ['downloads', 'engagement', 'notifications', 'search', 'subscription'], true) ? ' pc-admin__body--wide' : '');
    $body_class .= $tab === 'notifications' ? ' pc-admin__body--notifications' : '';
    echo '</nav><p class="pc-admin__sidebar-foot">Popcorn ' . esc_html(wp_get_theme(get_template())->get('Version')) . ' · SirenCode</p></aside><div class="pc-admin__content"><div class="pc-admin__head"><span>تنظیمات پاپکورن</span><h1>' . esc_html($tabs[$tab]['label']) . '</h1><p>مدیریت یکپارچهٔ قالب فیلم و سریال</p></div><div class="pc-admin__body' . $body_class . '">';
    if ($tab === 'general') {
        echo '<form method="post" action="options.php">';
        settings_fields('popcorn_theme');
        echo '<div class="pc-admin__card"><h2>ظاهر سایت</h2><p>رنگ قالب و متن‌های صفحهٔ نخست را تنظیم کنید.</p><table class="form-table"><tr><th><label for="popcorn-accent">رنگ قالب</label></th><td><div class="pc-admin__color"><input id="popcorn-accent" type="color" value="' . esc_attr(popcorn_theme_color()) . '"><input id="popcorn-accent-hex" type="text" name="popcorn_theme_options[accent]" value="' . esc_attr(popcorn_theme_color()) . '" aria-label="کد رنگ قالب" dir="ltr" maxlength="7" pattern="#[0-9A-Fa-f]{6}" required></div><p class="description">کد رنگ را وارد کنید یا از انتخابگر رنگ استفاده کنید.</p></td></tr><tr><th><label for="popcorn-hero-title">عنوان بنر اصلی</label></th><td><input id="popcorn-hero-title" class="regular-text" name="popcorn_theme_options[hero_title]" value="' . esc_attr(popcorn_theme_option('hero_title', 'دنیای فیلم و سریال، همین‌جا')) . '"></td></tr><tr><th><label for="popcorn-hero-text">متن بنر اصلی</label></th><td><textarea id="popcorn-hero-text" class="large-text" rows="3" name="popcorn_theme_options[hero_text]">' . esc_textarea(popcorn_theme_option('hero_text', 'از تازه‌ترین فیلم‌ها تا سریال‌های محبوب، قصهٔ بعدی‌ات را پیدا کن.')) . '</textarea></td></tr><tr><th><label for="popcorn-footer">متن پانوشت</label></th><td><input id="popcorn-footer" class="regular-text" name="popcorn_theme_options[footer_text]" value="' . esc_attr(popcorn_footer_text()) . '"></td></tr></table></div>';
        echo '<div class="pc-admin__card"><h2>تم رنگی</h2><p>حالت نمایش سایت را انتخاب کنید. در حالت «هر دو»، کاربر می‌تواند از هدر تم را تغییر دهد.</p><table class="form-table"><tr><th><label for="popcorn-theme-mode">حالت نمایش</label></th><td><select id="popcorn-theme-mode" name="popcorn_theme_options[theme_mode]">';
        foreach (['light' => 'روشن (لایت)', 'dark' => 'تاریک (دارک)', 'both' => 'هر دو (انتخاب کاربر)'] as $mode => $label) {
            echo '<option value="' . esc_attr($mode) . '"' . selected(popcorn_theme_mode(), $mode, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p class="description">انتخاب یک تم، همان حالت را برای همهٔ کاربران ثابت می‌کند.</p></td></tr></table></div>';
        submit_button('ذخیرهٔ تنظیمات');
        echo '</form><div class="pc-admin__card"><h2>هویت سایت</h2><p>نشان سایت را در بخش «نمایش ← سفارشی‌سازی ← هویت سایت» تعیین کنید.</p></div>';
    } elseif ($tab === 'about') {
        get_template_part('template-parts/admin-about');
    } else {
        do_action('popcorn_settings_tab_' . $tab);
        if (!has_action('popcorn_settings_tab_' . $tab)) {
            $plugin_name = $tab === 'account' ? 'حساب کاربری پاپکورن' : ($tab === 'subscription' ? 'اشتراک پاپکورن' : 'هستهٔ پاپکورن');
            echo '<div class="pc-admin__card"><p>برای استفاده از این بخش، افزونهٔ ' . esc_html($plugin_name) . ' را فعال کنید.</p></div>';
        }
    }
    echo '</div></div></div></div>';
}

add_action('wp_head', function () {
    echo '<style id="popcorn-theme-color">:root{--pc-accent:' . esc_html(popcorn_theme_color()) . '}</style>';
}, 20);

function popcorn_theme_ad($placement, $contained = false) {
    if (!function_exists('popcorn_get_ads') || is_404() || is_singular('popcorn_playback')) {
        return;
    }
    $html = '';
    foreach (popcorn_get_ads($placement) as $ad) {
        $image = popcorn_image($ad->image_id, 'full', ['alt' => $ad->alt_text]);
        if (!$image) {
            continue;
        }
        $urls = $ad->target_url && function_exists('popcorn_core_url_candidates') ? popcorn_core_url_candidates($ad->target_url) : [];
        $html .= '<div class="pc-ad">';
        if ($urls) {
            $html .= '<a href="' . esc_url($urls['primary']) . '" rel="sponsored noopener noreferrer" target="_blank">' . $image . '</a>';
        } else {
            $html .= $image;
        }
        $html .= '</div>';
    }
    if ($html) {
        echo '<aside class="pc-ad-slot pc-ad-slot--' . esc_attr($placement) . ($contained ? ' pc-container' : '') . '" aria-label="تبلیغات">' . $html . '</aside>';
    }
}
