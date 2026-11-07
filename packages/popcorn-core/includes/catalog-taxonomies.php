<?php
defined('ABSPATH') || exit;

add_action('init', function () {
    foreach (['popcorn_categury' => ['دسته‌بندی‌ها', 'دسته‌بندی', true], 'popcorn_tag' => ['برچسب‌ها', 'برچسب', false]] as $taxonomy => $settings) {
        [$plural, $singular, $hierarchical] = $settings;
        register_taxonomy($taxonomy, ['popcorn_movie', 'popcorn_series'], [
            'labels' => ['name' => $plural, 'singular_name' => $singular, 'menu_name' => $plural, 'search_items' => 'جست‌وجوی ' . $plural, 'all_items' => 'همهٔ ' . $plural, 'edit_item' => 'ویرایش ' . $singular, 'update_item' => 'به‌روزرسانی ' . $singular, 'add_new_item' => 'افزودن ' . $singular . ' تازه', 'new_item_name' => 'نام ' . $singular . ' تازه', 'parent_item' => $singular . ' مادر', 'parent_item_colon' => $singular . ' مادر:', 'not_found' => 'موردی پیدا نشد', 'choose_from_most_used' => 'انتخاب از ' . $plural . ' پراستفاده', 'separate_items_with_commas' => $plural . ' را با ویرگول جدا کنید', 'add_or_remove_items' => 'افزودن یا حذف ' . $plural],
            'public' => true, 'show_ui' => true, 'show_in_rest' => true, 'show_in_nav_menus' => true,
            'hierarchical' => $hierarchical, 'show_admin_column' => $hierarchical,
            'rewrite' => ['slug' => $taxonomy, 'with_front' => false, 'hierarchical' => $hierarchical],
        ]);
        foreach (['popcorn_movie', 'popcorn_series'] as $post_type) {
            unregister_taxonomy_for_object_type($hierarchical ? 'category' : 'post_tag', $post_type);
        }
    }
}, 6);

function popcorn_core_catalog_copy_term($term, $taxonomy) {
    $legacy_key = '_popcorn_legacy_' . $term->taxonomy . '_id';
    $mapped = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 1, 'meta_key' => $legacy_key, 'meta_value' => $term->term_id, 'fields' => 'ids']);
    if (is_wp_error($mapped)) { return $mapped; }
    if ($mapped) { return (int) $mapped[0]; }
    $parent = 0;
    if ($term->parent) {
        $source_parent = get_term($term->parent, $term->taxonomy);
        if (is_wp_error($source_parent) || !$source_parent) { return new WP_Error('popcorn_term_parent', 'دستهٔ مادر پیدا نشد.'); }
        $parent = popcorn_core_catalog_copy_term($source_parent, $taxonomy);
        if (is_wp_error($parent)) { return $parent; }
    }
    $existing = get_term_by('slug', $term->slug, $taxonomy);
    $result = $existing ? ['term_id' => $existing->term_id] : wp_insert_term($term->name, $taxonomy, ['slug' => $term->slug, 'description' => $term->description, 'parent' => $parent]);
    if (is_wp_error($result)) { return $result; }
    $id = (int) $result['term_id'];
    foreach (get_term_meta($term->term_id) as $key => $values) {
        if (!metadata_exists('term', $id, $key)) {
            foreach ($values as $value) { add_term_meta($id, $key, maybe_unserialize($value)); }
        }
    }
    update_term_meta($id, $legacy_key, $term->term_id);
    $yoast = get_option('wpseo_taxonomy_meta', []);
    if (is_array($yoast) && isset($yoast[$term->taxonomy][$term->term_id]) && !isset($yoast[$taxonomy][$id])) {
        $yoast[$taxonomy][$id] = $yoast[$term->taxonomy][$term->term_id];
        update_option('wpseo_taxonomy_meta', $yoast);
    }
    return $id;
}

function popcorn_core_catalog_migrate_post_terms($post_id) {
    if (!in_array(get_post_type($post_id), ['popcorn_movie', 'popcorn_series'], true)) { return true; }
    foreach (['category' => 'popcorn_categury', 'post_tag' => 'popcorn_tag'] as $source => $target) {
        $terms = wp_get_object_terms($post_id, $source);
        if (is_wp_error($terms)) { return $terms; }
        if (!$terms) { continue; }
        $ids = [];
        foreach ($terms as $term) {
            $id = popcorn_core_catalog_copy_term($term, $target);
            if (is_wp_error($id)) { return $id; }
            $ids[] = $id;
        }
        $saved = wp_set_object_terms($post_id, $ids, $target, true);
        if (is_wp_error($saved)) { return $saved; }
        $removed = wp_remove_object_terms($post_id, wp_list_pluck($terms, 'term_id'), $source);
        if (is_wp_error($removed) || $removed === false) { return is_wp_error($removed) ? $removed : new WP_Error('popcorn_term_remove', 'انتقال ارتباط دسته یا برچسب ناموفق بود.'); }
    }
    clean_object_term_cache($post_id, get_post_type($post_id));
    do_action('popcorn_core_item_saved', $post_id);
    popcorn_core_purge_playback_content($post_id);
    return true;
}

function popcorn_core_migrate_catalog_taxonomies() {
    if (get_option('popcorn_core_catalog_taxonomy_version') === '1') { return; }
    global $wpdb;
    $lock_key = 'popcorn_core_catalog_taxonomy_lock';
    $old_lock = get_option($lock_key);
    if ($old_lock && (int) $old_lock < time()) {
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $lock_key, $old_lock));
        wp_cache_delete($lock_key, 'options');
        wp_cache_delete('notoptions', 'options');
    }
    $lock = time() + 120;
    if (!add_option($lock_key, $lock, '', false)) { return; }
    try {
        $cursor = absint(get_option('popcorn_core_catalog_taxonomy_cursor', 0));
        $posts = $wpdb->get_col($wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p WHERE p.ID>%d AND p.post_type IN ('popcorn_movie','popcorn_series') AND EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=p.ID AND tt.taxonomy IN ('category','post_tag')) ORDER BY p.ID LIMIT 30", $cursor));
        if ($wpdb->last_error) { return; }
        foreach ($posts as $post_id) {
            $result = popcorn_core_catalog_migrate_post_terms((int) $post_id);
            if (is_wp_error($result)) {
                update_option('popcorn_core_catalog_taxonomy_error', $result->get_error_message(), false);
                return;
            }
            update_option('popcorn_core_catalog_taxonomy_cursor', (int) $post_id, false);
        }
        delete_option('popcorn_core_catalog_taxonomy_error');
        if (count($posts) < 30) {
            update_option('popcorn_core_catalog_taxonomy_version', '1', false);
            delete_option('popcorn_core_catalog_taxonomy_cursor');
            wp_clear_scheduled_hook('popcorn_core_migrate_catalog_taxonomies');
        } elseif (!wp_next_scheduled('popcorn_core_migrate_catalog_taxonomies')) {
            wp_schedule_single_event(time() + 5, 'popcorn_core_migrate_catalog_taxonomies');
        }
    } finally {
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $lock_key, $lock));
        wp_cache_delete($lock_key, 'options');
        wp_cache_delete('notoptions', 'options');
    }
}
add_action('admin_init', function () { if (current_user_can('manage_options')) { popcorn_core_migrate_catalog_taxonomies(); } });
add_action('popcorn_core_migrate_catalog_taxonomies', 'popcorn_core_migrate_catalog_taxonomies');
add_action('admin_notices', function () {
    $error = get_option('popcorn_core_catalog_taxonomy_error');
    if ($error && current_user_can('manage_options')) {
        echo '<div class="notice notice-error"><p>انتقال دسته‌ها و برچسب‌های پاپکورن کامل نشده است: ' . esc_html($error) . '</p></div>';
    }
});
