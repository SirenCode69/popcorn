<?php
defined('ABSPATH') || exit;

function popcorn_core_person_bulk_job_key() {
    return '_popcorn_person_translation_job';
}

function popcorn_core_person_bulk_new_job($size) {
    global $wpdb;
    $table = popcorn_core_table('person');
    $scope = "FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_type='popcorn_person' AND p.post_status IN ('publish','draft','pending','private','future')";
    $counts = $wpdb->get_row("SELECT COUNT(*) total, MAX(i.id) maximum $scope");
    return ['revision' => 0, 'size' => in_array($size, [30, 50], true) ? $size : 30, 'maximum' => (int) ($counts->maximum ?? 0), 'total' => (int) ($counts->total ?? 0), 'cursor' => 0, 'queue' => [], 'current' => null, 'processed' => 0, 'translated' => 0, 'skipped' => 0, 'failed' => 0, 'done' => empty($counts->total), 'logs' => []];
}

function popcorn_core_person_bulk_source($post, $person, $field) {
    $source = $field === 'persian_name' ? ($post->post_title ?: $person->name) : ($post->post_content ?: $person->biography);
    return trim(wp_strip_all_tags(strip_shortcodes((string) $source)));
}

function popcorn_core_person_bulk_finish(&$job, $status, $message) {
    $current = $job['current'];
    $job['cursor'] = max($job['cursor'], $current['id']);
    $job['processed']++;
    $job[$status]++;
    $job['logs'][] = ['post_id' => $current['post_id'], 'name' => $current['name'], 'status' => $status, 'message' => $message];
    $job['logs'] = array_slice($job['logs'], -100);
    $job['current'] = null;
}

function popcorn_core_person_bulk_select(&$job) {
    global $wpdb;
    $table = popcorn_core_table('person');
    if (!$job['queue']) {
        $job['queue'] = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT i.id FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_type='popcorn_person' AND p.post_status IN ('publish','draft','pending','private','future') WHERE i.id>%d AND i.id<=%d ORDER BY i.id LIMIT %d", $job['cursor'], $job['maximum'], $job['size'])));
    }
    if (!$job['queue']) {
        $job['done'] = true;
        return;
    }
    $id = array_shift($job['queue']);
    $person = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
    $post = $person ? get_post($person->post_id) : null;
    $job['current'] = ['id' => $id, 'post_id' => (int) ($person->post_id ?? 0), 'name' => (string) ($person->name ?? ('#' . $id)), 'fields' => [], 'field_index' => 0, 'chunk_index' => 0, 'texts' => [], 'saved' => []];
    if (!$post || $post->post_type !== 'popcorn_person' || in_array($post->post_status, ['trash', 'auto-draft'], true) || !current_user_can('edit_post', $post->ID)) {
        popcorn_core_person_bulk_finish($job, 'skipped', 'اثر در دسترس نیست یا اجازهٔ ویرایش ندارید.');
        return;
    }
    foreach (['persian_name', 'biography_fa'] as $field) {
        $source = popcorn_core_person_bulk_source($post, $person, $field);
        if (trim((string) $person->$field) === '' && $source !== '') {
            $job['current']['fields'][] = ['key' => $field, 'hash' => hash('sha256', $source)];
        }
    }
    if (!$job['current']['fields']) {
        popcorn_core_person_bulk_finish($job, 'skipped', 'فیلدهای فارسی پر هستند یا متن مبدأ موجود نیست.');
    }
}

function popcorn_core_person_bulk_step(&$job) {
    if ($job['done']) { return; }
    if (!$job['current']) {
        popcorn_core_person_bulk_select($job);
        if (!$job['current']) { return; }
    }
    update_user_meta(get_current_user_id(), popcorn_core_person_bulk_job_key(), $job);
    global $wpdb;
    $table = popcorn_core_table('person');
    $current = &$job['current'];
    $person = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $current['id']));
    $post = $person ? get_post($person->post_id) : null;
    if (!$post || $post->post_type !== 'popcorn_person' || in_array($post->post_status, ['trash', 'auto-draft'], true) || !current_user_can('edit_post', $post->ID)) {
        popcorn_core_person_bulk_finish($job, 'skipped', 'هنرمند دیگر قابل ویرایش نیست.');
        return;
    }
    $field_info = $current['fields'][$current['field_index']];
    $field = $field_info['key'];
    $source = popcorn_core_person_bulk_source($post, $person, $field);
    if (!hash_equals($field_info['hash'], hash('sha256', $source))) {
        popcorn_core_person_bulk_finish($job, 'failed', 'متن مبدأ هنگام ترجمه تغییر کرد؛ ترجمهٔ این هنرمند متوقف شد.');
        return;
    }
    if (trim((string) $person->$field) !== '') {
        $current['field_index']++;
        $current['chunk_index'] = 0;
        $current['texts'] = [];
    } else {
        $chunks = popcorn_mymemory_chunks($source);
        if (!$chunks || count($chunks) > 40) {
            popcorn_core_person_bulk_finish($job, 'failed', 'متن مبدأ خالی یا بیش از حد بلند است.');
            return;
        }
        $translated = popcorn_mymemory_translate($chunks[$current['chunk_index']]);
        if (is_wp_error($translated)) {
            popcorn_core_person_bulk_finish($job, 'failed', $translated->get_error_message() . ($current['saved'] ? ' نام فارسیِ ترجمه‌شده ذخیره شده است.' : ''));
            return;
        }
        $current['texts'][] = $translated;
        $current['chunk_index']++;
        if ($current['chunk_index'] < count($chunks)) { return; }
        clean_post_cache($post->ID);
        $fresh_post = get_post($post->ID);
        $fresh_person = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $current['id']));
        if (!$fresh_post || !$fresh_person || $fresh_post->post_type !== 'popcorn_person' || in_array($fresh_post->post_status, ['trash', 'auto-draft'], true) || !hash_equals($field_info['hash'], hash('sha256', popcorn_core_person_bulk_source($fresh_post, $fresh_person, $field)))) {
            popcorn_core_person_bulk_finish($job, 'failed', 'اطلاعات مبدأ هنگام ترجمه تغییر کرد؛ ترجمه ذخیره نشد.');
            return;
        }
        $value = implode("\n\n", $current['texts']);
        $value = $field === 'persian_name' ? sanitize_text_field($value) : sanitize_textarea_field($value);
        $value = $field === 'persian_name' ? (function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255)) : $value;
        $saved = $wpdb->query($wpdb->prepare("UPDATE $table SET $field=%s, updated_at=%s WHERE id=%d AND TRIM($field)=''", $value, current_time('mysql'), $current['id']));
        if ($saved === false) {
            popcorn_core_person_bulk_finish($job, 'failed', 'ذخیرهٔ ترجمه ناموفق بود.');
            return;
        }
        if ($saved) {
            $current['saved'][] = $field;
            $overrides = get_post_meta($post->ID, '_popcorn_field_overrides', true);
            $overrides = is_array($overrides) ? $overrides : [];
            $overrides[$field] = true;
            update_post_meta($post->ID, '_popcorn_field_overrides', $overrides);
            do_action('popcorn_core_item_saved', $post->ID);
            popcorn_core_purge_playback_content($post->ID);
        }
        $current['field_index']++;
        $current['chunk_index'] = 0;
        $current['texts'] = [];
    }
    if ($current['field_index'] >= count($current['fields'])) {
        popcorn_core_person_bulk_finish($job, $current['saved'] ? 'translated' : 'skipped', $current['saved'] ? 'ترجمهٔ فیلدهای خالی ذخیره شد.' : 'فیلدها قبلاً توسط مدیر دیگری تکمیل شده‌اند.');
    }
}

function popcorn_core_person_bulk_public_job($job) {
    if (!$job) { return null; }
    $result = array_intersect_key($job, array_flip(['revision', 'size', 'total', 'processed', 'translated', 'skipped', 'failed', 'done', 'logs']));
    $current = $job['current'];
    $result['current'] = $current ? ['name' => $current['name'], 'field' => $current['fields'][$current['field_index']]['key'] ?? '', 'chunk' => $current['chunk_index'] + 1] : null;
    return $result;
}

function popcorn_core_person_bulk_unlock($key, $value) {
    global $wpdb;
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $key, maybe_serialize($value)));
    wp_cache_delete($key, 'options');
    wp_cache_delete('notoptions', 'options');
}

function popcorn_core_person_bulk_ajax() {
    if (!current_user_can('edit_others_posts')) { wp_send_json_error(['message' => 'دسترسی مجاز نیست.'], 403); }
    check_ajax_referer('popcorn_person_bulk_translate', 'nonce');
    nocache_headers();
    $mode = sanitize_key(wp_unslash($_POST['mode'] ?? 'status'));
    if (!in_array($mode, ['status', 'start', 'step', 'skip'], true)) { wp_send_json_error(['message' => 'درخواست نامعتبر است.'], 400); }
    $user_id = get_current_user_id();
    $key = popcorn_core_person_bulk_job_key();
    if ($mode === 'status') { wp_send_json_success(['job' => popcorn_core_person_bulk_public_job(get_user_meta($user_id, $key, true))]); }
    $lock_key = 'popcorn_person_translation_lock_' . $user_id;
    $old_lock = get_option($lock_key);
    if (is_array($old_lock) && ($old_lock['expires'] ?? 0) < time()) { popcorn_core_person_bulk_unlock($lock_key, $old_lock); }
    $lock = ['token' => wp_generate_uuid4(), 'expires' => time() + 90];
    if (!add_option($lock_key, $lock, '', false)) { wp_send_json_error(['message' => 'یک درخواست ترجمه در حال اجراست. کمی بعد ادامه دهید.'], 409); }
    $response = null;
    $error = null;
    try {
        $job = get_user_meta($user_id, $key, true);
        if ($mode === 'start' && $job && !$job['done'] && empty($_POST['reset'])) {
            $response = ['job' => popcorn_core_person_bulk_public_job($job), 'stale' => true];
        } elseif ($mode === 'start') {
            $job = popcorn_core_person_bulk_new_job(absint($_POST['size'] ?? 30));
        } elseif (!$job) {
            $error = ['message' => 'ابتدا ترجمهٔ گروهی را شروع کنید.'];
        } elseif ((int) ($_POST['revision'] ?? -1) !== $job['revision']) {
            $response = ['job' => popcorn_core_person_bulk_public_job($job), 'stale' => true];
        } else {
            if ($mode === 'skip') {
                if (!$job['current']) { popcorn_core_person_bulk_select($job); }
                if ($job['current']) { popcorn_core_person_bulk_finish($job, 'failed', 'خطای ارتباط یا سرور؛ این هنرمند رد شد و کار ادامه یافت.'); }
            } else {
                popcorn_core_person_bulk_step($job);
            }
            $job['revision']++;
        }
        if (!$error && !$response) {
            update_user_meta($user_id, $key, $job);
            $response = ['job' => popcorn_core_person_bulk_public_job($job)];
        }
    } catch (Throwable $exception) {
        $error = ['message' => 'پردازش این هنرمند ناموفق بود؛ می‌توانید از او عبور کنید.'];
    } finally {
        popcorn_core_person_bulk_unlock($lock_key, $lock);
    }
    if ($error) { wp_send_json_error($error, 500); }
    wp_send_json_success($response);
}
add_action('wp_ajax_popcorn_person_bulk_translate', 'popcorn_core_person_bulk_ajax');

add_action('admin_enqueue_scripts', function ($hook) {
    $screen = get_current_screen();
    if ($hook !== 'edit.php' || !$screen || $screen->id !== 'edit-popcorn_person' || !current_user_can('edit_others_posts')) { return; }
    $base = dirname(__DIR__);
    $url = plugins_url('assets/', $base . '/popcorn-core.php');
    wp_enqueue_style('popcorn-person-bulk-translation', $url . 'css/person-bulk-translation.css', [], filemtime($base . '/assets/css/person-bulk-translation.css'));
    wp_enqueue_script('popcorn-person-bulk-translation', $url . 'js/person-bulk-translation.js', [], filemtime($base . '/assets/js/person-bulk-translation.js'), true);
    wp_add_inline_script('popcorn-person-bulk-translation', 'window.popcornPersonBulkTranslation=' . wp_json_encode(['url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('popcorn_person_bulk_translate')]) . ';', 'before');
});

add_action('admin_footer-edit.php', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-popcorn_person' || !current_user_can('edit_others_posts')) { return; }
    $accent = function_exists('popcorn_theme_color') ? popcorn_theme_color() : '#6C52EE';
    ?>
    <button type="button" class="page-title-action pc-person-bulk__open" aria-haspopup="dialog" hidden>ترجمهٔ گروهی فارسی</button>
    <dialog class="pc-person-bulk" aria-labelledby="pc-person-bulk-heading" style="--pc-bulk-accent:<?php echo esc_attr($accent); ?>">
      <div class="pc-person-bulk__head"><h2 id="pc-person-bulk-heading">ترجمهٔ گروهی هنرمندان</h2><button type="button" class="button" data-bulk-close>بستن</button></div>
      <p>نام و زندگی‌نامهٔ فارسیِ خالیِ همهٔ هنرمندان از متن موجود با MyMemory ترجمه و ذخیره می‌شوند. مقادیر فارسیِ موجود حفظ می‌شوند؛ هر مورد ناموفق رد می‌شود.</p>
      <p>هر دسته با درخواست‌های کوچک و پشت‌سرهم پردازش می‌شود. پنجره را باز نگه دارید؛ پس از توقف یا بستن صفحه، می‌توانید از همین‌جا ادامه دهید.</p>
      <div class="pc-person-bulk__settings"><label for="pc-person-bulk-size">تعداد هنرمندان در هر دسته</label><select id="pc-person-bulk-size"><option value="30">30 هنرمند</option><option value="50">50 هنرمند</option></select></div>
      <div class="pc-person-bulk__progress"><progress max="100" value="0" aria-label="پیشرفت ترجمهٔ گروهی"></progress><span data-bulk-percent>0%</span></div>
      <p class="pc-person-bulk__status" role="status" aria-live="polite" data-bulk-status>آمادهٔ شروع</p>
      <div class="pc-person-bulk__counts"><span>بررسی‌شده <b data-bulk-count="processed">0</b></span><span>ترجمه‌شده <b data-bulk-count="translated">0</b></span><span>ردشده <b data-bulk-count="skipped">0</b></span><span>ناموفق <b data-bulk-count="failed">0</b></span></div>
      <div class="pc-person-bulk__actions"><button type="button" class="button button-primary" data-bulk-start disabled>شروع ترجمه</button><button type="button" class="button" data-bulk-pause hidden>توقف</button><button type="button" class="button" data-bulk-reset hidden>بررسی دوباره از ابتدا</button><a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=popcorn_person')); ?>" data-bulk-refresh hidden>بازخوانی فهرست هنرمندان</a></div>
      <p data-bulk-error role="alert"></p>
      <details class="pc-person-bulk__report"><summary>گزارش نتایج — 100 هنرمند آخر</summary><div class="pc-person-bulk__log-wrap"><table class="widefat striped"><thead><tr><th>هنرمند</th><th>نتیجه</th></tr></thead><tbody data-bulk-log></tbody></table></div></details>
    </dialog>
    <?php
});
