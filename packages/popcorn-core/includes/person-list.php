<?php
defined('ABSPATH') || exit;

function popcorn_core_person_list_columns($columns) {
    $result = [];
    foreach ($columns as $key => $label) {
        if ($key === 'tags') {
            continue;
        }
        if ($key === 'title') {
            $result['popcorn_image'] = 'تصویر';
        }
        $result[$key] = $label;
        if ($key === 'title') {
            $result['popcorn_persian_name'] = 'نام فارسی';
            $result['popcorn_gender'] = 'جنسیت';
            $result['popcorn_profession'] = 'حرفه';
        }
    }
    return $result;
}
add_filter('manage_popcorn_person_posts_columns', 'popcorn_core_person_list_columns');

function popcorn_core_person_list_item($post_id) {
    static $items = [];
    if (!array_key_exists($post_id, $items)) {
        $items[$post_id] = popcorn_core_get_by_post('person', $post_id);
    }
    return $items[$post_id];
}

function popcorn_core_person_list_column($column, $post_id) {
    if (!in_array($column, ['popcorn_image', 'popcorn_persian_name', 'popcorn_gender', 'popcorn_profession'], true)) {
        return;
    }
    $item = popcorn_core_person_list_item($post_id);
    if ($column === 'popcorn_image') {
        $image_id = get_post_thumbnail_id($post_id) ?: absint($item->profile_id ?? 0);
        $url = popcorn_core_editor_image_url((string) ($item->profile_path ?? ''), $image_id, get_post_status($post_id) === 'publish');
        echo $url ? '<img class="pc-person-list__image" src="' . esc_url($url) . '"' . popcorn_core_image_fallback_attr($url) . ' alt="" width="52" height="70" loading="lazy" decoding="async">' : '<span class="pc-person-list__empty" aria-label="بدون تصویر">—</span>';
    } elseif ($column === 'popcorn_persian_name') {
        echo esc_html(trim((string) ($item->persian_name ?? '')) ?: '—');
    } elseif ($column === 'popcorn_gender') {
        $labels = popcorn_core_gender_labels();
        echo esc_html($labels[(string) ($item->gender ?? 0)] ?? $labels['0']);
    } else {
        echo esc_html(popcorn_core_profession_label((string) ($item->profession ?? '')));
    }
}
add_action('manage_popcorn_person_posts_custom_column', 'popcorn_core_person_list_column', 10, 2);

function popcorn_core_person_list_filter_value($key) {
    $value = isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    if ($key === 'popcorn_gender') {
        if ($value === '3') {
            return '0';
        }
        return array_key_exists($value, popcorn_core_gender_labels()) ? $value : '';
    }
    if ($key === 'popcorn_profession') {
        return $value === '__unknown' || ($value !== '' && array_key_exists($value, popcorn_core_profession_labels())) ? $value : '';
    }
    return '';
}

function popcorn_core_person_list_filters($post_type, $which = 'top') {
    if ($post_type !== 'popcorn_person' || $which !== 'top') {
        return;
    }
    $gender = popcorn_core_person_list_filter_value('popcorn_gender');
    $profession = popcorn_core_person_list_filter_value('popcorn_profession');
    echo '<select class="postform" name="popcorn_gender" aria-label="فیلتر جنسیت"><option value="">همهٔ جنسیت‌ها</option>';
    foreach (popcorn_core_gender_labels() as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($gender, (string) $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select><select class="postform" name="popcorn_profession" aria-label="فیلتر حرفه"><option value="">همهٔ حرفه‌ها</option><option value="__unknown"' . selected($profession, '__unknown', false) . '>نامشخص</option>';
    foreach (popcorn_core_profession_labels() as $value => $label) {
        if ($value !== '') {
            echo '<option value="' . esc_attr($value) . '"' . selected($profession, (string) $value, false) . '>' . esc_html($label) . '</option>';
        }
    }
    echo '</select>';
}
add_action('restrict_manage_posts', 'popcorn_core_person_list_filters', 10, 2);

function popcorn_core_person_list_filter_query($clauses, $query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'popcorn_person') {
        return $clauses;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-popcorn_person') {
        return $clauses;
    }
    $gender = popcorn_core_person_list_filter_value('popcorn_gender');
    $profession = popcorn_core_person_list_filter_value('popcorn_profession');
    if ($gender === '' && $profession === '') {
        return $clauses;
    }
    global $wpdb;
    $table = popcorn_core_table('person');
    $clauses['join'] .= " INNER JOIN $table pc_person_filter ON pc_person_filter.post_id = {$wpdb->posts}.ID";
    if ($gender !== '') {
        $clauses['where'] .= $gender === '0' ? ' AND pc_person_filter.gender IN (0,3)' : $wpdb->prepare(' AND pc_person_filter.gender = %d', (int) $gender);
    }
    if ($profession !== '') {
        $clauses['where'] .= $wpdb->prepare(' AND pc_person_filter.profession = %s', $profession === '__unknown' ? '' : $profession);
    }
    return $clauses;
}
add_filter('posts_clauses', 'popcorn_core_person_list_filter_query', 10, 2);

add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if ($hook !== 'edit.php' || !$screen || $screen->id !== 'edit-popcorn_person') {
        return;
    }
    $path = dirname(__DIR__) . '/assets/css/person-list.css';
    wp_enqueue_style('popcorn-person-list', plugins_url('assets/css/person-list.css', dirname(__DIR__) . '/popcorn-core.php'), [], filemtime($path));
});
