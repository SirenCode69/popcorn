<?php
defined('ABSPATH') || exit;
$site_name = get_bloginfo('name');
$description = get_bloginfo('description') ?: 'جایی برای پیدا کردن قصهٔ بعدی.';
?>
<footer class="pc-footer" aria-label="پانوشت سایت">
  <div class="pc-container">
    <div class="pc-footer__main">
      <div class="pc-footer__identity">
        <a class="pc-footer__brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr($site_name); ?>">
          <?php get_template_part('template-parts/site-brand', null, ['image_class' => 'pc-footer__logo', 'mark_class' => 'pc-footer__mark']); ?>
        </a>
        <p class="pc-footer__description"><?php echo esc_html($description); ?></p>
      </div>
      <nav class="pc-footer__nav" aria-label="منوی فوتر">
        <h2>دسترسی سریع</h2>
        <?php if (has_nav_menu('footer')) : ?>
          <?php wp_nav_menu(['theme_location' => 'footer', 'container' => false, 'menu_class' => 'pc-footer__menu', 'fallback_cb' => false, 'depth' => 1]); ?>
        <?php else : ?>
          <ul class="pc-footer__menu">
            <li><a href="<?php echo esc_url(home_url('/')); ?>">خانه</a></li>
            <li><a href="<?php echo esc_url(function_exists('popcorn_catalog_url') ? popcorn_catalog_url('movie') : home_url('/films/')); ?>">فیلم‌ها</a></li>
            <li><a href="<?php echo esc_url(function_exists('popcorn_catalog_url') ? popcorn_catalog_url('series') : home_url('/series/')); ?>">سریال‌ها</a></li>
            <li><a href="<?php echo esc_url(get_post_type_archive_link('popcorn_person') ?: home_url('/artists/')); ?>">هنرمندان</a></li>
          </ul>
        <?php endif; ?>
      </nav>
      <nav class="pc-footer__nav" aria-label="منوی دوم فوتر">
        <h2>دسترسی‌های بیشتر</h2>
        <?php if (has_nav_menu('footer_secondary')) : ?>
          <?php wp_nav_menu(['theme_location' => 'footer_secondary', 'container' => false, 'menu_class' => 'pc-footer__menu', 'fallback_cb' => false, 'depth' => 1]); ?>
        <?php else : ?>
          <?php $footer_terms = taxonomy_exists('popcorn_genre') ? get_terms(['taxonomy' => 'popcorn_genre', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 4]) : []; ?>
          <ul class="pc-footer__menu">
            <?php if (!is_wp_error($footer_terms) && $footer_terms) : ?>
              <?php foreach ($footer_terms as $footer_term) : $footer_term_url = get_term_link($footer_term); if (is_wp_error($footer_term_url)) { continue; } ?>
                <li><a href="<?php echo esc_url($footer_term_url); ?>"><?php echo esc_html($footer_term->name); ?></a></li>
              <?php endforeach; ?>
            <?php else : ?>
              <?php wp_list_pages(['title_li' => '', 'depth' => 1, 'number' => 4]); ?>
            <?php endif; ?>
          </ul>
        <?php endif; ?>
      </nav>
      <div class="pc-footer__credit"><span>طراحی و توسعه</span><a href="https://github.com/SirenCode69/" target="_blank" rel="noopener noreferrer"><strong dir="ltr">SirenCode</strong></a><small>قالب پاپکورن</small></div>
    </div>
    <div class="pc-footer__bottom">
      <span><?php echo esc_html(popcorn_footer_text()); ?></span>
    </div>
  </div>
</footer>
