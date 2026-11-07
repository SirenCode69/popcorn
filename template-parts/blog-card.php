<?php
defined('ABSPATH') || exit;
$news_post = get_post($args['item'] ?? get_the_ID());
if (!$news_post) {
    return;
}
$post_id = $news_post->ID;
$categories = get_the_category($post_id);
$title = get_the_title($post_id);
$permalink = get_permalink($post_id);
?>
<article <?php post_class('pc-blog-card', $post_id); ?>>
  <a class="pc-blog-card__image" href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>"><?php if (has_post_thumbnail($post_id)) { echo get_the_post_thumbnail($post_id, 'medium_large', ['alt' => popcorn_blog_image_alt($post_id)]); } else { echo '<span class="pc-blog-card__placeholder">' . popcorn_icon('newspaper') . '</span>'; } ?></a>
  <div class="pc-blog-card__body">
    <div class="pc-blog-card__meta"><?php if ($categories) : ?><a href="<?php echo esc_url(get_category_link($categories[0])); ?>"><?php echo esc_html($categories[0]->name); ?></a><?php endif; ?><time datetime="<?php echo esc_attr(get_the_date('c', $post_id)); ?>"><?php echo esc_html(human_time_diff(get_post_time('U', true, $news_post), time()) . ' پیش'); ?></time></div>
    <h2><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h2>
    <p><?php echo esc_html(post_password_required($news_post) ? 'این نوشته با رمز محافظت شده است.' : wp_trim_words(get_the_excerpt($news_post), 28)); ?></p>
  </div>
</article>
