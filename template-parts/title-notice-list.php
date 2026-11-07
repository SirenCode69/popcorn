<?php $notices = $args['notices']; ?>
<ol>
  <?php foreach ($notices as $notice) : ?>
  <li><time datetime="<?php echo esc_attr(mysql2date('c', $notice->created_at, false)); ?>"><?php echo esc_html(mysql2date('j F Y، H:i', $notice->created_at)); ?></time><div><?php echo wpautop(esc_html($notice->message)); ?></div></li>
  <?php endforeach; ?>
</ol>
