<?php
defined('ABSPATH') || exit;
global $wp_query;
$limit = (int) popcorn_core_search_options()['live_limit'];
$groups = popcorn_core_live_search_groups($wp_query->posts, $limit);
$labels = ['post' => ['اخبار', 'newspaper'], 'popcorn_movie' => ['فیلم‌ها', 'film'], 'popcorn_series' => ['سریال‌ها', 'tv'], 'popcorn_person' => ['هنرمندان', 'user'], 'page' => ['برگه‌ها', 'file']];
$total = array_sum(array_map('count', $groups));
$all_url = add_query_arg('s', get_search_query(false), home_url('/'));
?>
<div class="pc-live-results__header"><span class="pc-live-results__header-icon"><?php echo popcorn_icon('search'); ?></span><strong>پیشنهادهای جست‌وجو</strong><small><?php echo esc_html(number_format_i18n($total)); ?> نتیجه</small></div>
<?php if ($total) : ?>
    <div class="pc-live-results__list">
        <?php foreach ($groups as $type => $ids) : ?>
            <?php foreach ($ids as $index => $post_id) : ?>
                <?php get_template_part('template-parts/search-suggestion', null, ['post_id' => $post_id, 'group' => $index === 0 ? $labels[$type] : null, 'group_count' => count($ids)]); ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php else : ?>
    <div class="pc-live-results__empty" role="status"><span><?php echo popcorn_icon('search'); ?></span><strong>نتیجه‌ای پیدا نشد</strong><small>نام دیگری را امتحان کنید.</small></div>
<?php endif; ?>
<a class="pc-live-results__all" href="<?php echo esc_url($all_url); ?>"><span>مشاهدهٔ همهٔ نتایج</span><?php echo popcorn_icon('left'); ?></a>
