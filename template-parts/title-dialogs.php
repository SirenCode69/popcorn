<?php
$item = $args['item'];
$trailer = $args['trailer'];
?>
<?php if ($args['notices']) : ?>
<dialog id="pc-title-notices" class="pc-title-dialog" aria-labelledby="pc-title-notices-heading">
  <div class="pc-title-dialog__head"><h2 id="pc-title-notices-heading">اطلاعیه‌های این اثر</h2><button type="button" data-pc-dialog-close aria-label="بستن"><?php echo popcorn_icon('close'); ?></button></div>
  <div class="pc-title-notices pc-title-detail__notice-history"><?php get_template_part('template-parts/title-notice-list', null, ['notices' => $args['notices']]); ?></div>
</dialog>
<?php endif; ?>
<?php if ($trailer) : ?>
<dialog id="pc-title-trailer" class="pc-title-dialog pc-title-dialog--media" aria-labelledby="pc-title-trailer-heading">
  <div class="pc-title-dialog__head"><h2 id="pc-title-trailer-heading">تریلر</h2><button type="button" data-pc-dialog-close aria-label="بستن"><?php echo popcorn_icon('close'); ?></button></div>
  <div class="pc-title-dialog__media">
    <?php if ($trailer['kind'] === 'embed') : ?><iframe data-src="<?php echo esc_url($trailer['src']); ?>" title="تریلر <?php echo esc_attr(get_the_title($item->post_id)); ?>" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe><?php else : ?><template data-pc-trailer-player><?php echo popcorn_title_trailer_player($trailer['src']); ?></template><?php endif; ?>
  </div>
</dialog>
<?php endif; ?>
<?php if ($args['has_gallery']) : ?>
<dialog id="pc-title-image" class="pc-title-dialog pc-title-dialog--image" aria-label="نمایش تصویر گالری">
  <div class="pc-title-dialog__head"><span>تصاویر</span><button type="button" data-pc-dialog-close aria-label="بستن"><?php echo popcorn_icon('close'); ?></button></div>
  <div data-pc-gallery-loading hidden><?php get_template_part('template-parts/loader', null, ['label' => 'دریافت تصاویر گالری', 'size' => 'small']); ?></div>
  <p data-pc-gallery-status role="status" aria-live="polite"></p>
  <div data-pc-gallery-image></div>
  <div class="pc-title-dialog__gallery-controls" hidden><button type="button" data-pc-gallery-prev aria-label="تصویر قبلی"><?php echo popcorn_icon('right'); ?></button><span data-pc-gallery-count dir="ltr" aria-live="polite"></span><button type="button" data-pc-gallery-next aria-label="تصویر بعدی"><?php echo popcorn_icon('left'); ?></button></div>
</dialog>
<?php endif; ?>
