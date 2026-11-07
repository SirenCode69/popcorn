<?php
defined('ABSPATH') || exit;

function popcorn_core_content_list_columns($columns, $type) {
    $result = [];
    foreach ($columns as $key => $label) {
        if ($key === 'tags' || $key === 'taxonomy-popcorn_genre') {
            continue;
        }
        if ($key === 'title') {
            $result['popcorn_image'] = 'تصویر';
        }
        $result[$key] = $label;
        if ($key === 'title') {
            $result['popcorn_genre'] = 'ژانر';
            $result['popcorn_country'] = 'کشور';
            $result['popcorn_language'] = 'زبان';
            $result['popcorn_status'] = 'وضعیت پخش';
            if ($type === 'series') {
                $result['popcorn_episodes'] = 'فصل / قسمت';
            }
        }
    }
    return $result;
}

function popcorn_core_content_list_item($type, $post_id) {
    static $items = [];
    $key = $type . ':' . $post_id;
    if (!array_key_exists($key, $items)) {
        $items[$key] = popcorn_core_get_by_post($type, $post_id);
    }
    return $items[$key];
}

function popcorn_core_content_list_column($column, $post_id, $type) {
    if (!in_array($column, ['popcorn_image', 'popcorn_genre', 'popcorn_country', 'popcorn_language', 'popcorn_status', 'popcorn_episodes'], true)) {
        return;
    }
    if ($column === 'popcorn_genre') {
        $terms = get_the_terms($post_id, 'popcorn_genre');
        if (!$terms || is_wp_error($terms)) {
            echo '—';
            return;
        }
        $links = [];
        foreach ($terms as $term) {
            $url = add_query_arg(['post_type' => get_post_type($post_id), 'popcorn_genre_filter' => $term->term_id], admin_url('edit.php'));
            $links[] = '<a href="' . esc_url($url) . '">' . esc_html($term->name) . '</a>';
        }
        echo implode('، ', $links);
        return;
    }
    $item = popcorn_core_content_list_item($type, $post_id);
    if ($column === 'popcorn_image') {
        $image_id = get_post_thumbnail_id($post_id) ?: absint($item->poster_id ?? 0);
        $url = popcorn_core_editor_image_url((string) ($item->poster_path ?? ''), $image_id, get_post_status($post_id) === 'publish');
        echo $url ? '<img class="pc-content-list__image" src="' . esc_url($url) . '"' . popcorn_core_image_fallback_attr($url) . ' alt="" width="52" height="70" loading="lazy" decoding="async">' : '<span class="pc-content-list__empty" aria-label="بدون تصویر">—</span>';
    } elseif ($column === 'popcorn_country') {
        echo esc_html(popcorn_core_persian_country($item->country ?? '') ?: '—');
    } elseif ($column === 'popcorn_language') {
        echo esc_html(popcorn_core_persian_language($item->language ?? '') ?: '—');
    } elseif ($column === 'popcorn_status') {
        echo esc_html(popcorn_core_persian_production_status($item->production_status ?? '', $type) ?: '—');
    } elseif ($column === 'popcorn_episodes') {
        $seasons = absint($item->season_count ?? 0);
        $episodes = absint($item->episode_count ?? 0);
        echo $seasons || $episodes ? esc_html($seasons . ' فصل / ' . $episodes . ' قسمت') : '—';
    }
}

function popcorn_core_content_list_filter_value($key) {
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
}

function popcorn_core_content_list_filter_options($type, $field) {
    global $wpdb;
    $columns = ['country', 'language', 'production_status'];
    if (!in_array($type, ['movie', 'series'], true) || !in_array($field, $columns, true)) {
        return [];
    }
    $table = popcorn_core_table($type);
    return $wpdb->get_col("SELECT DISTINCT $field FROM $table WHERE $field <> '' ORDER BY $field ASC");
}

function popcorn_core_content_list_filters($post_type, $which = 'top') {
    if (!in_array($post_type, ['popcorn_movie', 'popcorn_series'], true) || $which !== 'top') {
        return;
    }
    $type = substr($post_type, 8);
    $category = absint(popcorn_core_content_list_filter_value('popcorn_categury_filter'));
    wp_dropdown_categories(['taxonomy' => 'popcorn_categury', 'name' => 'popcorn_categury_filter', 'id' => 'popcorn-category-filter', 'class' => 'postform', 'show_option_none' => 'همهٔ دسته‌بندی‌ها', 'option_none_value' => 0, 'hide_empty' => false, 'hierarchical' => true, 'value_field' => 'term_id', 'selected' => $category]);
    $genre = absint(popcorn_core_content_list_filter_value('popcorn_genre_filter'));
    wp_dropdown_categories(['taxonomy' => 'popcorn_genre', 'name' => 'popcorn_genre_filter', 'id' => 'popcorn-genre-filter', 'class' => 'postform', 'show_option_all' => 'همهٔ ژانرها', 'hide_empty' => false, 'hierarchical' => true, 'value_field' => 'term_id', 'selected' => $genre]);
    $fields = ['country' => ['label' => 'کشورها', 'normalizer' => 'popcorn_core_persian_country'], 'language' => ['label' => 'زبان‌ها', 'normalizer' => 'popcorn_core_persian_language'], 'production_status' => ['label' => 'وضعیت‌های پخش', 'normalizer' => 'popcorn_core_persian_production_status']];
    foreach ($fields as $field => $settings) {
        $key = $field === 'production_status' ? 'popcorn_status' : 'popcorn_' . $field;
        $selected = popcorn_core_content_list_filter_value($key);
        echo '<select class="postform" name="' . esc_attr($key) . '" aria-label="فیلتر ' . esc_attr($settings['label']) . '"><option value="">همهٔ ' . esc_html($settings['label']) . '</option>';
        foreach (popcorn_core_content_list_filter_options($type, $field) as $value) {
            $label = $field === 'production_status' ? $settings['normalizer']($value, $type) : $settings['normalizer']($value);
            echo '<option value="' . esc_attr($value) . '"' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
}
add_action('restrict_manage_posts', 'popcorn_core_content_list_filters', 10, 2);

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query() || !in_array($query->get('post_type'), ['popcorn_movie', 'popcorn_series'], true)) {
        return;
    }
    foreach (['popcorn_genre_filter' => 'popcorn_genre', 'popcorn_categury_filter' => 'popcorn_categury'] as $key => $taxonomy) {
        $term_id = absint(popcorn_core_content_list_filter_value($key));
        if ($term_id && term_exists($term_id, $taxonomy)) {
            $tax_query = (array) $query->get('tax_query');
            $clause = ['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => [$term_id]];
            $query->set('tax_query', $tax_query ? ['relation' => 'AND', $tax_query, $clause] : [$clause]);
        }
    }
});

function popcorn_core_content_list_filter_query($clauses, $query) {
    if (!is_admin() || !$query->is_main_query() || !in_array($query->get('post_type'), ['popcorn_movie', 'popcorn_series'], true)) {
        return $clauses;
    }
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, ['edit-popcorn_movie', 'edit-popcorn_series'], true)) {
        return $clauses;
    }
    global $wpdb;
    $conditions = [];
    foreach (['country' => 'popcorn_country', 'language' => 'popcorn_language', 'production_status' => 'popcorn_status'] as $field => $key) {
        $value = popcorn_core_content_list_filter_value($key);
        if ($value !== '') {
            $conditions[] = $wpdb->prepare("pc_item.$field = %s", $value);
        }
    }
    if ($conditions) {
        $type = substr($query->get('post_type'), 8);
        $table = popcorn_core_table($type);
        $clauses['where'] .= " AND EXISTS (SELECT 1 FROM $table pc_item WHERE pc_item.post_id = {$wpdb->posts}.ID AND " . implode(' AND ', $conditions) . ')';
    }
    return $clauses;
}
add_filter('posts_clauses', 'popcorn_core_content_list_filter_query', 10, 2);

foreach (['movie', 'series'] as $popcorn_content_list_type) {
    add_filter('manage_popcorn_' . $popcorn_content_list_type . '_posts_columns', function ($columns) use ($popcorn_content_list_type) {
        return popcorn_core_content_list_columns($columns, $popcorn_content_list_type);
    });
    add_action('manage_popcorn_' . $popcorn_content_list_type . '_posts_custom_column', function ($column, $post_id) use ($popcorn_content_list_type) {
        popcorn_core_content_list_column($column, $post_id, $popcorn_content_list_type);
    }, 10, 2);
}

add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if ($hook !== 'edit.php' || !$screen || !in_array($screen->id, ['edit-popcorn_movie', 'edit-popcorn_series'], true)) {
        return;
    }
    $root = dirname(__DIR__);
    wp_enqueue_style('popcorn-content-list', plugins_url('assets/css/content-list.css', $root . '/popcorn-core.php'), [], filemtime($root . '/assets/css/content-list.css'));
});
