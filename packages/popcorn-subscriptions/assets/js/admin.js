(function () {
  const root = document.querySelector('.pc-sub-admin-image');
  if (!root || !window.wp || !wp.media) return;
  const input = root.querySelector('input[name="plan[image_id]"]');
  const preview = root.querySelector('[data-image-preview]');
  root.querySelector('[data-select-image]').addEventListener('click', function () {
    const frame = wp.media({ title: 'تصویر پلن', button: { text: 'انتخاب تصویر' }, library: { type: 'image' }, multiple: false });
    frame.on('select', function () {
      const image = frame.state().get('selection').first().toJSON();
      input.value = image.id;
      const url = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
      preview.replaceChildren();
      const element = document.createElement('img');
      element.src = url;
      element.alt = image.alt || '';
      element.style.maxWidth = '120px';
      element.style.height = 'auto';
      preview.append(element);
    });
    frame.open();
  });
  root.querySelector('[data-remove-image]').addEventListener('click', function () {
    input.value = '0';
    preview.replaceChildren();
  });
})();
