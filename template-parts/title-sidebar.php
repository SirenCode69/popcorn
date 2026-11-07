<?php
$item = $args['item'];
$notices = $args['notices'];
?>
<div class="pc-title-detail__sidebar">
  <div class="pc-title-detail__rating" id="popcorn-engagement">
    <?php get_template_part('template-parts/rating-badges', null, ['item' => $item]); ?>
    <?php echo $args['rating']; ?>
  </div>
  <?php if ($notices) : ?>
  <aside class="pc-title-notices pc-title-detail__notice" aria-labelledby="pc-title-notice-heading">
    <div class="pc-title-detail__notice-heading"><h2 id="pc-title-notice-heading">اطلاعیه‌های این اثر</h2><button type="button" data-pc-dialog-open="pc-title-notices" aria-haspopup="dialog" aria-controls="pc-title-notices">بیشتر <?php echo popcorn_icon('left'); ?></button></div>
    <?php get_template_part('template-parts/title-notice-list', null, ['notices' => array_slice($notices, 0, 1)]); ?>
  </aside>
  <?php endif; ?>
  <?php popcorn_theme_ad('title_sidebar'); ?>
</div>
