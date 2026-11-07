<?php
$item = $args['item'] ?? null;
if (!$item) {
    return;
}
$index = absint($args['index'] ?? 0);
$title = trim((string) ($item->title ?? '')) ?: popcorn_display_title($item);
$persian_title = trim((string) ($item->original_title ?? ''));
$overview = popcorn_display_overview($item);
$url = popcorn_item_url($item);
$background = popcorn_content_image($item->backdrop_id ?: $item->poster_id, $item->backdrop_path ?: $item->poster_path, 'full', ['class' => 'pc-hero__image', 'alt' => popcorn_item_seo_image_alt($item), 'loading' => $index === 0 ? 'eager' : 'lazy', 'fetchpriority' => $index === 0 ? 'high' : 'low']);
$genres = wp_get_post_terms($item->post_id, 'popcorn_genre', ['fields' => 'names']);
$genres = is_wp_error($genres) ? [] : array_slice($genres, 0, 5);
if (!$genres && $item->genres) {
    $genres = array_slice(array_map('trim', explode(',', $item->genres)), 0, 5);
}
?>
<article class="pc-hero__slide" aria-label="<?php echo esc_attr($title); ?>">
  <?php echo $background; ?>
  <div class="pc-hero__shade" aria-hidden="true"></div>
  <div class="pc-hero__content">
    <span class="pc-hero__kind"><?php echo $item->popcorn_type === 'series' ? 'سریال' : 'فیلم'; ?></span>
    <div class="pc-hero__titles">
      <h2><bdi><?php echo esc_html($title); ?></bdi></h2>
      <?php if ($persian_title && $persian_title !== $title) : ?><p class="pc-hero__title-fa" lang="fa" dir="rtl"><?php echo esc_html($persian_title); ?></p><?php endif; ?>
    </div>
    <div class="pc-hero__facts">
      <?php if ((float) $item->imdb_rating > 0) : ?><span class="pc-hero__imdb">IMDb <b><?php echo esc_html(number_format_i18n((float) $item->imdb_rating, 1)); ?></b></span><?php endif; ?>
      <?php if ($item->certification) : ?><span><?php echo esc_html($item->certification); ?></span><?php endif; ?>
      <?php if ($item->release_date) : ?><span><?php echo esc_html(substr($item->release_date, 0, 4)); ?></span><?php endif; ?>
      <?php if ($item->runtime) : ?><span><?php echo esc_html(number_format_i18n($item->runtime)); ?> دقیقه</span><?php endif; ?>
    </div>
    <?php if ($genres) : ?><div class="pc-hero__genres"><?php foreach ($genres as $genre) : ?><span><?php echo esc_html($genre); ?></span><?php endforeach; ?></div><?php endif; ?>
    <?php if ($overview) : ?><p class="pc-hero__overview"><?php echo esc_html(wp_trim_words($overview, 36, '…')); ?></p><?php endif; ?>
    <div class="pc-hero__actions">
      <a class="pc-hero__primary" href="<?php echo esc_url($url . '#popcorn-downloads'); ?>"><?php echo popcorn_icon('play'); ?> تماشا و دانلود</a>
      <a class="pc-hero__secondary" href="<?php echo esc_url($url); ?>">اطلاعات بیشتر</a>
      <a class="pc-hero__add" href="<?php echo esc_url($url . '#popcorn-engagement'); ?>" aria-label="امتیاز و علاقه‌مندی <?php echo esc_attr($title); ?>" title="امتیاز و علاقه‌مندی"><?php echo popcorn_icon('plus'); ?></a>
    </div>
  </div>
</article>
