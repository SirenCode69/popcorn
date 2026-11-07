<?php
defined('ABSPATH') || exit;

function popcorn_core_is_blog_archive($query = null) {
    $query = $query ?: $GLOBALS['wp_query'];
    if (function_exists('popcorn_core_is_catalog_archive') && popcorn_core_is_catalog_archive($query)) {
        return false;
    }
    return (bool) $query->get('popcorn_blog') || ($query->is_home() && !$query->is_front_page()) || $query->is_category() || $query->is_tag() || $query->is_author() || $query->is_date();
}

add_action('init', function () {
    if (!get_page_by_path('blog')) {
        add_rewrite_rule('^blog/page/([0-9]+)/?$', 'index.php?popcorn_blog=1&paged=$matches[1]', 'top');
        add_rewrite_rule('^blog/?$', 'index.php?popcorn_blog=1', 'top');
    }
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'popcorn_blog';
    return $vars;
});

add_action('admin_init', function () {
    if (current_user_can('manage_options') && get_option('popcorn_blog_rewrite_version') !== '1') {
        flush_rewrite_rules(false);
        update_option('popcorn_blog_rewrite_version', '1', false);
    }
});

add_action('after_switch_theme', function () {
    delete_option('popcorn_blog_rewrite_version');
});

function popcorn_core_blog_filters() {
    $filters = ['pc_blog_sort' => 'newest'];
    foreach (['pc_blog_category', 'pc_blog_tag'] as $key) {
        $values = isset($_GET[$key]) ? (array) wp_unslash($_GET[$key]) : [];
        $filters[$key] = array_slice(array_values(array_unique(array_filter(array_map('absint', array_filter($values, 'is_scalar'))))), 0, 30);
    }
    if (isset($_GET['pc_blog_sort']) && is_string($_GET['pc_blog_sort']) && in_array($_GET['pc_blog_sort'], ['newest', 'oldest', 'name'], true)) {
        $filters['pc_blog_sort'] = $_GET['pc_blog_sort'];
    }
    return $filters;
}

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    if ($query->get('popcorn_blog')) {
        $query->set('post_type', 'post');
        $query->is_home = false;
        $query->is_archive = true;
        $query->is_post_type_archive = true;
    }
    if (!popcorn_core_is_blog_archive($query)) {
        return;
    }
    $query->set('post_type', 'post');
    $filters = popcorn_core_blog_filters();
    $order = $filters['pc_blog_sort'] === 'oldest' ? 'ASC' : 'DESC';
    $query->set('orderby', $filters['pc_blog_sort'] === 'name' ? ['title' => 'ASC', 'ID' => 'ASC'] : ['date' => $order, 'ID' => $order]);
    $tax_query = (array) $query->get('tax_query');
    foreach (['pc_blog_category' => 'category', 'pc_blog_tag' => 'post_tag'] as $key => $taxonomy) {
        if ($filters[$key] && !$query->is_category() && !$query->is_tag()) {
            $tax_query[] = ['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $filters[$key]];
        }
    }
    if ($tax_query) {
        $tax_query['relation'] = 'AND';
        $query->set('tax_query', $tax_query);
    }
}, 20);
