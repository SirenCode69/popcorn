<?php
defined('ABSPATH') || exit;
$image_class = $args['image_class'] ?? 'pc-brand__image';
$mark_class = $args['mark_class'] ?? 'pc-brand__mark';
?>
<?php if (has_custom_logo()) : ?>
  <?php echo popcorn_image(get_theme_mod('custom_logo'), 'full', ['class' => $image_class, 'alt' => get_bloginfo('name')]); ?>
<?php else : ?>
  <span class="<?php echo esc_attr($mark_class); ?>"><?php echo popcorn_icon('popcorn'); ?></span><span>پاپکورن<small>SirenCode</small></span>
<?php endif; ?>
