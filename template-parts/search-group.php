<?php
defined('ABSPATH') || exit;
$type = $args['type'];
$posts = $args['posts'];
$is_content = in_array($type, ['popcorn_movie', 'popcorn_series', 'popcorn_person'], true);
$ids = wp_list_pluck($posts, 'ID');
$items = $is_content ? array_values(array_filter($args['items'], static function ($item) use ($ids) { return in_array((int) $item->post_id, $ids, true); })) : $posts;
$card = $type === 'post' ? 'blog-card' : ($type === 'page' ? 'post-card' : ($type === 'popcorn_person' ? 'person-card' : 'card'));
$layout = $type === 'post' ? 'stories' : ($type === 'popcorn_person' ? 'people' : 'posters');
get_template_part('template-parts/slider', null, [
    'items' => $items,
    'card' => $card,
    'heading' => $args['title'],
    'heading_count' => $args['count'],
    'more_url' => $args['more_url'],
    'more_label' => 'نتایج بیشتر',
    'navigation' => true,
    'section_class' => 'pc-search-results__group pc-slider-section--' . $layout,
    'id' => 'pc-search-' . sanitize_html_class($type),
]);
