<?php
defined('ABSPATH') || exit;

add_action('init', function () {
    register_term_meta('popcorn_genre', 'popcorn_genre_image_id', [
        'type' => 'integer',
        'single' => true,
        'default' => 0,
        'sanitize_callback' => 'absint',
        'show_in_rest' => ['schema' => ['type' => 'integer']],
        'auth_callback' => function ($allowed, $meta_key, $term_id) {
            return current_user_can('edit_term', $term_id);
        },
    ]);
}, 7);

function popcorn_core_genre_image_id($term_id) {
    $attachment_id = absint(get_term_meta(absint($term_id), 'popcorn_genre_image_id', true));
    return $attachment_id && wp_attachment_is_image($attachment_id) ? $attachment_id : 0;
}

function popcorn_core_genre_image($term_id, $size = 'medium_large', $attributes = []) {
    $attachment_id = popcorn_core_genre_image_id($term_id);
    if (!$attachment_id || !function_exists('popcorn_image')) {
        return '';
    }
    $term = get_term(absint($term_id), 'popcorn_genre');
    $attributes = array_merge(['alt' => $term instanceof WP_Term ? $term->name : ''], is_array($attributes) ? $attributes : []);
    return popcorn_image($attachment_id, $size, $attributes);
}

function popcorn_core_genre_image_field($term_id = 0) {
    $attachment_id = $term_id ? popcorn_core_genre_image_id($term_id) : 0;
    $url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'medium') : '';
    echo '<div class="pc-genre-image"><input class="pc-genre-image__id" type="hidden" name="popcorn_genre_image_id" value="' . esc_attr($attachment_id) . '">';
    wp_nonce_field('popcorn_genre_image', 'popcorn_genre_image_nonce');
    echo '<div class="pc-genre-image__preview"' . ($url ? '' : ' hidden') . '><img src="' . esc_url($url ?: '') . '" alt="پیش‌نمایش تصویر ژانر"></div>';
    echo '<div class="pc-genre-image__actions"><button type="button" class="button button-primary pc-genre-image__choose">' . ($attachment_id ? 'تغییر تصویر' : 'انتخاب یا بارگذاری تصویر') . '</button><button type="button" class="button pc-genre-image__remove"' . ($attachment_id ? '' : ' hidden') . '>حذف تصویر</button></div>';
    echo '<p class="description">تصویر را از رسانهٔ وردپرس انتخاب یا بارگذاری کنید. در فرانت، نسخهٔ WebP قالب استفاده می‌شود.</p></div>';
}

add_action('popcorn_genre_add_form_fields', function () {
    echo '<div class="form-field"><label>تصویر ژانر</label>';
    popcorn_core_genre_image_field();
    echo '</div>';
});

add_action('popcorn_genre_edit_form_fields', function ($term) {
    echo '<tr class="form-field"><th scope="row"><label>تصویر ژانر</label></th><td>';
    popcorn_core_genre_image_field($term->term_id);
    echo '</td></tr>';
});

function popcorn_core_save_genre_image($term_id) {
    if (!current_user_can('edit_term', $term_id) || !current_user_can('upload_files')) {
        return;
    }
    $nonce = isset($_POST['popcorn_genre_image_nonce']) && is_string($_POST['popcorn_genre_image_nonce']) ? sanitize_text_field(wp_unslash($_POST['popcorn_genre_image_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'popcorn_genre_image') || !isset($_POST['popcorn_genre_image_id'])) {
        return;
    }
    $attachment_id = absint(wp_unslash($_POST['popcorn_genre_image_id']));
    if ($attachment_id && !wp_attachment_is_image($attachment_id)) {
        return;
    }
    if ($attachment_id) {
        update_term_meta($term_id, 'popcorn_genre_image_id', $attachment_id);
    } else {
        delete_term_meta($term_id, 'popcorn_genre_image_id');
    }
}
add_action('created_popcorn_genre', 'popcorn_core_save_genre_image');
add_action('edited_popcorn_genre', 'popcorn_core_save_genre_image');

add_filter('manage_edit-popcorn_genre_columns', function ($columns) {
    $result = [];
    foreach ($columns as $key => $label) {
        $result[$key] = $label;
        if ($key === 'cb') {
            $result['popcorn_genre_image'] = 'تصویر';
        }
    }
    return $result;
});

add_filter('manage_popcorn_genre_custom_column', function ($value, $column, $term_id) {
    if ($column !== 'popcorn_genre_image') {
        return $value;
    }
    $attachment_id = popcorn_core_genre_image_id($term_id);
    return $attachment_id ? wp_get_attachment_image($attachment_id, [56, 56], false, ['alt' => '', 'class' => 'pc-genre-image__list-thumb']) : '—';
}, 10, 3);

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->taxonomy !== 'popcorn_genre') {
        return;
    }
    $base = dirname(__DIR__);
    $url = plugin_dir_url($base . '/popcorn-core.php');
    wp_enqueue_media();
    wp_enqueue_style('popcorn-genre-image', $url . 'assets/css/genre-image.css', [], filemtime($base . '/assets/css/genre-image.css'));
    wp_enqueue_script('popcorn-genre-image', $url . 'assets/js/genre-image.js', ['media-views'], filemtime($base . '/assets/js/genre-image.js'), true);
});
