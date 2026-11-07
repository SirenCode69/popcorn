<?php
/*
Plugin Name: هستهٔ پاپکورن
Description: مدیریت فیلم‌ها، سریال‌ها و هنرمندان، دریافت اطلاعات از TMDb، مدیریت لینک‌های دانلود، پخش آنلاین و تبلیغات قالب پاپکورن.
Version: 1.0.0
Author: SirenCode69
Requires PHP: 8.0
License: GPL-2.0-or-later
Update URI: https://github.com/SirenCode69/popcorn
*/

defined('ABSPATH') || exit;

function popcorn_core_table($type) {
    global $wpdb;
    $names = ['movie' => 'popcorn_movies', 'series' => 'popcorn_series', 'person' => 'popcorn_people', 'credit' => 'popcorn_credits', 'collection' => 'popcorn_collections', 'ad' => 'popcorn_ads', 'download_group' => 'popcorn_download_groups', 'download_season' => 'popcorn_download_seasons', 'download_link' => 'popcorn_download_links', 'link_report' => 'popcorn_link_reports', 'notice' => 'popcorn_notices', 'notification' => 'popcorn_notifications', 'notification_campaign' => 'popcorn_notification_campaigns', 'user_rating' => 'popcorn_user_ratings', 'user_favorite' => 'popcorn_user_favorites', 'playback' => 'popcorn_playbacks', 'watch_progress' => 'popcorn_watch_progress'];
    $names['comment_vote'] = 'popcorn_comment_votes';
    $names['guest_rating'] = 'popcorn_guest_ratings';
    return isset($names[$type]) ? $wpdb->prefix . $names[$type] : '';
}

function popcorn_core_activate() {
    popcorn_core_install_tables();
    popcorn_core_install_comment_votes();
    popcorn_core_install_guest_ratings();
    popcorn_core_media_initialize();
    popcorn_core_rewrites();
    popcorn_core_register_playback_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'popcorn_core_activate');

function popcorn_core_install_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $previous_version = get_option('popcorn_core_db_version');
    $charset = $wpdb->get_charset_collate();
    foreach (['movie', 'series'] as $type) {
        $table = popcorn_core_table($type);
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tmdb_id bigint(20) unsigned DEFAULT NULL,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            collection_id bigint(20) unsigned NOT NULL DEFAULT 0,
            title varchar(255) NOT NULL,
            english_title varchar(255) NOT NULL DEFAULT '',
            original_title varchar(255) NOT NULL DEFAULT '',
            overview longtext NOT NULL,
            overview_en longtext NOT NULL,
            tagline varchar(255) NOT NULL DEFAULT '',
            release_date date DEFAULT NULL,
            vote_average decimal(4,2) NOT NULL DEFAULT 0,
            vote_count int(10) unsigned NOT NULL DEFAULT 0,
            rating decimal(4,2) NOT NULL DEFAULT 0,
            imdb_rating decimal(4,2) NOT NULL DEFAULT 0,
            imdb_votes int(10) unsigned NOT NULL DEFAULT 0,
            metacritic_rating tinyint(3) unsigned NOT NULL DEFAULT 0,
            rotten_tomatoes_rating tinyint(3) unsigned NOT NULL DEFAULT 0,
            popularity decimal(12,3) NOT NULL DEFAULT 0,
            is_featured tinyint(1) NOT NULL DEFAULT 0,
            show_in_slider tinyint(1) NOT NULL DEFAULT 0,
            is_coming_soon tinyint(1) NOT NULL DEFAULT 0,
            genres text NOT NULL,
            runtime smallint(5) unsigned NOT NULL DEFAULT 0,
            season_count smallint(5) unsigned NOT NULL DEFAULT 0,
            episode_count smallint(5) unsigned NOT NULL DEFAULT 0,
            imdb_id varchar(20) NOT NULL DEFAULT '',
            certification varchar(30) NOT NULL DEFAULT '',
            country varchar(120) NOT NULL DEFAULT '',
            language varchar(120) NOT NULL DEFAULT '',
            studio varchar(255) NOT NULL DEFAULT '',
            network varchar(255) NOT NULL DEFAULT '',
            production_status varchar(80) NOT NULL DEFAULT '',
            next_episode_date date DEFAULT NULL,
            trailer_url varchar(255) NOT NULL DEFAULT '',
            budget bigint(20) unsigned NOT NULL DEFAULT 0,
            revenue bigint(20) unsigned NOT NULL DEFAULT 0,
            keywords text NOT NULL,
            gallery longtext NOT NULL,
            poster_id bigint(20) unsigned NOT NULL DEFAULT 0,
            backdrop_id bigint(20) unsigned NOT NULL DEFAULT 0,
            poster_path text NOT NULL,
            backdrop_path text NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tmdb_id (tmdb_id),
            KEY post_id (post_id),
            KEY collection_id (collection_id),
            KEY updated_at (updated_at),
            KEY title (title(191))
        ) $charset;");
        $tmdb_column = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'tmdb_id'");
        if ($tmdb_column && $tmdb_column->Null === 'NO') {
            $wpdb->query("ALTER TABLE $table MODIFY tmdb_id bigint(20) unsigned DEFAULT NULL");
        }
        if (version_compare((string) $previous_version, '8', '<')) {
            $wpdb->query("UPDATE $table SET english_title = title WHERE english_title = '' AND tmdb_id IS NOT NULL");
        }
    }
    $people = popcorn_core_table('person');
    dbDelta("CREATE TABLE $people (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        tmdb_id bigint(20) unsigned DEFAULT NULL,
        post_id bigint(20) unsigned NOT NULL DEFAULT 0,
        name varchar(255) NOT NULL,
        persian_name varchar(255) NOT NULL DEFAULT '',
        biography longtext NOT NULL,
        biography_fa longtext NOT NULL,
        birth_date date DEFAULT NULL,
        death_date date DEFAULT NULL,
        gender tinyint(1) unsigned NOT NULL DEFAULT 0,
        place_of_birth varchar(255) NOT NULL DEFAULT '',
        profession varchar(120) NOT NULL DEFAULT '',
        popularity decimal(12,3) NOT NULL DEFAULT 0,
        imdb_id varchar(20) NOT NULL DEFAULT '',
        profile_id bigint(20) unsigned NOT NULL DEFAULT 0,
        profile_path text NOT NULL,
        backdrop_id bigint(20) unsigned NOT NULL DEFAULT 0,
        backdrop_path text NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY tmdb_id (tmdb_id),
        KEY post_id (post_id),
        KEY name (name(191))
    ) $charset;");
    $credits = popcorn_core_table('credit');
    dbDelta("CREATE TABLE $credits (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        content_type varchar(10) NOT NULL,
        content_id bigint(20) unsigned NOT NULL,
        person_id bigint(20) unsigned NOT NULL,
        role varchar(20) NOT NULL,
        character_name varchar(255) NOT NULL DEFAULT '',
        job varchar(120) NOT NULL DEFAULT '',
        sort_order smallint(5) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY content (content_type,content_id),
        KEY person_id (person_id)
    ) $charset;");
    $collections = popcorn_core_table('collection');
    dbDelta("CREATE TABLE $collections (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        content_type varchar(10) NOT NULL,
        tmdb_id bigint(20) unsigned DEFAULT NULL,
        name varchar(255) NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY type_tmdb (content_type,tmdb_id),
        KEY content_type (content_type),
        KEY name (name(191))
    ) $charset;");
    $ads = popcorn_core_table('ad');
    dbDelta("CREATE TABLE $ads (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        placement varchar(30) NOT NULL DEFAULT 'home',
        image_id bigint(20) unsigned NOT NULL,
        target_url varchar(2048) NOT NULL DEFAULT '',
        alt_text varchar(255) NOT NULL DEFAULT '',
        is_active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY placement_active (placement,is_active)
    ) $charset;");
    $groups = popcorn_core_table('download_group');
    dbDelta("CREATE TABLE $groups (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        content_type varchar(10) NOT NULL,
        title varchar(255) NOT NULL DEFAULT '',
        sort_order int(10) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY post_id (post_id),
        KEY content_type (content_type)
    ) $charset;");
    $seasons = popcorn_core_table('download_season');
    dbDelta("CREATE TABLE $seasons (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        group_id bigint(20) unsigned NOT NULL,
        season_number smallint(5) unsigned NOT NULL DEFAULT 1,
        title varchar(255) NOT NULL DEFAULT '',
        sort_order int(10) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY group_id (group_id)
    ) $charset;");
    $links = popcorn_core_table('download_link');
    dbDelta("CREATE TABLE $links (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        group_id bigint(20) unsigned NOT NULL,
        season_id bigint(20) unsigned NOT NULL DEFAULT 0,
        title varchar(255) NOT NULL DEFAULT '',
        quality varchar(80) NOT NULL DEFAULT '',
        encoder varchar(80) NOT NULL DEFAULT '',
        resolution varchar(80) NOT NULL DEFAULT '',
        size varchar(80) NOT NULL DEFAULT '',
        url varchar(2048) NOT NULL DEFAULT '',
        access_mode varchar(10) NOT NULL DEFAULT 'download',
        description text NOT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        is_dubbed tinyint(1) NOT NULL DEFAULT 0,
        has_subtitle tinyint(1) NOT NULL DEFAULT 0,
        sort_order int(10) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY group_id (group_id),
        KEY season_id (season_id)
    ) $charset;");
    $reports = popcorn_core_table('link_report');
    dbDelta("CREATE TABLE $reports (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        message text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'new',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY status_created (status,created_at),
        KEY post_created (post_id,created_at)
    ) $charset;");
    if (version_compare((string) $previous_version, '10', '<') && $wpdb->get_var("SHOW COLUMNS FROM $links LIKE 'size_unit'")) {
        $wpdb->query("UPDATE $links SET size=CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM size)), ' ', size_unit) WHERE CAST(size AS DECIMAL(10,2))>0 AND size_unit<>''");
        $wpdb->query("UPDATE $links SET size='' WHERE size IN ('0', '0.00')");
    }
    $notices = popcorn_core_table('notice');
    dbDelta("CREATE TABLE $notices (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        author_id bigint(20) unsigned NOT NULL DEFAULT 0,
        message text NOT NULL,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY post_active (post_id,is_active),
        KEY created_at (created_at)
    ) $charset;");
    $notifications = popcorn_core_table('notification');
    dbDelta("CREATE TABLE $notifications (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        source_key varchar(80) NOT NULL,
        title varchar(191) NOT NULL,
        message text NOT NULL,
        url varchar(2048) NOT NULL DEFAULT '',
        show_in_site tinyint(1) NOT NULL DEFAULT 1,
        telegram_status varchar(12) NOT NULL DEFAULT 'none',
        telegram_attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
        telegram_claimed_at datetime DEFAULT NULL,
        read_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_source (user_id,source_key),
        KEY user_read (user_id,show_in_site,read_at),
        KEY telegram_queue (telegram_status,telegram_attempts,id)
    ) $charset;");
    $campaigns = popcorn_core_table('notification_campaign');
    dbDelta("CREATE TABLE $campaigns (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(191) NOT NULL,
        message text NOT NULL,
        url varchar(2048) NOT NULL DEFAULT '',
        audience varchar(20) NOT NULL DEFAULT 'all',
        target_id bigint(20) unsigned NOT NULL DEFAULT 0,
        site_enabled tinyint(1) NOT NULL DEFAULT 1,
        telegram_enabled tinyint(1) NOT NULL DEFAULT 0,
        last_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        completed_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY completed_at (completed_at)
    ) $charset;");
    $ratings = popcorn_core_table('user_rating');
    dbDelta("CREATE TABLE $ratings (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        post_id bigint(20) unsigned NOT NULL,
        rating tinyint(3) unsigned NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_post (user_id,post_id),
        KEY post_id (post_id)
    ) $charset;");
    $favorites = popcorn_core_table('user_favorite');
    dbDelta("CREATE TABLE $favorites (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        post_id bigint(20) unsigned NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_post (user_id,post_id),
        KEY post_id (post_id)
    ) $charset;");
    $playbacks = popcorn_core_table('playback');
    dbDelta("CREATE TABLE $playbacks (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        content_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
        season_number smallint(5) unsigned NOT NULL DEFAULT 0,
        episode_number smallint(5) unsigned NOT NULL DEFAULT 0,
        quality varchar(80) NOT NULL DEFAULT '',
        video_url text NOT NULL,
        mp4_url text NOT NULL,
        direct_qualities longtext NULL,
        subtitles longtext NULL,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY post_id (post_id),
        KEY content_active (content_post_id,is_active,season_number,episode_number)
    ) $charset;");
    $progress = popcorn_core_table('watch_progress');
    dbDelta("CREATE TABLE $progress (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        playback_post_id bigint(20) unsigned NOT NULL,
        position_seconds decimal(12,3) NOT NULL DEFAULT 0,
        duration_seconds decimal(12,3) NOT NULL DEFAULT 0,
        client_sequence bigint(20) unsigned NOT NULL DEFAULT 0,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_playback (user_id,playback_post_id),
        KEY playback_post_id (playback_post_id)
    ) $charset;");
    update_option('popcorn_core_db_version', '18', false);
}

add_action('init', function () {
    if (get_option('popcorn_core_db_version') !== '18') {
        popcorn_core_install_tables();
    }
}, 1);

function popcorn_core_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'popcorn_core_deactivate');

function popcorn_core_rewrites() {
    add_rewrite_rule('^films/?$', 'index.php?popcorn_view=movie', 'top');
    add_rewrite_rule('^series/?$', 'index.php?popcorn_view=series', 'top');
    add_rewrite_rule('^title/(movie|series)/([0-9]+)/?$', 'index.php?popcorn_view=title&popcorn_type=$matches[1]&popcorn_id=$matches[2]', 'top');
    add_rewrite_rule('^notifications/?$', 'index.php?popcorn_notifications=1', 'top');
}
add_action('init', 'popcorn_core_rewrites');

add_action('init', function () {
    if (get_option('popcorn_core_rewrite_version') !== '6') {
        flush_rewrite_rules();
        update_option('popcorn_core_rewrite_version', '6', false);
    }
}, 100);

add_action('template_redirect', function () {
    if (get_query_var('popcorn_view') !== 'title') {
        return;
    }
    $item = popcorn_get_item(get_query_var('popcorn_type'), absint(get_query_var('popcorn_id')));
    if ($item && $item->post_id) {
        wp_safe_redirect(get_permalink($item->post_id), 301);
        exit;
    }
});

add_action('init', function () {
    $icons = [
        'movie' => '<path fill="#fff" d="M3 8h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"/><path fill="#fff" d="M3 8l1.6-5h15.8L22 8H3Z"/><path stroke="#000" d="M9 3 7.4 8M16 3l-1.6 5M4 8h16"/><path fill="#000" stroke="none" d="m10 11 5 3-5 3v-6Z"/>',
        'series' => '<rect x="3" y="6" width="18" height="14" rx="2" fill="#fff"/><path d="m8 2 4 4 4-4M8 22h8"/><rect x="5" y="8" width="14" height="10" rx="1" stroke="#000" stroke-width="1.4"/><path fill="#000" stroke="none" d="m10 10 5 3-5 3v-6Z"/>',
        'person' => '<circle cx="12" cy="7" r="3.5" fill="#fff"/><path fill="#fff" d="M4.5 20v-1.2c0-3.2 3-5.3 7.5-5.3s7.5 2.1 7.5 5.3V20H4.5Z"/><path stroke="#000" stroke-width="1.4" d="m9 15 3 3 3-3"/>',
    ];
    foreach (['movie' => ['name' => 'فیلم‌ها', 'singular' => 'فیلم', 'slug' => 'film'], 'series' => ['name' => 'سریال‌ها', 'singular' => 'سریال', 'slug' => 'show'], 'person' => ['name' => 'هنرمندان', 'singular' => 'هنرمند', 'slug' => 'artist']] as $type => $labels) {
        register_post_type('popcorn_' . $type, [
            'labels' => ['name' => $labels['name'], 'singular_name' => $labels['singular'], 'menu_name' => $labels['name'], 'all_items' => 'همهٔ ' . $labels['name'], 'add_new' => 'افزودن', 'add_new_item' => 'افزودن ' . $labels['singular'], 'edit_item' => 'ویرایش ' . $labels['singular'], 'view_item' => 'نمایش ' . $labels['singular'], 'search_items' => 'جست‌وجوی ' . $labels['name'], 'not_found' => 'موردی پیدا نشد'],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'custom-fields'],
            'taxonomies' => $type === 'person' ? ['post_tag'] : ['popcorn_categury', 'popcorn_tag', 'popcorn_genre'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'rewrite' => ['slug' => $labels['slug'], 'with_front' => false],
            'has_archive' => $type === 'person' ? 'artists' : false,
            'menu_icon' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $icons[$type] . '</svg>'),
            'menu_position' => ['movie' => 26, 'series' => 27, 'person' => 28][$type],
        ]);
    }
}, 5);

add_filter('query_vars', function ($vars) {
    return array_merge($vars, ['popcorn_view', 'popcorn_type', 'popcorn_id', 'popcorn_notifications']);
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() && $query->is_main_query() && in_array($query->get('popcorn_view'), ['movie', 'series', 'title'], true)) {
        $query->is_home = false;
        $query->set('no_found_rows', true);
    }
});

add_filter('template_include', function ($template) {
    $view = get_query_var('popcorn_view');
    if (!in_array($view, ['movie', 'series', 'title'], true)) {
        return $template;
    }
    if ($view === 'title') {
        $type = get_query_var('popcorn_type');
        $item = popcorn_get_item($type, absint(get_query_var('popcorn_id')));
        if (!$item) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return get_404_template();
        }
        $file = locate_template('popcorn-single.php');
    } else {
        $file = locate_template('popcorn-archive.php');
    }
    if (!$file) {
        return $template;
    }
    global $wp_query;
    $wp_query->is_404 = false;
    status_header(200);
    return $file;
}, 99);

add_filter('pre_get_document_title', function ($title) {
    $view = get_query_var('popcorn_view');
    if ($view === 'movie') {
        return 'فیلم‌ها | ' . get_bloginfo('name');
    }
    if ($view === 'series') {
        return 'سریال‌ها | ' . get_bloginfo('name');
    }
    if ($view === 'title') {
        $item = popcorn_get_item(get_query_var('popcorn_type'), absint(get_query_var('popcorn_id')));
        return $item ? $item->title . ' | ' . get_bloginfo('name') : $title;
    }
    return $title;
});

function popcorn_catalog_url($type) {
    return home_url($type === 'series' ? '/series/' : '/films/');
}

function popcorn_item_url($item) {
    if (!empty($item->post_id) && get_post_status($item->post_id) === 'publish') {
        return get_permalink($item->post_id);
    }
    return home_url('/title/' . ($item->popcorn_type === 'series' ? 'series' : 'movie') . '/' . absint($item->id) . '/');
}

function popcorn_display_title($item) {
    $persian = (string) ($item->original_title ?? '');
    $main = (string) ($item->title ?? '');
    if ($persian && preg_match('/[\x{0600}-\x{06FF}]/u', $persian)) {
        return $persian;
    }
    return $main ?: $persian;
}

function popcorn_display_overview($item) {
    $persian = (string) ($item->overview_en ?? '');
    $main = (string) ($item->overview ?? '');
    if ($persian && preg_match('/[\x{0600}-\x{06FF}]/u', $persian)) {
        return $persian;
    }
    return $main ?: $persian;
}

function popcorn_get_items($type = 'movie', $limit = 12, $offset = 0, $search = '') {
    global $wpdb;
    if (!in_array($type, ['movie', 'series'], true)) {
        return [];
    }
    $table = popcorn_core_table($type);
    $limit = min(100, max(1, (int) $limit));
    $offset = max(0, (int) $offset);
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish' WHERE i.title LIKE %s OR i.english_title LIKE %s OR i.original_title LIKE %s OR i.overview_en LIKE %s OR p.post_title LIKE %s OR p.post_content LIKE %s OR p.post_excerpt LIKE %s ORDER BY i.updated_at DESC LIMIT %d OFFSET %d", $like, $like, $like, $like, $like, $like, $like, $limit, $offset));
    } else {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish' ORDER BY i.updated_at DESC LIMIT %d OFFSET %d", $limit, $offset));
    }
    foreach ($rows as $row) {
        $row->popcorn_type = $type;
    }
    return $rows;
}

function popcorn_get_slider_items($limit = 6) {
    static $cache = [];
    $limit = min(6, max(1, absint($limit)));
    if (isset($cache[$limit])) {
        return $cache[$limit];
    }
    global $wpdb;
    $items = [];
    foreach (['movie', 'series'] as $type) {
        $table = popcorn_core_table($type);
        $post_type = 'popcorn_' . $type;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT i.* FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_type=%s AND p.post_status='publish' WHERE i.show_in_slider=1 ORDER BY i.updated_at DESC,i.id DESC LIMIT %d", $post_type, $limit));
        foreach ($rows as $row) {
            $row->popcorn_type = $type;
            $items[] = $row;
        }
    }
    usort($items, static function ($a, $b) {
        $date = strcmp((string) $b->updated_at, (string) $a->updated_at);
        return $date ?: ((int) $b->id <=> (int) $a->id);
    });
    return $cache[$limit] = array_slice($items, 0, $limit);
}

function popcorn_count_items($type, $search = '') {
    global $wpdb;
    static $counts = [];
    if (!in_array($type, ['movie', 'series'], true)) {
        return 0;
    }
    $cache_key = $type . ':' . $search;
    if (isset($counts[$cache_key])) {
        return $counts[$cache_key];
    }
    $table = popcorn_core_table($type);
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $counts[$cache_key] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish' WHERE i.title LIKE %s OR i.english_title LIKE %s OR i.original_title LIKE %s OR i.overview_en LIKE %s OR p.post_title LIKE %s OR p.post_content LIKE %s OR p.post_excerpt LIKE %s", $like, $like, $like, $like, $like, $like, $like));
        return $counts[$cache_key];
    }
    $counts[$cache_key] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table i INNER JOIN {$wpdb->posts} p ON p.ID=i.post_id AND p.post_status='publish'");
    return $counts[$cache_key];
}

function popcorn_get_item($type, $id) {
    global $wpdb;
    if (!in_array($type, ['movie', 'series'], true) || !$id) {
        return null;
    }
    $table = popcorn_core_table($type);
    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if ($item && get_post_status($item->post_id) !== 'publish') {
        return null;
    }
    if ($item) {
        $item->popcorn_type = $type;
    }
    return $item;
}

function popcorn_core_ad_placements() {
    return [
        'header' => ['label' => 'بعد از بخش بالای صفحه', 'description' => 'در همهٔ صفحات، بعد از اسلایدر یا معرفی اثر و بدون جابه‌جایی هدر.'],
        'home' => ['label' => 'صفحهٔ اصلی؛ بعد از آثار ویژه', 'description' => 'بین آثار ویژه و ژانرهای محبوب در صفحهٔ اصلی.'],
        'catalog' => ['label' => 'فهرست‌ها؛ بالای نتایج', 'description' => 'بعد از فیلترها و قبل از کارت‌های فیلم، سریال، هنرمند، خبر و نتایج جست‌وجو.'],
        'downloads' => ['label' => 'بالای لینک‌های دانلود', 'description' => 'در صفحهٔ فیلم و سریال، درست قبل از بخش دانلود؛ فقط وقتی بخش دانلود وجود دارد.'],
        'title_sidebar' => ['label' => 'کنار اثر؛ زیر امتیاز و اطلاعیه', 'description' => 'در ستون امتیازهای فیلم و سریال؛ در موبایل همراه همین ستون نمایش داده می‌شود.'],
        'post_content' => ['label' => 'پایان نوشتهٔ وبلاگ', 'description' => 'بعد از متن نوشته و قبل از دیدگاه‌ها.'],
        'footer' => ['label' => 'قبل از فوتر', 'description' => 'در انتهای صفحات معمول سایت، بالای فوتر.'],
    ];
}

function popcorn_get_ads($placement) {
    global $wpdb;
    $table = popcorn_core_table('ad');
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE placement = %s AND is_active = 1 ORDER BY id DESC", $placement));
}

function popcorn_tmdb_request($path, $args = []) {
    $credential = trim((string) get_option('popcorn_tmdb_token', ''));
    $credential = preg_replace('/^Bearer\s+/i', '', $credential);
    if (!$credential) {
        return new WP_Error('missing_token', 'ابتدا کلید API یا توکن دسترسی TMDb را در تنظیمات ثبت کنید.');
    }
    $query = array_merge(['language' => 'fa-IR'], $args);
    $headers = ['Accept' => 'application/json'];
    if (preg_match('/^[a-f0-9]{32}$/i', $credential)) {
        $query['api_key'] = $credential;
    } elseif (preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+$/', $credential)) {
        $headers['Authorization'] = 'Bearer ' . $credential;
    } else {
        return new WP_Error('invalid_credential', 'فرمت کلید TMDb معتبر نیست. API Key نسخهٔ ۳ یا API Read Access Token را وارد کنید.');
    }
    $url = add_query_arg($query, 'https://api.themoviedb.org/3/' . ltrim($path, '/'));
    $response = wp_safe_remote_get($url, ['headers' => $headers, 'timeout' => 15, 'redirection' => 0]);
    if (is_wp_error($response)) {
        return new WP_Error('tmdb_connection', 'اتصال به TMDb برقرار نشد. اتصال اینترنت سرور را بررسی کنید.');
    }
    if (wp_remote_retrieve_response_code($response) !== 200) {
        if (wp_remote_retrieve_response_code($response) === 401) {
            return new WP_Error('tmdb_auth', 'TMDb کلید ثبت‌شده را نپذیرفت. در تنظیمات پاپکورن کلید API یا توکن دسترسی معتبر وارد کنید.');
        }
        return new WP_Error('tmdb_http', 'درخواست TMDb ناموفق بود. کد: ' . wp_remote_retrieve_response_code($response));
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    return is_array($data) ? $data : new WP_Error('tmdb_json', 'پاسخ TMDb معتبر نیست.');
}

function popcorn_tmdb_existing_image($path, $urls) {
    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_type='attachment' AND p.post_mime_type LIKE 'image/%%' AND ((m.meta_key='_popcorn_tmdb_image_path' AND m.meta_value=%s) OR (m.meta_key='_source_url' AND m.meta_value IN (%s,%s))) ORDER BY p.ID DESC", $path, $urls['primary'], $urls['fallback'] ?? ''));
    foreach ($ids as $id) {
        $file = get_attached_file($id);
        if ($file && is_file($file) && wp_attachment_is_image($id)) {
            return (int) $id;
        }
    }
    return 0;
}

function popcorn_tmdb_image($path, $title) {
    if (!$path || !preg_match('~^/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~', $path)) {
        return 0;
    }
    $urls = popcorn_core_url_candidates('https://image.tmdb.org/t/p/w780' . $path);
    $existing = popcorn_tmdb_existing_image($path, $urls);
    if ($existing) {
        return $existing;
    }
    $lock = 'popcorn_tmdb_image_lock_' . md5($path);
    $started = (int) get_option($lock, 0);
    if ($started && $started < time() - 300) {
        delete_option($lock);
    }
    if (!add_option($lock, time(), '', false)) {
        return popcorn_tmdb_existing_image($path, $urls);
    }
    try {
        $existing = popcorn_tmdb_existing_image($path, $urls);
        if ($existing) {
            return $existing;
        }
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $id = media_sideload_image($urls['primary'], 0, $title, 'id');
        if (is_wp_error($id) && $id->get_error_code() === 'http_request_failed' && !empty($urls['fallback'])) {
            $id = media_sideload_image($urls['fallback'], 0, $title, 'id');
        }
        if (is_wp_error($id)) {
            return 0;
        }
        update_post_meta($id, '_popcorn_tmdb_image_path', $path);
        popcorn_core_prepare_webp($id);
        return (int) $id;
    } finally {
        delete_option($lock);
    }
}

function popcorn_core_prepare_webp($attachment_id) {
    if (!function_exists('popcorn_webp_src')) {
        return;
    }
    $sizes = array_merge(['medium', 'medium_large', 'large', 'full'], array_column(popcorn_core_media_options()['sizes'], 'slug'));
    foreach (array_unique($sizes) as $size) {
        popcorn_webp_src($attachment_id, $size);
    }
}

require_once __DIR__ . '/includes/network.php';
require_once __DIR__ . '/includes/media-settings.php';
require_once __DIR__ . '/includes/downloads.php';
require_once __DIR__ . '/includes/protected-links.php';
require_once __DIR__ . '/includes/link-reports.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/notices.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/guest-ratings.php';
require_once __DIR__ . '/includes/engagement.php';
require_once __DIR__ . '/includes/comment-votes.php';
require_once __DIR__ . '/includes/search-integration.php';
require_once __DIR__ . '/includes/localized-fields.php';
require_once __DIR__ . '/includes/tmdb-import.php';
require_once __DIR__ . '/includes/mymemory.php';
require_once __DIR__ . '/includes/content-editor.php';
require_once __DIR__ . '/includes/catalog-taxonomies.php';
require_once __DIR__ . '/includes/genre-images.php';
require_once __DIR__ . '/includes/homepage.php';
require_once __DIR__ . '/includes/playback-data.php';
require_once __DIR__ . '/includes/playback-admin.php';
require_once __DIR__ . '/includes/playback-public.php';
require_once __DIR__ . '/includes/playback-progress.php';
require_once __DIR__ . '/includes/mega-menus.php';
require_once __DIR__ . '/includes/editor-tabs.php';
require_once __DIR__ . '/includes/person-list.php';
require_once __DIR__ . '/includes/person-bulk-translation.php';
require_once __DIR__ . '/includes/person-archive.php';
require_once __DIR__ . '/includes/catalog-archive.php';
require_once __DIR__ . '/includes/schedule.php';
require_once __DIR__ . '/includes/blog-archive.php';
require_once __DIR__ . '/includes/content-public.php';
require_once __DIR__ . '/includes/content-list.php';
require_once __DIR__ . '/includes/tmdb-search.php';

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=popcorn_movie', 'دریافت فیلم از TMDb', 'دریافت از TMDb', 'edit_posts', 'popcorn-import-movie', 'popcorn_admin_import_page');
    add_submenu_page('edit.php?post_type=popcorn_series', 'دریافت سریال از TMDb', 'دریافت از TMDb', 'edit_posts', 'popcorn-import-series', 'popcorn_admin_import_page');
    add_submenu_page('edit.php?post_type=popcorn_person', 'دریافت هنرمند از TMDb', 'دریافت از TMDb', 'edit_posts', 'popcorn-import-person', 'popcorn_admin_import_page');
});

function popcorn_import_admin_url($type) {
    $type = in_array($type, ['movie', 'series', 'person'], true) ? $type : 'movie';
    return admin_url('edit.php?post_type=popcorn_' . $type . '&page=popcorn-import-' . $type);
}

add_action('popcorn_settings_tab_tmdb', 'popcorn_admin_tmdb_settings_tab');
add_action('popcorn_settings_tab_ads', 'popcorn_admin_ads_page');

function popcorn_tmdb_admin_message($code) {
    $messages = ['saved' => 'اطلاعات TMDb ذخیره شد.', 'connected' => 'اتصال به TMDb برقرار است و کلید معتبر است.', 'auth_error' => 'TMDb کلید ثبت‌شده را نپذیرفت. کلید API یا API Read Access Token را بررسی کنید.', 'invalid_credential' => 'فرمت کلید معتبر نیست. API Key نسخهٔ ۳ یا API Read Access Token را وارد کنید.', 'missing_token' => 'ابتدا کلید TMDb را ثبت کنید.', 'connection_error' => 'اتصال به TMDb برقرار نشد. اتصال اینترنت سرور را بررسی کنید.', 'error' => 'دریافت ناموفق بود. شناسه و پاسخ TMDb را بررسی کنید.'];
    return $messages[$code] ?? '';
}

function popcorn_admin_tmdb_settings_tab() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $message = isset($_GET['popcorn_message']) ? sanitize_key(wp_unslash($_GET['popcorn_message'])) : '';
    if (popcorn_tmdb_admin_message($message)) {
        echo '<div class="notice notice-' . (in_array($message, ['saved', 'connected'], true) ? 'success' : 'error') . '"><p>' . esc_html(popcorn_tmdb_admin_message($message)) . '</p></div>';
    }
    $saved_credential = trim((string) get_option('popcorn_tmdb_token', ''));
    $placeholder = !$saved_credential ? 'کلید را وارد کنید' : (preg_match('/^[a-f0-9]{32}$/i', $saved_credential) ? 'API Key نسخهٔ ۳ ثبت شده است' : 'توکن دسترسی ثبت شده است');
    echo '<div class="pc-admin__card"><h2>اتصال به TMDb</h2><p>API Key نسخهٔ ۳ یا API Read Access Token را ثبت کنید. دریافت فیلم، سریال و هنرمند از داخل پست‌تایپ هر بخش انجام می‌شود.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('popcorn_token');
    echo '<input type="hidden" name="action" value="popcorn_save_token"><table class="form-table"><tr><th><label for="popcorn-token">کلید اتصال TMDb</label></th><td><input id="popcorn-token" name="token" type="password" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr($placeholder) . '"><p class="description">کلید فعلی نمایش داده نمی‌شود. کلید ۳۲ کاراکتری با پارامتر api_key و توکن خواندن با Bearer ارسال می‌شود.</p></td></tr></table><p><button class="button button-primary">ذخیرهٔ کلید</button></p></form><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('popcorn_test_tmdb');
    echo '<input type="hidden" name="action" value="popcorn_test_tmdb"><button class="button">بررسی اتصال TMDb</button></form></div>';
    echo '<div class="pc-admin__card"><h2>دریافت محتوا</h2><p>فیلم، سریال و هنرمند در جدول‌های اختصاصی و پست‌تایپ‌های قابل ویرایش وردپرس ذخیره می‌شوند. بازیگران و عوامل هر اثر در پس‌زمینه تکمیل می‌شوند.</p><div class="pc-admin__links"><a href="' . esc_url(popcorn_import_admin_url('movie')) . '">دریافت فیلم از TMDb</a><a href="' . esc_url(popcorn_import_admin_url('series')) . '">دریافت سریال از TMDb</a><a href="' . esc_url(popcorn_import_admin_url('person')) . '">دریافت هنرمند از TMDb</a></div><a href="https://developer.themoviedb.org/docs/getting-started" target="_blank" rel="noopener noreferrer">راهنمای رسمی TMDb</a></div>';
}

function popcorn_admin_import_page() {
    if (!current_user_can('edit_posts')) {
        return;
    }
    $type = isset($_GET['post_type']) ? str_replace('popcorn_', '', sanitize_key(wp_unslash($_GET['post_type']))) : 'movie';
    $type = in_array($type, ['movie', 'series', 'person'], true) ? $type : 'movie';
    $label = ['movie' => 'فیلم', 'series' => 'سریال', 'person' => 'هنرمند'][$type];
    $search = isset($_GET['popcorn_search']) ? sanitize_text_field(wp_unslash($_GET['popcorn_search'])) : '';
    $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
    if ($post_id && (get_post_type($post_id) !== 'popcorn_' . $type || !current_user_can('edit_post', $post_id))) {
        $post_id = 0;
    }
    $message = isset($_GET['popcorn_message']) ? sanitize_key(wp_unslash($_GET['popcorn_message'])) : '';
    echo '<div class="wrap pc-admin pc-admin-import"><h1>دریافت ' . esc_html($label) . ' از TMDb</h1><p><a href="' . esc_url(admin_url('admin.php?page=popcorn-settings&tab=tmdb')) . '">تنظیمات اتصال TMDb</a></p>';
    if ($message === 'saved' || $message === 'draft_saved') {
        echo '<div class="notice notice-success"><p>' . ($message === 'draft_saved' ? 'پیش‌نویس ساخته شد. تصاویر پس از انتشار دریافت و به WebP تبدیل می‌شوند.' : 'محتوا اضافه یا به‌روزرسانی شد.') . '</p></div>';
    } elseif (popcorn_tmdb_admin_message($message)) {
        echo '<div class="notice notice-error"><p>' . esc_html(popcorn_tmdb_admin_message($message)) . '</p></div>';
    }
    echo '<form id="popcorn-tmdb-search" method="get" action="' . esc_url(admin_url('edit.php')) . '" data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('popcorn_search_tmdb')) . '" data-type="' . esc_attr($type) . '" data-post-id="' . esc_attr($post_id) . '"><input type="hidden" name="post_type" value="popcorn_' . esc_attr($type) . '"><input type="hidden" name="page" value="popcorn-import-' . esc_attr($type) . '"><input type="hidden" name="post_id" value="' . esc_attr($post_id) . '"><input type="search" name="popcorn_search" value="' . esc_attr($search) . '" placeholder="نام یا شناسهٔ ' . esc_attr($label) . '" maxlength="120" required> <button class="button button-primary" type="submit">جست‌وجو در TMDb</button></form>';
    echo '<div id="popcorn-tmdb-results" aria-live="polite">';
    if ($search !== '') {
        $results = popcorn_tmdb_search_data($type, $search);
        echo is_wp_error($results) ? '<p class="pc-import__empty">' . esc_html($results->get_error_message()) . '</p>' : popcorn_tmdb_search_html($type, $results, $post_id, $search);
    }
    echo '</div></div>';
}

add_action('admin_post_popcorn_save_token', function () {
    current_user_can('manage_options') || wp_die('دسترسی مجاز نیست.');
    check_admin_referer('popcorn_token');
    $token = isset($_POST['token']) ? trim(sanitize_text_field(wp_unslash($_POST['token']))) : '';
    $token = preg_replace('/^Bearer\s+/i', '', $token);
    if ($token !== '' && !preg_match('/^[a-f0-9]{32}$/i', $token) && !preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+$/', $token)) {
        wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=tmdb&popcorn_message=invalid_credential'));
        exit;
    }
    if ($token !== '') {
        update_option('popcorn_tmdb_token', $token, false);
    }
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=tmdb&popcorn_message=saved'));
    exit;
});

add_action('admin_post_popcorn_test_tmdb', function () {
    current_user_can('manage_options') || wp_die('دسترسی مجاز نیست.');
    check_admin_referer('popcorn_test_tmdb');
    $result = popcorn_tmdb_request('authentication');
    $message = !is_wp_error($result) && !empty($result['success']) ? 'connected' : (is_wp_error($result) ? popcorn_tmdb_error_message_code($result) : 'error');
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=tmdb&popcorn_message=' . $message));
    exit;
});

function popcorn_tmdb_error_message_code($error) {
    if (in_array($error->get_error_code(), ['tmdb_auth', 'invalid_credential', 'missing_token'], true)) {
        return $error->get_error_code() === 'tmdb_auth' ? 'auth_error' : $error->get_error_code();
    }
    return $error->get_error_code() === 'tmdb_connection' ? 'connection_error' : 'error';
}

add_action('admin_post_popcorn_import', function () {
    current_user_can('edit_posts') || wp_die('دسترسی مجاز نیست.');
    check_admin_referer('popcorn_import');
    $type = isset($_REQUEST['type']) ? sanitize_key(wp_unslash($_REQUEST['type'])) : '';
    $id = isset($_REQUEST['tmdb_id']) ? absint($_REQUEST['tmdb_id']) : 0;
    $post_id = isset($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : 0;
    $return_search = isset($_POST['return_search']) ? sanitize_text_field(wp_unslash($_POST['return_search'])) : '';
    if ($post_id && (get_post_type($post_id) !== 'popcorn_' . $type || !current_user_can('edit_post', $post_id))) {
        wp_die('دسترسی مجاز نیست.');
    }
    $as_draft = !empty($_POST['as_draft']) && !$post_id;
    $result = popcorn_import_tmdb($type, $id, $post_id, $as_draft);
    $return = $post_id ? get_edit_post_link($post_id, 'raw') : popcorn_import_admin_url($type);
    if (!$post_id && $return_search !== '') {
        $return = add_query_arg('popcorn_search', $return_search, $return);
    }
    wp_safe_redirect(add_query_arg('popcorn_message', is_wp_error($result) ? popcorn_tmdb_error_message_code($result) : ($as_draft ? 'draft_saved' : 'saved'), $return));
    exit;
});

add_action('admin_notices', function () {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['popcorn_movie', 'popcorn_series', 'popcorn_person'], true) || !in_array($screen->base, ['post', 'post-new'], true)) {
        return;
    }
    $message = isset($_GET['popcorn_message']) ? sanitize_key(wp_unslash($_GET['popcorn_message'])) : '';
    if (popcorn_tmdb_admin_message($message)) {
        echo '<div class="notice notice-' . ($message === 'saved' ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html(popcorn_tmdb_admin_message($message)) . '</p></div>';
    }
});

function popcorn_admin_ads_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $table = popcorn_core_table('ad');
    $ads = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 100");
    $placements = popcorn_core_ad_placements();
    $edit_id = isset($_GET['ad_id']) ? absint($_GET['ad_id']) : 0;
    $editing = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id)) : null;
    $message = isset($_GET['popcorn_message']) ? sanitize_key(wp_unslash($_GET['popcorn_message'])) : '';
    echo '<div class="pc-admin__card"><h2>' . ($editing ? 'ویرایش بنر تبلیغاتی' : 'افزودن بنر تبلیغاتی') . '</h2><p>تصویر، جایگاه و مقصد بنر را مشخص کنید. فایل‌های GIF بدون تبدیل نمایش داده می‌شوند تا انیمیشن حفظ شود. بنرها با نسبت تصویر اصلی و بدون کشیدگی نمایش داده می‌شوند.</p>';
    if ($message === 'saved') {
        echo '<div class="notice notice-success"><p>بنر ذخیره شد.</p></div>';
    } elseif ($message === 'error') {
        echo '<div class="notice notice-error"><p>ذخیرهٔ بنر ناموفق بود. تصویر و نشانی مقصد را بررسی کنید.</p></div>';
    }
    echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('popcorn_ad');
    echo '<input type="hidden" name="action" value="popcorn_save_ad"><input type="hidden" name="ad_id" value="' . absint($editing->id ?? 0) . '"><table class="form-table"><tr><th><label for="ad-title">عنوان</label></th><td><input id="ad-title" name="title" type="text" class="regular-text" value="' . esc_attr($editing->title ?? '') . '" required></td></tr><tr><th><label for="ad-image">تصویر بنر</label></th><td>';
    if ($editing) {
        echo wp_get_attachment_image($editing->image_id, [240, 120], false, ['style' => 'max-width:240px;height:auto']) . '<p class="description">برای حفظ تصویر فعلی، فایل تازه انتخاب نکنید.</p>';
    }
    echo '<input id="ad-image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"' . ($editing ? '' : ' required') . '></td></tr><tr><th><label for="ad-place">جایگاه</label></th><td><select id="ad-place" name="placement">';
    foreach ($placements as $key => $position) {
        echo '<option value="' . esc_attr($key) . '"' . selected($editing->placement ?? 'home', $key, false) . '>' . esc_html($position['label']) . '</option>';
    }
    echo '</select></td></tr><tr><th><label for="ad-url">لینک مقصد</label></th><td><input id="ad-url" name="target_url" type="url" class="regular-text" placeholder="https://" value="' . esc_attr($editing->target_url ?? '') . '"></td></tr><tr><th><label for="ad-alt">متن جایگزین</label></th><td><input id="ad-alt" name="alt_text" type="text" class="regular-text" value="' . esc_attr($editing->alt_text ?? '') . '" required></td></tr></table>';
    echo '<details class="pc-ad-guide"><summary><span class="pc-ad-guide__icon">' . (function_exists('popcorn_icon') ? popcorn_icon('info') : '') . '</span><span class="pc-ad-guide__heading"><strong>راهنمای جایگاه‌ها</strong><small>محل نمایش بنر را در طرح هر صفحه ببینید</small></span><span class="pc-ad-guide__count">' . count($placements) . ' جایگاه</span>' . (function_exists('popcorn_icon') ? popcorn_icon('down', 'pc-ad-guide__chevron') : '') . '</summary><div class="pc-ad-guide__grid">';
    foreach ($placements as $key => $position) {
        echo '<div class="pc-ad-guide__item" data-placement="' . esc_attr($key) . '"><span class="pc-ad-guide__map pc-ad-guide__map--' . esc_attr($key) . '" aria-hidden="true"><i class="pc-ad-guide__map-header"></i><i class="pc-ad-guide__map-hero"></i><i class="pc-ad-guide__map-lines"></i><i class="pc-ad-guide__map-footer"></i><i class="pc-ad-guide__map-banner"></i></span><div class="pc-ad-guide__copy"><strong>' . esc_html($position['label']) . '</strong><span class="pc-ad-guide__selected">انتخاب فعلی</span><p>' . esc_html($position['description']) . '</p></div></div>';
    }
    echo '</div><p class="pc-ad-guide__note">بخش رنگی، محل نمایش تبلیغ است. جایگاه‌های بدون بنر، فضای خالی ایجاد نمی‌کنند.</p></details><p><button class="button button-primary">' . ($editing ? 'ذخیرهٔ تغییرات' : 'افزودن بنر') . '</button>';
    if ($editing) {
        echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=popcorn-settings&tab=ads')) . '">انصراف</a>';
    }
    echo '</p></form></div>';
    echo '<div class="pc-admin__card"><h2>بنرهای ثبت‌شده</h2><table class="widefat striped pc-admin__table"><thead><tr><th>تصویر</th><th>عنوان</th><th>جایگاه</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>';
    foreach ($ads as $ad) {
        $toggle = wp_nonce_url(admin_url('admin-post.php?action=popcorn_toggle_ad&id=' . absint($ad->id)), 'popcorn_toggle_ad_' . $ad->id);
        $edit_url = admin_url('admin.php?page=popcorn-settings&tab=ads&ad_id=' . absint($ad->id));
        echo '<tr><td>' . wp_get_attachment_image($ad->image_id, [120, 60], false, ['style' => 'max-width:120px;height:auto']) . '</td><td>' . esc_html($ad->title) . '</td><td>' . esc_html($placements[$ad->placement]['label'] ?? $ad->placement) . '</td><td>' . ($ad->is_active ? 'فعال' : 'غیرفعال') . '</td><td><a class="button" href="' . esc_url($edit_url) . '">ویرایش</a> <a class="button" href="' . esc_url($toggle) . '">' . ($ad->is_active ? 'غیرفعال کردن' : 'فعال کردن') . '</a></td></tr>';
    }
    if (!$ads) {
        echo '<tr><td colspan="5">هنوز بنری ثبت نشده است.</td></tr>';
    }
    echo '</tbody></table></div>';
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_popcorn-settings' || sanitize_key(wp_unslash($_GET['tab'] ?? '')) !== 'ads') {
        return;
    }
    $file = __DIR__ . '/assets/css/ad-settings.css';
    wp_enqueue_style('popcorn-ad-settings', plugin_dir_url(__FILE__) . 'assets/css/ad-settings.css', ['popcorn-admin'], filemtime($file));
});

add_action('admin_post_popcorn_save_ad', function () {
    current_user_can('manage_options') || wp_die('دسترسی مجاز نیست.');
    check_admin_referer('popcorn_ad');
    $placement = isset($_POST['placement']) ? sanitize_key(wp_unslash($_POST['placement'])) : '';
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $alt = isset($_POST['alt_text']) ? sanitize_text_field(wp_unslash($_POST['alt_text'])) : '';
    $url = isset($_POST['target_url']) ? esc_url_raw(wp_unslash($_POST['target_url']), ['http', 'https']) : '';
    global $wpdb;
    $table = popcorn_core_table('ad');
    $ad_id = isset($_POST['ad_id']) ? absint($_POST['ad_id']) : 0;
    $existing = $ad_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $ad_id)) : null;
    if (!isset(popcorn_core_ad_placements()[$placement]) || !$title || !$alt || ($ad_id && !$existing) || (!$existing && empty($_FILES['image']['name']))) {
        wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=ads&popcorn_message=error'));
        exit;
    }
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $image_id = $existing ? (int) $existing->image_id : 0;
    if (!empty($_FILES['image']['name'])) {
        $image_id = media_handle_upload('image', 0);
    }
    if (is_wp_error($image_id) || !wp_attachment_is_image($image_id)) {
        wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=ads&popcorn_message=error'));
        exit;
    }
    update_post_meta($image_id, '_wp_attachment_image_alt', $alt);
    popcorn_core_prepare_webp($image_id);
    $data = ['title' => $title, 'placement' => $placement, 'image_id' => $image_id, 'target_url' => $url, 'alt_text' => $alt];
    $ok = $existing ? $wpdb->update($table, $data, ['id' => $ad_id]) !== false : $wpdb->insert($table, $data + ['is_active' => 1, 'created_at' => current_time('mysql')]);
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=ads&popcorn_message=' . ($ok ? 'saved' : 'error')));
    exit;
});

add_action('admin_post_popcorn_toggle_ad', function () {
    current_user_can('manage_options') || wp_die('دسترسی مجاز نیست.');
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    check_admin_referer('popcorn_toggle_ad_' . $id);
    global $wpdb;
    $table = popcorn_core_table('ad');
    $active = $wpdb->get_var($wpdb->prepare("SELECT is_active FROM $table WHERE id = %d", $id));
    if ($active !== null) {
        $wpdb->update($table, ['is_active' => $active ? 0 : 1], ['id' => $id]);
    }
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=ads&popcorn_message=saved'));
    exit;
});
