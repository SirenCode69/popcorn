<?php
$type = is_singular('popcorn_series') ? 'series' : (is_singular('popcorn_movie') ? 'movie' : get_query_var('popcorn_type'));
$item = is_singular(['popcorn_movie', 'popcorn_series']) ? popcorn_core_get_by_post($type, get_the_ID()) : popcorn_get_item($type, absint(get_query_var('popcorn_id')));
if (!$item) { get_template_part('404'); return; }
$item->popcorn_type = $type;
$display_overview = popcorn_display_overview($item);
$trailer = popcorn_title_trailer($item->trailer_url);
$backdrop = popcorn_content_image($item->backdrop_id ?: $item->poster_id, $item->backdrop_path ?: $item->poster_path, 'full', ['alt' => '', 'loading' => 'eager']);
$more = popcorn_core_similar_titles($type, $item->post_id, 12);
$cast = popcorn_core_get_credits($type, $item->id, 'cast');
$directors = popcorn_core_get_credits($type, $item->id, 'director');
$writers = popcorn_core_get_credits($type, $item->id, 'writer');
$gallery = array_values(array_filter(json_decode($item->gallery ?: '[]', true) ?: [], static function ($image) { return is_array($image) && (!empty($image['attachment_id']) || popcorn_external_image_url($image['path'] ?? '')); }));
[$collection_name, $collection_items] = popcorn_core_collection_items($type, absint($item->collection_id ?? 0), $item->id, 12);
$collection_backdrop = '';
foreach ($collection_items as $collection_item) {
    $collection_backdrop = popcorn_content_image($collection_item->backdrop_id, $collection_item->backdrop_path, 'large', ['alt' => '']);
    if ($collection_backdrop) { break; }
}
popcorn_card_features(0, array_merge($collection_items, $more));
$features = popcorn_card_features($item->post_id);
$play_url = popcorn_core_playback_start_url($item->post_id);
$downloads = popcorn_core_render_downloads($item->post_id);
$rating = popcorn_core_render_engagement($item->post_id, 'rating');
$favorite = popcorn_core_render_engagement($item->post_id, 'favorite');
$notices = popcorn_core_get_public_notices($item->post_id, 0);
$elementor_content = is_singular(['popcorn_movie', 'popcorn_series']) && get_post_meta($item->post_id, '_elementor_edit_mode', true) === 'builder';
if ($cast || $directors || $writers) { popcorn_enqueue_person_card_style(); }
if ($cast || $collection_items || $more) { popcorn_enqueue_slider_assets(count($cast) > 1 || count($collection_items) > 1 || count($more) > 1); }
get_header();
if (function_exists('elementor_theme_do_location') && elementor_theme_do_location('single')) { get_footer(); return; }
?>
<main id="main" class="pc-detail pc-title-detail<?php echo $backdrop ? ' pc-title-detail--has-backdrop' : ''; ?>">
  <?php if ($backdrop) : ?><div class="pc-detail__backdrop" aria-hidden="true"><?php echo $backdrop; ?></div><?php endif; ?>
  <div class="pc-container pc-detail__body">
    <nav class="pc-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url(home_url('/')); ?>">خانه</a><span>/</span><a href="<?php echo esc_url(popcorn_catalog_url($type)); ?>"><?php echo $type === 'series' ? 'سریال‌ها' : 'فیلم‌ها'; ?></a><span>/</span><span><?php echo esc_html(get_the_title($item->post_id)); ?></span></nav>
    <div class="pc-title-detail__intro">
      <div class="pc-title-detail__content">
        <?php get_template_part('template-parts/title-hero', null, compact('item', 'features', 'play_url', 'favorite', 'trailer', 'directors', 'writers') + ['has_downloads' => $downloads !== '', 'has_gallery' => (bool) $gallery]); ?>
        <?php get_template_part('template-parts/title-about', null, ['item' => $item, 'overview' => $display_overview]); ?>
      </div>
      <?php get_template_part('template-parts/title-sidebar', null, compact('item', 'rating', 'notices')); ?>
    </div>
    <?php popcorn_theme_ad('header'); ?>
    <?php if ($elementor_content) : ?><section class="pc-detail__editor-content"><?php the_content(); ?></section><?php endif; ?>
    <?php if ($cast) { get_template_part('template-parts/slider', null, ['items' => $cast, 'card' => 'person-card', 'heading' => 'بازیگران', 'section_class' => 'pc-section pc-slider-section--posters pc-title-detail__cast', 'navigation' => true, 'id' => 'pc-title-cast']); } ?>
    <?php if ($downloads || $item->is_coming_soon) { popcorn_theme_ad('downloads'); } ?>
    <?php echo $downloads; ?>
    <?php if (!$downloads && $item->is_coming_soon) : ?><section id="popcorn-downloads" class="pc-section"><h2>لینک‌های دانلود</h2><p>لینک‌های دانلود به‌زودی منتشر می‌شوند.</p></section><?php endif; ?>
    <?php if ($collection_items) : ?>
    <div class="pc-title-detail__collection">
      <?php if ($collection_backdrop) : ?><div class="pc-title-detail__collection-backdrop" aria-hidden="true"><?php echo $collection_backdrop; ?></div><?php endif; ?>
      <?php get_template_part('template-parts/slider', null, ['items' => $collection_items, 'card' => 'card', 'heading' => 'کالکشن ' . $collection_name, 'section_class' => 'pc-section pc-slider-section--posters', 'navigation' => true, 'id' => 'pc-title-collection']); ?>
    </div>
    <?php endif; ?>
    <?php if ($more) { get_template_part('template-parts/slider', null, ['items' => $more, 'card' => 'card', 'heading' => $type === 'series' ? 'سریال‌های مشابه' : 'فیلم‌های مشابه', 'section_class' => 'pc-section pc-slider-section--posters', 'navigation' => true, 'id' => 'pc-title-similar']); } ?>
    <?php if (is_singular(['popcorn_movie', 'popcorn_series']) && (comments_open() || get_comments_number())) { comments_template(); } ?>
  </div>
  <?php get_template_part('template-parts/title-dialogs', null, compact('item', 'trailer', 'notices') + ['has_gallery' => (bool) $gallery]); ?>
</main>
<?php get_footer(); ?>
