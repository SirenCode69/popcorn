<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?> dir="rtl" data-pc-theme-mode="<?php echo esc_attr(function_exists('popcorn_theme_mode') ? popcorn_theme_mode() : 'both'); ?>" data-pc-theme="<?php echo function_exists('popcorn_theme_mode') && popcorn_theme_mode() === 'dark' ? 'dark' : 'light'; ?>">
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><?php wp_head(); ?></head>
<body <?php body_class('pc-user-page'); ?>>
<?php wp_body_open(); ?>
<a class="pc-skip" href="#main">رفتن به محتوا</a>
<?php echo do_shortcode(popcorn_auth_page_kind() === 'account' ? '[popcorn_account]' : (popcorn_auth_page_kind() === 'register' ? '[popcorn_register]' : '[popcorn_auth]')); ?>
<?php wp_footer(); ?>
</body></html>
