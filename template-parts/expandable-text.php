<?php
$content = (string) ($args['content'] ?? '');
if (trim($content) === '') {
    return;
}
$id = wp_unique_id('pc-expandable-text-');
$lines = max(1, absint($args['lines'] ?? 4));
?>
<div class="pc-expandable-text" data-pc-expandable-text data-lines="<?php echo $lines; ?>">
  <div id="<?php echo esc_attr($id); ?>" class="pc-expandable-text__viewport"><div class="pc-expandable-text__content"><?php echo $content; ?></div></div>
  <button type="button" class="pc-expandable-text__toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($id); ?>" hidden><span>بیشتر</span><?php echo popcorn_icon('down'); ?></button>
</div>
