<?php
$person = $args['person'] ?? $args['item'] ?? null;
if (!$person) { return; }
$name = get_the_title($person->post_id) ?: ($person->persian_name ?: $person->name);
$attributes = ['alt' => $name, 'loading' => 'lazy'];
if (popcorn_person_is_deceased($person)) {
    $attributes['class'] = 'pc-person-portrait--deceased';
}
$image = get_the_post_thumbnail($person->post_id, popcorn_person_image_size(), $attributes);
$image = $image ?: popcorn_content_image($person->profile_id, $person->profile_path ?? '', popcorn_person_image_size(), $attributes);
$subtitle = !empty($person->character_name) ? $person->character_name : (function_exists('popcorn_core_profession_label') ? popcorn_core_profession_label($person->profession ?? '') : '');
$work_counts = function_exists('popcorn_core_person_work_counts') ? popcorn_core_person_work_counts([$person->id]) : [];
$work_count = $work_counts[(int) $person->id] ?? 0;
?>
<article class="pc-card pc-card--person">
  <a class="pc-person-card pc-card__link" href="<?php echo esc_url(get_permalink($person->post_id)); ?>" aria-label="<?php echo esc_attr('پروفایل ' . $name); ?>">
    <div class="pc-person-card__image pc-card__poster<?php echo $image ? '' : ' pc-person-card__image--empty'; ?>">
      <?php echo $image ?: '<span class="pc-card__placeholder">' . popcorn_icon('user') . '</span>'; ?>
      <span class="pc-card__genres pc-person-card__works"><span><?php echo esc_html(number_format($work_count)); ?> اثر</span></span>
    </div>
    <div class="pc-card__details"><h3 dir="auto" title="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></h3><?php if ($subtitle) : ?><div class="pc-card__meta"><span class="pc-person-card__subtitle"><?php echo esc_html($subtitle); ?></span></div><?php endif; ?></div>
  </a>
</article>
