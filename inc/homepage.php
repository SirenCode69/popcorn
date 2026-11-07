<?php
defined('ABSPATH') || exit;

function popcorn_homepage_slider_items($source) {
    switch ($source) {
        case 'news':
            return function_exists('popcorn_get_home_news') ? popcorn_get_home_news(10) : [];
        case 'genres':
            return popcorn_genre_slider_items();
        case 'featured':
            return function_exists('popcorn_get_featured_items') ? popcorn_get_featured_items(10) : [];
        case 'series':
            return function_exists('popcorn_get_new_series') ? popcorn_get_new_series(12) : [];
        case 'updated-series':
            return function_exists('popcorn_get_updated_series') ? popcorn_get_updated_series(12) : [];
        case 'actors':
            return function_exists('popcorn_get_popular_actors') ? popcorn_get_popular_actors(12) : [];
        case 'movies':
            return function_exists('popcorn_get_items') ? popcorn_get_items('movie', 12) : [];
    }
    return [];
}

function popcorn_homepage_prime_slider_items($items, $card_features = false) {
    $post_ids = [];
    $image_ids = [];
    foreach ($items as $item) {
        if (is_object($item)) {
            $post_ids[] = absint($item->post_id ?? 0);
            foreach (['poster_id', 'backdrop_id', 'profile_id'] as $field) {
                $image_ids[] = absint($item->$field ?? 0);
            }
        } elseif (is_array($item)) {
            $image_ids[] = absint($item['image_id'] ?? 0);
        }
    }
    $post_ids = array_values(array_unique(array_filter($post_ids)));
    $image_ids = array_values(array_unique(array_filter($image_ids)));
    if ($post_ids) {
        get_posts(['post_type' => ['popcorn_movie', 'popcorn_series', 'popcorn_person'], 'post_status' => 'publish', 'post__in' => $post_ids, 'posts_per_page' => count($post_ids), 'update_post_meta_cache' => true, 'update_post_term_cache' => false]);
        if ($card_features) {
            popcorn_card_features(0, $items);
        }
    }
    if ($image_ids) {
        get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'post__in' => $image_ids, 'posts_per_page' => count($image_ids), 'update_post_meta_cache' => true, 'update_post_term_cache' => false]);
    }
}

add_action('rest_api_init', function () {
    register_rest_route('popcorn/v1', '/home-slider/(?P<source>genres|featured|series|updated-series|actors|movies|news)(?:/(?P<bucket>[0-9]+))?/?', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'popcorn_homepage_slider_rest',
        'permission_callback' => '__return_true',
    ]);
});

function popcorn_homepage_slider_rest($request) {
    $source = (string) $request['source'];
    $items = popcorn_homepage_slider_items($source);
    if ($source === 'actors' && function_exists('popcorn_core_person_work_counts')) {
        popcorn_core_person_work_counts(wp_list_pluck($items, 'id'));
    }
    popcorn_homepage_prime_slider_items($items, in_array($source, ['featured', 'series', 'updated-series', 'movies'], true));
    ob_start();
    get_template_part('template-parts/slider-track', null, ['items' => $items, 'card' => $source === 'news' ? 'blog-card' : ($source === 'actors' ? 'person-card' : ($source === 'genres' ? 'genre-card' : 'card')), 'sliding' => count($items) > 1]);
    $payload = ['html' => ob_get_clean(), 'count' => count($items)];
    $response = new WP_REST_Response($payload);
    if (!function_exists('popcorn_core_cache_plugin_active') || !popcorn_core_cache_plugin_active()) {
        foreach (wp_get_nocache_headers() as $name => $value) {
            if ($value !== false) {
                $response->header($name, $value);
            }
        }
    }
    return $response;
}
