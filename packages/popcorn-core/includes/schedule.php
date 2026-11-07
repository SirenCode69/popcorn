<?php
defined('ABSPATH') || exit;

function popcorn_core_schedule_days() {
    return ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
}

function popcorn_core_schedule_today() {
    return ((int) current_datetime()->format('w') + 1) % 7;
}

add_action('init', function () {
    register_post_type('popcorn_schedule', [
        'labels' => [
            'name' => 'جدول پخش هفتگی', 'singular_name' => 'برنامهٔ پخش',
            'add_new' => 'افزودن برنامه', 'add_new_item' => 'افزودن برنامهٔ پخش',
            'edit_item' => 'ویرایش برنامهٔ پخش', 'new_item' => 'برنامهٔ پخش جدید',
            'search_items' => 'جست‌وجوی برنامه‌ها', 'not_found' => 'برنامه‌ای ثبت نشده است.',
            'not_found_in_trash' => 'برنامه‌ای در زباله‌دان نیست.',
        ],
        'public' => false, 'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=popcorn_series',
        'supports' => false, 'capability_type' => 'post', 'map_meta_cap' => true,
        'delete_with_user' => false,
    ]);
}, 6);

add_action('init', function () {
    if (get_stylesheet() !== 'popcorn' || get_option('popcorn_schedule_page_initialized')) {
        return;
    }
    $page = get_page_by_path('schedule');
    if (!$page) {
        $id = wp_insert_post([
            'post_type' => 'page', 'post_status' => 'publish',
            'post_title' => 'جدول پخش هفتگی', 'post_name' => 'schedule',
            'comment_status' => 'closed', 'ping_status' => 'closed',
            'meta_input' => ['_wp_page_template' => 'page-schedule.php'],
        ], true);
        if (is_wp_error($id) || !$id) {
            return;
        }
        update_option('popcorn_schedule_page_id', $id, false);
    } elseif (get_page_template_slug($page->ID) === 'page-schedule.php' || !$page->post_content) {
        update_post_meta($page->ID, '_wp_page_template', 'page-schedule.php');
        update_option('popcorn_schedule_page_id', $page->ID, false);
    }
    update_option('popcorn_schedule_page_initialized', 1, false);
}, 30);

function popcorn_core_schedule_series_ended($series_id) {
    $series = popcorn_core_get_by_post('series', $series_id);
    $status = popcorn_core_persian_production_status($series->production_status ?? '', 'series');
    return str_replace(['‌', 'ي', 'ك'], [' ', 'ی', 'ک'], trim($status)) === 'پایان یافته';
}

function popcorn_core_schedule_series_options($query = '') {
    global $wpdb;
    $table = popcorn_core_table('series');
    $like = '%' . $wpdb->esc_like($query) . '%';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT p.ID,p.post_title,p.post_status,s.original_title,s.poster_id,s.poster_path FROM {$wpdb->posts} p LEFT JOIN $table s ON s.post_id=p.ID WHERE p.post_type='popcorn_series' AND p.post_status IN ('publish','draft','pending','future','private') AND LOWER(TRIM(REPLACE(REPLACE(REPLACE(COALESCE(s.production_status,''),'‌',' '),'ي','ی'),'ك','ک'))) NOT IN ('ended','پایان یافته') AND (p.post_title LIKE %s OR s.title LIKE %s OR s.original_title LIKE %s OR s.english_title LIKE %s) ORDER BY p.post_title ASC LIMIT 30", $like, $like, $like, $like));
    return array_values(array_filter($rows, static function ($series) { return current_user_can('edit_post', $series->ID); }));
}

function popcorn_core_schedule_series_cover($series_id, $row) {
    $image_id = get_post_thumbnail_id($series_id) ?: absint($row->poster_id ?? 0);
    if ($image_id) {
        return wp_get_attachment_image($image_id, 'thumbnail', false, ['alt' => '', 'loading' => 'lazy']);
    }
    $url = popcorn_core_editor_image_url((string) ($row->poster_path ?? ''), 0, get_post_status($series_id) === 'publish');
    return $url ? '<img src="' . esc_url($url) . '" alt="" width="44" height="66" loading="lazy" decoding="async">' : '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>';
}

add_action('wp_ajax_popcorn_schedule_lookup', function () {
    if (!current_user_can('edit_posts')) { wp_send_json_error(['message' => 'دسترسی مجاز نیست.'], 403); }
    check_ajax_referer('popcorn_schedule_lookup', 'nonce');
    $query = isset($_POST['query']) && is_string($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
    $query = function_exists('mb_substr') ? mb_substr($query, 0, 100) : substr($query, 0, 100);
    $items = [];
    foreach (popcorn_core_schedule_series_options($query) as $row) {
        $items[] = ['id' => (int) $row->ID, 'title' => $row->post_title, 'persian' => (string) $row->original_title, 'cover' => popcorn_core_schedule_series_cover($row->ID, $row)];
    }
    wp_send_json_success(['items' => $items]);
});

add_action('add_meta_boxes_popcorn_schedule', function () {
    add_meta_box('popcorn-schedule', 'برنامهٔ پخش سریال', function ($post) {
        wp_nonce_field('popcorn_schedule_save', 'popcorn_schedule_nonce');
        $series_id = absint(get_post_meta($post->ID, '_popcorn_schedule_series', true));
        $day = get_post_meta($post->ID, '_popcorn_schedule_day', true);
        $active = get_post_meta($post->ID, '_popcorn_schedule_active', true);
        $series = $series_id ? popcorn_core_get_by_post('series', $series_id) : null;
        $selected_title = $series_id ? get_post_field('post_title', $series_id) : '';
        echo '<div class="pc-editor"><p>یک سریال می‌تواند در چند روز برنامه داشته باشد. سریال‌های پایان‌یافته در فهرست انتخاب نمایش داده نمی‌شوند.</p><table class="form-table"><tbody><tr><th><label for="pc-schedule-search">سریال</label></th><td><div class="pc-schedule-picker" data-lookup-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-lookup-nonce="' . esc_attr(wp_create_nonce('popcorn_schedule_lookup')) . '"><span class="pc-schedule-picker__selected-cover" data-selected-cover' . (!$series_id ? ' hidden' : '') . '>' . ($series_id ? popcorn_core_schedule_series_cover($series_id, $series) : '') . '</span><input id="pc-schedule-search" type="search" value="' . esc_attr($selected_title) . '" placeholder="برای انتخاب سریال کلیک کنید یا جست‌وجو کنید" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="pc-schedule-results" aria-expanded="false" required><input id="pc-schedule-series" type="hidden" name="popcorn_schedule[series]" value="' . ($series_id ?: '') . '"><button type="button" class="pc-schedule-picker__clear" aria-label="پاک کردن سریال انتخاب‌شده"' . (!$series_id ? ' hidden' : '') . '>×</button><div id="pc-schedule-results" class="pc-schedule-picker__results" role="listbox" hidden></div><p class="description" data-picker-status role="status"></p></div></td></tr><tr><th><label for="pc-schedule-day">روز پخش</label></th><td><select id="pc-schedule-day" name="popcorn_schedule[day]">';
        foreach (popcorn_core_schedule_days() as $index => $label) {
            echo '<option value="' . $index . '"' . selected($day === '' ? popcorn_core_schedule_today() : (int) $day, $index, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr><tr><th><label for="pc-schedule-order">ترتیب نمایش</label></th><td><input type="number" min="0" step="1" id="pc-schedule-order" name="popcorn_schedule[order]" value="' . absint(get_post_meta($post->ID, '_popcorn_schedule_order', true)) . '" class="small-text"><p class="description">عدد کمتر زودتر نمایش داده می‌شود.</p></td></tr><tr><th>وضعیت نمایش</th><td><label><input type="checkbox" name="popcorn_schedule[active]" value="1"' . checked($active === '' || $active === '1', true, false) . '> فعال</label></td></tr></tbody></table></div>';
    }, 'popcorn_schedule', 'normal', 'high');
});

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'popcorn_schedule' && $screen->base === 'post') {
        $css = dirname(__DIR__) . '/assets/css/schedule-admin.css';
        wp_enqueue_style('popcorn-schedule-admin', plugins_url('assets/css/schedule-admin.css', dirname(__DIR__) . '/popcorn-core.php'), [], filemtime($css));
        $file = dirname(__DIR__) . '/assets/js/schedule-admin.js';
        wp_enqueue_script('popcorn-schedule-admin', plugins_url('assets/js/schedule-admin.js', dirname(__DIR__) . '/popcorn-core.php'), [], filemtime($file), true);
    }
});

add_action('admin_notices', function () {
    $screen = get_current_screen();
    $page_id = absint(get_option('popcorn_schedule_page_id'));
    if ($screen && $screen->post_type === 'popcorn_schedule' && $screen->base === 'edit' && get_post_status($page_id) === 'publish') {
        echo '<div class="notice notice-info"><p>برنامهٔ هر روز را با افزودن سریال ثبت کنید. ';
        echo '<a href="' . esc_url(get_permalink($page_id)) . '" target="_blank" rel="noopener">مشاهدهٔ جدول پخش سایت</a>';
        echo ' · این برگه از بخش «نمایش ← فهرست‌ها» قابل افزودن به منوی سایت است.</p></div>';
    }
});

add_action('save_post_popcorn_schedule', function ($id) {
    if (wp_is_post_autosave($id) || wp_is_post_revision($id) || !current_user_can('edit_post', $id)
        || !isset($_POST['popcorn_schedule_nonce']) || !is_string($_POST['popcorn_schedule_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['popcorn_schedule_nonce'])), 'popcorn_schedule_save')) {
        return;
    }
    $input = isset($_POST['popcorn_schedule']) && is_array($_POST['popcorn_schedule']) ? wp_unslash($_POST['popcorn_schedule']) : [];
    $series_id = isset($input['series']) && is_scalar($input['series']) ? absint($input['series']) : 0;
    $day = isset($input['day']) && is_scalar($input['day']) ? (string) $input['day'] : '';
    if (get_post_type($series_id) !== 'popcorn_series' || popcorn_core_schedule_series_ended($series_id) || !current_user_can('edit_post', $series_id) || !preg_match('/^[0-6]$/D', $day)) {
        update_post_meta($id, '_popcorn_schedule_active', '0');
        return;
    }
    update_post_meta($id, '_popcorn_schedule_series', $series_id);
    update_post_meta($id, '_popcorn_schedule_day', (int) $day);
    update_post_meta($id, '_popcorn_schedule_order', isset($input['order']) && is_scalar($input['order']) ? absint($input['order']) : 0);
    update_post_meta($id, '_popcorn_schedule_active', isset($input['active']) && $input['active'] === '1' ? '1' : '0');
    global $wpdb;
    $wpdb->update($wpdb->posts, ['post_title' => get_post_field('post_title', $series_id) . ' — ' . popcorn_core_schedule_days()[(int) $day]], ['ID' => $id]);
    clean_post_cache($id);
});

add_filter('manage_popcorn_schedule_posts_columns', function ($columns) {
    return ['cb' => $columns['cb'], 'title' => 'سریال', 'schedule_day' => 'روز پخش', 'schedule_order' => 'ترتیب نمایش', 'schedule_active' => 'وضعیت نمایش', 'date' => 'تاریخ'];
});

add_action('manage_popcorn_schedule_posts_custom_column', function ($column, $id) {
    if ($column === 'schedule_day') {
        $day = get_post_meta($id, '_popcorn_schedule_day', true);
        echo esc_html($day !== '' ? (popcorn_core_schedule_days()[(int) $day] ?? '—') : '—');
    } elseif ($column === 'schedule_order') {
        echo absint(get_post_meta($id, '_popcorn_schedule_order', true));
    } elseif ($column === 'schedule_active') {
        echo get_post_meta($id, '_popcorn_schedule_active', true) === '1' ? 'فعال' : 'غیرفعال';
    }
}, 10, 2);

add_action('restrict_manage_posts', function ($type) {
    if ($type !== 'popcorn_schedule') {
        return;
    }
    $selected = isset($_GET['schedule_day']) && is_scalar($_GET['schedule_day']) ? sanitize_text_field(wp_unslash($_GET['schedule_day'])) : '';
    echo '<select name="schedule_day" aria-label="روز پخش"><option value="">همهٔ روزها</option>';
    foreach (popcorn_core_schedule_days() as $index => $day) {
        echo '<option value="' . $index . '"' . selected($selected, (string) $index, false) . '>' . esc_html($day) . '</option>';
    }
    echo '</select>';
    $active = isset($_GET['schedule_active']) && is_scalar($_GET['schedule_active']) ? sanitize_text_field(wp_unslash($_GET['schedule_active'])) : '';
    echo '<select name="schedule_active" aria-label="وضعیت نمایش"><option value="">همهٔ وضعیت‌ها</option><option value="1"' . selected($active, '1', false) . '>فعال</option><option value="0"' . selected($active, '0', false) . '>غیرفعال</option></select>';
});

add_filter('manage_edit-popcorn_schedule_sortable_columns', function ($columns) {
    $columns['schedule_day'] = 'schedule_day';
    $columns['schedule_order'] = 'schedule_order';
    return $columns;
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'popcorn_schedule') {
        return;
    }
    $meta = [];
    if (isset($_GET['schedule_day']) && is_string($_GET['schedule_day']) && preg_match('/^[0-6]$/D', $_GET['schedule_day'])) {
        $meta[] = ['key' => '_popcorn_schedule_day', 'value' => (int) $_GET['schedule_day']];
    }
    if (isset($_GET['schedule_active']) && is_string($_GET['schedule_active']) && preg_match('/^[01]$/D', $_GET['schedule_active'])) {
        $meta[] = ['key' => '_popcorn_schedule_active', 'value' => $_GET['schedule_active']];
    }
    if ($meta) {
        $query->set('meta_query', $meta);
    }
    if (in_array($query->get('orderby'), ['schedule_day', 'schedule_order'], true)) {
        $query->set('meta_key', $query->get('orderby') === 'schedule_day' ? '_popcorn_schedule_day' : '_popcorn_schedule_order');
        $query->set('orderby', 'meta_value_num');
    }
});

add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type === 'popcorn_schedule') {
        unset($actions['inline hide-if-no-js']);
    }
    return $actions;
}, 10, 2);

function popcorn_core_schedule_items() {
    $days = array_fill(0, 7, []);
    $entries = get_posts([
        'post_type' => 'popcorn_schedule', 'post_status' => 'publish', 'posts_per_page' => -1,
        'meta_query' => [['key' => '_popcorn_schedule_active', 'value' => '1']],
        'orderby' => 'ID', 'order' => 'ASC', 'update_post_term_cache' => false,
    ]);
    usort($entries, static function ($a, $b) {
        return (absint(get_post_meta($a->ID, '_popcorn_schedule_order', true)) <=> absint(get_post_meta($b->ID, '_popcorn_schedule_order', true))) ?: ($a->ID <=> $b->ID);
    });
    $ids = array_values(array_unique(array_filter(array_map(static function ($entry) {
        return absint(get_post_meta($entry->ID, '_popcorn_schedule_series', true));
    }, $entries))));
    if (!$ids) {
        return $days;
    }
    $posts = get_posts(['post_type' => 'popcorn_series', 'post_status' => 'publish', 'post__in' => $ids, 'posts_per_page' => count($ids), 'has_password' => false, 'update_post_term_cache' => false]);
    $items = [];
    foreach (popcorn_core_catalog_items($posts) as $item) {
        $items[(int) $item->post_id] = $item;
    }
    foreach ($entries as $entry) {
        $day = get_post_meta($entry->ID, '_popcorn_schedule_day', true);
        $series_id = absint(get_post_meta($entry->ID, '_popcorn_schedule_series', true));
        if (preg_match('/^[0-6]$/D', (string) $day) && isset($items[$series_id]) && !isset($days[(int) $day][$series_id])) {
            $days[(int) $day][$series_id] = $items[$series_id];
        }
    }
    return array_map('array_values', $days);
}
