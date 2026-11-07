<?php
defined('ABSPATH') || exit;

function popcorn_genre_slider_items() {
    static $items = null;
    if ($items !== null) {
        return $items;
    }
    $items = [];
    if (!taxonomy_exists('popcorn_genre')) {
        return $items;
    }
    $terms = get_terms(['taxonomy' => 'popcorn_genre', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC']);
    if (is_wp_error($terms) || !$terms) {
        return $items;
    }
    $images = [];
    $missing = [];
    foreach ($terms as $term) {
        $images[$term->term_id] = absint(get_term_meta($term->term_id, 'popcorn_genre_image_id', true));
        if (!$images[$term->term_id]) {
            $missing[$term->term_id] = true;
        }
    }
    if ($missing) {
        $posts = new WP_Query([
            'post_type' => ['popcorn_movie', 'popcorn_series'],
            'post_status' => 'publish',
            'posts_per_page' => 60,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'tax_query' => [['taxonomy' => 'popcorn_genre', 'field' => 'term_id', 'terms' => array_keys($missing), 'include_children' => false]],
            'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']],
        ]);
        foreach ($posts->posts as $post) {
            $image_id = get_post_thumbnail_id($post->ID);
            $post_terms = get_the_terms($post->ID, 'popcorn_genre');
            if (!$image_id || is_wp_error($post_terms) || !$post_terms) {
                continue;
            }
            foreach ($post_terms as $term) {
                if (isset($missing[$term->term_id])) {
                    $images[$term->term_id] = $image_id;
                    unset($missing[$term->term_id]);
                }
            }
            if (!$missing) {
                break;
            }
        }
    }
    $image_ids = array_values(array_unique(array_filter($images)));
    if ($image_ids) {
        get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'post__in' => $image_ids, 'posts_per_page' => count($image_ids), 'update_post_meta_cache' => true, 'update_post_term_cache' => false]);
    }
    foreach ($terms as $term) {
        $url = get_term_link($term);
        if (!is_wp_error($url)) {
            $items[] = ['term' => $term, 'url' => $url, 'image_id' => $images[$term->term_id]];
        }
    }
    return $items;
}
