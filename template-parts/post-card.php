<?php
defined('ABSPATH') || exit;
$entry = $args['post'] ?? $args['item'] ?? null;
if (!$entry instanceof WP_Post) {
    return;
}
$title = get_the_title($entry);
$image = get_the_post_thumbnail($entry->ID, popcorn_card_image_size(), ['alt' => $title, 'loading' => 'lazy']);
?>
<article class="pc-card">
  <a class="pc-card__link" href="<?php echo esc_url(get_permalink($entry)); ?>">
    <div class="pc-card__poster"><?php echo $image ?: '<span class="pc-card__placeholder">' . popcorn_icon('film') . '</span>'; ?><span class="pc-card__hover" aria-hidden="true"><strong><?php echo esc_html($title); ?></strong><span class="pc-card__overview"><?php echo esc_html(wp_trim_words(get_the_excerpt($entry), 38, '…')); ?></span><span class="pc-card__hover-action">مشاهدهٔ مطلب <?php echo popcorn_icon('left'); ?></span></span></div>
    <div class="pc-card__details"><h3 dir="auto" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></h3><div class="pc-card__meta"><span class="pc-card__year"><?php echo esc_html(get_the_date('', $entry)); ?></span></div></div>
  </a>
</article>
