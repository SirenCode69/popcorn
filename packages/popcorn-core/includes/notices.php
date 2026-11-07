<?php
defined('ABSPATH') || exit;

function popcorn_core_notice_post($post_id) {
    $post = get_post(absint($post_id));
    return $post && in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true) ? $post : null;
}

function popcorn_core_notice_clean_message($value) {
    if (!is_string($value)) {
        return new WP_Error('invalid_notice', 'متن اطلاعیه معتبر نیست.', ['status' => 400]);
    }
    $message = trim(sanitize_textarea_field($value));
    $length = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
    if ($message === '' || $length > 2000) {
        return new WP_Error('invalid_notice', 'متن اطلاعیه باید بین ۱ تا ۲۰۰۰ نویسه باشد.', ['status' => 400]);
    }
    return $message;
}

function popcorn_core_notice_rest_item($item) {
    return [
        'id' => (int) $item->id,
        'message' => $item->message,
        'is_active' => (bool) $item->is_active,
        'created_label' => mysql2date('j F Y، H:i', $item->created_at),
    ];
}

function popcorn_core_notice_rest_permission($request) {
    $post_id = absint($request['post_id'] ?? 0);
    if (isset($request['id'])) {
        global $wpdb;
        $table = popcorn_core_table('notice');
        $post_id = absint($wpdb->get_var($wpdb->prepare("SELECT post_id FROM $table WHERE id=%d", absint($request['id']))));
    }
    if (!popcorn_core_notice_post($post_id) || !current_user_can('edit_post', $post_id)) {
        return new WP_Error('forbidden_notice', 'اجازهٔ مدیریت اطلاعیه‌های این اثر را ندارید.', ['status' => 403]);
    }
    return true;
}

function popcorn_core_notice_rest_list($request) {
    global $wpdb;
    $table = popcorn_core_table('notice');
    $post_id = absint($request['post_id']);
    $page = min(1000, max(1, absint($request['page'] ?? 1)));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id,message,is_active,created_at FROM $table WHERE post_id=%d ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d", $post_id, 21, ($page - 1) * 20));
    return rest_ensure_response(['items' => array_map('popcorn_core_notice_rest_item', array_slice($rows, 0, 20)), 'has_more' => count($rows) > 20]);
}

function popcorn_core_notice_rest_create($request) {
    $message = popcorn_core_notice_clean_message($request['message']);
    if (is_wp_error($message)) {
        return $message;
    }
    global $wpdb;
    $table = popcorn_core_table('notice');
    $now = current_time('mysql');
    if (!$wpdb->insert($table, ['post_id' => absint($request['post_id']), 'author_id' => get_current_user_id(), 'message' => $message, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now])) {
        return new WP_Error('notice_save_failed', 'ذخیرهٔ اطلاعیه ناموفق بود.', ['status' => 500]);
    }
    $notice_id = (int) $wpdb->insert_id;
    $item = $wpdb->get_row($wpdb->prepare("SELECT id,message,is_active,created_at FROM $table WHERE id=%d", $notice_id));
    do_action('popcorn_notice_created', $notice_id);
    return new WP_REST_Response(popcorn_core_notice_rest_item($item), 201);
}

function popcorn_core_notice_rest_update($request) {
    global $wpdb;
    $table = popcorn_core_table('notice');
    $id = absint($request['id']);
    $was_active = (bool) $wpdb->get_var($wpdb->prepare("SELECT is_active FROM $table WHERE id=%d", $id));
    $changes = [];
    if ($request->has_param('message')) {
        $message = popcorn_core_notice_clean_message($request['message']);
        if (is_wp_error($message)) {
            return $message;
        }
        $changes['message'] = $message;
    }
    if ($request->has_param('is_active')) {
        $changes['is_active'] = rest_sanitize_boolean($request['is_active']) ? 1 : 0;
    }
    if (!$changes) {
        return new WP_Error('empty_notice_update', 'تغییری برای اطلاعیه فرستاده نشد.', ['status' => 400]);
    }
    $changes['updated_at'] = current_time('mysql');
    if ($wpdb->update($table, $changes, ['id' => $id]) === false) {
        return new WP_Error('notice_update_failed', 'به‌روزرسانی اطلاعیه ناموفق بود.', ['status' => 500]);
    }
    $item = $wpdb->get_row($wpdb->prepare("SELECT id,message,is_active,created_at FROM $table WHERE id=%d", $id));
    if (!$was_active && $item && $item->is_active) {
        do_action('popcorn_notice_created', $id);
    }
    return rest_ensure_response(popcorn_core_notice_rest_item($item));
}

function popcorn_core_notice_rest_delete($request) {
    global $wpdb;
    $table = popcorn_core_table('notice');
    if (!$wpdb->delete($table, ['id' => absint($request['id'])])) {
        return new WP_Error('notice_delete_failed', 'حذف اطلاعیه ناموفق بود.', ['status' => 500]);
    }
    do_action('popcorn_notice_deleted', absint($request['id']));
    return rest_ensure_response(['deleted' => true]);
}

add_action('rest_api_init', function () {
    register_rest_route('popcorn/v1', '/notices', [
        ['methods' => WP_REST_Server::READABLE, 'callback' => 'popcorn_core_notice_rest_list', 'permission_callback' => 'popcorn_core_notice_rest_permission'],
        ['methods' => WP_REST_Server::CREATABLE, 'callback' => 'popcorn_core_notice_rest_create', 'permission_callback' => 'popcorn_core_notice_rest_permission'],
    ]);
    register_rest_route('popcorn/v1', '/notices/(?P<id>\d+)', [
        ['methods' => WP_REST_Server::EDITABLE, 'callback' => 'popcorn_core_notice_rest_update', 'permission_callback' => 'popcorn_core_notice_rest_permission'],
        ['methods' => WP_REST_Server::DELETABLE, 'callback' => 'popcorn_core_notice_rest_delete', 'permission_callback' => 'popcorn_core_notice_rest_permission'],
    ]);
});

function popcorn_core_get_public_notices($post_id, $limit = 10) {
    static $cache = [];
    $post_id = absint($post_id);
    $limit = absint($limit);
    $cache_key = $post_id . ':' . $limit;
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }
    if (!popcorn_core_notice_post($post_id) || get_post_status($post_id) !== 'publish') {
        return $cache[$cache_key] = [];
    }
    global $wpdb;
    $table = popcorn_core_table('notice');
    $query = $wpdb->prepare("SELECT message,created_at FROM $table WHERE post_id=%d AND is_active=1 ORDER BY created_at DESC,id DESC", $post_id);
    return $cache[$cache_key] = $wpdb->get_results($query . ($limit ? ' LIMIT ' . $limit : ''));
}

function popcorn_core_render_notices($post_id) {
    $notices = popcorn_core_get_public_notices($post_id);
    if (!$notices) {
        return '';
    }
    $html = '<aside class="pc-title-notices" aria-label="اطلاعیه‌های این اثر"><h2>اطلاعیه‌های این اثر</h2><ol>';
    foreach ($notices as $notice) {
        $html .= '<li><time>' . esc_html(mysql2date('j F Y، H:i', $notice->created_at)) . '</time><div>' . wpautop(esc_html($notice->message)) . '</div></li>';
    }
    return $html . '</ol></aside>';
}

add_shortcode('popcorn_notices', function ($atts) {
    $atts = shortcode_atts(['post_id' => 0], $atts, 'popcorn_notices');
    return popcorn_core_render_notices(absint($atts['post_id']) ?: get_the_ID());
});

add_action('wp_enqueue_scripts', function () {
    $post_id = 0;
    if (is_singular(['popcorn_movie', 'popcorn_series'])) {
        $post_id = get_queried_object_id();
    } elseif (get_query_var('popcorn_view') === 'title' && in_array(get_query_var('popcorn_type'), ['movie', 'series'], true)) {
        $item = popcorn_get_item(get_query_var('popcorn_type'), absint(get_query_var('popcorn_id')));
        $post_id = absint($item->post_id ?? 0);
    }
    if ($post_id && popcorn_core_get_public_notices($post_id)) {
        $base = dirname(__DIR__);
        wp_enqueue_style('popcorn-notices-public', plugin_dir_url($base . '/popcorn-core.php') . 'assets/css/notices-public.css', [], filemtime($base . '/assets/css/notices-public.css'));
    }
});

add_action('enqueue_block_editor_assets', function () {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        return;
    }
    $base = dirname(__DIR__);
    $url = plugin_dir_url($base . '/popcorn-core.php');
    wp_enqueue_script('popcorn-notices-sidebar', $url . 'assets/js/notices-sidebar.js', ['wp-api-fetch', 'wp-components', 'wp-data', 'wp-editor', 'wp-element', 'wp-plugins'], filemtime($base . '/assets/js/notices-sidebar.js'), true);
    wp_add_inline_script('popcorn-notices-sidebar', 'window.popcornNoticeSettings=' . wp_json_encode(['nonce' => wp_create_nonce('wp_rest')]) . ';', 'before');
    wp_enqueue_style('popcorn-notices-sidebar', $url . 'assets/css/notices-sidebar.css', [], filemtime($base . '/assets/css/notices-sidebar.css'));
    $accent = function_exists('popcorn_theme_color') ? popcorn_theme_color() : '#6C52EE';
    wp_add_inline_style('popcorn-notices-sidebar', '.pc-notice-sidebar{--pc-notice-accent:' . esc_html($accent) . '}');
});

add_action('before_delete_post', function ($post_id, $post) {
    if (!in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        return;
    }
    global $wpdb;
    $wpdb->delete(popcorn_core_table('notice'), ['post_id' => $post_id]);
}, 9, 2);
