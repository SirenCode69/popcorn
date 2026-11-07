<?php
$account_url = (string) ($args['account_url'] ?? '');
$subscription_url = (string) ($args['subscription_url'] ?? '');
$current_user = $args['current_user'] ?? wp_get_current_user();
$genres = taxonomy_exists('popcorn_genre') ? get_terms(['taxonomy' => 'popcorn_genre', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']) : [];
$genres = is_wp_error($genres) ? [] : $genres;
$view = get_query_var('popcorn_view');
$type = get_query_var('popcorn_type');
$section = is_front_page() && !get_query_var('popcorn_notifications') ? 'home' : ($view === 'movie' || $type === 'movie' || is_singular('popcorn_movie') ? 'movie' : ($view === 'series' || $type === 'series' || is_singular('popcorn_series') ? 'series' : ''));
?>
<nav class="pc-mobile-dock" aria-label="ناوبری پایین صفحه">
  <div class="pc-mobile-dock__bar"<?php echo !$account_url ? ' style="--pc-dock-items:4"' : ''; ?>>
  <a class="pc-mobile-dock__item<?php echo $section === 'home' ? ' is-active' : ''; ?>" href="<?php echo esc_url(home_url('/')); ?>"<?php echo $section === 'home' ? ' aria-current="page"' : ''; ?>><?php echo popcorn_icon('home'); ?><span>خانه</span></a>
  <a class="pc-mobile-dock__item<?php echo $section === 'movie' ? ' is-active' : ''; ?>" href="<?php echo esc_url(function_exists('popcorn_catalog_url') ? popcorn_catalog_url('movie') : home_url('/films/')); ?>"<?php echo $section === 'movie' ? ' aria-current="page"' : ''; ?>><?php echo popcorn_icon('film'); ?><span>فیلم‌ها</span></a>
  <a class="pc-mobile-dock__item<?php echo $section === 'series' ? ' is-active' : ''; ?>" href="<?php echo esc_url(function_exists('popcorn_catalog_url') ? popcorn_catalog_url('series') : home_url('/series/')); ?>"<?php echo $section === 'series' ? ' aria-current="page"' : ''; ?>><?php echo popcorn_icon('tv'); ?><span>سریال‌ها</span></a>
  <button class="pc-mobile-dock__item pc-mobile-dock__genre-toggle" type="button" aria-label="باز کردن ژانرها" aria-controls="pc-mobile-genres" aria-expanded="false"><?php echo popcorn_icon('grid'); ?><span>ژانرها</span></button>
  <?php if ($account_url) : ?>
  <?php if (is_user_logged_in()) : ?>
  <button class="pc-mobile-dock__item pc-mobile-dock__account-toggle" type="button" aria-label="باز کردن منوی کاربری" aria-controls="pc-mobile-account" aria-expanded="false"><?php echo popcorn_icon('user'); ?><span>حساب</span></button>
  <?php else : ?>
  <a class="pc-mobile-dock__item" href="<?php echo esc_url($account_url); ?>"><?php echo popcorn_icon('user'); ?><span>ورود</span></a>
  <?php endif; ?>
  <?php endif; ?>
  </div>
  <div class="pc-mobile-genre-panel" id="pc-mobile-genres" hidden>
  <strong>ژانرها</strong>
  <?php if ($genres) : ?>
  <div class="pc-mobile-genre-panel__grid">
    <?php foreach ($genres as $genre) : ?><a href="<?php echo esc_url(get_term_link($genre)); ?>"><?php echo esc_html($genre->name); ?></a><?php endforeach; ?>
  </div>
  <?php else : ?><p>هنوز ژانری برای نمایش وجود ندارد.</p><?php endif; ?>
  </div>
<?php if ($account_url && is_user_logged_in()) : ?>
  <div class="pc-mobile-account-panel" id="pc-mobile-account" hidden>
  <strong><?php echo esc_html($current_user->display_name ?: $current_user->user_login); ?></strong>
  <a href="<?php echo esc_url($account_url); ?>"><?php echo popcorn_icon('user'); ?> حساب من</a>
  <?php if ($subscription_url) : ?><a href="<?php echo esc_url($subscription_url); ?>"><?php echo popcorn_icon('star'); ?> خرید اشتراک</a><?php endif; ?>
  <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php echo popcorn_icon('exit'); ?> خروج</a>
  </div>
<?php endif; ?>
</nav>
