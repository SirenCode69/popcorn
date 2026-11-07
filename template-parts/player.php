<?php
$sources = $args['sources'] ?? [];
$tracks = $args['subtitles'] ?? [];
$nav = $args['navigation'] ?? ['episodes' => [], 'previous' => '', 'next' => ''];
$playback = $args['playback'];
if (!$sources) { return; }
$episode_seasons = [];
foreach ($nav['episodes'] as $entry) { $episode_seasons[$entry['season']][] = $entry; }
?>
<div class="pc-player" data-pc-player data-sources="<?php echo esc_attr(wp_json_encode($sources)); ?>" data-subtitles="<?php echo esc_attr(wp_json_encode($tracks)); ?>" data-next="<?php echo esc_url($nav['next']); ?>" data-resume="<?php echo esc_attr($args['resume'] ?? 0); ?>" data-playback="<?php echo absint($playback->post_id); ?>"<?php if (is_user_logged_in()) : ?> data-progress-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-progress-nonce="<?php echo esc_attr(wp_create_nonce('popcorn_playback_progress')); ?>"<?php endif; ?> tabindex="0" aria-label="پلیر <?php echo esc_attr($args['title']); ?>">
  <video controls playsinline preload="none" src="<?php echo esc_url($sources[0]['url']); ?>" aria-label="<?php echo esc_attr($args['title']); ?>">مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.</video>
  <?php if (!empty($args['cover'])) : ?><div class="pc-player__cover" data-player-cover><?php echo $args['cover']; ?></div><?php endif; ?>
  <div class="pc-player__top pc-player__chrome">
    <div class="pc-player__heading"><h1><?php echo esc_html($args['title']); ?></h1><?php if ($args['episode']) : ?><span><?php echo esc_html($args['episode']); ?></span><?php endif; ?></div>
    <?php if (!empty($args['resume'])) : ?><button class="pc-player__button pc-player__restart" type="button" data-player-restart>از ابتدا</button><?php endif; ?>
    <a class="pc-player__button pc-player__back" href="<?php echo esc_url($args['back']); ?>" aria-label="خروج از پلیر و بازگشت به صفحهٔ اثر" title="خروج از پلیر و بازگشت به صفحهٔ اثر"><?php echo popcorn_icon('exit'); ?></a>
  </div>
  <button class="pc-player__center" type="button" data-player-center aria-label="شروع پخش"><?php echo popcorn_icon('play'); ?></button>
  <div class="pc-player__loading" aria-label="در حال بارگذاری" hidden></div>
  <div class="pc-player__captions" data-player-captions aria-live="off"></div>
  <div class="pc-player__bottom pc-player__chrome">
    <p class="pc-player__status" data-player-status role="status" aria-live="polite"></p>
    <button class="pc-player__button" type="button" data-player-retry hidden>تلاش دوباره</button>
    <div class="pc-player__timeline"><div data-player-buffer></div><input type="range" min="0" max="1000" value="0" step="1" data-player-seek aria-label="جابه‌جایی زمان پخش" dir="ltr"></div>
    <div class="pc-player__tools">
      <div class="pc-player__transport" dir="ltr">
        <?php if ($nav['previous']) : ?><a class="pc-player__button pc-player__previous" href="<?php echo esc_url($nav['previous']); ?>" title="قسمت قبلی" aria-label="قسمت قبلی"><?php echo popcorn_icon('previous'); ?></a><?php endif; ?>
        <button class="pc-player__button" type="button" data-player-rewind title="۱۰ ثانیه عقب" aria-label="۱۰ ثانیه عقب"><?php echo popcorn_icon('rewind'); ?></button>
        <button class="pc-player__button" type="button" data-player-play title="پخش" aria-label="پخش"><?php echo popcorn_icon('play', 'pc-player__play-icon'); ?><?php echo popcorn_icon('pause', 'pc-player__pause-icon'); ?></button>
        <button class="pc-player__button" type="button" data-player-forward title="۱۰ ثانیه جلو" aria-label="۱۰ ثانیه جلو"><?php echo popcorn_icon('forward'); ?></button>
        <?php if ($nav['next']) : ?><a class="pc-player__button pc-player__next" href="<?php echo esc_url($nav['next']); ?>" title="قسمت بعدی" aria-label="قسمت بعدی"><?php echo popcorn_icon('next'); ?></a><?php endif; ?>
        <button class="pc-player__button" type="button" data-player-mute title="قطع صدا" aria-label="قطع صدا"><?php echo popcorn_icon('volume', 'pc-player__volume-icon'); ?><?php echo popcorn_icon('mute', 'pc-player__mute-icon'); ?></button>
        <input class="pc-player__volume" type="range" min="0" max="1" step=".05" value="1" data-player-volume aria-label="بلندی صدا">
        <span class="pc-player__time" data-player-time>0:00 / 0:00</span>
      </div>
      <div class="pc-player__options">
        <?php if ($nav['episodes']) : ?><button class="pc-player__button" type="button" data-player-panel="episodes" aria-controls="pc-player-episodes" aria-expanded="false" aria-haspopup="dialog" title="فصل و قسمت‌ها" aria-label="فصل و قسمت‌ها"><?php echo popcorn_icon('menu'); ?></button><?php endif; ?>
        <?php if ($tracks) : ?><button class="pc-player__button" type="button" data-player-panel="subtitles" aria-controls="pc-player-subtitles" aria-expanded="false" title="زیرنویس" aria-label="زیرنویس"><?php echo popcorn_icon('subtitles'); ?></button><?php endif; ?>
        <button class="pc-player__button" type="button" data-player-panel="settings" aria-controls="pc-player-settings" aria-expanded="false" title="تنظیمات پخش" aria-label="تنظیمات پخش"><?php echo popcorn_icon('settings'); ?></button>
        <?php if (count($sources) > 1) : ?><button class="pc-player__button" type="button" data-player-panel="quality" aria-controls="pc-player-quality" aria-expanded="false" title="کیفیت پخش" aria-label="کیفیت پخش"><?php echo popcorn_icon('quality'); ?></button><?php endif; ?>
        <button class="pc-player__button" type="button" data-player-pip hidden title="تصویر در تصویر" aria-label="تصویر در تصویر"><?php echo popcorn_icon('pip'); ?></button>
        <button class="pc-player__button" type="button" data-player-fullscreen title="تمام‌صفحه" aria-label="تمام‌صفحه"><?php echo popcorn_icon('fullscreen'); ?></button>
      </div>
    </div>
  </div>
  <?php if (count($sources) > 1) : ?>
  <section class="pc-player__panel pc-player__quality" id="pc-player-quality" data-player-pane="quality" hidden aria-label="کیفیت پخش">
    <h2>کیفیت پخش</h2>
    <div class="pc-player__quality-options"><?php foreach ($sources as $index => $source) : ?><button class="pc-player__button" type="button" dir="auto" data-player-source="<?php echo absint($index); ?>" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"><?php echo esc_html($source['label']); ?></button><?php endforeach; ?></div>
  </section>
  <?php endif; ?>
  <section class="pc-player__panel" id="pc-player-settings" data-player-pane="settings" hidden aria-label="تنظیمات پخش">
    <h2>تنظیمات پخش</h2>
    <label>سرعت پخش<select data-player-speed><?php foreach (['0.5'=>'۰٫۵×','0.75'=>'۰٫۷۵×','1'=>'۱×','1.25'=>'۱٫۲۵×','1.5'=>'۱٫۵×','2'=>'۲×'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>"<?php selected((string) $value, '1'); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
    <?php if ($nav['next']) : ?><label class="pc-player__check"><input type="checkbox" data-player-auto-next checked> پخش خودکار قسمت بعدی</label><?php endif; ?>
    <p>Space: پخش/توقف · ← و →: جابه‌جایی · F: تمام‌صفحه · M: صدا</p>
  </section>
  <?php if ($tracks) : ?>
  <section class="pc-player__panel" id="pc-player-subtitles" data-player-pane="subtitles" hidden aria-label="زیرنویس">
    <h2>زیرنویس</h2>
    <label>انتخاب زیرنویس<select data-player-subtitle><option value="off">خاموش</option><?php foreach ($tracks as $index => $track) : ?><option value="<?php echo absint($index); ?>"><?php echo esc_html($track['label']); ?></option><?php endforeach; ?><?php if (in_array('fa', array_column($tracks, 'language'), true) && in_array('en', array_column($tracks, 'language'), true)) : ?><option value="both">فارسی و انگلیسی</option><?php endif; ?></select></label>
    <label>اندازه<select data-player-subtitle-size><option value="small">کوچک</option><option value="medium" selected>متوسط</option><option value="large">بزرگ</option><option value="xlarge">خیلی بزرگ</option></select></label>
    <label>رنگ<select data-player-subtitle-color><option value="white">سفید</option><option value="yellow">زرد</option><option value="lime">سبز</option><option value="accent">رنگ قالب</option></select></label>
  </section>
  <?php endif; ?>
  <?php if ($nav['episodes']) : ?>
  <section class="pc-player__panel pc-player__episodes" id="pc-player-episodes" data-player-pane="episodes" hidden role="dialog" aria-modal="true" aria-labelledby="pc-player-episodes-title" tabindex="-1">
    <div class="pc-player__episodes-header">
      <h2 id="pc-player-episodes-title">فصل و قسمت‌ها</h2>
      <button class="pc-player__button pc-player__episodes-close" type="button" data-player-close-pane aria-label="بازگشت به پلیر"><?php echo popcorn_icon('left'); ?><span>بازگشت</span></button>
    </div>
    <div class="pc-player__seasons">
      <?php foreach ($episode_seasons as $season => $entries) : ?>
      <section class="pc-player__season" aria-labelledby="pc-player-season-<?php echo absint($season); ?>">
        <h3 id="pc-player-season-<?php echo absint($season); ?>">فصل <?php echo esc_html(number_format_i18n($season)); ?></h3>
        <nav aria-label="قسمت‌های فصل <?php echo esc_attr(number_format_i18n($season)); ?>"><?php foreach ($entries as $entry) : ?><a href="<?php echo esc_url($entry['url']); ?>" title="<?php echo esc_attr($entry['label']); ?>"<?php if ($entry['id'] === (int) $playback->post_id) : ?> aria-current="page"<?php endif; ?>>قسمت <?php echo esc_html(number_format_i18n($entry['episode'])); ?></a><?php endforeach; ?></nav>
      </section>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>
