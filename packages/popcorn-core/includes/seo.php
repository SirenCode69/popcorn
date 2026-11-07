<?php
defined('ABSPATH') || exit;

function popcorn_core_seo_context() {
    if (!is_singular(['popcorn_movie', 'popcorn_series', 'popcorn_person'])) {
        return null;
    }
    $post_id = get_queried_object_id();
    static $cache = [];
    if (array_key_exists($post_id, $cache)) {
        return $cache[$post_id];
    }
    $post_type = get_post_type($post_id);
    $type = substr($post_type, strlen('popcorn_'));
    $item = popcorn_core_get_by_post($type, $post_id);
    return $cache[$post_id] = $item ? ['post_id' => $post_id, 'type' => $type, 'item' => $item] : null;
}

function popcorn_core_seo_description($context) {
    $item = $context['item'];
    $post = get_post($context['post_id']);
    if ($post && trim($post->post_excerpt) !== '') {
        $source = $post->post_excerpt;
    } elseif ($context['type'] === 'person') {
        $source = $item->biography_fa ?: ($item->biography ?: $post->post_content);
    } else {
        $source = function_exists('popcorn_display_overview') ? popcorn_display_overview($item) : ($item->overview_en ?: $item->overview);
    }
    $source = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $source)));
    return wp_html_excerpt($source, 300, '…');
}

function popcorn_core_seo_entity() {
    $context = popcorn_core_seo_context();
    if (!$context) {
        return null;
    }
    $item = $context['item'];
    $type = $context['type'];
    $post_id = $context['post_id'];
    $url = get_permalink($post_id);
    $name = $type === 'person' ? ($item->persian_name ?: $item->name) : popcorn_display_title($item);
    $entity = ['@type' => ['movie' => 'Movie', 'series' => 'TVSeries', 'person' => 'Person'][$type], '@id' => $url . '#popcorn-content', 'url' => $url, 'name' => $name];
    $description = popcorn_core_seo_description($context);
    if ($description !== '') {
        $entity['description'] = $description;
    }
    $image_id = $type === 'person' ? absint($item->profile_id) : absint($item->poster_id);
    $image_path = $type === 'person' ? $item->profile_path : $item->poster_path;
    $image = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';
    if (!$image && function_exists('popcorn_external_image_url')) {
        $image = popcorn_external_image_url($image_path);
    }
    if ($image) {
        $entity['image'] = $image;
    }
    if ($type === 'person') {
        if ($item->name && $item->name !== $name) {
            $entity['alternateName'] = $item->name;
        }
        if (!empty($item->birth_date) && $item->birth_date !== '0000-00-00') {
            $entity['birthDate'] = $item->birth_date;
        }
    } else {
        if ($item->title && $item->title !== $name) {
            $entity['alternateName'] = $item->title;
        }
        if (!empty($item->release_date) && $item->release_date !== '0000-00-00') {
            $entity['datePublished'] = $item->release_date;
        }
        if (!empty($item->runtime)) {
            $entity['duration'] = 'PT' . absint($item->runtime) . 'M';
        }
        $genres = wp_get_post_terms($post_id, 'popcorn_genre', ['fields' => 'names']);
        if (!is_wp_error($genres) && $genres) {
            $entity['genre'] = $genres;
        }
        $votes = popcorn_core_rating_vote_count($post_id);
        if ($votes > 0 && (float) $item->rating > 0) {
            $entity['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round((float) $item->rating, 1), 'bestRating' => 10, 'ratingCount' => $votes];
        }
        $same_as = [];
        if ($item->tmdb_id) {
            $same_as[] = 'https://www.themoviedb.org/' . ($type === 'series' ? 'tv/' : 'movie/') . absint($item->tmdb_id);
        }
        if (preg_match('/^tt\d+$/', (string) $item->imdb_id)) {
            $same_as[] = 'https://www.imdb.com/title/' . $item->imdb_id . '/';
        }
        if ($same_as) {
            $entity['sameAs'] = $same_as;
        }
    }
    return $entity;
}

function popcorn_core_seo_graph_types($node) {
    return array_map('strval', (array) ($node['@type'] ?? []));
}

function popcorn_core_seo_merge_graph($graph, $entity) {
    if (!is_array($graph) || !$entity) {
        return $graph;
    }
    $is_list = !$graph || array_keys($graph) === range(0, count($graph) - 1);
    $target = $entity['@type'];
    foreach ($graph as $node) {
        if (is_array($node) && in_array($target, popcorn_core_seo_graph_types($node), true) && ($target !== 'Person' || ($node['@id'] ?? '') === $entity['@id'] || ($node['url'] ?? '') === $entity['url'])) {
            return $graph;
        }
    }
    foreach ($graph as $key => $node) {
        if (!is_array($node)) {
            continue;
        }
        $types = popcorn_core_seo_graph_types($node);
        if (array_intersect($types, ['Article', 'BlogPosting', 'NewsArticle'])) {
            unset($graph[$key]);
        } elseif (in_array('WebPage', $types, true)) {
            $graph[$key]['mainEntity'] = ['@id' => $entity['@id']];
        }
    }
    if ($is_list) {
        $graph[] = $entity;
        return array_values($graph);
    }
    $graph['PopcornContent'] = $entity;
    return $graph;
}

add_filter('wpseo_metadesc', function ($description) {
    $context = popcorn_core_seo_context();
    return $context && !get_post_meta($context['post_id'], '_yoast_wpseo_metadesc', true) ? popcorn_core_seo_description($context) ?: $description : $description;
});

add_filter('rank_math/frontend/description', function ($description) {
    $context = popcorn_core_seo_context();
    return $context && !get_post_meta($context['post_id'], 'rank_math_description', true) ? popcorn_core_seo_description($context) ?: $description : $description;
});

add_filter('wpseo_schema_graph', function ($graph) {
    return popcorn_core_seo_merge_graph($graph, popcorn_core_seo_entity());
}, 20);

add_filter('rank_math/json_ld', function ($graph) {
    return popcorn_core_seo_merge_graph($graph, popcorn_core_seo_entity());
}, 20);

add_action('wp_head', function () {
    if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || function_exists('rank_math')) {
        return;
    }
    $entity = popcorn_core_seo_entity();
    if (!$entity) {
        return;
    }
    $description = $entity['description'] ?? '';
    if ($description) {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }
    echo '<script type="application/ld+json">' . wp_json_encode(['@context' => 'https://schema.org', '@graph' => [$entity]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 5);
