(() => {
  const root = document.documentElement;
  const mode = root.dataset.pcThemeMode || 'both';
  let saved = '';
  if (mode === 'both') {
    try { saved = localStorage.getItem('popcorn-theme') || ''; } catch (error) {}
  }
  root.dataset.pcTheme = mode === 'light' || mode === 'dark' ? mode : (saved === 'light' || saved === 'dark' ? saved : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
  document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('[data-pc-theme-toggle]');
    const update = () => buttons.forEach((button) => {
      const dark = root.dataset.pcTheme === 'dark';
      const label = dark ? 'تغییر به حالت روشن' : 'تغییر به حالت تاریک';
      button.setAttribute('aria-pressed', String(dark));
      button.setAttribute('aria-label', label);
      button.title = label;
    });
    update();
    buttons.forEach((button) => {
      if (mode !== 'both') {
        button.hidden = true;
        return;
      }
      button.addEventListener('click', () => {
        const next = root.dataset.pcTheme === 'dark' ? 'light' : 'dark';
        root.dataset.pcTheme = next;
        try { localStorage.setItem('popcorn-theme', next); } catch (error) {}
        update();
      });
    });
  });
})();
