<?php
$comment = $args['comment'];
$options = $args['options'];
$depth = $args['depth'];
$is_ping = in_array(get_comment_type($comment), ['pingback', 'trackback'], true);
$is_author = $comment->user_id && (int) $comment->user_id === (int) get_post_field('post_author', $comment->comment_post_ID);
$has_votes = !$is_ping && (string) $comment->comment_approved === '1' && function_exists('popcorn_core_comment_vote_counts');
$votes = $has_votes ? popcorn_core_comment_vote_counts($comment->comment_ID) : [];
?>
<li <?php comment_class('pc-comment', $comment); ?> id="comment-<?php echo absint($comment->comment_ID); ?>">
  <article id="div-comment-<?php echo absint($comment->comment_ID); ?>" class="pc-comment__body">
    <header class="pc-comment__head">
      <?php if (!$is_ping && get_option('show_avatars')) : ?><div class="pc-comment__avatar"><?php echo get_avatar($comment, 48, '', '', ['loading' => 'lazy', 'extra_attr' => 'decoding="async"']); ?></div><?php endif; ?>
      <div class="pc-comment__meta"><div class="pc-comment__author"><?php echo get_comment_author_link($comment); ?><?php if ($is_author) : ?><span class="pc-comment__badge">نویسنده</span><?php endif; ?><?php if ($is_ping) : ?><span class="pc-comment__badge">بازتاب</span><?php endif; ?></div><a class="pc-comment__date" href="<?php echo esc_url(get_comment_link($comment)); ?>"><time datetime="<?php echo esc_attr(get_comment_date('c', $comment)); ?>"><?php echo esc_html(get_comment_date('', $comment) . '، ' . get_comment_time('', false, true, $comment)); ?></time></a></div>
      <?php if ($has_votes) : ?><div class="pc-comment__votes" data-comment-votes data-comment-id="<?php echo absint($comment->comment_ID); ?>" role="group" aria-label="رأی به دیدگاه">
        <p class="pc-comments__vote-status" data-comment-vote-status role="status" aria-live="polite"></p>
        <div class="pc-comment__vote-buttons">
        <button type="button" data-comment-vote="1" aria-label="لایک، <?php echo esc_attr(number_format((int) $votes['likes'])); ?> رأی" aria-pressed="false"><?php echo popcorn_icon('thumb-up'); ?><span data-vote-count><?php echo esc_html(number_format((int) $votes['likes'])); ?></span></button>
        <button type="button" data-comment-vote="-1" aria-label="دیسلایک، <?php echo esc_attr(number_format((int) $votes['dislikes'])); ?> رأی" aria-pressed="false"><?php echo popcorn_icon('thumb-down'); ?><span data-vote-count><?php echo esc_html(number_format((int) $votes['dislikes'])); ?></span></button>
        </div>
      </div><?php endif; ?>
    </header>
    <?php if ($comment->comment_approved === '0') : ?><p class="pc-comment__moderation" role="status">دیدگاه شما در انتظار تأیید مدیر است.</p><?php endif; ?>
    <?php if (!$is_ping) : ?><div class="pc-comment__content" dir="auto"><?php comment_text($comment, $options); ?></div><?php endif; ?>
    <footer class="pc-comment__actions">
      <?php if (!$is_ping) {
          if (comments_open($comment->comment_post_ID) && $depth < $options['max_depth'] && get_option('comment_registration') && !is_user_logged_in()) {
              $login = popcorn_public_auth_enabled() ? popcorn_auth_login_url(get_comment_link($comment)) : '';
              if ($login) {
                  echo '<a class="comment-reply-login" href="' . esc_url($login) . '">ورود برای پاسخ</a>';
              }
          } else {
              comment_reply_link(array_merge($options, ['add_below' => 'div-comment', 'depth' => $depth, 'reply_text' => popcorn_icon('reply') . ' پاسخ', 'login_text' => 'ورود برای پاسخ', 'reply_to_text' => 'پاسخ به %s']), $comment);
          }
      }
      if (popcorn_public_auth_enabled()) {
          edit_comment_link('ویرایش', '', '', $comment->comment_ID);
      } ?>
    </footer>
  </article>
