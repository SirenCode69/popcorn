<?php
$display_overview = $args['overview'];
?>
<section class="pc-title-detail__about pc-detail__copy" aria-label="داستان و اطلاعات اثر">
  <div class="pc-detail__overview"><?php get_template_part('template-parts/expandable-text', null, ['content' => wpautop(esc_html($display_overview ?: 'توضیحی برای این عنوان ثبت نشده است.')), 'lines' => 4]); ?></div>
</section>
