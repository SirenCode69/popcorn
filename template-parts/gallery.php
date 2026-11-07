<?php
$gallery = $args['gallery'] ?? [];
if (!$gallery) { return; }
?>
<section class="pc-section pc-detail__gallery"><div class="pc-section__head"><h2>گالری تصاویر</h2></div><div class="pc-detail__gallery-grid">
  <?php foreach ($gallery as $image) : $html = popcorn_content_image(absint($image['attachment_id'] ?? 0), $image['path'] ?? '', 'medium_large', ['alt' => ($args['title'] ?? '') . ' - تصویر گالری']); if ($html) : ?><div><?php echo $html; ?></div><?php endif; endforeach; ?>
</div></section>
