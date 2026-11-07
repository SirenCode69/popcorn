<?php
$url = $args['url'] ?? '';
if (!$url) { return; }
?>
<a class="pc-imdb-profile" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" aria-label="پروفایل IMDb"><span>پروفایل</span><?php echo popcorn_icon('imdb'); ?></a>
