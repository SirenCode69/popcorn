<?php
defined('ABSPATH') || exit;

function popcorn_mymemory_chunks($source) {
    $source = trim(preg_replace('/\s+/u', ' ', (string) $source));
    if ($source === '') {
        return [];
    }
    $tokens = preg_split('/(\s+)/u', $source, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $chunks = [];
    $current = '';
    foreach ($tokens as $token) {
        if (strlen($current . $token) <= 450) {
            $current .= $token;
            continue;
        }
        if (trim($current) !== '') {
            $chunks[] = trim($current);
            $current = '';
        }
        if (strlen($token) <= 450) {
            $current = ltrim($token);
            continue;
        }
        foreach (preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) as $character) {
            if (strlen($current . $character) > 450) {
                $chunks[] = trim($current);
                $current = '';
            }
            $current .= $character;
        }
    }
    if (trim($current) !== '') {
        $chunks[] = trim($current);
    }
    return $chunks;
}

function popcorn_mymemory_translate($source) {
    if (trim($source) === '' || strlen($source) > 500) {
        return new WP_Error('mymemory_text', 'متن ترجمه نامعتبر یا بیش از حد مجاز است.');
    }
    $query = ['q' => $source, 'langpair' => 'en|fa'];
    $key = trim((string) get_option('popcorn_mymemory_key', ''));
    $email = trim((string) get_option('popcorn_mymemory_email', ''));
    if ($key !== '') {
        $query['key'] = $key;
    }
    if ($email !== '' && is_email($email)) {
        $query['de'] = $email;
    }
    $url = add_query_arg($query, 'https://api.mymemory.translated.net/get');
    $response = wp_safe_remote_get($url, ['headers' => ['Accept' => 'application/json'], 'timeout' => 12, 'limit_response_size' => 12000, 'redirection' => 0]);
    if (is_wp_error($response)) {
        return new WP_Error('mymemory_connection', 'اتصال به MyMemory برقرار نشد.');
    }
    if (wp_remote_retrieve_response_code($response) !== 200) {
        return new WP_Error('mymemory_http', 'پاسخ MyMemory ناموفق بود. کد: ' . wp_remote_retrieve_response_code($response));
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || (int) ($data['responseStatus'] ?? 0) !== 200) {
        return new WP_Error('mymemory_api', sanitize_text_field($data['responseDetails'] ?? 'پاسخ MyMemory معتبر نیست یا سهمیهٔ ترجمه تمام شده است.'));
    }
    $translated = $data['responseData']['translatedText'] ?? '';
    $translated = is_string($translated) ? sanitize_textarea_field(html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
    if ($translated === '' || !preg_match('/[\x{0600}-\x{06FF}]/u', $translated)) {
        return new WP_Error('mymemory_empty', 'ترجمهٔ فارسی معتبری دریافت نشد؛ مقدار قبلی تغییر نکرد.');
    }
    return $translated;
}

function popcorn_mymemory_translate_content_chunk($post_id, $field, $index) {
    $post = get_post($post_id);
    $allowed = ['popcorn_person' => ['persian_name', 'biography_fa'], 'popcorn_movie' => ['original_title', 'overview_en'], 'popcorn_series' => ['original_title', 'overview_en']];
    if (!$post || !current_user_can('edit_post', $post_id) || !in_array($field, $allowed[$post->post_type] ?? [], true)) {
        return new WP_Error('mymemory_forbidden', 'درخواست ترجمه مجاز نیست.');
    }
    if ($field === 'original_title') {
        $type = $post->post_type === 'popcorn_movie' ? 'movie' : 'series';
        $item = popcorn_core_get_by_post($type, $post_id);
        $source = (string) ($item->english_title ?? '');
    } else {
        $source = $field === 'persian_name' ? $post->post_title : wp_strip_all_tags(strip_shortcodes($post->post_content));
    }
    $chunks = popcorn_mymemory_chunks($source);
    if (!$chunks) {
        return new WP_Error('mymemory_source', 'ابتدا عنوان یا متن انگلیسی را ذخیره کنید.');
    }
    if (count($chunks) > 40) {
        return new WP_Error('mymemory_length', 'زندگی‌نامه برای ترجمهٔ یک‌باره بسیار بلند است.');
    }
    if ($index < 0 || $index >= count($chunks)) {
        return new WP_Error('mymemory_index', 'بخش درخواستی نامعتبر است.');
    }
    $translated = popcorn_mymemory_translate($chunks[$index]);
    return is_wp_error($translated) ? $translated : ['text' => $translated, 'index' => $index, 'total' => count($chunks)];
}

function popcorn_mymemory_translate_person_chunk($post_id, $field, $index) {
    if (get_post_type($post_id) !== 'popcorn_person') {
        return new WP_Error('mymemory_forbidden', 'درخواست ترجمه مجاز نیست.');
    }
    return popcorn_mymemory_translate_content_chunk($post_id, $field, $index);
}

function popcorn_mymemory_ajax_translate() {
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'دسترسی مجاز نیست.'], 403);
    }
    check_ajax_referer('popcorn_translate_content_' . $post_id, 'nonce');
    $field = isset($_POST['field']) ? sanitize_key(wp_unslash($_POST['field'])) : '';
    $index = isset($_POST['index']) ? absint($_POST['index']) : 0;
    $result = popcorn_mymemory_translate_content_chunk($post_id, $field, $index);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 400);
    }
    wp_send_json_success($result);
}
add_action('wp_ajax_popcorn_translate_content', 'popcorn_mymemory_ajax_translate');

function popcorn_mymemory_settings_tab() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $messages = ['saved' => 'تنظیمات MyMemory ذخیره شد.', 'connected' => 'ترجمهٔ آزمایشی MyMemory با موفقیت انجام شد.', 'invalid_email' => 'نشانی ایمیل معتبر نیست.', 'error' => 'اتصال یا ترجمهٔ آزمایشی MyMemory ناموفق بود.'];
    $message = isset($_GET['popcorn_message']) ? sanitize_key(wp_unslash($_GET['popcorn_message'])) : '';
    if (isset($messages[$message])) {
        echo '<div class="notice notice-' . (in_array($message, ['saved', 'connected'], true) ? 'success' : 'error') . '"><p>' . esc_html($messages[$message]) . '</p></div>';
    }
    $saved_key = trim((string) get_option('popcorn_mymemory_key', ''));
    $email = (string) get_option('popcorn_mymemory_email', '');
    echo '<div class="pc-admin__card"><h2>ترجمه با MyMemory</h2><p>در ویرایش هنرمندان، فیلم‌ها و سریال‌ها می‌توانید عنوان و متن انگلیسیِ ذخیره‌شده را با دکمهٔ ترجمه به فارسی برگردانید. هنگام دریافت از TMDb، اطلاعات فارسی در اولویت است و ترجمه فقط با انتخاب شما انجام می‌شود.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('popcorn_mymemory_settings');
    echo '<input type="hidden" name="action" value="popcorn_save_mymemory"><table class="form-table"><tr><th><label for="popcorn-mymemory-key">کلید API MyMemory</label></th><td><input id="popcorn-mymemory-key" name="mymemory_key" type="password" class="regular-text" autocomplete="new-password" placeholder="' . ($saved_key ? 'کلید ثبت شده است' : 'اختیاری') . '"><p class="description">کلید اختیاری است و مقدار ثبت‌شده نمایش داده نمی‌شود.</p><label><input type="checkbox" name="clear_mymemory_key" value="1"> حذف کلید ذخیره‌شده</label></td></tr><tr><th><label for="popcorn-mymemory-email">ایمیل تماس MyMemory</label></th><td><input id="popcorn-mymemory-email" name="mymemory_email" type="email" value="' . esc_attr($email) . '" class="regular-text" autocomplete="email"><p class="description">اختیاری؛ MyMemory برای سهمیهٔ رایگان بیشتر، ایمیل معتبر را توصیه می‌کند.</p></td></tr></table><p><button class="button button-primary">ذخیرهٔ تنظیمات</button></p></form><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('popcorn_test_mymemory');
    echo '<input type="hidden" name="action" value="popcorn_test_mymemory"><button class="button">بررسی اتصال MyMemory</button></form><p><a href="https://mymemory.translated.net/doc/spec.php" target="_blank" rel="noopener noreferrer">راهنمای رسمی API MyMemory</a></p></div>';
}
add_action('popcorn_settings_tab_translation', 'popcorn_mymemory_settings_tab');

add_action('admin_post_popcorn_save_mymemory', function () {
    current_user_can('manage_options') || wp_die('دسترسی مجاز نیست.');
    check_admin_referer('popcorn_mymemory_settings');
    $email = isset($_POST['mymemory_email']) ? trim(sanitize_email(wp_unslash($_POST['mymemory_email']))) : '';
    if ($email !== '' && !is_email($email)) {
        wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=translation&popcorn_message=invalid_email'));
        exit;
    }
    $key = isset($_POST['mymemory_key']) ? trim(sanitize_text_field(wp_unslash($_POST['mymemory_key']))) : '';
    if (!empty($_POST['clear_mymemory_key'])) {
        delete_option('popcorn_mymemory_key');
    } elseif ($key !== '') {
        update_option('popcorn_mymemory_key', substr($key, 0, 255), false);
    }
    update_option('popcorn_mymemory_email', $email, false);
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=translation&popcorn_message=saved'));
    exit;
});

add_action('admin_post_popcorn_test_mymemory', function () {
    current_user_can('manage_options') || wp_die('دسترسی مجاز نیست.');
    check_admin_referer('popcorn_test_mymemory');
    $result = popcorn_mymemory_translate('Hello');
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=translation&popcorn_message=' . (is_wp_error($result) ? 'error' : 'connected')));
    exit;
});
