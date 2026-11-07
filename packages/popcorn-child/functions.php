<?php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('popcorn-child', get_stylesheet_uri(), ['popcorn-base'], filemtime(get_stylesheet_directory() . '/style.css'));
}, 100);
