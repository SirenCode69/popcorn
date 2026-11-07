<?php
defined('ABSPATH') || exit;

function popcorn_core_editor_sections($type) {
    if ($type === 'person') {
        return [
            'details' => ['جزئیات', ['persian_name', 'gender', 'profession', 'popularity', 'imdb_id', 'tmdb_id']],
            'biography' => ['زندگی‌نامه', ['birth_date', 'death_date', 'place_of_birth', 'biography_fa']],
            'images' => ['تصاویر', []],
            'works' => ['فیلم‌ها و سریال‌های مرتبط', []],
        ];
    }
    return [
        'general' => ['اطلاعات کلی', ['english_title', 'original_title', 'overview_en', 'tagline', 'release_date', 'runtime', 'is_featured', 'show_in_slider', 'is_coming_soon']],
        'downloads' => ['لینک‌های دانلود', []],
        'credits' => ['بازیگران و عوامل', []],
        'collection' => ['کالکشن', []],
        'details' => ['جزئیات', array_merge(['certification', 'country', 'language', 'studio', 'production_status', 'trailer_url', 'keywords'], $type === 'series' ? ['season_count', 'episode_count', 'network', 'next_episode_date'] : ['budget', 'revenue'])],
        'ratings' => ['امتیازها و شناسه‌ها', ['rating', 'imdb_rating', 'imdb_votes', 'metacritic_rating', 'rotten_tomatoes_rating', 'vote_average', 'vote_count', 'popularity', 'imdb_id', 'tmdb_id']],
        'images' => ['تصاویر', []],
        'gallery' => ['گالری تصاویر', []],
    ];
}

function popcorn_core_editor_field($item, $key, $type, $post_id) {
    if ($key === 'tmdb_id') {
        $error = get_post_meta($post_id, '_popcorn_tmdb_id_error', true);
        echo '<tr><th><label for="popcorn-field-tmdb-id">شناسهٔ TMDb</label></th><td><input id="popcorn-field-tmdb-id" name="popcorn_tmdb_id" type="number" min="1" step="1" value="' . esc_attr($item->tmdb_id ?? '') . '" placeholder="شناسهٔ عددی TMDb">';
        if ($error) {
            echo '<p class="pc-editor__error" role="alert">' . esc_html($error) . '</p>';
        }
        echo '</td></tr>';
        return;
    }
    $fields = popcorn_core_editor_fields($type);
    if (!isset($fields[$key])) {
        return;
    }
    [$label, $field_type] = $fields[$key];
    $value = (string) ($item->$key ?? '');
    $rating_votes = $key === 'rating' ? popcorn_core_rating_vote_count($post_id) : 0;
    echo '<tr><th><label for="popcorn-field-' . esc_attr($key) . '">' . esc_html($label) . '</label>';
    if (($type === 'person' && in_array($key, ['persian_name', 'biography_fa'], true)) || ($type !== 'person' && in_array($key, ['original_title', 'overview_en'], true))) {
        echo '<div class="pc-editor__translate-row"><button type="button" class="button pc-editor__translate" data-field="' . esc_attr($key) . '">ترجمه</button><span class="pc-editor__translate-status" role="status" aria-live="polite"></span></div>';
    }
    echo '</th><td>';
    if ($key === 'rating' && $rating_votes > 0) {
        echo '<input id="popcorn-field-rating" type="number" value="' . esc_attr($value) . '" min="0" max="10" step="0.01" readonly><p class="description">میانگین ' . esc_html(number_format_i18n($rating_votes)) . ' رأی کاربران است و با ثبت یا حذف رأی، خودکار به‌روزرسانی می‌شود.</p>';
    } elseif ($field_type === 'textarea') {
        echo '<textarea rows="5" id="popcorn-field-' . esc_attr($key) . '" name="popcorn_fields[' . esc_attr($key) . ']">' . esc_textarea($value) . '</textarea>';
    } elseif ($field_type === 'select') {
        $options = $type === 'person' ? ($key === 'gender' ? popcorn_core_gender_labels() : popcorn_core_profession_labels()) : popcorn_core_production_status_options($type);
        if ($key === 'production_status') {
            $value = popcorn_core_persian_production_status($value, $type);
        }
        if ($key === 'gender' && !array_key_exists($value, $options)) {
            $value = '0';
        }
        if (in_array($key, ['profession', 'production_status'], true) && $value !== '' && !array_key_exists($value, $options)) {
            $options[$value] = $value;
        }
        echo '<select id="popcorn-field-' . esc_attr($key) . '" name="popcorn_fields[' . esc_attr($key) . ']">';
        foreach ($options as $option => $text) {
            echo '<option value="' . esc_attr($option) . '"' . selected($value, (string) $option, false) . '>' . esc_html($text) . '</option>';
        }
        echo '</select>';
    } elseif ($field_type === 'checkbox') {
        echo '<input type="hidden" name="popcorn_fields[' . esc_attr($key) . ']" value="0"><input id="popcorn-field-' . esc_attr($key) . '" name="popcorn_fields[' . esc_attr($key) . ']" type="checkbox" value="1"' . checked($value, '1', false) . '>';
    } else {
        echo '<input id="popcorn-field-' . esc_attr($key) . '" name="popcorn_fields[' . esc_attr($key) . ']" type="' . esc_attr($field_type) . '" value="' . esc_attr($value) . '"' . ($field_type === 'number' ? ' min="0" step="any"' : '') . '>';
    }
    if (($type === 'person' && in_array($key, ['persian_name', 'biography_fa'], true)) || ($type !== 'person' && in_array($key, ['original_title', 'overview_en'], true))) {
        echo '<p class="description">ترجمه از متن انگلیسیِ ذخیره‌شده انجام می‌شود؛ پس از ترجمه، نوشته را ذخیره کنید.</p>';
    }
    if ($key === 'rating' && !$rating_votes) {
        echo '<p class="description">تا ثبت نخستین رأی، امتیاز اولیه را می‌توانید دستی وارد کنید. سپس میانگین رأی کاربران جایگزین می‌شود.</p>';
    }
    echo '</td></tr>';
}

function popcorn_core_editor_image_url($path, $attachment_id, $published = false, $size = 'medium') {
    if ($attachment_id && wp_attachment_is_image($attachment_id)) {
        if ($published && function_exists('popcorn_webp_src')) {
            $image = popcorn_webp_src($attachment_id, $size);
            if (!empty($image[0])) {
                return $image[0];
            }
        }
        return wp_get_attachment_image_url($attachment_id, $size) ?: '';
    }
    if (preg_match('~^/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~', (string) $path)) {
        return 'https://image.tmdb.org/t/p/' . ($size === 'large' ? 'w780' : 'w185') . $path;
    }
    return popcorn_core_external_image_url($path);
}

function popcorn_core_editor_image_controls($path_name, $id_name, $path, $attachment_id, $title) {
    echo '<button type="button" class="button button-primary pc-editor__image-trigger" data-title="' . esc_attr($title) . '" aria-haspopup="dialog">مدیریت تصویر</button><input class="pc-editor__image-path" type="hidden" name="' . esc_attr($path_name) . '" value="' . esc_attr($path) . '"><input class="pc-editor__attachment-id" type="hidden" name="' . esc_attr($id_name) . '" value="' . esc_attr($attachment_id) . '">';
}

function popcorn_core_editor_media_panel($item, $type, $published) {
    foreach (['backdrop' => 'پس‌زمینه'] as $key => $label) {
        $path = (string) ($item->{$key . '_path'} ?? '');
        $attachment_id = absint($item->{$key . '_id'} ?? 0);
        $url = popcorn_core_editor_image_url($path, $attachment_id, $published, 'large');
        echo '<div class="pc-editor__media"><div class="pc-editor__media-preview">';
        if ($url) {
            echo '<img src="' . esc_url($url) . '"' . popcorn_core_image_fallback_attr($url) . ' alt="' . esc_attr($label) . '" loading="lazy">';
        } else {
            echo '<span>تصویری ثبت نشده</span>';
        }
        echo '</div><div class="pc-editor__media-controls"><strong>' . esc_html($label) . '</strong>';
        popcorn_core_editor_image_controls('popcorn_media[' . $key . '_path]', 'popcorn_media[' . $key . '_id]', $path, $attachment_id, $label);
        echo '</div></div>';
    }
}

function popcorn_core_editor_gallery_row($index, $entry, $published) {
    $path = (string) ($entry['path'] ?? '');
    $attachment_id = absint($entry['attachment_id'] ?? 0);
    $url = popcorn_core_editor_image_url($path, $attachment_id, $published);
    echo '<div class="pc-editor__repeat-row pc-editor__gallery-row"><div class="pc-editor__gallery-preview">';
    if ($url) {
        echo '<img src="' . esc_url($url) . '"' . popcorn_core_image_fallback_attr($url) . ' alt="تصویر گالری" loading="lazy">';
    } else {
        echo '<span>بدون تصویر</span>';
    }
    echo '</div><div class="pc-editor__repeat-fields">';
    popcorn_core_editor_image_controls('popcorn_gallery[' . $index . '][path]', 'popcorn_gallery[' . $index . '][attachment_id]', $path, $attachment_id, 'تصویر گالری');
    echo '</div>' . popcorn_core_editor_repeat_actions() . '</div>';
}

function popcorn_core_editor_repeat_actions() {
    $up = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 14 6-6 6 6"/></svg>';
    $down = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 10 6 6 6-6"/></svg>';
    return '<div class="pc-editor__repeat-actions"><button type="button" class="button pc-editor__move-up" aria-label="انتقال به بالا" title="انتقال به بالا">' . $up . '</button><button type="button" class="button pc-editor__move-down" aria-label="انتقال به پایین" title="انتقال به پایین">' . $down . '</button><button type="button" class="button pc-editor__remove-row">حذف</button></div>';
}

function popcorn_core_editor_gallery_panel($item, $published) {
    $gallery = json_decode((string) ($item->gallery ?? '[]'), true);
    $gallery = is_array($gallery) ? $gallery : [];
    echo '<p>ترتیب تصاویر را با دکمه‌های بالا و پایین تغییر دهید.</p><div class="pc-editor__repeater" data-repeater="gallery">';
    foreach ($gallery as $index => $entry) {
        if (is_array($entry)) {
            popcorn_core_editor_gallery_row($index, $entry, $published);
        }
    }
    echo '</div><input type="hidden" name="popcorn_gallery_present" value="1"><button type="button" class="button pc-editor__add-row" data-kind="gallery">افزودن تصویر</button><template id="pc-editor-gallery-template">';
    popcorn_core_editor_gallery_row('__INDEX__', [], $published);
    echo '</template>';
}

function popcorn_core_editor_credit_row($index, $credit = []) {
    $person_id = absint($credit['person_id'] ?? 0);
    $name = (string) ($credit['name'] ?? '');
    $role = (string) ($credit['role'] ?? 'cast');
    echo '<div class="pc-editor__repeat-row pc-editor__credit-row"><div class="pc-editor__person-picker"><input class="pc-editor__person-search" type="search" name="popcorn_credits[' . esc_attr($index) . '][person_name]" value="' . esc_attr($name) . '" placeholder="جست‌وجو یا نام هنرمند تازه" autocomplete="off"><input class="pc-editor__person-id" type="hidden" name="popcorn_credits[' . esc_attr($index) . '][person_id]" value="' . esc_attr($person_id) . '"><div class="pc-editor__suggestions" role="listbox"></div></div><select name="popcorn_credits[' . esc_attr($index) . '][role]">';
    foreach (['cast' => 'بازیگر', 'director' => 'کارگردان', 'writer' => 'نویسنده', 'crew' => 'سایر عوامل'] as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($role, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select><input class="pc-editor__credit-detail" type="text" name="popcorn_credits[' . esc_attr($index) . '][character_name]" value="' . esc_attr($credit['character_name'] ?? '') . '" placeholder="نام نقش" aria-label="نام نقش"><input class="pc-editor__credit-detail" type="text" name="popcorn_credits[' . esc_attr($index) . '][job]" value="' . esc_attr($credit['job'] ?? '') . '" placeholder="سمت عوامل" aria-label="سمت عوامل">' . popcorn_core_editor_repeat_actions() . '</div>';
}

function popcorn_core_editor_credits_panel($item, $type) {
    global $wpdb;
    $rows = [];
    if ($item && $item->id) {
        $credits = popcorn_core_table('credit');
        $people = popcorn_core_table('person');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT c.person_id,c.role,c.character_name,c.job,p.name FROM $credits c INNER JOIN $people p ON p.id=c.person_id WHERE c.content_type=%s AND c.content_id=%d ORDER BY c.sort_order,c.role", $type, $item->id), ARRAY_A);
    }
    echo '<p>هنرمند را از نتیجهٔ جست‌وجو انتخاب کنید یا نام هنرمند تازه را بنویسید. هنرمند تازه با ذخیرهٔ اثر به بخش هنرمندان اضافه می‌شود.</p><div class="pc-editor__repeater" data-repeater="credits">';
    foreach ($rows as $index => $row) {
        popcorn_core_editor_credit_row($index, $row);
    }
    echo '</div><input type="hidden" name="popcorn_credits_present" value="1"><button type="button" class="button pc-editor__add-row" data-kind="credits">افزودن هنرمند</button><template id="pc-editor-credits-template">';
    popcorn_core_editor_credit_row('__INDEX__');
    echo '</template>';
}

function popcorn_core_editor_collection_panel($item, $type, $post_id) {
    global $wpdb;
    $table = popcorn_core_table('collection');
    $content = popcorn_core_table($type);
    $selected = absint($item->collection_id ?? 0);
    $collections = $wpdb->get_results($wpdb->prepare("SELECT id,name FROM $table WHERE content_type=%s ORDER BY name LIMIT 300", $type));
    $current = $selected ? $wpdb->get_row($wpdb->prepare("SELECT id,name FROM $table WHERE id=%d AND content_type=%s", $selected, $type)) : null;
    $members = $selected ? $wpdb->get_results($wpdb->prepare("SELECT p.ID,i.title,i.original_title FROM $content i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE i.collection_id=%d AND p.ID<>%d ORDER BY i.title LIMIT 200", $selected, $post_id)) : [];
    echo '<label for="popcorn-collection-id">کالکشن موجود</label><select id="popcorn-collection-id" name="popcorn_collection_id"><option value="0">بدون کالکشن</option>';
    foreach ($collections as $collection) {
        echo '<option value="' . esc_attr($collection->id) . '"' . selected($selected, $collection->id, false) . '>' . esc_html($collection->name) . '</option>';
    }
    echo '</select><label for="popcorn-collection-name">نام کالکشن</label><input id="popcorn-collection-name" type="text" name="popcorn_collection_name" value="' . esc_attr($current->name ?? '') . '" placeholder="برای ساخت کالکشن تازه، نام آن را وارد کنید" autocomplete="off"><input id="popcorn-collection-name-edited" type="hidden" name="popcorn_collection_name_edited" value="0"><p>برای ساخت کالکشن تازه، «بدون کالکشن» را انتخاب کنید و نام را بنویسید. برای تغییر نام کالکشن موجود، همین فیلد را ویرایش کنید. اعضای کالکشن تازه انتخاب‌شده پس از ذخیره نمایش داده می‌شوند.</p><div class="pc-editor__collection-members">';
    foreach ($members as $member) {
        echo '<span class="pc-editor__chip">' . esc_html(popcorn_display_title($member)) . '<input type="hidden" name="popcorn_collection_members[]" value="' . esc_attr($member->ID) . '"><button type="button" class="pc-editor__remove-chip" aria-label="حذف از کالکشن">حذف</button></span>';
    }
    echo '</div><div hidden>';
    foreach ($members as $member) {
        echo '<input type="hidden" name="popcorn_collection_visible_members[]" value="' . esc_attr($member->ID) . '">';
    }
    echo '</div><div class="pc-editor__collection-picker"><label for="popcorn-collection-search">افزودن ' . ($type === 'movie' ? 'فیلم' : 'سریال') . ' به کالکشن</label><input id="popcorn-collection-search" class="pc-editor__collection-search" type="search" placeholder="برای دیدن عنوان‌ها کلیک کنید یا جست‌وجو کنید" autocomplete="off"><div class="pc-editor__suggestions" role="listbox"></div></div><input type="hidden" name="popcorn_collection_members_present" value="1">';
}

function popcorn_core_editor_works_panel($item) {
    if (!$item || !$item->id) {
        echo '<p>پس از ثبت هنرمند، آثار مرتبط اینجا نمایش داده می‌شوند.</p>';
        return;
    }
    global $wpdb;
    $credits = popcorn_core_table('credit');
    $movies = popcorn_core_table('movie');
    $series = popcorn_core_table('series');
    $works = $wpdb->get_results($wpdb->prepare("SELECT c.content_type,c.character_name,c.role,i.post_id,p.post_title FROM $credits c INNER JOIN $movies i ON i.id=c.content_id INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE c.person_id=%d AND c.content_type='movie' UNION ALL SELECT c.content_type,c.character_name,c.role,i.post_id,p.post_title FROM $credits c INNER JOIN $series i ON i.id=c.content_id INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE c.person_id=%d AND c.content_type='series' ORDER BY post_title LIMIT 100", $item->id, $item->id));
    if (!$works) {
        echo '<p>هنوز اثری برای این هنرمند ثبت نشده است.</p>';
        return;
    }
    echo '<div class="pc-editor__table-wrap"><table class="pc-editor__works"><thead><tr><th scope="col">اثر</th><th scope="col">نوع</th><th scope="col">نقش</th></tr></thead><tbody>';
    foreach ($works as $work) {
        echo '<tr><td><a href="' . esc_url(get_edit_post_link($work->post_id)) . '">' . esc_html($work->post_title) . '</a></td><td><span class="pc-editor__work-type">' . ($work->content_type === 'movie' ? 'فیلم' : 'سریال') . '</span></td><td>' . esc_html($work->character_name ?: $work->role) . '</td></tr>';
    }
    echo '</tbody></table></div><p>برای تغییر رابطه و نقش، تب «بازیگران و عوامل» همان اثر را ویرایش کنید.</p>';
}

function popcorn_core_render_editor_tabs($post, $type) {
    $item = popcorn_core_get_by_post($type, $post->ID);
    $published = $post->post_status === 'publish';
    $sections = popcorn_core_editor_sections($type);
    $accent = function_exists('popcorn_theme_color') ? popcorn_theme_color() : '#6C52EE';
    wp_nonce_field('popcorn_fields_' . $post->ID, 'popcorn_fields_nonce');
    echo '<div class="pc-editor" style="--pc-editor-accent:' . esc_attr($accent) . '" data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('popcorn_editor_lookup')) . '" data-image-nonce="' . esc_attr(wp_create_nonce('popcorn_image_preview')) . '" data-type="' . esc_attr($type) . '" data-post-id="' . esc_attr($post->ID) . '" data-translate-nonce="' . esc_attr(wp_create_nonce('popcorn_translate_content_' . $post->ID)) . '" data-published="' . ($published ? '1' : '0') . '"><div class="pc-editor__tabs" role="tablist" aria-label="بخش‌های اطلاعات پاپکورن">';
    foreach ($sections as $key => [$label]) {
        echo '<button type="button" class="pc-editor__tab" role="tab" id="pc-editor-tab-' . esc_attr($key) . '" aria-controls="pc-editor-panel-' . esc_attr($key) . '" aria-selected="' . ($key === array_key_first($sections) ? 'true' : 'false') . '" tabindex="' . ($key === array_key_first($sections) ? '0' : '-1') . '">' . esc_html($label) . '</button>';
    }
    echo '</div>';
    foreach ($sections as $key => [$label, $fields]) {
        echo '<section class="pc-editor__panel" role="tabpanel" id="pc-editor-panel-' . esc_attr($key) . '" aria-labelledby="pc-editor-tab-' . esc_attr($key) . '"' . ($key === array_key_first($sections) ? '' : ' hidden') . '>';
        if ($type !== 'person' && $key === 'general') {
            echo '<p>عنوان اثر به زبان اصلی را در عنوان وردپرس و خلاصهٔ انگلیسی را در متن اصلی ویرایش کنید.</p>';
        } elseif ($type === 'person' && $key === 'details') {
            echo '<p>نام انگلیسی هنرمند را در عنوان اصلی وردپرس ویرایش کنید.</p>';
        } elseif ($type === 'person' && $key === 'biography') {
            echo '<p>زندگی‌نامهٔ انگلیسی در متن اصلی وردپرس قرار دارد.</p>';
        }
        if ($fields) {
            echo '<table class="form-table"><tbody>';
            foreach ($fields as $field) {
                popcorn_core_editor_field($item, $field, $type, $post->ID);
            }
            echo '</tbody></table>';
        }
        if ($key === 'downloads') {
            popcorn_core_downloads_panel($post->ID, $type);
        } elseif ($key === 'credits') {
            popcorn_core_editor_credits_panel($item, $type);
        } elseif ($key === 'collection') {
            popcorn_core_editor_collection_panel($item, $type, $post->ID);
        } elseif ($key === 'images') {
            popcorn_core_editor_media_panel($item, $type, $published);
        } elseif ($key === 'gallery') {
            popcorn_core_editor_gallery_panel($item, $published);
        } elseif ($key === 'works') {
            popcorn_core_editor_works_panel($item);
        }
        echo '</section>';
    }
    echo '<div class="pc-editor__image-modal" style="--pc-editor-accent:' . esc_attr($accent) . '" role="dialog" aria-modal="true" aria-labelledby="pc-editor-image-title" hidden><div class="pc-editor__image-modal-backdrop"></div><div class="pc-editor__image-modal-panel"><div class="pc-editor__image-modal-head"><h2 id="pc-editor-image-title">مدیریت تصویر</h2><button type="button" class="button pc-editor__image-close">انصراف</button></div><span class="pc-editor__image-modal-status"></span><label for="pc-editor-image-path">مسیر TMDb یا لینک مستقیم تصویر</label><input id="pc-editor-image-path" type="text" dir="ltr" autocomplete="off" placeholder="/poster.jpg یا https://example.com/image.jpg"><p class="pc-editor__image-modal-help"></p><div class="pc-editor__image-modal-actions"><button type="button" class="button pc-editor__choose-media">انتخاب از رسانه</button><button type="button" class="button button-primary pc-editor__image-done">انجام شد</button></div></div></div></div>';
}

function popcorn_core_external_image_url($input) {
    $url = is_scalar($input) ? trim((string) $input) : '';
    if (strlen($url) > 2048 || !preg_match('~^https?://~i', $url)) {
        return '';
    }
    $url = esc_url_raw($url, ['http', 'https']);
    $parts = wp_parse_url($url);
    if (!$url || !is_array($parts) || empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass'])) {
        return '';
    }
    $candidates = popcorn_core_url_candidates($url);
    return $candidates['primary'] ?? '';
}

function popcorn_core_editor_image_path($input) {
    $path = is_scalar($input) ? trim((string) $input) : '';
    if ($path === '' || strlen($path) > 2048) {
        return '';
    }
    $path = preg_replace('~^https://image\.tmdb\.org/t/p/(?:original|w\d+)/~i', '/', $path);
    if (preg_match('~^/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~', $path)) {
        return $path;
    }
    return popcorn_core_external_image_url($path);
}

function popcorn_core_editor_save_media($post_id, $type, $item, $input) {
    global $wpdb;
    $row = [];
    $changed = false;
    foreach (['backdrop'] as $key) {
        $old_path = (string) ($item->{$key . '_path'} ?? '');
        $old_id = absint($item->{$key . '_id'} ?? 0);
        $raw_path = trim((string) ($input[$key . '_path'] ?? ''));
        $path = popcorn_core_editor_image_path($raw_path);
        if ($raw_path !== '' && $path === '') {
            $path = $old_path;
        }
        $chosen_id = absint($input[$key . '_id'] ?? 0);
        $chosen_id = $chosen_id && wp_attachment_is_image($chosen_id) && current_user_can('edit_post', $chosen_id) ? $chosen_id : 0;
        if ($path !== $old_path && $path !== '') {
            $chosen_id = 0;
        } elseif (!$path && !$chosen_id && $old_id) {
            $chosen_id = 0;
        }
        if ($chosen_id && $chosen_id !== $old_id && get_post_status($post_id) === 'publish') {
            popcorn_core_prepare_webp($chosen_id);
        }
        $row[$key . '_path'] = $path;
        $row[$key . '_id'] = $chosen_id;
        $changed = $changed || $path !== $old_path || $chosen_id !== $old_id;
    }
    if ($changed) {
        $row['updated_at'] = current_time('mysql');
        $wpdb->update(popcorn_core_table($type), $row, ['id' => $item->id]);
        update_post_meta($post_id, '_popcorn_media_customized', 1);
    }
}

function popcorn_core_editor_save_gallery($post_id, $item, $type, $input, $published) {
    global $wpdb;
    $old = json_decode((string) $item->gallery, true);
    $old = is_array($old) ? $old : [];
    $attempts = [];
    $previous_paths = [];
    foreach ($old as $image) {
        if (!empty($image['path'])) {
            $attempts[$image['path']] = absint($image['attempts'] ?? 0);
        }
        if (!empty($image['attachment_id'])) {
            $previous_paths[absint($image['attachment_id'])] = (string) ($image['path'] ?? '');
        }
    }
    $gallery = [];
    $seen = [];
    foreach (array_slice($input, 0, 40) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $path = popcorn_core_editor_image_path($entry['path'] ?? '');
        $attachment_id = absint($entry['attachment_id'] ?? 0);
        $attachment_id = $attachment_id && wp_attachment_is_image($attachment_id) && current_user_can('edit_post', $attachment_id) ? $attachment_id : 0;
        if ($path && $attachment_id && ($previous_paths[$attachment_id] ?? null) !== $path) {
            $attachment_id = 0;
        }
        $key = $path ?: 'attachment:' . $attachment_id;
        if ((!$path && !$attachment_id) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        if ($attachment_id && $published) {
            popcorn_core_prepare_webp($attachment_id);
        }
        $gallery[] = ['path' => $path, 'attachment_id' => $attachment_id, 'attempts' => $path ? ($attempts[$path] ?? 0) : 0];
    }
    if (wp_json_encode($gallery) !== wp_json_encode($old)) {
        $wpdb->update(popcorn_core_table($type), ['gallery' => wp_json_encode($gallery), 'updated_at' => current_time('mysql')], ['id' => $item->id]);
        update_post_meta($post_id, '_popcorn_gallery_customized', 1);
    }
}

function popcorn_core_editor_new_person($name, $role, $published) {
    global $wpdb;
    $table = popcorn_core_table('person');
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name=%s LIMIT 1", $name));
    if ($existing) {
        return (int) $existing;
    }
    $post_id = wp_insert_post(['post_type' => 'popcorn_person', 'post_title' => $name, 'post_status' => $published && current_user_can('publish_posts') ? 'publish' : 'draft'], true);
    if (is_wp_error($post_id) || !$post_id) {
        return 0;
    }
    $person = popcorn_core_get_by_post('person', $post_id);
    if (!$person) {
        return 0;
    }
    $profession = ['cast' => 'Acting', 'director' => 'Directing', 'writer' => 'Writing', 'crew' => 'Crew'][$role] ?? '';
    $changes = ['profession' => $profession, 'updated_at' => current_time('mysql')];
    if (preg_match('/[\x{0600}-\x{06FF}]/u', $name)) {
        $changes['persian_name'] = $name;
    }
    $wpdb->update($table, $changes, ['id' => $person->id]);
    if (!$published) {
        update_post_meta($post_id, '_popcorn_deferred_credit', 1);
    }
    return (int) $person->id;
}

function popcorn_core_editor_save_credits($post_id, $item, $type, $input) {
    global $wpdb;
    $table = popcorn_core_table('credit');
    $people = popcorn_core_table('person');
    $rows = [];
    foreach (array_slice($input, 0, 60) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $person_id = absint($entry['person_id'] ?? 0);
        $role = sanitize_key($entry['role'] ?? '');
        if (!in_array($role, ['cast', 'director', 'writer', 'crew'], true)) {
            continue;
        }
        if (!$person_id) {
            $name = trim(sanitize_text_field($entry['person_name'] ?? ''));
            $name = function_exists('mb_substr') ? mb_substr($name, 0, 160) : substr($name, 0, 160);
            if ($name === '') {
                continue;
            }
            $person_id = popcorn_core_editor_new_person($name, $role, get_post_status($post_id) === 'publish');
        }
        if (!$person_id || !$wpdb->get_var($wpdb->prepare("SELECT id FROM $people WHERE id=%d", $person_id))) {
            continue;
        }
        $rows[] = ['content_type' => $type, 'content_id' => $item->id, 'person_id' => $person_id, 'role' => $role, 'character_name' => sanitize_text_field($entry['character_name'] ?? ''), 'job' => sanitize_text_field($entry['job'] ?? ''), 'sort_order' => count($rows)];
    }
    $existing = $wpdb->get_results($wpdb->prepare("SELECT person_id,role,character_name,job,sort_order FROM $table WHERE content_type=%s AND content_id=%d ORDER BY sort_order,role", $type, $item->id), ARRAY_A);
    $comparison = array_map(function ($row) { unset($row['content_type'], $row['content_id'], $row['sort_order']); return array_map('strval', $row); }, $rows);
    if ($comparison === array_map(function ($row) { unset($row['sort_order']); return array_map('strval', $row); }, $existing)) {
        return;
    }
    $wpdb->delete($table, ['content_type' => $type, 'content_id' => $item->id]);
    foreach ($rows as $row) {
        $wpdb->insert($table, $row);
    }
    update_post_meta($post_id, '_popcorn_credits_customized', 1);
}

function popcorn_core_editor_save_collection($post_id, $item, $type, $input) {
    global $wpdb;
    $table = popcorn_core_table('collection');
    $content = popcorn_core_table($type);
    $old_id = absint($item->collection_id ?? 0);
    $selected = absint($input['id'] ?? 0);
    if ($selected && !$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id=%d AND content_type=%s", $selected, $type))) {
        $selected = 0;
    }
    $name = trim(sanitize_text_field($input['name'] ?? ''));
    $name = function_exists('mb_substr') ? mb_substr($name, 0, 160) : substr($name, 0, 160);
    $created = false;
    if (!$selected && $name !== '') {
        $wpdb->insert($table, ['content_type' => $type, 'name' => $name, 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql')]);
        $selected = (int) $wpdb->insert_id;
        $created = (bool) $selected;
        if ($created) {
            update_post_meta($post_id, '_popcorn_collection_customized', 1);
        }
    } elseif ($selected && $name !== '' && ($selected === $old_id || !empty($input['name_edited']))) {
        $old_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table WHERE id=%d", $selected));
        if ($name !== $old_name) {
            $wpdb->update($table, ['name' => $name, 'updated_at' => current_time('mysql')], ['id' => $selected, 'content_type' => $type]);
            update_post_meta($post_id, '_popcorn_collection_customized', 1);
        }
    }
    if ($selected !== $old_id) {
        $wpdb->update($content, ['collection_id' => $selected, 'updated_at' => current_time('mysql')], ['id' => $item->id]);
        update_post_meta($post_id, '_popcorn_collection_customized', 1);
    }
    if (!$selected || empty($input['members_present'])) {
        return;
    }
    $member_ids = array_values(array_unique(array_filter(array_map('absint', (array) ($input['members'] ?? [])))));
    $member_ids = array_slice($member_ids, 0, 100);
    if ($selected === $old_id) {
        $visible_ids = array_slice(array_values(array_unique(array_filter(array_map('absint', (array) ($input['visible_members'] ?? []))))), 0, 200);
        foreach ($visible_ids as $member_post_id) {
            if (!in_array($member_post_id, $member_ids, true) && current_user_can('edit_post', $member_post_id) && $wpdb->update($content, ['collection_id' => 0], ['post_id' => $member_post_id, 'collection_id' => $selected])) {
                update_post_meta($member_post_id, '_popcorn_collection_customized', 1);
            }
        }
    }
    foreach ($member_ids as $member_post_id) {
        if ($member_post_id !== $post_id && get_post_type($member_post_id) === 'popcorn_' . $type && current_user_can('edit_post', $member_post_id)) {
            $current_id = $wpdb->get_var($wpdb->prepare("SELECT collection_id FROM $content WHERE post_id=%d", $member_post_id));
            if ((int) $current_id !== $selected) {
                $wpdb->update($content, ['collection_id' => $selected], ['post_id' => $member_post_id]);
                update_post_meta($member_post_id, '_popcorn_collection_customized', 1);
            }
        }
    }
}

function popcorn_core_editor_save_tmdb_id($post_id, $item, $type, $raw) {
    if (!is_scalar($raw)) {
        return;
    }
    $raw = trim((string) $raw);
    $id = $raw === '' ? null : filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        update_post_meta($post_id, '_popcorn_tmdb_id_error', 'شناسهٔ TMDb باید یک عدد صحیح مثبت باشد.');
        return;
    }
    global $wpdb;
    $table = popcorn_core_table($type);
    if ($id !== null && $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE tmdb_id=%d AND id<>%d", $id, $item->id))) {
        update_post_meta($post_id, '_popcorn_tmdb_id_error', 'این شناسهٔ TMDb برای مورد دیگری ثبت شده است.');
        return;
    }
    if ((int) $id !== (int) $item->tmdb_id && $wpdb->update($table, ['tmdb_id' => $id, 'updated_at' => current_time('mysql')], ['id' => $item->id]) === false) {
        update_post_meta($post_id, '_popcorn_tmdb_id_error', 'ذخیرهٔ شناسهٔ TMDb ناموفق بود.');
        return;
    }
    delete_post_meta($post_id, '_popcorn_tmdb_id_error');
}

function popcorn_core_editor_save_extras($post_id, $post) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || $post->post_status === 'auto-draft' || !current_user_can('edit_post', $post_id) || empty($_POST['popcorn_fields_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['popcorn_fields_nonce'])), 'popcorn_fields_' . $post_id)) {
        return;
    }
    $type = str_replace('popcorn_', '', $post->post_type);
    if (!in_array($type, ['movie', 'series', 'person'], true)) {
        return;
    }
    $item = popcorn_core_get_by_post($type, $post_id);
    if (!$item) {
        return;
    }
    if (array_key_exists('popcorn_tmdb_id', $_POST)) {
        popcorn_core_editor_save_tmdb_id($post_id, $item, $type, wp_unslash($_POST['popcorn_tmdb_id']));
    }
    if (isset($_POST['popcorn_media']) && is_array($_POST['popcorn_media'])) {
        popcorn_core_editor_save_media($post_id, $type, $item, wp_unslash($_POST['popcorn_media']));
    }
    if ($type !== 'person') {
        if (isset($_POST['popcorn_gallery']) && is_array($_POST['popcorn_gallery'])) {
            popcorn_core_editor_save_gallery($post_id, $item, $type, wp_unslash($_POST['popcorn_gallery']), $post->post_status === 'publish');
        } elseif (isset($_POST['popcorn_gallery_present'])) {
            popcorn_core_editor_save_gallery($post_id, $item, $type, [], $post->post_status === 'publish');
        }
        if (isset($_POST['popcorn_credits']) && is_array($_POST['popcorn_credits'])) {
            popcorn_core_editor_save_credits($post_id, $item, $type, wp_unslash($_POST['popcorn_credits']));
        } elseif (isset($_POST['popcorn_credits_present'])) {
            popcorn_core_editor_save_credits($post_id, $item, $type, []);
        }
        if (isset($_POST['popcorn_collection_id'])) {
            popcorn_core_editor_save_collection($post_id, $item, $type, ['id' => wp_unslash($_POST['popcorn_collection_id']), 'name' => wp_unslash($_POST['popcorn_collection_name'] ?? ''), 'name_edited' => !empty($_POST['popcorn_collection_name_edited']), 'members' => wp_unslash($_POST['popcorn_collection_members'] ?? []), 'visible_members' => wp_unslash($_POST['popcorn_collection_visible_members'] ?? []), 'members_present' => isset($_POST['popcorn_collection_members_present'])]);
        }
    }
}

foreach (['movie', 'series', 'person'] as $popcorn_tabs_type) {
    add_action('save_post_popcorn_' . $popcorn_tabs_type, 'popcorn_core_editor_save_extras', 30, 2);
}

function popcorn_core_editor_collection_candidates($type, $query, $post_id, $excluded_ids = []) {
    if (!in_array($type, ['movie', 'series'], true)) {
        return [];
    }
    global $wpdb;
    $table = popcorn_core_table($type);
    $excluded = array_slice(array_values(array_unique(array_filter(array_map('absint', (array) $excluded_ids)))), 0, 200);
    $excluded[] = absint($post_id);
    $placeholders = implode(',', array_fill(0, count($excluded), '%d'));
    if ($query === '') {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT i.post_id,i.title,i.english_title,i.original_title FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE i.post_id NOT IN ($placeholders) AND p.post_status IN ('publish','draft','pending','private') ORDER BY i.updated_at DESC LIMIT 20", $excluded));
    } else {
        $like = '%' . $wpdb->esc_like($query) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT i.post_id,i.title,i.english_title,i.original_title FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id WHERE i.post_id NOT IN ($placeholders) AND p.post_status IN ('publish','draft','pending','private') AND (i.title LIKE %s OR i.english_title LIKE %s OR i.original_title LIKE %s OR p.post_title LIKE %s) ORDER BY i.updated_at DESC LIMIT 20", array_merge($excluded, [$like, $like, $like, $like])));
    }
    $items = [];
    foreach ($rows as $row) {
        if (current_user_can('edit_post', $row->post_id)) {
            $label = trim((string) $row->title);
            if ($label === '') {
                $label = trim((string) $row->english_title) ?: get_the_title($row->post_id);
            }
            $persian_title = trim((string) $row->original_title);
            $items[] = ['id' => (int) $row->post_id, 'label' => $label, 'secondary' => $persian_title !== $label ? $persian_title : ''];
        }
    }
    return $items;
}

add_action('wp_ajax_popcorn_editor_lookup', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'دسترسی مجاز نیست.'], 403);
    }
    check_ajax_referer('popcorn_editor_lookup', 'nonce');
    $kind = isset($_POST['kind']) ? sanitize_key(wp_unslash($_POST['kind'])) : '';
    $type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
    $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
    $query = function_exists('mb_substr') ? mb_substr($query, 0, 100) : substr($query, 0, 100);
    if ($kind !== 'person' && ($kind !== 'collection' || !in_array($type, ['movie', 'series'], true))) {
        wp_send_json_error(['message' => 'درخواست نامعتبر است.'], 400);
    }
    if ($kind === 'person' && (function_exists('mb_strlen') ? mb_strlen($query) < 2 : strlen($query) < 2)) {
        wp_send_json_success(['items' => []]);
    }
    global $wpdb;
    $items = [];
    if ($kind === 'person') {
        $table = popcorn_core_table('person');
        $like = '%' . $wpdb->esc_like($query) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,name,post_id FROM $table WHERE name LIKE %s OR persian_name LIKE %s ORDER BY name LIMIT 12", $like, $like));
        foreach ($rows as $row) {
            if (current_user_can('edit_post', $row->post_id)) {
                $items[] = ['id' => (int) $row->id, 'label' => $row->name];
            }
        }
    } else {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $post_id = get_post_type($post_id) === 'popcorn_' . $type ? $post_id : 0;
        $excluded_ids = isset($_POST['excluded']) && is_array($_POST['excluded']) ? wp_unslash($_POST['excluded']) : [];
        $items = popcorn_core_editor_collection_candidates($type, $query, $post_id, $excluded_ids);
    }
    wp_send_json_success(['items' => $items]);
});

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->base, ['post', 'post-new'], true) || !in_array($screen->post_type, ['popcorn_movie', 'popcorn_series', 'popcorn_person'], true)) {
        return;
    }
    $base = dirname(__DIR__);
    $url = plugin_dir_url($base . '/popcorn-core.php');
    wp_enqueue_style('popcorn-editor-tabs', $url . 'assets/css/editor-tabs.css', [], filemtime($base . '/assets/css/editor-tabs.css'));
    wp_enqueue_script('popcorn-editor-tabs', $url . 'assets/js/editor-tabs.js', [], filemtime($base . '/assets/js/editor-tabs.js'), true);
    popcorn_core_enqueue_image_fallback();
    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    if ($post_id && get_post_status($post_id) === 'publish') {
        wp_enqueue_media();
    }
});
