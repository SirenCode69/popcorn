<?php
defined('ABSPATH') || exit;

function popcorn_auth_gateway_field_row($field, $options, $schema) {
    $visible = in_array($field, $schema['fields'], true);
    $id = 'pc-auth-' . str_replace('_', '-', $field);
    $label = $schema['labels'][$field];
    $help = $schema['help'][$field];
    echo '<tr data-pc-auth-field="' . esc_attr($field) . '"' . ($visible ? '' : ' hidden') . '><th><label for="' . esc_attr($id) . '" data-pc-auth-label>' . esc_html($label) . '</label></th><td>';
    if ($field === 'message') {
        echo '<textarea id="' . esc_attr($id) . '" name="popcorn_auth[message]" class="large-text" rows="3">' . esc_textarea($options['message']) . '</textarea>';
    } else {
        $secret = in_array($field, ['api_key', 'api_password'], true);
        $type = $secret ? 'password' : 'text';
        $value = $secret ? '' : $options[$field];
        $placeholder = $secret && $options[$field] !== '' ? 'ثبت شده است؛ برای تغییر مقدار جدید بنویسید' : $label;
        $dir = $field === 'template' ? $schema['template_dir'] : 'ltr';
        echo '<input id="' . esc_attr($id) . '" type="' . esc_attr($type) . '" name="popcorn_auth[' . esc_attr($field) . ']" value="' . esc_attr($value) . '" autocomplete="off" class="regular-text" dir="' . esc_attr($dir) . '" data-has-stored="' . ($secret && $options[$field] !== '' ? '1' : '0') . '" placeholder="' . esc_attr($placeholder) . '">';
    }
    echo '<p class="description" data-pc-auth-help>' . esc_html($help) . '</p></td></tr>';
}

function popcorn_auth_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $options = popcorn_auth_options();
    $gateway_schema = popcorn_auth_gateway_fields($options['gateway']);
    $status = isset($_GET['popcorn_message']) ? sanitize_key(wp_unslash($_GET['popcorn_message'])) : '';
    if ($status === 'saved') {
        echo '<div class="notice notice-success"><p>تنظیمات حساب کاربری ذخیره شد.</p></div>';
    } elseif ($status === 'page_error') {
        echo '<div class="notice notice-error"><p>صفحهٔ ورود، ثبت‌نام یا حساب کاربری ساخته نشد. دوباره تلاش کنید.</p></div>';
    } elseif ($status === 'https_required') {
        echo '<div class="notice notice-error"><p>برای فعال‌سازی حساب کاربری، نشانی سایت باید HTTPS باشد. در محیط محلی localhost، HTTP مجاز است.</p></div>';
    } elseif ($status === 'method_required') {
        echo '<div class="notice notice-error"><p>برای فعال‌سازی حساب کاربری، دست‌کم یکی از روش‌های ایمیل یا موبایل را روشن کنید.</p></div>';
    } elseif ($status === 'test_sent') {
        echo '<div class="notice notice-success"><p>پیامک آزمایشی از درگاه انتخاب‌شده ارسال شد.</p></div>';
    } elseif ($status === 'test_error') {
        $code = isset($_GET['popcorn_error']) ? sanitize_key(wp_unslash($_GET['popcorn_error'])) : 'gateway_rejected';
        echo '<div class="notice notice-error"><p>ارسال پیامک آزمایشی انجام نشد. کد خطا: <code>' . esc_html($code) . '</code></p></div>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="popcorn_auth_save_options">';
    wp_nonce_field('popcorn_auth_options');
    echo '<div class="pc-admin__card"><h2>سیستم حساب کاربری</h2><p>در حالت خاموش، لینک‌های ورود و حساب کاربری از سایت پنهان می‌شوند. در حالت روشن، ورود و ثبت‌نام پاپکورن فعال می‌شود.</p><table class="form-table"><tbody>';
    echo '<tr><th><label for="pc-auth-enabled">فعال‌سازی</label></th><td><label><input id="pc-auth-enabled" type="checkbox" name="popcorn_auth[enabled]" value="1"' . checked($options['enabled'], 1, false) . '> فعال‌کردن حساب کاربری پاپکورن</label></td></tr>';
    echo '<tr><th><label for="pc-auth-register">ثبت‌نام</label></th><td><label><input id="pc-auth-register" type="checkbox" name="popcorn_auth[registration]" value="1"' . checked($options['registration'], 1, false) . '> ساخت حساب جدید پس از تأیید ایمیل یا موبایل</label></td></tr>';
    echo '<tr><th><label for="pc-auth-email">ایمیل</label></th><td><label><input id="pc-auth-email" type="checkbox" name="popcorn_auth[email_enabled]" value="1"' . checked($options['email_enabled'], 1, false) . '> ورود با کد ارسالی به ایمیل</label></td></tr>';
    echo '<tr><th><label for="pc-auth-mobile">موبایل</label></th><td><label><input id="pc-auth-mobile" type="checkbox" name="popcorn_auth[mobile_enabled]" value="1"' . checked($options['mobile_enabled'], 1, false) . '> ورود با کد پیامکی</label></td></tr>';
    echo '<tr><th><label for="pc-auth-length">طول کد تأیید</label></th><td><select id="pc-auth-length" name="popcorn_auth[otp_length]">';
    foreach ([4, 5, 6, 7, 8] as $length) {
        echo '<option value="' . $length . '"' . selected($options['otp_length'], $length, false) . '>' . $length . ' رقم</option>';
    }
    echo '</select><p class="description">اعتبار کد ۵ دقیقه و فاصلهٔ ارسال دوباره ۶۰ ثانیه است.</p></td></tr></tbody></table></div>';
    echo '<div class="pc-admin__card"><h2>ظاهر صفحهٔ ورود و ثبت‌نام</h2><p>رنگ و حالت روشن/تاریک از تنظیمات عمومی قالب گرفته می‌شود. تصویر در نیمهٔ دوم صفحهٔ دسکتاپ نمایش داده می‌شود.</p><input type="hidden" id="pc-auth-graphic-id" name="popcorn_auth[graphic_id]" value="' . absint($options['graphic_id']) . '"><div id="pc-auth-graphic-preview">';
    if ($options['graphic_id']) { echo wp_get_attachment_image($options['graphic_id'], 'medium', false, ['style' => 'max-width:240px;height:auto;border-radius:12px']); }
    echo '</div><p><button type="button" class="button" data-auth-graphic-select>انتخاب تصویر</button> <button type="button" class="button" data-auth-graphic-remove>حذف تصویر</button></p><p class="description">بدون تصویر، طرح پیش‌فرض قالب نمایش داده می‌شود.</p></div>';
    echo '<div class="pc-admin__card"><h2>درگاه پیامک</h2><p>درگاه‌های افزونهٔ مرجع در این بخش در دسترس‌اند. همهٔ درخواست‌ها فقط با HTTPS ارسال می‌شوند؛ درگاه بدون اتصال امن پیامک ارسال نمی‌کند.</p><table class="form-table"><tbody>';
    echo '<tr><th><label for="pc-auth-gateway">درگاه</label></th><td><select id="pc-auth-gateway" name="popcorn_auth[gateway]" data-original-gateway="' . esc_attr($options['gateway']) . '">';
    foreach (popcorn_auth_gateways() as $id => $label) {
        echo '<option value="' . esc_attr($id) . '" data-schema="' . esc_attr(wp_json_encode(popcorn_auth_gateway_fields($id), JSON_UNESCAPED_UNICODE)) . '"' . selected($options['gateway'], $id, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select><p class="description">با انتخاب درگاه، فقط فیلدهای همان پنل نمایش داده می‌شوند. با تغییر درگاه، کلید و رمز قبلی پاک می‌شود؛ اطلاعات پنل تازه را وارد کنید.</p></td></tr>';
    foreach (['api_key', 'api_password', 'from', 'template', 'message'] as $field) {
        popcorn_auth_gateway_field_row($field, $options, $gateway_schema);
    }
    echo '</tbody></table></div>';
    submit_button('ذخیرهٔ تنظیمات');
    echo '</form><div class="pc-auth-settings__aside">';
    if ($options['login_page_id'] && get_post_status($options['login_page_id']) === 'publish') {
        echo '<div class="pc-admin__card"><h2>صفحه‌های کاربری</h2><p><a href="' . esc_url(get_permalink($options['login_page_id'])) . '" target="_blank" rel="noopener noreferrer">صفحهٔ ورود</a>';
        if ($options['register_page_id'] && get_post_status($options['register_page_id']) === 'publish') {
            echo ' · <a href="' . esc_url(get_permalink($options['register_page_id'])) . '" target="_blank" rel="noopener noreferrer">صفحهٔ ثبت‌نام</a>';
        }
        if ($options['account_page_id'] && get_post_status($options['account_page_id']) === 'publish') {
            echo ' · <a href="' . esc_url(get_permalink($options['account_page_id'])) . '" target="_blank" rel="noopener noreferrer">صفحهٔ حساب کاربری</a>';
        }
        echo '</p><p>شورت‌کدها: <code>[popcorn_auth]</code>، <code>[popcorn_register]</code> و <code>[popcorn_account]</code></p></div>';
    }
    echo '<div class="pc-admin__card"><h2>آزمایش درگاه انتخاب‌شده</h2><p>پس از ذخیرهٔ تنظیمات، یک کد آزمایشی به شمارهٔ زیر ارسال کنید. این آزمایش حساب کاربری نمی‌سازد.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="popcorn_auth_test_sms">';
    wp_nonce_field('popcorn_auth_test_sms');
    echo '<label for="pc-auth-test-mobile">شماره موبایل</label><p><input id="pc-auth-test-mobile" type="text" name="mobile" class="regular-text" dir="ltr" inputmode="tel" placeholder="09123456789" pattern="09[0-9]{9}" required></p>';
    submit_button('ارسال پیامک آزمایشی', 'secondary');
    echo '</form></div></div>';
}
add_action('popcorn_settings_tab_account', 'popcorn_auth_settings_page');

add_action('admin_enqueue_scripts', function ($hook) {
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    if ($hook !== 'toplevel_page_popcorn-settings' || $tab !== 'account') {
        return;
    }
    $path = dirname(__DIR__);
    wp_enqueue_media();
    wp_enqueue_script('popcorn-auth-appearance', plugin_dir_url($path . '/popcorn-auth.php') . 'assets/js/admin-appearance.js', ['media-views'], filemtime($path . '/assets/js/admin-appearance.js'), true);
    wp_enqueue_script('popcorn-auth-gateways', plugin_dir_url($path . '/popcorn-auth.php') . 'assets/js/admin-gateways.js', [], filemtime($path . '/assets/js/admin-gateways.js'), true);
});

function popcorn_auth_create_page($id, $title, $slug, $shortcode) {
    if ($id && get_post_status($id) === 'publish') {
        return $id;
    }
    $existing = get_page_by_path($slug);
    if ($existing && $existing->post_status === 'publish' && has_shortcode($existing->post_content, substr($shortcode, 1, -1))) {
        return (int) $existing->ID;
    }
    $created = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $shortcode, 'comment_status' => 'closed'], true);
    return is_wp_error($created) ? 0 : (int) $created;
}

add_action('admin_post_popcorn_auth_save_options', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    check_admin_referer('popcorn_auth_options');
    $input = isset($_POST['popcorn_auth']) && is_array($_POST['popcorn_auth']) ? wp_unslash($_POST['popcorn_auth']) : [];
    $old = popcorn_auth_options();
    $options = $old;
    $graphic_id = isset($input['graphic_id']) && is_scalar($input['graphic_id']) ? absint($input['graphic_id']) : 0;
    $options['graphic_id'] = $graphic_id && wp_attachment_is_image($graphic_id) ? $graphic_id : 0;
    foreach (['enabled', 'registration', 'email_enabled', 'mobile_enabled'] as $key) {
        $options[$key] = empty($input[$key]) ? 0 : 1;
    }
    $options['gateway'] = isset(popcorn_auth_gateways()[$input['gateway'] ?? '']) ? $input['gateway'] : 'kavenegar';
    if ($options['gateway'] !== $old['gateway']) {
        $options['api_key'] = '';
        $options['api_password'] = '';
    }
    $gateway_fields = popcorn_auth_gateway_fields($options['gateway'])['fields'];
    $options['otp_length'] = min(8, max(4, absint($input['otp_length'] ?? 6)));
    $options['template'] = in_array('template', $gateway_fields, true) ? sanitize_text_field($input['template'] ?? '') : '';
    $options['from'] = in_array('from', $gateway_fields, true) ? sanitize_text_field($input['from'] ?? '') : '';
    $options['message'] = in_array('message', $gateway_fields, true) ? sanitize_textarea_field($input['message'] ?? '') : '';
    $key = is_string($input['api_key'] ?? null) ? trim($input['api_key']) : '';
    if ($key !== '') {
        $options['api_key'] = sanitize_text_field($key);
    }
    $password = is_string($input['api_password'] ?? null) ? trim($input['api_password']) : '';
    if ($password !== '') {
        $options['api_password'] = sanitize_text_field($password);
    }
    if (!in_array('api_password', $gateway_fields, true)) {
        $options['api_password'] = '';
    }
    if ($options['enabled'] && !popcorn_auth_transport_secure()) {
        $options['enabled'] = 0;
        $message = 'https_required';
    }
    if ($options['enabled'] && !$options['email_enabled'] && !$options['mobile_enabled']) {
        $options['enabled'] = 0;
        $message = 'method_required';
    }
    if ($options['enabled']) {
        $options['login_page_id'] = popcorn_auth_create_page($old['login_page_id'], 'ورود و ثبت‌نام', 'popcorn-login', '[popcorn_auth]');
        $options['account_page_id'] = popcorn_auth_create_page($old['account_page_id'], 'حساب کاربری', 'popcorn-account', '[popcorn_account]');
        if ($options['registration']) {
            $options['register_page_id'] = popcorn_auth_create_page($old['register_page_id'], 'ثبت‌نام', 'popcorn-register', '[popcorn_register]');
        }
        if (!$options['login_page_id'] || !$options['account_page_id'] || ($options['registration'] && !$options['register_page_id'])) {
            $options['enabled'] = 0;
            $message = 'page_error';
        }
    }
    update_option('popcorn_auth_options', $options, false);
    wp_safe_redirect(add_query_arg('popcorn_message', $message ?? 'saved', admin_url('admin.php?page=popcorn-settings&tab=account')));
    exit;
});

add_action('admin_post_popcorn_auth_test_sms', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    if (!popcorn_auth_transport_secure() || !popcorn_auth_request_secure()) {
        wp_die('برای آزمایش پیامک، اتصال امن HTTPS لازم است.', '', ['response' => 403]);
    }
    check_admin_referer('popcorn_auth_test_sms');
    $mobile = popcorn_auth_identifier(wp_unslash($_POST['mobile'] ?? ''));
    if (!$mobile || is_email($mobile)) {
        wp_die('شماره موبایل معتبر نیست.', '', ['response' => 400]);
    }
    $code = (string) random_int(100000, 999999);
    $result = popcorn_auth_send_sms($mobile, $code, popcorn_auth_options());
    $status = $result === true ? 'test_sent' : 'test_error';
    $error = is_wp_error($result) ? $result->get_error_code() : 'gateway_rejected';
    wp_safe_redirect(add_query_arg(['popcorn_message' => $status, 'popcorn_error' => sanitize_key($error)], admin_url('admin.php?page=popcorn-settings&tab=account')));
    exit;
});
