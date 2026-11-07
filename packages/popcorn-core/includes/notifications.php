<?php
defined('ABSPATH') || exit;

function popcorn_notification_options() {
    $defaults = ['site_enabled' => 1, 'telegram_enabled' => 0, 'favorite_notices' => 1, 'bot_token' => '', 'bot_username' => '', 'notice_title_pattern' => 'اطلاعیهٔ {title}', 'notice_message_pattern' => '{message}', 'retention_days' => 90];
    $saved = get_option('popcorn_notification_options', []);
    return is_array($saved) ? array_merge($defaults, $saved) : $defaults;
}

function popcorn_notification_url() {
    return home_url('/notifications/');
}

function popcorn_notification_telegram_request($method, $fields) {
    $token = (string) popcorn_notification_options()['bot_token'];
    if (!preg_match('/^[0-9]+:[A-Za-z0-9_-]{30,}$/', $token) || !in_array($method, ['getMe', 'setWebhook', 'sendMessage'], true)) {
        return new WP_Error('telegram_config', 'توکن بات تلگرام تنظیم نشده یا معتبر نیست.');
    }
    $response = wp_remote_post('https://api.telegram.org/bot' . $token . '/' . $method, ['timeout' => 8, 'redirection' => 0, 'body' => $fields]);
    if (is_wp_error($response)) {
        return new WP_Error('telegram_connection', 'ارتباط امن با تلگرام برقرار نشد.');
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (wp_remote_retrieve_response_code($response) !== 200 || !is_array($data) || empty($data['ok'])) {
        return new WP_Error('telegram_api', is_array($data) ? sanitize_text_field($data['description'] ?? 'پاسخ تلگرام معتبر نیست.') : 'پاسخ تلگرام معتبر نیست.');
    }
    return $data;
}

function popcorn_notification_schedule_telegram($delay = 5) {
    if (!wp_next_scheduled('popcorn_notification_telegram_queue')) {
        wp_schedule_single_event(time() + $delay, 'popcorn_notification_telegram_queue');
    }
}

function popcorn_notification_deliver($user_id, $source, $title, $message, $url, $site, $telegram) {
    $user_id = absint($user_id);
    if (!$user_id || !get_userdata($user_id)) {
        return 0;
    }
    $options = popcorn_notification_options();
    $site = $site && $options['site_enabled'] && !get_user_meta($user_id, 'popcorn_notification_site_off', true);
    $chat = (string) get_user_meta($user_id, 'popcorn_telegram_chat_id', true);
    $telegram = $telegram && $options['telegram_enabled'] && $chat !== '' && !get_user_meta($user_id, 'popcorn_notification_telegram_off', true);
    if (!$site && !$telegram) {
        return 0;
    }
    global $wpdb;
    $table = popcorn_core_table('notification');
    $title = sanitize_text_field($title);
    $title = function_exists('mb_substr') ? mb_substr($title, 0, 191) : substr($title, 0, 191);
    $saved = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (user_id,source_key,title,message,url,show_in_site,telegram_status,created_at) VALUES (%d,%s,%s,%s,%s,%d,%s,%s)", $user_id, sanitize_key($source), $title, sanitize_textarea_field($message), esc_url_raw($url, ['https', 'http']), (int) $site, $telegram ? 'pending' : 'none', current_time('mysql')));
    if ($saved && $telegram) {
        popcorn_notification_schedule_telegram();
    }
    return $saved ? (int) $wpdb->insert_id : 0;
}

add_action('popcorn_notification_telegram_queue', function () {
    global $wpdb;
    $table = popcorn_core_table('notification');
    $wpdb->query($wpdb->prepare("UPDATE $table SET telegram_status='pending' WHERE telegram_status='sending' AND telegram_claimed_at < %s", wp_date('Y-m-d H:i:s', time() - 300)));
    $wpdb->query("UPDATE $table SET telegram_status='failed' WHERE telegram_status='pending' AND telegram_attempts>=3");
    $rows = $wpdb->get_results("SELECT id,user_id,title,message,url,telegram_attempts FROM $table WHERE telegram_status='pending' AND telegram_attempts<3 ORDER BY id ASC LIMIT 5");
    foreach ($rows as $row) {
        $claimed = $wpdb->query($wpdb->prepare("UPDATE $table SET telegram_status='sending',telegram_attempts=telegram_attempts+1,telegram_claimed_at=%s WHERE id=%d AND telegram_status='pending'", current_time('mysql'), $row->id));
        if (!$claimed) {
            continue;
        }
        $chat = (string) get_user_meta($row->user_id, 'popcorn_telegram_chat_id', true);
        if ($chat === '' || !popcorn_notification_options()['telegram_enabled'] || get_user_meta($row->user_id, 'popcorn_notification_telegram_off', true)) {
            $status = 'skipped';
        } else {
            $body = trim($row->title . "\n\n" . $row->message . ($row->url ? "\n\n" . $row->url : ''));
            $text = function_exists('mb_substr') ? mb_substr($body, 0, 4000) : substr($body, 0, 4000);
            $result = popcorn_notification_telegram_request('sendMessage', ['chat_id' => $chat, 'text' => $text]);
            $status = is_wp_error($result) ? ((int) $row->telegram_attempts >= 2 ? 'failed' : 'pending') : 'sent';
        }
        $wpdb->update($table, ['telegram_status' => $status, 'telegram_claimed_at' => null], ['id' => $row->id]);
    }
    if ($wpdb->get_var("SELECT id FROM $table WHERE telegram_status='pending' AND telegram_attempts<3 LIMIT 1")) {
        popcorn_notification_schedule_telegram(60);
    }
});

function popcorn_notification_schedule_notice($notice_id) {
    $notice_id = absint($notice_id);
    if ($notice_id && !wp_next_scheduled('popcorn_notification_notice_batch', [$notice_id, 0])) {
        wp_schedule_single_event(time() + 5, 'popcorn_notification_notice_batch', [$notice_id, 0]);
    }
}

add_action('popcorn_notification_notice_batch', function ($notice_id, $cursor) {
    global $wpdb;
    $notices = popcorn_core_table('notice');
    $notice = $wpdb->get_row($wpdb->prepare("SELECT post_id,message FROM $notices WHERE id=%d AND is_active=1", absint($notice_id)));
    if (!$notice || get_post_status($notice->post_id) !== 'publish' || !popcorn_notification_options()['favorite_notices']) {
        return;
    }
    $favorites = popcorn_core_table('user_favorite');
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id,user_id FROM $favorites WHERE post_id=%d AND id>%d ORDER BY id ASC LIMIT 50", $notice->post_id, absint($cursor)));
    $options = popcorn_notification_options();
    $variables = ['{title}' => get_the_title($notice->post_id), '{message}' => $notice->message];
    $title = strtr($options['notice_title_pattern'], $variables);
    $message = strtr($options['notice_message_pattern'], $variables);
    foreach ($rows as $favorite) {
        popcorn_notification_deliver($favorite->user_id, 'notice-' . $notice_id, $title, $message, get_permalink($notice->post_id), true, true);
    }
    if (count($rows) === 50) {
        wp_schedule_single_event(time() + 5, 'popcorn_notification_notice_batch', [$notice_id, (int) end($rows)->id]);
    }
}, 10, 2);

add_action('popcorn_notice_created', 'popcorn_notification_schedule_notice');

add_action('popcorn_notice_deleted', function ($notice_id) {
    global $wpdb;
    $wpdb->delete(popcorn_core_table('notification'), ['source_key' => 'notice-' . absint($notice_id)]);
});

add_action('before_delete_post', function ($post_id, $post) {
    if (!in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        return;
    }
    global $wpdb;
    $notices = popcorn_core_table('notice');
    $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $notices WHERE post_id=%d", $post_id));
    foreach ($ids as $id) {
        do_action('popcorn_notice_deleted', $id);
    }
}, 8, 2);

add_action('deleted_user', function ($user_id) {
    global $wpdb;
    $wpdb->delete(popcorn_core_table('notification'), ['user_id' => absint($user_id)]);
});

add_action('transition_post_status', function ($new, $old, $post) {
    if ($new !== 'publish' || $old === 'publish' || !in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        return;
    }
    global $wpdb;
    $table = popcorn_core_table('notice');
    $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE post_id=%d AND is_active=1", $post->ID));
    foreach ($ids as $id) {
        popcorn_notification_schedule_notice($id);
    }
}, 10, 3);

add_action('popcorn_notification_campaign_batch', function ($campaign_id) {
    global $wpdb;
    $table = popcorn_core_table('notification_campaign');
    $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d AND completed_at IS NULL", absint($campaign_id)));
    if (!$campaign) {
        return;
    }
    if ($campaign->audience === 'favorites') {
        $favorites = popcorn_core_table('user_favorite');
        $ids = $wpdb->get_col($wpdb->prepare("SELECT user_id FROM $favorites WHERE post_id=%d AND user_id>%d ORDER BY user_id ASC LIMIT 50", $campaign->target_id, $campaign->last_user_id));
    } elseif ($campaign->audience === 'user') {
        $ids = $campaign->last_user_id ? [] : [$campaign->target_id];
    } else {
        $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->users WHERE ID>%d ORDER BY ID ASC LIMIT 50", $campaign->last_user_id));
    }
    foreach ($ids as $user_id) {
        popcorn_notification_deliver($user_id, 'campaign-' . $campaign->id, $campaign->title, $campaign->message, $campaign->url, (bool) $campaign->site_enabled, (bool) $campaign->telegram_enabled);
    }
    if ($ids) {
        $wpdb->update($table, ['last_user_id' => (int) end($ids)], ['id' => $campaign->id]);
    }
    if (count($ids) === 50) {
        wp_schedule_single_event(time() + 5, 'popcorn_notification_campaign_batch', [$campaign->id]);
    } else {
        $wpdb->update($table, ['completed_at' => current_time('mysql')], ['id' => $campaign->id]);
    }
});

function popcorn_notification_admin_return($status) {
    wp_safe_redirect(add_query_arg(['page' => 'popcorn-settings', 'tab' => 'notifications', 'pc_notification' => sanitize_key($status)], admin_url('admin.php')));
    exit;
}

function popcorn_notification_admin_guard($action) {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی ندارید.', '', ['response' => 403]);
    }
    check_admin_referer($action);
}

add_action('admin_post_popcorn_notification_settings', function () {
    popcorn_notification_admin_guard('popcorn_notification_settings');
    $old = popcorn_notification_options();
    $token = isset($_POST['bot_token']) && is_string($_POST['bot_token']) ? trim(wp_unslash($_POST['bot_token'])) : '';
    if ($token !== '' && (strlen($token) > 128 || !preg_match('/^[0-9]+:[A-Za-z0-9_-]{30,}$/', $token))) {
        popcorn_notification_admin_return('invalid_token');
    }
    $username = isset($_POST['bot_username']) && is_string($_POST['bot_username']) ? ltrim(trim(wp_unslash($_POST['bot_username'])), '@') : '';
    if ($username !== '' && !preg_match('/^[A-Za-z0-9_]{5,32}$/', $username)) {
        popcorn_notification_admin_return('invalid_username');
    }
    $options = [
        'site_enabled' => isset($_POST['site_enabled']) ? 1 : 0,
        'telegram_enabled' => isset($_POST['telegram_enabled']) ? 1 : 0,
        'favorite_notices' => isset($_POST['favorite_notices']) ? 1 : 0,
        'bot_token' => $token ?: $old['bot_token'],
        'bot_username' => $username,
        'notice_title_pattern' => sanitize_text_field(wp_unslash($_POST['notice_title_pattern'] ?? 'اطلاعیهٔ {title}')),
        'notice_message_pattern' => sanitize_textarea_field(wp_unslash($_POST['notice_message_pattern'] ?? '{message}')),
        'retention_days' => min(365, max(7, absint($_POST['retention_days'] ?? 90))),
    ];
    if ($options['notice_title_pattern'] === '' || $options['notice_message_pattern'] === '' || strlen($options['notice_title_pattern']) > 191 || strlen($options['notice_message_pattern']) > 2000) {
        popcorn_notification_admin_return('invalid_pattern');
    }
    update_option('popcorn_notification_options', $options, false);
    if ($token !== '' && $token !== $old['bot_token']) {
        delete_option('popcorn_notification_webhook_secret');
    }
    popcorn_notification_admin_return('saved');
});

add_action('admin_post_popcorn_notification_telegram_check', function () {
    popcorn_notification_admin_guard('popcorn_notification_telegram_check');
    $result = popcorn_notification_telegram_request('getMe', []);
    popcorn_notification_admin_return(is_wp_error($result) ? 'telegram_failed' : 'telegram_ready');
});

add_action('admin_post_popcorn_notification_telegram_webhook', function () {
    popcorn_notification_admin_guard('popcorn_notification_telegram_webhook');
    $url = rest_url('popcorn/v1/telegram-webhook');
    $host = (string) wp_parse_url($url, PHP_URL_HOST);
    if (wp_parse_url($url, PHP_URL_SCHEME) !== 'https' || in_array($host, ['localhost', '127.0.0.1'], true)) {
        popcorn_notification_admin_return('https_required');
    }
    $secret = bin2hex(random_bytes(32));
    $result = popcorn_notification_telegram_request('setWebhook', ['url' => $url, 'secret_token' => $secret, 'allowed_updates' => wp_json_encode(['message'])]);
    if (is_wp_error($result)) {
        popcorn_notification_admin_return('webhook_failed');
    }
    update_option('popcorn_notification_webhook_secret', $secret, false);
    popcorn_notification_admin_return('webhook_ready');
});

add_action('admin_post_popcorn_notification_campaign', function () {
    popcorn_notification_admin_guard('popcorn_notification_campaign');
    $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $url = esc_url_raw(wp_unslash($_POST['url'] ?? ''), ['https', 'http']);
    $audience = sanitize_key(wp_unslash($_POST['audience'] ?? 'all'));
    $target = absint($_POST['target_id'] ?? 0);
    $site = isset($_POST['site_enabled']) ? 1 : 0;
    $telegram = isset($_POST['telegram_enabled']) ? 1 : 0;
    $valid_target = $audience === 'all' || ($audience === 'user' && get_userdata($target)) || ($audience === 'favorites' && popcorn_core_notice_post($target) && get_post_status($target) === 'publish');
    if ($title === '' || $message === '' || strlen($title) > 191 || strlen($message) > 4000 || strlen($url) > 2048 || !$valid_target || (!$site && !$telegram)) {
        popcorn_notification_admin_return('invalid_campaign');
    }
    if ($site && !popcorn_notification_options()['site_enabled']) {
        popcorn_notification_admin_return('site_disabled');
    }
    if ($telegram && !popcorn_notification_options()['telegram_enabled']) {
        popcorn_notification_admin_return('telegram_disabled');
    }
    global $wpdb;
    $saved = $wpdb->insert(popcorn_core_table('notification_campaign'), ['title' => $title, 'message' => $message, 'url' => $url, 'audience' => $audience, 'target_id' => $target, 'site_enabled' => $site, 'telegram_enabled' => $telegram, 'created_at' => current_time('mysql')]);
    if (!$saved) {
        popcorn_notification_admin_return('campaign_failed');
    }
    wp_schedule_single_event(time() + 5, 'popcorn_notification_campaign_batch', [(int) $wpdb->insert_id]);
    popcorn_notification_admin_return('campaign_queued');
});

add_action('wp_ajax_popcorn_notification_target_search', function () {
    if (!current_user_can('manage_options') || !check_ajax_referer('popcorn_notification_target_search', 'nonce', false)) {
        wp_send_json_error(['message' => 'دسترسی مجاز نیست.'], 403);
    }
    $query = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
    $audience = sanitize_key(wp_unslash($_GET['audience'] ?? ''));
    $length = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);
    if ($length < 2 || $length > 80 || !in_array($audience, ['user', 'favorites'], true)) {
        wp_send_json_success(['items' => []]);
    }
    $items = [];
    if ($audience === 'user') {
        $users = get_users(['search' => '*' . $query . '*', 'search_columns' => ['user_login', 'user_email', 'display_name'], 'number' => 10]);
        foreach ($users as $user) {
            $items[] = ['id' => (int) $user->ID, 'label' => $user->display_name . ' · ' . $user->user_email];
        }
    } else {
        $posts = get_posts(['post_type' => ['popcorn_movie', 'popcorn_series'], 'post_status' => 'publish', 's' => $query, 'fields' => 'ids', 'numberposts' => 10]);
        foreach ($posts as $post_id) {
            $items[] = ['id' => (int) $post_id, 'label' => get_the_title($post_id) . ' · ' . (get_post_type($post_id) === 'popcorn_series' ? 'سریال' : 'فیلم')];
        }
    }
    wp_send_json_success(['items' => $items]);
});

add_action('admin_post_popcorn_notification_retry_telegram', function () {
    popcorn_notification_admin_guard('popcorn_notification_retry_telegram');
    global $wpdb;
    $table = popcorn_core_table('notification');
    $wpdb->query("UPDATE $table SET telegram_status='pending',telegram_attempts=0 WHERE telegram_status='failed'");
    popcorn_notification_schedule_telegram();
    popcorn_notification_admin_return('retry_queued');
});

add_action('init', function () {
    if (!wp_next_scheduled('popcorn_notification_prune')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'popcorn_notification_prune');
    }
});

add_action('popcorn_notification_prune', function () {
    global $wpdb;
    $table = popcorn_core_table('notification');
    $days = min(365, max(7, (int) popcorn_notification_options()['retention_days']));
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE read_at IS NOT NULL AND read_at < %s", wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)));
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s", wp_date('Y-m-d H:i:s', time() - $days * 2 * DAY_IN_SECONDS)));
    $campaigns = popcorn_core_table('notification_campaign');
    $wpdb->query($wpdb->prepare("DELETE FROM $campaigns WHERE completed_at IS NOT NULL AND completed_at < %s", wp_date('Y-m-d H:i:s', time() - $days * 2 * DAY_IN_SECONDS)));
});

add_action('popcorn_settings_tab_notifications', function () {
    $options = popcorn_notification_options();
    global $wpdb;
    $notifications = popcorn_core_table('notification');
    $campaigns = popcorn_core_table('notification_campaign');
    $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM $notifications WHERE telegram_status='failed'");
    $recent = $wpdb->get_results("SELECT id,title,audience,completed_at,created_at FROM $campaigns ORDER BY id DESC LIMIT 10");
    $messages = ['saved' => 'تنظیمات اعلان‌ها ذخیره شد.', 'invalid_token' => 'توکن بات معتبر نیست.', 'invalid_username' => 'نام کاربری بات معتبر نیست.', 'invalid_pattern' => 'الگوی پیام معتبر نیست.', 'telegram_failed' => 'ارتباط با بات تلگرام برقرار نشد؛ توکن یا اتصال شبکه را بررسی کنید.', 'telegram_ready' => 'بات تلگرام پاسخ داد.', 'https_required' => 'برای Webhook، نشانی عمومی HTTPS لازم است.', 'webhook_failed' => 'ثبت Webhook ناموفق بود.', 'webhook_ready' => 'Webhook تلگرام ثبت شد.', 'invalid_campaign' => 'عنوان، متن، مخاطب و کانال اطلاعیه را بررسی کنید.', 'site_disabled' => 'ابتدا اعلان داخل سایت را در تنظیمات فعال کنید.', 'telegram_disabled' => 'ابتدا کانال تلگرام را در تنظیمات فعال کنید.', 'campaign_failed' => 'ذخیرهٔ اطلاعیه انجام نشد.', 'campaign_queued' => 'اطلاعیه در صف ارسال قرار گرفت.', 'retry_queued' => 'ارسال‌های ناموفق تلگرام دوباره در صف قرار گرفتند.'];
    $status = isset($_GET['pc_notification']) ? sanitize_key(wp_unslash($_GET['pc_notification'])) : '';
    if (isset($messages[$status])) {
        echo '<div class="notice notice-' . (in_array($status, ['saved', 'telegram_ready', 'webhook_ready', 'campaign_queued', 'retry_queued'], true) ? 'success' : 'error') . '"><p>' . esc_html($messages[$status]) . '</p></div>';
    }
    ?>
    <div class="pc-notification-settings__column pc-notification-settings__column--right">
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="pc-notification-settings pc-notification-settings--site">
        <input type="hidden" name="action" value="popcorn_notification_settings">
        <?php wp_nonce_field('popcorn_notification_settings'); ?>
        <div class="pc-admin__card">
        <h2>اعلان‌های سایت</h2>
        <p>اطلاعیه‌های فیلم و سریال به کاربرانی که آن اثر را به علاقه‌مندی‌ها افزوده‌اند می‌رسد. دریافت اعلان به فعال‌بودن حساب کاربری پاپکورن وابسته نیست.</p>
        <table class="form-table"><tbody>
            <tr><th>کانال‌های پیش‌فرض</th><td><label><input type="checkbox" name="site_enabled" value="1" <?php checked($options['site_enabled']); ?>> اعلان داخل سایت</label><br><label><input type="checkbox" name="telegram_enabled" value="1" <?php checked($options['telegram_enabled']); ?>> ارسال تلگرام برای کاربران متصل</label><br><label><input type="checkbox" name="favorite_notices" value="1" <?php checked($options['favorite_notices']); ?>> اطلاعیه‌های فیلم و سریال برای علاقه‌مندان</label></td></tr>
            <tr><th><label for="pc-notification-bot-token">توکن بات تلگرام</label></th><td><input id="pc-notification-bot-token" type="password" name="bot_token" autocomplete="new-password" placeholder="<?php echo $options['bot_token'] ? 'توکن ثبت شده؛ برای تغییر، مقدار تازه وارد کنید' : 'توکن دریافتی از BotFather'; ?>"><p class="description">توکن در صفحه نمایش داده نمی‌شود و فقط از طریق HTTPS به Telegram Bot API فرستاده می‌شود.</p></td></tr>
            <tr><th><label for="pc-notification-bot-username">نام کاربری بات</label></th><td><input id="pc-notification-bot-username" name="bot_username" value="<?php echo esc_attr($options['bot_username']); ?>" placeholder="MyPopcornBot" dir="ltr"></td></tr>
            <tr><th><label for="pc-notification-title-pattern">عنوان اطلاعیهٔ آثار</label></th><td><input id="pc-notification-title-pattern" name="notice_title_pattern" value="<?php echo esc_attr($options['notice_title_pattern']); ?>"><p class="description">جای‌گذاری: {title}</p></td></tr>
            <tr><th><label for="pc-notification-message-pattern">متن اطلاعیهٔ آثار</label></th><td><textarea id="pc-notification-message-pattern" name="notice_message_pattern" rows="3"><?php echo esc_textarea($options['notice_message_pattern']); ?></textarea><p class="description">جای‌گذاری‌ها: {title} و {message}</p></td></tr>
            <tr><th><label for="pc-notification-retention">نگه‌داری اعلان‌ها</label></th><td><input id="pc-notification-retention" type="number" name="retention_days" min="7" max="365" value="<?php echo esc_attr($options['retention_days']); ?>"> روز پس از خوانده‌شدن</td></tr>
        </tbody></table>
        </div>
        <?php submit_button('ذخیرهٔ تنظیمات اعلان‌ها'); ?>
    </form>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="pc-notification-settings pc-notification-settings--campaign">
        <input type="hidden" name="action" value="popcorn_notification_campaign"><?php wp_nonce_field('popcorn_notification_campaign'); ?>
        <div class="pc-admin__card">
        <h2>ارسال اطلاعیهٔ عمومی</h2><p>اعلان برای مخاطبان انتخاب‌شده در صف قرار می‌گیرد. هر کاربر فقط یک نسخه از این اطلاعیه دریافت می‌کند.</p>
        <table class="form-table"><tbody>
            <tr><th><label for="pc-notification-title">عنوان</label></th><td><input id="pc-notification-title" name="title" maxlength="191" required></td></tr>
            <tr><th><label for="pc-notification-message">متن</label></th><td><textarea id="pc-notification-message" name="message" rows="4" maxlength="4000" required></textarea></td></tr>
            <tr><th><label for="pc-notification-url">لینک مقصد</label></th><td><input id="pc-notification-url" type="url" name="url" placeholder="https://..."></td></tr>
            <tr><th><label for="pc-notification-audience">مخاطب</label></th><td><select id="pc-notification-audience" name="audience"><option value="all">همهٔ کاربران</option><option value="user">یک کاربر</option><option value="favorites">علاقه‌مندان یک فیلم یا سریال</option></select><div class="pc-notification-target" hidden><input id="pc-notification-target-search" type="search" placeholder="جست‌وجوی مخاطب" autocomplete="off" hidden><input id="pc-notification-target-id" type="number" name="target_id" min="1" placeholder="شناسهٔ کاربر یا فیلم/سریال"><div class="pc-notification-target__results" role="listbox" hidden></div></div><p class="description">برای «یک کاربر» نام یا ایمیل و برای علاقه‌مندان، نام فیلم یا سریال را جست‌وجو کنید.</p></td></tr>
            <tr><th>کانال‌ها</th><td><label><input type="checkbox" name="site_enabled" value="1" checked> داخل سایت</label> <label><input type="checkbox" name="telegram_enabled" value="1"> تلگرام</label></td></tr>
        </tbody></table>
        </div>
        <?php submit_button('ذخیرهٔ اطلاعیه'); ?>
    </form>
    </div>
    <div class="pc-notification-settings__column pc-notification-settings__column--left">
    <div class="pc-notification-settings pc-notification-settings--telegram"><div class="pc-admin__card"><h2>اتصال تلگرام</h2><p>ابتدا تنظیمات بات را ذخیره کنید. Webhook فقط با نشانی عمومی HTTPS ثبت می‌شود؛ در localhost امکان ثبت آن وجود ندارد.</p><p class="description">وضعیت Webhook: <?php echo get_option('popcorn_notification_webhook_secret') ? 'ثبت‌شده' : 'ثبت‌نشده'; ?></p><div class="pc-notification-settings__actions">
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="popcorn_notification_telegram_check"><?php wp_nonce_field('popcorn_notification_telegram_check'); ?><button type="submit" class="button">بررسی بات</button></form>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="popcorn_notification_telegram_webhook"><?php wp_nonce_field('popcorn_notification_telegram_webhook'); ?><button type="submit" class="button">ثبت Webhook تلگرام</button></form>
    </div></div></div>
    <div class="pc-notification-settings pc-notification-settings--status">
        <div class="pc-admin__card">
            <h2>وضعیت ارسال</h2>
            <p><?php echo esc_html(number_format_i18n($failed)); ?> پیام تلگرام پس از چند تلاش ارسال نشده است.</p>
            <?php if ($recent) : ?>
                <table class="widefat striped"><thead><tr><th>عنوان</th><th>مخاطب</th><th>زمان</th><th>وضعیت</th></tr></thead><tbody>
                    <?php foreach ($recent as $campaign) : ?>
                        <tr><td><?php echo esc_html($campaign->title); ?></td><td><?php echo esc_html(['all' => 'همهٔ کاربران', 'user' => 'یک کاربر', 'favorites' => 'علاقه‌مندان اثر'][$campaign->audience] ?? $campaign->audience); ?></td><td><?php echo esc_html(mysql2date('j F Y، H:i', $campaign->created_at)); ?></td><td><?php echo $campaign->completed_at ? 'پایان‌یافته' : 'در صف'; ?></td></tr>
                    <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
        <?php if ($failed) : ?>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="pc-notification-settings__retry">
                <input type="hidden" name="action" value="popcorn_notification_retry_telegram">
                <?php wp_nonce_field('popcorn_notification_retry_telegram'); ?>
                <p class="submit"><button type="submit" class="button">تلاش دوباره برای ارسال</button></p>
            </form>
        <?php endif; ?>
    </div>
    </div>
    <?php
});

add_action('rest_api_init', function () {
    register_rest_route('popcorn/v1', '/telegram-webhook', ['methods' => WP_REST_Server::CREATABLE, 'callback' => function ($request) {
        $expected = (string) get_option('popcorn_notification_webhook_secret', '');
        $provided = (string) $request->get_header('x-telegram-bot-api-secret-token');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return new WP_Error('telegram_forbidden', 'دسترسی مجاز نیست.', ['status' => 403]);
        }
        if (!popcorn_notification_options()['telegram_enabled']) {
            return rest_ensure_response(['ok' => true]);
        }
        $update = $request->get_json_params();
        $message = is_array($update) ? ($update['message'] ?? []) : [];
        $chat = is_array($message) ? ($message['chat'] ?? []) : [];
        $text = is_array($message) ? trim((string) ($message['text'] ?? '')) : '';
        if (($chat['type'] ?? '') !== 'private' || !preg_match('/^\/start ([a-f0-9]{32})$/', $text, $match)) {
            return rest_ensure_response(['ok' => true]);
        }
        $chat_id = (string) ($chat['id'] ?? '');
        $user_id = absint(get_transient('pc_telegram_link_' . $match[1]));
        if (!$user_id || !$chat_id || !get_userdata($user_id)) {
            return rest_ensure_response(['ok' => true]);
        }
        delete_transient('pc_telegram_link_' . $match[1]);
        $others = get_users(['meta_key' => 'popcorn_telegram_chat_id', 'meta_value' => $chat_id, 'fields' => 'ids']);
        foreach ($others as $other_id) {
            if ((int) $other_id !== $user_id) {
                delete_user_meta($other_id, 'popcorn_telegram_chat_id');
            }
        }
        update_user_meta($user_id, 'popcorn_telegram_chat_id', $chat_id);
        popcorn_notification_telegram_request('sendMessage', ['chat_id' => $chat_id, 'text' => 'حساب تلگرام شما به پاپکورن متصل شد. اعلان‌های فعال را اینجا هم دریافت می‌کنید.']);
        return rest_ensure_response(['ok' => true]);
    }, 'permission_callback' => '__return_true']);
});

add_action('admin_post_popcorn_notification_user', function () {
    if (!is_user_logged_in()) {
        wp_die('برای مدیریت اعلان‌ها وارد شوید.', '', ['response' => 401]);
    }
    check_admin_referer('popcorn_notification_user');
    $user_id = get_current_user_id();
    $operation = sanitize_key(wp_unslash($_POST['operation'] ?? ''));
    global $wpdb;
    $table = popcorn_core_table('notification');
    if ($operation === 'read') {
        $wpdb->update($table, ['read_at' => current_time('mysql')], ['id' => absint($_POST['notification_id'] ?? 0), 'user_id' => $user_id, 'show_in_site' => 1, 'read_at' => null]);
    } elseif ($operation === 'read_all') {
        $wpdb->query($wpdb->prepare("UPDATE $table SET read_at=%s WHERE user_id=%d AND show_in_site=1 AND read_at IS NULL", current_time('mysql'), $user_id));
    } elseif ($operation === 'clear') {
        $wpdb->query($wpdb->prepare("UPDATE $table SET show_in_site=0 WHERE user_id=%d AND show_in_site=1", $user_id));
    } elseif ($operation === 'preferences') {
        $options = popcorn_notification_options();
        if ($options['site_enabled']) {
            update_user_meta($user_id, 'popcorn_notification_site_off', isset($_POST['site_enabled']) ? 0 : 1);
        }
        if ($options['telegram_enabled']) {
            update_user_meta($user_id, 'popcorn_notification_telegram_off', isset($_POST['telegram_enabled']) ? 0 : 1);
        }
    } elseif ($operation === 'unlink') {
        delete_user_meta($user_id, 'popcorn_telegram_chat_id');
        $wpdb->query($wpdb->prepare("UPDATE $table SET telegram_status='skipped' WHERE user_id=%d AND telegram_status='pending'", $user_id));
    } elseif ($operation === 'link') {
        $options = popcorn_notification_options();
        if ($options['telegram_enabled'] && get_option('popcorn_notification_webhook_secret') && preg_match('/^[A-Za-z0-9_]{5,32}$/', $options['bot_username']) && $options['bot_token']) {
            $token = bin2hex(random_bytes(16));
            set_transient('pc_telegram_link_' . $token, $user_id, 15 * MINUTE_IN_SECONDS);
            wp_redirect('https://t.me/' . rawurlencode($options['bot_username']) . '?start=' . $token, 302, 'Popcorn');
            exit;
        }
    }
    wp_safe_redirect(popcorn_notification_url());
    exit;
});

function popcorn_notification_user_form($operation, $label, $class = '', $id = 0) {
    return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="popcorn_notification_user"><input type="hidden" name="operation" value="' . esc_attr($operation) . '"><input type="hidden" name="notification_id" value="' . absint($id) . '">' . wp_nonce_field('popcorn_notification_user', '_wpnonce', true, false) . '<button type="submit" class="' . esc_attr($class) . '">' . esc_html($label) . '</button></form>';
}

function popcorn_notification_bell() {
    if (!is_user_logged_in() || !popcorn_notification_options()['site_enabled']) {
        return '';
    }
    global $wpdb;
    $table = popcorn_core_table('notification');
    $user_id = get_current_user_id();
    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d AND show_in_site=1 AND read_at IS NULL", $user_id));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT title,message,url,read_at FROM $table WHERE user_id=%d AND show_in_site=1 ORDER BY id DESC LIMIT 5", $user_id));
    $icon = function_exists('popcorn_icon') ? popcorn_icon('bell') : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>';
    $html = '<details class="pc-notification-bell"><summary aria-label="اعلان‌ها، ' . esc_attr($count) . ' خوانده‌نشده">' . $icon . ($count ? '<span class="pc-notification-bell__count">' . esc_html($count > 99 ? '۹۹+' : number_format_i18n($count)) . '</span>' : '') . '</summary><div class="pc-notification-bell__panel"><div class="pc-notification-bell__head"><strong>اعلان‌ها</strong><a href="' . esc_url(popcorn_notification_url()) . '">نمایش همه</a></div>';
    foreach ($rows as $row) {
        $html .= '<a class="pc-notification-bell__item' . ($row->read_at ? '' : ' is-unread') . '" href="' . esc_url($row->url ?: popcorn_notification_url()) . '"><strong>' . esc_html($row->title) . '</strong><span>' . esc_html(wp_trim_words($row->message, 12)) . '</span></a>';
    }
    if (!$rows) {
        $html .= '<p class="pc-notification-bell__empty">اعلان تازه‌ای ندارید.</p>';
    }
    return $html . '</div></details>';
}

add_shortcode('popcorn_notification_bell', 'popcorn_notification_bell');

add_action('admin_bar_menu', function ($admin_bar) {
    if (!is_user_logged_in() || !popcorn_notification_options()['site_enabled']) {
        return;
    }
    global $wpdb;
    $table = popcorn_core_table('notification');
    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d AND show_in_site=1 AND read_at IS NULL", get_current_user_id()));
    $admin_bar->add_node(['id' => 'popcorn-notifications', 'parent' => 'top-secondary', 'title' => 'اعلان‌ها' . ($count ? ' (' . number_format_i18n($count) . ')' : ''), 'href' => popcorn_notification_url()]);
}, 70);

function popcorn_notification_page_content() {
    if (!is_user_logged_in()) {
        $login = popcorn_core_public_login_url(popcorn_notification_url());
        $icon = function_exists('popcorn_icon') ? popcorn_icon('bell') : '';
        return '<div class="pc-notification-page pc-notification-page--guest"><span class="pc-notification-page__guest-icon">' . $icon . '</span><h1>اعلان‌ها</h1><p>' . ($login ? 'برای دیدن اعلان‌های خود وارد شوید.' : 'اعلان‌های شخصی در حال حاضر در دسترس نیستند.') . '</p>' . ($login ? '<a class="pc-button" href="' . esc_url($login) . '">ورود به حساب</a>' : '') . '</div>';
    }
    global $wpdb;
    $table = popcorn_core_table('notification');
    $user_id = get_current_user_id();
    $page = max(1, absint($_GET['notification_page'] ?? 1));
    $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d AND show_in_site=1", $user_id));
    $unread = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d AND show_in_site=1 AND read_at IS NULL", $user_id));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id,title,message,url,read_at,created_at FROM $table WHERE user_id=%d AND show_in_site=1 ORDER BY id DESC LIMIT 20 OFFSET %d", $user_id, ($page - 1) * 20));
    $options = popcorn_notification_options();
    ob_start();
    ?>
    <div class="pc-notification-page">
        <div class="pc-notification-page__head"><div><h1>اعلان‌ها</h1><p><?php echo esc_html(number_format_i18n($unread)); ?> اعلان خوانده‌نشده</p></div><div class="pc-notification-page__actions"><?php if ($unread) { echo popcorn_notification_user_form('read_all', 'خواندن همه'); } ?><?php if ($total) { echo popcorn_notification_user_form('clear', 'پاک کردن فهرست', 'is-danger'); } ?></div></div>
        <div class="pc-notification-page__list">
            <?php foreach ($rows as $row) : ?><article class="pc-notification-page__item<?php echo $row->read_at ? '' : ' is-unread'; ?>"><div><time><?php echo esc_html(mysql2date('j F Y، H:i', $row->created_at)); ?></time><h2><?php echo esc_html($row->title); ?></h2><p><?php echo nl2br(esc_html($row->message)); ?></p><?php if ($row->url) : ?><a href="<?php echo esc_url($row->url); ?>">نمایش جزئیات</a><?php endif; ?></div><?php if (!$row->read_at) { echo popcorn_notification_user_form('read', 'خوانده شد', '', $row->id); } ?></article><?php endforeach; ?>
            <?php if (!$rows) : ?><p class="pc-notification-page__empty">هنوز اعلانی برای شما ثبت نشده است.</p><?php endif; ?>
        </div>
        <?php if ($total > 20) : ?><nav class="pc-notification-page__pages" aria-label="صفحه‌های اعلان‌ها"><?php echo paginate_links(['base' => add_query_arg('notification_page', '%#%', popcorn_notification_url()), 'format' => '', 'current' => $page, 'total' => (int) ceil($total / 20)]); ?></nav><?php endif; ?>
        <section class="pc-notification-page__settings"><h2>تنظیمات دریافت</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="popcorn_notification_user"><input type="hidden" name="operation" value="preferences"><?php wp_nonce_field('popcorn_notification_user'); ?><label><input type="checkbox" name="site_enabled" value="1" <?php checked(!get_user_meta($user_id, 'popcorn_notification_site_off', true)); ?> <?php disabled(!$options['site_enabled']); ?>> داخل سایت</label><label><input type="checkbox" name="telegram_enabled" value="1" <?php checked(!get_user_meta($user_id, 'popcorn_notification_telegram_off', true)); ?> <?php disabled(!$options['telegram_enabled']); ?>> تلگرام</label><button type="submit">ذخیرهٔ تنظیمات</button></form>
            <?php if (($options['telegram_enabled'] && $options['bot_username']) || get_user_meta($user_id, 'popcorn_telegram_chat_id', true)) : ?><div class="pc-notification-page__telegram"><?php if (get_user_meta($user_id, 'popcorn_telegram_chat_id', true)) : ?><p>تلگرام شما متصل است.</p><?php echo popcorn_notification_user_form('unlink', 'قطع اتصال تلگرام'); ?><?php elseif ($options['bot_token'] && get_option('popcorn_notification_webhook_secret')) : ?><p>برای دریافت اعلان در تلگرام، حساب خود را به بات پاپکورن وصل کنید.</p><?php echo popcorn_notification_user_form('link', 'اتصال تلگرام'); ?><?php else : ?><p>اتصال تلگرام پس از ثبت Webhook توسط مدیر سایت فعال می‌شود.</p><?php endif; ?></div><?php endif; ?>
        </section>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('popcorn_notifications', 'popcorn_notification_page_content');

add_filter('template_include', function ($template) {
    return get_query_var('popcorn_notifications') ? dirname(__DIR__) . '/templates/notifications.php' : $template;
}, 30);

add_filter('document_title_parts', function ($parts) {
    if (get_query_var('popcorn_notifications')) {
        $parts['title'] = 'اعلان‌ها';
    }
    return $parts;
});

add_action('template_redirect', function () {
    if (get_query_var('popcorn_notifications')) {
        nocache_headers();
    }
});

add_action('wp_enqueue_scripts', function () {
    if (function_exists('popcorn_auth_page_kind') && popcorn_auth_enabled() && popcorn_auth_page_kind()) {
        return;
    }
    if (is_404() || is_singular('popcorn_playback') || (!get_query_var('popcorn_notifications') && (!is_user_logged_in() || !popcorn_notification_options()['site_enabled']))) {
        return;
    }
    $base = dirname(__DIR__);
    $file = $base . '/assets/css/notifications.css';
    wp_enqueue_style('popcorn-notifications', plugin_dir_url($base . '/popcorn-core.php') . 'assets/css/notifications.css', [], filemtime($file));
});

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_popcorn-settings' || sanitize_key(wp_unslash($_GET['tab'] ?? '')) !== 'notifications') {
        return;
    }
    $base = dirname(__DIR__);
    $file = $base . '/assets/css/notification-settings.css';
    wp_enqueue_style('popcorn-notification-settings', plugin_dir_url($base . '/popcorn-core.php') . 'assets/css/notification-settings.css', ['popcorn-admin'], filemtime($file));
    $script = $base . '/assets/js/notification-settings.js';
    wp_enqueue_script('popcorn-notification-settings', plugin_dir_url($base . '/popcorn-core.php') . 'assets/js/notification-settings.js', [], filemtime($script), true);
    wp_add_inline_script('popcorn-notification-settings', 'window.popcornNotificationTarget=' . wp_json_encode(['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('popcorn_notification_target_search')]) . ';', 'before');
});
