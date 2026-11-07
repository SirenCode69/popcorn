<?php
defined('ABSPATH') || exit;

function popcorn_subscription_gateway_request($endpoint, $body) {
    $response = wp_safe_remote_post('https://payment.zarinpal.com/pg/v4/payment/' . $endpoint . '.json', ['timeout' => 20, 'sslverify' => true, 'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'], 'body' => wp_json_encode($body)]);
    if (is_wp_error($response)) {
        return $response;
    }
    if (wp_remote_retrieve_response_code($response) !== 200) {
        return new WP_Error('gateway_http', 'درگاه پرداخت پاسخ معتبر نداد.');
    }
    $json = json_decode(wp_remote_retrieve_body($response), true);
    return is_array($json) && isset($json['data']) && is_array($json['data']) ? $json['data'] : new WP_Error('gateway_response', 'پاسخ درگاه پرداخت معتبر نیست.');
}

function popcorn_subscription_payment_result_url($result) {
    $url = popcorn_auth_account_url() ?: popcorn_subscription_url();
    return add_query_arg('popcorn_payment', $result, $url ?: home_url('/'));
}

function popcorn_subscription_checkout_ready() {
    $options = popcorn_subscription_options();
    return popcorn_subscription_enabled() && preg_match('/^[a-f0-9-]{36}$/i', $options['merchant_id']) && wp_parse_url(home_url('/'), PHP_URL_SCHEME) === 'https';
}

function popcorn_subscription_plans_html() {
    if (!popcorn_subscription_enabled()) {
        return '';
    }
    global $wpdb;
    $plans = $wpdb->get_results('SELECT * FROM ' . popcorn_subscription_table('plan') . ' WHERE is_active = 1 ORDER BY is_featured DESC, price_toman ASC');
    $active = popcorn_subscription_active();
    $html = '<section class="pc-subscription"><h2>خرید اشتراک</h2>';
    if ($active) {
        $html .= '<p class="pc-subscription__status">اشتراک «' . esc_html($active->plan_name) . '» شما تا ' . esc_html(get_date_from_gmt($active->ends_at, 'Y/m/d H:i')) . ' فعال است.</p>';
    }
    if (!$plans) {
        return $html . '<p>فعلاً پلن فعالی وجود ندارد.</p></section>';
    }
    if (!is_user_logged_in()) {
        $html .= '<p>برای خرید اشتراک، ابتدا <a href="' . esc_url(popcorn_auth_login_url(popcorn_subscription_url())) . '">وارد حساب کاربری شوید</a>.</p>';
    } elseif (!popcorn_subscription_checkout_ready()) {
        $html .= '<p>خرید آنلاین هنوز برای این سایت آماده نیست.</p>';
    }
    $html .= '<div class="pc-subscription__plans">';
    foreach ($plans as $plan) {
        $features = json_decode($plan->features, true);
        $html .= '<article class="pc-subscription__plan" style="--pc-sub-color:' . esc_attr(sanitize_hex_color($plan->color) ?: '#6C52EE') . '">';
        if ($plan->image_id) {
            $html .= function_exists('popcorn_image') ? popcorn_image($plan->image_id, 'medium', ['alt' => $plan->name]) : wp_get_attachment_image($plan->image_id, 'medium', false, ['loading' => 'lazy']);
        }
        if ($plan->is_featured) {
            $html .= '<span class="pc-subscription__featured">پیشنهادی</span>';
        }
        $html .= '<h3>' . esc_html($plan->name) . '</h3><p><strong>' . esc_html(number_format_i18n($plan->price_toman)) . ' تومان</strong> · ' . esc_html(number_format_i18n($plan->duration_days)) . ' روز</p>';
        if ($plan->description) {
            $html .= '<p>' . esc_html($plan->description) . '</p>';
        }
        if ($features) {
            $html .= '<ul>';
            foreach ($features as $feature) {
                $html .= '<li>' . esc_html($feature) . '</li>';
            }
            $html .= '</ul>';
        }
        if (is_user_logged_in() && popcorn_subscription_checkout_ready()) {
            $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="popcorn_subscription_buy"><input type="hidden" name="plan_id" value="' . absint($plan->id) . '">' . wp_nonce_field('popcorn_subscription_buy_' . $plan->id, '_pc_sub_nonce', true, false) . '<button type="submit">خرید اشتراک</button></form>';
        }
        $html .= '</article>';
    }
    return $html . '</div></section>';
}
add_shortcode('popcorn_subscription', 'popcorn_subscription_plans_html');

add_filter('popcorn_auth_account_html', function ($html) {
    if (!popcorn_subscription_enabled()) {
        return $html;
    }
    $active = popcorn_subscription_active();
    $result = sanitize_key(wp_unslash($_GET['popcorn_payment'] ?? ''));
    $messages = ['success' => 'پرداخت تأیید شد و اشتراک شما فعال است.', 'cancelled' => 'پرداخت لغو شد.', 'failed' => 'پرداخت تأیید نشد. در صورت کسر وجه، وضعیت را از پشتیبانی پیگیری کنید.'];
    $html .= '<section class="pc-subscription pc-subscription--account"><h2>اشتراک من</h2>';
    if (isset($messages[$result])) {
        $html .= '<p role="status">' . esc_html($messages[$result]) . '</p>';
    }
    $html .= $active ? '<p>پلن «' . esc_html($active->plan_name) . '» تا ' . esc_html(get_date_from_gmt($active->ends_at, 'Y/m/d H:i')) . ' فعال است.</p>' : '<p>اشتراک فعالی ندارید.</p>';
    $url = popcorn_subscription_url();
    return $html . ($url ? '<a href="' . esc_url($url) . '">دیدن پلن‌ها و تمدید اشتراک</a>' : '') . '</section>';
});

add_action('wp_enqueue_scripts', function () {
    if (!popcorn_subscription_enabled() || !is_singular('page')) {
        return;
    }
    $post = get_queried_object();
    $content = $post instanceof WP_Post ? $post->post_content : '';
    $options = popcorn_subscription_options();
    $auth = popcorn_auth_options();
    if (!is_page($options['page_id']) && !is_page($auth['account_page_id']) && !has_shortcode($content, 'popcorn_subscription') && !has_shortcode($content, 'popcorn_account')) {
        return;
    }
    $file = dirname(__DIR__) . '/assets/css/subscription.css';
    wp_enqueue_style('popcorn-subscription', plugin_dir_url(dirname(__DIR__) . '/popcorn-subscriptions.php') . 'assets/css/subscription.css', [], filemtime($file));
});

add_action('admin_post_popcorn_subscription_buy', function () {
    if (!is_user_logged_in() || !popcorn_subscription_checkout_ready()) {
        wp_die('خرید اشتراک در دسترس نیست.', '', ['response' => 403]);
    }
    $plan_id = absint($_POST['plan_id'] ?? 0);
    check_admin_referer('popcorn_subscription_buy_' . $plan_id, '_pc_sub_nonce');
    global $wpdb;
    $plan = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . popcorn_subscription_table('plan') . ' WHERE id = %d AND is_active = 1', $plan_id));
    if (!$plan || $plan->price_toman < 1000 || $plan->price_toman > 1000000000) {
        wp_die('پلن انتخابی معتبر نیست.', '', ['response' => 400]);
    }
    $token = bin2hex(random_bytes(32));
    $amount = (int) $plan->price_toman * 10;
    $temporary = 'pending-' . wp_generate_uuid4();
    $merchant = popcorn_subscription_options()['merchant_id'];
    $inserted = $wpdb->insert(popcorn_subscription_table('payment'), ['user_id' => get_current_user_id(), 'plan_id' => $plan_id, 'amount_rial' => $amount, 'duration_days' => (int) $plan->duration_days, 'merchant_id' => $merchant, 'authority' => $temporary, 'status' => 'pending', 'token_hash' => hash('sha256', $token), 'created_at' => current_time('mysql', true)]);
    if (!$inserted) {
        wp_die('ثبت درخواست پرداخت انجام نشد.', '', ['response' => 500]);
    }
    $payment_id = (int) $wpdb->insert_id;
    $callback = add_query_arg(['action' => 'popcorn_subscription_callback', 'order' => $payment_id, 'token' => $token], admin_url('admin-post.php', 'https'));
    $data = popcorn_subscription_gateway_request('request', ['merchant_id' => $merchant, 'amount' => $amount, 'currency' => 'IRR', 'description' => 'خرید اشتراک ' . $plan->name . ' #' . $payment_id, 'callback_url' => $callback]);
    $authority = is_array($data) ? (string) ($data['authority'] ?? '') : '';
    if (is_wp_error($data) || (int) ($data['code'] ?? 0) !== 100 || !preg_match('/^[A-Za-z0-9-]{20,80}$/', $authority)) {
        $wpdb->update(popcorn_subscription_table('payment'), ['status' => 'failed'], ['id' => $payment_id]);
        wp_safe_redirect(popcorn_subscription_payment_result_url('failed'));
        exit;
    }
    if ($wpdb->update(popcorn_subscription_table('payment'), ['authority' => $authority], ['id' => $payment_id]) === false) {
        wp_die('ذخیرهٔ شناسهٔ تراکنش انجام نشد. پرداختی انجام ندهید.', '', ['response' => 500]);
    }
    wp_redirect('https://payment.zarinpal.com/pg/StartPay/' . rawurlencode($authority), 303);
    exit;
});

function popcorn_subscription_callback() {
    global $wpdb;
    $id = absint($_GET['order'] ?? 0);
    $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
    $authority = sanitize_text_field(wp_unslash($_GET['Authority'] ?? ''));
    $status = sanitize_text_field(wp_unslash($_GET['Status'] ?? ''));
    $table = popcorn_subscription_table('payment');
    $payment = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)) : null;
    if (!$payment || !preg_match('/^[a-f0-9]{64}$/', $token) || !hash_equals($payment->token_hash, hash('sha256', $token)) || !hash_equals($payment->authority, $authority)) {
        wp_die('درخواست بازگشت پرداخت معتبر نیست.', '', ['response' => 400]);
    }
    if ($payment->status === 'paid') {
        wp_safe_redirect(popcorn_subscription_payment_result_url('success'));
        exit;
    }
    if ($status !== 'OK') {
        $wpdb->update($table, ['status' => 'cancelled'], ['id' => $id, 'status' => 'pending']);
        wp_safe_redirect(popcorn_subscription_payment_result_url('cancelled'));
        exit;
    }
    $data = popcorn_subscription_gateway_request('verify', ['merchant_id' => $payment->merchant_id, 'amount' => (int) $payment->amount_rial, 'authority' => $payment->authority]);
    if (is_wp_error($data) || !in_array((int) ($data['code'] ?? 0), [100, 101], true) || empty($data['ref_id'])) {
        wp_safe_redirect(popcorn_subscription_payment_result_url('failed'));
        exit;
    }
    $wpdb->query('START TRANSACTION');
    $locked = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d FOR UPDATE", $id));
    if ($locked && $locked->status === 'paid') {
        $wpdb->query('COMMIT');
        wp_safe_redirect(popcorn_subscription_payment_result_url('success'));
        exit;
    }
    $wpdb->get_var($wpdb->prepare('SELECT ID FROM ' . $wpdb->users . ' WHERE ID = %d FOR UPDATE', $payment->user_id));
    $end = popcorn_subscription_grant($payment->user_id, $payment->plan_id, $payment->duration_days, $id);
    $updated = $end && $wpdb->update($table, ['status' => 'paid', 'ref_id' => sanitize_text_field((string) $data['ref_id']), 'verified_at' => current_time('mysql', true)], ['id' => $id]);
    if (!$updated) {
        $wpdb->query('ROLLBACK');
        wp_safe_redirect(popcorn_subscription_payment_result_url('failed'));
        exit;
    }
    $wpdb->query('COMMIT');
    wp_safe_redirect(popcorn_subscription_payment_result_url('success'));
    exit;
}
add_action('admin_post_popcorn_subscription_callback', 'popcorn_subscription_callback');
add_action('admin_post_nopriv_popcorn_subscription_callback', 'popcorn_subscription_callback');
