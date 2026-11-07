<?php
get_template_part('template-parts/slider', null, [
    'items' => [],
    'card' => 'genre-card',
    'async_url' => rest_url('popcorn/v1/home-slider/genres/'),
    'id' => 'pc-home-genres',
    'section_class' => 'pc-genres',
    'class' => 'pc-genres__slider',
    'heading' => 'ژانرهای محبوب',
    'empty_message' => 'ژانرها به‌زودی اینجا نمایش داده می‌شوند.',
    'navigation' => true,
    'prev_label' => 'ژانرهای قبلی',
    'next_label' => 'ژانرهای بعدی',
]);
