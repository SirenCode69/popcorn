<?php
global $wp_query;
$filters = function_exists('popcorn_core_person_archive_filters') ? popcorn_core_person_archive_filters() : [];
$people = function_exists('popcorn_core_person_archive_items') ? popcorn_core_person_archive_items($wp_query->posts) : [];
get_header();
?>
<main id="main" class="pc-container pc-person-archive pc-filtered-archive" data-archive-page>
  <header class="pc-archive__head"><h1>هنرمندان</h1><p data-archive-count><?php echo esc_html(number_format((int) $wp_query->found_posts)); ?> بازیگر و کارگردان</p></header>
  <?php get_template_part('template-parts/archive-filters', null, ['action' => get_post_type_archive_link('popcorn_person'), 'filters' => $filters, 'options' => [
      'role' => ['label' => 'حرفه', 'values' => ['' => 'همه (بازیگر و کارگردان)', 'actor' => 'بازیگر', 'director' => 'کارگردان']],
      'gender' => ['label' => 'جنسیت', 'values' => ['' => 'همهٔ جنسیت‌ها', 'M' => 'مرد', 'F' => 'زن']],
      'sort' => ['label' => 'ترتیب نمایش', 'values' => ['popular' => 'محبوب‌ترین‌ها', 'recent' => 'جدیدترین‌ها', 'name' => 'نام (الفبا)', 'youngest' => 'جوان‌ترین‌ها', 'oldest' => 'مسن‌ترین‌ها']],
  ]]); ?>
  <?php popcorn_theme_ad('catalog'); ?>
  <div class="pc-archive__results-wrap">
    <div class="pc-archive__loading" data-archive-loading hidden><?php get_template_part('template-parts/loader', null, ['size' => 'small', 'label' => 'در حال دریافت هنرمندان']); ?></div>
    <div data-archive-results aria-busy="false">
      <?php if (have_posts()) : ?><div class="pc-detail__people-grid">
        <?php while (have_posts()) : the_post(); $person = $people[get_the_ID()] ?? null; if ($person) { get_template_part('template-parts/person-card', null, ['person' => $person]); } endwhile; ?>
      </div><?php else : ?><div class="pc-archive__empty"><?php echo popcorn_icon('user'); ?><p>هنرمندی با این مشخصات پیدا نشد.</p></div><?php endif; ?>
      <?php the_posts_pagination(['mid_size' => 1, 'prev_text' => popcorn_icon('right') . '<span class="screen-reader-text">صفحهٔ قبلی</span>', 'next_text' => popcorn_icon('left') . '<span class="screen-reader-text">صفحهٔ بعدی</span>', 'screen_reader_text' => 'صفحه‌بندی هنرمندان']); ?>
    </div>
  </div>
  <p class="screen-reader-text" data-archive-status role="status" aria-live="polite"></p>
</main>
<?php get_footer(); ?>
