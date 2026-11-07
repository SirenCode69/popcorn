<?php
defined('ABSPATH') || exit;

function popcorn_blog_url() {
    $page = absint(get_option('page_for_posts'));
    if (get_option('show_on_front') === 'page' && $page) {
        return get_permalink($page);
    }
    return !get_option('permalink_structure') || get_page_by_path('blog') ? add_query_arg('popcorn_blog', 1, home_url('/')) : home_url('/blog/');
}

function popcorn_is_blog_archive($query = null) {
    if (function_exists('popcorn_core_is_blog_archive')) {
        return popcorn_core_is_blog_archive($query);
    }
    $query = $query ?: $GLOBALS['wp_query'];
    return ($query->is_home() && !$query->is_front_page()) || $query->is_category() || $query->is_tag() || $query->is_author() || $query->is_date();
}

function popcorn_blog_filters() {
    return function_exists('popcorn_core_blog_filters') ? popcorn_core_blog_filters() : ['pc_blog_category' => [], 'pc_blog_tag' => [], 'pc_blog_sort' => 'newest'];
}

add_filter('template_include', function ($template) {
    return get_query_var('popcorn_blog') && !is_404() ? get_template_directory() . '/blog-archive.php' : $template;
}, 99);

add_filter('document_title_parts', function ($parts) {
    if (get_query_var('popcorn_blog') && !is_404()) {
        $parts['title'] = 'وبلاگ';
    }
    return $parts;
});

add_filter('body_class', function ($classes) {
    if (get_query_var('popcorn_blog')) {
        $classes = array_values(array_diff($classes, ['home', 'blog', 'pc-has-hero']));
    }
    return $classes;
}, 20);

add_action('wp_enqueue_scripts', function () {
    if (is_404() || (!is_singular('post') && !popcorn_is_blog_archive())) {
        return;
    }
    $path = get_template_directory();
    $uri = get_template_directory_uri();
    wp_enqueue_style('popcorn-blog', $uri . '/assets/css/blog.css', ['popcorn-blog-card'], filemtime($path . '/assets/css/blog.css'));
    $dependencies = [];
    if (popcorn_is_blog_archive()) {
        wp_enqueue_script('popcorn-archive-filters', $uri . '/assets/js/archive-filters.js', [], filemtime($path . '/assets/js/archive-filters.js'), true);
        $dependencies[] = 'popcorn-archive-filters';
    }
    wp_enqueue_script('popcorn-blog', $uri . '/assets/js/blog.js', $dependencies, filemtime($path . '/assets/js/blog.js'), true);
}, 20);

add_action('wp_enqueue_scripts', function () {
    if (is_404() || (!is_search() && !is_singular('post') && !popcorn_is_blog_archive() && !(is_front_page() && !get_query_var('popcorn_blog') && function_exists('popcorn_get_home_news') && popcorn_get_home_news(1)))) {
        return;
    }
    wp_enqueue_style('popcorn-blog-card', get_template_directory_uri() . '/assets/css/blog-card.css', ['popcorn-base'], filemtime(get_template_directory() . '/assets/css/blog-card.css'));
}, 19);

function popcorn_blog_title() {
    if (is_category() || is_tag()) {
        return single_term_title('', false);
    }
    if (is_author() || is_date()) {
        return wp_strip_all_tags(get_the_archive_title());
    }
    $page = absint(get_option('page_for_posts'));
    return is_home() && $page ? get_the_title($page) : 'وبلاگ';
}

function popcorn_blog_reading_time($post) {
    $text = wp_strip_all_tags(strip_shortcodes($post->post_content));
    preg_match_all('/[\p{L}\p{N}]+/u', $text, $words);
    return max(1, (int) ceil(count($words[0]) / 200));
}

function popcorn_blog_image_alt($post_id = 0) {
    $post_id = $post_id ?: get_the_ID();
    return function_exists('popcorn_item_seo_image_alt') ? popcorn_item_seo_image_alt((object) ['title' => get_the_title($post_id), 'post_id' => $post_id]) : get_the_title($post_id);
}

function popcorn_blog_side_posts($post_id, $related = false) {
    $args = ['post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 5, 'post__not_in' => [$post_id], 'has_password' => false, 'ignore_sticky_posts' => true, 'no_found_rows' => true];
    if ($related) {
        $categories = wp_get_post_categories($post_id);
        if (!$categories) {
            return [];
        }
        $args['category__in'] = $categories;
    }
    return get_posts($args);
}

function popcorn_blog_toc_content($html, $post_id) {
    $items = [];
    $used = [];
    preg_match_all('/(?:^|\s)id\s*=\s*["\']([^"\']+)["\']/i', $html, $ids);
    foreach ($ids[1] as $id) {
        $used[html_entity_decode($id, ENT_QUOTES, 'UTF-8')] = true;
    }
    $index = 0;
    $html = preg_replace_callback('/<h([23])\b([^>]*)>(.*?)<\/h\1\s*>/is', function ($match) use (&$items, &$used, &$index, $post_id) {
        $text = trim(html_entity_decode(wp_strip_all_tags($match[3]), ENT_QUOTES, 'UTF-8'));
        if ($text === '') {
            return $match[0];
        }
        if (preg_match('/(?:^|\s)id\s*=\s*(["\'])(.*?)\1/is', $match[2], $id_match)) {
            $id = html_entity_decode($id_match[2], ENT_QUOTES, 'UTF-8');
            $attributes = $match[2];
        } else {
            do {
                $id = 'pc-post-' . $post_id . '-heading-' . ++$index;
            } while (isset($used[$id]));
            $used[$id] = true;
            $attributes = $match[2] . ' id="' . esc_attr($id) . '"';
        }
        $items[] = ['id' => $id, 'level' => $match[1], 'text' => $text];
        return '<h' . $match[1] . $attributes . '>' . $match[3] . '</h' . $match[1] . '>';
    }, $html);
    return ['html' => $html, 'items' => $items];
}
