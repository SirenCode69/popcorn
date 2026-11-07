(() => {
  const select = document.getElementById('pc-auth-gateway');
  if (!select) return;

  const rows = [...document.querySelectorAll('[data-pc-auth-field]')];
  const drafts = new Map();
  let previous = select.value;
  const readValues = () => Object.fromEntries(rows.filter((row) => !['api_key', 'api_password'].includes(row.dataset.pcAuthField)).map((row) => [row.dataset.pcAuthField, row.querySelector('input, textarea').value]));
  drafts.set(previous, readValues());

  const render = () => {
    const schema = JSON.parse(select.selectedOptions[0].dataset.schema);
    rows.forEach((row) => {
      const field = row.dataset.pcAuthField;
      const input = row.querySelector('input, textarea');
      const visible = schema.fields.includes(field);
      row.hidden = !visible;
      input.disabled = !visible;
      row.querySelector('[data-pc-auth-label]').textContent = schema.labels[field];
      row.querySelector('[data-pc-auth-help]').textContent = schema.help[field];
      if (field === 'template') input.dir = schema.template_dir;
      if (input.type === 'password') {
        input.placeholder = select.value === select.dataset.originalGateway && input.dataset.hasStored === '1' ? 'ثبت شده است؛ برای تغییر مقدار جدید بنویسید' : schema.labels[field];
      }
    });
  };

  select.addEventListener('change', () => {
    drafts.set(previous, readValues());
    const draft = drafts.get(select.value) || {};
    rows.forEach((row) => {
      const field = row.dataset.pcAuthField;
      const input = row.querySelector('input, textarea');
      input.value = input.type === 'password' ? '' : (draft[field] ?? (field === 'message' ? 'کد تأیید شما: {otp}' : ''));
    });
    previous = select.value;
    render();
  });

  render();
})();
