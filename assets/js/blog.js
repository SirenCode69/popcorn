document.querySelectorAll('[data-blog-filters]').forEach((form) => {
  form.querySelectorAll('[data-blog-select]').forEach((select) => {
    select.querySelector('[data-blog-term-search]').addEventListener('input', (event) => {
      const text = event.target.value.trim().toLocaleLowerCase('fa');
      select.querySelectorAll('[data-blog-term]').forEach((term) => {
        term.hidden = !term.textContent.toLocaleLowerCase('fa').includes(text);
      });
    });
    select.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { select.open = false; select.querySelector('summary').focus(); }
    });
  });
  document.addEventListener('click', (event) => {
    form.querySelectorAll('[data-blog-select][open]').forEach((select) => {
      if (!select.contains(event.target)) select.open = false;
    });
  });
});

document.querySelectorAll('[data-blog-share]').forEach((button) => {
  const status = button.parentElement.querySelector('[data-blog-share-status]');
  button.addEventListener('click', async () => {
    const url = button.dataset.blogUrl;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(url);
      } else {
        const field = document.createElement('textarea');
        field.value = url;
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.append(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();
        button.focus();
        if (!copied) throw new Error('copy');
      }
      status.textContent = 'لینک نوشته کپی شد.';
      button.classList.add('is-copied');
      button.setAttribute('aria-label', 'لینک نوشته کپی شد');
      setTimeout(() => { button.classList.remove('is-copied'); button.setAttribute('aria-label', 'کپی لینک نوشته'); }, 1800);
    } catch (error) {
      status.textContent = 'امکان کپی لینک در این مرورگر وجود ندارد.';
    }
  });
});
