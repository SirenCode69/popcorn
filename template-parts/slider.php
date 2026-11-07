<?php
$items = $args['items'] ?? [];
$empty_message = (string) ($args['empty_message'] ?? '');
$async_url = esc_url_raw($args['async_url'] ?? '');
$hide_empty = !empty($args['hide_empty']);
if ($hide_empty && !$items && !$async_url) {
    return;
}
if (!is_array($items) || (!$items && !$empty_message && !$async_url)) {
    return;
}
$card = sanitize_key($args['card'] ?? 'card');
$variant = ($args['variant'] ?? 'cards') === 'hero' ? 'hero' : 'cards';
$heading = (string) ($args['heading'] ?? '');
$more_url = (string) ($args['more_url'] ?? '');
$extra_links = is_array($args['extra_links'] ?? null) ? $args['extra_links'] : [];
$label = (string) ($args['label'] ?? $heading);
$id = sanitize_html_class($args['id'] ?? wp_unique_id('pc-slider-'));
$direction = ($args['direction'] ?? 'rtl') === 'ltr' ? 'ltr' : 'rtl';
$sliding = count($items) > 1;
$navigation = ($sliding || $async_url) && !empty($args['navigation']);
$thumb_card = sanitize_key($args['thumb_card'] ?? '');
if ($card === 'card' && $items) {
    popcorn_card_features(0, $items);
}
?>
<section class="pc-slider-section <?php echo esc_attr($args['section_class'] ?? ''); ?>" <?php echo $heading ? 'aria-labelledby="' . esc_attr($id . '-heading') . '"' : 'aria-label="' . esc_attr($label) . '"'; ?><?php echo $async_url ? ' data-pc-slider-url="' . esc_url($async_url) . '"' : ''; ?><?php echo $hide_empty ? ' data-pc-slider-hide-empty' : ''; ?><?php echo $async_url && function_exists('popcorn_core_cache_plugin_active') && popcorn_core_cache_plugin_active() ? ' data-pc-slider-cache="plugin"' : ''; ?>>
  <?php if ($heading || $navigation || $more_url || $extra_links) : ?>
  <div class="pc-slider__head">
    <?php if ($heading) : ?><h2 id="<?php echo esc_attr($id . '-heading'); ?>"><?php echo esc_html($heading); ?><?php if (isset($args['heading_count'])) : ?> <span class="pc-slider__count"><?php echo esc_html(number_format(absint($args['heading_count']))); ?></span><?php endif; ?></h2><?php endif; ?>
    <?php if ($navigation || $more_url || $extra_links) : ?>
    <div class="pc-slider__tools">
    <?php if ($more_url) : ?><a class="pc-slider__more" href="<?php echo esc_url($more_url); ?>"><?php echo esc_html($args['more_label'] ?? 'مشاهدهٔ همه'); ?><?php echo popcorn_icon($direction === 'rtl' ? 'left' : 'right'); ?></a><?php endif; ?>
    <?php foreach ($extra_links as $link) : ?>
    <?php if (!empty($link['url']) && !empty($link['label'])) : ?><a class="pc-slider__more" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?><?php echo popcorn_icon($direction === 'rtl' ? 'left' : 'right'); ?></a><?php endif; ?>
    <?php endforeach; ?>
    <?php if ($navigation) : ?>
    <div class="pc-slider__navigation" hidden>
      <button type="button" data-pc-slider-prev aria-label="<?php echo esc_attr($args['prev_label'] ?? 'موارد قبلی'); ?>"><?php echo popcorn_icon($direction === 'rtl' ? 'right' : 'left'); ?></button>
      <button type="button" data-pc-slider-next aria-label="<?php echo esc_attr($args['next_label'] ?? 'موارد بعدی'); ?>"><?php echo popcorn_icon($direction === 'rtl' ? 'left' : 'right'); ?></button>
    </div>
    <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ($items || $async_url) : ?>
  <div id="<?php echo esc_attr($id); ?>" class="pc-slider <?php echo esc_attr($args['class'] ?? ''); ?><?php echo $sliding ? ' swiper' : ''; ?>" data-pc-slider="<?php echo esc_attr($variant); ?>" dir="<?php echo esc_attr($direction); ?>">
    <?php if ($async_url) : ?>
    <div class="pc-slider__status" data-pc-slider-status>
      <span data-pc-slider-loading><?php get_template_part('template-parts/loader', null, ['label' => 'در حال دریافت ' . ($heading ?: 'آثار')]); ?></span>
      <button type="button" data-pc-slider-retry hidden>بارگذاری دوباره</button>
    </div>
    <?php else : ?>
    <?php get_template_part('template-parts/slider-track', null, ['items' => $items, 'card' => $card, 'sliding' => $sliding, 'thumb_card' => $thumb_card, 'thumb_class' => $args['thumb_class'] ?? '']); ?>
    <?php endif; ?>
  </div>
  <?php if ($async_url) : ?><div class="pc-empty pc-slider__empty" data-pc-slider-empty hidden><p><?php echo esc_html($empty_message); ?></p></div><?php endif; ?>
  <?php else : ?>
  <div class="pc-empty pc-slider__empty"><p><?php echo esc_html($empty_message); ?></p></div>
  <?php endif; ?>
</section>
