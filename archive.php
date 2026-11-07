<?php
defined('ABSPATH') || exit;
if (!function_exists('popcorn_core_is_catalog_archive') || !popcorn_core_is_catalog_archive()) {
    get_template_part('blog-archive');
    return;
}
get_template_part('popcorn-archive');
