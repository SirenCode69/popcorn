<?php
defined('ABSPATH') || exit;

function popcorn_core_catalog_filters() {
    $filters = [];
    foreach (['genre', 'release_year', 'country', 'language', 'production_status', 'sort'] as $key) {
        $filters[$key] = isset($_GET[$key]) && is_string($_GET[$key]) ? mb_substr(sanitize_text_field(wp_unslash($_GET[$key])), 0, 100) : '';
    }
    $filters['genre'] = absint($filters['genre']);
    $filters['release_year'] = preg_match('/^[1-9][0-9]{3}$/D', $filters['release_year']) ? $filters['release_year'] : '';
    $filters['sort'] = in_array($filters['sort'], ['recent', 'updated', 'popular', 'rating', 'year', 'name', 'coming-soon'], true) ? $filters['sort'] : 'recent';
    return $filters;
}

function popcorn_core_is_catalog_archive($query = null) {
    $query = $query ?: $GLOBALS['wp_query'];
    if ($query->is_post_type_archive(['popcorn_movie', 'popcorn_series'])) {
        return true;
    }
    if ($query->is_author() || $query->is_date()) {
        return (bool) array_intersect((array) $query->get('post_type'), ['popcorn_movie', 'popcorn_series']);
    }
    if ($query->is_tax() || $query->is_category() || $query->is_tag()) {
        $term = $query->get_queried_object();
        $taxonomy = $term instanceof WP_Term ? get_taxonomy($term->taxonomy) : null;
        return $taxonomy && (bool) array_intersect($taxonomy->object_type, ['popcorn_movie', 'popcorn_series']);
    }
    return false;
}

function popcorn_core_catalog_source($type) {
    if (in_array($type, ['movie', 'series'], true)) {
        return popcorn_core_table($type);
    }
    $fields = 'post_id,country,language,production_status,release_date,is_coming_soon,popularity,imdb_rating,vote_average';
    return '(SELECT ' . $fields . ' FROM ' . popcorn_core_table('movie') . ' UNION ALL SELECT ' . $fields . ' FROM ' . popcorn_core_table('series') . ')';
}

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !popcorn_core_is_catalog_archive($query)) {
        return;
    }
    if (!$query->is_post_type_archive()) {
        $types = ['post', 'popcorn_movie', 'popcorn_series'];
        if ($query->is_category() || $query->is_tag() || $query->is_tax()) {
            $term = $query->get_queried_object();
            $taxonomy = $term instanceof WP_Term ? get_taxonomy($term->taxonomy) : null;
            if ($taxonomy) {
                $types = array_values(array_intersect($taxonomy->object_type, ['post', 'popcorn_movie', 'popcorn_series', 'popcorn_person']));
            }
        }
        $query->set('post_type', $types);
    }
    $filters = popcorn_core_catalog_filters();
    $query->set('popcorn_catalog_filters', $filters);
    $query->set('posts_per_page', 24);
    $query->set('ignore_sticky_posts', true);
    if (isset($_GET['pg']) && is_scalar($_GET['pg'])) {
        $query->set('paged', max(1, absint($_GET['pg'])));
    }
    if ($filters['genre']) {
        $tax_query = (array) $query->get('tax_query');
        $genre = ['taxonomy' => 'popcorn_genre', 'field' => 'term_id', 'terms' => [$filters['genre']]];
        $query->set('tax_query', $tax_query ? ['relation' => 'AND', $tax_query, $genre] : [$genre]);
    }
});

function popcorn_core_catalog_filter_options($type) {
    if (!in_array($type, ['movie', 'series', 'all'], true)) {
        return [];
    }
    global $wpdb;
    $table = popcorn_core_catalog_source($type);
    $types = $type === 'all' ? ['popcorn_movie', 'popcorn_series'] : ['popcorn_' . $type];
    $placeholders = implode(',', array_fill(0, count($types), '%s'));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT i.country,i.language,YEAR(i.release_date) AS release_year FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE p.post_type IN ($placeholders) AND p.post_status='publish'", $types));
    $statuses = $type === 'all' ? array_replace(popcorn_core_production_status_options('movie'), popcorn_core_production_status_options('series')) : popcorn_core_production_status_options($type);
    $options = [
        'genre' => ['label' => 'ژانر', 'values' => ['' => 'همهٔ ژانرها']],
        'release_year' => ['label' => 'سال ساخت', 'values' => ['' => 'همهٔ سال‌ها']],
        'country' => ['label' => 'کشور', 'values' => ['' => 'همهٔ کشورها']],
        'language' => ['label' => 'زبان', 'values' => ['' => 'همهٔ زبان‌ها']],
        'production_status' => ['label' => 'وضعیت پخش', 'values' => array_replace($statuses, ['' => 'همهٔ وضعیت‌ها'])],
        'sort' => ['label' => 'ترتیب نمایش', 'values' => ['recent' => 'جدیدترین‌ها', 'updated' => 'آخرین به‌روزرسانی', 'popular' => 'محبوب‌ترین‌ها', 'rating' => 'بیشترین امتیاز', 'year' => 'سال ساخت', 'name' => 'نام (الفبا)', 'coming-soon' => 'به‌زودی']],
    ];
    $genres = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT tt.term_id FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id=tt.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID=tr.object_id INNER JOIN $table i ON i.post_id=p.ID WHERE tt.taxonomy='popcorn_genre' AND p.post_type IN ($placeholders) AND p.post_status='publish'", $types));
    $terms = $genres ? get_terms(['taxonomy' => 'popcorn_genre', 'include' => array_map('absint', $genres), 'hide_empty' => false]) : [];
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $options['genre']['values'][$term->term_id] = $term->name;
        }
    }
    $years = [];
    $values = ['country' => [], 'language' => []];
    foreach ($rows as $row) {
        if ((int) $row->release_year > 0) {
            $years[(int) $row->release_year] = (string) $row->release_year;
        }
        foreach ($values as $field => $unused) {
            $parts = preg_split('/[,،]/u', $row->$field);
            foreach ($parts as $value) {
                $value = trim($value);
                if ($value !== '') {
                    $normalizer = 'popcorn_core_persian_' . $field;
                    $values[$field][$value] = $normalizer($value);
                }
            }
        }
    }
    krsort($years, SORT_NUMERIC);
    $options['release_year']['values'] += $years;
    foreach ($values as $field => $choices) {
        asort($choices, SORT_STRING);
        $options[$field]['values'] += $choices;
    }
    return $options;
}

function popcorn_core_catalog_archive($type, $filters, $page = 1) {
    if (!in_array($type, ['movie', 'series'], true)) {
        return ['query' => null, 'items' => []];
    }
    $args = ['post_type' => 'popcorn_' . $type, 'post_status' => 'publish', 'posts_per_page' => 24, 'paged' => max(1, absint($page)), 'ignore_sticky_posts' => true, 'popcorn_catalog_filters' => $filters];
    if ($filters['genre']) {
        $args['tax_query'] = [['taxonomy' => 'popcorn_genre', 'field' => 'term_id', 'terms' => [$filters['genre']]]];
    }
    $query = new WP_Query($args);
    return ['query' => $query, 'items' => popcorn_core_catalog_items($query->posts)];
}

function popcorn_core_catalog_items($posts) {
    global $wpdb;
    $by_post = [];
    foreach (['movie', 'series', 'person'] as $type) {
        $ids = [];
        foreach ($posts as $post) {
            if ($post->post_type === 'popcorn_' . $type) {
                $ids[] = (int) $post->ID;
            }
        }
        if (!$ids) {
            continue;
        }
        $table = popcorn_core_table($type);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i WHERE i.post_id IN ($placeholders)", $ids));
        foreach ($rows as $row) {
            $row->popcorn_type = $type;
            $by_post[(int) $row->post_id] = $row;
        }
    }
    $items = [];
    foreach ($posts as $post) {
        if (isset($by_post[$post->ID])) {
            $items[] = $by_post[$post->ID];
        }
    }
    return $items;
}

add_filter('posts_clauses', function ($clauses, $query) {
    $filters = $query->get('popcorn_catalog_filters');
    $post_type = $query->get('post_type');
    $types = (array) $post_type;
    if (!is_array($filters) || !array_intersect($types, ['popcorn_movie', 'popcorn_series'])) {
        return $clauses;
    }
    global $wpdb;
    $type = count($types) === 1 && in_array($types[0], ['popcorn_movie', 'popcorn_series'], true) ? substr($types[0], 8) : 'all';
    $columns = [];
    if ($type === 'all') {
        $movie_table = popcorn_core_table('movie');
        $series_table = popcorn_core_table('series');
        $clauses['join'] .= " LEFT JOIN $movie_table pc_archive_movie ON pc_archive_movie.post_id={$wpdb->posts}.ID AND {$wpdb->posts}.post_type='popcorn_movie' LEFT JOIN $series_table pc_archive_series ON pc_archive_series.post_id={$wpdb->posts}.ID AND {$wpdb->posts}.post_type='popcorn_series'";
    } else {
        $table = popcorn_core_table($type);
        $clauses['join'] .= " INNER JOIN $table pc_archive_title ON pc_archive_title.post_id={$wpdb->posts}.ID";
    }
    foreach (['country', 'language', 'production_status', 'release_date', 'is_coming_soon', 'popularity', 'imdb_rating', 'vote_average'] as $field) {
        $columns[$field] = $type === 'all' ? "COALESCE(pc_archive_movie.$field,pc_archive_series.$field)" : "pc_archive_title.$field";
    }
    foreach (['country', 'language'] as $field) {
        if ($filters[$field] !== '') {
            $clauses['where'] .= $wpdb->prepare(" AND FIND_IN_SET(%s, REPLACE(REPLACE({$columns[$field]},'،',','),', ',',')) > 0", $filters[$field]);
        }
    }
    if ($filters['production_status'] !== '') {
        $clauses['where'] .= $wpdb->prepare(" AND {$columns['production_status']}=%s", $filters['production_status']);
    }
    if ($filters['release_year'] !== '') {
        $clauses['where'] .= $wpdb->prepare(" AND {$columns['release_date']} >= %s AND {$columns['release_date']} <= %s", $filters['release_year'] . '-01-01', $filters['release_year'] . '-12-31');
    }
    if ($filters['sort'] === 'coming-soon') {
        $clauses['where'] .= " AND {$columns['is_coming_soon']}=1";
    }
    $orders = ['recent' => "{$wpdb->posts}.post_date DESC", 'updated' => "{$wpdb->posts}.post_modified DESC", 'popular' => "{$columns['popularity']} DESC", 'rating' => "CASE WHEN {$columns['imdb_rating']} > 0 THEN {$columns['imdb_rating']} ELSE {$columns['vote_average']} END DESC", 'year' => "{$columns['release_date']} DESC", 'name' => "{$wpdb->posts}.post_title ASC", 'coming-soon' => "({$columns['release_date']} IS NULL OR {$columns['release_date']}='0000-00-00') ASC, {$columns['release_date']} ASC"];
    $clauses['orderby'] = ($orders[$filters['sort']] ?? $orders['recent']) . ", {$wpdb->posts}.ID DESC";
    return $clauses;
}, 20, 2);
