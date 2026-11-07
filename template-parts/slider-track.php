<?php
$items = is_array($args['items'] ?? null) ? $args['items'] : [];
$card = sanitize_key($args['card'] ?? 'card');
$sliding = !empty($args['sliding']);
$thumb_card = sanitize_key($args['thumb_card'] ?? '');
?>
<div class="pc-slider__track<?php echo $sliding ? ' swiper-wrapper' : ''; ?>">
  <?php foreach ($items as $index => $item) : ?>
  <div class="pc-slider__slide<?php echo $sliding ? ' swiper-slide' : ''; ?>"><?php get_template_part('template-parts/' . $card, null, ['item' => $item, 'index' => $index]); ?></div>
  <?php endforeach; ?>
</div>
<?php if ($sliding && $thumb_card) : ?>
<div class="pc-slider__thumbs <?php echo esc_attr($args['thumb_class'] ?? ''); ?>" role="group" aria-label="انتخاب اسلاید">
  <?php foreach ($items as $index => $item) { get_template_part('template-parts/' . $thumb_card, null, ['item' => $item, 'index' => $index]); } ?>
</div>
<?php endif; ?>
