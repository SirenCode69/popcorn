<?php
defined('ABSPATH') || exit;

function popcorn_bundled_plugins() {
    return [
        'popcorn-core' => ['هستهٔ پاپکورن', 'برای فعال شدن کاتالوگ فیلم و سریال، دانلود و پخش ضروری است.'],
        'popcorn-auth' => ['حساب کاربری پاپکورن', 'اختیاری برای ورود، ثبت‌نام و پنل کاربری.'],
        'popcorn-subscriptions' => ['اشتراک پاپکورن', 'اختیاری برای فروش اشتراک و محدود کردن دانلود. به حساب کاربری پاپکورن نیاز دارد.'],
    ];
}

add_action('admin_menu', function () {
    add_theme_page('نصب افزونهٔ پاپکورن', 'نصب افزونهٔ پاپکورن', 'install_plugins', 'popcorn-install-plugin', 'popcorn_install_bundled_plugin');
});

function popcorn_install_bundled_plugin() {
    if (!current_user_can('install_plugins')) {
        wp_die('برای نصب افزونه باید دسترسی نصب افزونه داشته باشید.', '', ['response' => 403]);
    }

    $slug = sanitize_key(wp_unslash($_GET['plugin'] ?? ''));
    $plugins = popcorn_bundled_plugins();
    if (!isset($plugins[$slug])) {
        echo '<div class="wrap"><h1>افزونه‌های همراه پاپکورن</h1><p>از اعلان صفحهٔ پوسته‌ها افزونهٔ موردنظر را انتخاب کنید.</p><a href="' . esc_url(admin_url('themes.php')) . '">بازگشت به پوسته‌ها</a></div>';
        return;
    }
    check_admin_referer("popcorn-install-$slug");

    $file = "$slug/$slug.php";
    if (isset(get_plugins()[$file])) {
        echo '<div class="wrap"><h1>افزونه از قبل نصب شده است</h1><a href="' . esc_url(admin_url('plugins.php')) . '">رفتن به افزونه‌ها</a></div>';
        return;
    }

    $package = get_template_directory() . "/plugins/$slug.zip";
    if (!is_readable($package)) {
        wp_die('فایل ZIP افزونه در پوشهٔ قالب پیدا نشد.', '', ['back_link' => true]);
    }

    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    $url = add_query_arg(['page' => 'popcorn-install-plugin', 'plugin' => $slug], admin_url('themes.php'));
    $skin = new Plugin_Installer_Skin([
        'type' => 'bundled',
        'title' => 'نصب ' . $plugins[$slug][0],
        'url' => $url,
        'nonce' => "popcorn-install-$slug",
    ]);
    $upgrader = new Plugin_Upgrader($skin);
    $upgrader->install($package, ['clear_update_cache' => false]);
}

add_action('admin_notices', function () {
    if (!current_user_can('activate_plugins') && !current_user_can('install_plugins')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, ['dashboard', 'themes', 'plugins', 'toplevel_page_popcorn-settings'], true)) {
        return;
    }

    $pending = [];
    foreach (popcorn_bundled_plugins() as $slug => $plugin) {
        if (!is_plugin_active("$slug/$slug.php")) {
            $pending[$slug] = $plugin;
        }
    }

    $needs_core = isset($pending['popcorn-core']);
    if (!$pending || (!$needs_core && $screen->id === 'dashboard')) {
        return;
    }

    $installed = get_plugins();
    $can_install = current_user_can('install_plugins');
    ?>
    <div class="notice <?php echo $needs_core ? 'notice-warning' : 'notice-info'; ?>" id="popcorn-plugin-notice">
        <p><strong>افزونه‌های همراه پاپکورن</strong></p>
        <ul class="ul-disc">
            <?php foreach ($pending as $slug => [$name, $description]) :
                $file = "$slug/$slug.php";
                ?>
                <li>
                    <strong><?php echo esc_html($name); ?></strong>
                    <span><?php echo esc_html($description); ?></span>
                    <?php if (isset($installed[$file])) : ?>
                        <?php if (current_user_can('activate_plugin', $file)) :
                            $activate = add_query_arg(['action' => 'activate', 'plugin' => $file], admin_url('plugins.php'));
                            ?>
                            <a class="button button-small" href="<?php echo esc_url(wp_nonce_url($activate, "activate-plugin_$file")); ?>">فعال‌سازی</a>
                        <?php endif; ?>
                    <?php elseif ($can_install && is_readable(get_template_directory() . "/plugins/$slug.zip")) :
                        $install = add_query_arg(['page' => 'popcorn-install-plugin', 'plugin' => $slug], admin_url('themes.php'));
                        ?>
                        <a class="button button-small" href="<?php echo esc_url(wp_nonce_url($install, "popcorn-install-$slug")); ?>">نصب از بستهٔ قالب</a>
                    <?php elseif ($can_install) : ?>
                        <a href="https://github.com/SirenCode69/popcorn/releases">دریافت ZIP از صفحهٔ انتشار</a>
                    <?php else : ?>
                        <span>نصب به دسترسی نصب افزونه نیاز دارد.</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
});
