<?php
$item = $args['item'] ?? null;
if (!$item) {
    return;
}
$display_title = popcorn_display_title($item);
$main_title = trim((string) ($item->title ?? '')) ?: $display_title;
$persian_title = $display_title !== $main_title ? $display_title : '';
$overview = trim(wp_strip_all_tags(popcorn_display_overview($item)));
$overview = $overview ? wp_trim_words($overview, 38, '…') : '';
$genres = array_slice(array_filter(array_map('trim', preg_split('/[,،]/u', (string) ($item->genres ?? '')))), 0, 3);
$poster_attributes = ['alt' => popcorn_item_seo_image_alt($item), 'loading' => 'lazy'];
$poster = !empty($item->post_id) ? get_the_post_thumbnail($item->post_id, popcorn_card_image_size(), $poster_attributes) : '';
$poster = $poster ?: popcorn_content_image($item->poster_id ?? 0, $item->poster_path ?? '', popcorn_card_image_size(), $poster_attributes);
$rating_source = (float) ($item->imdb_rating ?? 0) > 0 ? 'imdb' : 'tmdb';
$year = substr((string) ($item->release_date ?? ''), 0, 4);
$year = preg_match('/^[1-9][0-9]{3}$/D', $year) ? $year : '';
$features = popcorn_card_features($item->post_id ?? 0);
?>
<article class="pc-card">
  <a class="pc-card__link" href="<?php echo esc_url(popcorn_item_url($item)); ?>" aria-label="<?php echo esc_attr('جزئیات ' . $main_title . ($persian_title ? '، ' . $persian_title : '')); ?>">
    <div class="pc-card__poster">
      <?php echo $poster ?: '<span class="pc-card__placeholder">' . popcorn_icon('film') . '</span>'; ?>
      <?php if (array_filter($features)) : ?><span class="pc-card__tools" aria-hidden="true"><?php if ($features['play']) : ?><span><?php echo popcorn_icon('play'); ?></span><?php endif; ?><?php if ($features['dubbed']) : ?><span><?php echo popcorn_icon('microphone'); ?></span><?php endif; ?><?php if ($features['subtitle']) : ?><span><?php echo popcorn_icon('subtitles'); ?></span><?php endif; ?></span><?php endif; ?>
      <span class="pc-card__genres" aria-hidden="true"><?php foreach ($genres ?: [$item->popcorn_type === 'series' ? 'سریال' : 'فیلم'] as $genre) : ?><span><?php echo esc_html($genre); ?></span><?php endforeach; ?></span>
      <span class="pc-card__hover" aria-hidden="true"><strong dir="rtl"><?php echo esc_html($persian_title ?: $main_title); ?></strong><span class="pc-card__overview"><?php echo esc_html($overview ?: 'اطلاعات و جزئیات این اثر را ببینید.'); ?></span><span class="pc-card__hover-action">جزئیات و تماشا <?php echo popcorn_icon('left'); ?></span></span>
    </div>
    <div class="pc-card__details">
      <h3 dir="auto" title="<?php echo esc_attr($main_title); ?>"><?php echo esc_html($main_title); ?></h3>
      <div class="pc-card__meta"><?php echo popcorn_rating_score($item, $rating_source, 'pc-card__rating', false, true, true); ?><span class="pc-card__year" dir="ltr"><?php echo esc_html($year ?: 'جدید'); ?></span></div>
    </div>
  </a>
</article>
