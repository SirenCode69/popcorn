<?php
$hero_items = function_exists('popcorn_get_slider_items') ? popcorn_get_slider_items(6) : [];
popcorn_homepage_prime_slider_items($hero_items);
get_header();
?>
<main id="main">
  <?php get_template_part('template-parts/slider', null, ['items' => $hero_items, 'card' => 'hero-slide', 'variant' => 'hero', 'class' => 'pc-hero', 'id' => 'pc-home-hero', 'label' => 'آثار منتخب اسلایدر', 'direction' => 'ltr', 'thumb_card' => 'hero-thumb', 'thumb_class' => 'pc-hero__thumbs']); ?>
  <div class="pc-container pc-home">
    <?php popcorn_theme_ad('header'); ?>
    <?php
    $schedule_page_id = absint(get_option('popcorn_schedule_page_id'));
    $schedule_links = $schedule_page_id && get_post_status($schedule_page_id) === 'publish' ? [['url' => get_permalink($schedule_page_id), 'label' => 'جدول پخش']] : [];
    $sections = [
        'featured' => ['heading' => 'ویژه‌های ' . get_bloginfo('name'), 'empty_message' => 'آثار ویژه به‌زودی اینجا نمایش داده می‌شوند.'],
        'updated-series' => ['heading' => 'سریال‌های بروز شده', 'more_url' => function_exists('popcorn_catalog_url') ? popcorn_catalog_url('series') : home_url('/'), 'more_label' => 'همهٔ سریال‌ها', 'empty_message' => 'سریال‌های بروز شده به‌زودی اینجا نمایش داده می‌شوند.'],
        'series' => ['heading' => 'سریال‌های جدید', 'more_url' => function_exists('popcorn_catalog_url') ? popcorn_catalog_url('series') : home_url('/'), 'more_label' => 'همهٔ سریال‌ها', 'empty_message' => 'سریال‌های جدید به‌زودی اینجا نمایش داده می‌شوند.'],
        'actors' => ['heading' => 'بازیگران محبوب', 'card' => 'person-card', 'more_url' => get_post_type_archive_link('popcorn_person') ?: home_url('/'), 'more_label' => 'همهٔ هنرمندان', 'empty_message' => 'بازیگران محبوب به‌زودی اینجا نمایش داده می‌شوند.'],
        'movies' => ['heading' => 'فیلم‌های جدید', 'more_url' => function_exists('popcorn_catalog_url') ? popcorn_catalog_url('movie') : home_url('/'), 'more_label' => 'همهٔ فیلم‌ها', 'empty_message' => 'فیلم‌های جدید به‌زودی اینجا نمایش داده می‌شوند.'],
    ];
    if (function_exists('popcorn_get_home_news') && popcorn_get_home_news(1)) {
        $sections['news'] = ['heading' => 'اخبار', 'card' => 'blog-card', 'more_url' => popcorn_blog_url(), 'more_label' => 'همهٔ اخبار', 'hide_empty' => true];
    }
    foreach ($sections as $key => $section) {
        if (in_array($key, ['series', 'updated-series'], true)) {
            $section['extra_links'] = $schedule_links;
        }
        get_template_part('template-parts/slider', null, array_merge([
            'items' => [],
            'card' => 'card',
            'async_url' => rest_url('popcorn/v1/home-slider/' . $key . '/'),
            'id' => 'pc-home-' . $key,
            'section_class' => 'pc-home__section ' . ($key === 'news' ? 'pc-home__news pc-slider-section--stories' : ($key === 'actors' ? 'pc-home__people pc-slider-section--people' : 'pc-home__posters pc-slider-section--posters')) . (!empty($section['extra_links']) ? ' pc-home__section--schedule' : ''),
            'navigation' => true,
        ], $section));
        if ($key === 'featured') {
            popcorn_theme_ad('home');
            get_template_part('template-parts/genre-slider');
        }
    }
    ?>
    <?php if (is_page() && (comments_open() || get_comments_number())) { comments_template(); } ?>
  </div>
</main>
<?php get_footer(); ?>
