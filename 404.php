<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?> dir="rtl" data-pc-theme-mode="<?php echo esc_attr(popcorn_theme_mode()); ?>" data-pc-theme="<?php echo popcorn_theme_mode() === 'dark' ? 'dark' : 'light'; ?>">
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?php wp_head(); ?>
</head>
<body <?php body_class('pc-error-page'); ?>>
<?php wp_body_open(); ?>
<main id="main" class="pc-error">
  <div class="pc-error__content">
    <a class="pc-error__brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>"><?php get_template_part('template-parts/site-brand', null, ['image_class' => 'pc-error__logo', 'mark_class' => 'pc-error__mark']); ?></a>
    <div class="pc-error__code" aria-hidden="true" dir="ltr">4<span>0</span>4</div>
    <span class="pc-error__label"><?php echo popcorn_icon('film'); ?> این سکانس پیدا نشد</span>
    <h1>صفحه‌ای که دنبالش بودی اینجا نیست.</h1>
    <p>شاید نشانی تغییر کرده یا صفحه حذف شده باشد.<br>از خانه شروع کن یا نام فیلم، سریال و هنرمند را جست‌وجو کن.</p>
    <form class="pc-error__search" action="<?php echo esc_url(home_url('/')); ?>" method="get" role="search">
      <label class="screen-reader-text" for="pc-error-search">جست‌وجو در سایت</label>
      <input id="pc-error-search" name="s" type="search" placeholder="فیلم، سریال یا هنرمند..." required>
      <button type="submit" aria-label="جست‌وجو"><?php echo popcorn_icon('search'); ?></button>
    </form>
    <a class="pc-error__home" href="<?php echo esc_url(home_url('/')); ?>"><?php echo popcorn_icon('home'); ?> بازگشت به خانه</a>
  </div>
</main>
<?php wp_footer(); ?>
</body>
</html>
