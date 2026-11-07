document.querySelectorAll('.pc-link-report__toggle').forEach((button) => {
  const dialog = document.getElementById(button.getAttribute('aria-controls'));
  if (!dialog) return;
  button.addEventListener('click', () => {
    if (dialog.open) return;
    dialog.showModal();
    button.setAttribute('aria-expanded', 'true');
    document.documentElement.classList.add('pc-link-report-dialog-open');
    dialog.querySelector('textarea')?.focus();
  });
  dialog.querySelector('.pc-link-report-dialog__close')?.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => {
    const rect = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
  });
  dialog.addEventListener('close', () => {
    button.setAttribute('aria-expanded', 'false');
    if (!document.querySelector('.pc-link-report-dialog[open]')) document.documentElement.classList.remove('pc-link-report-dialog-open');
    button.focus({ preventScroll: true });
  });
});
