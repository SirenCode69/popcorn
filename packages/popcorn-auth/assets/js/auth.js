(() => {
  document.querySelectorAll('.pc-auth[data-ajax-url]').forEach(root => {
    const status = root.querySelector('.pc-auth__status');
    const sendForm = root.querySelector('[data-auth-action="popcorn_auth_send_code"]');
    const verifyForm = root.querySelector('[data-auth-action="popcorn_auth_verify_code"]');
    const passwordForm = root.querySelector('[data-auth-action="popcorn_auth_password"]');
    const identity = sendForm.querySelector('[name="identity"]');
    const resend = root.querySelector('[data-auth-resend]');
    const modeButtons = root.querySelectorAll('[data-auth-mode]');
    let busy = false;
    let resendAt = 0;
    let timer;
    const showStatus = (message, error = false) => {
      status.textContent = message;
      status.classList.toggle('is-error', error);
    };
    const countdown = () => {
      clearTimeout(timer);
      const seconds = Math.max(0, Math.ceil((resendAt - Date.now()) / 1000));
      resend.disabled = seconds > 0 || busy;
      resend.textContent = seconds > 0 ? `ارسال دوباره تا ${seconds} ثانیه` : 'ارسال دوبارهٔ کد';
      if (seconds > 0) timer = setTimeout(countdown, 1000);
    };
    const switchMode = mode => {
      sendForm.hidden = mode !== 'code';
      verifyForm.hidden = true;
      if (passwordForm) passwordForm.hidden = mode !== 'password';
      modeButtons.forEach(button => {
        const active = button.dataset.authMode === mode;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
      });
      if (passwordForm && mode === 'password') {
        const target = passwordForm.querySelector('[name="identity"]');
        if (!target.value) target.value = identity.value;
        target.focus();
      } else identity.focus();
      showStatus('');
    };
    const submit = async form => {
      if (busy || !form.reportValidity()) return;
      busy = true;
      root.setAttribute('aria-busy', 'true');
      root.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
      const body = new URLSearchParams(new FormData(form));
      body.set('action', form.dataset.authAction);
      body.set('nonce', root.dataset.nonce);
      body.set('intent', root.dataset.intent);
      if (form === verifyForm) {
        for (const [key, value] of new FormData(sendForm)) body.set(key, value);
        body.set('code', verifyForm.querySelector('[name="code"]').value);
      }
      showStatus('در حال بررسی…');
      try {
        const response = await fetch(root.dataset.ajaxUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body, cache:'no-store'});
        const result = await response.json();
        if (!result.success) {
          showStatus(result.data?.message || 'درخواست انجام نشد. دوباره تلاش کنید.', true);
          return;
        }
        if (form === sendForm) {
          sendForm.hidden = true;
          if (passwordForm) passwordForm.hidden = true;
          verifyForm.hidden = false;
          root.querySelector('[data-auth-recipient]').textContent = identity.value.trim();
          verifyForm.querySelector('[name="code"]').value = '';
          verifyForm.querySelector('[name="code"]').focus();
          resendAt = Date.now() + Number(result.data?.resend_after || 60) * 1000;
        } else {
          const target = new URL(root.dataset.redirect || result.data?.redirect || '/', location.href);
          location.assign(target.origin === location.origin ? target.href : '/');
          return;
        }
        showStatus(result.data?.message || 'کد تأیید ارسال شد.');
      } catch {
        showStatus('ارتباط برقرار نشد. دوباره تلاش کنید.', true);
      } finally {
        busy = false;
        root.removeAttribute('aria-busy');
        root.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; });
        countdown();
      }
    };
    root.querySelectorAll('form[data-auth-action]').forEach(form => {
      form.addEventListener('submit', event => { event.preventDefault(); submit(form); });
    });
    root.querySelector('[data-auth-back]').addEventListener('click', () => switchMode('code'));
    resend.addEventListener('click', () => { if (Date.now() >= resendAt) submit(sendForm); });
    modeButtons.forEach(button => button.addEventListener('click', () => switchMode(button.dataset.authMode)));
    root.querySelector('[data-auth-forgot]')?.addEventListener('click', () => {
      const value = passwordForm.querySelector('[name="identity"]').value;
      if (value) identity.value = value;
      switchMode('code');
      showStatus('با کد یک‌بارمصرف وارد شوید و در حساب کاربری رمز تازه تعیین کنید.');
    });
  });
  document.querySelectorAll('[data-auth-eye]').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.authEye);
      if (!input) return;
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(visible));
      button.setAttribute('aria-label', visible ? 'پنهان کردن رمز عبور' : 'نمایش رمز عبور');
    });
  });
  const shell = document.querySelector('[data-account-shell]');
  if (!shell) return;
  const toggle = shell.querySelector('[data-account-toggle]');
  const dismiss = shell.querySelector('[data-account-dismiss]');
  const sidebar = shell.querySelector('.pc-account-sidebar');
  const main = shell.querySelector('.pc-account-main');
  let oldOverflow = '';
  const close = (focus = true) => {
    if (!shell.classList.contains('is-menu-open')) return;
    shell.classList.remove('is-menu-open');
    dismiss.hidden = true;
    main.inert = false;
    toggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = oldOverflow;
    if (focus) toggle.focus();
  };
  toggle.addEventListener('click', () => {
    if (shell.classList.contains('is-menu-open')) { close(); return; }
    oldOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    shell.classList.add('is-menu-open');
    dismiss.hidden = false;
    main.inert = true;
    toggle.setAttribute('aria-expanded', 'true');
    sidebar.querySelector('a').focus();
  });
  dismiss.addEventListener('click', () => close());
  document.addEventListener('keydown', event => {
    if (!shell.classList.contains('is-menu-open')) return;
    if (event.key === 'Escape') { close(); return; }
    if (event.key !== 'Tab') return;
    const links = Array.from(sidebar.querySelectorAll('a[href],button:not([disabled])'));
    const first = links[0], last = links[links.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  window.matchMedia('(min-width: 900px)').addEventListener('change', event => { if (event.matches) close(false); });
})();
