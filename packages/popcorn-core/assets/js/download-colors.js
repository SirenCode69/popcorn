(() => {
  const form = document.querySelector('.pc-download-settings');
  if (!form) return;
  for (const picker of form.querySelectorAll('[data-color]')) {
    const hex = form.querySelector('[data-color-hex="' + picker.dataset.color + '"]');
    if (!hex) continue;
    picker.addEventListener('input', () => { hex.value = picker.value.toUpperCase(); });
    hex.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) picker.value = hex.value; });
  }
  let nextIndex = Date.now();
  form.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (!button || !form.contains(button)) return;
    const row = button.closest('.pc-download-option-row');
    if (button.classList.contains('pc-download-option-remove')) {
      row?.remove();
      return;
    }
    if (button.classList.contains('pc-download-option-up')) {
      if (row?.previousElementSibling) row.before(row.previousElementSibling);
      return;
    }
    if (button.classList.contains('pc-download-option-down')) {
      if (row?.nextElementSibling) row.after(row.nextElementSibling);
      return;
    }
    const key = button.dataset.addOption;
    if (!key) return;
    const list = form.querySelector('[data-download-list="' + key + '"]');
    const template = form.querySelector('[data-option-template="' + key + '"]');
    if (!list || !template) return;
    const holder = document.createElement('div');
    holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)).trim();
    const newRow = holder.firstElementChild;
    if (newRow) {
      list.append(newRow);
      newRow.querySelector('input')?.focus();
    }
  });
})();
