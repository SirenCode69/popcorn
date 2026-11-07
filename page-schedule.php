<?php
/* Template Name: جدول پخش هفتگی */
defined('ABSPATH') || exit;
$days = function_exists('popcorn_core_schedule_days') ? popcorn_core_schedule_days() : ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
$today = function_exists('popcorn_core_schedule_today') ? popcorn_core_schedule_today() : ((int) current_datetime()->format('w') + 1) % 7;
$requested_day = isset($_GET['schedule_day']) && is_string($_GET['schedule_day']) && preg_match('/^[0-6]$/D', $_GET['schedule_day']) ? (int) $_GET['schedule_day'] : null;
$selected = $requested_day ?? $today;
$groups = function_exists('popcorn_core_schedule_items') ? popcorn_core_schedule_items() : array_fill(0, 7, []);
$items = array_merge(...$groups);
popcorn_homepage_prime_slider_items($items, true);
get_header();
?>
<main id="main" class="pc-container pc-schedule" data-schedule data-timezone="<?php echo esc_attr(wp_timezone_string()); ?>" data-day-explicit="<?php echo $requested_day !== null ? '1' : '0'; ?>">
  <?php while (have_posts()) : the_post(); ?>
    <header class="pc-schedule__head"><h1><?php the_title(); ?></h1><p>سریال‌های در حال پخش، بر اساس روزهای هفته</p></header>
    <?php if (trim(get_the_content())) : ?><div class="pc-schedule__intro"><?php the_content(); ?></div><?php endif; ?>
  <?php endwhile; ?>
  <nav class="pc-schedule__days" aria-label="روزهای پخش" data-schedule-tabs>
    <?php foreach ($days as $day => $label) : ?>
      <a id="pc-schedule-tab-<?php echo $day; ?>" class="pc-schedule__day<?php echo $day === $selected ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('schedule_day', $day, get_permalink())); ?>" data-schedule-day="<?php echo $day; ?>" aria-controls="pc-schedule-panel-<?php echo $day; ?>"<?php echo $day === $selected ? ' aria-current="true"' : ''; ?>><span><?php echo esc_html($label); ?></span><small data-schedule-today<?php echo $day !== $today ? ' hidden' : ''; ?>>امروز</small></a>
    <?php endforeach; ?>
  </nav>
  <?php foreach ($days as $day => $label) : ?>
    <section id="pc-schedule-panel-<?php echo $day; ?>" class="pc-schedule__panel" data-schedule-panel="<?php echo $day; ?>" aria-labelledby="pc-schedule-tab-<?php echo $day; ?>"<?php echo $day !== $selected ? ' hidden' : ''; ?>>
      <header class="pc-schedule__panel-head"><h2>برنامهٔ پخش <?php echo esc_html($label); ?></h2><span><?php echo count($groups[$day]); ?> سریال</span></header>
      <?php if ($groups[$day]) : ?>
        <div class="pc-schedule__grid"><?php foreach ($groups[$day] as $item) { get_template_part('template-parts/card', null, ['item' => $item]); } ?></div>
      <?php else : ?>
        <div class="pc-schedule__empty"><?php echo popcorn_icon('tv'); ?><p>برنامه‌ای برای این روز ثبت نشده است.</p><span>برای دیدن سریال‌های روزهای دیگر، روز پخش را تغییر دهید.</span></div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  <p class="pc-schedule__note">روزهای درج‌شده، برنامهٔ پخش اصلی سریال‌ها هستند؛ زمان قرار گرفتن قسمت جدید در سایت ممکن است متفاوت باشد.</p>
</main>
<?php get_footer(); ?>
