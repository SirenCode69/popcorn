<?php
defined('ABSPATH') || exit;
global $wp_query;
$filters = popcorn_blog_filters();
$base_url = remove_query_arg(['pc_blog_category', 'pc_blog_tag', 'pc_blog_sort', 'paged'], get_pagenum_link(1, false));
$query_args = [];
wp_parse_str((string) wp_parse_url($base_url, PHP_URL_QUERY), $query_args);
get_header();
?>
<main id="main" class="pc-container pc-blog-archive" data-archive-page>
  <nav class="pc-blog-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url(home_url('/')); ?>">خانه</a><span>/</span><?php if (is_category() || is_tag() || is_author() || is_date()) : ?><a href="<?php echo esc_url(popcorn_blog_url()); ?>">وبلاگ</a><span>/</span><?php endif; ?><span aria-current="page"><?php echo esc_html(popcorn_blog_title()); ?></span></nav>
  <header class="pc-blog-archive__head"><h1><?php echo esc_html(popcorn_blog_title()); ?></h1><span data-archive-count><?php echo esc_html(number_format((int) $wp_query->found_posts)); ?> نوشته</span></header>
  <?php if (is_archive() && get_the_archive_description()) : ?><div class="pc-blog-description"><?php echo wp_kses_post(get_the_archive_description()); ?></div><?php endif; ?>
  <form class="pc-blog-filters" method="get" action="<?php echo esc_url($base_url); ?>" data-blog-filters data-archive-filters data-archive-checkboxes<?php echo function_exists('popcorn_core_cache_plugin_active') && popcorn_core_cache_plugin_active() ? ' data-archive-cache="plugin"' : ''; ?>>
    <?php foreach ($query_args as $key => $value) : ?><?php if (is_scalar($value)) : ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>"><?php endif; ?><?php endforeach; ?>
    <button type="button" class="pc-blog-filters__toggle" data-archive-toggle aria-expanded="false" aria-controls="pc-blog-filter-fields"><?php echo popcorn_icon('filter'); ?> فیلترها <?php echo popcorn_icon('down'); ?></button>
    <div class="pc-blog-filters__fields" id="pc-blog-filter-fields" data-archive-fields>
      <?php if (!is_category() && !is_tag()) : ?>
        <?php foreach (['pc_blog_category' => ['category', 'دسته‌بندی‌ها'], 'pc_blog_tag' => ['post_tag', 'برچسب‌ها']] as $key => [$taxonomy, $label]) : $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]); ?>
          <details class="pc-blog-select" data-blog-select><summary><span><?php echo esc_html($label); ?><?php if ($filters[$key]) : ?> <small>(<?php echo count($filters[$key]); ?>)</small><?php endif; ?></span><?php echo popcorn_icon('down'); ?></summary><div class="pc-blog-select__panel"><label class="screen-reader-text" for="<?php echo esc_attr($key); ?>-search">جست‌وجوی <?php echo esc_html($label); ?></label><input type="search" id="<?php echo esc_attr($key); ?>-search" placeholder="جست‌وجو..." data-blog-term-search autocomplete="off"><div class="pc-blog-select__terms"><?php if (!is_wp_error($terms) && $terms) : ?><?php foreach ($terms as $term) : ?><label data-blog-term><input type="checkbox" name="<?php echo esc_attr($key); ?>[]" value="<?php echo absint($term->term_id); ?>" <?php checked(in_array((int) $term->term_id, $filters[$key], true)); ?>><span><?php echo esc_html($term->name); ?></span></label><?php endforeach; ?><?php else : ?><p>موردی ثبت نشده است.</p><?php endif; ?></div></div></details>
        <?php endforeach; ?>
      <?php endif; ?>
      <label class="pc-blog-sort"><span class="screen-reader-text">ترتیب نمایش</span><select name="pc_blog_sort"><option value="newest" <?php selected($filters['pc_blog_sort'], 'newest'); ?>>جدیدترین‌ها</option><option value="oldest" <?php selected($filters['pc_blog_sort'], 'oldest'); ?>>قدیمی‌ترین‌ها</option><option value="name" <?php selected($filters['pc_blog_sort'], 'name'); ?>>نام (الفبا)</option></select><?php echo popcorn_icon('down'); ?></label>
      <button type="submit" class="pc-blog-filter-submit">اعمال فیلترها</button>
      <a class="pc-blog-reset" href="<?php echo esc_url($base_url); ?>" data-archive-reset <?php echo !$filters['pc_blog_category'] && !$filters['pc_blog_tag'] && $filters['pc_blog_sort'] === 'newest' ? 'hidden' : ''; ?>>پاک کردن فیلترها</a>
    </div>
  </form>
  <?php popcorn_theme_ad('catalog'); ?>
  <div class="pc-blog-results-wrap">
    <div class="pc-blog-loading" data-archive-loading hidden><?php get_template_part('template-parts/loader', null, ['size' => 'small', 'label' => 'در حال دریافت نوشته‌ها']); ?></div>
    <div data-archive-results aria-busy="false">
  <?php if (have_posts()) : ?>
    <div class="pc-blog-grid"><?php while (have_posts()) : the_post(); get_template_part('template-parts/blog-card'); endwhile; ?></div>
    <?php the_posts_pagination(['mid_size' => 1, 'class' => 'pc-pagination', 'prev_text' => popcorn_icon('right') . '<span class="screen-reader-text">صفحهٔ قبلی</span>', 'next_text' => popcorn_icon('left') . '<span class="screen-reader-text">صفحهٔ بعدی</span>']); ?>
  <?php else : ?><div class="pc-blog-empty"><?php echo popcorn_icon('newspaper'); ?><h2>نوشته‌ای پیدا نشد.</h2><p>فیلترهای انتخاب‌شده را تغییر دهید یا به وبلاگ برگردید.</p><a href="<?php echo esc_url(popcorn_blog_url()); ?>">همهٔ نوشته‌ها</a></div><?php endif; ?>
    </div>
  </div>
  <p class="screen-reader-text" data-archive-status role="status" aria-live="polite"></p>
</main>
<?php get_footer(); ?>
