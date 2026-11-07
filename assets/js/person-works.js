document.querySelectorAll('.pc-person-filmography').forEach((section) => {
  const buttons = section.querySelectorAll('[data-pc-work-filter]');
  const list = section.querySelector('.pc-person-filmography__list');
  const rows = [...section.querySelectorAll('[data-pc-work-type]')];
  const empty = section.querySelector('.pc-person-filmography__empty');
  const pagination = section.querySelector('.pc-person-filmography__pagination');
  const pages = section.querySelector('[data-pc-work-pages]');
  const previous = section.querySelector('[data-pc-work-previous]');
  const next = section.querySelector('[data-pc-work-next]');
  const status = section.querySelector('[data-pc-work-status]');
  const perPage = Math.max(1, Number(section.dataset.perPage) || 10);
  const numbers = new Intl.NumberFormat('fa-IR');
  let filter = 'all';
  let page = 1;

  const render = (announce = true) => {
    const matches = rows.filter((row) => filter === 'all' || row.dataset.pcWorkType === filter);
    const total = Math.max(1, Math.ceil(matches.length / perPage));
    page = Math.min(page, total);
    const visible = matches.slice((page - 1) * perPage, page * perPage);
    rows.forEach((row) => {
      row.hidden = !visible.includes(row);
      row.classList.toggle('is-first-visible', row === visible[0]);
    });
    list.hidden = matches.length === 0;
    empty.hidden = matches.length > 0;
    pagination.hidden = total <= 1;
    previous.disabled = page <= 1;
    next.disabled = page >= total;
    pages.replaceChildren();
    let lastNumber = 0;
    for (let number = 1; number <= total; number += 1) {
      if (total > 7 && number !== 1 && number !== total && Math.abs(number - page) > 1) continue;
      if (lastNumber && number - lastNumber > 1) {
        const gap = document.createElement('span');
        gap.textContent = '…';
        gap.setAttribute('aria-hidden', 'true');
        pages.append(gap);
      }
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = numbers.format(number);
      button.dataset.pcWorkPage = String(number);
      button.setAttribute('aria-label', `صفحهٔ ${numbers.format(number)}`);
      if (number === page) button.setAttribute('aria-current', 'page');
      pages.append(button);
      lastNumber = number;
    }
    if (announce) status.textContent = matches.length ? `صفحهٔ ${numbers.format(page)} از ${numbers.format(total)}، ${numbers.format(visible.length)} اثر` : 'اثری در این بخش ثبت نشده است.';
  };

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      filter = button.dataset.pcWorkFilter;
      page = 1;
      buttons.forEach((option) => {
        const active = option === button;
        option.classList.toggle('is-active', active);
        option.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      render();
    });
  });
  previous.addEventListener('click', () => {
    page -= 1;
    render();
    if (previous.disabled) next.focus({ preventScroll: true });
  });
  next.addEventListener('click', () => {
    page += 1;
    render();
    if (next.disabled) previous.focus({ preventScroll: true });
  });
  pages.addEventListener('click', (event) => {
    const button = event.target.closest('[data-pc-work-page]');
    if (!button) return;
    page = Number(button.dataset.pcWorkPage);
    render();
    pages.querySelector('[aria-current="page"]').focus({ preventScroll: true });
  });
  render(false);
});
