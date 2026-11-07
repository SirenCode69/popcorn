<?php
$playback = popcorn_core_get_playback(get_queried_object_id());
$access = popcorn_core_playback_access($playback);
$sources = is_wp_error($access) ? [] : popcorn_core_playback_sources($playback);
$parent_url = $playback ? get_permalink($playback->content_post_id) : home_url('/');
$parent_title = $playback ? get_the_title($playback->content_post_id) : '';
$episode = $playback ? popcorn_core_playback_episode_label($playback) : '';
$parent_item = $playback ? popcorn_core_get_by_post(get_post_type($playback->content_post_id) === 'popcorn_series' ? 'series' : 'movie', $playback->content_post_id) : null;
$cover = $parent_item ? popcorn_content_image($parent_item->backdrop_id, $parent_item->backdrop_path, 'full', ['alt' => popcorn_item_seo_image_alt($parent_item), 'loading' => 'eager', 'fetchpriority' => 'high']) : '';
$progress = $sources ? popcorn_core_playback_progress($playback->post_id) : null;
$resume = $progress && (float) $progress->position_seconds >= 5 && (float) $progress->position_seconds < (float) $progress->duration_seconds * .95 ? (float) $progress->position_seconds : 0;
?>
<!doctype html>
<html <?php language_attributes(); ?> data-pc-theme-mode="<?php echo esc_attr(popcorn_theme_mode()); ?>" data-pc-theme="<?php echo popcorn_theme_mode() === 'dark' ? 'dark' : 'light'; ?>">
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?php wp_head(); ?>
</head>
<body <?php body_class('pc-watch-page'); ?>>
<?php wp_body_open(); ?>
<main id="main" class="pc-watch">
  <?php if ($sources) : ?>
  <?php get_template_part('template-parts/player', null, ['sources' => $sources, 'subtitles' => popcorn_core_playback_subtitles($playback), 'navigation' => popcorn_core_playback_navigation($playback), 'playback' => $playback, 'title' => $parent_title, 'episode' => $episode, 'back' => $parent_url, 'resume' => $resume, 'cover' => $cover]); ?>
  <?php elseif (is_wp_error($access)) : ?>
  <section class="pc-watch__access">
    <a href="<?php echo esc_url($parent_url); ?>"><?php echo popcorn_icon('right'); ?> بازگشت به صفحهٔ اثر</a>
    <h1><?php echo esc_html($parent_title); ?></h1>
    <p><?php echo esc_html($access->get_error_message()); ?></p>
    <?php $login = popcorn_core_public_login_url(get_permalink()); ?>
    <?php if ($login && ($access->get_error_code() === 'login' || ($access->get_error_code() === 'subscription' && !is_user_logged_in()))) : ?>
    <a class="pc-button" href="<?php echo esc_url($login); ?>">ورود به حساب کاربری</a>
    <?php elseif ($access->get_error_code() === 'subscription' && function_exists('popcorn_subscription_url')) : ?>
    <a class="pc-button" href="<?php echo esc_url(popcorn_subscription_url()); ?>">مشاهدهٔ پلن‌های اشتراک</a>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
