<?php
$item = $args['item'];
$title = get_the_title($item->post_id) ?: $item->title;
$features = $args['features'];
$badge_labels = popcorn_core_download_badge_labels();
$directors = $args['directors'] ?? [];
$writers = $args['writers'] ?? [];
$genres = wp_get_post_terms($item->post_id, 'popcorn_genre');
$genres = is_wp_error($genres) ? [] : $genres;
$poster = get_the_post_thumbnail($item->post_id, 'large', ['alt' => popcorn_item_seo_image_alt($item), 'loading' => 'eager', 'fetchpriority' => 'high']);
$poster = $poster ?: popcorn_content_image($item->poster_id, $item->poster_path, 'large', ['alt' => popcorn_item_seo_image_alt($item), 'loading' => 'eager']);
?>
<div class="pc-title-detail__hero">
  <div class="pc-title-detail__poster-column">
    <div class="pc-title-detail__poster"><?php echo $poster ?: '<span>' . popcorn_icon('film') . '</span>'; ?><?php if ($args['has_gallery']) : ?><button type="button" class="pc-title-detail__gallery-button" data-pc-gallery-open data-endpoint="<?php echo esc_url(rest_url('popcorn/v1/title-gallery/' . absint($item->post_id))); ?>" aria-haspopup="dialog" aria-controls="pc-title-image"><?php echo popcorn_icon('image'); ?> گالری تصاویر</button><?php endif; ?></div>
  </div>
  <div class="pc-title-detail__main">
    <div class="pc-title-detail__header">
      <div class="pc-title-detail__names"><h1><bdi><?php echo esc_html($title); ?></bdi></h1><?php if ($item->original_title && $item->original_title !== $title) : ?><p lang="fa"><?php echo esc_html($item->original_title); ?></p><?php endif; ?><?php if ($item->tagline) : ?><p class="pc-detail__tagline"><?php echo esc_html($item->tagline); ?></p><?php endif; ?></div>
    </div>
    <?php if ($genres || $directors || $writers) : ?><div class="pc-title-detail__people-info">
      <?php if ($genres) : ?><div class="pc-title-detail__genres"><span>ژانر</span><div><?php $separator = ''; foreach ($genres as $genre) : $url = get_term_link($genre); if (is_wp_error($url)) { continue; } ?><?php echo $separator; ?><a href="<?php echo esc_url($url); ?>"><bdi><?php echo esc_html($genre->name); ?></bdi></a><?php $separator = '، '; endforeach; ?></div></div><?php endif; ?>
      <?php foreach (['کارگردان' => $directors, 'نویسنده' => $writers] as $label => $people) : if (!$people) { continue; } ?><div><span><?php echo esc_html($label); ?></span><div><?php foreach ($people as $index => $person) : ?><?php echo $index ? '، ' : ''; ?><a href="<?php echo esc_url(get_permalink($person->post_id)); ?>"><bdi><?php echo esc_html(get_the_title($person->post_id) ?: $person->name); ?></bdi></a><?php endforeach; ?></div></div><?php endforeach; ?>
    </div><?php endif; ?>
    <?php get_template_part('template-parts/title-facts', null, ['item' => $item]); ?>
    <div class="pc-title-detail__badges">
      <?php if ($item->release_date) : ?><span><?php echo esc_html(substr($item->release_date, 0, 4)); ?></span><?php endif; ?>
      <?php if ($item->production_status) : ?><span class="pc-title-detail__badge-status" data-status="<?php echo esc_attr(popcorn_title_status_variant($item->production_status)); ?>"><?php echo esc_html(popcorn_core_persian_production_status($item->production_status, $item->popcorn_type)); ?></span><?php endif; ?>
      <?php if ($item->runtime) : ?><span><?php echo $item->popcorn_type === 'series' ? 'هر قسمت: ' : ''; ?><?php echo esc_html(number_format((int) $item->runtime)); ?> دقیقه</span><?php endif; ?>
      <?php if ($item->certification) : ?><span dir="ltr"><?php echo esc_html($item->certification); ?></span><?php endif; ?>
      <?php if ($item->popcorn_type === 'series' && $item->season_count) : ?><span><?php echo esc_html(number_format((int) $item->season_count)); ?> فصل · <?php echo esc_html(number_format((int) $item->episode_count)); ?> قسمت</span><?php endif; ?>
      <?php if ($features['dubbed']) : ?><span class="pc-title-detail__badge-dubbed"><?php echo popcorn_icon('microphone'); ?> <?php echo esc_html($badge_labels['dubbed']); ?></span><?php endif; ?>
      <?php if ($features['subtitle']) : ?><span class="pc-title-detail__badge-subtitle"><?php echo popcorn_icon('subtitles'); ?> <?php echo esc_html($badge_labels['subtitle']); ?></span><?php endif; ?>
      <?php $next_episode = $item->popcorn_type === 'series' ? popcorn_title_next_episode_time($item->next_episode_date ?? '') : ''; if ($next_episode) : ?><span>قسمت بعد: <?php echo esc_html($next_episode); ?></span><?php endif; ?>
    </div>
    <div class="pc-title-detail__actions" id="popcorn-playbacks">
      <?php if ($item->is_coming_soon) : ?><span class="pc-title-detail__coming-soon">به‌زودی</span><?php else : ?>
      <?php if ($args['play_url']) : ?><a class="pc-button pc-title-detail__play" href="<?php echo esc_url($args['play_url']); ?>"><?php echo popcorn_icon('play'); ?> پخش آنلاین</a><?php endif; ?>
      <?php if ($args['has_downloads']) : ?><a class="pc-button pc-button--outline" href="#popcorn-downloads"><?php echo popcorn_icon('download'); ?> دانلود</a><?php endif; ?>
      <?php endif; ?>
      <?php if ($args['trailer']) : ?><button class="pc-button pc-button--outline" type="button" data-pc-dialog-open="pc-title-trailer"><?php echo popcorn_icon('play'); ?> تریلر</button><?php endif; ?>
      <?php echo $args['favorite']; ?>
      <button class="pc-button pc-button--outline pc-title-detail__icon-button" type="button" data-pc-share aria-label="اشتراک‌گذاری" title="اشتراک‌گذاری"><?php echo popcorn_icon('share'); ?></button>
      <span class="pc-title-detail__share-status" data-pc-share-status role="status" aria-live="polite"></span>
    </div>
  </div>
</div>
