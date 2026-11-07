<?php
$item = $args['item'];
?>
<dl class="pc-detail__facts">
  <?php foreach (['country' => 'کشور', 'language' => 'زبان', 'studio' => 'استودیو', 'network' => 'شبکه'] as $field => $label) : if (empty($item->$field)) { continue; } ?><div><dt><?php echo esc_html($label); ?></dt><dd><bdi><?php echo esc_html($item->$field); ?></bdi></dd></div><?php endforeach; ?>
</dl>
