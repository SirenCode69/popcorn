<?php defined('ABSPATH') || exit; ?>
<main id="main" class="pc-auth-layout">
  <div class="pc-auth-side">
    <div class="pc-auth-top"><a class="pc-auth-brand" href="<?php echo esc_url(home_url('/')); ?>"><?php echo popcorn_auth_brand(); ?></a><?php if (function_exists('popcorn_theme_mode') && popcorn_theme_mode() === 'both') : ?><button class="pc-auth-icon-button" type="button" data-pc-theme-toggle aria-label="تغییر حالت نمایش"><?php echo popcorn_auth_icon('moon'); ?><span class="screen-reader-text">تغییر حالت نمایش</span></button><?php endif; ?></div>
    <section class="pc-auth" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('popcorn_auth_form')); ?>" data-intent="<?php echo $register ? 'register' : 'login'; ?>" data-redirect="<?php echo esc_url($redirect); ?>">
      <header class="pc-auth-heading"><span><?php echo esc_html(get_bloginfo('name')); ?></span><h1><?php echo $register ? 'ساخت حساب کاربری' : 'خوش آمدید'; ?></h1><p><?php echo $register ? 'حساب خود را بسازید و آثار موردعلاقه‌تان را دنبال کنید.' : 'برای ادامه، وارد حساب کاربری خود شوید.'; ?></p></header>
      <?php if (isset($_GET['password_changed']) && $_GET['password_changed'] === '1') : ?><p class="pc-auth__success" role="status">رمز عبور تغییر کرد. دوباره وارد شوید.</p><?php endif; ?>
      <?php if (!$register) : ?><div class="pc-auth-modes" role="group" aria-label="روش ورود"><button type="button" data-auth-mode="code" class="is-active" aria-pressed="true">کد یک‌بارمصرف</button><button type="button" data-auth-mode="password" aria-pressed="false">رمز عبور</button></div><?php endif; ?>
      <form data-auth-action="popcorn_auth_send_code" class="pc-auth-form">
        <?php if ($register) : ?>
          <label for="pc-register-name">نام نمایشی<input id="pc-register-name" name="display_name" type="text" autocomplete="name" maxlength="100" required placeholder="نامی که در سایت نمایش داده می‌شود"></label>
          <label for="pc-register-username">نام کاربری<input id="pc-register-username" name="username" type="text" dir="ltr" autocomplete="username" maxlength="60" required placeholder="username"></label>
        <?php endif; ?>
        <label for="pc-auth-identity"><?php echo esc_html($method); ?><span class="pc-auth-field"><input id="pc-auth-identity" name="identity" type="<?php echo $options['email_enabled'] && !$options['mobile_enabled'] ? 'email' : ($options['mobile_enabled'] && !$options['email_enabled'] ? 'tel' : 'text'); ?>" autocomplete="<?php echo $options['mobile_enabled'] && !$options['email_enabled'] ? 'tel' : 'email'; ?>" dir="ltr" required placeholder="<?php echo $options['email_enabled'] ? 'you@example.com' : '09xxxxxxxxx'; ?>"><?php echo popcorn_auth_icon($options['email_enabled'] ? 'mail' : 'user'); ?></span></label>
        <?php if ($register) : ?>
          <details class="pc-auth-optional-password"><summary>تعیین رمز عبور (اختیاری)</summary><p>بدون رمز هم می‌توانید با کد یک‌بارمصرف وارد شوید.</p><label for="pc-register-password">رمز عبور<span class="pc-auth-field"><input id="pc-register-password" name="new_password" type="password" dir="ltr" autocomplete="new-password" minlength="8"><button type="button" data-auth-eye="pc-register-password" aria-label="نمایش رمز عبور" aria-pressed="false"><?php echo popcorn_auth_icon('eye'); ?></button></span></label><label for="pc-register-confirm">تکرار رمز عبور<input id="pc-register-confirm" name="confirm_password" type="password" dir="ltr" autocomplete="new-password" minlength="8"></label><small>حداقل 8 نویسه، شامل حرف انگلیسی و عدد.</small></details>
        <?php endif; ?>
        <label class="pc-auth-check"><input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار</label>
        <button class="pc-auth-submit" type="submit"><?php echo $register ? 'دریافت کد و ادامهٔ ثبت‌نام' : 'دریافت کد ورود'; ?></button>
      </form>
      <form data-auth-action="popcorn_auth_verify_code" class="pc-auth-form" hidden>
        <p class="pc-auth-code-hint">کد ارسال‌شده به <bdi data-auth-recipient></bdi> را وارد کنید.</p>
        <label for="pc-auth-code">کد تأیید</label><input id="pc-auth-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" minlength="<?php echo (int) $options['otp_length']; ?>" maxlength="<?php echo (int) $options['otp_length']; ?>" pattern="[0-9۰-۹٠-٩]{<?php echo (int) $options['otp_length']; ?>}" dir="ltr" class="pc-auth-code" required>
        <button class="pc-auth-submit" type="submit"><?php echo $register ? 'تأیید و ساخت حساب' : 'تأیید و ورود'; ?></button>
        <div class="pc-auth-code-actions"><button type="button" data-auth-resend disabled>ارسال دوبارهٔ کد</button><button type="button" data-auth-back>ویرایش اطلاعات</button></div>
      </form>
      <?php if (!$register) : ?>
        <form data-auth-action="popcorn_auth_password" class="pc-auth-form" hidden>
          <label for="pc-auth-password-identity">ایمیل یا شماره موبایل<span class="pc-auth-field"><input id="pc-auth-password-identity" name="identity" type="text" dir="ltr" autocomplete="username" required><?php echo popcorn_auth_icon('user'); ?></span></label>
          <label for="pc-auth-password">رمز عبور<span class="pc-auth-field"><input id="pc-auth-password" name="password" type="password" dir="ltr" autocomplete="current-password" required><button type="button" data-auth-eye="pc-auth-password" aria-label="نمایش رمز عبور" aria-pressed="false"><?php echo popcorn_auth_icon('eye'); ?></button></span></label>
          <label class="pc-auth-check"><input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار</label>
          <button class="pc-auth-submit" type="submit">ورود به حساب</button><button class="pc-auth-text-button" type="button" data-auth-forgot>رمز عبور را فراموش کرده‌ام</button>
        </form>
      <?php endif; ?>
      <p class="pc-auth__status" role="status" aria-live="polite"></p>
      <footer class="pc-auth-footer"><?php if ($register) : ?>قبلاً حساب ساخته‌اید؟ <a href="<?php echo esc_url(popcorn_auth_login_url()); ?>">ورود به حساب</a><?php elseif (popcorn_auth_register_url()) : ?>حساب ندارید؟ <a href="<?php echo esc_url(popcorn_auth_register_url()); ?>">ثبت‌نام</a><?php endif; ?></footer>
      <noscript><p>برای ورود با کد، جاوااسکریپت مرورگر را فعال کنید.</p></noscript>
    </section>
    <a class="pc-auth-return" href="<?php echo esc_url(home_url('/')); ?>"><?php echo popcorn_auth_icon('home'); ?> بازگشت به سایت</a>
  </div>
  <aside class="pc-auth-graphic" aria-hidden="true">
    <?php if (!empty($options['graphic_id']) && wp_attachment_is_image($options['graphic_id'])) : ?><?php echo wp_get_attachment_image($options['graphic_id'], 'large', false, ['class' => 'pc-auth-graphic-image', 'alt' => '', 'fetchpriority' => 'low', 'loading' => 'lazy']); ?><?php else : ?><div class="pc-auth-graphic-art"><?php echo popcorn_auth_icon('film'); ?></div><?php endif; ?>
    <div class="pc-auth-graphic-content"><span><?php echo esc_html(get_bloginfo('name')); ?></span><h2>دنیای فیلم و سریال،<br>در حساب شما</h2><p>آثار دلخواهتان را ذخیره کنید، امتیاز بدهید و از خبرهای تازه باخبر شوید.</p></div>
  </aside>
</main>
