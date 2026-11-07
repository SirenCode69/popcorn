<?php
defined('ABSPATH') || exit;

function popcorn_get_home_news($limit = 10) {
    return get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => min(10, max(1, absint($limit))),
        'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
        'has_password' => false,
        'ignore_sticky_posts' => true,
        'no_found_rows' => true,
    ]);
}

function popcorn_get_new_series($limit = 12) {
    global $wpdb;
    $limit = min(100, max(1, absint($limit)));
    $table = popcorn_core_table('series');
    $items = $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_type='popcorn_series' AND p.post_status='publish' ORDER BY p.post_date DESC,p.ID DESC LIMIT %d", $limit));
    foreach ($items as $item) {
        $item->popcorn_type = 'series';
    }
    return $items;
}

function popcorn_get_updated_series($limit = 12) {
    global $wpdb;
    $limit = min(100, max(1, absint($limit)));
    $table = popcorn_core_table('series');
    $items = $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_type='popcorn_series' AND p.post_status='publish' ORDER BY p.post_modified DESC,p.ID DESC LIMIT %d", $limit));
    foreach ($items as $item) {
        $item->popcorn_type = 'series';
    }
    return $items;
}

function popcorn_get_featured_items($limit = 10) {
    static $cache = [];
    $limit = min(10, max(1, absint($limit)));
    if (isset($cache[$limit])) {
        return $cache[$limit];
    }
    global $wpdb;
    $items = [];
    foreach (['movie', 'series'] as $type) {
        $table = popcorn_core_table($type);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_type=%s AND p.post_status='publish' WHERE i.is_featured=1 ORDER BY i.updated_at DESC,i.id DESC LIMIT %d", 'popcorn_' . $type, $limit));
        foreach ($rows as $row) {
            $row->popcorn_type = $type;
            $items[] = $row;
        }
    }
    usort($items, static function ($a, $b) {
        $date = strcmp((string) $b->updated_at, (string) $a->updated_at);
        return $date ?: ((int) $b->post_id <=> (int) $a->post_id);
    });
    return $cache[$limit] = array_slice($items, 0, $limit);
}

function popcorn_get_popular_actors($limit = 12) {
    static $cache = [];
    $limit = min(50, max(1, absint($limit)));
    if (isset($cache[$limit])) {
        return $cache[$limit];
    }
    global $wpdb;
    $table = popcorn_core_table('person');
    if (function_exists('pvc_get_post_views')) {
        add_filter('posts_clauses', 'popcorn_core_popular_actor_clauses', 20, 2);
        $posts = get_posts([
            'post_type' => 'popcorn_person',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => ['post_views' => 'DESC', 'date' => 'DESC'],
            'views_query' => ['hide_empty' => false],
            'no_found_rows' => true,
            'suppress_filters' => false,
            'popcorn_popular_actors' => true,
        ]);
        remove_filter('posts_clauses', 'popcorn_core_popular_actor_clauses', 20);
        if (!$posts) {
            return $cache[$limit] = [];
        }
        $post_ids = array_map('absint', wp_list_pluck($posts, 'ID'));
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i WHERE i.post_id IN ($placeholders)", $post_ids));
        $by_post = [];
        foreach ($rows as $row) {
            $by_post[(int) $row->post_id] = $row;
        }
        $actors = [];
        foreach ($post_ids as $post_id) {
            if (isset($by_post[$post_id])) {
                $actors[] = $by_post[$post_id];
            }
        }
        return $cache[$limit] = $actors;
    }
    return $cache[$limit] = $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_type='popcorn_person' AND p.post_status='publish' WHERE i.profession=%s ORDER BY i.popularity DESC,i.updated_at DESC,i.id DESC LIMIT %d", 'Acting', $limit));
}

function popcorn_core_popular_actor_clauses($clauses, $query) {
    if (!$query->get('popcorn_popular_actors')) {
        return $clauses;
    }
    global $wpdb;
    $table = popcorn_core_table('person');
    $clauses['join'] .= " INNER JOIN $table pc_actor ON pc_actor.post_id = {$wpdb->posts}.ID";
    $clauses['where'] .= $wpdb->prepare(' AND pc_actor.profession = %s', 'Acting');
    return $clauses;
}
