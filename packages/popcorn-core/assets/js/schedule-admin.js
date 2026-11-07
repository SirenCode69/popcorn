(() => {
  const picker = document.querySelector('.pc-schedule-picker');
  if (!picker) return;
  const search = picker.querySelector('#pc-schedule-search');
  const selected = picker.querySelector('#pc-schedule-series');
  const cover = picker.querySelector('[data-selected-cover]');
  const clear = picker.querySelector('.pc-schedule-picker__clear');
  const results = picker.querySelector('[role=listbox]');
  const status = picker.querySelector('[data-picker-status]');
  let timer, request, generation = 0, active = -1;
  const close = () => {
    clearTimeout(timer);
    generation++;
    request?.abort();
    results.hidden = true;
    search.setAttribute('aria-expanded', 'false');
    search.removeAttribute('aria-activedescendant');
    status.textContent = '';
    active = -1;
  };
  const reset = () => {
    selected.value = '';
    cover.hidden = true;
    cover.replaceChildren();
    clear.hidden = true;
    search.setCustomValidity('سریال را از فهرست نتایج انتخاب کنید.');
  };
  const select = item => {
    selected.value = item.id;
    search.value = item.title;
    search.setCustomValidity('');
    cover.innerHTML = item.cover;
    cover.hidden = false;
    clear.hidden = false;
    close();
    search.focus();
  };
  const lookup = async query => {
    const current = ++generation;
    request?.abort();
    request = new AbortController();
    status.textContent = 'در حال جست‌وجو…';
    try {
      const body = new URLSearchParams({action:'popcorn_schedule_lookup',nonce:picker.dataset.lookupNonce,query});
      const response = await fetch(picker.dataset.lookupUrl, {method:'POST',body,credentials:'same-origin',cache:'no-store',signal:request.signal});
      const data = await response.json();
      if (current !== generation) return;
      if (!data.success) throw new Error(data.data?.message || 'جست‌وجو انجام نشد.');
      results.replaceChildren();
      active = -1;
      search.removeAttribute('aria-activedescendant');
      data.data.items.forEach((item, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pc-schedule-picker__option';
        button.id = `pc-schedule-option-${index}`;
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', 'false');
        button.tabIndex = -1;
        const image = document.createElement('span');
        image.className = 'pc-schedule-picker__cover';
        image.innerHTML = item.cover;
        const text = document.createElement('span');
        text.className = 'pc-schedule-picker__title';
        const title = document.createElement('strong');
        title.textContent = item.title;
        text.append(title);
        if (item.persian && item.persian !== item.title) {
          const persian = document.createElement('small');
          persian.textContent = item.persian;
          text.append(persian);
        }
        button.append(image, text);
        button.addEventListener('pointerdown', event => event.preventDefault());
        button.addEventListener('click', () => select(item));
        results.append(button);
      });
      results.hidden = !data.data.items.length;
      search.setAttribute('aria-expanded', String(!results.hidden));
      status.textContent = data.data.items.length ? '' : 'سریالی پیدا نشد.';
    } catch (error) {
      if (error.name === 'AbortError' || current !== generation) return;
      close();
      status.textContent = error.message || 'ارتباط برقرار نشد.';
    }
  };
  search.addEventListener('focus', () => { if (!selected.value) lookup(search.value.trim()); });
  search.addEventListener('click', () => { if (selected.value && results.hidden) lookup(''); });
  search.addEventListener('input', () => {
    reset();
    close();
    timer = setTimeout(() => lookup(search.value.trim()), 200);
  });
  search.addEventListener('keydown', event => {
    if (event.key === 'Escape') { close(); return; }
    if (results.hidden) {
      if (event.key === 'ArrowDown') { event.preventDefault(); lookup(selected.value ? '' : search.value.trim()); }
      return;
    }
    const options = [...results.children];
    if (event.key === 'Enter') {
      event.preventDefault();
      if (active >= 0) options[active].click();
      return;
    }
    if (!['ArrowDown','ArrowUp'].includes(event.key)) return;
    event.preventDefault();
    active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
    options.forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
    search.setAttribute('aria-activedescendant', options[active].id);
    options[active].scrollIntoView({block:'nearest'});
  });
  clear.addEventListener('click', () => { reset(); search.value = ''; close(); search.focus(); lookup(''); });
  picker.addEventListener('focusout', event => { if (!picker.contains(event.relatedTarget)) close(); });
  document.addEventListener('click', event => { if (!picker.contains(event.target)) close(); });
  if (!selected.value) search.setCustomValidity('سریال را از فهرست نتایج انتخاب کنید.');
})();
