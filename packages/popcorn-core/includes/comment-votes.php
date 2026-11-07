<?php
defined('ABSPATH') || exit;

function popcorn_core_install_comment_votes() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = popcorn_core_table('comment_vote');
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        comment_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        voter_key char(64) NOT NULL,
        vote tinyint(4) NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY comment_voter (comment_id,voter_key),
        KEY user_id (user_id)
    ) $charset;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
        update_option('popcorn_comment_votes_db_version', '1', false);
    }
}

add_action('init', function () {
    if (get_option('popcorn_comment_votes_db_version') !== '1') {
        popcorn_core_install_comment_votes();
    }
}, 2);

function popcorn_core_comment_vote_post($post_id) {
    $post = get_post(absint($post_id));
    return $post && is_post_publicly_viewable($post) && post_type_supports($post->post_type, 'comments') && !post_password_required($post) ? $post : null;
}

function popcorn_core_comment_voter_key($create = false) {
    if (is_user_logged_in()) {
        return hash_hmac('sha256', 'user:' . get_current_user_id(), wp_salt('auth'));
    }
    $name = 'popcorn_comment_voter_' . COOKIEHASH;
    $cookie = isset($_COOKIE[$name]) && is_string($_COOKIE[$name]) ? wp_unslash($_COOKIE[$name]) : '';
    if (preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/D', $cookie, $parts) && hash_equals(hash_hmac('sha256', $parts[1], wp_salt('auth')), $parts[2])) {
        return hash_hmac('sha256', 'guest:' . $parts[1], wp_salt('auth'));
    }
    if (!$create || headers_sent()) {
        return new WP_Error('voter_cookie', 'برای ثبت رأی، کوکی مرورگر باید فعال باشد.');
    }
    $id = bin2hex(random_bytes(16));
    $value = $id . '.' . hash_hmac('sha256', $id, wp_salt('auth'));
    if (!setcookie($name, $value, ['expires' => time() + YEAR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'])) {
        return new WP_Error('voter_cookie', 'شناسهٔ رأی‌دهنده آماده نشد. دوباره تلاش کنید.');
    }
    $_COOKIE[$name] = $value;
    return hash_hmac('sha256', 'guest:' . $id, wp_salt('auth'));
}

function popcorn_core_comment_vote_summaries($ids, $voter_key = '') {
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    if (!$ids) return [];
    global $wpdb;
    $table = popcorn_core_table('comment_vote');
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $sql = "SELECT comment_id,SUM(vote=1) AS likes,SUM(vote=-1) AS dislikes,MAX(CASE WHEN voter_key=%s THEN vote ELSE NULL END) AS selected FROM $table WHERE comment_id IN ($placeholders) GROUP BY comment_id";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $voter_key, ...$ids));
    $results = array_fill_keys($ids, ['likes' => 0, 'dislikes' => 0, 'selected' => 0]);
    foreach ($rows as $row) {
        $results[(int) $row->comment_id] = ['likes' => (int) $row->likes, 'dislikes' => (int) $row->dislikes, 'selected' => (int) $row->selected];
    }
    return $results;
}

function popcorn_core_comment_vote_counts($comment_id = 0, $comments = []) {
    static $counts = [];
    if ($comments) {
        $ids = [];
        foreach ($comments as $comment) {
            if ((string) $comment->comment_approved === '1' && in_array($comment->comment_type, ['', 'comment'], true)) $ids[] = $comment->comment_ID;
        }
        $counts = array_replace($counts, popcorn_core_comment_vote_summaries($ids));
    }
    return $counts[absint($comment_id)] ?? ['likes' => 0, 'dislikes' => 0, 'selected' => 0];
}

function popcorn_core_comment_votes_state() {
    nocache_headers();
    $post_id = isset($_REQUEST['post_id']) && is_scalar($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : 0;
    if (!popcorn_core_comment_vote_post($post_id)) wp_send_json_error(['message' => 'این صفحه در دسترس نیست.'], 404);
    $input = isset($_REQUEST['comment_ids']) && is_string($_REQUEST['comment_ids']) ? wp_unslash($_REQUEST['comment_ids']) : '';
    if (strlen($input) > 2200) wp_send_json_error(['message' => 'تعداد دیدگاه‌ها معتبر نیست.'], 400);
    $ids = array_values(array_unique(array_filter(array_map('absint', explode(',', $input)))));
    if (!$ids || count($ids) > 100) wp_send_json_error(['message' => 'تعداد دیدگاه‌ها معتبر نیست.'], 400);
    $comments = get_comments(['post_id' => $post_id, 'include' => $ids, 'status' => 'approve', 'type' => 'comment', 'number' => 100]);
    $ids = array_map('intval', wp_list_pluck($comments, 'comment_ID'));
    $voter = popcorn_core_comment_voter_key(true);
    if (is_wp_error($voter)) wp_send_json_error(['message' => $voter->get_error_message()], 400);
    $nonce = wp_create_nonce('popcorn_comment_vote_' . $post_id);
    $token = hash_hmac('sha256', $voter . '|' . $post_id . '|' . $nonce, wp_salt('nonce'));
    wp_send_json_success(['votes' => popcorn_core_comment_vote_summaries($ids, $voter), 'nonce' => $nonce, 'token' => $token]);
}
add_action('wp_ajax_popcorn_comment_votes_state', 'popcorn_core_comment_votes_state');
add_action('wp_ajax_nopriv_popcorn_comment_votes_state', 'popcorn_core_comment_votes_state');

function popcorn_core_save_comment_vote($comment_id, $voter_key, $vote, $user_id = 0) {
    global $wpdb;
    $table = popcorn_core_table('comment_vote');
    if (!in_array($vote, [-1, 0, 1], true) || !preg_match('/^[a-f0-9]{64}$/D', $voter_key)) return new WP_Error('invalid_vote', 'رأی معتبر نیست.');
    if ($vote === 0) {
        $saved = $wpdb->delete($table, ['comment_id' => absint($comment_id), 'voter_key' => $voter_key], ['%d', '%s']);
    } else {
        $saved = $wpdb->query($wpdb->prepare("INSERT INTO $table (comment_id,user_id,voter_key,vote,updated_at) VALUES (%d,%d,%s,%d,%s) ON DUPLICATE KEY UPDATE vote=VALUES(vote),updated_at=VALUES(updated_at)", absint($comment_id), absint($user_id), $voter_key, $vote, current_time('mysql')));
    }
    return $saved === false ? new WP_Error('save_vote', 'ثبت رأی انجام نشد. دوباره تلاش کنید.') : popcorn_core_comment_vote_summaries([$comment_id], $voter_key)[absint($comment_id)];
}

function popcorn_core_comment_vote_submit() {
    nocache_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_send_json_error(['message' => 'درخواست معتبر نیست.'], 405);
    $post_id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $comment_id = isset($_POST['comment_id']) && is_scalar($_POST['comment_id']) ? absint($_POST['comment_id']) : 0;
    $comment = get_comment($comment_id);
    if (!popcorn_core_comment_vote_post($post_id) || !$comment || (int) $comment->comment_post_ID !== $post_id || (string) $comment->comment_approved !== '1' || !in_array($comment->comment_type, ['', 'comment'], true)) wp_send_json_error(['message' => 'دیدگاه در دسترس نیست.'], 404);
    $nonce = isset($_POST['nonce']) && is_string($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    $token = isset($_POST['token']) && is_string($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
    $voter = popcorn_core_comment_voter_key();
    if (is_wp_error($voter) || !wp_verify_nonce($nonce, 'popcorn_comment_vote_' . $post_id) || !hash_equals(hash_hmac('sha256', $voter . '|' . $post_id . '|' . $nonce, wp_salt('nonce')), $token)) wp_send_json_error(['message' => 'اعتبار درخواست تمام شده است. صفحه را تازه کنید.'], 403);
    $raw = isset($_POST['vote']) && is_scalar($_POST['vote']) ? (string) $_POST['vote'] : '';
    if (!preg_match('/^-?[01]$/D', $raw)) wp_send_json_error(['message' => 'رأی معتبر نیست.'], 400);
    $rate_key = 'pc_comment_vote_' . substr($voter, 0, 40);
    $rate = get_transient($rate_key);
    $now = time();
    if (!is_array($rate) || ($rate['until'] ?? 0) <= $now) $rate = ['count' => 0, 'until' => $now + MINUTE_IN_SECONDS];
    if ($rate['count'] >= 30) wp_send_json_error(['message' => 'کمی صبر کنید و دوباره تلاش کنید.'], 429);
    $rate['count']++;
    set_transient($rate_key, $rate, max(1, $rate['until'] - $now));
    $result = popcorn_core_save_comment_vote($comment_id, $voter, (int) $raw, get_current_user_id());
    if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()], 500);
    wp_send_json_success(['comment_id' => $comment_id, 'vote' => $result]);
}
add_action('wp_ajax_popcorn_comment_vote', 'popcorn_core_comment_vote_submit');
add_action('wp_ajax_nopriv_popcorn_comment_vote', 'popcorn_core_comment_vote_submit');

add_action('delete_comment', function ($comment_id) {
    global $wpdb;
    $wpdb->delete(popcorn_core_table('comment_vote'), ['comment_id' => absint($comment_id)], ['%d']);
});
add_action('delete_user', function ($user_id) {
    global $wpdb;
    $wpdb->delete(popcorn_core_table('comment_vote'), ['user_id' => absint($user_id)], ['%d']);
});

add_action('wp_enqueue_scripts', function () {
    if (!is_singular() || is_singular('popcorn_playback') || !get_comments_number() || !popcorn_core_comment_vote_post(get_queried_object_id())) return;
    $path = dirname(__DIR__) . '/assets/js/comment-votes.js';
    wp_enqueue_script('popcorn-comment-votes', plugins_url('assets/js/comment-votes.js', dirname(__DIR__) . '/popcorn-core.php'), [], filemtime($path), true);
}, 21);
