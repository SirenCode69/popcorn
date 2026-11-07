<?php popcorn_theme_ad('footer', true); ?>
<?php if (!function_exists('elementor_theme_do_location') || !elementor_theme_do_location('footer')) : ?>
<?php get_template_part('template-parts/footer'); ?>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
