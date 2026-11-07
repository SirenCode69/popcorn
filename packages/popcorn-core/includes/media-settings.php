<?php
defined('ABSPATH') || exit;

function popcorn_core_media_defaults() {
    return [
        'webp_quality' => 82,
        'sizes' => [
            ['name' => 'پوستر', 'slug' => 'popcorn_poster', 'width' => 300, 'height' => 450, 'crop' => 1],
            ['name' => 'جست‌وجوی زنده و فهرست آثار', 'slug' => 'popcorn_compact', 'width' => 45, 'height' => 58, 'crop' => 1],
            ['name' => 'کارت هنرمندان', 'slug' => 'popcorn_person', 'width' => 200, 'height' => 250, 'crop' => 0],
        ],
    ];
}

function popcorn_core_media_options() {
    $saved = get_option('popcorn_media_options', false);
    return is_array($saved) && isset($saved['sizes'], $saved['webp_quality']) ? $saved : popcorn_core_media_defaults();
}

function popcorn_core_media_quality() {
    return (int) popcorn_core_media_options()['webp_quality'];
}

function popcorn_core_media_initialize() {
    if (get_option('popcorn_media_options', false) === false) {
        add_option('popcorn_media_options', popcorn_core_media_defaults(), '', false);
    }
    if (get_option('popcorn_media_defaults_version') !== '4') {
        $options = popcorn_core_media_options();
        foreach (array_slice(popcorn_core_media_defaults()['sizes'], 1) as $size) {
            if (!in_array($size['slug'], array_column($options['sizes'], 'slug'), true)) {
                $options['sizes'][] = $size;
            }
        }
        foreach ($options['sizes'] as &$size) {
            if ($size['slug'] === 'popcorn_person' && (int) $size['width'] === 200 && (int) $size['height'] === 300 && !empty($size['crop'])) {
                $size['height'] = 250;
                $size['crop'] = 0;
            }
        }
        unset($size);
        update_option('popcorn_media_options', $options, false);
        update_option('popcorn_media_defaults_version', '4', false);
    }
}
add_action('after_setup_theme', 'popcorn_core_media_initialize', 19);

function popcorn_core_register_media_sizes() {
    foreach (popcorn_core_media_options()['sizes'] as $size) {
        add_image_size($size['slug'], (int) $size['width'], (int) $size['height'], !empty($size['crop']));
    }
}
add_action('after_setup_theme', 'popcorn_core_register_media_sizes', 20);

add_filter('image_size_names_choose', function ($names) {
    foreach (popcorn_core_media_options()['sizes'] as $size) {
        $names[$size['slug']] = $size['name'] . ' (' . $size['width'] . '×' . $size['height'] . ')';
    }
    return $names;
});

function popcorn_core_media_validate($input) {
    $quality = filter_var($input['webp_quality'] ?? null, FILTER_VALIDATE_INT);
    if ($quality === false || $quality < 1 || $quality > 100) {
        return new WP_Error('quality', 'کیفیت WebP باید عددی بین ۱ تا ۱۰۰ باشد.');
    }
    $sizes = [];
    $used = [];
    if (!isset($input['sizes']) || !is_array($input['sizes'])) {
        return new WP_Error('size', 'اطلاعات اندازه‌ها معتبر نیست.');
    }
    foreach ($input['sizes'] ?? [] as $size) {
        if (!is_array($size)) {
            return new WP_Error('size', 'اطلاعات یکی از اندازه‌ها معتبر نیست.');
        }
        if (!is_scalar($size['name'] ?? null) || !is_scalar($size['slug'] ?? null)) {
            return new WP_Error('size', 'نام یا شناسهٔ اندازه معتبر نیست.');
        }
        $name = sanitize_text_field($size['name']);
        $slug = sanitize_key($size['slug']);
        $width = filter_var($size['width'] ?? null, FILTER_VALIDATE_INT);
        $height = filter_var($size['height'] ?? null, FILTER_VALIDATE_INT);
        if ($name === '' || !preg_match('/^popcorn_[a-z0-9_-]+$/', $slug) || $width === false || $height === false || $width < 1 || $height < 1 || $width > 10000 || $height > 10000) {
            return new WP_Error('size', 'برای هر اندازه، نام، شناسهٔ popcorn_ و عرض و ارتفاع ۱ تا ۱۰۰۰۰ پیکسل را وارد کنید.');
        }
        if (isset($used[$slug])) {
            return new WP_Error('duplicate', 'شناسهٔ اندازه‌ها باید یکتا باشد.');
        }
        $used[$slug] = true;
        $sizes[] = ['name' => $name, 'slug' => $slug, 'width' => $width, 'height' => $height, 'crop' => !empty($size['crop']) ? 1 : 0];
    }
    return ['webp_quality' => $quality, 'sizes' => $sizes];
}

function popcorn_core_media_size_row($index, $size) {
    $name = 'popcorn_sizes[' . $index . ']';
    echo '<div class="pc-media-size" data-media-size><div class="pc-media-size__fields"><label>نام اندازه<input type="text" name="' . esc_attr($name) . '[name]" value="' . esc_attr($size['name'] ?? '') . '" required maxlength="80" placeholder="مثلاً پوستر کارت"></label><label>شناسه<input type="text" name="' . esc_attr($name) . '[slug]" value="' . esc_attr($size['slug'] ?? '') . '" required pattern="popcorn_[a-z0-9_-]+" dir="ltr" placeholder="popcorn_poster"></label><label>عرض (پیکسل)<input type="number" name="' . esc_attr($name) . '[width]" value="' . esc_attr($size['width'] ?? '') . '" required min="1" max="10000"></label><label>ارتفاع (پیکسل)<input type="number" name="' . esc_attr($name) . '[height]" value="' . esc_attr($size['height'] ?? '') . '" required min="1" max="10000"></label></div><div class="pc-media-size__actions"><label><input type="checkbox" name="' . esc_attr($name) . '[crop]" value="1"' . checked(!empty($size['crop']), true, false) . '> برش به اندازهٔ دقیق</label><button type="button" class="button pc-media-size__remove">حذف اندازه</button></div></div>';
}

function popcorn_core_media_settings_tab() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $message = isset($_GET['popcorn_message']) ? sanitize_key(wp_unslash($_GET['popcorn_message'])) : '';
    if ($message === 'saved') {
        echo '<div class="notice notice-success"><p>تنظیمات مدیا ذخیره شد.</p></div>';
    } elseif ($message !== '') {
        $messages = ['quality' => 'کیفیت WebP باید عددی بین ۱ تا ۱۰۰ باشد.', 'size' => 'نام، شناسه و ابعاد اندازه‌ها را بررسی کنید.', 'duplicate' => 'شناسهٔ اندازه‌ها باید یکتا باشد.'];
        echo '<div class="notice notice-error"><p>' . esc_html($messages[$message] ?? 'ذخیرهٔ تنظیمات مدیا ناموفق بود.') . '</p></div>';
    }
    $options = popcorn_core_media_options();
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="popcorn-media-settings"><input type="hidden" name="action" value="popcorn_save_media_options">';
    wp_nonce_field('popcorn_media_options');
    echo '<div class="pc-admin__card"><h2>اندازه‌های تصویر</h2><p>برای تصاویر تازه، هر تعداد اندازهٔ دلخواه بسازید. با فعال بودن «برش»، تصویر به ابعاد دقیق می‌رسد؛ بدون آن، نسبت تصویر حفظ می‌شود و داخل این محدوده تغییر اندازه می‌یابد.</p><div id="popcorn-media-sizes">';
    foreach ($options['sizes'] as $index => $size) {
        popcorn_core_media_size_row($index, $size);
    }
    echo '</div><button type="button" class="button" id="popcorn-add-media-size">افزودن اندازه</button><template id="popcorn-media-size-template">';
    popcorn_core_media_size_row('__INDEX__', []);
    echo '</template></div><div class="pc-admin__card"><h2>تبدیل به WebP</h2><p>این کیفیت برای تصاویر WebP قالب و اندازه‌های سفارشی استفاده می‌شود.</p><table class="form-table"><tr><th><label for="popcorn-webp-quality">کیفیت WebP</label></th><td><input id="popcorn-webp-quality" type="number" name="webp_quality" value="' . esc_attr($options['webp_quality']) . '" min="1" max="100" required><p class="description">۱ کمترین حجم و ۱۰۰ بیشترین کیفیت است. مقدار پیش‌فرض ۸۲ است.</p></td></tr></table></div>';
    submit_button('ذخیرهٔ تنظیمات مدیا');
    echo '</form><div class="pc-admin__card"><h2>تصاویر موجود</h2><p>پس از ذخیرهٔ اندازه‌ها، با این دکمه اندازه‌های سفارشی تصاویر قبلی ساخته و نسخهٔ WebP آن‌ها با کیفیت فعلی آماده می‌شود.</p><button type="button" class="button" id="popcorn-regenerate-media" data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('popcorn_regenerate_media')) . '">ساخت اندازه‌ها برای تصاویر موجود</button><p id="popcorn-regenerate-status" role="status" aria-live="polite"></p></div>';
}
add_action('popcorn_settings_tab_media', 'popcorn_core_media_settings_tab');

add_action('wp_ajax_popcorn_regenerate_media', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'دسترسی مجاز نیست.'], 403);
    }
    check_ajax_referer('popcorn_regenerate_media', 'nonce');
    global $wpdb;
    $cursor = isset($_POST['cursor']) ? absint($_POST['cursor']) : 0;
    $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s AND ID > %d ORDER BY ID ASC LIMIT 5", 'attachment', 'image/%', $cursor));
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $processed = 0;
    foreach ($ids as $id) {
        $cursor = (int) $id;
        $parent = wp_get_post_parent_id($id);
        if (($parent && get_post_status($parent) !== 'publish') || !wp_attachment_is_image($id)) {
            continue;
        }
        $previous_metadata = null;
        $metadata = wp_get_attachment_metadata($id);
        if (is_array($metadata)) {
            $previous_metadata = $metadata;
            foreach (popcorn_core_media_options()['sizes'] as $size) {
                unset($metadata['sizes'][$size['slug']]);
            }
            wp_update_attachment_metadata($id, $metadata);
        }
        $updated = wp_update_image_subsizes($id);
        if (!is_wp_error($updated)) {
            popcorn_core_prepare_webp($id);
            $processed++;
        } elseif ($previous_metadata !== null) {
            wp_update_attachment_metadata($id, $previous_metadata);
        }
    }
    wp_send_json_success(['cursor' => $cursor, 'processed' => $processed, 'done' => count($ids) < 5]);
});

add_action('admin_enqueue_scripts', function ($hook) {
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
    if ($hook !== 'toplevel_page_popcorn-settings' || $tab !== 'media') {
        return;
    }
    $root = dirname(__DIR__);
    $url = plugin_dir_url($root . '/popcorn-core.php');
    wp_enqueue_style('popcorn-media-settings', $url . 'assets/css/media-settings.css', [], filemtime($root . '/assets/css/media-settings.css'));
    wp_enqueue_script('popcorn-media-settings', $url . 'assets/js/media-settings.js', [], filemtime($root . '/assets/js/media-settings.js'), true);
});

add_action('admin_post_popcorn_save_media_options', function () {
    current_user_can('manage_options') || wp_die('دسترسی مجاز نیست.');
    check_admin_referer('popcorn_media_options');
    $input = ['webp_quality' => wp_unslash($_POST['webp_quality'] ?? ''), 'sizes' => wp_unslash($_POST['popcorn_sizes'] ?? [])];
    $result = popcorn_core_media_validate($input);
    $message = is_wp_error($result) ? $result->get_error_code() : 'saved';
    if (!is_wp_error($result)) {
        update_option('popcorn_media_options', $result, false);
    }
    wp_safe_redirect(add_query_arg(['page' => 'popcorn-settings', 'tab' => 'media', 'popcorn_message' => $message], admin_url('admin.php')));
    exit;
});
