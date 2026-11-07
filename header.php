<!doctype html>
<html <?php language_attributes(); ?> dir="rtl" data-pc-theme-mode="<?php echo esc_attr(popcorn_theme_mode()); ?>" data-pc-theme="<?php echo popcorn_theme_mode() === 'dark' ? 'dark' : 'light'; ?>">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="pc-skip" href="#main">رفتن به محتوا</a>
<?php if (!function_exists('elementor_theme_do_location') || !elementor_theme_do_location('header')) : ?>
<?php
$auth_enabled = function_exists('popcorn_auth_enabled') && popcorn_auth_enabled();
$account_url = $auth_enabled ? (is_user_logged_in() ? popcorn_auth_account_url() : popcorn_auth_login_url()) : '';
$subscription_url = function_exists('popcorn_subscription_enabled') && popcorn_subscription_enabled() && function_exists('popcorn_subscription_url') ? popcorn_subscription_url() : '';
$current_user = wp_get_current_user();
?>
<header class="pc-header">
  <div class="pc-container pc-header__inner">
    <a class="pc-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?>">
      <?php get_template_part('template-parts/site-brand'); ?>
    </a>
    <div class="pc-header__nav" id="pc-main-nav">
      <?php if (has_nav_menu('primary')) { wp_nav_menu(['theme_location' => 'primary', 'container' => 'nav', 'container_class' => 'pc-classic-nav', 'container_aria_label' => 'ناوبری اصلی', 'menu_class' => 'pc-nav__menu', 'fallback_cb' => false, 'depth' => 2]); } elseif (function_exists('popcorn_mega_render_navigation_item')) { block_template_part('navigation'); } ?>
    </div>
    <div class="pc-header__tools">
      <?php if ($subscription_url) : ?><a class="pc-header__subscribe" href="<?php echo esc_url($subscription_url); ?>">خرید اشتراک</a><?php endif; ?>
      <div class="pc-header__search-wrap">
        <button class="pc-header__icon-button pc-header__search-toggle" type="button" aria-label="باز کردن جست‌وجو" aria-controls="pc-header-search" aria-expanded="false"><?php echo popcorn_icon('search'); ?></button>
        <form class="pc-search" id="pc-header-search" action="<?php echo esc_url(home_url('/')); ?>" method="get" role="search">
          <label class="screen-reader-text" for="pc-search">جست‌وجوی خبر، فیلم، سریال و هنرمند</label>
          <input id="pc-search" name="s" type="search" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="فیلم، سریال، هنرمند یا خبر..."<?php if (function_exists('popcorn_core_live_search_ready') && popcorn_core_live_search_ready()) : ?> data-rlvlive="true" data-rlvparentel="#pc-live-results" data-rlvconfig="popcorn"<?php endif; ?>>
          <button class="pc-search__clear" type="button" aria-label="پاک‌کردن جست‌وجو"><?php echo popcorn_icon('close'); ?></button>
          <button type="submit" aria-label="جست‌وجو"><?php echo popcorn_icon('search'); ?></button>
          <?php if (function_exists('popcorn_core_live_search_ready') && popcorn_core_live_search_ready()) : ?><div id="pc-live-results"></div><?php endif; ?>
        </form>
      </div>
      <?php if (popcorn_theme_mode() === 'both') : ?><button class="pc-header__icon-button pc-header__theme-toggle" data-pc-theme-toggle type="button" aria-label="تغییر حالت نمایش" aria-pressed="false"><?php echo popcorn_icon('moon', 'pc-theme-icon--moon'); ?><?php echo popcorn_icon('sun', 'pc-theme-icon--sun'); ?></button><?php endif; ?>
      <?php if (function_exists('popcorn_notification_bell')) { echo popcorn_notification_bell(); } ?>
      <?php if ($account_url) : ?>
      <?php if (is_user_logged_in()) : ?>
      <details class="pc-header__account"><summary aria-label="حساب <?php echo esc_attr($current_user->display_name); ?>"><span class="pc-header__avatar"><?php echo popcorn_icon('user'); ?></span><span class="pc-header__name"><?php echo esc_html($current_user->display_name ?: $current_user->user_login); ?></span><?php echo popcorn_icon('down'); ?></summary><div class="pc-header__account-panel"><a href="<?php echo esc_url($account_url); ?>">حساب من</a><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">خروج</a></div></details>
      <?php else : ?><a class="pc-header__login" href="<?php echo esc_url($account_url); ?>"><span class="pc-header__avatar"><?php echo popcorn_icon('user'); ?></span><span>ورود / ثبت‌نام</span></a><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php get_template_part('template-parts/mobile-dock', null, ['account_url' => $account_url, 'current_user' => $current_user, 'subscription_url' => $subscription_url]); ?>
<?php if ((!is_front_page() || get_query_var('popcorn_notifications')) && !is_singular(['popcorn_movie', 'popcorn_series', 'popcorn_person'])) { popcorn_theme_ad('header', true); } ?>
<?php endif; ?>
