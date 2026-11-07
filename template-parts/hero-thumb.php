<?php
$item = $args['item'] ?? null;
if (!$item) {
    return;
}
$index = absint($args['index'] ?? 0);
$title = popcorn_display_title($item);
$image = popcorn_content_image($item->backdrop_id ?: $item->poster_id, $item->backdrop_path ?: $item->poster_path, 'medium', ['alt' => popcorn_item_seo_image_alt($item), 'loading' => $index < 2 ? 'eager' : 'lazy']);
?>
<button type="button" class="pc-hero__thumb<?php echo $index === 0 ? ' is-active' : ''; ?>" data-slide-index="<?php echo $index; ?>" aria-label="نمایش <?php echo esc_attr($title); ?>" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"><?php echo $image ?: popcorn_icon('film'); ?></button>
