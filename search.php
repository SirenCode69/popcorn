<?php
$term = get_search_query(false);
global $wp_query;
$types = popcorn_core_search_page_types();
$selected_type = $wp_query->get('popcorn_search_type') ?: '';
$groups = $selected_type ? [] : popcorn_core_search_page_groups($term);
$result_posts = $selected_type ? $wp_query->posts : array_merge([], ...array_column($groups, 'posts'));
$items = popcorn_core_catalog_items($result_posts);
popcorn_card_features(0, $items);
$people = array_filter($items, static function ($item) { return $item->popcorn_type === 'person'; });
if ($people) { popcorn_core_person_work_counts(wp_list_pluck($people, 'id')); }
$page = max(1, absint(get_query_var('paged')));
get_header();
?>
<main id="main" class="pc-container pc-catalog pc-filtered-archive pc-search-results" data-archive-page<?php echo function_exists('popcorn_core_cache_plugin_active') && popcorn_core_cache_plugin_active() ? ' data-archive-cache="plugin"' : ''; ?>>
  <header class="pc-archive__head pc-search-results__head">
    <h1>نتایج جست‌وجو</h1>
    <p data-archive-count><?php echo esc_html(number_format((int) $wp_query->found_posts)); ?> نتیجه برای «<bdi><?php echo esc_html($term); ?></bdi>» پیدا شد.</p>
  </header>
  <?php popcorn_theme_ad('catalog'); ?>
  <div class="pc-archive__results-wrap">
    <div class="pc-archive__loading" data-archive-loading hidden><?php get_template_part('template-parts/loader', null, ['size' => 'small', 'label' => 'در حال دریافت نتایج جست‌وجو']); ?></div>
    <div data-archive-results aria-busy="false">
      <?php if ($result_posts) : ?>
        <?php if (!$selected_type) : ?>
        <?php foreach ($groups as $post_type => $group) {
            get_template_part('template-parts/search-group', null, $group + ['type' => $post_type, 'items' => $items, 'more_url' => add_query_arg(['s' => $term, 'search_type' => $post_type], home_url('/'))]);
        } ?>
        <?php else : ?>
          <header class="pc-search-results__group-head"><h2><?php echo esc_html($types[$selected_type]); ?></h2><a class="pc-slider__more" href="<?php echo esc_url(add_query_arg('s', $term, home_url('/'))); ?>">همهٔ بخش‌ها <?php echo popcorn_icon('left'); ?></a></header>
          <?php if ($selected_type === 'post') : ?><div class="pc-search-results__stories"><?php foreach ($result_posts as $entry) { get_template_part('template-parts/blog-card', null, ['item' => $entry]); } ?></div><?php else : ?>
          <?php get_template_part('template-parts/archive-results', null, ['posts' => $result_posts, 'items' => $items, 'type' => $selected_type === 'popcorn_person' ? 'person' : 'all']); ?>
          <?php endif; ?>
          <?php if ($wp_query->max_num_pages > 1) : ?><nav class="pc-pagination" aria-label="صفحه‌بندی نتایج جست‌وجو"><?php echo paginate_links(['current' => $page, 'total' => $wp_query->max_num_pages, 'mid_size' => 1, 'prev_text' => popcorn_icon('right') . '<span class="screen-reader-text">صفحهٔ قبلی</span>', 'next_text' => popcorn_icon('left') . '<span class="screen-reader-text">صفحهٔ بعدی</span>']); ?></nav><?php endif; ?>
        <?php endif; ?>
      <?php else : ?><div class="pc-archive__empty"><?php echo popcorn_icon('search'); ?><h2>نتیجه‌ای پیدا نشد</h2><p>نام دیگری را از جست‌وجوی هدر جست‌وجو کنید.</p><a class="pc-button pc-search-results__browse" href="<?php echo esc_url(popcorn_catalog_url('movie')); ?>">دیدن فیلم‌ها <?php echo popcorn_icon('left'); ?></a></div><?php endif; ?>
    </div>
  </div>
  <p class="screen-reader-text" data-archive-status role="status" aria-live="polite"></p>
</main>
<?php get_footer(); ?>
