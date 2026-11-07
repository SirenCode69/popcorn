<?php
defined('ABSPATH') || exit;
$result_posts = $args['posts'] ?? [];
$items = [];
foreach ($args['items'] ?? [] as $item) {
    $items[(int) $item->post_id] = $item;
}
?>
<?php if ($result_posts) : ?><div class="pc-grid<?php echo ($args['type'] ?? '') === 'person' ? ' pc-grid--people' : ''; ?>">
  <?php foreach ($result_posts as $entry) {
      $item = $items[$entry->ID] ?? null;
      if ($item && in_array($item->popcorn_type, ['movie', 'series'], true)) {
          get_template_part('template-parts/card', null, ['item' => $item]);
      } elseif ($item && $item->popcorn_type === 'person') {
          get_template_part('template-parts/person-card', null, ['person' => $item]);
      } else {
          get_template_part('template-parts/post-card', null, ['post' => $entry]);
      }
  } ?>
</div><?php else : ?><div class="pc-archive__empty"><?php echo popcorn_icon('film'); ?><p><?php echo ($args['type'] ?? '') === 'all' ? 'محتوایی با این مشخصات پیدا نشد.' : (($args['type'] ?? '') === 'series' ? 'سریالی با این مشخصات پیدا نشد.' : 'فیلمی با این مشخصات پیدا نشد.'); ?></p></div><?php endif; ?>
