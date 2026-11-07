<?php
defined('ABSPATH') || exit;

function popcorn_auth_options() {
    $defaults = ['enabled' => 0, 'registration' => 1, 'email_enabled' => 1, 'mobile_enabled' => 0, 'gateway' => 'kavenegar', 'api_key' => '', 'api_password' => '', 'from' => '', 'template' => '', 'message' => 'کد تأیید شما: {otp}', 'otp_length' => 6, 'login_page_id' => 0, 'account_page_id' => 0, 'register_page_id' => 0, 'graphic_id' => 0];
    $saved = get_option('popcorn_auth_options', []);
    return is_array($saved) ? array_merge($defaults, $saved) : $defaults;
}

function popcorn_auth_enabled() {
    return (bool) popcorn_auth_options()['enabled'];
}

function popcorn_auth_login_url($redirect = '') {
    $options = popcorn_auth_options();
    if (!$options['enabled'] || !$options['login_page_id'] || get_post_status($options['login_page_id']) !== 'publish') {
        return '';
    }
    $url = get_permalink($options['login_page_id']);
    return $redirect ? add_query_arg('redirect_to', wp_validate_redirect($redirect, home_url('/')), $url) : $url;
}

function popcorn_auth_account_url() {
    $options = popcorn_auth_options();
    return $options['enabled'] && $options['account_page_id'] && get_post_status($options['account_page_id']) === 'publish' ? get_permalink($options['account_page_id']) : '';
}

add_action('template_redirect', function () {
    if (popcorn_auth_enabled()) {
        return;
    }
    $options = popcorn_auth_options();
    if (($options['login_page_id'] && is_page($options['login_page_id'])) || ($options['register_page_id'] && is_page($options['register_page_id']))) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
    if ($options['account_page_id'] && is_page($options['account_page_id'])) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
});

add_filter('login_redirect', function ($redirect, $requested, $user) {
    return popcorn_auth_enabled() && $user instanceof WP_User && !user_can($user, 'edit_posts') && popcorn_auth_account_url() ? popcorn_auth_account_url() : $redirect;
}, 10, 3);

add_action('admin_init', function () {
    global $pagenow;
    if (!popcorn_auth_enabled() || !is_user_logged_in() || current_user_can('edit_posts') || wp_doing_ajax() || in_array($pagenow, ['admin-post.php', 'async-upload.php'], true)) {
        return;
    }
    $url = popcorn_auth_account_url();
    if ($url) {
        wp_safe_redirect($url);
        exit;
    }
});

function popcorn_auth_transport_secure() {
    $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    return wp_parse_url(home_url('/'), PHP_URL_SCHEME) === 'https' || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function popcorn_auth_request_secure() {
    $host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    return is_ssl() || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function popcorn_auth_identifier($input) {
    if (!is_string($input)) {
        return '';
    }
    $value = trim(strtr($input, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
    if (preg_match('/^09[0-9]{9}$/', $value)) {
        return $value;
    }
    return is_email($value) ? strtolower(sanitize_email($value)) : '';
}

function popcorn_auth_find_user($identifier) {
    if (is_email($identifier)) {
        return get_user_by('email', $identifier) ?: null;
    }
    $ids = get_users(['meta_key' => 'popcorn_auth_mobile', 'meta_value' => $identifier, 'fields' => 'ids', 'number' => 1]);
    return $ids ? get_userdata((int) $ids[0]) : null;
}

function popcorn_auth_error($message, $status = 400) {
    wp_send_json_error(['message' => $message], $status);
}

function popcorn_auth_request_guard() {
    if (!popcorn_auth_enabled()) {
        popcorn_auth_error('سیستم حساب کاربری غیرفعال است.', 403);
    }
    if (!popcorn_auth_transport_secure() || !popcorn_auth_request_secure()) {
        popcorn_auth_error('برای ورود، اتصال امن HTTPS لازم است.', 403);
    }
    check_ajax_referer('popcorn_auth_form', 'nonce');
    if (is_user_logged_in()) {
        popcorn_auth_error('شما وارد حساب کاربری شده‌اید.', 403);
    }
}

function popcorn_auth_rate_key($kind, $value) {
    return 'pc_auth_' . hash_hmac('sha256', $kind . '|' . $value, wp_salt('nonce'));
}

function popcorn_auth_limit($identifier) {
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
    foreach ([['ip', $ip, 20], ['id', $identifier, 8]] as [$kind, $value, $limit]) {
        $key = popcorn_auth_rate_key($kind, $value);
        $count = (int) get_transient($key);
        if ($count >= $limit) {
            popcorn_auth_error('درخواست‌های زیادی ثبت شده است. کمی بعد تلاش کنید.', 429);
        }
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }
}

function popcorn_auth_send_sms($mobile, $code, $options) {
    return popcorn_auth_gateway_send($mobile, $code, $options);
}

add_action('wp_ajax_nopriv_popcorn_auth_send_code', function () {
    popcorn_auth_request_guard();
    $options = popcorn_auth_options();
    $identity = popcorn_auth_identifier(wp_unslash($_POST['identity'] ?? ''));
    if (!$identity || (is_email($identity) ? !$options['email_enabled'] : !$options['mobile_enabled'])) {
        popcorn_auth_error('ایمیل یا شماره موبایل معتبر و فعال وارد کنید.');
    }
    $intent = popcorn_auth_post_text('intent') === 'register' ? 'register' : 'login';
    $registration = $intent === 'register' ? popcorn_auth_registration_data() : null;
    if (is_wp_error($registration)) { popcorn_auth_error($registration->get_error_message()); }
    $user = popcorn_auth_find_user($identity);
    if ($intent === 'register' && ($user || !$options['registration'])) {
        popcorn_auth_error($user ? 'این حساب قبلاً ساخته شده است؛ از صفحهٔ ورود استفاده کنید.' : 'ثبت‌نام حساب جدید غیرفعال است.');
    }
    if (!$user && !$options['registration']) {
        popcorn_auth_error('ثبت‌نام حساب جدید غیرفعال است.');
    }
    $cooldown = popcorn_auth_rate_key('cooldown', $identity);
    if (get_transient($cooldown)) {
        popcorn_auth_error('برای دریافت کد بعدی کمی صبر کنید.', 429);
    }
    popcorn_auth_limit($identity);
    $length = min(8, max(4, (int) $options['otp_length']));
    $code = (string) random_int(10 ** ($length - 1), (10 ** $length) - 1);
    $key = popcorn_auth_rate_key('otp', $identity);
    set_transient($key, ['hash' => wp_hash_password($code), 'attempts' => 0, 'expires' => time() + 5 * MINUTE_IN_SECONDS, 'intent' => $intent, 'registration' => $registration ? array_intersect_key($registration, array_flip(['user_login', 'display_name'])) : null], 5 * MINUTE_IN_SECONDS);
    if (is_email($identity)) {
        $sent = wp_mail($identity, 'کد ورود به ' . get_bloginfo('name'), 'کد تأیید شما: ' . $code . "\nاین کد تا ۵ دقیقه اعتبار دارد.");
    } else {
        $sent = popcorn_auth_send_sms($identity, $code, $options);
    }
    if (is_wp_error($sent) || !$sent) {
        delete_transient($key);
        popcorn_auth_error('ارسال کد تأیید انجام نشد. تنظیمات درگاه یا ایمیل سایت را بررسی کنید.', 502);
    }
    set_transient($cooldown, 1, MINUTE_IN_SECONDS);
    wp_send_json_success(['message' => 'کد تأیید ارسال شد.', 'resend_after' => 60]);
});

add_action('wp_ajax_nopriv_popcorn_auth_verify_code', function () {
    popcorn_auth_request_guard();
    $identity = popcorn_auth_identifier(wp_unslash($_POST['identity'] ?? ''));
    $code = is_string($_POST['code'] ?? null) ? trim(strtr(wp_unslash($_POST['code']), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'])) : '';
    if (!$identity || !preg_match('/^[0-9]{4,8}$/', $code)) {
        popcorn_auth_error('کد تأیید معتبر نیست.');
    }
    popcorn_auth_limit($identity);
    $key = popcorn_auth_rate_key('otp', $identity);
    $otp = get_transient($key);
    if (!is_array($otp) || empty($otp['hash']) || (int) ($otp['attempts'] ?? 0) >= 5 || (int) ($otp['expires'] ?? 0) < time()) {
        popcorn_auth_error('کد تأیید منقضی شده است. کد تازه بگیرید.');
    }
    if (!wp_check_password($code, $otp['hash'])) {
        $otp['attempts'] = (int) ($otp['attempts'] ?? 0) + 1;
        set_transient($key, $otp, max(1, (int) $otp['expires'] - time()));
        popcorn_auth_error('کد تأیید درست نیست.');
    }
    $intent = popcorn_auth_post_text('intent') === 'register' ? 'register' : 'login';
    if (($otp['intent'] ?? 'login') !== $intent) { popcorn_auth_error('کد تأیید مربوط به این فرم نیست. دوباره کد بگیرید.'); }
    $registration = $intent === 'register' ? popcorn_auth_registration_data() : null;
    if (is_wp_error($registration)) { popcorn_auth_error($registration->get_error_message()); }
    if ($registration && (array_intersect_key($registration, array_flip(['user_login', 'display_name'])) !== ($otp['registration'] ?? null))) {
        popcorn_auth_error('اطلاعات ثبت‌نام تغییر کرده است؛ کد تازه بگیرید.');
    }
    $user = popcorn_auth_find_user($identity);
    if ($intent === 'register' && $user) { popcorn_auth_error('این حساب قبلاً ساخته شده است؛ وارد شوید.'); }
    delete_transient($key);
    if (!$user) {
        if (!popcorn_auth_options()['registration']) {
            popcorn_auth_error('ثبت‌نام حساب جدید غیرفعال است.', 403);
        }
        $login = $registration['user_login'] ?? (is_email($identity) ? 'pc_' . substr(hash('sha256', $identity), 0, 16) : 'pc_' . $identity);
        $user_id = wp_insert_user(['user_login' => $login, 'user_pass' => $registration['user_pass'] ?? wp_generate_password(32), 'user_email' => is_email($identity) ? $identity : '', 'display_name' => $registration['display_name'] ?? (is_email($identity) ? explode('@', $identity)[0] : 'کاربر ' . get_bloginfo('name')), 'role' => 'subscriber']);
        if (is_wp_error($user_id)) {
            popcorn_auth_error('ساخت حساب انجام نشد. دوباره تلاش کنید.', 500);
        }
        if (!is_email($identity)) {
            update_user_meta($user_id, 'popcorn_auth_mobile', $identity);
        }
        $user = get_userdata($user_id);
    }
    if (!$user || user_can($user, 'edit_posts')) {
        popcorn_auth_error('برای این حساب از صفحهٔ ورود وردپرس استفاده کنید.', 403);
    }
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, popcorn_auth_post_text('remember') === '1', is_ssl());
    do_action('wp_login', $user->user_login, $user);
    wp_send_json_success(['message' => 'وارد شدید.', 'redirect' => popcorn_auth_account_url() ?: home_url('/')]);
});

add_action('wp_ajax_nopriv_popcorn_auth_password', function () {
    popcorn_auth_request_guard();
    $identity = popcorn_auth_identifier(wp_unslash($_POST['identity'] ?? ''));
    $password = is_string($_POST['password'] ?? null) ? wp_unslash($_POST['password']) : '';
    if (!$identity || $password === '') {
        popcorn_auth_error('اطلاعات ورود معتبر نیست.');
    }
    popcorn_auth_limit($identity);
    $user = popcorn_auth_find_user($identity);
    if (!$user || user_can($user, 'edit_posts')) {
        popcorn_auth_error('اطلاعات ورود درست نیست.', 401);
    }
    $signed_in = wp_signon(['user_login' => $user->user_login, 'user_password' => $password, 'remember' => popcorn_auth_post_text('remember') === '1'], is_ssl());
    if (is_wp_error($signed_in)) {
        popcorn_auth_error('اطلاعات ورود درست نیست.', 401);
    }
    wp_send_json_success(['message' => 'وارد شدید.', 'redirect' => popcorn_auth_account_url() ?: home_url('/')]);
});

function popcorn_auth_enqueue_assets() {
    $kind = popcorn_auth_page_kind();
    if (!popcorn_auth_enabled() || !$kind) {
        return;
    }
    $base = dirname(__DIR__);
    $url = plugin_dir_url($base . '/popcorn-auth.php');
    wp_enqueue_style('popcorn-auth', $url . 'assets/css/auth.css', [], filemtime($base . '/assets/css/auth.css'));
    wp_enqueue_script('popcorn-auth', $url . 'assets/js/auth.js', [], filemtime($base . '/assets/js/auth.js'), true);
    if ($kind === 'account' && function_exists('popcorn_card_image_size')) {
        $file = get_template_directory() . '/assets/css/content-card.css';
        wp_enqueue_style('popcorn-content-card', get_template_directory_uri() . '/assets/css/content-card.css', ['popcorn-base'], filemtime($file));
    }
}
add_action('wp_enqueue_scripts', 'popcorn_auth_enqueue_assets', 20);

add_shortcode('popcorn_auth', 'popcorn_auth_form_html');
add_shortcode('popcorn_register', 'popcorn_auth_register_html');
add_shortcode('popcorn_account', 'popcorn_auth_account_html');

add_action('admin_post_popcorn_auth_profile', function () {
    if (!popcorn_auth_enabled() || !is_user_logged_in() || !popcorn_auth_transport_secure() || !popcorn_auth_request_secure()) {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    check_admin_referer('popcorn_auth_profile', '_pc_auth_profile');
    $name = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
    if ($name === '') {
        wp_die('نام نمایشی لازم است.', '', ['response' => 400]);
    }
    $updated = wp_update_user(['ID' => get_current_user_id(), 'display_name' => $name, 'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')), 'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''))]);
    if (is_wp_error($updated)) {
        wp_die('ذخیرهٔ اطلاعات حساب انجام نشد.', '', ['response' => 500]);
    }
    wp_safe_redirect(add_query_arg('popcorn_profile', 'saved', popcorn_auth_account_url() ?: home_url('/')));
    exit;
});

add_action('admin_post_popcorn_auth_change_password', function () {
    if (!popcorn_auth_enabled() || !is_user_logged_in() || !popcorn_auth_transport_secure() || !popcorn_auth_request_secure()) {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    check_admin_referer('popcorn_auth_change_password', '_pc_auth_password');
    $password = is_string($_POST['new_password'] ?? null) ? wp_unslash($_POST['new_password']) : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? wp_unslash($_POST['confirm_password']) : '';
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password) || $password !== $confirm) {
        wp_die('رمز عبور باید دست‌کم ۸ نویسه، شامل حرف انگلیسی و عدد باشد و با تکرار آن مطابقت کند.', '', ['response' => 400]);
    }
    wp_set_password($password, get_current_user_id());
    wp_clear_auth_cookie();
    wp_safe_redirect(add_query_arg('password_changed', '1', popcorn_auth_login_url()));
    exit;
});
