<?php
defined('ABSPATH') || exit;

function popcorn_core_person_archive_filters() {
    $values = [];
    foreach (['q', 'role', 'gender', 'sort'] as $key) {
        $values[$key] = isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }
    $values['q'] = mb_substr($values['q'], 0, 100);
    $values['role'] = in_array($values['role'], ['actor', 'director'], true) ? $values['role'] : '';
    $values['gender'] = in_array($values['gender'], ['M', 'F'], true) ? $values['gender'] : '';
    $values['sort'] = in_array($values['sort'], ['popular', 'recent', 'name', 'youngest', 'oldest'], true) ? $values['sort'] : 'popular';
    return $values;
}

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('popcorn_person') || $query->is_feed()) {
        return;
    }
    $query->set('popcorn_person_archive', popcorn_core_person_archive_filters());
    $query->set('post_status', 'publish');
    $query->set('posts_per_page', 21);
});

add_filter('posts_clauses', function ($clauses, $query) {
    $filters = $query->get('popcorn_person_archive');
    if (!is_array($filters)) {
        return $clauses;
    }
    global $wpdb;
    $table = popcorn_core_table('person');
    $clauses['join'] .= " INNER JOIN $table pc_archive_person ON pc_archive_person.post_id = {$wpdb->posts}.ID";
    $professions = $filters['role'] === 'actor' ? ['Acting'] : ($filters['role'] === 'director' ? ['Directing'] : ['Acting', 'Directing']);
    $placeholders = implode(',', array_fill(0, count($professions), '%s'));
    $clauses['where'] .= $wpdb->prepare(" AND pc_archive_person.profession IN ($placeholders)", $professions);
    if ($filters['gender'] !== '') {
        $clauses['where'] .= $wpdb->prepare(' AND pc_archive_person.gender = %d', $filters['gender'] === 'M' ? 2 : 1);
    }
    if ($filters['q'] !== '') {
        $term = '%' . $wpdb->esc_like($filters['q']) . '%';
        $clauses['where'] .= $wpdb->prepare(" AND (pc_archive_person.name LIKE %s OR pc_archive_person.persian_name LIKE %s OR {$wpdb->posts}.post_title LIKE %s)", $term, $term, $term);
    }
    $orders = [
        'popular' => 'pc_archive_person.popularity DESC',
        'recent' => "{$wpdb->posts}.post_date DESC",
        'name' => "{$wpdb->posts}.post_title ASC",
        'youngest' => "(pc_archive_person.birth_date IS NULL OR pc_archive_person.birth_date = '0000-00-00') ASC, pc_archive_person.birth_date DESC",
        'oldest' => "(pc_archive_person.birth_date IS NULL OR pc_archive_person.birth_date = '0000-00-00') ASC, pc_archive_person.birth_date ASC",
    ];
    $clauses['orderby'] = ($orders[$filters['sort']] ?? $orders['popular']) . ", {$wpdb->posts}.ID DESC";
    return $clauses;
}, 20, 2);

function popcorn_core_person_archive_items($posts) {
    $ids = array_values(array_filter(array_map('absint', wp_list_pluck($posts, 'ID'))));
    if (!$ids) {
        return [];
    }
    global $wpdb;
    $table = popcorn_core_table('person');
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE post_id IN ($placeholders)", $ids));
    popcorn_core_person_work_counts(wp_list_pluck($rows, 'id'));
    $items = [];
    foreach ($rows as $row) {
        $items[(int) $row->post_id] = $row;
    }
    return $items;
}
