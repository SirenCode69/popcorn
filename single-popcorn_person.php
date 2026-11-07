<?php
$person = function_exists('popcorn_core_get_by_post') ? popcorn_core_get_by_post('person', get_the_ID()) : null;
if (!$person) { get_template_part('404'); return; }
$name = $person->persian_name ?: $person->name;
$birth_date = !empty($person->birth_date) ? DateTimeImmutable::createFromFormat('!Y-m-d', (string) $person->birth_date, wp_timezone()) : false;
$death_date = !empty($person->death_date) ? DateTimeImmutable::createFromFormat('!Y-m-d', (string) $person->death_date, wp_timezone()) : false;
$today = current_datetime();
$age = null;
$deceased = popcorn_person_is_deceased($person);
if ($birth_date && $birth_date->format('Y-m-d') === (string) $person->birth_date && $birth_date <= $today) {
    $age = $birth_date->diff($deceased && $death_date >= $birth_date ? $death_date : $today)->y;
}
$portrait_attributes = ['alt' => $name];
if ($deceased) {
    $portrait_attributes['class'] = 'pc-person-portrait--deceased';
}
$portrait = get_the_post_thumbnail($person->post_id, 'large', $portrait_attributes);
$portrait = $portrait ?: popcorn_content_image($person->profile_id, $person->profile_path, 'large', $portrait_attributes);
$imdb_id = trim((string) ($person->imdb_id ?? ''));
$imdb_url = preg_match('/^nm[0-9]+$/', $imdb_id) ? 'https://www.imdb.com/name/' . $imdb_id . '/' : '';
$backdrop = popcorn_content_image($person->backdrop_id, $person->backdrop_path, 'large', ['alt' => '', 'loading' => 'eager']);
$has_backdrop = $backdrop !== '';
if (!$has_backdrop) {
    add_filter('body_class', static function ($classes) {
        $classes[] = 'pc-person-no-backdrop';
        return $classes;
    });
}
$works = popcorn_core_person_works($person->id, 100);
$work_credits = popcorn_core_person_work_credits($person->id, $works);
$known_works = popcorn_core_person_works($person->id, 10, 'popularity');
popcorn_homepage_prime_slider_items(array_merge($works, $known_works));
popcorn_card_features(0, $known_works);
$elementor_content = get_post_meta($person->post_id, '_elementor_edit_mode', true) === 'builder';
get_header();
if (function_exists('elementor_theme_do_location') && elementor_theme_do_location('single')) { get_footer(); return; }
?>
<main id="main" class="pc-detail pc-person-detail<?php echo $has_backdrop ? ' pc-person-detail--has-backdrop' : ' pc-person-detail--no-backdrop'; ?>">
  <?php if ($has_backdrop) : ?><div class="pc-detail__backdrop"><?php echo $backdrop; ?></div><?php endif; ?>
  <div class="pc-container pc-detail__body">
    <nav class="pc-breadcrumb" aria-label="مسیر صفحه"><a href="<?php echo esc_url(home_url('/')); ?>">خانه</a><span>/</span><a href="<?php echo esc_url(get_post_type_archive_link('popcorn_person')); ?>">هنرمندان</a><span>/</span><span><?php echo esc_html(get_the_title()); ?></span></nav>
    <div class="pc-detail__intro">
      <div class="pc-person-detail__sidebar">
        <div class="pc-detail__poster"><?php echo $portrait ?: popcorn_icon('film'); ?></div>
        <dl class="pc-detail__facts"><?php if ($person->persian_name && $person->persian_name !== get_the_title()) : ?><div><dt>نام فارسی</dt><dd><?php echo esc_html($person->persian_name); ?></dd></div><?php endif; ?><?php foreach (['profession' => 'حرفه', 'birth_date' => 'تاریخ تولد', 'death_date' => 'تاریخ درگذشت', 'place_of_birth' => 'محل تولد'] as $field => $label) : if (empty($person->$field)) { continue; } ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($field === 'profession' ? popcorn_core_profession_label($person->$field) : $person->$field); ?></dd></div><?php endforeach; ?><?php if ($age !== null) : ?><div><dt><?php echo $deceased ? 'سن هنگام درگذشت' : 'سن'; ?></dt><dd><?php echo esc_html(number_format_i18n($age)); ?> سال</dd></div><?php endif; ?></dl>
        <?php get_template_part('template-parts/imdb-profile', null, ['url' => $imdb_url]); ?>
      </div>
      <div class="pc-detail__copy">
        <h1 class="pc-person-detail__desktop-title"><?php echo esc_html(get_the_title()); ?></h1>
        <div class="pc-detail__overview"><?php ob_start(); if (!$elementor_content && trim((string) $person->biography_fa) !== '') { echo wpautop(esc_html($person->biography_fa)); } elseif (!$elementor_content && trim(get_the_content()) !== '') { the_content(); } else { echo wpautop(esc_html($person->biography)); } get_template_part('template-parts/expandable-text', null, ['content' => ob_get_clean(), 'lines' => 4]); ?></div>
        <?php if ($works) { get_template_part('template-parts/slider', null, ['items' => $known_works, 'card' => 'card', 'heading' => 'آثار شناخته‌شده', 'section_class' => 'pc-section pc-slider-section--posters pc-person-works__known', 'navigation' => true, 'id' => 'pc-person-known-works']); } ?>
        <?php if ($works) { get_template_part('template-parts/person-filmography', null, ['works' => $works, 'credits' => $work_credits, 'id' => 'pc-person-filmography-inline', 'class' => 'pc-person-filmography--inline']); } ?>
      </div>
    </div>
    <?php popcorn_theme_ad('header'); ?>
    <?php if ($elementor_content) : ?><section class="pc-detail__editor-content"><?php the_content(); ?></section><?php endif; ?>
    <?php if (comments_open() || get_comments_number()) { comments_template(); } ?>
  </div>
</main>
<?php get_footer(); ?>
