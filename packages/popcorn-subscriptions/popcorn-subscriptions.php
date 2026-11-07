<?php
/*
Plugin Name: اشتراک پاپکورن
Description: ساخت پلن، فروش اشتراک و تعیین دسترسی مشترکان به لینک‌های دانلود. برای راه‌اندازی به حساب کاربری پاپکورن نیاز دارد.
Version: 1.0.0
Author: SirenCode69
Requires PHP: 8.0
License: GPL-2.0-or-later
Update URI: https://github.com/SirenCode69/popcorn
*/
defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/purchase.php';

register_activation_hook(__FILE__, 'popcorn_subscription_install');
add_action('plugins_loaded', 'popcorn_subscription_upgrade');
