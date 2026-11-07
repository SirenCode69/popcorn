(() => {
  const form = document.getElementById('popcorn-tmdb-search');
  const results = document.getElementById('popcorn-tmdb-results');
  if (!form || !results) return;
  const field = form.querySelector('[name="popcorn_search"]');
  const button = form.querySelector('button[type="submit"]');
  let controller = null;
  let timer = null;
  const runSearch = async () => {
    const search = field.value.trim();
    if (!search) {
      field.focus();
      return;
    }
    if (controller) controller.abort();
    const requestController = new AbortController();
    controller = requestController;
    button.disabled = true;
    results.setAttribute('aria-busy', 'true');
    results.textContent = 'در حال جست‌وجو در TMDb…';
    const body = new URLSearchParams({
      action: 'popcorn_search_tmdb',
      nonce: form.dataset.nonce,
      type: form.dataset.type,
      post_id: form.dataset.postId,
      search,
    });
    try {
      const response = await fetch(form.dataset.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body,
        signal: requestController.signal,
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.data?.message || 'جست‌وجو ناموفق بود. دوباره تلاش کنید.');
      results.innerHTML = payload.data.html;
      const url = new URL(window.location.href);
      url.searchParams.set('popcorn_search', search);
      window.history.replaceState({}, '', url);
    } catch (error) {
      if (error.name !== 'AbortError') {
        results.textContent = error.message || 'ارتباط با سرور برقرار نشد.';
      }
    } finally {
      if (controller === requestController) {
        button.disabled = false;
        results.removeAttribute('aria-busy');
        controller = null;
      }
    }
  };
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    window.clearTimeout(timer);
    runSearch();
  });
  field.addEventListener('input', () => {
    window.clearTimeout(timer);
    if (controller) controller.abort();
    const search = field.value.trim();
    if (!search) {
      results.replaceChildren();
      const url = new URL(window.location.href);
      url.searchParams.delete('popcorn_search');
      window.history.replaceState({}, '', url);
      return;
    }
    if (search.length >= 3 || /^\d+$/.test(search)) {
      timer = window.setTimeout(runSearch, 550);
    } else {
      results.textContent = 'برای جست‌وجوی نام دست‌کم ۳ حرف وارد کنید.';
    }
  });
})();
