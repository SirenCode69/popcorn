document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.pc-header');
  if (!header) return;
  const nav = header.querySelector('.pc-header__nav');
  const dock = document.querySelector('.pc-mobile-dock');
  const genreButton = dock?.querySelector('.pc-mobile-dock__genre-toggle');
  const genrePanel = document.querySelector('.pc-mobile-genre-panel');
  const accountButton = dock?.querySelector('.pc-mobile-dock__account-toggle');
  const accountPanel = document.querySelector('.pc-mobile-account-panel');
  const searchWrap = header.querySelector('.pc-header__search-wrap');
  const searchButton = header.querySelector('.pc-header__search-toggle');
  const searchInput = header.querySelector('.pc-search input[type="search"]');
  const searchClear = header.querySelector('.pc-search__clear');
  const adminBar = document.getElementById('wpadminbar');
  const mobileMedia = window.matchMedia('(max-width: 900px)');
  const updateOffsets = () => {
    const barHeight = adminBar && getComputedStyle(adminBar).display !== 'none'
      ? Math.max(0, Math.min(adminBar.getBoundingClientRect().bottom, adminBar.getBoundingClientRect().height))
      : 0;
    const adminValue = `${Math.round(barHeight)}px`;
    if (document.body.style.getPropertyValue('--pc-admin-offset') !== adminValue) {
      document.body.style.setProperty('--pc-admin-offset', adminValue);
    }
  };
  const updateLayout = () => {
    header.classList.toggle('is-scrolled', window.scrollY > 12);
    updateOffsets();
  };
  updateLayout();
  window.addEventListener('scroll', updateLayout, { passive: true });
  window.addEventListener('resize', updateOffsets, { passive: true });
  const closeMenus = () => {
    nav?.querySelectorAll('.pc-nav__menu>li.is-open,.pc-block-nav .wp-block-navigation-item.is-open').forEach((item) => {
      item.classList.remove('is-open');
      item.querySelector(':scope > .pc-menu-toggle')?.setAttribute('aria-expanded', 'false');
    });
  };
  const setGenreOpen = (open) => {
    if (!genrePanel) return;
    genrePanel.hidden = !open;
    genreButton?.setAttribute('aria-expanded', String(open));
    genreButton?.setAttribute('aria-label', open ? 'بستن ژانرها' : 'باز کردن ژانرها');
  };
  const setAccountOpen = (open) => {
    if (!accountPanel) return;
    accountPanel.hidden = !open;
    accountButton?.setAttribute('aria-expanded', String(open));
    accountButton?.setAttribute('aria-label', open ? 'بستن منوی کاربری' : 'باز کردن منوی کاربری');
  };
  const closeSearch = () => {
    searchWrap?.classList.remove('is-open');
    searchButton?.setAttribute('aria-expanded', 'false');
    searchButton?.setAttribute('aria-label', 'باز کردن جست‌وجو');
  };
  mobileMedia.addEventListener('change', () => {
    setGenreOpen(false);
    setAccountOpen(false);
  });
  genreButton?.addEventListener('click', () => {
    const open = genrePanel.hidden;
    setAccountOpen(false);
    if (open) closeSearch();
    setGenreOpen(open);
  });
  accountButton?.addEventListener('click', () => {
    const open = accountPanel.hidden;
    setGenreOpen(false);
    if (open) closeSearch();
    setAccountOpen(open);
  });
  searchButton?.addEventListener('click', () => {
    const open = searchWrap.classList.toggle('is-open');
    if (open) {
      setGenreOpen(false);
      setAccountOpen(false);
    }
    searchButton.setAttribute('aria-expanded', String(open));
    searchButton.setAttribute('aria-label', open ? 'بستن جست‌وجو' : 'باز کردن جست‌وجو');
    if (open) searchInput?.focus();
  });
  searchClear?.addEventListener('click', () => {
    if (!searchInput) return;
    searchInput.value = '';
    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    searchInput.focus();
  });
  nav?.querySelectorAll('.pc-menu-toggle').forEach((button) => button.addEventListener('click', () => {
    const item = button.closest('li');
    const open = !item.classList.contains('is-open');
    closeMenus();
    item.classList.toggle('is-open', open);
    button.setAttribute('aria-expanded', String(open));
  }));
  document.addEventListener('click', (event) => {
    if (!header.contains(event.target) && !dock?.contains(event.target) && !genrePanel?.contains(event.target) && !accountPanel?.contains(event.target)) {
      closeMenus();
      setGenreOpen(false);
      setAccountOpen(false);
      closeSearch();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    closeMenus();
    setGenreOpen(false);
    setAccountOpen(false);
    closeSearch();
  });
});
