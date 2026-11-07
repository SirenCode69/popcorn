<?php
defined('ABSPATH') || exit;

function popcorn_core_public_login_url($redirect = '') {
    return function_exists('popcorn_auth_login_url') && function_exists('popcorn_auth_enabled') && popcorn_auth_enabled() ? popcorn_auth_login_url($redirect) : '';
}

function popcorn_core_engagement_options() {
    $defaults = ['rating_enabled' => 1, 'favorites_enabled' => 1, 'show_counts' => 1];
    $saved = get_option('popcorn_engagement_options', []);
    if (!is_array($saved)) {
        return $defaults;
    }
    foreach ($defaults as $key => $value) {
        if (array_key_exists($key, $saved)) {
            $defaults[$key] = empty($saved[$key]) ? 0 : 1;
        }
    }
    return $defaults;
}

function popcorn_core_engagement_post($post_id) {
    $post = get_post(absint($post_id));
    return $post && in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true) && $post->post_status === 'publish' ? $post : null;
}

function popcorn_core_rating_vote_count($post_id) {
    $stats = popcorn_core_rating_statistics(absint($post_id));
    return (int) ($stats->total ?? 0);
}

function popcorn_core_recalculate_site_rating($post_id) {
    global $wpdb;
    $post = get_post(absint($post_id));
    if (!$post || !in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        return new WP_Error('rating_post_missing', 'اثر موردنظر پیدا نشد.');
    }
    $table = popcorn_core_table($post->post_type === 'popcorn_movie' ? 'movie' : 'series');
    $stats = popcorn_core_rating_statistics($post_id);
    $average = round((float) ($stats->average ?? 0), 2);
    $saved = $wpdb->update($table, ['rating' => $average], ['post_id' => $post_id], ['%f'], ['%d']);
    if ($saved === false) {
        return new WP_Error('site_rating_save_failed', 'به‌روزرسانی امتیاز سایت انجام نشد.');
    }
    clean_post_cache($post_id);
    return $average;
}

function popcorn_core_engagement_summary($post_id, $user_id = 0) {
    global $wpdb;
    $options = popcorn_core_engagement_options();
    $result = ['rating_enabled' => (bool) $options['rating_enabled'], 'favorites_enabled' => (bool) $options['favorites_enabled'], 'show_counts' => (bool) $options['show_counts'], 'rating_count' => 0, 'rating_average' => 0, 'site_rating' => 0, 'favorite_count' => 0, 'user_rating' => 0, 'favorited' => false];
    $post = get_post(absint($post_id));
    if ($post && in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        $table = popcorn_core_table($post->post_type === 'popcorn_movie' ? 'movie' : 'series');
        $result['site_rating'] = (float) $wpdb->get_var($wpdb->prepare("SELECT rating FROM $table WHERE post_id=%d", $post_id));
    }
    if ($options['rating_enabled']) {
        $table = popcorn_core_table('user_rating');
        $stats = popcorn_core_rating_statistics($post_id);
        $result['rating_count'] = (int) ($stats->total ?? 0);
        $result['rating_average'] = $result['rating_count'] ? round((float) $stats->average, 1) : 0;
        if ($user_id) {
            $result['user_rating'] = (int) $wpdb->get_var($wpdb->prepare("SELECT rating FROM $table WHERE post_id=%d AND user_id=%d", $post_id, $user_id));
        }
    }
    if ($options['favorites_enabled']) {
        $table = popcorn_core_table('user_favorite');
        $result['favorite_count'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE post_id=%d", $post_id));
        if ($user_id) {
            $result['favorited'] = (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE post_id=%d AND user_id=%d", $post_id, $user_id));
        }
    }
    return $result;
}

function popcorn_core_engagement_rest_permission($request) {
    if (!popcorn_core_engagement_post($request['post_id'])) {
        return new WP_Error('missing_title', 'فیلم یا سریال منتشرشده‌ای با این شناسه پیدا نشد.', ['status' => 404]);
    }
    if ($request->get_method() !== 'GET' && !is_user_logged_in()) {
        return popcorn_core_guest_rating_permission($request);
    }
    return true;
}

function popcorn_core_engagement_rest_get($request) {
    $post_id = absint($request['post_id']);
    $data = popcorn_core_engagement_summary($post_id, get_current_user_id());
    if (!is_user_logged_in() && popcorn_core_guest_rating_enabled()) {
        $voter = popcorn_core_guest_rating_voter(true);
        if (is_wp_error($voter)) {
            return $voter;
        }
        global $wpdb;
        $table = popcorn_core_table('guest_rating');
        $data['user_rating'] = (int) $wpdb->get_var($wpdb->prepare("SELECT rating FROM $table WHERE post_id=%d AND voter_key=%s", $post_id, $voter));
        $data['guest_nonce'] = wp_create_nonce('popcorn_guest_rating_' . $post_id);
        $data['guest_token'] = popcorn_core_guest_rating_token($post_id, $voter, $data['guest_nonce']);
    }
    $response = rest_ensure_response($data);
    $response->header('Cache-Control', 'private, no-store');
    $response->header('Vary', 'Cookie');
    return $response;
}

function popcorn_core_engagement_rest_rating($request) {
    $options = popcorn_core_engagement_options();
    if (!$options['rating_enabled']) {
        return new WP_Error('rating_disabled', 'امتیازدهی غیرفعال است.', ['status' => 403]);
    }
    $post_id = absint($request['post_id']);
    $user_id = get_current_user_id();
    global $wpdb;
    $table = popcorn_core_table('user_rating');
    $input = $request['rating'];
    $rating = is_scalar($input) ? filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]) : false;
    if ($request->get_method() !== 'DELETE' && $rating === false) {
        return new WP_Error('invalid_rating', 'امتیاز باید عددی بین 1 تا 10 باشد.', ['status' => 400]);
    }
    if (!$user_id) {
        $permission = popcorn_core_guest_rating_permission($request);
        if (is_wp_error($permission)) {
            return $permission;
        }
        $saved = popcorn_core_save_guest_rating($post_id, $rating, $request->get_method() === 'DELETE');
        if (is_wp_error($saved)) {
            return $saved;
        }
    } elseif ($request->get_method() === 'DELETE') {
        if ($wpdb->delete($table, ['post_id' => $post_id, 'user_id' => $user_id]) === false) {
            return new WP_Error('rating_delete_failed', 'حذف امتیاز انجام نشد.', ['status' => 500]);
        }
    } else {
        $now = current_time('mysql');
        $saved = $wpdb->query($wpdb->prepare("INSERT INTO $table (user_id,post_id,rating,created_at,updated_at) VALUES (%d,%d,%d,%s,%s) ON DUPLICATE KEY UPDATE rating=VALUES(rating),updated_at=VALUES(updated_at)", $user_id, $post_id, $rating, $now, $now));
        if ($saved === false) {
            return new WP_Error('rating_save_failed', 'ذخیرهٔ امتیاز انجام نشد.', ['status' => 500]);
        }
    }
    $recalculated = popcorn_core_recalculate_site_rating($post_id);
    if (is_wp_error($recalculated)) {
        return new WP_Error($recalculated->get_error_code(), $recalculated->get_error_message(), ['status' => 500]);
    }
    return popcorn_core_engagement_rest_get($request);
}

function popcorn_core_engagement_rest_favorite($request) {
    $options = popcorn_core_engagement_options();
    if (!$options['favorites_enabled']) {
        return new WP_Error('favorites_disabled', 'علاقه‌مندی‌ها غیرفعال است.', ['status' => 403]);
    }
    $post_id = absint($request['post_id']);
    $user_id = get_current_user_id();
    global $wpdb;
    $table = popcorn_core_table('user_favorite');
    if ($request->get_method() === 'DELETE') {
        $saved = $wpdb->delete($table, ['post_id' => $post_id, 'user_id' => $user_id]);
    } else {
        $saved = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (user_id,post_id,created_at) VALUES (%d,%d,%s)", $user_id, $post_id, current_time('mysql')));
    }
    if ($saved === false) {
        return new WP_Error('favorite_save_failed', 'ذخیرهٔ علاقه‌مندی انجام نشد.', ['status' => 500]);
    }
    return popcorn_core_engagement_rest_get($request);
}

add_action('rest_api_init', function () {
    register_rest_route('popcorn/v1', '/engagement/(?P<post_id>\d+)', ['methods' => WP_REST_Server::READABLE, 'callback' => 'popcorn_core_engagement_rest_get', 'permission_callback' => 'popcorn_core_engagement_rest_permission']);
    register_rest_route('popcorn/v1', '/engagement/(?P<post_id>\d+)/rating', [
        ['methods' => WP_REST_Server::CREATABLE, 'callback' => 'popcorn_core_engagement_rest_rating', 'permission_callback' => 'popcorn_core_engagement_rest_permission'],
        ['methods' => WP_REST_Server::DELETABLE, 'callback' => 'popcorn_core_engagement_rest_rating', 'permission_callback' => 'popcorn_core_engagement_rest_permission'],
    ]);
    register_rest_route('popcorn/v1', '/engagement/(?P<post_id>\d+)/favorite', [
        ['methods' => WP_REST_Server::CREATABLE, 'callback' => 'popcorn_core_engagement_rest_favorite', 'permission_callback' => 'popcorn_core_engagement_rest_permission'],
        ['methods' => WP_REST_Server::DELETABLE, 'callback' => 'popcorn_core_engagement_rest_favorite', 'permission_callback' => 'popcorn_core_engagement_rest_permission'],
    ]);
});

function popcorn_core_render_engagement($post_id, $part = 'all') {
    if (!popcorn_core_engagement_post($post_id)) {
        return '';
    }
    $options = popcorn_core_engagement_options();
    $part = in_array($part, ['rating', 'favorite'], true) ? $part : 'all';
    if ($part === 'rating') {
        $options['favorites_enabled'] = false;
    } elseif ($part === 'favorite') {
        $options['rating_enabled'] = false;
    }
    if (!$options['rating_enabled'] && !$options['favorites_enabled']) {
        return '';
    }
    popcorn_core_engagement_enqueue_assets(true);
    $stats = popcorn_core_engagement_summary($post_id);
    $logged_in = is_user_logged_in();
    $can_rate = $logged_in || popcorn_core_guest_rating_enabled();
    $url = rest_url('popcorn/v1/engagement/' . absint($post_id));
    $login = popcorn_core_public_login_url(get_permalink($post_id));
    $html = '<section id="popcorn-engagement' . ($part === 'all' ? '' : '-' . $part) . '" class="pc-engagement pc-engagement--' . $part . '" data-endpoint="' . esc_url($url) . '" data-post-id="' . absint($post_id) . '" data-nonce="' . esc_attr($logged_in ? wp_create_nonce('wp_rest') : '') . '" aria-label="امتیاز و علاقه‌مندی کاربران">';
    if ($options['rating_enabled']) {
        $html .= '<div class="pc-engagement__rating"><div class="pc-engagement__heading"><h2>امتیاز کاربران</h2>';
        if ($options['show_counts']) {
            $html .= '<p class="pc-engagement__summary"><strong data-rating-average>' . esc_html(number_format($stats['rating_average'], 1, '.', '')) . '</strong> از 10 <span data-rating-count>‌(' . esc_html(number_format($stats['rating_count'])) . ' رأی)</span></p>';
        }
        $html .= '</div>';
        if ($can_rate) {
            $star = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="m12 2.8 2.8 5.7 6.3.9-4.6 4.5 1.1 6.3-5.6-3-5.6 3 1.1-6.3L2.9 9.4l6.3-.9Z"/></svg>';
            $html .= '<div class="pc-engagement__choices" role="group" aria-label="امتیاز شما از 10">';
            for ($value = 1; $value <= 10; $value++) {
                $html .= '<button type="button" data-rating="' . $value . '" aria-label="امتیاز ' . $value . ' از 10" aria-pressed="' . ($value === (int) $stats['user_rating'] ? 'true' : 'false') . '" data-filled="' . ($value <= (int) $stats['user_rating'] ? 'true' : 'false') . '" title="' . $value . ' از 10">' . $star . '</button>';
            }
            $mine = $stats['user_rating'] ? 'امتیاز شما: ' . number_format($stats['user_rating']) . ' از 10' : 'هنوز امتیازی ثبت نکرده‌اید.';
            $html .= '</div><div class="pc-engagement__user-row"><p class="pc-engagement__mine" data-user-rating>' . esc_html($mine) . '</p><button type="button" class="pc-engagement__clear" data-clear-rating' . ($stats['user_rating'] ? '' : ' hidden') . '>حذف امتیاز من</button></div>';
        }
        $html .= '</div>';
    }
    if ($options['favorites_enabled']) {
        $heart = '<svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 8.4c0 4.1-8.8 10.4-8.8 10.4S3.2 12.5 3.2 8.4a4.7 4.7 0 0 1 8.8-2.2 4.7 4.7 0 0 1 8.8 2.2Z"/></svg>';
        $html .= '<div class="pc-engagement__favorite"><h2>علاقه‌مندی</h2>';
        if ($options['show_counts']) {
            $html .= '<p class="pc-engagement__summary"><span data-favorite-count>' . esc_html(number_format($stats['favorite_count'])) . '</span> نفر این اثر را پسندیده‌اند.</p>';
        }
        if ($logged_in) {
            $html .= '<button type="button" class="pc-engagement__favorite-button" data-favorite aria-pressed="false">' . $heart . '<span>افزودن به علاقه‌مندی‌ها</span></button>';
        } elseif ($part === 'favorite' && $login) {
            $html .= '<a class="pc-engagement__favorite-button" href="' . esc_url($login) . '" aria-label="ورود برای افزودن به علاقه‌مندی‌ها">' . $heart . '<span>علاقه‌مندی</span></a>';
        }
        $html .= '</div>';
    }
    if (!$logged_in && $login && $part !== 'favorite') {
        $action = $options['rating_enabled'] && $options['favorites_enabled'] ? 'امتیاز دادن و ثبت علاقه‌مندی' : ($options['rating_enabled'] ? 'امتیاز دادن' : 'ثبت علاقه‌مندی');
        $html .= '<p class="pc-engagement__login"><a href="' . esc_url($login) . '">برای ' . esc_html($action) . ' وارد شوید.</a></p>';
    }
    return $html . '<p class="pc-engagement__status" role="status" aria-live="polite"></p></section>';
}

add_shortcode('popcorn_engagement', function ($atts) {
    $atts = shortcode_atts(['post_id' => 0], $atts, 'popcorn_engagement');
    return popcorn_core_render_engagement(absint($atts['post_id']) ?: get_the_ID());
});

add_shortcode('popcorn_favorites', function () {
    if (!is_user_logged_in()) {
        $login = popcorn_core_public_login_url(get_permalink());
        return $login ? '<p><a href="' . esc_url($login) . '">برای دیدن علاقه‌مندی‌ها وارد شوید.</a></p>' : '';
    }
    if (!popcorn_core_engagement_options()['favorites_enabled']) {
        return '<p>بخش علاقه‌مندی‌ها غیرفعال است.</p>';
    }
    popcorn_core_engagement_enqueue_assets(false);
    global $wpdb;
    $table = popcorn_core_table('user_favorite');
    $from = "FROM $table f INNER JOIN {$wpdb->posts} p ON p.ID=f.post_id AND p.post_status='publish' AND p.post_type IN ('popcorn_movie','popcorn_series') WHERE f.user_id=%d";
    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $from", get_current_user_id()));
    $per_page = 24;
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($pages, max(1, absint($_GET['pc_favorites_page'] ?? 1)));
    $ids = $wpdb->get_col($wpdb->prepare("SELECT p.ID $from ORDER BY f.created_at DESC,f.id DESC LIMIT %d OFFSET %d", get_current_user_id(), $per_page, ($page - 1) * $per_page));
    if (!$ids) {
        return '<p>هنوز فیلم یا سریالی به علاقه‌مندی‌ها اضافه نکرده‌اید.</p>';
    }
    if (function_exists('popcorn_card_features')) {
        popcorn_card_features(0, $ids);
    }
    $html = '<section class="pc-favorites-list"><h2>علاقه‌مندی‌های من</h2><div class="pc-grid">';
    foreach ($ids as $id) {
        $type = get_post_type($id) === 'popcorn_series' ? 'series' : 'movie';
        $item = popcorn_core_get_by_post($type, $id);
        if (!$item) {
            $html .= '<a href="' . esc_url(get_permalink($id)) . '">' . esc_html(get_the_title($id)) . '</a>';
            continue;
        }
        $item->popcorn_type = $type;
        if (function_exists('popcorn_display_title')) {
            ob_start();
            get_template_part('template-parts/card', null, ['item' => $item]);
            $html .= ob_get_clean();
        } else {
            $html .= '<a href="' . esc_url(get_permalink($id)) . '">' . esc_html(get_the_title($id)) . '</a>';
        }
    }
    $html .= '</div>';
    if ($pages > 1) {
        $links = paginate_links(['base' => add_query_arg('pc_favorites_page', '%#%'), 'format' => '', 'current' => $page, 'total' => $pages, 'prev_text' => 'قبلی', 'next_text' => 'بعدی']);
        if ($links) {
            $html .= '<nav class="pc-pagination" aria-label="صفحه‌های علاقه‌مندی‌ها">' . $links . '</nav>';
        }
    }
    return $html . '</section>';
});

function popcorn_core_engagement_settings_tab() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $options = popcorn_core_engagement_options();
    if (isset($_GET['popcorn_message']) && sanitize_key(wp_unslash($_GET['popcorn_message'])) === 'saved') {
        echo '<div class="notice notice-success"><p>تنظیمات امتیاز و علاقه‌مندی ذخیره شد.</p></div>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pc-engagement-settings"><input type="hidden" name="action" value="popcorn_save_engagement_options">';
    wp_nonce_field('popcorn_engagement_options');
    echo '<div class="pc-admin__card"><h2>تعامل کاربران</h2><p>هر کاربر برای هر فیلم یا سریال یک امتیاز از ۱ تا ۱۰ و یک وضعیت علاقه‌مندی دارد. داده‌ها در جدول‌های اختصاصی افزونه ذخیره می‌شوند. اگر حساب کاربری خاموش یا افزونهٔ آن حذف شده باشد، امتیازدهی مهمان با کوکی و نشست مرورگر فعال است؛ IP به‌صورت هش‌شده برای محدودکردن درخواست‌های تکراری استفاده می‌شود.</p><table class="form-table"><tbody>';
    foreach (['rating_enabled' => ['امتیازدهی کاربران', 'نمایش و ثبت امتیاز ۱ تا ۱۰ در صفحهٔ اثر'], 'favorites_enabled' => ['علاقه‌مندی کاربران', 'نمایش دکمهٔ علاقه‌مندی و ذخیرهٔ فهرست شخصی'], 'show_counts' => ['نمایش آمار عمومی', 'نمایش میانگین امتیاز، تعداد رأی و تعداد علاقه‌مندی‌ها']] as $key => [$label, $description]) {
        echo '<tr><th><label for="popcorn-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><label><input id="popcorn-' . esc_attr($key) . '" type="checkbox" name="popcorn_engagement_options[' . esc_attr($key) . ']" value="1"' . checked($options[$key], 1, false) . '> ' . esc_html($description) . '</label></td></tr>';
    }
    echo '</tbody></table></div><div class="pc-admin__card"><h2>فهرست علاقه‌مندی‌ها</h2><p>برای نمایش فهرست شخصی کاربران در یک برگه یا قالب المنتور، از شورت‌کد <code>[popcorn_favorites]</code> استفاده کنید. برای نمایش کنترل امتیاز و علاقه‌مندی در قالب المنتور، شورت‌کد <code>[popcorn_engagement]</code> در دسترس است.</p></div>';
    submit_button('ذخیرهٔ تنظیمات');
    echo '</form>';
}
add_action('popcorn_settings_tab_engagement', 'popcorn_core_engagement_settings_tab');

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_popcorn-settings' || !isset($_GET['tab']) || sanitize_key(wp_unslash($_GET['tab'])) !== 'engagement') {
        return;
    }
    $base = dirname(__DIR__);
    wp_enqueue_style('popcorn-engagement-settings', plugin_dir_url($base . '/popcorn-core.php') . 'assets/css/engagement-settings.css', ['popcorn-admin'], filemtime($base . '/assets/css/engagement-settings.css'));
});

add_action('admin_post_popcorn_save_engagement_options', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.');
    }
    check_admin_referer('popcorn_engagement_options');
    $input = isset($_POST['popcorn_engagement_options']) && is_array($_POST['popcorn_engagement_options']) ? wp_unslash($_POST['popcorn_engagement_options']) : [];
    $options = [];
    foreach (popcorn_core_engagement_options() as $key => $value) {
        $options[$key] = !empty($input[$key]) ? 1 : 0;
    }
    update_option('popcorn_engagement_options', $options, false);
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=engagement&popcorn_message=saved'));
    exit;
});

function popcorn_core_engagement_enqueue_assets($script = true) {
    $base = dirname(__DIR__);
    $url = plugin_dir_url($base . '/popcorn-core.php');
    wp_enqueue_style('popcorn-engagement', $url . 'assets/css/engagement.css', [], filemtime($base . '/assets/css/engagement.css'));
    if ($script) {
        wp_enqueue_script('popcorn-engagement', $url . 'assets/js/engagement.js', [], filemtime($base . '/assets/js/engagement.js'), true);
    }
}

add_action('before_delete_post', function ($post_id, $post) {
    if (!in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        return;
    }
    global $wpdb;
    $wpdb->delete(popcorn_core_table('user_rating'), ['post_id' => $post_id]);
    $wpdb->delete(popcorn_core_table('guest_rating'), ['post_id' => $post_id]);
    $wpdb->delete(popcorn_core_table('user_favorite'), ['post_id' => $post_id]);
}, 9, 2);

add_action('delete_user', function ($user_id) {
    global $wpdb;
    $table = popcorn_core_table('user_rating');
    $posts = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM $table WHERE user_id=%d", absint($user_id)));
    $wpdb->delete($table, ['user_id' => absint($user_id)]);
    foreach ($posts as $post_id) {
        popcorn_core_recalculate_site_rating((int) $post_id);
    }
    $wpdb->delete(popcorn_core_table('user_favorite'), ['user_id' => absint($user_id)]);
});
