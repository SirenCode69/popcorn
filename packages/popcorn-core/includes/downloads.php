<?php
defined('ABSPATH') || exit;

function popcorn_core_download_clean_badge_labels($input) {
    $input = is_array($input) ? $input : [];
    $labels = [];
    foreach (['dubbed', 'subtitle'] as $key) {
        $value = isset($input[$key]) && is_scalar($input[$key]) ? sanitize_text_field((string) $input[$key]) : '';
        $labels[$key] = mb_substr(trim($value), 0, 80);
    }
    return $labels;
}

function popcorn_core_download_badge_labels() {
    $saved = popcorn_core_download_clean_badge_labels(get_option('popcorn_download_badge_labels', []));
    return ['dubbed' => $saved['dubbed'] ?: 'دوبله فارسی', 'subtitle' => $saved['subtitle'] ?: 'زیرنویس فارسی'];
}

function popcorn_core_download_colors() {
    $defaults = ['group' => '#6C52EE', 'season' => '#2877C9', 'link' => '#159B80'];
    $saved = get_option('popcorn_download_colors', []);
    foreach ($defaults as $key => $default) {
        $defaults[$key] = strtoupper(sanitize_hex_color(is_array($saved) ? ($saved[$key] ?? '') : '') ?: $default);
    }
    return $defaults;
}

function popcorn_core_download_select_defaults() {
    return ['quality' => ['WEB-DL', 'BluRay', 'HDTV'], 'encoder' => ['x264', 'x265'], 'resolution' => ['720p', '1080p', '2160p']];
}

function popcorn_core_download_select_options() {
    $defaults = popcorn_core_download_select_defaults();
    $saved = get_option('popcorn_download_select_options', false);
    if (!is_array($saved)) {
        return $defaults;
    }
    foreach ($defaults as $key => $values) {
        $defaults[$key] = isset($saved[$key]) && is_array($saved[$key]) ? array_values(array_filter(array_map('sanitize_text_field', $saved[$key]), 'strlen')) : $values;
    }
    return $defaults;
}

function popcorn_core_protected_download_options() {
    $saved = get_option('popcorn_protected_downloads', []);
    $saved = is_array($saved) ? $saved : [];
    return [
        'enabled' => empty($saved['enabled']) ? 0 : 1,
        'download_minutes' => max(1, min(1440, absint($saved['download_minutes'] ?? 15))),
        'watch_minutes' => max(1, min(1440, absint($saved['watch_minutes'] ?? 5))),
    ];
}

function popcorn_core_download_clean_select_options($input) {
    if (!is_array($input)) {
        return new WP_Error('download_options', 'فهرست گزینه‌های دانلود معتبر نیست.');
    }
    $clean = [];
    foreach (popcorn_core_download_select_defaults() as $key => $defaults) {
        $values = $input[$key] ?? [];
        if (!is_array($values) || count($values) > 100) {
            return new WP_Error('download_options', 'هر فهرست حداکثر ۱۰۰ گزینه می‌تواند داشته باشد.');
        }
        $clean[$key] = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                return new WP_Error('download_options', 'مقدار یکی از گزینه‌ها معتبر نیست.');
            }
            $value = trim(sanitize_text_field((string) $value));
            if ((function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) > 80) {
                return new WP_Error('download_options', 'نام هر گزینه باید حداکثر ۸۰ نویسه باشد.');
            }
            if ($value !== '' && !in_array($value, $clean[$key], true)) {
                $clean[$key][] = $value;
            }
        }
    }
    return $clean;
}

function popcorn_core_downloads_tree($post_id, $type) {
    if (!in_array($type, ['movie', 'series'], true)) {
        return [];
    }
    global $wpdb;
    $groups_table = popcorn_core_table('download_group');
    $seasons_table = popcorn_core_table('download_season');
    $links_table = popcorn_core_table('download_link');
    $groups = $wpdb->get_results($wpdb->prepare("SELECT * FROM $groups_table WHERE post_id=%d AND content_type=%s ORDER BY sort_order,id", $post_id, $type), ARRAY_A);
    if (!$groups) {
        return [];
    }
    $ids = implode(',', array_map('absint', array_column($groups, 'id')));
    $seasons = $type === 'series' ? $wpdb->get_results("SELECT * FROM $seasons_table WHERE group_id IN ($ids) ORDER BY sort_order,id", ARRAY_A) : [];
    $links = $wpdb->get_results("SELECT * FROM $links_table WHERE group_id IN ($ids) ORDER BY sort_order,id", ARRAY_A);
    $group_map = [];
    $season_map = [];
    foreach ($groups as $index => &$group) {
        $group['seasons'] = [];
        $group['links'] = [];
        $group_map[(int) $group['id']] = $index;
    }
    unset($group);
    foreach ($seasons as $season) {
        $group_id = (int) $season['group_id'];
        if (!isset($group_map[$group_id])) {
            continue;
        }
        $index = $group_map[$group_id];
        $season['links'] = [];
        $groups[$index]['seasons'][] = $season;
        $season_map[(int) $season['id']] = [$index, count($groups[$index]['seasons']) - 1];
    }
    foreach ($links as $link) {
        $group_id = (int) $link['group_id'];
        if (!isset($group_map[$group_id])) {
            continue;
        }
        if ($type === 'series') {
            $season_id = (int) $link['season_id'];
            if (isset($season_map[$season_id]) && $season_map[$season_id][0] === $group_map[$group_id]) {
                [$group_index, $season_index] = $season_map[$season_id];
                $groups[$group_index]['seasons'][$season_index]['links'][] = $link;
            }
        } else {
            $groups[$group_map[$group_id]]['links'][] = $link;
        }
    }
    return $groups;
}

function popcorn_core_download_actions() {
    $up = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 14 6-6 6 6"/></svg>';
    $down = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 10 6 6 6-6"/></svg>';
    return '<div class="pc-downloads__actions"><button type="button" class="button pc-downloads__up" aria-label="بالا" title="بالا">' . $up . '</button><button type="button" class="button pc-downloads__down" aria-label="پایین" title="پایین">' . $down . '</button><button type="button" class="button pc-downloads__remove">حذف</button></div>';
}

function popcorn_core_download_field($name, $label, $value = '', $type = 'text', $extra = '', $class = '') {
    echo '<label class="pc-downloads__field ' . esc_attr($class) . '"><span>' . esc_html($label) . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . $extra . '></label>';
}

function popcorn_core_download_select_field($name, $label, $value, $choices) {
    $value = (string) $value;
    if ($value !== '' && !in_array($value, $choices, true)) {
        $choices[] = $value;
    }
    echo '<label class="pc-downloads__field"><span>' . esc_html($label) . '</span><select name="' . esc_attr($name) . '"><option value="">انتخاب کنید</option>';
    foreach ($choices as $choice) {
        echo '<option value="' . esc_attr($choice) . '"' . selected($value, $choice, false) . '>' . esc_html($choice) . '</option>';
    }
    echo '</select></label>';
}

function popcorn_core_download_link_row($group_index, $season_index, $index, $link = [], $type = 'movie') {
    $name = 'popcorn_downloads[' . $group_index . ']';
    if ($type === 'series') {
        $name .= '[seasons][' . $season_index . ']';
    }
    $name .= '[links][' . $index . ']';
    echo '<div class="pc-downloads__node pc-downloads__link"><div class="pc-downloads__heading"><strong>لینک دانلود</strong>' . popcorn_core_download_actions() . '</div><div class="pc-downloads__fields">';
    popcorn_core_download_field($name . '[title]', 'عنوان لینک', $link['title'] ?? '', 'text', 'maxlength="255" placeholder="مثلاً نسخهٔ دوبله"', 'pc-downloads__field--half');
    popcorn_core_download_field($name . '[url]', 'آدرس دانلود', $link['url'] ?? '', 'url', 'dir="ltr" maxlength="2048" required placeholder="https://example.com/file.mkv"', 'pc-downloads__field--half');
    $options = popcorn_core_download_select_options();
    popcorn_core_download_select_field($name . '[quality]', 'کیفیت', $link['quality'] ?? '', $options['quality']);
    popcorn_core_download_select_field($name . '[encoder]', 'انکدر', $link['encoder'] ?? '', $options['encoder']);
    popcorn_core_download_select_field($name . '[resolution]', 'رزولوشن', $link['resolution'] ?? '', $options['resolution']);
    popcorn_core_download_field($name . '[size]', 'حجم', $link['size'] ?? '', 'text', 'maxlength="80" placeholder="مثلاً 1.2 GB"');
    echo '<label class="pc-downloads__field pc-downloads__field--wide"><span>توضیحات</span><textarea name="' . esc_attr($name . '[description]') . '" rows="2">' . esc_textarea($link['description'] ?? '') . '</textarea></label></div><div class="pc-downloads__checks">';
    foreach (['is_active' => 'فعال', 'is_dubbed' => 'دوبلهٔ فارسی', 'has_subtitle' => 'زیرنویس فارسی'] as $key => $label) {
        $checked = array_key_exists($key, $link) ? !empty($link[$key]) : $key === 'is_active';
        echo '<label><input type="checkbox" name="' . esc_attr($name . '[' . $key . ']') . '" value="1"' . checked($checked, true, false) . '> ' . esc_html($label) . '</label>';
    }
    echo '</div></div>';
}

function popcorn_core_download_season_row($group_index, $index, $season = []) {
    $name = 'popcorn_downloads[' . $group_index . '][seasons][' . $index . ']';
    echo '<div class="pc-downloads__node pc-downloads__season" data-season-index="' . esc_attr($index) . '"><div class="pc-downloads__heading"><strong>فصل</strong>' . popcorn_core_download_actions() . '</div><div class="pc-downloads__fields">';
    popcorn_core_download_field($name . '[season_number]', 'شمارهٔ فصل', $season['season_number'] ?? '1', 'number', 'min="0" max="999" required');
    popcorn_core_download_field($name . '[title]', 'عنوان فصل', $season['title'] ?? '', 'text', 'maxlength="255" placeholder="اختیاری"');
    echo '</div><div class="pc-downloads__list pc-downloads__list--links" data-level="link">';
    foreach (($season['links'] ?? []) as $link_index => $link) {
        popcorn_core_download_link_row($group_index, $index, $link_index, $link, 'series');
    }
    echo '</div><button type="button" class="button pc-downloads__add" data-add="link">افزودن لینک</button></div>';
}

function popcorn_core_download_group_row($index, $group = [], $type = 'movie') {
    $name = 'popcorn_downloads[' . $index . ']';
    echo '<div class="pc-downloads__node pc-downloads__group" data-group-index="' . esc_attr($index) . '"><div class="pc-downloads__heading"><strong>گروه دانلود</strong>' . popcorn_core_download_actions() . '</div><div class="pc-downloads__fields">';
    popcorn_core_download_field($name . '[title]', 'عنوان گروه', $group['title'] ?? '', 'text', 'maxlength="255" required placeholder="مثلاً نسخه‌های اصلی"');
    echo '</div><div class="pc-downloads__list" data-level="' . ($type === 'series' ? 'season' : 'link') . '">';
    if ($type === 'series') {
        foreach (($group['seasons'] ?? []) as $season_index => $season) {
            popcorn_core_download_season_row($index, $season_index, $season);
        }
    } else {
        foreach (($group['links'] ?? []) as $link_index => $link) {
            popcorn_core_download_link_row($index, '', $link_index, $link);
        }
    }
    echo '</div><button type="button" class="button pc-downloads__add" data-add="' . ($type === 'series' ? 'season' : 'link') . '">افزودن ' . ($type === 'series' ? 'فصل' : 'لینک') . '</button></div>';
}

function popcorn_core_downloads_panel($post_id, $type) {
    $colors = popcorn_core_download_colors();
    $style = '--pc-download-group:' . $colors['group'] . ';--pc-download-season:' . $colors['season'] . ';--pc-download-link:' . $colors['link'];
    echo '<div class="pc-downloads" data-type="' . esc_attr($type) . '" style="' . esc_attr($style) . '"><p>گروه‌های دانلود را بسازید و لینک‌ها را در ترتیب دلخواه قرار دهید. لینک‌های سریال داخل فصل هر گروه ثبت می‌شوند.</p><input type="hidden" name="popcorn_downloads_present" value="1"><div class="pc-downloads__list pc-downloads__list--groups" data-level="group">';
    foreach (popcorn_core_downloads_tree($post_id, $type) as $index => $group) {
        popcorn_core_download_group_row($index, $group, $type);
    }
    echo '</div><button type="button" class="button pc-downloads__add" data-add="group">افزودن گروه</button><template id="pc-download-group-template">';
    popcorn_core_download_group_row('__GROUP__', [], $type);
    echo '</template><template id="pc-download-season-template">';
    popcorn_core_download_season_row('__GROUP__', '__SEASON__');
    echo '</template><template id="pc-download-link-template">';
    popcorn_core_download_link_row('__GROUP__', '__SEASON__', '__LINK__', [], $type);
    echo '</template></div>';
}

function popcorn_core_download_episode_number($link) {
    $digits = static fn($value) => strtr((string) $value, array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
    $title = $digits($link['title'] ?? '');
    if (preg_match('/(?:قسمت\s*|\b(?:episode|ep|e)\s*[._-]?\s*)(\d+)/iu', $title, $match)) { return (int) $match[1]; }
    $url = $digits(rawurldecode((string) ($link['url'] ?? '')));
    if (preg_match('/(?:s\d+e|\b(?:episode|ep)[._-]?)(\d+)/i', $url, $match) || preg_match('/[_\/.](\d{1,3})[_\.-](?:2160|1080|720|576|540|480|360|240)p\b/i', $url, $match)) { return (int) $match[1]; }
    return 0;
}

function popcorn_core_download_order_episode_links($links) {
    if (!array_filter($links, 'popcorn_core_download_episode_number')) { return $links; }
    usort($links, static fn($a, $b) => [popcorn_core_download_episode_number($a) ?: PHP_INT_MAX, (int) ($a['resolution'] ?? '')] <=> [popcorn_core_download_episode_number($b) ?: PHP_INT_MAX, (int) ($b['resolution'] ?? '')]);
    return $links;
}

function popcorn_core_download_clean_links($links) {
    if (!is_array($links) || count($links) > 1000) {
        return new WP_Error('download_links', 'تعداد لینک‌های یک بخش معتبر نیست.');
    }
    $clean = [];
    foreach ($links as $link) {
        if (!is_array($link) || !is_scalar($link['url'] ?? null)) {
            return new WP_Error('download_link', 'آدرس یکی از لینک‌های دانلود معتبر نیست.');
        }
        $url = esc_url_raw(trim((string) $link['url']), ['http', 'https']);
        if (!$url || strlen($url) > 2048 || !wp_parse_url($url, PHP_URL_HOST) || wp_parse_url($url, PHP_URL_USER) || wp_parse_url($url, PHP_URL_PASS)) {
            return new WP_Error('download_link', 'آدرس لینک دانلود باید یک نشانی کامل HTTP یا HTTPS باشد.');
        }
        $size = is_scalar($link['size'] ?? null) ? trim(sanitize_text_field((string) $link['size'])) : '';
        if ((function_exists('mb_strlen') ? mb_strlen($size) : strlen($size)) > 80) {
            return new WP_Error('download_size', 'حجم لینک دانلود نباید بیش از ۸۰ نویسه باشد.');
        }
        $clean[] = [
            'title' => sanitize_text_field($link['title'] ?? ''),
            'quality' => sanitize_text_field($link['quality'] ?? ''),
            'encoder' => sanitize_text_field($link['encoder'] ?? ''),
            'resolution' => sanitize_text_field($link['resolution'] ?? ''),
            'size' => $size,
            'url' => $url,
            'access_mode' => 'download',
            'description' => sanitize_textarea_field($link['description'] ?? ''),
            'is_active' => empty($link['is_active']) ? 0 : 1,
            'is_dubbed' => empty($link['is_dubbed']) ? 0 : 1,
            'has_subtitle' => empty($link['has_subtitle']) ? 0 : 1,
        ];
    }
    return $clean;
}

function popcorn_core_download_clean_tree($input, $type) {
    if (!is_array($input) || count($input) > 50) {
        return new WP_Error('download_groups', 'تعداد گروه‌های دانلود معتبر نیست.');
    }
    $groups = [];
    foreach ($input as $group) {
        if (!is_array($group) || !is_scalar($group['title'] ?? null) || trim((string) $group['title']) === '') {
            return new WP_Error('download_group', 'برای هر گروه دانلود عنوان وارد کنید.');
        }
        $clean = ['title' => sanitize_text_field($group['title']), 'seasons' => [], 'links' => []];
        if ($type === 'series') {
            $seasons = $group['seasons'] ?? [];
            if (!is_array($seasons) || count($seasons) > 100) {
                return new WP_Error('download_seasons', 'تعداد فصل‌های گروه معتبر نیست.');
            }
            foreach ($seasons as $season) {
                if (!is_array($season) || !is_scalar($season['season_number'] ?? null) || !preg_match('/^\d{1,3}$/', (string) $season['season_number'])) {
                    return new WP_Error('download_season', 'شمارهٔ فصل باید بین ۰ تا ۹۹۹ باشد.');
                }
                $links = popcorn_core_download_clean_links($season['links'] ?? []);
                if (is_wp_error($links)) {
                    return $links;
                }
                $clean['seasons'][] = ['season_number' => (int) $season['season_number'], 'title' => sanitize_text_field($season['title'] ?? ''), 'links' => $links];
            }
        } else {
            $links = popcorn_core_download_clean_links($group['links'] ?? []);
            if (is_wp_error($links)) {
                return $links;
            }
            $clean['links'] = $links;
        }
        $groups[] = $clean;
    }
    return $groups;
}

function popcorn_core_downloads_replace($post_id, $type, $groups) {
    global $wpdb;
    $groups_table = popcorn_core_table('download_group');
    $seasons_table = popcorn_core_table('download_season');
    $links_table = popcorn_core_table('download_link');
    if ($wpdb->query('START TRANSACTION') === false) {
        return false;
    }
    $old_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $groups_table WHERE post_id=%d AND content_type=%s", $post_id, $type));
    if ($old_ids) {
        $ids = implode(',', array_map('absint', $old_ids));
        if ($wpdb->query("DELETE FROM $links_table WHERE group_id IN ($ids)") === false || $wpdb->query("DELETE FROM $seasons_table WHERE group_id IN ($ids)") === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
    if ($wpdb->delete($groups_table, ['post_id' => $post_id, 'content_type' => $type]) === false) {
        $wpdb->query('ROLLBACK');
        return false;
    }
    foreach ($groups as $group_order => $group) {
        if ($wpdb->insert($groups_table, ['post_id' => $post_id, 'content_type' => $type, 'title' => $group['title'], 'sort_order' => $group_order]) === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        $group_id = (int) $wpdb->insert_id;
        $containers = $type === 'series' ? $group['seasons'] : [['links' => $group['links']]];
        foreach ($containers as $season_order => $container) {
            $season_id = 0;
            if ($type === 'series') {
                if ($wpdb->insert($seasons_table, ['group_id' => $group_id, 'season_number' => $container['season_number'], 'title' => $container['title'], 'sort_order' => $season_order]) === false) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
                $season_id = (int) $wpdb->insert_id;
            }
            foreach ($container['links'] as $link_order => $link) {
                if ($wpdb->insert($links_table, array_merge($link, ['group_id' => $group_id, 'season_id' => $season_id, 'sort_order' => $link_order])) === false) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
            }
        }
    }
    return $wpdb->query('COMMIT') !== false;
}

function popcorn_core_downloads_save($post_id, $post) {
    if (!isset($_POST['popcorn_downloads_present'], $_POST['popcorn_fields_nonce']) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !current_user_can('edit_post', $post_id) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['popcorn_fields_nonce'])), 'popcorn_fields_' . $post_id)) {
        return;
    }
    $type = str_replace('popcorn_', '', $post->post_type);
    if (!in_array($type, ['movie', 'series'], true)) {
        return;
    }
    $input = isset($_POST['popcorn_downloads']) ? wp_unslash($_POST['popcorn_downloads']) : [];
    $clean = popcorn_core_download_clean_tree($input, $type);
    if (is_wp_error($clean) || !popcorn_core_downloads_replace($post_id, $type, $clean)) {
        $message = is_wp_error($clean) ? $clean->get_error_message() : 'ذخیرهٔ لینک‌های دانلود ناموفق بود.';
        set_transient('popcorn_download_error_' . get_current_user_id() . '_' . $post_id, $message, MINUTE_IN_SECONDS);
    }
}
add_action('save_post_popcorn_movie', 'popcorn_core_downloads_save', 30, 2);
add_action('save_post_popcorn_series', 'popcorn_core_downloads_save', 30, 2);

add_action('admin_notices', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || !in_array($screen->post_type, ['popcorn_movie', 'popcorn_series'], true) || !isset($_GET['post'])) {
        return;
    }
    $key = 'popcorn_download_error_' . get_current_user_id() . '_' . absint($_GET['post']);
    $message = get_transient($key);
    if ($message) {
        delete_transient($key);
        echo '<div class="notice notice-error"><p>' . esc_html($message) . ' لینک‌های قبلی حفظ شدند.</p></div>';
    }
});

add_action('before_delete_post', function ($post_id, $post) {
    if (!in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        return;
    }
    global $wpdb;
    $groups = popcorn_core_table('download_group');
    $seasons = popcorn_core_table('download_season');
    $links = popcorn_core_table('download_link');
    $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $groups WHERE post_id=%d", $post_id));
    if ($ids) {
        $list = implode(',', array_map('absint', $ids));
        $wpdb->query("DELETE FROM $links WHERE group_id IN ($list)");
        $wpdb->query("DELETE FROM $seasons WHERE group_id IN ($list)");
        $wpdb->delete($groups, ['post_id' => $post_id]);
    }
}, 9, 2);

function popcorn_core_download_option_row($key, $index, $value = '') {
    $up = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 14 6-6 6 6"/></svg>';
    $down = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 10 6 6 6-6"/></svg>';
    echo '<div class="pc-download-option-row"><input type="text" name="popcorn_download_selects[' . esc_attr($key) . '][' . esc_attr($index) . ']" value="' . esc_attr($value) . '" maxlength="80" placeholder="نام گزینه"><button type="button" class="button pc-download-option-up" aria-label="انتقال به بالا" title="انتقال به بالا">' . $up . '</button><button type="button" class="button pc-download-option-down" aria-label="انتقال به پایین" title="انتقال به پایین">' . $down . '</button><button type="button" class="button pc-download-option-remove">حذف</button></div>';
}

function popcorn_core_download_settings_tab() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $message = isset($_GET['popcorn_message']) ? sanitize_key(wp_unslash($_GET['popcorn_message'])) : '';
    if ($message === 'saved') {
        echo '<div class="notice notice-success"><p>تنظیمات لینک‌های دانلود ذخیره شد.</p></div>';
    } elseif ($message === 'invalid') {
        echo '<div class="notice notice-error"><p>گزینه‌های کیفیت، انکدر و رزولوشن را بررسی کنید؛ تنظیمات قبلی حفظ شد.</p></div>';
    }
    $colors = popcorn_core_download_colors();
    $options = popcorn_core_download_select_options();
    $protected = popcorn_core_protected_download_options();
    $badge_labels = popcorn_core_download_clean_badge_labels(get_option('popcorn_download_badge_labels', []));
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pc-download-settings"><input type="hidden" name="action" value="popcorn_save_download_settings">';
    wp_nonce_field('popcorn_download_settings');
    echo '<div class="pc-download-settings__main"><div class="pc-admin__card"><h2>رنگ بخش‌های لینک دانلود</h2><p>برای گروه، فصل سریال و لینک، رنگ جداگانه انتخاب کنید. این رنگ‌ها در فرم ویرایش فیلم و سریال نمایش داده می‌شوند.</p><table class="form-table"><tbody>';
    foreach (['group' => 'گروه', 'season' => 'فصل', 'link' => 'لینک'] as $key => $label) {
        echo '<tr><th><label for="pc-download-' . esc_attr($key) . '-hex">رنگ ' . esc_html($label) . '</label></th><td><div class="pc-admin__color"><input type="color" value="' . esc_attr($colors[$key]) . '" data-color="' . esc_attr($key) . '" aria-label="انتخاب رنگ ' . esc_attr($label) . '"><input id="pc-download-' . esc_attr($key) . '-hex" type="text" name="popcorn_download_colors[' . esc_attr($key) . ']" value="' . esc_attr($colors[$key]) . '" data-color-hex="' . esc_attr($key) . '" maxlength="7" pattern="#[0-9A-Fa-f]{6}" dir="ltr" required></div></td></tr>';
    }
    echo '</tbody></table></div><div class="pc-admin__card"><h2>متن بدج‌های دوبله و زیرنویس</h2><p>این متن‌ها در صفحهٔ فیلم و سریال و روی لینک‌های دانلود نمایش داده می‌شوند. اگر فیلد خالی باشد، متن پیش‌فرض استفاده می‌شود.</p><table class="form-table"><tbody>';
    foreach (['dubbed' => 'دوبله فارسی', 'subtitle' => 'زیرنویس فارسی'] as $key => $label) {
        echo '<tr><th><label for="pc-download-badge-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input id="pc-download-badge-' . esc_attr($key) . '" type="text" name="popcorn_download_badge_labels[' . esc_attr($key) . ']" value="' . esc_attr($badge_labels[$key]) . '" placeholder="' . esc_attr($label) . '" maxlength="80" class="regular-text"></td></tr>';
    }
    echo '</tbody></table></div><div class="pc-admin__card"><h2>محافظت از لینک‌ها</h2><p>در حالت فعال، نشانی اصلی در صفحه نمایش داده نمی‌شود و لینک‌های موقت با امضای هش ساخته می‌شوند. اگر حساب کاربری فعال باشد، ورود برای دانلود و تماشا لازم است.</p><table class="form-table"><tbody>';
    echo '<tr><th>فعال‌سازی</th><td><label><input type="checkbox" name="popcorn_protected_downloads[enabled]" value="1"' . checked($protected['enabled'], 1, false) . '> فعال‌کردن لینک‌های محافظت‌شده</label></td></tr>';
    echo '<tr><th><label for="pc-download-validity">اعتبار لینک دانلود</label></th><td><input id="pc-download-validity" type="number" name="popcorn_protected_downloads[download_minutes]" value="' . esc_attr($protected['download_minutes']) . '" min="1" max="1440" required> دقیقه</td></tr>';
    echo '<tr><th><label for="pc-watch-validity">اعتبار لینک تماشا</label></th><td><input id="pc-watch-validity" type="number" name="popcorn_protected_downloads[watch_minutes]" value="' . esc_attr($protected['watch_minutes']) . '" min="1" max="1440" required> دقیقه</td></tr>';
    echo '</tbody></table><p>اعتبار از زمان ساخت لینک روی صفحه محاسبه می‌شود. پس از پایان اعتبار، کاربر باید صفحهٔ اثر را دوباره باز کند.</p></div></div><div class="pc-download-settings__options">';
    foreach (['quality' => 'کیفیت', 'encoder' => 'انکدر', 'resolution' => 'رزولوشن'] as $key => $label) {
        echo '<div class="pc-admin__card"><h2>گزینه‌های ' . esc_html($label) . '</h2><p>این گزینه‌ها در فیلد ' . esc_html($label) . ' لینک‌های دانلود فیلم و سریال نمایش داده می‌شوند.</p><div class="pc-download-option-list" data-download-list="' . esc_attr($key) . '">';
        foreach ($options[$key] as $index => $value) {
            popcorn_core_download_option_row($key, $index, $value);
        }
        echo '</div><button type="button" class="button pc-download-option-add" data-add-option="' . esc_attr($key) . '">افزودن ' . esc_html($label) . '</button><template data-option-template="' . esc_attr($key) . '">';
        popcorn_core_download_option_row($key, '__INDEX__');
        echo '</template></div>';
    }
    echo '</div>';
    submit_button('ذخیرهٔ تنظیمات دانلود');
    echo '</form>';
}
add_action('popcorn_settings_tab_downloads', 'popcorn_core_download_settings_tab');

add_action('admin_post_popcorn_save_download_settings', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.');
    }
    check_admin_referer('popcorn_download_settings');
    $selects_input = isset($_POST['popcorn_download_selects']) && is_array($_POST['popcorn_download_selects']) ? wp_unslash($_POST['popcorn_download_selects']) : [];
    $selects = popcorn_core_download_clean_select_options($selects_input);
    if (is_wp_error($selects)) {
        wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=downloads&popcorn_message=invalid'));
        exit;
    }
    $input = isset($_POST['popcorn_download_colors']) && is_array($_POST['popcorn_download_colors']) ? wp_unslash($_POST['popcorn_download_colors']) : [];
    $colors = popcorn_core_download_colors();
    foreach ($colors as $key => $current) {
        $colors[$key] = strtoupper(sanitize_hex_color($input[$key] ?? '') ?: $current);
    }
    update_option('popcorn_download_colors', $colors, false);
    update_option('popcorn_download_select_options', $selects, false);
    $badge_before = popcorn_core_download_badge_labels();
    $badge_input = isset($_POST['popcorn_download_badge_labels']) && is_array($_POST['popcorn_download_badge_labels']) ? wp_unslash($_POST['popcorn_download_badge_labels']) : [];
    update_option('popcorn_download_badge_labels', popcorn_core_download_clean_badge_labels($badge_input), false);
    $protected_input = isset($_POST['popcorn_protected_downloads']) && is_array($_POST['popcorn_protected_downloads']) ? wp_unslash($_POST['popcorn_protected_downloads']) : [];
    $before = popcorn_core_protected_download_options();
    $protected_saved = [
        'enabled' => empty($protected_input['enabled']) ? 0 : 1,
        'download_minutes' => max(1, min(1440, absint($protected_input['download_minutes'] ?? 15))),
        'watch_minutes' => max(1, min(1440, absint($protected_input['watch_minutes'] ?? 5))),
    ];
    update_option('popcorn_protected_downloads', $protected_saved, false);
    if ($before !== $protected_saved || $badge_before !== popcorn_core_download_badge_labels()) {
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        do_action('litespeed_purge_all');
    }
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=downloads&popcorn_message=saved'));
    exit;
});

add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    $base = dirname(__DIR__);
    $url = plugin_dir_url($base . '/popcorn-core.php');
    if ($screen && in_array($screen->base, ['post', 'post-new'], true) && in_array($screen->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        wp_enqueue_style('popcorn-downloads', $url . 'assets/css/downloads.css', ['popcorn-editor-tabs'], filemtime($base . '/assets/css/downloads.css'));
        wp_enqueue_script('popcorn-downloads', $url . 'assets/js/downloads.js', ['popcorn-editor-tabs'], filemtime($base . '/assets/js/downloads.js'), true);
    } elseif ($hook === 'toplevel_page_popcorn-settings' && isset($_GET['tab']) && sanitize_key(wp_unslash($_GET['tab'])) === 'downloads') {
        wp_enqueue_style('popcorn-download-settings', $url . 'assets/css/download-settings.css', [], filemtime($base . '/assets/css/download-settings.css'));
        wp_enqueue_script('popcorn-download-colors', $url . 'assets/js/download-colors.js', [], filemtime($base . '/assets/js/download-colors.js'), true);
    }
});
