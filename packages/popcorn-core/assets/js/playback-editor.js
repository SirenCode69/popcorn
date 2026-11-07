document.addEventListener('DOMContentLoaded', function () {
  const editor = document.querySelector('.pc-playback-editor');
  if (!editor) return;
  editor.querySelectorAll('[data-playback-repeater]').forEach(function (repeater) {
    let index = repeater.querySelectorAll('.pc-playback-editor__row').length;
    repeater.querySelector('[data-repeater-add]').addEventListener('click', function () {
      const rows = repeater.querySelector('[data-repeater-rows]');
      const limit = repeater.dataset.playbackRepeater === 'subtitles' ? 10 : 20;
      if (rows.children.length >= limit) return;
      const fragment = repeater.querySelector('template').content.cloneNode(true);
      fragment.querySelectorAll('[name]').forEach(function (field) { field.name = field.name.replace('__INDEX__', String(index)); });
      index++;
      rows.append(fragment);
      rows.lastElementChild.querySelector('input').focus();
    });
    repeater.addEventListener('click', function (event) {
      const remove = event.target.closest('[data-repeater-remove]');
      if (remove) remove.closest('.pc-playback-editor__row').remove();
    });
  });
  const search = editor.querySelector('input[type=search]');
  const results = editor.querySelector('.pc-playback-editor__results');
  const status = editor.querySelector('[data-lookup-status]');
  const parent = editor.querySelector('[data-parent-id]');
  const label = editor.querySelector('[data-parent-label]');
  const cover = editor.querySelector('[data-parent-cover]');
  const clear = editor.querySelector('[data-clear-parent]');
  const episodes = editor.querySelector('.pc-playback-editor__episodes');
  let timer;
  let request;
  let generation = 0;
  let selecting = false;
  const coverPreview = function (item) {
    const preview = document.createElement('span');
    preview.className = 'pc-playback-editor__cover';
    if (item.cover) {
      const image = document.createElement('img');
      image.alt = '';
      image.width = 44;
      image.height = 66;
      image.loading = 'lazy';
      image.decoding = 'async';
      if (item.cover_fallback) image.dataset.httpFallback = item.cover_fallback;
      image.src = item.cover;
      preview.append(image);
    } else {
      preview.append(editor.querySelector('[data-cover-placeholder]').content.cloneNode(true));
    }
    return preview;
  };
  const setTitle = function (title) {
    const store = window.wp?.data?.dispatch?.('core/editor');
    if (store?.editPost) {
      store.editPost({title:title});
    } else {
      const field = document.getElementById('title');
      if (field) {
        field.value = title;
        field.dispatchEvent(new Event('input', {bubbles:true}));
        field.dispatchEvent(new Event('change', {bubbles:true}));
      }
    }
  };
  const close = function () {
    results.hidden = true;
    search.setAttribute('aria-expanded', 'false');
  };
  const lookup = async function () {
    const current = ++generation;
    if (request) request.abort();
    request = new AbortController();
    status.textContent = 'در حال جست‌وجو…';
    try {
      const body = new URLSearchParams({action:'popcorn_playback_lookup',nonce:editor.dataset.lookupNonce,query:search.value.trim()});
      const response = await fetch(editor.dataset.lookupUrl, {method:'POST',body,credentials:'same-origin',signal:request.signal});
      const data = await response.json();
      if (current !== generation) return;
      if (!data.success) throw new Error(data.data?.message || 'جست‌وجو انجام نشد.');
      results.replaceChildren();
      data.data.items.forEach(function (item) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pc-playback-editor__result';
        const text = document.createElement('span');
        text.className = 'pc-playback-editor__result-label';
        text.textContent = item.label;
        button.append(coverPreview(item), text);
        button.addEventListener('click', function () {
          parent.value = item.id;
          label.textContent = item.label;
          const preview = coverPreview(item);
          cover.replaceChildren(...preview.childNodes);
          cover.hidden = false;
          setTitle(item.title);
          episodes.hidden = item.type !== 'series';
          clear.hidden = false;
          search.value = '';
          clearTimeout(timer);
          generation++;
          if (request) request.abort();
          status.textContent = 'اثر انتخاب شد.';
          close();
          selecting = true;
          search.focus();
          selecting = false;
        });
        results.append(button);
      });
      results.hidden = !data.data.items.length;
      search.setAttribute('aria-expanded', results.hidden ? 'false' : 'true');
      status.textContent = data.data.items.length ? '' : 'اثری پیدا نشد.';
    } catch (error) {
      if (error.name === 'AbortError' || current !== generation) return;
      close();
      status.textContent = error.message || 'ارتباط برقرار نشد. دوباره تلاش کنید.';
    }
  };
  search.addEventListener('input', function () {
    clearTimeout(timer);
    generation++;
    if (request) request.abort();
    timer = setTimeout(lookup, 300);
  });
  search.addEventListener('focus', function () { if (!selecting) lookup(); });
  search.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowDown' && !results.hidden) {
      event.preventDefault();
      results.querySelector('button')?.focus();
    }
    if (event.key === 'Enter') event.preventDefault();
  });
  clear.addEventListener('click', function () {
    parent.value = '';
    label.textContent = 'اثری انتخاب نشده است.';
    cover.replaceChildren();
    cover.hidden = true;
    clear.hidden = true;
    episodes.hidden = true;
    search.focus();
  });
  document.addEventListener('click', function (event) {
    if (!editor.contains(event.target)) close();
  });
});
