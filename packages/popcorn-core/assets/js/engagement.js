(() => {
  const format = new Intl.NumberFormat('en-US', { numberingSystem: 'latn' });
  const decimal = new Intl.NumberFormat('en-US', { numberingSystem: 'latn', minimumFractionDigits: 1, maximumFractionDigits: 1 });

  const update = (root, data) => {
    root.dataset.guestNonce = data.guest_nonce || '';
    root.dataset.guestToken = data.guest_token || '';
    const average = root.querySelector('[data-rating-average]');
    const votes = root.querySelector('[data-rating-count]');
    const favorites = root.querySelector('[data-favorite-count]');
    const mine = root.querySelector('[data-user-rating]');
    const clear = root.querySelector('[data-clear-rating]');
    const favorite = root.querySelector('[data-favorite]');
    const rating = Number(data.user_rating) || 0;
    if (average) average.textContent = decimal.format(Number(data.rating_average) || 0);
    if (votes) votes.textContent = `‌(${format.format(Number(data.rating_count) || 0)} رأی)`;
    if (favorites) favorites.textContent = format.format(Number(data.favorite_count) || 0);
    document.querySelectorAll('[data-site-rating-post]').forEach(score => {
      if (score.dataset.siteRatingPost !== root.dataset.postId) return;
      const value = score.querySelector('[data-site-rating-value]');
      if (value) value.textContent = Number(data.site_rating) > 0 ? decimal.format(Number(data.site_rating)) : '—';
    });
    root.querySelectorAll('[data-rating]').forEach(button => {
      button.setAttribute('aria-pressed', String(Number(button.dataset.rating) === rating));
      button.dataset.filled = String(Number(button.dataset.rating) <= rating);
    });
    if (mine) mine.textContent = rating ? `امتیاز شما: ${format.format(rating)} از 10` : 'هنوز امتیازی ثبت نکرده‌اید.';
    if (clear) clear.hidden = !rating;
    if (favorite) {
      const selected = Boolean(data.favorited);
      favorite.setAttribute('aria-pressed', String(selected));
      favorite.querySelector('span').textContent = selected ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها';
    }
  };

  const request = async (root, suffix = '', method = 'GET', body, retry = true) => {
    const headers = { Accept: 'application/json' };
    if (root.dataset.nonce) headers['X-WP-Nonce'] = root.dataset.nonce;
    if (root.dataset.guestToken) {
      headers['X-PC-Rating-Nonce'] = root.dataset.guestNonce;
      headers['X-PC-Rating-Token'] = root.dataset.guestToken;
    }
    if (body) headers['Content-Type'] = 'application/json';
    const response = await fetch(root.dataset.endpoint + suffix, {
      method,
      headers,
      credentials: 'same-origin',
      cache: 'no-store',
      body: body ? JSON.stringify(body) : undefined
    });
    const result = await response.json();
    if (!response.ok && retry && suffix === '/rating' && result.code === 'rating_session_expired') {
      update(root, await request(root));
      return request(root, suffix, method, body, false);
    }
    if (!response.ok) throw new Error(result.message || 'درخواست انجام نشد.');
    return result;
  };

  document.querySelectorAll('.pc-engagement[data-endpoint]').forEach(root => {
    const choices = root.querySelector('.pc-engagement__choices');
    if (choices) {
      const preview = value => choices.querySelectorAll('[data-rating]').forEach(star => {
        star.dataset.filled = String(Number(star.dataset.rating) <= value);
      });
      const restore = () => preview(Number(choices.querySelector('[aria-pressed=true]')?.dataset.rating) || 0);
      choices.addEventListener('pointerover', event => {
        const star = event.target.closest('[data-rating]');
        if (star && choices.contains(star)) preview(Number(star.dataset.rating));
      });
      choices.addEventListener('pointerleave', restore);
      choices.addEventListener('focusin', event => {
        const star = event.target.closest('[data-rating]');
        if (star) preview(Number(star.dataset.rating));
      });
      choices.addEventListener('focusout', event => {
        if (!choices.contains(event.relatedTarget)) restore();
      });
    }
    const status = root.querySelector('.pc-engagement__status');
    root.dataset.busy = 'true';
    request(root).then(data => update(root, data)).catch(() => {
      if (status) status.textContent = 'دریافت وضعیت فعلی انجام نشد.';
    }).finally(() => {
      root.dataset.busy = 'false';
    });
    root.addEventListener('click', async event => {
      const button = event.target.closest('[data-rating], [data-clear-rating], [data-favorite]');
      if (!button || !root.contains(button) || root.dataset.busy === 'true') return;
      let suffix = '/rating';
      let method = 'POST';
      let body;
      if (button.matches('[data-rating]')) body = { rating: Number(button.dataset.rating) };
      if (button.matches('[data-clear-rating]')) method = 'DELETE';
      if (button.matches('[data-favorite]')) {
        suffix = '/favorite';
        method = button.getAttribute('aria-pressed') === 'true' ? 'DELETE' : 'POST';
      }
      root.dataset.busy = 'true';
      root.setAttribute('aria-busy', 'true');
      if (status) status.textContent = '';
      try {
        const data = await request(root, suffix, method, body);
        document.querySelectorAll('.pc-engagement[data-endpoint]').forEach(instance => {
          if (instance.dataset.endpoint === root.dataset.endpoint) update(instance, data);
        });
      } catch (error) {
        if (status) status.textContent = error.message || 'ذخیره انجام نشد.';
      } finally {
        root.dataset.busy = 'false';
        root.removeAttribute('aria-busy');
      }
    });
  });
})();
