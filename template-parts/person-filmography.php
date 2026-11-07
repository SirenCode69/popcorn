<?php
$works = $args['works'] ?? [];
if (!$works) {
    return;
}
$work_credits = $args['credits'] ?? [];
$id = sanitize_html_class($args['id'] ?? wp_unique_id('pc-person-filmography-'));
$class = sanitize_html_class($args['class'] ?? '');
$image_size = popcorn_compact_image_size();
$per_page = max(1, absint($args['per_page'] ?? get_option('posts_per_page', 10)));
?>
<section id="<?php echo esc_attr($id); ?>" class="pc-section pc-person-filmography <?php echo esc_attr($class); ?>" aria-label="آثار هنرمند" data-per-page="<?php echo $per_page; ?>">
  <div class="pc-person-filmography__filters" role="group" aria-label="نمایش آثار بر اساس نوع">
    <button type="button" class="is-active" data-pc-work-filter="all" aria-pressed="true">همه</button>
    <button type="button" data-pc-work-filter="movie" aria-pressed="false">فیلم‌ها</button>
    <button type="button" data-pc-work-filter="series" aria-pressed="false">سریال‌ها</button>
  </div>
  <div class="pc-person-filmography__list">
    <?php foreach ($works as $work) :
        $type = ($work->popcorn_type ?? '') === 'series' ? 'series' : 'movie';
        $title = get_the_title($work->post_id) ?: trim((string) ($work->title ?? ''));
        $image_attributes = ['alt' => popcorn_item_seo_image_alt($work), 'loading' => 'lazy'];
        $image = get_the_post_thumbnail($work->post_id, $image_size, $image_attributes);
        if (!$image && !empty($work->poster_id)) {
            $image = wp_get_attachment_image($work->poster_id, $image_size, false, $image_attributes);
        }
        $year = substr((string) ($work->release_date ?? ''), 0, 4);
        $year = preg_match('/^[1-9][0-9]{3}$/D', $year) ? $year : '—';
        $roles = [];
        foreach ($work_credits[$type . ':' . $work->id] ?? [] as $credit) {
            $role = ['cast' => 'بازیگر', 'director' => 'کارگردان', 'writer' => 'نویسنده', 'crew' => 'سایر عوامل'][$credit->role] ?? 'سایر عوامل';
            $roles[] = $credit->role === 'cast' && $credit->character_name !== '' ? 'در نقش ' . $credit->character_name : ($credit->role === 'crew' && $credit->job !== '' ? $credit->job : $role);
        }
        $role_text = implode('، ', array_unique($roles));
    ?>
    <a class="pc-person-filmography__row" href="<?php echo esc_url(popcorn_item_url($work)); ?>" data-pc-work-type="<?php echo esc_attr($type); ?>">
      <span class="pc-person-filmography__image"><?php echo $image ?: popcorn_icon($type === 'series' ? 'tv' : 'film'); ?></span>
      <span class="pc-person-filmography__description"><strong dir="auto"><?php echo esc_html($title); ?></strong><?php if ($role_text !== '') : ?><span><?php echo esc_html($role_text); ?></span><?php endif; ?></span>
      <span class="pc-person-filmography__year" dir="ltr"><?php echo esc_html($year); ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <p class="pc-person-filmography__empty" hidden>اثری در این بخش ثبت نشده است.</p>
  <nav class="pc-person-filmography__pagination" aria-label="صفحه‌بندی آثار هنرمند" hidden>
    <button type="button" data-pc-work-previous aria-label="صفحهٔ قبلی"><?php echo popcorn_icon('right'); ?></button>
    <div class="pc-person-filmography__pages" data-pc-work-pages></div>
    <button type="button" data-pc-work-next aria-label="صفحهٔ بعدی"><?php echo popcorn_icon('left'); ?></button>
  </nav>
  <span class="screen-reader-text" data-pc-work-status role="status" aria-live="polite"></span>
</section>
