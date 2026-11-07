<?php
defined('ABSPATH') || exit;

function popcorn_core_similar_titles($type, $post_id, $limit = 12) {
    if (!in_array($type, ['movie', 'series'], true)) {
        return [];
    }
    global $wpdb;
    $table = popcorn_core_table($type);
    $genres = wp_get_post_terms($post_id, 'popcorn_genre', ['fields' => 'ids']);
    $genres = is_wp_error($genres) ? [] : array_map('absint', $genres);
    $shared = '0';
    if ($genres) {
        $shared = '(SELECT COUNT(DISTINCT tt.term_id) FROM ' . $wpdb->term_relationships . ' tr INNER JOIN ' . $wpdb->term_taxonomy . " tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=p.ID AND tt.taxonomy='popcorn_genre' AND tt.term_id IN (" . implode(',', $genres) . '))';
    }
    $items = $wpdb->get_results($wpdb->prepare("SELECT i.*,$shared AS shared_genres FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE p.post_type=%s AND p.post_status='publish' AND p.ID<>%d ORDER BY shared_genres DESC,i.popularity DESC,p.post_date DESC,p.ID DESC LIMIT %d", 'popcorn_' . $type, absint($post_id), min(24, max(1, absint($limit)))));
    foreach ($items as $item) {
        $item->popcorn_type = $type;
    }
    return $items;
}
