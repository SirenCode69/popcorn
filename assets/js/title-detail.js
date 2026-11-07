(() => {
  const page = document.querySelector('.pc-title-detail');
  if (!page) return;
  const dialogs = [...page.querySelectorAll('dialog.pc-title-dialog')];
  const galleryDialog = page.querySelector('#pc-title-image');
  let galleryImages = [];
  let galleryRequest;
  let galleryIndex = 0;
  const showImage = index => {
    if (!galleryImages.length) return;
    galleryIndex = (index + galleryImages.length) % galleryImages.length;
    const template = document.createElement('template');
    template.innerHTML = galleryImages[galleryIndex];
    galleryDialog.querySelector('[data-pc-gallery-image]').replaceChildren(template.content.cloneNode(true));
    galleryDialog.querySelector('[data-pc-gallery-count]').textContent = `${galleryIndex + 1} / ${galleryImages.length}`;
  };
  const loadGallery = async trigger => {
    galleryRequest?.abort();
    const controller = new AbortController();
    galleryRequest = controller;
    galleryImages = [];
    const loading = galleryDialog.querySelector('[data-pc-gallery-loading]');
    const status = galleryDialog.querySelector('[data-pc-gallery-status]');
    const controls = galleryDialog.querySelector('.pc-title-dialog__gallery-controls');
    galleryDialog.querySelector('[data-pc-gallery-image]').replaceChildren();
    controls.hidden = true;
    status.textContent = '';
    loading.hidden = false;
    galleryDialog.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(trigger.dataset.endpoint, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'دریافت تصاویر انجام نشد.');
      if (controller.signal.aborted || !galleryDialog.open) return;
      galleryImages = Array.isArray(data.images) ? data.images.filter(image => typeof image === 'string' && image) : [];
      if (!galleryImages.length) {
        status.textContent = 'تصویری برای این اثر ثبت نشده است.';
        return;
      }
      showImage(0);
      controls.hidden = false;
      controls.querySelectorAll('button').forEach(button => { button.disabled = galleryImages.length < 2; });
    } catch (error) {
      if (error.name !== 'AbortError') status.textContent = 'دریافت تصاویر انجام نشد. گالری را ببندید و دوباره باز کنید.';
    } finally {
      if (galleryRequest === controller) {
        loading.hidden = true;
        galleryDialog.removeAttribute('aria-busy');
      }
    }
  };
  if (galleryDialog) {
    galleryDialog.querySelector('[data-pc-gallery-prev]').addEventListener('click', () => showImage(galleryIndex - 1));
    galleryDialog.querySelector('[data-pc-gallery-next]').addEventListener('click', () => showImage(galleryIndex + 1));
    galleryDialog.addEventListener('keydown', event => {
      if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
      event.preventDefault();
      showImage(galleryIndex + (event.key === 'ArrowLeft' ? 1 : -1));
    });
  }
  const close = dialog => dialog.close();
  dialogs.forEach(dialog => {
    dialog.querySelector('[data-pc-dialog-close]').addEventListener('click', () => close(dialog));
    dialog.addEventListener('click', event => {
      const rect = dialog.getBoundingClientRect();
      if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) close(dialog);
    });
    dialog.addEventListener('close', () => {
      const frame = dialog.querySelector('iframe');
      const video = dialog.querySelector('video');
      if (frame) frame.removeAttribute('src');
      if (video) (video.player?.media || video).pause();
      if (dialog === galleryDialog) {
        galleryRequest?.abort();
        galleryImages = [];
        galleryDialog.querySelector('[data-pc-gallery-image]').replaceChildren();
      }
      document.documentElement.classList.remove('pc-title-dialog-open');
    });
  });
  page.addEventListener('click', async event => {
    const trigger = event.target.closest('[data-pc-dialog-open], [data-pc-gallery-open]');
    if (trigger) {
      const gallery = trigger.hasAttribute('data-pc-gallery-open');
      const dialog = document.getElementById(gallery ? 'pc-title-image' : trigger.dataset.pcDialogOpen);
      if (!dialog) return;
      dialog.showModal();
      document.documentElement.classList.add('pc-title-dialog-open');
      if (gallery) loadGallery(trigger);
      const playerTemplate = dialog.querySelector('[data-pc-trailer-player]');
      if (playerTemplate) {
        playerTemplate.replaceWith(playerTemplate.content.cloneNode(true));
        window.wp?.mediaelement?.initialize();
      }
      const frame = dialog.querySelector('iframe');
      const video = dialog.querySelector('video');
      if (frame) frame.src = frame.dataset.src;
      if (video) {
        if (video.dataset.src) video.src = video.dataset.src;
        const result = (video.player?.media || video).play();
        result?.catch(() => {});
      }
    }
    const share = event.target.closest('[data-pc-share]');
    if (share) {
      const status = page.querySelector('[data-pc-share-status]');
      const url = new URL(window.location.href);
      url.search = '';
      url.hash = '';
      try {
        if (navigator.share) await navigator.share({title:document.title, url:url.href});
        else {
          await navigator.clipboard.writeText(url.href);
          status.textContent = 'لینک کپی شد.';
        }
      } catch (error) {
        if (error.name !== 'AbortError') status.textContent = 'امکان کپی خودکار نیست؛ نشانی صفحه را کپی کنید.';
      }
    }
  });
})();
