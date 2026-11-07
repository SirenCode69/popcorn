document.querySelectorAll('[data-pc-expandable-text]').forEach((section) => {
  const viewport = section.querySelector('.pc-expandable-text__viewport');
  const content = section.querySelector('.pc-expandable-text__content');
  const button = section.querySelector('.pc-expandable-text__toggle');
  const label = button.querySelector('span');
  const lines = Number(section.dataset.lines) || 4;
  let expanded = false;
  let collapsedHeight = 0;

  const update = () => {
    const style = getComputedStyle(content);
    const lineHeight = parseFloat(style.lineHeight) || parseFloat(style.fontSize) * 2.1;
    collapsedHeight = lineHeight * lines;
    const fullHeight = content.getBoundingClientRect().height;
    const collapsible = fullHeight > collapsedHeight + 1;
    button.hidden = !collapsible;
    if (!collapsible) expanded = false;
    button.setAttribute('aria-expanded', String(expanded));
    label.textContent = expanded ? 'کمتر' : 'بیشتر';
    viewport.style.height = `${collapsible && !expanded ? collapsedHeight : fullHeight}px`;
  };

  button.addEventListener('click', () => {
    expanded = !expanded;
    update();
  });
  update();
  if ('ResizeObserver' in window) {
    const observer = new ResizeObserver(update);
    observer.observe(content);
  } else {
    window.addEventListener('resize', update, { passive: true });
  }
  document.fonts?.ready.then(update);
});
