<?php
defined('ABSPATH') || exit;

function popcorn_subscription_admin_url($message = '') {
    $url = admin_url('admin.php?page=popcorn-settings&tab=subscription');
    return $message ? add_query_arg('popcorn_message', $message, $url) : $url;
}

function popcorn_subscription_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $options = popcorn_subscription_options();
    $plans = $wpdb->get_results('SELECT * FROM ' . popcorn_subscription_table('plan') . ' ORDER BY is_active DESC, id DESC');
    $edit_id = absint($_GET['edit_plan'] ?? 0);
    $edit = $edit_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . popcorn_subscription_table('plan') . ' WHERE id = %d', $edit_id)) : null;
    $message = sanitize_key(wp_unslash($_GET['popcorn_message'] ?? ''));
    $messages = ['saved' => 'تنظیمات اشتراک ذخیره شد.', 'plan_saved' => 'پلن ذخیره شد.', 'granted' => 'اشتراک کاربر ثبت شد.', 'subscription_updated' => 'وضعیت اشتراک تغییر کرد.', 'auth_required' => 'ابتدا سیستم حساب کاربری پاپکورن را فعال کنید.', 'invalid' => 'اطلاعات واردشده معتبر نیست.', 'error' => 'ذخیره انجام نشد. دوباره تلاش کنید.'];
    if (isset($messages[$message])) {
        echo '<div class="notice notice-' . (in_array($message, ['saved', 'plan_saved', 'granted', 'subscription_updated'], true) ? 'success' : 'error') . '"><p>' . esc_html($messages[$message]) . '</p></div>';
    }
    echo '<div class="pc-sub-admin"><div class="pc-sub-admin__main">';
    echo '<div class="pc-admin__card"><h2>سیستم اشتراک</h2><p>این بخش فقط همراه با حساب کاربری پاپکورن کار می‌کند. در حالت خاموش، سایت و لینک‌های دانلود بدون کنترل اشتراک پاپکورن در دسترس می‌مانند.</p>';
    if (!function_exists('popcorn_auth_enabled') || !popcorn_auth_enabled()) {
        echo '<p><strong>حساب کاربری پاپکورن خاموش است.</strong> برای روشن‌کردن اشتراک، ابتدا آن را در <a href="' . esc_url(admin_url('admin.php?page=popcorn-settings&tab=account')) . '">تب حساب کاربری</a> فعال کنید.</p>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="popcorn_subscription_save_options">';
    wp_nonce_field('popcorn_subscription_save_options');
    echo '<table class="form-table"><tbody><tr><th>فعال‌سازی</th><td><label><input type="checkbox" name="subscription[enabled]" value="1"' . checked($options['enabled'], 1, false) . (!function_exists('popcorn_auth_enabled') || !popcorn_auth_enabled() ? ' disabled' : '') . '> فعال‌کردن سیستم اشتراک و خرید پلن</label></td></tr>';
    echo '<tr><th><label for="pc-sub-access">دسترسی دانلود</label></th><td><select id="pc-sub-access" name="subscription[access_mode]"><option value="free"' . selected($options['access_mode'], 'free', false) . '>آزاد؛ کنترل اشتراک روی لینک‌ها اعمال نشود</option><option value="downloads"' . selected($options['access_mode'], 'downloads', false) . '>فقط کاربران دارای اشتراک فعال لینک‌ها را ببینند</option></select><p class="description">اگر لینک‌ها را اسکریپت دیگری کنترل می‌کند، «آزاد» را انتخاب کنید. با خاموش‌کردن کل سیستم، این محدودیت هم خاموش می‌شود.</p></td></tr>';
    echo '<tr><th><label for="pc-sub-merchant">شناسهٔ پذیرنده زرین‌پال</label></th><td><input id="pc-sub-merchant" type="text" class="regular-text" name="subscription[merchant_id]" value="' . esc_attr($options['merchant_id']) . '" dir="ltr" autocomplete="off"><p class="description">برای خرید آنلاین لازم است. مبلغ پلن به تومان ثبت و برای پرداخت به ریال تبدیل می‌شود. بازگشت پرداخت به نشانی عمومی HTTPS سایت نیاز دارد.</p></td></tr></tbody></table>';
    submit_button('ذخیرهٔ تنظیمات');
    echo '</form></div>';
    echo '<div class="pc-admin__card"><h2>' . ($edit ? 'ویرایش پلن' : 'افزودن پلن') . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="popcorn_subscription_save_plan"><input type="hidden" name="plan_id" value="' . absint($edit->id ?? 0) . '">';
    wp_nonce_field('popcorn_subscription_save_plan');
    echo '<table class="form-table"><tbody>';
    $fields = [['name', 'نام پلن', 'text', $edit->name ?? ''], ['duration_days', 'مدت (روز)', 'number', $edit->duration_days ?? '30'], ['price_toman', 'قیمت (تومان)', 'number', $edit->price_toman ?? ''], ['color', 'رنگ پلن', 'color', $edit->color ?? '#6C52EE']];
    foreach ($fields as [$key, $label, $type, $value]) {
        $min = $key === 'duration_days' ? ' min="1" max="3650"' : ($key === 'price_toman' || $key === 'image_id' ? ' min="0"' : '');
        echo '<tr><th><label for="pc-plan-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input id="pc-plan-' . esc_attr($key) . '" type="' . esc_attr($type) . '" name="plan[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="regular-text"' . $min . ($key === 'name' || $key === 'duration_days' || $key === 'price_toman' ? ' required' : '') . '></td></tr>';
    }
    $image_id = absint($edit->image_id ?? 0);
    echo '<tr><th>تصویر پلن</th><td><div class="pc-sub-admin-image"><input type="hidden" name="plan[image_id]" value="' . $image_id . '"><div data-image-preview>' . ($image_id ? wp_get_attachment_image($image_id, 'thumbnail') : '') . '</div><button type="button" class="button" data-select-image>انتخاب تصویر</button> <button type="button" class="button" data-remove-image>حذف تصویر</button></div></td></tr>';
    echo '<tr><th><label for="pc-plan-features">ویژگی‌ها</label></th><td><textarea id="pc-plan-features" name="plan[features]" rows="4" class="large-text" placeholder="هر ویژگی در یک سطر">' . esc_textarea($edit ? implode("\n", json_decode($edit->features, true) ?: []) : '') . '</textarea></td></tr>';
    echo '<tr><th><label for="pc-plan-description">توضیحات</label></th><td><textarea id="pc-plan-description" name="plan[description]" rows="3" class="large-text">' . esc_textarea($edit->description ?? '') . '</textarea></td></tr>';
    echo '<tr><th>وضعیت</th><td><label><input type="checkbox" name="plan[is_active]" value="1"' . checked($edit->is_active ?? 1, 1, false) . '> فعال</label> <label><input type="checkbox" name="plan[is_featured]" value="1"' . checked($edit->is_featured ?? 0, 1, false) . '> پیشنهادی</label></td></tr></tbody></table>';
    submit_button($edit ? 'ذخیرهٔ پلن' : 'افزودن پلن');
    echo '</form></div>';
    echo '<div class="pc-admin__card"><h2>اشتراک دستی کاربر</h2><p>برای هدیه یا رسیدگی به مشکل پرداخت، اشتراک را به یک حساب موجود بدهید. این کار پرداخت ثبت نمی‌کند.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="popcorn_subscription_grant">';
    wp_nonce_field('popcorn_subscription_grant');
    echo '<table class="form-table"><tr><th><label for="pc-sub-user">شناسهٔ کاربر یا ایمیل</label></th><td><input id="pc-sub-user" name="user" class="regular-text" required></td></tr><tr><th><label for="pc-sub-plan">پلن</label></th><td><select id="pc-sub-plan" name="plan_id" required>';
    foreach ($plans as $plan) {
        echo '<option value="' . absint($plan->id) . '">' . esc_html($plan->name) . '</option>';
    }
    echo '</select></td></tr><tr><th><label for="pc-sub-note">یادداشت مدیر</label></th><td><textarea id="pc-sub-note" name="note" class="large-text" rows="2"></textarea></td></tr></table>';
    submit_button('ثبت اشتراک دستی', 'secondary');
    echo '</form></div>';
    echo '</div><div class="pc-sub-admin__side">';
    echo '<div class="pc-admin__card"><h2>پلن‌های ثبت‌شده</h2><table class="widefat striped pc-admin__table"><thead><tr><th>نام</th><th>مدت</th><th>قیمت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>';
    foreach ($plans as $plan) {
        echo '<tr><td>' . esc_html($plan->name) . '</td><td>' . esc_html(number_format_i18n($plan->duration_days)) . ' روز</td><td>' . esc_html(number_format_i18n($plan->price_toman)) . ' تومان</td><td>' . ($plan->is_active ? 'فعال' : 'غیرفعال') . '</td><td><a class="button" href="' . esc_url(add_query_arg('edit_plan', (int) $plan->id, popcorn_subscription_admin_url())) . '">ویرایش</a></td></tr>';
    }
    if (!$plans) {
        echo '<tr><td colspan="5">هنوز پلنی ثبت نشده است.</td></tr>';
    }
    echo '</tbody></table></div>';
    $recent = $wpdb->get_results('SELECT s.*, p.name AS plan_name, u.display_name FROM ' . popcorn_subscription_table('subscription') . ' s LEFT JOIN ' . popcorn_subscription_table('plan') . ' p ON p.id = s.plan_id LEFT JOIN ' . $wpdb->users . ' u ON u.ID = s.user_id ORDER BY s.id DESC LIMIT 30');
    echo '<div class="pc-admin__card"><h2>اشتراک‌های اخیر</h2><table class="widefat striped pc-admin__table"><thead><tr><th>کاربر</th><th>پلن</th><th>پایان</th><th>وضعیت</th><th>منبع</th><th>عملیات</th></tr></thead><tbody>';
    foreach ($recent as $row) {
        $status = !$row->is_active ? 'غیرفعال' : (strtotime($row->ends_at . ' UTC') > time() ? 'فعال' : 'منقضی');
        echo '<tr><td>' . esc_html($row->display_name ?: '#' . $row->user_id) . '</td><td>' . esc_html($row->plan_name ?: '—') . '</td><td>' . esc_html(get_date_from_gmt($row->ends_at, 'Y/m/d H:i')) . '</td><td>' . esc_html($status) . '</td><td>' . ($row->payment_id ? 'خرید' : 'مدیر') . '</td><td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="popcorn_subscription_toggle"><input type="hidden" name="subscription_id" value="' . absint($row->id) . '">' . wp_nonce_field('popcorn_subscription_toggle_' . $row->id, '_pc_sub_toggle', true, false) . '<button class="button" type="submit">' . ($row->is_active ? 'غیرفعال کردن' : 'فعال کردن') . '</button></form></td></tr>';
    }
    if (!$recent) {
        echo '<tr><td colspan="6">هنوز اشتراکی ثبت نشده است.</td></tr>';
    }
    echo '</tbody></table></div>';
    $payments = $wpdb->get_results('SELECT x.*, u.display_name FROM ' . popcorn_subscription_table('payment') . ' x LEFT JOIN ' . $wpdb->users . ' u ON u.ID = x.user_id ORDER BY x.id DESC LIMIT 30');
    echo '<div class="pc-admin__card"><h2>پرداخت‌های اخیر</h2><table class="widefat striped pc-admin__table"><thead><tr><th>کاربر</th><th>مبلغ</th><th>وضعیت</th><th>کد پیگیری</th></tr></thead><tbody>';
    foreach ($payments as $row) {
        $status = ['pending' => 'در انتظار', 'paid' => 'موفق', 'failed' => 'ناموفق', 'cancelled' => 'لغوشده'];
        echo '<tr><td>' . esc_html($row->display_name ?: '#' . $row->user_id) . '</td><td>' . esc_html(number_format_i18n((int) $row->amount_rial / 10)) . ' تومان</td><td>' . esc_html($status[$row->status] ?? $row->status) . '</td><td dir="ltr">' . esc_html($row->ref_id ?: '—') . '</td></tr>';
    }
    if (!$payments) {
        echo '<tr><td colspan="4">هنوز پرداختی ثبت نشده است.</td></tr>';
    }
    echo '</tbody></table></div></div></div>';
}
add_action('popcorn_settings_tab_subscription', 'popcorn_subscription_admin_page');

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_popcorn-settings' || sanitize_key(wp_unslash($_GET['tab'] ?? '')) !== 'subscription') {
        return;
    }
    wp_enqueue_media();
    $base = dirname(__DIR__);
    wp_enqueue_style('popcorn-subscription-admin', plugin_dir_url($base . '/popcorn-subscriptions.php') . 'assets/css/admin.css', [], filemtime($base . '/assets/css/admin.css'));
    wp_enqueue_script('popcorn-subscription-admin', plugin_dir_url($base . '/popcorn-subscriptions.php') . 'assets/js/admin.js', ['media-views'], filemtime($base . '/assets/js/admin.js'), true);
});

add_action('admin_post_popcorn_subscription_save_options', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    check_admin_referer('popcorn_subscription_save_options');
    $input = isset($_POST['subscription']) && is_array($_POST['subscription']) ? wp_unslash($_POST['subscription']) : [];
    $old = popcorn_subscription_options();
    $enabled = !empty($input['enabled']);
    $auth_ready = function_exists('popcorn_auth_enabled') && popcorn_auth_enabled();
    $options = ['enabled' => $enabled && $auth_ready ? 1 : 0, 'access_mode' => ($input['access_mode'] ?? '') === 'downloads' ? 'downloads' : 'free', 'merchant_id' => sanitize_text_field($input['merchant_id'] ?? ''), 'page_id' => absint($old['page_id'])];
    if ($options['enabled']) {
        $page = $options['page_id'] ? get_post($options['page_id']) : get_page_by_path('popcorn-subscription');
        if (!$page || $page->post_status !== 'publish' || !has_shortcode($page->post_content, 'popcorn_subscription')) {
            $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'خرید اشتراک', 'post_name' => 'popcorn-subscription', 'post_content' => '[popcorn_subscription]', 'comment_status' => 'closed'], true);
            if (!is_wp_error($id)) {
                $options['page_id'] = $id;
            }
        } else {
            $options['page_id'] = $page->ID;
        }
    }
    update_option('popcorn_subscription_options', $options, false);
    wp_safe_redirect(popcorn_subscription_admin_url($enabled && !$auth_ready ? 'auth_required' : 'saved'));
    exit;
});

add_action('admin_post_popcorn_subscription_save_plan', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    check_admin_referer('popcorn_subscription_save_plan');
    global $wpdb;
    $input = isset($_POST['plan']) && is_array($_POST['plan']) ? wp_unslash($_POST['plan']) : [];
    $name = sanitize_text_field($input['name'] ?? '');
    $days = absint($input['duration_days'] ?? 0);
    $price = absint($input['price_toman'] ?? 0);
    if ($name === '' || !$days || $days > 3650 || $price < 1000 || $price > 1000000000) {
        wp_safe_redirect(popcorn_subscription_admin_url('invalid'));
        exit;
    }
    $features = array_values(array_slice(array_filter(array_map('sanitize_text_field', preg_split('/\r\n|\r|\n/', (string) ($input['features'] ?? '')))), 0, 20));
    $color = sanitize_hex_color($input['color'] ?? '') ?: '#6C52EE';
    $image_id = absint($input['image_id'] ?? 0);
    $data = ['name' => $name, 'duration_days' => $days, 'price_toman' => $price, 'features' => wp_json_encode($features, JSON_UNESCAPED_UNICODE), 'color' => $color, 'image_id' => $image_id && wp_attachment_is_image($image_id) ? $image_id : 0, 'description' => sanitize_textarea_field($input['description'] ?? ''), 'is_active' => empty($input['is_active']) ? 0 : 1, 'is_featured' => empty($input['is_featured']) ? 0 : 1];
    $id = absint($_POST['plan_id'] ?? 0);
    $table = popcorn_subscription_table('plan');
    $result = $id ? $wpdb->update($table, $data, ['id' => $id]) : $wpdb->insert($table, $data + ['created_at' => current_time('mysql', true)]);
    wp_safe_redirect(popcorn_subscription_admin_url($result === false ? 'error' : 'plan_saved'));
    exit;
});

add_action('admin_post_popcorn_subscription_grant', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    check_admin_referer('popcorn_subscription_grant');
    global $wpdb;
    $value = sanitize_text_field(wp_unslash($_POST['user'] ?? ''));
    $user = ctype_digit($value) ? get_user_by('id', (int) $value) : get_user_by('email', $value);
    $plan_id = absint($_POST['plan_id'] ?? 0);
    $plan = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . popcorn_subscription_table('plan') . ' WHERE id = %d', $plan_id));
    if (!$user || !$plan) {
        wp_safe_redirect(popcorn_subscription_admin_url('invalid'));
        exit;
    }
    $note = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
    $result = popcorn_subscription_grant($user->ID, $plan_id, $plan->duration_days, null, $note);
    wp_safe_redirect(popcorn_subscription_admin_url($result ? 'granted' : 'error'));
    exit;
});

add_action('admin_post_popcorn_subscription_toggle', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
    }
    $id = absint($_POST['subscription_id'] ?? 0);
    check_admin_referer('popcorn_subscription_toggle_' . $id, '_pc_sub_toggle');
    global $wpdb;
    $table = popcorn_subscription_table('subscription');
    $row = $wpdb->get_row($wpdb->prepare("SELECT id, is_active FROM $table WHERE id = %d", $id));
    $updated = $row ? $wpdb->update($table, ['is_active' => $row->is_active ? 0 : 1], ['id' => $id], ['%d'], ['%d']) : false;
    wp_safe_redirect(popcorn_subscription_admin_url($updated === false ? 'error' : 'subscription_updated'));
    exit;
});
