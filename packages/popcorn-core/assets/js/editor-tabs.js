(() => {
  const editor = document.querySelector('.pc-editor');
  if (!editor) return;
  const tabs = [...editor.querySelectorAll('.pc-editor__tab')];
  const requests = new WeakMap();
  let nextRow = Date.now();
  const activeKey = 'popcorn-editor-tab:' + window.location.pathname + window.location.search;
  const activate = (tab) => {
    tabs.forEach((item) => {
      const active = item === tab;
      item.setAttribute('aria-selected', String(active));
      item.tabIndex = active ? 0 : -1;
      const panel = document.getElementById(item.getAttribute('aria-controls'));
      if (panel) panel.hidden = !active;
    });
    window.sessionStorage.setItem(activeKey, tab.id);
  };
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activate(tab));
    tab.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return;
      event.preventDefault();
      const next = tabs[(index + (event.key === 'ArrowLeft' ? 1 : tabs.length - 1)) % tabs.length];
      activate(next);
      next.focus();
    });
  });
  const remembered = document.getElementById(window.sessionStorage.getItem(activeKey));
  if (remembered && tabs.includes(remembered)) activate(remembered);
  const preview = (row, url) => {
    const holder = row.querySelector('.pc-editor__media-preview,.pc-editor__gallery-preview');
    if (!holder) return;
    holder.replaceChildren();
    if (url) {
      const image = document.createElement('img');
      const parsed = new URL(url, window.location.href);
      if (parsed.protocol === 'http:' && (parsed.host !== window.location.host || window.location.protocol === 'https:')) parsed.protocol = 'https:';
      if (parsed.protocol === 'https:' && parsed.host !== window.location.host && !parsed.search) {
        const fallback = new URL(parsed.href);
        fallback.protocol = 'http:';
        const proxy = new URL(editor.dataset.ajaxUrl);
        proxy.searchParams.set('action', 'popcorn_image_http_fallback');
        proxy.searchParams.set('url', fallback.href);
        proxy.searchParams.set('nonce', editor.dataset.imageNonce);
        image.dataset.httpFallback = proxy.href;
      }
      image.src = parsed.href;
      image.alt = 'پیش‌نمایش تصویر';
      image.loading = 'lazy';
      holder.append(image);
    } else {
      const empty = document.createElement('span');
      empty.textContent = 'بدون تصویر';
      holder.append(empty);
    }
  };
  const imageModal = editor.querySelector('.pc-editor__image-modal');
  const imageTitle = imageModal.querySelector('#pc-editor-image-title');
  const imagePath = imageModal.querySelector('#pc-editor-image-path');
  const imageStatus = imageModal.querySelector('.pc-editor__image-modal-status');
  const imageHelp = imageModal.querySelector('.pc-editor__image-modal-help');
  const chooseImage = imageModal.querySelector('.pc-editor__choose-media');
  let activeImageRow = null;
  let imageReturnFocus = null;
  let initialImageState = null;
  document.body.append(imageModal);
  const imagePathMatch = (value) => value.trim().match(/^(?:https:\/\/image\.tmdb\.org\/t\/p\/(?:original|w\d+))?(\/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp))$/i);
  const externalImageUrl = (value) => {
    const url = value.trim();
    if (url.length > 2048 || !/^https?:\/\//i.test(url)) return '';
    try {
      const parsed = new URL(url);
      return parsed.hostname && !parsed.username && !parsed.password ? url : '';
    } catch {
      return '';
    }
  };
  const imageReference = (value) => {
    const tmdb = imagePathMatch(value);
    if (tmdb) return { type: 'tmdb', url: 'https://image.tmdb.org/t/p/w185' + tmdb[1] };
    const external = externalImageUrl(value);
    return external ? { type: 'external', url: external } : null;
  };
  const updateImageStatus = () => {
    if (!activeImageRow) return;
    const attachmentId = Number(activeImageRow.querySelector('.pc-editor__attachment-id').value);
    const path = activeImageRow.querySelector('.pc-editor__image-path').value.trim();
    const reference = imageReference(path);
    const invalid = !attachmentId && path !== '' && !reference;
    imageStatus.textContent = attachmentId ? 'در رسانه' : (path ? (invalid ? 'لینک یا مسیر تصویر نامعتبر است' : (reference.type === 'external' ? 'لینک خارجی؛ نمایش مستقیم' : (editor.dataset.published === '1' ? 'در انتظار دریافت' : 'در انتظار انتشار'))) : 'بدون تصویر');
    imageStatus.classList.toggle('is-error', invalid);
  };
  const closeImageModal = (commit = false) => {
    if (!activeImageRow) return;
    const pathField = activeImageRow.querySelector('.pc-editor__image-path');
    const attachmentField = activeImageRow.querySelector('.pc-editor__attachment-id');
    if (commit && pathField.value.trim() && !imageReference(pathField.value)) {
      updateImageStatus();
      imagePath.focus();
      return;
    }
    if (!commit && initialImageState) {
      pathField.value = initialImageState.path;
      attachmentField.value = initialImageState.attachmentId;
      initialImageState.preview.replaceChildren(...initialImageState.nodes.map((node) => node.cloneNode(true)));
    }
    imageModal.hidden = true;
    activeImageRow = null;
    initialImageState = null;
    imageReturnFocus?.focus();
  };
  const openImageModal = (button) => {
    activeImageRow = button.closest('.pc-editor__media,.pc-editor__gallery-row');
    imageReturnFocus = button;
    const previewHolder = activeImageRow.querySelector('.pc-editor__media-preview,.pc-editor__gallery-preview');
    initialImageState = { path: activeImageRow.querySelector('.pc-editor__image-path').value, attachmentId: activeImageRow.querySelector('.pc-editor__attachment-id').value, preview: previewHolder, nodes: [...previewHolder.childNodes].map((node) => node.cloneNode(true)) };
    imageTitle.textContent = button.dataset.title || 'مدیریت تصویر';
    imagePath.value = activeImageRow.querySelector('.pc-editor__image-path').value;
    chooseImage.hidden = editor.dataset.published !== '1';
    imageHelp.textContent = (editor.dataset.published === '1' ? (activeImageRow.classList.contains('pc-editor__gallery-row') ? 'تصاویر تازهٔ TMDb پس از ذخیره به‌تدریج دریافت و به WebP تبدیل می‌شوند.' : 'تصویر TMDb پس از ذخیره دریافت و به WebP تبدیل می‌شود.') : 'مسیر TMDb در پیش‌نویس نگهداری می‌شود و تصویر پس از انتشار دریافت و به WebP تبدیل می‌شود.') + ' لینک‌های خارجی مستقیم نمایش داده می‌شوند و در رسانه ذخیره یا به WebP تبدیل نمی‌شوند.';
    updateImageStatus();
    imageModal.hidden = false;
    imagePath.focus();
  };
  imageModal.addEventListener('click', (event) => {
    if (event.target.closest('.pc-editor__image-done')) {
      closeImageModal(true);
      return;
    }
    if (event.target.closest('.pc-editor__image-close,.pc-editor__image-modal-backdrop')) {
      closeImageModal();
      return;
    }
    if (event.target.closest('.pc-editor__choose-media') && window.wp?.media && activeImageRow) {
      const row = activeImageRow;
      const frame = window.wp.media({ title: imageTitle.textContent, button: { text: 'انتخاب تصویر' }, library: { type: 'image' }, multiple: false });
      frame.on('select', () => {
        const file = frame.state().get('selection').first()?.toJSON();
        if (!file) return;
        row.querySelector('.pc-editor__attachment-id').value = String(file.id);
        row.querySelector('.pc-editor__image-path').value = '';
        imagePath.value = '';
        preview(row, file.sizes?.medium?.url || file.sizes?.thumbnail?.url || file.url);
        updateImageStatus();
      });
      frame.open();
    }
  });
  imageModal.addEventListener('input', (event) => {
    if (event.target !== imagePath || !activeImageRow) return;
    activeImageRow.querySelector('.pc-editor__image-path').value = imagePath.value;
    activeImageRow.querySelector('.pc-editor__attachment-id').value = '';
    const reference = imageReference(imagePath.value);
    preview(activeImageRow, reference ? reference.url : '');
    updateImageStatus();
  });
  imageModal.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      closeImageModal();
    }
  });
  editor.addEventListener('click', (event) => {
    const imageTrigger = event.target.closest('.pc-editor__image-trigger');
    if (imageTrigger) {
      openImageModal(imageTrigger);
      return;
    }
    const translate = event.target.closest('.pc-editor__translate');
    if (translate) {
      const field = translate.dataset.field;
      const target = editor.querySelector('#popcorn-field-' + field);
      const status = translate.parentElement.querySelector('.pc-editor__translate-status');
      if (!target || !status) return;
      translate.disabled = true;
      status.textContent = 'در حال ترجمه…';
      (async () => {
        const translated = [];
        for (let index = 0; index < 40; index++) {
          const body = new URLSearchParams({ action: 'popcorn_translate_content', nonce: editor.dataset.translateNonce, post_id: editor.dataset.postId, field, index: String(index) });
          const response = await fetch(editor.dataset.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body });
          const data = await response.json();
          if (!response.ok || !data.success) throw new Error(data.data?.message || 'ترجمه انجام نشد.');
          translated.push(data.data.text);
          status.textContent = 'در حال ترجمهٔ بخش ' + (index + 1) + ' از ' + data.data.total + '…';
          if (index + 1 >= data.data.total) break;
        }
        target.value = translated.join(' ');
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.dispatchEvent(new Event('change', { bubbles: true }));
        status.textContent = 'ترجمه آماده است؛ برای ثبت، نوشته را ذخیره کنید.';
      })().catch((error) => {
        status.textContent = error.message || 'ترجمه انجام نشد.';
      }).finally(() => {
        translate.disabled = false;
      });
      return;
    }
    const add = event.target.closest('.pc-editor__add-row');
    if (add) {
      const kind = add.dataset.kind;
      const template = document.getElementById('pc-editor-' + kind + '-template');
      const list = editor.querySelector('[data-repeater="' + kind + '"]');
      if (template && list) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextRow++));
        list.append(...wrapper.children);
      }
      return;
    }
    const remove = event.target.closest('.pc-editor__remove-row');
    if (remove) {
      remove.closest('.pc-editor__repeat-row')?.remove();
      return;
    }
    const up = event.target.closest('.pc-editor__move-up');
    const down = event.target.closest('.pc-editor__move-down');
    if (up || down) {
      const row = event.target.closest('.pc-editor__repeat-row');
      if (up && row?.previousElementSibling) row.before(row.previousElementSibling);
      if (down && row?.nextElementSibling) row.after(row.nextElementSibling);
      return;
    }
    const removeChip = event.target.closest('.pc-editor__remove-chip');
    if (removeChip) {
      removeChip.closest('.pc-editor__chip')?.remove();
      return;
    }
  });
  editor.addEventListener('input', (event) => {
    const person = event.target.closest('.pc-editor__person-search');
    if (person) {
      person.closest('.pc-editor__person-picker').querySelector('.pc-editor__person-id').value = '';
      lookup(person, 'person');
    }
    const collection = event.target.closest('.pc-editor__collection-search');
    if (collection) lookup(collection, 'collection');
  });
  const collectionSelect = editor.querySelector('#popcorn-collection-id');
  const collectionName = editor.querySelector('#popcorn-collection-name');
  const collectionNameEdited = editor.querySelector('#popcorn-collection-name-edited');
  const collectionMembers = editor.querySelector('.pc-editor__collection-members');
  const collectionDrafts = new Map();
  const collectionNames = new Map();
  const collectionEdits = new Map();
  let activeCollection = collectionSelect?.value ?? '0';
  collectionName?.addEventListener('input', () => { collectionNameEdited.value = '1'; });
  collectionSelect?.addEventListener('change', () => {
    collectionDrafts.set(activeCollection, [...collectionMembers.children].map((child) => child.cloneNode(true)));
    collectionNames.set(activeCollection, collectionName.value);
    collectionEdits.set(activeCollection, collectionNameEdited.value);
    activeCollection = collectionSelect.value;
    collectionName.value = collectionNames.get(activeCollection) ?? (activeCollection === '0' ? '' : collectionSelect.selectedOptions[0]?.textContent ?? '');
    collectionNameEdited.value = collectionEdits.get(activeCollection) ?? '0';
    collectionMembers.replaceChildren(...(collectionDrafts.get(activeCollection) ?? []).map((child) => child.cloneNode(true)));
    const search = editor.querySelector('.pc-editor__collection-search');
    search.value = '';
    search.parentElement.querySelector('.pc-editor__suggestions').replaceChildren();
  });
  editor.addEventListener('focusin', (event) => {
    const collection = event.target.closest('.pc-editor__collection-search');
    if (collection) lookup(collection, 'collection', true);
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.pc-editor__collection-picker')) editor.querySelector('.pc-editor__collection-picker .pc-editor__suggestions')?.replaceChildren();
  });
  const lookup = (input, kind, immediate = false) => {
    const previous = requests.get(input);
    if (previous) {
      window.clearTimeout(previous.timer);
      previous.controller?.abort();
    }
    const holder = input.parentElement.querySelector('.pc-editor__suggestions');
    holder.replaceChildren();
    const query = input.value.trim();
    if (kind === 'person' && query.length < 2) return;
    const state = { controller: null, timer: null };
    requests.set(input, state);
    state.timer = window.setTimeout(async () => {
      state.controller = new AbortController();
      const body = new URLSearchParams({ action: 'popcorn_editor_lookup', nonce: editor.dataset.nonce, type: editor.dataset.type, post_id: editor.dataset.postId, kind, query });
      if (kind === 'collection') collectionMembers.querySelectorAll('input[name="popcorn_collection_members[]"]').forEach((field) => body.append('excluded[]', field.value));
      try {
        const response = await fetch(editor.dataset.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body, signal: state.controller.signal });
        const data = await response.json();
        if (!response.ok || !data.success) return;
        for (const result of data.data.items) {
          if (kind === 'collection' && collectionMembers.querySelector('input[value="' + result.id + '"]')) continue;
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'pc-editor__suggestion';
          if (result.secondary) {
            const label = document.createElement('span');
            label.textContent = result.label;
            const secondary = document.createElement('small');
            secondary.textContent = result.secondary;
            button.append(label, secondary);
          } else {
            button.textContent = result.label;
          }
          button.addEventListener('click', () => {
            if (kind === 'person') {
              input.value = result.label;
              input.parentElement.querySelector('.pc-editor__person-id').value = String(result.id);
            } else {
              const list = editor.querySelector('.pc-editor__collection-members');
              if (!list.querySelector('input[value="' + result.id + '"]')) {
                const chip = document.createElement('span');
                chip.className = 'pc-editor__chip';
                chip.textContent = result.label;
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'popcorn_collection_members[]';
                hidden.value = String(result.id);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'pc-editor__remove-chip';
                remove.setAttribute('aria-label', 'حذف از کالکشن');
                remove.textContent = 'حذف';
                chip.append(hidden, remove);
                list.append(chip);
              }
              input.value = '';
            }
            holder.replaceChildren();
          });
          holder.append(button);
        }
        if (!holder.childElementCount && kind === 'collection') holder.textContent = 'عنوان دیگری پیدا نشد.';
      } catch (error) {
        if (error.name !== 'AbortError') holder.textContent = 'جست‌وجو ناموفق بود.';
      }
    }, immediate ? 0 : 300);
  };
})();
