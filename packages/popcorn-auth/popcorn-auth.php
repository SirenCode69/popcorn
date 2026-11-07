<?php
/*
Plugin Name: حساب کاربری پاپکورن
Description: ورود و ثبت‌نام با رمز یا کد یک‌بارمصرف ایمیل یا پیامک، مدیریت حساب کاربری، امتیازها و علاقه‌مندی‌ها در قالب پاپکورن.
Version: 1.0.0
Author: SirenCode69
Requires PHP: 8.0
License: GPL-2.0-or-later
Update URI: https://github.com/SirenCode69/popcorn
*/
defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/gateways.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/frontend.php';
