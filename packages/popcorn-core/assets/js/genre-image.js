(() => {
  const field = document.querySelector('.pc-genre-image');
  if (!field || !window.wp?.media) return;
  const input = field.querySelector('.pc-genre-image__id');
  const preview = field.querySelector('.pc-genre-image__preview');
  const image = preview.querySelector('img');
  const choose = field.querySelector('.pc-genre-image__choose');
  const remove = field.querySelector('.pc-genre-image__remove');
  let frame;
  const update = (id, url) => {
    input.value = id || '';
    image.src = url || '';
    preview.hidden = !url;
    remove.hidden = !url;
    choose.textContent = url ? 'تغییر تصویر' : 'انتخاب یا بارگذاری تصویر';
  };
  choose.addEventListener('click', () => {
    if (!frame) {
      frame = wp.media({ title: 'تصویر ژانر', button: { text: 'انتخاب تصویر' }, library: { type: 'image' }, multiple: false });
      frame.on('select', () => {
        const selected = frame.state().get('selection').first()?.toJSON();
        if (selected) update(selected.id, selected.sizes?.medium?.url || selected.url);
      });
    }
    frame.open();
  });
  remove.addEventListener('click', () => update('', ''));
  if (document.getElementById('addtag') && window.jQuery) {
    window.jQuery(document).ajaxSuccess((event, request, settings) => {
      const data = typeof settings.data === 'string' ? new URLSearchParams(settings.data) : null;
      if (data?.get('action') !== 'add-tag') return;
      const response = request.responseXML || new DOMParser().parseFromString(request.responseText || '', 'text/xml');
      if (response?.querySelector('response') && !response.querySelector('wp_error')) update('', '');
    });
  }
})();
