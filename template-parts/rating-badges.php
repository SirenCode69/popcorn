<?php
$item = $args['item'];
$sources = [
    'imdb' => ['IMDb', 'imdb-circle', $item->imdb_rating ?? 0, 1],
    'tmdb' => ['TMDb', 'tmdb-circle', $item->vote_average ?? 0, 1],
    'rotten-tomatoes' => ['Rotten Tomatoes', 'rotten-tomatoes-circle', $item->rotten_tomatoes_rating ?? 0, 0],
    'metacritic' => ['Metacritic', 'metacritic-circle', $item->metacritic_rating ?? 0, 0],
    'site' => ['امتیاز سایت', 'star', $item->rating ?? 0, 1],
];
$site_icon = get_site_icon_url(32);
?>
<div class="pc-rating-badges" aria-label="امتیازهای منابع">
  <?php foreach ($sources as $source => [$label, $icon, $value, $decimals]) : $number = (float) $value > 0 ? number_format((float) $value, $decimals, '.', '') : '—'; ?>
  <span class="pc-rating-badges__item" title="<?php echo esc_attr($label); ?>" aria-label="<?php echo esc_attr($label . ': ' . $number); ?>"<?php if ($source === 'site') : ?> data-site-rating-post="<?php echo absint($item->post_id); ?>"<?php endif; ?>>
    <span class="pc-rating-badges__icon pc-rating-badges__icon--<?php echo esc_attr($source); ?>"><?php if ($source === 'site' && $site_icon) : ?><img src="<?php echo esc_url($site_icon); ?>" alt="" width="28" height="28" loading="lazy" decoding="async"><?php else : ?><?php echo popcorn_icon($icon); ?><?php endif; ?></span>
    <strong dir="ltr"<?php if ($source === 'site') : ?> data-site-rating-value<?php endif; ?>><?php echo esc_html($number); ?></strong>
  </span>
  <?php endforeach; ?>
</div>
