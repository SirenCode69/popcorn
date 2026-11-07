<?php
$genre = $args['item'] ?? null;
if (!$genre || empty($genre['term']) || empty($genre['url'])) {
    return;
}
$term = $genre['term'];
$tone = absint($args['index'] ?? 0) % 8;
$image = !empty($genre['image_id']) ? popcorn_image($genre['image_id'], 'medium', ['alt' => '', 'class' => 'pc-genre-card__image']) : '';
?>
<a class="pc-genre-card pc-genre-card--tone-<?php echo $tone; ?>" href="<?php echo esc_url($genre['url']); ?>">
  <span class="pc-genre-card__copy">
    <strong><bdi><?php echo esc_html($term->name); ?></bdi></strong>
    <span>مشاهدهٔ آثار <?php echo popcorn_icon('left'); ?></span>
  </span>
  <span class="pc-genre-card__art" aria-hidden="true"><?php echo $image ?: popcorn_icon('film'); ?></span>
</a>
