<?php
defined('ABSPATH') || exit;

function popcorn_subscription_table($name) {
    global $wpdb;
    $names = ['plan' => 'popcorn_subscription_plans', 'subscription' => 'popcorn_user_subscriptions', 'payment' => 'popcorn_subscription_payments'];
    return $wpdb->prefix . $names[$name];
}

function popcorn_subscription_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $plans = popcorn_subscription_table('plan');
    $subscriptions = popcorn_subscription_table('subscription');
    $payments = popcorn_subscription_table('payment');
    dbDelta("CREATE TABLE $plans (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        duration_days int(10) unsigned NOT NULL,
        price_toman bigint(20) unsigned NOT NULL,
        features longtext NOT NULL,
        color char(7) NOT NULL DEFAULT '#6C52EE',
        image_id bigint(20) unsigned NOT NULL DEFAULT 0,
        description text NOT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        is_featured tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY is_active (is_active)
    ) $charset;");
    dbDelta("CREATE TABLE $subscriptions (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        plan_id bigint(20) unsigned NOT NULL,
        payment_id bigint(20) unsigned DEFAULT NULL,
        starts_at datetime NOT NULL,
        ends_at datetime NOT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        admin_note text NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY payment_id (payment_id),
        KEY user_active (user_id,is_active,ends_at)
    ) $charset;");
    dbDelta("CREATE TABLE $payments (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        plan_id bigint(20) unsigned NOT NULL,
        amount_rial bigint(20) unsigned NOT NULL,
        duration_days int(10) unsigned NOT NULL,
        merchant_id varchar(80) NOT NULL,
        authority varchar(80) NOT NULL DEFAULT '',
        ref_id varchar(80) NOT NULL DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'pending',
        token_hash char(64) NOT NULL,
        created_at datetime NOT NULL,
        verified_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY authority (authority),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset;");
    update_option('popcorn_subscription_db_version', '3', false);
}

function popcorn_subscription_upgrade() {
    if (get_option('popcorn_subscription_db_version') !== '3') {
        popcorn_subscription_install();
    }
}

function popcorn_subscription_options() {
    $defaults = ['enabled' => 0, 'access_mode' => 'free', 'merchant_id' => '', 'page_id' => 0];
    $saved = get_option('popcorn_subscription_options', []);
    return is_array($saved) ? array_merge($defaults, $saved) : $defaults;
}

function popcorn_subscription_enabled() {
    return function_exists('popcorn_auth_enabled') && popcorn_auth_enabled() && (bool) popcorn_subscription_options()['enabled'];
}

function popcorn_subscription_disable() {
    $options = popcorn_subscription_options();
    if ($options['enabled']) {
        $options['enabled'] = 0;
        update_option('popcorn_subscription_options', $options, false);
    }
}

add_action('update_option_popcorn_auth_options', function ($old, $new) {
    if (empty($new['enabled'])) {
        popcorn_subscription_disable();
    }
}, 10, 2);

add_action('deactivated_plugin', function ($plugin) {
    if ($plugin === 'popcorn-auth/popcorn-auth.php') {
        popcorn_subscription_disable();
    }
});

add_action('init', function () {
    if (!function_exists('popcorn_auth_enabled') || !popcorn_auth_enabled()) {
        popcorn_subscription_disable();
    }
}, 1);

function popcorn_subscription_url() {
    $id = absint(popcorn_subscription_options()['page_id']);
    return $id && get_post_status($id) === 'publish' ? get_permalink($id) : '';
}

function popcorn_subscription_active($user_id = 0) {
    global $wpdb;
    $user_id = absint($user_id ?: get_current_user_id());
    if (!$user_id) {
        return null;
    }
    $table = popcorn_subscription_table('subscription');
    return $wpdb->get_row($wpdb->prepare("SELECT s.*, p.name AS plan_name, p.color AS plan_color FROM $table s LEFT JOIN " . popcorn_subscription_table('plan') . " p ON p.id = s.plan_id WHERE s.user_id = %d AND s.is_active = 1 AND s.starts_at <= UTC_TIMESTAMP() AND s.ends_at > UTC_TIMESTAMP() ORDER BY s.ends_at DESC LIMIT 1", $user_id));
}

function popcorn_subscription_grant($user_id, $plan_id, $duration_days, $payment_id = null, $note = '') {
    global $wpdb;
    $table = popcorn_subscription_table('subscription');
    $latest = $wpdb->get_var($wpdb->prepare("SELECT MAX(ends_at) FROM $table WHERE user_id = %d AND is_active = 1 AND ends_at > UTC_TIMESTAMP()", absint($user_id)));
    $start = $latest ? strtotime($latest . ' UTC') : time();
    $end = gmdate('Y-m-d H:i:s', $start + (absint($duration_days) * DAY_IN_SECONDS));
    return $wpdb->insert(popcorn_subscription_table('subscription'), ['user_id' => absint($user_id), 'plan_id' => absint($plan_id), 'payment_id' => $payment_id ?: null, 'starts_at' => gmdate('Y-m-d H:i:s', $start), 'ends_at' => $end, 'is_active' => 1, 'admin_note' => $note], ['%d', '%d', '%d', '%s', '%s', '%d', '%s']) ? $end : false;
}

function popcorn_subscription_requires_download() {
    $options = popcorn_subscription_options();
    return popcorn_subscription_enabled() && $options['access_mode'] === 'downloads';
}

add_filter('popcorn_download_links_visible', function ($visible) {
    return $visible && (!popcorn_subscription_requires_download() || (bool) popcorn_subscription_active());
});

add_filter('popcorn_download_access_message', function ($message) {
    if (!popcorn_subscription_requires_download()) {
        return $message;
    }
    $url = is_user_logged_in() ? popcorn_subscription_url() : popcorn_auth_login_url(get_permalink());
    $label = is_user_logged_in() ? 'دیدن پلن‌های اشتراک' : 'ورود به حساب کاربری';
    return '<p class="pc-public-downloads__access">برای دریافت لینک‌ها اشتراک فعال لازم است. <a class="pc-button" href="' . esc_url($url ?: home_url('/')) . '">' . esc_html($label) . '</a></p>';
});

add_action('template_redirect', function () {
    if (popcorn_subscription_requires_download() && is_singular(['popcorn_movie', 'popcorn_series'])) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
    }
}, 1);
