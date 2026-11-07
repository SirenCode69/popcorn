<?php
$post_id = absint($args['post_id'] ?? 0);
$post = get_post($post_id);
if (!$post || $post->post_status !== 'publish') {
    return;
}
$post_type = $post->post_type;
$labels = ['post' => 'خبر', 'popcorn_movie' => 'فیلم', 'popcorn_series' => 'سریال', 'popcorn_person' => 'هنرمند', 'page' => 'برگه'];
if (!isset($labels[$post_type])) {
    return;
}
$type = str_replace('popcorn_', '', $post_type);
$item = in_array($type, ['movie', 'series', 'person'], true) && function_exists('popcorn_core_get_by_post') ? popcorn_core_get_by_post($type, $post_id) : null;
if (strpos($post_type, 'popcorn_') === 0 && !$item) {
    return;
}
$title = $item ? (trim((string) ($type === 'person' ? $item->name : $item->title)) ?: get_the_title($post_id)) : get_the_title($post_id);
$image_size = popcorn_compact_image_size();
$image = $item ? ($type === 'person' ? popcorn_content_image($item->profile_id, $item->profile_path, $image_size, ['alt' => $title]) : popcorn_content_image($item->poster_id, $item->poster_path, $image_size, ['alt' => $title])) : popcorn_image(get_post_thumbnail_id($post_id), $image_size, ['alt' => $title]);
$icon = ['post' => 'newspaper', 'popcorn_movie' => 'film', 'popcorn_series' => 'tv', 'popcorn_person' => 'user', 'page' => 'file'][$post_type];
$subtitle = '';
$badge = '';
if ($type === 'movie' || $type === 'series') {
    $persian_title = trim((string) $item->original_title);
    $subtitle = $persian_title !== $title && preg_match('/[\x{0600}-\x{06FF}]/u', $persian_title) ? $persian_title : '';
    $badge = $item->release_date ? substr($item->release_date, 0, 4) : '';
} elseif ($type === 'person') {
    $persian_name = trim((string) $item->persian_name);
    $profession = popcorn_core_profession_label($item->profession);
    $subtitle = $persian_name && $persian_name !== $title && preg_match('/[\x{0600}-\x{06FF}]/u', $persian_name) ? $persian_name . ($profession ? ' · ' . $profession : '') : $profession;
} elseif ($post_type === 'post') {
    $subtitle = get_the_date('Y/m/d', $post_id);
} else {
    $subtitle = 'صفحهٔ سایت';
}
$group = is_array($args['group'] ?? null) ? $args['group'] : null;
?>
<div class="relevanssi-live-search-result pc-live-result pc-live-result--<?php echo esc_attr($type); ?>" role="option" aria-selected="false" aria-label="<?php echo esc_attr($labels[$post_type] . ': ' . $title); ?>" data-postype="<?php echo esc_attr($post_type); ?>" data-postid="<?php echo $post_id; ?>">
    <?php if ($group) : ?><div class="pc-live-result__group" aria-hidden="true"><span class="pc-live-result__group-name"><?php echo popcorn_icon($group[1]); ?><?php echo esc_html($group[0]); ?></span><span class="pc-live-result__group-count"><?php echo esc_html(number_format_i18n(absint($args['group_count'] ?? 0))); ?></span></div><?php endif; ?>
    <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="pc-live-result__link">
        <span class="pc-live-result__image"><?php echo $image ?: popcorn_icon($icon); ?></span>
        <span class="pc-live-result__text"><strong><?php echo esc_html($title); ?></strong><?php if ($subtitle) : ?><small><?php echo esc_html($subtitle); ?></small><?php endif; ?></span>
        <?php if ($badge) : ?><span class="pc-live-result__badge"><?php echo esc_html($badge); ?></span><?php endif; ?>
        <span class="pc-live-result__arrow"><?php echo popcorn_icon('left'); ?></span>
    </a>
</div>
