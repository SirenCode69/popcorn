<?php
defined('ABSPATH') || exit;

function popcorn_render_comment($comment, $args, $depth) {
    get_template_part('template-parts/comment', null, ['comment' => $comment, 'options' => $args, 'depth' => $depth]);
}

function popcorn_comment_form_options() {
    $options = [
        'class_form' => 'comment-form pc-comment-form',
        'class_submit' => 'pc-comments__submit',
        'title_reply' => 'دیدگاهتان را بنویسید',
        'title_reply_to' => 'پاسخ به %s',
        'cancel_reply_link' => 'لغو پاسخ',
        'label_submit' => 'ارسال دیدگاه',
        'logged_in_as' => '',
        'comment_field' => '<p class="comment-form-comment"><label for="comment">دیدگاه <span class="required" aria-hidden="true">*</span></label><textarea id="comment" name="comment" rows="5" maxlength="65525" required></textarea></p>',
    ];
    $options['must_log_in'] = '<p class="must-log-in">ارسال دیدگاه نیازمند حساب کاربری است و ورود در حال حاضر در دسترس نیست.</p>';
    $login = popcorn_public_auth_enabled() ? popcorn_auth_login_url(get_permalink()) : '';
    if ($login) {
        $options['must_log_in'] = '<p class="must-log-in">برای ارسال دیدگاه، <a href="' . esc_url($login) . '">وارد حساب کاربری شوید</a>.</p>';
    }
    return $options;
}

add_action('wp_enqueue_scripts', function () {
    if (!is_singular() || is_singular('popcorn_playback') || post_password_required() || (!comments_open() && !get_comments_number())) {
        return;
    }
    $path = get_template_directory() . '/assets/css/comments.css';
    wp_enqueue_style('popcorn-comments', get_template_directory_uri() . '/assets/css/comments.css', ['popcorn-base'], filemtime($path));
    if (comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}, 20);
