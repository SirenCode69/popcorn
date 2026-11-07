<?php
defined('ABSPATH') || exit;

function popcorn_tmdb_search_data($type, $search) {
    if (!in_array($type, ['movie', 'series', 'person'], true)) {
        return new WP_Error('invalid_type', 'نوع محتوا معتبر نیست.');
    }
    $search = trim($search);
    if ($search === '' || strlen($search) > 360 || (function_exists('mb_strlen') && mb_strlen($search) > 120)) {
        return new WP_Error('invalid_search', 'نام یا شناسه‌ای تا ۱۲۰ کاراکتر وارد کنید.');
    }
    $path = $type === 'series' ? 'tv' : $type;
    if (ctype_digit($search)) {
        $item = popcorn_tmdb_request($path . '/' . $search);
        return is_wp_error($item) ? $item : [$item];
    }
    $response = popcorn_tmdb_request('search/' . $path, ['query' => $search, 'include_adult' => 'false']);
    return is_wp_error($response) ? $response : array_slice($response['results'] ?? [], 0, 20);
}

function popcorn_tmdb_search_existing($type, $results) {
    global $wpdb;
    $ids = array_values(array_unique(array_filter(array_map('absint', array_column($results, 'id')))));
    if (!$ids) {
        return [];
    }
    $table = popcorn_core_table($type);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT tmdb_id,post_id FROM $table WHERE tmdb_id IN ($placeholders)", ...$ids));
    $existing = [];
    foreach ($rows as $row) {
        $existing[(int) $row->tmdb_id] = $row;
    }
    return $existing;
}

function popcorn_tmdb_search_html($type, $results, $post_id = 0, $search = '') {
    if (!$results) {
        return '<p class="pc-import__empty">نتیجه‌ای پیدا نشد.</p>';
    }
    $existing = popcorn_tmdb_search_existing($type, $results);
    ob_start();
    ?>
    <div class="pc-import__summary"><?php echo esc_html(number_format_i18n(count($results))); ?> نتیجه در TMDb پیدا شد.</div>
    <div class="pc-import__table-wrap"><table class="widefat striped pc-admin__table pc-import__table"><thead><tr><th>تصویر</th><th>اطلاعات</th><th>تاریخ</th><th>شناسهٔ TMDb</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
    <?php foreach ($results as $result) :
        $id = absint($result['id'] ?? 0);
        if (!$id) { continue; }
        $localized_name = popcorn_tmdb_text($result[$type === 'movie' ? 'title' : 'name'] ?? '');
        $original = $type === 'person' ? '' : popcorn_tmdb_text($result[$type === 'movie' ? 'original_title' : 'original_name'] ?? '');
        $name = $original ?: $localized_name;
        $secondary_title = $localized_name !== $name ? $localized_name : '';
        $date = popcorn_tmdb_text($result[$type === 'person' ? 'birthday' : ($type === 'series' ? 'first_air_date' : 'release_date')] ?? '');
        $overview = wp_trim_words(sanitize_textarea_field($result['overview'] ?? ''), 24, '…');
        $image_path = $result[$type === 'person' ? 'profile_path' : 'poster_path'] ?? '';
        $image_url = is_string($image_path) && preg_match('~^/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~', $image_path) ? 'https://image.tmdb.org/t/p/w185' . $image_path : '';
        $record = $existing[$id] ?? null;
        $target_post_id = $record && $record->post_id ? (int) $record->post_id : absint($post_id);
        $can_update = !$record || !$record->post_id || current_user_can('edit_post', $record->post_id);
        ?>
        <tr><td class="pc-import__image-cell"><?php if ($image_url) : ?><img class="pc-import__image" src="<?php echo esc_url($image_url); ?>"<?php echo popcorn_core_image_fallback_attr($image_url); ?> alt="<?php echo esc_attr($name); ?>" width="72" height="108" loading="lazy" decoding="async"><?php else : ?><span class="pc-import__image pc-import__image--empty">بدون تصویر</span><?php endif; ?></td><td class="pc-import__details"><strong><?php echo esc_html($name); ?></strong><?php if ($secondary_title) : ?><small dir="auto"><?php echo esc_html($secondary_title); ?></small><?php endif; ?><?php if ($overview) : ?><p><?php echo esc_html($overview); ?></p><?php endif; ?></td><td><?php echo esc_html($date ?: '—'); ?></td><td><code><?php echo esc_html($id); ?></code></td><td><span class="pc-import__badge <?php echo $record ? 'is-imported' : 'is-new'; ?>"><?php echo $record ? 'دریافت‌شده' : 'جدید'; ?></span></td><td><?php if ($can_update) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('popcorn_import'); ?><input type="hidden" name="action" value="popcorn_import"><input type="hidden" name="type" value="<?php echo esc_attr($type); ?>"><input type="hidden" name="post_id" value="<?php echo esc_attr($target_post_id); ?>"><input type="hidden" name="tmdb_id" value="<?php echo esc_attr($id); ?>"><input type="hidden" name="return_search" value="<?php echo esc_attr($search); ?>"><button class="button <?php echo $record ? '' : 'button-primary'; ?>"><?php echo $record ? 'به‌روزرسانی' : 'افزودن'; ?></button><?php if (!$record && !$post_id) : ?><button class="button" type="submit" name="as_draft" value="1">افزودن پیش‌نویس</button><?php endif; ?></form><?php else : ?><span class="pc-import__restricted">اجازهٔ ویرایش ندارید</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php
    return ob_get_clean();
}

add_action('wp_ajax_popcorn_search_tmdb', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'دسترسی مجاز نیست.'], 403);
    }
    check_ajax_referer('popcorn_search_tmdb', 'nonce');
    $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!in_array($type, ['movie', 'series', 'person'], true) || ($post_id && (get_post_type($post_id) !== 'popcorn_' . $type || !current_user_can('edit_post', $post_id)))) {
        wp_send_json_error(['message' => 'درخواست معتبر نیست.'], 400);
    }
    $results = popcorn_tmdb_search_data($type, $search);
    if (is_wp_error($results)) {
        wp_send_json_error(['message' => $results->get_error_message()], 502);
    }
    wp_send_json_success(['html' => popcorn_tmdb_search_html($type, $results, $post_id, $search)]);
});

add_action('admin_enqueue_scripts', function () {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if (!in_array($page, ['popcorn-import-movie', 'popcorn-import-series', 'popcorn-import-person'], true)) {
        return;
    }
    $url = plugin_dir_url(dirname(__DIR__) . '/popcorn-core.php');
    $base = dirname(__DIR__);
    wp_enqueue_style('popcorn-import', $url . 'assets/css/import.css', [], filemtime($base . '/assets/css/import.css'));
    wp_enqueue_script('popcorn-import', $url . 'assets/js/import.js', [], filemtime($base . '/assets/js/import.js'), true);
    popcorn_core_enqueue_image_fallback();
});
