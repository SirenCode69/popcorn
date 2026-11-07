(() => {
  const select = document.querySelector('[data-auth-graphic-select]');
  const remove = document.querySelector('[data-auth-graphic-remove]');
  const input = document.getElementById('pc-auth-graphic-id');
  const preview = document.getElementById('pc-auth-graphic-preview');
  if (!select || !input || !window.wp?.media) return;
  let frame;
  select.addEventListener('click', () => {
    if (!frame) {
      frame = wp.media({title:'تصویر صفحهٔ ورود و ثبت‌نام', library:{type:'image'}, button:{text:'انتخاب تصویر'}, multiple:false});
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        input.value = attachment.id;
        const image = document.createElement('img');
        image.src = attachment.sizes?.medium?.url || attachment.url;
        image.alt = '';
        image.style.cssText = 'max-width:240px;height:auto;border-radius:12px';
        preview.replaceChildren(image);
      });
    }
    frame.open();
  });
  remove.addEventListener('click', () => { input.value = '0'; preview.replaceChildren(); });
})();
