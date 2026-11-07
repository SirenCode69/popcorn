<?php
defined('ABSPATH') || exit;
if (post_password_required() || (!comments_open() && !get_comments_number())) {
    return;
}
?>
<section id="comments" class="pc-comments" aria-labelledby="pc-comments-title"<?php if (function_exists('popcorn_core_comment_vote_counts')) : ?> data-vote-url="<?php echo esc_url(admin_url('admin-ajax.php', 'relative')); ?>" data-post-id="<?php echo absint(get_the_ID()); ?>"<?php endif; ?>>
  <header class="pc-comments__head"><h2 id="pc-comments-title">دیدگاه‌ها</h2><?php if (get_comments_number()) : ?><span class="pc-comments__count"><?php echo esc_html(number_format((int) get_comments_number())); ?> دیدگاه</span><?php endif; ?></header>
  <?php if (have_comments()) : ?>
    <?php if (function_exists('popcorn_core_comment_vote_counts')) { popcorn_core_comment_vote_counts(0, $wp_query->comments); } ?>
    <ol class="pc-comments__list"><?php wp_list_comments(['style' => 'ol', 'format' => 'html5', 'callback' => 'popcorn_render_comment', 'short_ping' => true, 'avatar_size' => 48]); ?></ol>
    <div class="pc-comments__pagination"><?php the_comments_pagination(['mid_size' => 1, 'prev_text' => popcorn_icon('right') . '<span class="screen-reader-text">دیدگاه‌های قبلی</span>', 'next_text' => popcorn_icon('left') . '<span class="screen-reader-text">دیدگاه‌های بعدی</span>', 'screen_reader_text' => 'صفحه‌بندی دیدگاه‌ها', 'aria_label' => 'صفحه‌بندی دیدگاه‌ها']); ?></div>
  <?php endif; ?>
  <?php if (!comments_open() && get_comments_number()) : ?><p class="pc-comments__closed">ارسال دیدگاه برای این صفحه بسته است.</p><?php endif; ?>
  <?php comment_form(popcorn_comment_form_options()); ?>
</section>
