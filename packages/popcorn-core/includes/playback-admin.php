<?php
defined('ABSPATH') || exit;

add_action('popcorn_settings_tab_playback', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_GET['popcorn_message']) && $_GET['popcorn_message'] === 'saved') {
        echo '<div class="notice notice-success"><p>تنظیمات پخش آنلاین ذخیره شد.</p></div>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="popcorn_save_playback_settings">';
    wp_nonce_field('popcorn_playback_settings');
    echo '<div class="pc-admin__card"><h2>پخش آنلاین آثار</h2><table class="form-table"><tr><th>فعال‌سازی</th><td><label><input type="checkbox" name="enabled" value="1"' . checked(popcorn_core_playback_enabled(), true, false) . '> فعال‌کردن پخش آنلاین فیلم و سریال</label></td></tr></table><p>در منوی «پخش آنلاین» یک پخش بسازید و آن را به فیلم یا سریال وصل کنید. برای هر قسمت سریال، یک پخش با شمارهٔ فصل و قسمت بسازید؛ کیفیت‌ها و زیرنویس‌ها در همان پخش ثبت می‌شوند. در صفحهٔ اثر فقط یک دکمه نمایش داده می‌شود و انتخاب فصل، قسمت و کیفیت داخل پلیر است.</p><p>فقط لینک مستقیم MP4 و MKV پذیرفته می‌شود. برای MKV می‌توانید نسخهٔ جایگزین MP4 ثبت کنید؛ پخش فایل به پشتیبانی مرورگر و کدک آن وابسته است.</p><p>ورود، اشتراک و اعتبار لینک‌های پخش از تنظیمات فعلی حساب کاربری، اشتراک و محافظت از لینک‌ها پیروی می‌کنند. غیرفعال‌کردن این بخش، صفحات و دکمه‌های پخش را از دسترس خارج می‌کند و داده‌ها حفظ می‌شوند.</p><a class="button" href="' . esc_url(admin_url('edit.php?post_type=popcorn_playback')) . '">مدیریت پخش‌های آنلاین</a></div>';
    submit_button('ذخیرهٔ تنظیمات');
    echo '</form>';
});

add_action('admin_post_popcorn_save_playback_settings', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    check_admin_referer('popcorn_playback_settings');
    update_option('popcorn_playback_options', ['enabled' => empty($_POST['enabled']) ? 0 : 1], false);
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }
    do_action('litespeed_purge_all');
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=playback&popcorn_message=saved'));
    exit;
});

add_action('add_meta_boxes_popcorn_playback', function () {
    remove_meta_box('postimagediv', 'popcorn_playback', 'side');
    add_meta_box('popcorn_playback_fields', 'اطلاعات پخش آنلاین · SirenCode', 'popcorn_core_playback_metabox', 'popcorn_playback', 'normal', 'high');
});

function popcorn_core_playback_parent_title($post_id, $row = null, $type = '') {
    $type = $type ?: (get_post_type($post_id) === 'popcorn_series' ? 'series' : 'movie');
    $row = $row ?: popcorn_core_get_by_post($type, $post_id);
    $main = trim((string) ($row->title ?? '')) ?: ($row->post_title ?? get_the_title($post_id));
    $persian = trim((string) ($row->original_title ?? ''));
    return $main . ($persian && $persian !== $main ? ' (' . $persian . ')' : '');
}

function popcorn_core_playback_parent_label($post_id, $row = null, $type = '') {
    $type = $type ?: (get_post_type($post_id) === 'popcorn_series' ? 'series' : 'movie');
    $row = $row ?: popcorn_core_get_by_post($type, $post_id);
    $year = substr((string) ($row->release_date ?? ''), 0, 4);
    $year = preg_match('/^[1-9][0-9]{3}$/D', $year) ? $year : '';
    return ($type === 'series' ? 'سریال' : 'فیلم') . ' - ' . popcorn_core_playback_parent_title($post_id, $row, $type) . ($year ? ' - ' . $year : '');
}

function popcorn_core_playback_parent_cover($post_id, $row = null, $type = '') {
    $type = $type ?: (get_post_type($post_id) === 'popcorn_series' ? 'series' : 'movie');
    $row = $row ?: popcorn_core_get_by_post($type, $post_id);
    $image_id = absint(get_post_meta($post_id, '_thumbnail_id', true)) ?: absint($row->poster_id ?? 0);
    $url = popcorn_core_editor_image_url((string) ($row->poster_path ?? ''), $image_id, ($row->post_status ?? get_post_status($post_id)) === 'publish');
    $urls = popcorn_core_url_candidates($url);
    $fallback = !empty($urls['fallback']) ? add_query_arg(['action'=>'popcorn_image_http_fallback','url'=>$urls['fallback'],'nonce'=>wp_create_nonce('popcorn_image_preview')], admin_url('admin-ajax.php')) : '';
    return ['cover' => $url, 'cover_fallback' => $fallback];
}

function popcorn_core_playback_metabox($post) {
    $row = popcorn_core_get_playback($post->ID);
    $parent_id = absint($row->content_post_id ?? 0);
    $parent_type = get_post_type($parent_id);
    $parent_item = $parent_id ? popcorn_core_get_by_post($parent_type === 'popcorn_series' ? 'series' : 'movie', $parent_id) : null;
    $parent_label = $parent_id ? popcorn_core_playback_parent_label($parent_id, $parent_item) : '';
    $cover = $parent_id ? popcorn_core_playback_parent_cover($parent_id, $parent_item) : ['cover'=>'','cover_fallback'=>''];
    $placeholder = function_exists('popcorn_icon') ? popcorn_icon('image') : '';
    $cover_html = $cover['cover'] ? '<img src="' . esc_url($cover['cover']) . '"' . ($cover['cover_fallback'] ? ' data-http-fallback="' . esc_url($cover['cover_fallback']) . '"' : '') . ' alt="" width="44" height="66" loading="lazy" decoding="async">' : $placeholder;
    wp_nonce_field('popcorn_playback_save_' . $post->ID, 'popcorn_playback_nonce');
    if (!popcorn_core_playback_enabled()) {
        echo '<p>پخش آنلاین در تنظیمات پاپکورن غیرفعال است. اطلاعات قابل ویرایش هستند و در سایت نمایش داده نمی‌شوند.</p>';
    }
    echo '<div class="pc-playback-editor" data-lookup-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-lookup-nonce="' . esc_attr(wp_create_nonce('popcorn_playback_lookup')) . '"><template data-cover-placeholder>' . $placeholder . '</template><div class="pc-playback-editor__field"><label for="pc-playback-search">فیلم یا سریال مرتبط</label><div class="pc-playback-editor__lookup"><input id="pc-playback-search" type="search" autocomplete="off" placeholder="جست‌وجو با عنوان فارسی یا اصلی" aria-controls="pc-playback-results" aria-expanded="false"><input type="hidden" name="popcorn_playback[content_post_id]" value="' . $parent_id . '" data-parent-id><div id="pc-playback-results" class="pc-playback-editor__results" hidden></div></div><p class="pc-playback-editor__selection"><span class="pc-playback-editor__cover" data-parent-cover' . (!$parent_id ? ' hidden' : '') . '>' . $cover_html . '</span><span data-parent-label>' . esc_html($parent_label ?: 'اثری انتخاب نشده است.') . '</span><button type="button" class="button" data-clear-parent' . (!$parent_id ? ' hidden' : '') . '>تغییر اثر</button></p><p data-lookup-status class="description" role="status"></p></div>';
    echo '<div class="pc-playback-editor__episodes"' . ($parent_type !== 'popcorn_series' ? ' hidden' : '') . '>';
    foreach (['season_number' => ['شمارهٔ فصل', 1000], 'episode_number' => ['شمارهٔ قسمت', 10000]] as $field => $label) {
        echo '<div class="pc-playback-editor__field"><label for="pc-playback-' . esc_attr($field) . '">' . esc_html($label[0]) . '</label><input id="pc-playback-' . esc_attr($field) . '" type="number" name="popcorn_playback[' . esc_attr($field) . ']" min="0" max="' . $label[1] . '" value="' . absint($row->$field ?? 0) . '"></div>';
    }
    echo '</div>';
    foreach (['video_url' => ['لینک فایل MP4 یا MKV', 'url'], 'mp4_url' => ['نسخهٔ جایگزین MP4 (اختیاری)', 'url'], 'quality' => ['عنوان کیفیت اصلی (اختیاری)', 'text']] as $field => $label) {
        echo '<div class="pc-playback-editor__field"><label for="pc-playback-' . esc_attr($field) . '">' . esc_html($label[0]) . '</label><input id="pc-playback-' . esc_attr($field) . '" type="' . esc_attr($label[1]) . '" name="popcorn_playback[' . esc_attr($field) . ']" value="' . esc_attr($row->$field ?? '') . '"' . ($label[1] === 'url' ? ' dir="ltr" maxlength="2048"' : ' maxlength="80"') . ($field === 'video_url' ? ' required' : '') . '></div>';
    }
    popcorn_core_playback_repeater('direct_qualities', 'کیفیت‌های دیگر', popcorn_core_playback_rows($row, 'direct_qualities'));
    popcorn_core_playback_repeater('subtitles', 'زیرنویس‌ها', popcorn_core_playback_rows($row, 'subtitles'));
    echo '<label class="pc-playback-editor__active"><input type="checkbox" name="popcorn_playback[is_active]" value="1"' . checked($row->is_active ?? 1, 1, false) . '> این پخش فعال باشد</label><p class="description">انتشار این نوشته، پخش را در صفحهٔ اثر مرتبط نمایش می‌دهد. ویدیو از سرور فایل پخش می‌شود و در رسانهٔ وردپرس دانلود یا تبدیل نمی‌شود. برای جابه‌جایی در زمان ویدیو، سرور فایل باید درخواست‌های Range را پشتیبانی کند.</p></div>';
}

function popcorn_core_playback_repeater($field, $title, $rows) {
    echo '<section class="pc-playback-editor__repeater" data-playback-repeater="' . esc_attr($field) . '"><h3>' . esc_html($title) . '</h3><div data-repeater-rows>';
    foreach ($rows as $index => $row) {
        popcorn_core_playback_repeater_row($field, $index, $row);
    }
    echo '</div><button type="button" class="button" data-repeater-add>افزودن ' . ($field === 'subtitles' ? 'زیرنویس' : 'کیفیت') . '</button><template>';
    popcorn_core_playback_repeater_row($field, '__INDEX__', []);
    echo '</template></section>';
}

function popcorn_core_playback_repeater_row($field, $index, $row) {
    $prefix = 'popcorn_playback[' . $field . '][' . $index . ']';
    echo '<div class="pc-playback-editor__row"><label>عنوان<input type="text" name="' . esc_attr($prefix . '[label]') . '" value="' . esc_attr($row['label'] ?? '') . '" maxlength="80" placeholder="' . ($field === 'subtitles' ? 'زیرنویس فارسی' : '720p') . '"></label><label>لینک فایل<input type="url" dir="ltr" name="' . esc_attr($prefix . '[url]') . '" value="' . esc_attr($row['url'] ?? '') . '" maxlength="2048"></label>';
    if ($field === 'subtitles') {
        echo '<label>زبان<select name="' . esc_attr($prefix . '[language]') . '"><option value="fa"' . selected($row['language'] ?? 'fa', 'fa', false) . '>فارسی</option><option value="en"' . selected($row['language'] ?? 'fa', 'en', false) . '>انگلیسی</option></select></label>';
    }
    echo '<button type="button" class="button pc-playback-editor__remove" data-repeater-remove aria-label="حذف ردیف">حذف</button></div>';
}

add_action('save_post_popcorn_playback', function ($post_id, $post) {
    static $updating_title = false;
    if ($updating_title || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !current_user_can('edit_post', $post_id) || !isset($_POST['popcorn_playback_nonce']) || !is_string($_POST['popcorn_playback_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['popcorn_playback_nonce'])), 'popcorn_playback_save_' . $post_id)) {
        return;
    }
    $data = popcorn_core_clean_playback(isset($_POST['popcorn_playback']) ? wp_unslash($_POST['popcorn_playback']) : []);
    if (is_wp_error($data)) {
        set_transient('popcorn_playback_error_' . get_current_user_id(), $data->get_error_message(), MINUTE_IN_SECONDS);
        return;
    }
    global $wpdb;
    $table = popcorn_core_table('playback');
    $existing = popcorn_core_get_playback($post_id);
    $data['updated_at'] = current_time('mysql');
    $formats = ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s'];
    if ($existing) {
        $saved = $wpdb->update($table, $data, ['post_id' => $post_id], $formats, ['%d']);
    } else {
        $data['post_id'] = $post_id;
        $data['created_at'] = current_time('mysql');
        $saved = $wpdb->insert($table, $data, array_merge($formats, ['%d', '%s']));
    }
    if ($saved === false) {
        set_transient('popcorn_playback_error_' . get_current_user_id(), 'اطلاعات پخش ذخیره نشد. دوباره تلاش کنید.', MINUTE_IN_SECONDS);
    } else {
        $title = popcorn_core_playback_parent_title($data['content_post_id']);
        if ($title !== '' && $post->post_title !== $title) {
            $updating_title = true;
            try {
                $title_saved = wp_update_post(['ID' => $post_id, 'post_title' => wp_slash($title)], true);
                if (is_wp_error($title_saved)) {
                    set_transient('popcorn_playback_error_' . get_current_user_id(), 'عنوان پخش ذخیره نشد. دوباره تلاش کنید.', MINUTE_IN_SECONDS);
                }
            } finally {
                $updating_title = false;
            }
        }
        foreach (array_unique([$data['content_post_id'], (int) ($existing->content_post_id ?? 0)]) as $parent_id) {
            popcorn_core_purge_playback_content($parent_id);
        }
    }
}, 10, 2);

add_action('admin_notices', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'popcorn_playback') {
        return;
    }
    $error = get_transient('popcorn_playback_error_' . get_current_user_id());
    if ($error) {
        echo '<div class="notice notice-error"><p>' . esc_html($error) . ' اطلاعات قبلی حفظ شدند.</p></div>';
        delete_transient('popcorn_playback_error_' . get_current_user_id());
    }
});

add_action('wp_ajax_popcorn_playback_lookup', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'دسترسی مجاز نیست.'], 403);
    }
    check_ajax_referer('popcorn_playback_lookup', 'nonce');
    $query = isset($_POST['query']) && is_string($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
    $query = function_exists('mb_substr') ? mb_substr($query, 0, 100) : substr($query, 0, 100);
    global $wpdb;
    $items = [];
    foreach (['movie' => 'فیلم', 'series' => 'سریال'] as $type => $label) {
        $table = popcorn_core_table($type);
        $like = '%' . $wpdb->esc_like($query) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT i.post_id,i.title,i.original_title,i.release_date,i.poster_id,i.poster_path,p.post_title,p.post_status FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_type=%s WHERE p.post_status IN ('publish','draft','pending','future','private') AND (i.title LIKE %s OR i.original_title LIKE %s OR i.english_title LIKE %s OR p.post_title LIKE %s) ORDER BY i.updated_at DESC LIMIT 10", 'popcorn_' . $type, $like, $like, $like, $like));
        update_meta_cache('post', array_map(static function ($row) { return (int) $row->post_id; }, $rows));
        foreach ($rows as $row) {
            if (current_user_can('edit_post', $row->post_id)) {
                $items[] = ['id' => (int) $row->post_id, 'label' => popcorn_core_playback_parent_label($row->post_id, $row, $type), 'title' => popcorn_core_playback_parent_title($row->post_id, $row, $type), 'type' => $type, 'kind' => $label] + popcorn_core_playback_parent_cover($row->post_id, $row, $type);
            }
        }
    }
    wp_send_json_success(['items' => $items]);
});

add_filter('manage_popcorn_playback_posts_columns', function ($columns) {
    $result = [];
    foreach ($columns as $key => $value) {
        $result[$key] = $value;
        if ($key === 'title') {
            $result += ['pc_playback_parent' => 'اثر مرتبط', 'pc_playback_episode' => 'فصل و قسمت', 'pc_playback_format' => 'فرمت', 'pc_playback_quality' => 'کیفیت', 'pc_playback_active' => 'وضعیت پخش'];
        }
    }
    return $result;
});

add_action('manage_popcorn_playback_posts_custom_column', function ($column, $post_id) {
    if (strpos($column, 'pc_playback_') !== 0) {
        return;
    }
    static $rows = [];
    if (!array_key_exists($post_id, $rows)) {
        $rows[$post_id] = popcorn_core_get_playback($post_id);
    }
    $row = $rows[$post_id];
    if (!$row) {
        echo '—';
        return;
    }
    if ($column === 'pc_playback_parent') {
        $parent = get_post($row->content_post_id);
        echo $parent ? '<a href="' . esc_url(get_edit_post_link($parent->ID)) . '">' . esc_html(popcorn_core_playback_parent_label($parent->ID)) . '</a>' : '—';
    } elseif ($column === 'pc_playback_episode') {
        echo get_post_type($row->content_post_id) === 'popcorn_series' ? 'فصل ' . esc_html(number_format_i18n($row->season_number)) . ' · قسمت ' . esc_html(number_format_i18n($row->episode_number)) : '—';
    } elseif ($column === 'pc_playback_format') {
        echo esc_html(strtoupper(popcorn_core_playback_format($row->video_url)));
    } elseif ($column === 'pc_playback_quality') {
        echo esc_html(implode('، ', array_merge([$row->quality ?: 'اصلی'], array_column(popcorn_core_playback_rows($row, 'direct_qualities'), 'label'))));
    } elseif ($column === 'pc_playback_active') {
        echo $row->is_active ? 'فعال' : 'غیرفعال';
    }
}, 10, 2);

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'popcorn_playback' || !in_array($screen->base, ['post', 'post-new'], true)) {
        return;
    }
    $base = dirname(__DIR__);
    $url = plugin_dir_url($base . '/popcorn-core.php');
    wp_enqueue_style('popcorn-playback-editor', $url . 'assets/css/playback-editor.css', [], filemtime($base . '/assets/css/playback-editor.css'));
    wp_enqueue_script('popcorn-playback-editor', $url . 'assets/js/playback-editor.js', [], filemtime($base . '/assets/js/playback-editor.js'), true);
    popcorn_core_enqueue_image_fallback();
});
