<?php
$native_archive = popcorn_core_is_catalog_archive();
$type = $native_archive ? (is_post_type_archive('popcorn_movie') ? 'movie' : (is_post_type_archive('popcorn_series') ? 'series' : 'all')) : (get_query_var('popcorn_view') === 'series' ? 'series' : 'movie');
$title = $type === 'series' ? 'سریال‌ها' : 'فیلم‌ها';
$page = isset($_GET['pg']) && is_scalar($_GET['pg']) ? max(1, absint($_GET['pg'])) : max(1, absint(get_query_var('paged')));
$filters = popcorn_core_catalog_filters();
if ($native_archive) {
    global $wp_query;
    $archive = ['query' => $wp_query, 'items' => popcorn_core_catalog_items($wp_query->posts)];
    $title = is_category() || is_tag() || is_tax() ? single_term_title('', false) : wp_strip_all_tags(get_the_archive_title());
    $base_url = remove_query_arg(array_merge(array_keys($filters), ['pg']), get_pagenum_link(1, false));
} else {
    $archive = popcorn_core_catalog_archive($type, $filters, $page);
    $base_url = popcorn_catalog_url($type);
}
$items = $archive['items'];
$query = $archive['query'];
$query_args = [];
wp_parse_str((string) wp_parse_url($base_url, PHP_URL_QUERY), $query_args);
$total = (int) $query->found_posts;
popcorn_card_features(0, $items);
if (function_exists('popcorn_core_person_work_counts')) {
    $people = array_filter($items, static function ($item) { return $item->popcorn_type === 'person'; });
    if ($people) {
        popcorn_core_person_work_counts(wp_list_pluck($people, 'id'));
    }
}
get_header();
?>
<main id="main" class="pc-container pc-catalog pc-filtered-archive" data-archive-page>
  <header class="pc-archive__head"><h1><?php echo esc_html($title); ?></h1><p data-archive-count><?php echo esc_html(number_format($total)); ?> <?php echo $type === 'all' ? 'نتیجه' : ($type === 'series' ? 'سریال' : 'فیلم'); ?></p></header>
  <?php if ($native_archive && get_the_archive_description()) : ?><div class="pc-archive__description"><?php echo wp_kses_post(get_the_archive_description()); ?></div><?php endif; ?>
  <?php get_template_part('template-parts/archive-filters', null, ['action' => $base_url, 'query_args' => $query_args, 'filters' => $filters, 'options' => popcorn_core_catalog_filter_options($type), 'search' => false]); ?>
  <?php popcorn_theme_ad('catalog'); ?>
  <div class="pc-archive__results-wrap">
    <div class="pc-archive__loading" data-archive-loading hidden><?php get_template_part('template-parts/loader', null, ['size' => 'small', 'label' => 'در حال دریافت ' . $title]); ?></div>
    <div data-archive-results aria-busy="false">
      <?php get_template_part('template-parts/archive-results', null, ['posts' => $query->posts, 'items' => $items, 'type' => $type]); ?>
      <?php if ($query->max_num_pages > 1) : ?><nav class="pc-pagination" aria-label="صفحه‌بندی <?php echo esc_attr($title); ?>"><?php echo paginate_links(['base' => add_query_arg('pg', '%#%', $base_url), 'format' => '', 'current' => $page, 'total' => $query->max_num_pages, 'add_args' => array_filter($filters, static function ($value) { return $value !== '' && $value !== 0; }), 'mid_size' => 1, 'prev_text' => popcorn_icon('right') . '<span class="screen-reader-text">صفحهٔ قبلی</span>', 'next_text' => popcorn_icon('left') . '<span class="screen-reader-text">صفحهٔ بعدی</span>']); ?></nav><?php endif; ?>
    </div>
  </div>
  <p class="screen-reader-text" data-archive-status role="status" aria-live="polite"></p>
</main>
<?php get_footer(); ?>
