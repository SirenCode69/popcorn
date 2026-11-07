<?php
$label = trim((string) ($args['label'] ?? 'در حال بارگذاری'));
$size = in_array($args['size'] ?? '', ['small', 'large'], true) ? $args['size'] : '';
?>
<span class="pc-loader<?php echo $size ? ' pc-loader--' . esc_attr($size) : ''; ?>" role="status"><span class="pc-loader__bar" aria-hidden="true"></span><span class="pc-loader__bar" aria-hidden="true"></span><span class="pc-loader__bar" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_html($label); ?></span></span>
