<?php
defined('ABSPATH') || exit;

function popcorn_core_guest_rating_enabled() {
    return popcorn_core_engagement_options()['rating_enabled'] && (!function_exists('popcorn_auth_enabled') || !popcorn_auth_enabled());
}

function popcorn_core_install_guest_ratings() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = popcorn_core_table('guest_rating');
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        voter_key char(64) NOT NULL,
        ip_hash char(64) NOT NULL,
        rating tinyint(3) unsigned NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY post_voter (post_id,voter_key),
        KEY ip_updated (ip_hash,updated_at)
    ) $charset;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
        update_option('popcorn_guest_ratings_db_version', '1', false);
    }
}

add_action('init', function () {
    if (get_option('popcorn_guest_ratings_db_version') !== '1') {
        popcorn_core_install_guest_ratings();
    }
}, 2);

function popcorn_core_guest_rating_voter($create = false) {
    $names = ['popcorn_rating_voter_' . COOKIEHASH, 'popcorn_rating_session_' . COOKIEHASH];
    $id = '';
    foreach ($names as $name) {
        $cookie = isset($_COOKIE[$name]) && is_string($_COOKIE[$name]) ? wp_unslash($_COOKIE[$name]) : '';
        if (preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/D', $cookie, $parts) && hash_equals(hash_hmac('sha256', 'rating:' . $parts[1], wp_salt('auth')), $parts[2])) {
            $id = $parts[1];
            break;
        }
    }
    if (!$id && !$create) {
        return new WP_Error('rating_session_expired', 'برای ثبت امتیاز، کوکی مرورگر را فعال کنید و دوباره تلاش کنید.', ['status' => 403]);
    }
    if ($create) {
        $id = $id ?: bin2hex(random_bytes(16));
        $value = $id . '.' . hash_hmac('sha256', 'rating:' . $id, wp_salt('auth'));
        foreach ($names as $index => $name) {
            if (isset($_COOKIE[$name]) && $_COOKIE[$name] === $value) {
                continue;
            }
            if (headers_sent() || !setcookie($name, $value, ['expires' => $index === 0 ? time() + YEAR_IN_SECONDS : 0, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'])) {
                return new WP_Error('rating_cookie', 'شناسهٔ امتیازدهی آماده نشد. دوباره تلاش کنید.', ['status' => 400]);
            }
            $_COOKIE[$name] = $value;
        }
    }
    return hash_hmac('sha256', 'rating-guest:' . $id, wp_salt('auth'));
}

function popcorn_core_guest_rating_token($post_id, $voter, $nonce) {
    return hash_hmac('sha256', absint($post_id) . '|' . $voter . '|' . $nonce, wp_salt('nonce'));
}

function popcorn_core_guest_rating_permission($request) {
    if (!popcorn_core_guest_rating_enabled() || !preg_match('~/rating$~', $request->get_route())) {
        return new WP_Error('login_required', 'برای این کار وارد حساب کاربری شوید.', ['status' => 401]);
    }
    $voter = popcorn_core_guest_rating_voter();
    if (is_wp_error($voter)) {
        return $voter;
    }
    $nonce = $request->get_header('X-PC-Rating-Nonce');
    $token = $request->get_header('X-PC-Rating-Token');
    if (!wp_verify_nonce($nonce, 'popcorn_guest_rating_' . absint($request['post_id'])) || !hash_equals(popcorn_core_guest_rating_token($request['post_id'], $voter, $nonce), $token)) {
        return new WP_Error('rating_session_expired', 'اعتبار درخواست تمام شده است. دوباره تلاش کنید.', ['status' => 403]);
    }
    return true;
}

function popcorn_core_guest_rating_ip_hash() {
    $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ip = filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : 'unknown';
    return hash_hmac('sha256', 'rating-ip:' . $ip, wp_salt('auth'));
}

function popcorn_core_save_guest_rating($post_id, $rating, $delete = false) {
    $voter = popcorn_core_guest_rating_voter();
    if (is_wp_error($voter)) {
        return $voter;
    }
    $ip = popcorn_core_guest_rating_ip_hash();
    foreach ([$voter => 20, $ip => 60] as $hash => $limit) {
        $key = 'pc_guest_rating_' . $hash;
        $rate = get_transient($key);
        if (!is_array($rate) || ($rate['until'] ?? 0) <= time()) {
            $rate = ['count' => 0, 'until' => time() + MINUTE_IN_SECONDS];
        }
        if ($rate['count'] >= $limit) {
            return new WP_Error('rating_rate_limit', 'تعداد درخواست‌های امتیازدهی زیاد است. کمی بعد دوباره تلاش کنید.', ['status' => 429]);
        }
        $rate['count']++;
        if (!set_transient($key, $rate, max(1, $rate['until'] - time()))) {
            return new WP_Error('rating_rate_save', 'درخواست آماده نشد. دوباره تلاش کنید.', ['status' => 500]);
        }
    }
    global $wpdb;
    $table = popcorn_core_table('guest_rating');
    $now = current_time('mysql');
    $saved = $delete ? $wpdb->delete($table, ['post_id' => $post_id, 'voter_key' => $voter]) : $wpdb->query($wpdb->prepare("INSERT INTO $table (post_id,voter_key,ip_hash,rating,created_at,updated_at) VALUES (%d,%s,%s,%d,%s,%s) ON DUPLICATE KEY UPDATE rating=VALUES(rating),ip_hash=VALUES(ip_hash),updated_at=VALUES(updated_at)", $post_id, $voter, $ip, $rating, $now, $now));
    return $saved === false ? new WP_Error('rating_save_failed', 'ذخیرهٔ امتیاز انجام نشد.', ['status' => 500]) : true;
}

function popcorn_core_rating_statistics($post_id) {
    global $wpdb;
    $users = popcorn_core_table('user_rating');
    $guests = popcorn_core_table('guest_rating');
    return $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS total,AVG(rating) AS average FROM (SELECT rating FROM $users WHERE post_id=%d UNION ALL SELECT rating FROM $guests WHERE post_id=%d) votes", $post_id, $post_id));
}
