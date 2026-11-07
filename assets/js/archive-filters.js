document.querySelectorAll('[data-archive-page]').forEach((page) => {
  const form = page.querySelector('[data-archive-filters]');
  const results = page.querySelector('[data-archive-results]');
  const count = page.querySelector('[data-archive-count]');
  const loading = page.querySelector('[data-archive-loading]');
  const status = page.querySelector('[data-archive-status]');
  if (!results) return;
  const toggle = form?.querySelector('[data-archive-toggle]');
  const fields = form?.querySelector('[data-archive-fields]');
  let controller;
  let timer;
  form?.classList.add('is-enhanced');

  toggle?.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    fields.classList.toggle('is-open', open);
  });

  const formUrl = () => {
    const url = new URL(form.action, window.location.href);
    const names = new Set();
    for (const [key, value] of new FormData(form)) {
      if (!names.has(key)) url.searchParams.delete(key);
      names.add(key);
      if (String(value).trim() !== '') url.searchParams.append(key, String(value).trim());
    }
    return url;
  };

  const load = async (url, historyMode = 'push', scroll = false) => {
    clearTimeout(timer);
    controller?.abort();
    const request = new AbortController();
    controller = request;
    loading.hidden = false;
    results.setAttribute('aria-busy', 'true');
    status.textContent = '';
    try {
      const response = await fetch(url, { credentials: 'same-origin', cache: (form || page).dataset.archiveCache === 'plugin' ? 'default' : 'no-store', signal: request.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!response.ok) throw new Error('archive');
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (request.signal.aborted) return;
      const nextResults = doc.querySelector('[data-archive-results]');
      const nextCount = doc.querySelector('[data-archive-count]');
      if (!nextResults || !nextCount) throw new Error('archive');
      results.querySelectorAll('[data-pc-slider]').forEach((slider) => slider.swiper?.destroy(true, true));
      results.replaceChildren(...nextResults.childNodes);
      count.replaceChildren(...nextCount.childNodes);
      document.title = doc.title;
      const nextControls = Array.from(doc.querySelectorAll('[data-archive-filters] input, [data-archive-filters] select'));
      Array.from(form?.elements || []).forEach((control) => {
        if (!control.name) return;
        const next = nextControls.find((input) => input.name === control.name && (control.type !== 'checkbox' || input.value === control.value));
        if (!next) return;
        if (control.type === 'checkbox') control.checked = next.checked;
        else control.value = next.value;
      });
      form?.querySelectorAll('[data-blog-select]').forEach((select, index) => {
        const next = doc.querySelectorAll('[data-blog-select] summary span')[index];
        if (next) select.querySelector('summary span').replaceChildren(...next.childNodes);
      });
      const reset = form?.querySelector('[data-archive-reset]');
      const nextReset = doc.querySelector('[data-archive-reset]');
      if (reset && nextReset) reset.hidden = nextReset.hidden;
      if (historyMode === 'push') window.history.pushState({}, '', url);
      if (historyMode === 'replace') window.history.replaceState({}, '', url);
      status.textContent = nextCount.textContent.trim();
      document.dispatchEvent(new CustomEvent('popcorn:archive-updated', { detail: { root: results } }));
      if (scroll) page.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
    } catch (error) {
      if (error.name !== 'AbortError') window.location.assign(url);
    } finally {
      if (controller === request) {
        loading.hidden = true;
        results.setAttribute('aria-busy', 'false');
      }
    }
  };

  form?.addEventListener('submit', (event) => {
    event.preventDefault();
    load(formUrl());
  });
  form?.addEventListener('change', (event) => {
    if (event.target.matches('select')) load(formUrl());
    if (form.hasAttribute('data-archive-checkboxes') && event.target.matches('input[type="checkbox"]')) {
      clearTimeout(timer);
      controller?.abort();
      timer = setTimeout(() => load(formUrl()), 200);
    }
  });
  form?.addEventListener('input', (event) => {
    if (!event.target.matches('input[type="search"]') || event.target.hasAttribute('data-blog-term-search')) return;
    clearTimeout(timer);
    controller?.abort();
    const length = event.target.value.trim().length;
    if (length === 1) return;
    timer = setTimeout(() => load(formUrl(), 'replace'), 400);
  });
  results.addEventListener('click', (event) => {
    const link = event.target.closest('.page-numbers[href], .pc-search-results .pc-slider__more[href]');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return;
    event.preventDefault();
    load(url, 'push', true);
  });
  form?.addEventListener('click', (event) => {
    const link = event.target.closest('[data-archive-reset][href]');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return;
    event.preventDefault();
    load(url);
  });
  window.addEventListener('popstate', () => load(new URL(window.location.href), 'none'));
});
