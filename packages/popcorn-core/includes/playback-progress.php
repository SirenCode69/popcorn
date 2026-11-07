<?php
defined('ABSPATH') || exit;

function popcorn_core_playback_progress($post_id) {
    if (!is_user_logged_in()) {
        return null;
    }
    global $wpdb;
    $table = popcorn_core_table('watch_progress');
    return $wpdb->get_row($wpdb->prepare("SELECT position_seconds,duration_seconds FROM $table WHERE user_id=%d AND playback_post_id=%d", get_current_user_id(), absint($post_id)));
}

function popcorn_core_save_playback_progress($input) {
    if (!is_user_logged_in() || !is_array($input)) {
        return new WP_Error('login', 'ورود به حساب کاربری لازم است.');
    }
    foreach (['playback', 'position', 'duration', 'sequence'] as $key) {
        if (!isset($input[$key]) || !is_scalar($input[$key]) || !is_numeric($input[$key])) {
            return new WP_Error('invalid', 'زمان تماشا معتبر نیست.');
        }
    }
    $row = popcorn_core_get_playback(absint($input['playback']));
    $access = popcorn_core_playback_access($row);
    if (is_wp_error($access)) {
        return $access;
    }
    $position = (float) $input['position'];
    $duration = (float) $input['duration'];
    $sequence = (float) $input['sequence'];
    if (!is_finite($position) || !is_finite($duration) || !is_finite($sequence) || $duration <= 0 || $duration > 7 * DAY_IN_SECONDS || $position < 0 || $position > $duration || $sequence < 1 || $sequence > 999999999999999) {
        return new WP_Error('invalid', 'زمان تماشا معتبر نیست.');
    }
    global $wpdb;
    $table = popcorn_core_table('watch_progress');
    $saved = $wpdb->query($wpdb->prepare("INSERT INTO $table (user_id,playback_post_id,position_seconds,duration_seconds,client_sequence,updated_at) VALUES (%d,%d,%f,%f,%d,%s) ON DUPLICATE KEY UPDATE position_seconds=IF(VALUES(client_sequence)>client_sequence,VALUES(position_seconds),position_seconds),duration_seconds=IF(VALUES(client_sequence)>client_sequence,VALUES(duration_seconds),duration_seconds),updated_at=IF(VALUES(client_sequence)>client_sequence,VALUES(updated_at),updated_at),client_sequence=GREATEST(client_sequence,VALUES(client_sequence))", get_current_user_id(), $row->post_id, $position, $duration, $sequence, current_time('mysql')));
    return $saved === false ? new WP_Error('storage', 'ذخیرهٔ زمان تماشا انجام نشد.') : true;
}

add_action('wp_ajax_popcorn_playback_progress', function () {
    check_ajax_referer('popcorn_playback_progress', 'nonce');
    $result = popcorn_core_save_playback_progress(wp_unslash($_POST));
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 403);
    }
    wp_send_json_success();
});

add_action('delete_user', function ($user_id) {
    global $wpdb;
    $wpdb->delete(popcorn_core_table('watch_progress'), ['user_id' => absint($user_id)], ['%d']);
});
