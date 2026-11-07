<?php
defined('ABSPATH') || exit;

function popcorn_core_public_download_groups($post_id) {
    static $cache = [];
    $post_id = absint($post_id);
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish' || !in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        return $cache[$post_id] = [];
    }
    $type = $post->post_type === 'popcorn_series' ? 'series' : 'movie';
    $groups = popcorn_core_downloads_tree($post->ID, $type);
    foreach ($groups as &$group) {
        $group['links'] = array_values(array_filter($group['links'], static function ($link) { return !empty($link['is_active']); }));
        foreach ($group['seasons'] as &$season) {
            $season['links'] = popcorn_core_download_order_episode_links(array_values(array_filter($season['links'], static function ($link) { return !empty($link['is_active']); })));
        }
        unset($season);
        $group['seasons'] = array_values(array_filter($group['seasons'], static function ($season) { return !empty($season['links']); }));
    }
    unset($group);
    return $cache[$post_id] = array_values(array_filter($groups, static function ($group) { return !empty($group['links']) || !empty($group['seasons']); }));
}

function popcorn_core_public_download_icon($name) {
    $paths = [
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 16v4h16v-4"/>',
        'microphone' => '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2M12 19v3m-4 0h8"/>',
        'subtitles' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M6 10h3m3 0h6M6 14h6m3 0h3"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.1"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
    ];
    return isset($paths[$name]) ? '<svg class="pc-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>' : '';
}

function popcorn_core_public_download_links($links, $post_id) {
    $badge_labels = popcorn_core_download_badge_labels();
    $html = '<ul class="pc-public-downloads__links">';
    $protected = popcorn_core_protected_link_enabled();
    foreach ($links as $link) {
        $urls = popcorn_core_url_candidates($link['url']);
        if (!$urls) {
            continue;
        }
        if ($protected) {
            $urls['primary'] = popcorn_core_protected_link_url($link, $post_id);
        }
        if (empty($urls['primary'])) {
            continue;
        }
        $details = ['' => trim((string) ($link['title'] ?? '')), 'کیفیت' => $link['quality'] ?? '', 'حجم' => $link['size'] ?? '', 'رزولوشن' => $link['resolution'] ?? '', 'انکودر' => $link['encoder'] ?? ''];
        $title = trim((string) ($link['title'] ?? '')) ?: 'دریافت فایل';
        $variant = !empty($link['is_dubbed']) ? ' pc-public-downloads__link--dubbed' : (!empty($link['has_subtitle']) ? ' pc-public-downloads__link--subtitle' : '');
        $resolution = (int) ($link['resolution'] ?? '');
        $html .= '<li class="pc-public-downloads__link' . $variant . '" data-resolution="' . esc_attr($resolution) . '"><div class="pc-public-downloads__meta">';
        foreach ($details as $label => $value) {
            if ($value !== '') {
                $field_label = $label !== '' ? '<span class="pc-public-downloads__label">' . esc_html($label) . ':</span> ' : '';
                $field_class = $label === '' ? ' pc-public-downloads__field--title' : '';
                $html .= '<span class="pc-public-downloads__field' . $field_class . '">' . $field_label . '<b dir="auto">' . esc_html($value) . '</b></span>';
            }
        }
        if (!empty($link['is_dubbed'])) { $html .= '<span class="pc-public-downloads__badge pc-public-downloads__badge--dubbed">' . popcorn_core_public_download_icon('microphone') . ' ' . esc_html($badge_labels['dubbed']) . '</span>'; }
        if (!empty($link['has_subtitle'])) { $html .= '<span class="pc-public-downloads__badge pc-public-downloads__badge--subtitle">' . popcorn_core_public_download_icon('subtitles') . ' ' . esc_html($badge_labels['subtitle']) . '</span>'; }
        $html .= '</div><div class="pc-public-downloads__actions">';
        if (!empty($link['description'])) {
            $html .= '<details class="pc-public-downloads__info"><summary aria-label="توضیحات لینک ' . esc_attr($title) . '">' . popcorn_core_public_download_icon('info') . '</summary><p>' . esc_html($link['description']) . '</p></details>';
        }
        $label = 'دانلود';
        $html .= '<div class="pc-public-downloads__download"><a class="pc-button" href="' . esc_url($urls['primary']) . '" target="_blank" rel="nofollow noopener noreferrer" aria-label="' . esc_attr($label . ' ' . $title) . '">' . popcorn_core_public_download_icon('download') . esc_html($label) . '</a>';
        $html .= '</div></div></li>';
    }
    return $html . '</ul>';
}

function popcorn_core_render_downloads($post_id) {
    $groups = popcorn_core_public_download_groups($post_id);
    if (!$groups) {
        return '';
    }
    if (popcorn_core_protected_link_enabled()) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        if (!headers_sent()) {
            nocache_headers();
        }
    }
    $result = isset($_GET['popcorn_link_report']) ? sanitize_key(wp_unslash($_GET['popcorn_link_report'])) : '';
    $messages = ['sent' => 'گزارش شما ثبت شد. سپاس از اطلاع‌رسانی.', 'invalid' => 'لطفاً توضیح خرابی را بین ۱۰ تا ۱۰۰۰ نویسه وارد کنید.', 'slow' => 'گزارش قبلی شما ثبت شده است. کمی بعد دوباره تلاش کنید.', 'error' => 'ثبت گزارش ناموفق بود. دوباره تلاش کنید.'];
    $colors = popcorn_core_download_colors();
    $style = '--pc-download-group:' . $colors['group'] . ';--pc-download-season:' . $colors['season'] . ';--pc-download-link:' . $colors['link'];
    $html = '<section id="popcorn-downloads" class="pc-section pc-public-downloads" style="' . esc_attr($style) . '"><div class="pc-section__head"><h2>لینک‌های دانلود</h2><button class="pc-link-report__toggle" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="pc-link-report-dialog">گزارش خرابی لینک‌ها</button></div>';
    $html .= '<dialog id="pc-link-report-dialog" class="pc-link-report-dialog" aria-labelledby="pc-link-report-heading"><div class="pc-link-report-dialog__head"><h2 id="pc-link-report-heading">گزارش خرابی لینک‌ها</h2><button type="button" class="pc-link-report-dialog__close" aria-label="بستن گزارش خرابی">' . popcorn_core_public_download_icon('close') . '</button></div><div class="pc-link-report"><form id="pc-link-report-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    $html .= '<input type="hidden" name="action" value="popcorn_submit_link_report"><input type="hidden" name="post_id" value="' . absint($post_id) . '">';
    $html .= wp_nonce_field('popcorn_link_report_' . absint($post_id), '_popcorn_report_nonce', true, false);
    $html .= '<label for="pc-link-report-message">کدام لینک مشکل دارد؟ نشانی یا مشخصات لینک و نوع خرابی را توضیح دهید.</label><textarea id="pc-link-report-message" name="message" rows="4" minlength="10" maxlength="1000" required></textarea><input class="pc-link-report__trap" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"><button type="submit" class="pc-button">ارسال گزارش</button></form></div></dialog>';
    if (isset($messages[$result])) {
        $html .= '<p class="pc-link-report__feedback" role="status">' . esc_html($messages[$result]) . '</p>';
    }
    if (!apply_filters('popcorn_download_links_visible', true, $post_id)) {
        return $html . apply_filters('popcorn_download_access_message', '', $post_id) . '</section>';
    }
    if (popcorn_core_protected_link_enabled() && popcorn_core_protected_link_auth_required() && !is_user_logged_in()) {
        $return_url = get_permalink($post_id) . '#popcorn-downloads';
        $login_url = popcorn_core_public_login_url($return_url);
        return $html . '<p class="pc-public-downloads__access">برای دانلود یا تماشای این اثر وارد حساب کاربری شوید.' . ($login_url ? ' <a class="pc-button" href="' . esc_url($login_url) . '">ورود به حساب کاربری</a>' : '') . '</p></section>';
    }
    foreach ($groups as $group) {
        $icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" class="pc-icon"><path d="m6 9 6 6 6-6"/></svg>';
        $html .= '<div class="pc-public-downloads__group"><h3 class="pc-public-downloads__group-title">' . esc_html($group['title']) . '</h3>';
        if ($group['seasons']) {
            $html .= '<div class="pc-public-downloads__seasons">';
            foreach ($group['seasons'] as $season) {
                $heading = trim((string) $season['title']) ?: 'فصل ' . number_format_i18n((int) $season['season_number']);
                $html .= '<details class="pc-public-downloads__season" open><summary>' . esc_html($heading) . $icon . '</summary>' . popcorn_core_public_download_links($season['links'], $post_id) . '</details>';
            }
            $html .= '</div>';
        } else {
            $html .= popcorn_core_public_download_links($group['links'], $post_id);
        }
        $html .= '</div>';
    }
    return $html . '</section>';
}

add_shortcode('popcorn_downloads', function ($atts) {
    $atts = shortcode_atts(['post_id' => 0], $atts, 'popcorn_downloads');
    return popcorn_core_render_downloads(absint($atts['post_id']) ?: get_the_ID());
});

add_action('wp_enqueue_scripts', function () {
    if (!is_singular(['popcorn_movie', 'popcorn_series']) || !popcorn_core_public_download_groups(get_queried_object_id())) {
        return;
    }
    $file = dirname(__DIR__) . '/assets/css/public-downloads.css';
    $url = plugin_dir_url(dirname(__DIR__) . '/popcorn-core.php');
    wp_enqueue_style('popcorn-public-downloads', $url . 'assets/css/public-downloads.css', [], filemtime($file));
    $script = dirname(__DIR__) . '/assets/js/link-report.js';
    wp_enqueue_script('popcorn-link-report', $url . 'assets/js/link-report.js', [], filemtime($script), true);
});

function popcorn_core_link_report_redirect($post_id, $state) {
    $url = add_query_arg('popcorn_link_report', $state, get_permalink($post_id));
    wp_safe_redirect($url . '#popcorn-downloads', 303);
    exit;
}

function popcorn_core_submit_link_report() {
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!popcorn_core_public_download_groups($post_id)) {
        wp_die('این اثر لینک دانلود فعالی ندارد.', 'گزارش خرابی', ['response' => 400]);
    }
    $nonce = isset($_POST['_popcorn_report_nonce']) ? sanitize_text_field(wp_unslash($_POST['_popcorn_report_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'popcorn_link_report_' . $post_id)) {
        popcorn_core_link_report_redirect($post_id, 'error');
    }
    if (!empty($_POST['website'])) {
        popcorn_core_link_report_redirect($post_id, 'sent');
    }
    $message = isset($_POST['message']) && is_string($_POST['message']) ? trim(sanitize_textarea_field(wp_unslash($_POST['message']))) : '';
    $length = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
    if ($length < 10 || $length > 1000) {
        popcorn_core_link_report_redirect($post_id, 'invalid');
    }
    $identity = get_current_user_id() ?: (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = 'pc_link_report_' . hash_hmac('sha256', $post_id . '|' . $identity, wp_salt('nonce'));
    if (get_transient($key)) {
        popcorn_core_link_report_redirect($post_id, 'slow');
    }
    global $wpdb;
    $now = current_time('mysql');
    $saved = $wpdb->insert(popcorn_core_table('link_report'), ['post_id' => $post_id, 'user_id' => get_current_user_id(), 'message' => $message, 'status' => 'new', 'created_at' => $now, 'updated_at' => $now], ['%d', '%d', '%s', '%s', '%s', '%s']);
    if (!$saved) {
        popcorn_core_link_report_redirect($post_id, 'error');
    }
    set_transient($key, 1, 5 * MINUTE_IN_SECONDS);
    popcorn_core_link_report_redirect($post_id, 'sent');
}
add_action('admin_post_popcorn_submit_link_report', 'popcorn_core_submit_link_report');
add_action('admin_post_nopriv_popcorn_submit_link_report', 'popcorn_core_submit_link_report');

add_action('admin_menu', function () {
    $icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h5"/></svg>');
    add_menu_page('گزارش خرابی لینک‌ها', 'گزارش لینک‌ها', 'moderate_comments', 'popcorn-link-reports', 'popcorn_core_link_reports_page', $icon, 29);
});

function popcorn_core_link_reports_page() {
    if (!current_user_can('moderate_comments')) {
        wp_die('اجازهٔ مدیریت گزارش‌ها را ندارید.');
    }
    global $wpdb;
    $table = popcorn_core_table('link_report');
    $states = ['new' => 'جدید', 'resolved' => 'رسیدگی‌شده'];
    $status = isset($_GET['report_status']) ? sanitize_key(wp_unslash($_GET['report_status'])) : '';
    $status = isset($states[$status]) ? $status : '';
    $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $counts = $wpdb->get_results("SELECT status,COUNT(*) total FROM $table GROUP BY status", OBJECT_K);
    $total = $status ? (int) ($counts[$status]->total ?? 0) : array_sum(array_map(static function ($row) { return (int) $row->total; }, $counts));
    $where = $status ? $wpdb->prepare('WHERE r.status=%s', $status) : '';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT r.*,p.post_title,p.post_type,p.post_status FROM $table r LEFT JOIN {$wpdb->posts} p ON p.ID=r.post_id $where ORDER BY r.created_at DESC,r.id DESC LIMIT %d OFFSET %d", 20, ($page - 1) * 20));
    $base = admin_url('admin.php?page=popcorn-link-reports');
    echo '<div class="wrap"><h1 class="wp-heading-inline">گزارش خرابی لینک‌ها</h1><hr class="wp-header-end"><p>گزارش‌ها فقط در مدیریت نمایش داده می‌شوند. ساختهٔ SirenCode برای پاپکورن.</p><ul class="subsubsub" aria-label="وضعیت گزارش‌ها">';
    $filters = ['' => 'همه'] + $states;
    $filter_index = 0;
    foreach ($filters as $key => $label) {
        $count = $key ? (int) ($counts[$key]->total ?? 0) : array_sum(array_map(static function ($row) { return (int) $row->total; }, $counts));
        $url = $key ? add_query_arg('report_status', $key, $base) : $base;
        echo '<li>' . ($filter_index++ ? ' | ' : '') . '<a class="' . ($status === $key ? 'current' : '') . '" href="' . esc_url($url) . '"' . ($status === $key ? ' aria-current="page"' : '') . '>' . esc_html($label) . ' <span class="count">(' . esc_html(number_format_i18n($count)) . ')</span></a></li>';
    }
    echo '</ul><br class="clear"><table class="wp-list-table widefat fixed striped table-view-list"><thead><tr><th scope="col" class="manage-column column-primary">اثر</th><th scope="col" class="manage-column">نوع</th><th scope="col" class="manage-column">توضیح کاربر</th><th scope="col" class="manage-column">فرستنده</th><th scope="col" class="manage-column">وضعیت</th><th scope="col" class="manage-column">تاریخ</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $title = $row->post_title ?: 'اثر حذف‌شده';
        $edit_url = $row->post_status ? get_edit_post_link($row->post_id) : '';
        $type = ['popcorn_movie' => 'فیلم', 'popcorn_series' => 'سریال'][$row->post_type] ?? '—';
        $user = $row->user_id ? get_userdata($row->user_id) : null;
        echo '<tr><td class="column-primary" data-colname="اثر"><strong>' . ($edit_url ? '<a href="' . esc_url($edit_url) . '">' . esc_html($title) . '</a>' : esc_html($title)) . '</strong><div class="row-actions">';
        $action_index = 0;
        foreach (['new' => 'بازگشایی', 'resolved' => 'رسیدگی شد', 'delete' => 'حذف'] as $action => $label) {
            if ($action === $row->status) {
                continue;
            }
            $url = wp_nonce_url(add_query_arg(['action' => 'popcorn_report_action', 'report_id' => (int) $row->id, 'report_action' => $action], admin_url('admin-post.php')), 'popcorn_report_' . (int) $row->id);
            echo ($action_index++ ? ' | ' : '') . '<span class="' . ($action === 'delete' ? 'trash' : 'edit') . '"><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></span>';
        }
        echo '</div><button type="button" class="toggle-row"><span class="screen-reader-text">نمایش جزئیات بیشتر</span></button></td><td data-colname="نوع">' . esc_html($type) . '</td><td data-colname="توضیح کاربر">' . nl2br(esc_html($row->message)) . '</td><td data-colname="فرستنده">' . esc_html($user ? $user->display_name : 'مهمان') . '</td><td data-colname="وضعیت">' . esc_html($states[$row->status] ?? $row->status) . '</td><td data-colname="تاریخ">' . esc_html(mysql2date('j F Y، H:i', $row->created_at)) . '</td></tr>';
    }
    if (!$rows) {
        echo '<tr class="no-items"><td colspan="6">گزارشی پیدا نشد.</td></tr>';
    }
    echo '</tbody><tfoot><tr><th scope="col" class="manage-column column-primary">اثر</th><th scope="col" class="manage-column">نوع</th><th scope="col" class="manage-column">توضیح کاربر</th><th scope="col" class="manage-column">فرستنده</th><th scope="col" class="manage-column">وضعیت</th><th scope="col" class="manage-column">تاریخ</th></tr></tfoot></table>';
    if ($total > 20) {
        $links = paginate_links(['base' => add_query_arg(['page' => 'popcorn-link-reports', 'report_status' => $status, 'paged' => '%#%'], admin_url('admin.php')), 'format' => '', 'current' => $page, 'total' => ceil($total / 20)]);
        echo '<div class="tablenav bottom"><div class="tablenav-pages">' . $links . '</div></div>';
    }
    echo '</div>';
}

add_action('admin_post_popcorn_report_action', function () {
    if (!current_user_can('moderate_comments')) {
        wp_die('اجازهٔ مدیریت گزارش‌ها را ندارید.', '', ['response' => 403]);
    }
    $id = isset($_GET['report_id']) ? absint($_GET['report_id']) : 0;
    check_admin_referer('popcorn_report_' . $id);
    $action = isset($_GET['report_action']) ? sanitize_key(wp_unslash($_GET['report_action'])) : '';
    global $wpdb;
    $table = popcorn_core_table('link_report');
    if ($action === 'delete') {
        $wpdb->delete($table, ['id' => $id], ['%d']);
    } elseif (in_array($action, ['new', 'resolved'], true)) {
        $wpdb->update($table, ['status' => $action, 'updated_at' => current_time('mysql')], ['id' => $id], ['%s', '%s'], ['%d']);
    }
    wp_safe_redirect(admin_url('admin.php?page=popcorn-link-reports'));
    exit;
});

add_action('before_delete_post', function ($post_id, $post) {
    if (in_array($post->post_type, ['popcorn_movie', 'popcorn_series'], true)) {
        global $wpdb;
        $wpdb->delete(popcorn_core_table('link_report'), ['post_id' => $post_id], ['%d']);
    }
}, 9, 2);
