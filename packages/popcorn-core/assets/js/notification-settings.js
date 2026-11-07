(() => {
  const config = window.popcornNotificationTarget;
  const audience = document.getElementById('pc-notification-audience');
  const wrapper = document.querySelector('.pc-notification-target');
  const search = document.getElementById('pc-notification-target-search');
  const target = document.getElementById('pc-notification-target-id');
  const results = document.querySelector('.pc-notification-target__results');
  if (!config || !audience || !wrapper || !search || !target || !results) return;

  let timer;
  let request;
  search.hidden = false;
  target.hidden = true;

  const clearResults = () => {
    results.replaceChildren();
    results.hidden = true;
  };

  const refreshAudience = () => {
    wrapper.hidden = audience.value === 'all';
    search.placeholder = audience.value === 'user' ? 'نام یا ایمیل کاربر...' : 'نام فیلم یا سریال...';
    search.value = '';
    target.value = '';
    clearResults();
  };

  audience.addEventListener('change', refreshAudience);
  refreshAudience();

  search.addEventListener('input', () => {
    target.value = '';
    clearTimeout(timer);
    if (request) request.abort();
    clearResults();
    const query = search.value.trim();
    if (query.length < 2) return;
    timer = setTimeout(async () => {
      request = new AbortController();
      const params = new URLSearchParams({ action: 'popcorn_notification_target_search', nonce: config.nonce, audience: audience.value, q: query });
      try {
        const response = await fetch(config.url + '?' + params.toString(), { credentials: 'same-origin', signal: request.signal });
        const data = await response.json();
        if (!response.ok || !data.success || search.value.trim() !== query) return;
        const items = data.data.items || [];
        items.forEach((item) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.setAttribute('role', 'option');
          button.textContent = item.label;
          button.addEventListener('click', () => {
            search.value = item.label;
            target.value = item.id;
            clearResults();
          });
          results.append(button);
        });
        if (!items.length) {
          const empty = document.createElement('span');
          empty.textContent = 'موردی پیدا نشد.';
          results.append(empty);
        }
        results.hidden = false;
      } catch (error) {
        if (error.name !== 'AbortError') clearResults();
      }
    }, 250);
  });

  document.addEventListener('click', (event) => {
    if (!wrapper.contains(event.target)) clearResults();
  });
})();
