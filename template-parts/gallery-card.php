<?php
$image = $args['item'] ?? [];
$label = $image['title'] ?? 'تصویر گالری';
$thumbnail = popcorn_content_image(absint($image['attachment_id'] ?? 0), $image['path'] ?? '', 'medium_large', ['alt' => $label]);
if (!$thumbnail) { return; }
$full = popcorn_content_image(absint($image['attachment_id'] ?? 0), $image['path'] ?? '', 'full', ['alt' => $label]);
?>
<button class="pc-gallery-card" type="button" data-pc-gallery-open aria-label="<?php echo esc_attr('نمایش ' . $label); ?>"><?php echo $thumbnail; ?><template><?php echo $full; ?></template><span><?php echo popcorn_icon('image'); ?></span></button>
