(() => {
  const root = document.querySelector('.pc-downloads');
  if (!root) return;
  let nextIndex = Date.now();
  const insert = (templateId, list, values) => {
    const template = root.querySelector('#pc-download-' + templateId + '-template');
    if (!template || !list) return;
    let html = template.innerHTML;
    for (const [key, value] of Object.entries(values)) html = html.replaceAll(key, String(value));
    const holder = document.createElement('div');
    holder.innerHTML = html.trim();
    const node = holder.firstElementChild;
    if (node) {
      list.append(node);
      node.querySelector('input')?.focus();
    }
    return node;
  };
  root.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    if (!button || !root.contains(button)) return;
    const node = button.closest('.pc-downloads__node');
    if (button.classList.contains('pc-downloads__remove')) {
      node?.remove();
      return;
    }
    if (button.classList.contains('pc-downloads__up')) {
      const previous = node?.previousElementSibling;
      if (previous?.classList.contains('pc-downloads__node')) previous.before(node);
      return;
    }
    if (button.classList.contains('pc-downloads__down')) {
      const next = node?.nextElementSibling;
      if (next?.classList.contains('pc-downloads__node')) next.after(node);
      return;
    }
    if (!button.classList.contains('pc-downloads__add')) return;
    const kind = button.dataset.add;
    const group = button.closest('.pc-downloads__group');
    const season = button.closest('.pc-downloads__season');
    const list = kind === 'group' ? root.querySelector('.pc-downloads__list--groups') : button.parentElement.querySelector(':scope > .pc-downloads__list');
    const newNode = insert(kind, list, { __GROUP__: kind === 'group' ? nextIndex++ : group?.dataset.groupIndex, __SEASON__: kind === 'season' ? nextIndex++ : season?.dataset.seasonIndex, __LINK__: nextIndex++ });
    if (kind === 'season' && newNode) {
      const numbers = [...list.querySelectorAll(':scope > .pc-downloads__season input[name$="[season_number]"]')].slice(0, -1).map((field) => Number(field.value) || 0);
      newNode.querySelector('input[name$="[season_number]"]').value = String(Math.min(999, Math.max(0, ...numbers) + 1));
    }
  });
})();
