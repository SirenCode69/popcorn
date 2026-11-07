const popcornMediaSizes = document.getElementById('popcorn-media-sizes');
const popcornMediaTemplate = document.getElementById('popcorn-media-size-template');
const popcornAddMediaSize = document.getElementById('popcorn-add-media-size');
let popcornMediaIndex = Date.now();

if (popcornMediaSizes && popcornMediaTemplate && popcornAddMediaSize) {
  popcornAddMediaSize.addEventListener('click', () => {
    const index = popcornMediaIndex++;
    const html = popcornMediaTemplate.innerHTML.replaceAll('__INDEX__', String(index));
    popcornMediaSizes.insertAdjacentHTML('beforeend', html);
    const row = popcornMediaSizes.lastElementChild;
    row.querySelector('input[name$="[slug]"]').value = `popcorn_size_${index}`;
    row.querySelector('input')?.focus();
  });

  popcornMediaSizes.addEventListener('click', (event) => {
    const button = event.target.closest('.pc-media-size__remove');
    if (button) {
      button.closest('[data-media-size]')?.remove();
    }
  });
}

const popcornRegenerateMedia = document.getElementById('popcorn-regenerate-media');
const popcornRegenerateStatus = document.getElementById('popcorn-regenerate-status');

if (popcornRegenerateMedia && popcornRegenerateStatus) {
  popcornRegenerateMedia.addEventListener('click', async () => {
    popcornRegenerateMedia.disabled = true;
    let cursor = 0;
    let processed = 0;
    popcornRegenerateStatus.textContent = 'در حال ساخت اندازه‌ها و تبدیل تصاویر...';

    try {
      while (true) {
        const body = new URLSearchParams({
          action: 'popcorn_regenerate_media',
          nonce: popcornRegenerateMedia.dataset.nonce,
          cursor: String(cursor),
        });
        const response = await fetch(popcornRegenerateMedia.dataset.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body,
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.data?.message || 'ساخت تصاویر ناموفق بود.');
        }
        cursor = result.data.cursor;
        processed += result.data.processed;
        popcornRegenerateStatus.textContent = `${processed} تصویر آماده شد...`;
        if (result.data.done) {
          break;
        }
      }
      popcornRegenerateStatus.textContent = `ساخت تصاویر پایان یافت. ${processed} تصویر آماده شد.`;
    } catch (error) {
      popcornRegenerateStatus.textContent = error.message;
    } finally {
      popcornRegenerateMedia.disabled = false;
    }
  });
}
