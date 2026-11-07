<?php
defined('ABSPATH') || exit;

function popcorn_core_search_options() {
    $defaults = ['movie' => 1, 'series' => 1, 'person' => 1, 'genre' => 0, 'include_cast' => 0, 'title_boost' => 5, 'min_word_length' => 3, 'live_enabled' => 1, 'live_limit' => 7, 'live_min_chars' => 3, 'live_delay' => 300];
    $saved = get_option('popcorn_search_options', []);
    return array_merge($defaults, is_array($saved) ? $saved : [], ['genre' => 0, 'include_cast' => 0]);
}

function popcorn_core_search_types() {
    $options = popcorn_core_search_options();
    $types = [];
    foreach (['movie', 'series', 'person'] as $type) {
        if (!empty($options[$type])) {
            $types[] = 'popcorn_' . $type;
        }
    }
    return $types;
}

function popcorn_core_relevanssi_ready() {
    if (!function_exists('relevanssi_search') || get_option('relevanssi_indexed') !== 'done') {
        return false;
    }
    $types = array_merge(['post', 'page'], popcorn_core_search_types());
    $indexed = (array) get_option('relevanssi_index_post_types', []);
    return $types && !array_diff($types, $indexed);
}

function popcorn_core_live_search_ready() {
    return popcorn_core_relevanssi_ready() && function_exists('relevanssi_live_search_init') && !empty(popcorn_core_search_options()['live_enabled']);
}

function popcorn_core_search_page_types() {
    $types = ['' => 'همهٔ نتایج'];
    foreach (['movie' => 'فیلم‌ها', 'series' => 'سریال‌ها', 'person' => 'هنرمندان'] as $type => $label) {
        if (in_array('popcorn_' . $type, popcorn_core_search_types(), true)) {
            $types['popcorn_' . $type] = $label;
        }
    }
    return $types + ['post' => 'نوشته‌ها', 'page' => 'برگه‌ها'];
}

function popcorn_core_search_page_groups($term, $limit = 12) {
    $term = trim((string) $term);
    if ($term === '') {
        return [];
    }
    $groups = [];
    $types = popcorn_core_search_page_types();
    foreach (['post', 'popcorn_movie', 'popcorn_series', 'popcorn_person', 'page'] as $type) {
        if (!isset($types[$type])) {
            continue;
        }
        $query = new WP_Query([
            's' => $term,
            'post_type' => [$type],
            'post_status' => 'publish',
            'posts_per_page' => min(24, max(1, absint($limit))),
            'paged' => 1,
            'ignore_sticky_posts' => true,
            'popcorn_search_page' => true,
            'relevanssi' => popcorn_core_relevanssi_ready(),
        ]);
        if ($query->posts) {
            $groups[$type] = ['title' => $types[$type], 'posts' => $query->posts, 'count' => (int) $query->found_posts];
        }
    }
    return $groups;
}

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_search() || $query->is_feed()) {
        return;
    }
    $types = popcorn_core_search_page_types();
    $selected = isset($_GET['search_type']) && is_string($_GET['search_type']) ? sanitize_key(wp_unslash($_GET['search_type'])) : '';
    $selected = isset($types[$selected]) ? $selected : '';
    $query->set('popcorn_search_page', true);
    $query->set('popcorn_search_type', $selected);
    $query->set('post_type', $selected ? [$selected] : array_values(array_filter(array_keys($types))));
    $query->set('post_status', 'publish');
    $query->set('posts_per_page', 24);
    $query->set('ignore_sticky_posts', true);
});

add_filter('relevanssi_search_ok', function ($enabled, $query) {
    return popcorn_core_is_scoped_search($query) ? popcorn_core_relevanssi_ready() : $enabled;
}, 10, 2);

add_filter('relevanssi_prevent_default_request', function ($enabled, $query) {
    return popcorn_core_is_scoped_search($query) ? popcorn_core_relevanssi_ready() : $enabled;
}, 10, 2);

// The same field whitelist governs native search, live search and existing indexes.
function popcorn_core_search_fields($type) {
    return $type === 'person' ? ['persian_name'] : ['english_title', 'original_title'];
}

function popcorn_core_search_scope_sql($term, $types, $post_alias) {
    global $wpdb;
    $terms = preg_split('/\s+/u', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY);
    if (!$terms) {
        return '1=0';
    }
    $matches = [];
    foreach ((array) $types as $post_type) {
        $type = substr($post_type, 8);
        $core = in_array($post_type, ['popcorn_movie', 'popcorn_series', 'popcorn_person'], true);
        $conditions = [];
        foreach ($terms as $word) {
            $like = '%' . $wpdb->esc_like($word) . '%';
            $fields = [$wpdb->prepare("$post_alias.post_title LIKE %s", $like)];
            if ($core) {
                foreach (popcorn_core_search_fields($type) as $field) {
                    $fields[] = $wpdb->prepare("i.$field LIKE %s", $like);
                }
            } elseif ($post_type === 'post') {
                $fields[] = $wpdb->prepare("$post_alias.post_excerpt LIKE %s", $like);
            } elseif ($post_type === 'page') {
                $fields[] = $wpdb->prepare("$post_alias.post_excerpt LIKE %s", $like);
                $fields[] = $wpdb->prepare("$post_alias.post_content LIKE %s", $like);
            } else {
                continue 2;
            }
            $conditions[] = '(' . implode(' OR ', $fields) . ')';
        }
        $clause = implode(' AND ', $conditions);
        if ($core) {
            $table = popcorn_core_table($type);
            $role = $type === 'person' ? " AND i.profession IN ('Acting','Directing')" : '';
            $clause = "EXISTS (SELECT 1 FROM $table i WHERE i.post_id=$post_alias.ID$role AND $clause)";
        }
        $matches[] = '(' . $wpdb->prepare("$post_alias.post_type=%s", $post_type) . " AND $clause)";
    }
    return $matches ? '(' . implode(' OR ', $matches) . ')' : '1=0';
}

function popcorn_core_is_scoped_search($query) {
    return $query->get('popcorn_search_page') || $query->get('popcorn_search_live');
}

add_filter('posts_search', function ($search, $query) {
    if (!popcorn_core_is_scoped_search($query) || popcorn_core_relevanssi_ready()) {
        return $search;
    }
    global $wpdb;
    $clause = ' AND ' . popcorn_core_search_scope_sql($query->get('s'), $query->get('post_type'), $wpdb->posts);
    if (!is_user_logged_in()) {
        $clause .= " AND {$wpdb->posts}.post_password='' ";
    }
    return $clause;
}, 10, 2);

// Filter before Relevanssi counts and paginates, including stale index entries.
add_filter('relevanssi_hits_filter', function ($data, $query) {
    if (!popcorn_core_is_scoped_search($query) || !$data[0]) {
        return $data;
    }
    global $wpdb;
    $ids = array_map(static function ($hit) { return is_object($hit) ? (int) $hit->ID : (int) $hit; }, $data[0]);
    $allowed = [];
    $scope = popcorn_core_search_scope_sql($query->get('s'), $query->get('post_type'), 'p');
    foreach (array_chunk($ids, 500) as $batch) {
        $placeholders = implode(',', array_fill(0, count($batch), '%d'));
        $id_list = $wpdb->prepare($placeholders, $batch);
        $valid = $wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p WHERE p.ID IN ($id_list) AND $scope");
        foreach ($valid as $id) { $allowed[(int) $id] = true; }
    }
    $data[0] = array_values(array_filter($data[0], static function ($hit) use ($allowed) {
        return isset($allowed[is_object($hit) ? (int) $hit->ID : (int) $hit]);
    }));
    return $data;
}, 20, 2);

function popcorn_core_search_results($term) {
    $results = ['news' => [], 'movie' => [], 'series' => [], 'person' => [], 'pages' => []];
    if (trim((string) $term) === '') {
        return $results;
    }
    foreach (['post' => 'news', 'popcorn_movie' => 'movie', 'popcorn_series' => 'series', 'popcorn_person' => 'person', 'page' => 'pages'] as $post_type => $key) {
        if (!isset(popcorn_core_search_page_types()[$post_type])) { continue; }
        $query = new WP_Query(['s' => $term, 'post_type' => [$post_type], 'post_status' => 'publish', 'posts_per_page' => 24, 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'popcorn_search_live' => true, 'relevanssi' => popcorn_core_relevanssi_ready()]);
        if (in_array($key, ['news', 'pages'], true)) {
            $results[$key] = $query->posts;
        } else {
            foreach ($query->posts as $post) {
                $item = popcorn_core_get_by_post($key, $post->ID);
                if (!$item) { continue; }
                if ($key !== 'person') { $item->popcorn_type = $key; }
                $results[$key][] = $item;
            }
        }
    }
    return $results;
}

function popcorn_core_live_search_groups($posts, $limit) {
    $groups = ['post' => [], 'popcorn_movie' => [], 'popcorn_series' => [], 'popcorn_person' => [], 'page' => []];
    $ranked = [];
    foreach ($posts as $post) {
        if (!($post instanceof WP_Post) || $post->post_status !== 'publish' || !isset($groups[$post->post_type])) {
            continue;
        }
        $groups[$post->post_type][] = $post->ID;
        $ranked[] = $post->ID;
    }
    $limit = max(1, (int) $limit);
    $selected = [];
    foreach ($groups as $ids) {
        if ($ids && count($selected) < $limit) {
            $selected[$ids[0]] = true;
        }
    }
    foreach ($ranked as $id) {
        if (count($selected) >= $limit) {
            break;
        }
        $selected[$id] = true;
    }
    foreach ($groups as $type => $ids) {
        $groups[$type] = array_values(array_filter($ids, static function ($id) use ($selected) {
            return isset($selected[$id]);
        }));
    }
    return $groups;
}

function popcorn_core_search_indexed_types() {
    return ['post', 'popcorn_movie', 'popcorn_series', 'popcorn_person'];
}

function popcorn_core_search_index_content($content, $post) {
    if (!in_array($post->post_type, popcorn_core_search_indexed_types(), true)) {
        return $content;
    }
    if ($post->post_type === 'post') {
        return (string) $post->post_excerpt;
    }
    $type = substr($post->post_type, 8);
    $item = popcorn_core_get_by_post($type, $post->ID);
    $values = [];
    foreach (popcorn_core_search_fields($type) as $field) {
        if ($item && !empty($item->$field)) { $values[] = $item->$field; }
    }
    return implode(' ', $values);
}
add_filter('relevanssi_content_to_index', 'popcorn_core_search_index_content', 100, 2);

// Override generated post bodies as well as extra content from other plugins.
add_filter('relevanssi_post_content_before_tokenize', function ($content, $post) {
    return in_array($post->post_type, popcorn_core_search_indexed_types(), true)
        ? popcorn_core_search_index_content('', $post) : $content;
}, 100, 2);

add_filter('relevanssi_indexing_data', function ($data, $post) {
    if (!in_array($post->post_type, popcorn_core_search_indexed_types(), true)) { return $data; }
    $contexts = $post->post_type === 'post' ? ['title', 'content', 'excerpt'] : ['title', 'content'];
    foreach ($data as $term => $counts) {
        $data[$term] = array_intersect_key($counts, array_flip($contexts));
        if (!array_sum($data[$term])) { unset($data[$term]); }
    }
    return $data;
}, 100, 2);

add_filter('relevanssi_do_not_index', function ($exclude, $post_id, $post) {
    if ($post->post_type !== 'popcorn_person') { return $exclude; }
    $item = popcorn_core_get_by_post('person', $post_id);
    return $exclude || !$item || !in_array($item->profession, ['Acting', 'Directing'], true);
}, 10, 3);

add_action('save_post', function ($post_id, $post) {
    if (in_array($post->post_type, ['popcorn_movie', 'popcorn_series', 'popcorn_person'], true) && $post->post_status === 'publish' && function_exists('relevanssi_insert_edit')) {
        relevanssi_insert_edit($post_id);
    }
}, 100, 2);

add_action('popcorn_core_item_saved', function ($post_id) {
    if (function_exists('relevanssi_insert_edit') && get_post_status($post_id) === 'publish') {
        relevanssi_insert_edit($post_id);
    }
});

add_filter('relevanssi_live_search_configs', function ($configs) {
    $options = popcorn_core_search_options();
    $configs['popcorn'] = $configs['default'];
    $configs['popcorn']['input']['min_chars'] = (int) $options['live_min_chars'];
    $configs['popcorn']['input']['delay'] = (int) $options['live_delay'];
    $configs['popcorn']['results']['width'] = 'css';
    return $configs;
});

add_filter('relevanssi_live_search_posts_per_page', function ($count) {
    return popcorn_core_live_search_ready() ? (int) popcorn_core_search_options()['live_limit'] : $count;
});

add_filter('relevanssi_live_search_query_args', function ($args) {
    if (!popcorn_core_live_search_ready()) {
        return $args;
    }
    $args['post_type'] = array_merge(['post', 'page'], popcorn_core_search_types());
    $args['post_status'] = 'publish';
    $args['popcorn_search_live'] = true;
    $args['posts_per_page'] = min(60, max(20, (int) popcorn_core_search_options()['live_limit'] * 6));
    return $args;
});

add_filter('relevanssi_live_search_hijack_get_search_form', '__return_false');
add_filter('relevanssi_live_search_base_styles', function ($enabled) {
    return popcorn_core_live_search_ready() && !is_404() && !is_singular('popcorn_playback') ? $enabled : false;
});

add_action('wp_enqueue_scripts', function () {
    if (popcorn_core_live_search_ready() && !is_404() && !is_singular('popcorn_playback')) {
        return;
    }
    global $wp_filter;
    foreach (($wp_filter['wp_enqueue_scripts']->callbacks[12] ?? []) as $callback) {
        $function = $callback['function'];
        if (is_array($function) && $function[0] instanceof Relevanssi_Live_Search_Form && $function[1] === 'assets') {
            remove_action('wp_enqueue_scripts', $function, 12);
        }
    }
}, 11);

add_action('wp_enqueue_scripts', function () {
    if (!popcorn_core_live_search_ready() || is_404() || is_singular('popcorn_playback')) {
        wp_dequeue_style('relevanssi-live-search');
        wp_dequeue_script('relevanssi-live-search-client');
        return;
    }
    $path = get_template_directory() . '/assets/css/live-search.css';
    wp_enqueue_style('popcorn-live-search', get_template_directory_uri() . '/assets/css/live-search.css', ['popcorn-base', 'relevanssi-live-search'], filemtime($path));
}, 20);

function popcorn_core_search_index_taxonomies($existing, $include_genre) {
    $taxonomies = array_values(array_diff($existing, ['popcorn_genre']));
    foreach (['category' => 'popcorn_categury', 'post_tag' => 'popcorn_tag'] as $source => $target) {
        if (in_array($source, $existing, true)) { $taxonomies[] = $target; }
    }
    if ($include_genre) { $taxonomies[] = 'popcorn_genre'; }
    return array_values(array_unique($taxonomies));
}

function popcorn_core_search_apply_relevanssi_options($old, $new) {
    if (!function_exists('relevanssi_search')) {
        return;
    }
    $existing = (array) get_option('relevanssi_index_post_types', []);
    $other = array_values(array_diff($existing, ['popcorn_movie', 'popcorn_series', 'popcorn_person']));
    $types = array_values(array_unique(array_merge($other, ['post', 'page'], popcorn_core_search_types())));
    $existing_taxonomies = (array) get_option('relevanssi_index_taxonomies_list', []);
    $taxonomies = popcorn_core_search_index_taxonomies($existing_taxonomies, $new['genre']);
    $existing_min_word_length = (int) get_option('relevanssi_min_word_length', 3);
    update_option('relevanssi_index_post_types', $types);
    update_option('relevanssi_index_taxonomies_list', $taxonomies, false);
    update_option('relevanssi_title_boost', (string) $new['title_boost']);
    update_option('relevanssi_min_word_length', (int) $new['min_word_length']);
    if (get_option('popcorn_search_relevanssi_synced') !== md5('title-scope-v2:' . wp_json_encode($old)) || array_intersect_key($old, array_flip(['movie', 'series', 'person', 'genre', 'include_cast', 'min_word_length'])) !== array_intersect_key($new, array_flip(['movie', 'series', 'person', 'genre', 'include_cast', 'min_word_length'])) || $existing !== $types || $existing_taxonomies !== $taxonomies || $existing_min_word_length !== (int) $new['min_word_length']) {
        update_option('relevanssi_indexed', '');
    }
    update_option('popcorn_search_relevanssi_synced', md5('title-scope-v2:' . wp_json_encode($new)), false);
}

add_action('init', function () {
    if (!function_exists('relevanssi_search')) {
        return;
    }
    $options = popcorn_core_search_options();
    $required = array_merge(['post', 'page'], popcorn_core_search_types());
    $existing_taxonomies = (array) get_option('relevanssi_index_taxonomies_list', []);
    if (get_option('popcorn_search_relevanssi_synced') !== md5('title-scope-v2:' . wp_json_encode($options)) || array_diff($required, (array) get_option('relevanssi_index_post_types', [])) || $existing_taxonomies !== popcorn_core_search_index_taxonomies($existing_taxonomies, $options['genre'])) {
        popcorn_core_search_apply_relevanssi_options($options, $options);
    }
}, 20);

function popcorn_core_search_settings_tab() {
    if (!current_user_can('manage_options')) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $engine_active = is_plugin_active('relevanssi/relevanssi.php');
    $live_active = is_plugin_active('relevanssi-live-ajax-search/relevanssi-live-ajax-search.php');
    $options = popcorn_core_search_options();
    $message = isset($_GET['popcorn_message']) ? sanitize_key(wp_unslash($_GET['popcorn_message'])) : '';
    if ($message === 'saved') {
        echo '<div class="notice notice-success"><p>تنظیمات جست‌وجو ذخیره شد.</p></div>';
    } elseif ($message === 'choose_type') {
        echo '<div class="notice notice-error"><p>دست‌کم یک نوع محتوا را برای جست‌وجو انتخاب کنید.</p></div>';
    }
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pc-search-settings"><input type="hidden" name="action" value="popcorn_save_search_options">';
    wp_nonce_field('popcorn_search_options');
    echo '<div class="pc-search-settings__column"><div class="pc-admin__card"><h2>وضعیت موتور جست‌وجو</h2><p>جست‌وجوی داخلی پاپکورن همیشه فعال می‌ماند. Relevanssi پس از فعال‌سازی، تنظیم و ساخت ایندکس جایگزین آن می‌شود.</p>';
    foreach ([['Relevanssi', $engine_active, 'relevanssi/relevanssi.php', 'relevanssi'], ['Relevanssi Live Ajax Search', $live_active, 'relevanssi-live-ajax-search/relevanssi-live-ajax-search.php', 'relevanssi-live-ajax-search']] as [$label, $active, $file, $slug]) {
        $installed = file_exists(WP_PLUGIN_DIR . '/' . $file);
        $url = $installed ? admin_url('plugins.php') : add_query_arg(['s' => $slug, 'tab' => 'search', 'type' => 'term'], admin_url('plugin-install.php'));
        echo '<p><strong>' . esc_html($label) . ':</strong> ' . ($active ? 'فعال' : ($installed ? 'نصب‌شده ولی غیرفعال' : 'نصب‌نشده'));
        if (!$active) {
            echo ' — <a href="' . esc_url($url) . '">' . ($installed ? 'فعال‌سازی افزونه' : 'نصب افزونه') . '</a> برای نتایج بهتر پیشنهاد می‌شود.';
        }
        echo '</p>';
    }
    if ($engine_active && !popcorn_core_relevanssi_ready()) {
        echo '<p>ایندکس جست‌وجو آماده نیست یا نوع محتوای انتخابی در آن نیست. پس از ذخیرهٔ تنظیم‌ها، ایندکس را در صفحهٔ Relevanssi بسازید.</p><p><a class="button" href="' . esc_url(admin_url('admin.php?page=relevanssi/relevanssi.php')) . '">مدیریت ایندکس Relevanssi</a></p>';
    }
    echo '</div>';
    echo '<div class="pc-admin__card"><h2>دامنهٔ جست‌وجو</h2><p>اخبار و برگه‌ها همیشه جست‌وجو می‌شوند. این گزینه‌ها در جست‌وجوی داخلی پاپکورن هم کار می‌کنند.</p><table class="form-table"><tbody>';
    foreach (['movie' => 'فیلم‌ها', 'series' => 'سریال‌ها', 'person' => 'هنرمندان'] as $key => $label) {
        echo '<tr><th><label for="popcorn-search-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input id="popcorn-search-' . esc_attr($key) . '" type="checkbox" name="popcorn_search_options[' . esc_attr($key) . ']" value="1"' . checked(!empty($options[$key]), true, false) . '></td></tr>';
    }
    echo '</tbody></table></div></div><div class="pc-search-settings__column">';
    echo '<div class="pc-admin__card' . ($engine_active ? '' : ' pc-admin__card--disabled') . '"><h2>تنظیمات Relevanssi</h2><p>' . ($engine_active ? 'پس از تغییر گزینه‌های ایندکس، ایندکس Relevanssi را دوباره بسازید.' : 'برای استفاده از این تنظیمات، افزونهٔ Relevanssi را نصب و فعال کنید.') . '</p><fieldset class="pc-admin__fieldset"' . ($engine_active ? '' : ' disabled') . '><table class="form-table"><tbody>';
    echo '<tr><th><label for="popcorn-search-title">وزن عنوان</label></th><td><input id="popcorn-search-title" type="number" min="1" max="100" name="popcorn_search_options[title_boost]" value="' . esc_attr($options['title_boost']) . '"></td></tr>';
    echo '<tr><th><label for="popcorn-search-word">حداقل طول واژهٔ ایندکس</label></th><td><input id="popcorn-search-word" type="number" min="2" max="5" name="popcorn_search_options[min_word_length]" value="' . esc_attr($options['min_word_length']) . '"></td></tr></tbody></table></fieldset><p class="pc-admin__search-help">در بخش <strong>Custom fields</strong> افزونه، برای داده‌های پاپکورن هیچ کلیدی وارد نکنید. نوشته‌ها فقط با عنوان و چکیده؛ فیلم و سریال فقط با عنوان اصلی، عنوان فارسی و عنوان انگلیسی؛ هنرمندان فقط با عنوان و نام فارسی جست‌وجو می‌شوند. فقط بازیگران و کارگردانان در نتایج هنرمندان هستند. خلاصهٔ آثار، زندگی‌نامه، شناسه‌ها، ژانرها و ارتباط هنرمندان با آثار جست‌وجو نمی‌شوند. اگر سایت فقط از پاپکورن استفاده می‌کند، گزینهٔ <strong>Do not index custom fields</strong> را انتخاب کنید؛ این گزینه روی سایر نوشته‌های سایت هم اثر دارد.</p></div>';
    $live_available = $engine_active && $live_active;
    echo '<div class="pc-admin__card' . ($live_available ? '' : ' pc-admin__card--disabled') . '"><h2>جست‌وجوی زنده</h2><p>' . ($live_available ? 'پیشنهادها پس از آماده شدن ایندکس نمایش داده می‌شوند.' : 'برای استفاده از این تنظیمات، هر دو افزونهٔ Relevanssi و Relevanssi Live Ajax Search باید فعال باشند.') . '</p><fieldset class="pc-admin__fieldset"' . ($live_available ? '' : ' disabled') . '><table class="form-table"><tbody><tr><th><label for="popcorn-search-live">نمایش پیشنهادها</label></th><td><input id="popcorn-search-live" type="checkbox" name="popcorn_search_options[live_enabled]" value="1"' . checked(!empty($options['live_enabled']), true, false) . '><p class="description">فقط وقتی هر دو افزونه فعال و ایندکس آماده باشد نمایش داده می‌شود.</p></td></tr>';
    foreach (['live_limit' => ['تعداد پیشنهادها', 3, 20], 'live_min_chars' => ['حداقل حروف شروع جست‌وجو', 2, 6], 'live_delay' => ['مکث درخواست (میلی‌ثانیه)', 150, 800]] as $key => [$label, $min, $max]) {
        echo '<tr><th><label for="popcorn-search-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input id="popcorn-search-' . esc_attr($key) . '" type="number" min="' . $min . '" max="' . $max . '" name="popcorn_search_options[' . esc_attr($key) . ']" value="' . esc_attr($options[$key]) . '"></td></tr>';
    }
    echo '</tbody></table></fieldset></div></div>';
    submit_button('ذخیره و اعمال تنظیمات');
    echo '</form>';
}
add_action('popcorn_settings_tab_search', 'popcorn_core_search_settings_tab');

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_popcorn-settings' || !isset($_GET['tab']) || sanitize_key(wp_unslash($_GET['tab'])) !== 'search') {
        return;
    }
    $base = dirname(__DIR__);
    wp_enqueue_style('popcorn-search-settings', plugin_dir_url($base . '/popcorn-core.php') . 'assets/css/search-settings.css', ['popcorn-admin'], filemtime($base . '/assets/css/search-settings.css'));
});

function popcorn_core_search_sanitize_options($input, $old, $engine_active, $live_active) {
    $new = array_merge($old, ['genre' => 0, 'include_cast' => 0]);
    foreach (['movie', 'series', 'person'] as $key) {
        $new[$key] = empty($input[$key]) ? 0 : 1;
    }
    if (!$new['movie'] && !$new['series'] && !$new['person']) {
        return new WP_Error('choose_type');
    }
    if ($engine_active) {
        foreach (['title_boost' => [1, 100], 'min_word_length' => [2, 5]] as $key => [$min, $max]) {
            $new[$key] = min($max, max($min, absint($input[$key] ?? $old[$key])));
        }
    }
    if ($engine_active && $live_active) {
        $new['live_enabled'] = empty($input['live_enabled']) ? 0 : 1;
        foreach (['live_limit' => [3, 20], 'live_min_chars' => [2, 6], 'live_delay' => [150, 800]] as $key => [$min, $max]) {
            $new[$key] = min($max, max($min, absint($input[$key] ?? $old[$key])));
        }
    }
    return $new;
}

add_action('admin_post_popcorn_save_search_options', function () {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی مجاز نیست.');
    }
    check_admin_referer('popcorn_search_options');
    $input = isset($_POST['popcorn_search_options']) && is_array($_POST['popcorn_search_options']) ? wp_unslash($_POST['popcorn_search_options']) : [];
    $old = popcorn_core_search_options();
    $new = popcorn_core_search_sanitize_options($input, $old, function_exists('relevanssi_search'), function_exists('relevanssi_live_search_init'));
    if (is_wp_error($new)) {
        wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=search&popcorn_message=choose_type'));
        exit;
    }
    update_option('popcorn_search_options', $new, false);
    popcorn_core_search_apply_relevanssi_options($old, $new);
    wp_safe_redirect(admin_url('admin.php?page=popcorn-settings&tab=search&popcorn_message=saved'));
    exit;
});
