<?php
defined('ABSPATH') || exit;
get_header();
?>
<main id="main" class="pc-container pc-blog-single">
<?php while (have_posts()) : the_post();
    $locked = post_password_required();
    $content = popcorn_blog_toc_content(apply_filters('the_content', get_the_content()), get_the_ID());
    $related = $locked ? [] : popcorn_blog_side_posts(get_the_ID(), true);
    $recent = popcorn_blog_side_posts(get_the_ID());
?>
  <nav class="pc-blog-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url(home_url('/')); ?>">خانه</a><span>/</span><a href="<?php echo esc_url(popcorn_blog_url()); ?>">وبلاگ</a><span>/</span><span aria-current="page"><?php the_title(); ?></span></nav>
  <div class="pc-blog-layout<?php echo $content['items'] && !$locked ? ' pc-blog-layout--toc' : ''; ?><?php echo !$related && !$recent ? ' pc-blog-layout--no-side' : ''; ?>">
    <?php if ($content['items'] && !$locked) : ?><aside class="pc-blog-toc" aria-label="فهرست محتوا"><div class="pc-blog-panel"><h2>فهرست محتوا</h2><nav><?php foreach ($content['items'] as $item) : ?><a class="pc-blog-toc__level-<?php echo (int) $item['level']; ?>" href="#<?php echo esc_attr($item['id']); ?>"><?php echo esc_html($item['text']); ?></a><?php endforeach; ?></nav></div></aside><?php endif; ?>
    <article <?php post_class('pc-blog-main'); ?>>
      <?php if (has_post_thumbnail()) : ?><div class="pc-blog-cover"><?php echo get_the_post_thumbnail(null, 'large', ['alt' => popcorn_blog_image_alt(), 'loading' => 'eager', 'fetchpriority' => 'high']); ?></div><?php endif; ?>
      <header><h1><?php the_title(); ?></h1><div class="pc-blog-meta"><?php foreach (get_the_category() as $category) : ?><a class="pc-blog-chip" href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a><?php endforeach; ?><?php if (!$locked) : ?><span>زمان مطالعه: <?php echo popcorn_blog_reading_time(get_post()); ?> دقیقه</span><?php endif; ?><button class="pc-blog-share" type="button" data-blog-share data-blog-url="<?php the_permalink(); ?>" aria-label="کپی لینک نوشته"><?php echo popcorn_icon('share'); ?></button><span class="screen-reader-text" data-blog-share-status role="status"></span></div></header>
      <div class="pc-blog-content"><?php echo $content['html']; ?></div>
      <?php wp_link_pages(['before' => '<nav class="pc-pagination" aria-label="صفحه‌های نوشته">', 'after' => '</nav>', 'link_before' => '<span class="page-numbers">', 'link_after' => '</span>']); ?>
      <?php if (!$locked && get_the_tags()) : ?><nav class="pc-blog-tags" aria-label="برچسب‌های نوشته"><?php foreach (get_the_tags() as $tag) : ?><a class="pc-blog-chip" href="<?php echo esc_url(get_tag_link($tag)); ?>">#<?php echo esc_html($tag->name); ?></a><?php endforeach; ?></nav><?php endif; ?>
    </article>
    <?php if ($related || $recent) : ?><aside class="pc-blog-side" aria-label="نوشته‌های دیگر"><?php foreach (['از بین نوشته‌های مرتبط' => $related, 'جدیدترین نوشته‌ها' => $recent] as $label => $side_posts) : ?><?php if ($side_posts) : ?><section class="pc-blog-panel"><h2><?php echo esc_html($label); ?></h2><div class="pc-blog-side__list"><?php foreach ($side_posts as $story) : ?><a href="<?php echo esc_url(get_permalink($story)); ?>"><?php echo popcorn_icon('left'); ?><span><?php echo esc_html(get_the_title($story)); ?></span></a><?php endforeach; ?></div></section><?php endif; ?><?php endforeach; ?></aside><?php endif; ?>
  </div>
  <?php popcorn_theme_ad('post_content'); ?>
  <?php if (comments_open() || get_comments_number()) { comments_template(); } ?>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
