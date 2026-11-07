<?php
$post = $args['post'] ?? null;
if (!($post instanceof WP_Post) || $post->post_status !== 'publish') {
    return;
}
$post_id = (int) $post->ID;
$is_news = $post->post_type === 'post';
$title = get_the_title($post_id);
$image = popcorn_image(get_post_thumbnail_id($post_id), 'medium_large', ['alt' => $title]);
$excerpt = wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 22);
?>
<article class="pc-post pc-search-story"><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><span class="pc-search-story__media"><?php echo $image ?: popcorn_icon($is_news ? 'newspaper' : 'file'); ?></span><span class="pc-search-story__meta"><?php echo $is_news ? 'خبر · ' . esc_html(get_the_date('Y/m/d', $post_id)) : 'برگهٔ سایت'; ?></span><h2><?php echo esc_html($title); ?></h2><?php if ($excerpt) : ?><p><?php echo esc_html($excerpt); ?></p><?php endif; ?></a></article>
