<?php
defined('ABSPATH') || exit;

function popcorn_auth_post_text($key) {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
}

function popcorn_auth_registration_data() {
    $login = sanitize_user(popcorn_auth_post_text('username'), true);
    $name = sanitize_text_field(popcorn_auth_post_text('display_name'));
    $password = popcorn_auth_post_text('new_password');
    if (!$login || strlen($login) > 60 || !validate_username($login) || username_exists($login)) {
        return new WP_Error('registration_username', 'نام کاربری معتبر و استفاده‌نشده انتخاب کنید.');
    }
    if (!$name || mb_strlen($name) > 100) {
        return new WP_Error('registration_name', 'نام نمایشی را وارد کنید؛ حداکثر 100 نویسه.');
    }
    if ($password !== '' && (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password) || $password !== popcorn_auth_post_text('confirm_password'))) {
        return new WP_Error('registration_password', 'رمز باید دست‌کم 8 نویسه، شامل حرف انگلیسی و عدد باشد و با تکرار آن مطابقت کند.');
    }
    $data = ['user_login' => $login, 'display_name' => $name];
    if ($password !== '') {
        $data['user_pass'] = $password;
    }
    return $data;
}

function popcorn_auth_page_kind() {
    if (!is_singular('page')) {
        return '';
    }
    $post = get_queried_object();
    $options = popcorn_auth_options();
    foreach (['login' => 'popcorn_auth', 'register' => 'popcorn_register', 'account' => 'popcorn_account'] as $kind => $shortcode) {
        if ((!empty($options[$kind . '_page_id']) && is_page($options[$kind . '_page_id'])) || ($post instanceof WP_Post && has_shortcode($post->post_content, $shortcode))) {
            return $kind;
        }
    }
    return '';
}

function popcorn_auth_register_url() {
    $options = popcorn_auth_options();
    return $options['enabled'] && $options['registration'] && get_post_status($options['register_page_id']) === 'publish' ? get_permalink($options['register_page_id']) : '';
}

add_action('init', function () {
    $options = popcorn_auth_options();
    if (!$options['enabled'] || !$options['registration'] || get_post_status($options['register_page_id']) === 'publish') {
        return;
    }
    $id = popcorn_auth_create_page($options['register_page_id'], 'ثبت‌نام', 'popcorn-register', '[popcorn_register]');
    if ($id) {
        $options['register_page_id'] = $id;
        update_option('popcorn_auth_options', $options, false);
    }
}, 40);

add_action('template_redirect', function () {
    $kind = popcorn_auth_page_kind();
    if (!popcorn_auth_enabled() || !$kind) {
        return;
    }
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }
    nocache_headers();
    if ($kind === 'account' && !is_user_logged_in()) {
        wp_safe_redirect(popcorn_auth_login_url(get_permalink()));
        exit;
    }
    if (($kind !== 'account' && is_user_logged_in()) || ($kind === 'register' && !popcorn_auth_options()['registration'])) {
        wp_safe_redirect($kind === 'register' && !is_user_logged_in() ? popcorn_auth_login_url() : (popcorn_auth_account_url() ?: home_url('/')));
        exit;
    }
}, 5);

add_filter('template_include', function ($template) {
    if (popcorn_auth_enabled() && popcorn_auth_page_kind() && get_template() === 'popcorn') {
        return dirname(__DIR__) . '/templates/page.php';
    }
    return $template;
}, 100);

function popcorn_auth_icon($name) {
    if (function_exists('popcorn_icon')) {
        $icon = popcorn_icon($name);
        if ($icon) {
            return $icon;
        }
    }
    $paths = [
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'card' => '<rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 9h20M6 15h4"/>',
        'comment' => '<path d="M21 11a9 9 0 0 1-9 9H4l-3 2 2-7a9 9 0 1 1 18-4Z"/><path d="M7 9h10M7 13h6"/>',
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'home' => '<path d="m3 10 9-7 9 7v11H3V10Z"/>',
        'film' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 3v18M16 3v18M3 8h5M16 8h5M3 16h5M16 16h5"/>',
        'exit' => '<path d="M9 21H3V3h6M9 12h12m-5-5 5 5-5 5"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'star' => '<path d="m12 2 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1 3-6Z"/>',
    ];
    return '<svg class="pc-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['user']) . '</svg>';
}

function popcorn_auth_brand() {
    ob_start();
    if (get_template() === 'popcorn' && function_exists('popcorn_image')) {
        get_template_part('template-parts/site-brand', null, ['image_class' => 'pc-auth-logo', 'mark_class' => 'pc-auth-mark']);
    } else {
        $logo = get_theme_mod('custom_logo');
        echo $logo ? wp_get_attachment_image($logo, 'full', false, ['class' => 'pc-auth-logo', 'alt' => get_bloginfo('name')]) : '<span class="pc-auth-mark">' . popcorn_auth_icon('film') . '</span><span>' . esc_html(get_bloginfo('name')) . '</span>';
    }
    return ob_get_clean();
}

function popcorn_auth_form_html($atts = []) {
    return popcorn_auth_render_form(false);
}

function popcorn_auth_register_html() {
    return popcorn_auth_render_form(true);
}

function popcorn_auth_render_form($register) {
    if (!popcorn_auth_enabled() || ($register && !popcorn_auth_options()['registration'])) {
        return '';
    }
    if (is_user_logged_in()) {
        return '<p><a href="' . esc_url(popcorn_auth_account_url() ?: home_url('/')) . '">رفتن به حساب کاربری</a></p>';
    }
    if (!popcorn_auth_transport_secure() || !popcorn_auth_request_secure()) {
        return '<p>ورود به حساب کاربری به اتصال امن HTTPS نیاز دارد.</p>';
    }
    $options = popcorn_auth_options();
    $method = $options['email_enabled'] && $options['mobile_enabled'] ? 'ایمیل یا شماره موبایل' : ($options['mobile_enabled'] ? 'شماره موبایل' : 'ایمیل');
    $fallback = popcorn_auth_account_url() ?: home_url('/');
    $redirect = isset($_GET['redirect_to']) && is_string($_GET['redirect_to']) ? wp_validate_redirect(wp_unslash($_GET['redirect_to']), $fallback) : $fallback;
    ob_start();
    include dirname(__DIR__) . '/templates/form.php';
    return ob_get_clean();
}

function popcorn_auth_account_tabs() {
    $tabs = ['overview' => ['نمای کلی', 'home'], 'profile' => ['مشخصات و امنیت', 'user']];
    if (function_exists('popcorn_core_engagement_options')) {
        $engagement = popcorn_core_engagement_options();
        if ($engagement['favorites_enabled']) { $tabs['favorites'] = ['علاقه‌مندی‌ها', 'heart']; }
        if ($engagement['rating_enabled']) { $tabs['ratings'] = ['امتیازهای من', 'star']; }
    }
    $tabs['comments'] = ['دیدگاه‌های من', 'comment'];
    if (function_exists('popcorn_subscription_enabled') && popcorn_subscription_enabled()) {
        $tabs['subscription'] = ['اشتراک من', 'star'];
        $tabs['payments'] = ['پرداخت‌ها', 'card'];
    }
    return $tabs;
}

function popcorn_auth_account_tab_url($tab, $page = 1) {
    $url = popcorn_auth_account_url() ?: get_permalink();
    return add_query_arg(array_filter(['account_tab' => $tab, 'account_page' => $page > 1 ? $page : null]), $url);
}

function popcorn_auth_account_records($kind, $page = 1, $limit = 12) {
    global $wpdb;
    $result = ['rows' => [], 'total' => 0];
    if (!popcorn_auth_enabled() || !is_user_logged_in()) { return $result; }
    $id = get_current_user_id();
    $offset = (max(1, (int) $page) - 1) * $limit;
    if (in_array($kind, ['favorites', 'ratings'], true) && function_exists('popcorn_core_table')) {
        $table = popcorn_core_table($kind === 'favorites' ? 'user_favorite' : 'user_rating');
        $from = "FROM $table u INNER JOIN {$wpdb->posts} p ON p.ID=u.post_id AND p.post_status='publish' AND p.post_password='' AND p.post_type IN ('popcorn_movie','popcorn_series') WHERE u.user_id=%d";
        $result['total'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) $from", $id));
        $date = $kind === 'ratings' ? 'updated_at' : 'created_at';
        $result['rows'] = $wpdb->get_results($wpdb->prepare("SELECT u.*,p.post_title $from ORDER BY u.$date DESC,u.id DESC LIMIT %d OFFSET %d", $id, $limit, $offset));
    } elseif ($kind === 'comments') {
        $args = ['user_id' => $id, 'status' => 'all', 'type' => 'comment', 'post_status' => 'publish'];
        $result['total'] = (int) get_comments(array_merge($args, ['count' => true]));
        $result['rows'] = get_comments(array_merge($args, ['number' => $limit, 'offset' => $offset, 'orderby' => 'comment_date_gmt', 'order' => 'DESC']));
    } elseif (in_array($kind, ['payments', 'subscription'], true) && function_exists('popcorn_subscription_enabled') && popcorn_subscription_enabled()) {
        $table = popcorn_subscription_table($kind === 'payments' ? 'payment' : 'subscription');
        $plans = popcorn_subscription_table('plan');
        $result['total'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d", $id));
        $result['rows'] = $wpdb->get_results($wpdb->prepare("SELECT u.*,p.name AS plan_name FROM $table u LEFT JOIN $plans p ON p.id=u.plan_id WHERE u.user_id=%d ORDER BY u.id DESC LIMIT %d OFFSET %d", $id, $limit, $offset));
    }
    return $result;
}

function popcorn_auth_account_cards($rows) {
    if (!$rows) { return ''; }
    $ids = array_map('absint', wp_list_pluck($rows, 'post_id'));
    $posts = get_posts(['post_type' => ['popcorn_movie', 'popcorn_series'], 'post_status' => 'publish', 'post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => count($ids)]);
    ob_start();
    echo '<div class="pc-account-grid">';
    if (function_exists('popcorn_core_catalog_items') && function_exists('popcorn_card_image_size')) {
        $items = popcorn_core_catalog_items($posts);
        if (function_exists('popcorn_homepage_prime_slider_items')) { popcorn_homepage_prime_slider_items($items, true); }
        foreach ($items as $item) { get_template_part('template-parts/card', null, ['item' => $item]); }
    } else {
        foreach ($posts as $post) {
            echo '<a class="pc-account-panel" href="' . esc_url(get_permalink($post)) . '">' . get_the_post_thumbnail($post, 'medium', ['loading' => 'lazy']) . '<strong>' . esc_html($post->post_title) . '</strong></a>';
        }
    }
    echo '</div>';
    return ob_get_clean();
}

function popcorn_auth_account_html() {
    if (!popcorn_auth_enabled()) { return ''; }
    if (!popcorn_auth_transport_secure() || !popcorn_auth_request_secure()) { return '<p>حساب کاربری به اتصال امن HTTPS نیاز دارد.</p>'; }
    if (!is_user_logged_in()) { return '<p><a href="' . esc_url(popcorn_auth_login_url(get_permalink())) . '">برای دیدن حساب کاربری وارد شوید.</a></p>'; }
    $user = wp_get_current_user();
    $tabs = popcorn_auth_account_tabs();
    $requested = isset($_GET['account_tab']) && is_string($_GET['account_tab']) ? sanitize_key(wp_unslash($_GET['account_tab'])) : 'overview';
    $tab = isset($tabs[$requested]) ? $requested : 'overview';
    $page = isset($_GET['account_page']) && is_scalar($_GET['account_page']) ? max(1, absint($_GET['account_page'])) : 1;
    $records = $tab !== 'profile' && $tab !== 'overview' ? popcorn_auth_account_records($tab, $page) : ['rows' => [], 'total' => 0];
    if ($page > max(1, (int) ceil($records['total'] / 12))) {
        $page = max(1, (int) ceil($records['total'] / 12));
        $records = popcorn_auth_account_records($tab, $page);
    }
    ob_start();
    include dirname(__DIR__) . '/templates/account.php';
    return ob_get_clean();
}
