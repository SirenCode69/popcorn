(() => {
    const page = document.querySelector('[data-schedule]');
    if (!page) return;
    const list = page.querySelector('[data-schedule-tabs]');
    const tabs = Array.from(page.querySelectorAll('[data-schedule-day]'));
    const panels = Array.from(page.querySelectorAll('[data-schedule-panel]'));
    const dayFromUrl = () => {
        const value = new URL(location.href).searchParams.get('schedule_day');
        return /^[0-6]$/.test(value ?? '') ? Number(value) : null;
    };
    let today = new Date().getDay();
    try {
        const zone = page.dataset.timezone;
        const date = new Date();
        if (/^[+-]\d{2}:\d{2}$/.test(zone)) {
            const sign = zone.startsWith('-') ? -1 : 1;
            const offset = sign * (Number(zone.slice(1, 3)) * 60 + Number(zone.slice(4, 6)));
            today = new Date(date.getTime() + offset * 60000).getUTCDay();
        } else {
            const weekday = new Intl.DateTimeFormat('en-US', {timeZone: zone, weekday: 'short'}).format(date);
            today = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(weekday);
        }
    } catch (_) {}
    today = (today + 1) % 7;
    list.setAttribute('role', 'tablist');
    tabs.forEach(tab => {
        tab.setAttribute('role', 'tab');
        tab.querySelector('[data-schedule-today]').hidden = Number(tab.dataset.scheduleDay) !== today;
    });
    panels.forEach(panel => { panel.setAttribute('role', 'tabpanel'); panel.tabIndex = 0; });
    const select = (day, focus = false) => {
        tabs.forEach(tab => {
            const active = Number(tab.dataset.scheduleDay) === day;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', String(active));
            tab.removeAttribute('aria-current');
            tab.tabIndex = active ? 0 : -1;
            if (active && focus) { tab.focus(); tab.scrollIntoView({block: 'nearest', inline: 'nearest'}); }
        });
        panels.forEach(panel => { panel.hidden = Number(panel.dataset.schedulePanel) !== day; });
    };
    const navigate = (day, focus = false) => {
        select(day, focus);
        const url = new URL(location.href);
        url.searchParams.set('schedule_day', String(day));
        history.pushState(null, '', url);
    };
    tabs.forEach(tab => {
        tab.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            navigate(Number(tab.dataset.scheduleDay));
        });
        tab.addEventListener('keydown', event => {
            const rtl = getComputedStyle(list).direction === 'rtl';
            let target = Number(tab.dataset.scheduleDay);
            if (event.key === 'ArrowRight') target += rtl ? -1 : 1;
            else if (event.key === 'ArrowLeft') target += rtl ? 1 : -1;
            else if (event.key === 'Home') target = 0;
            else if (event.key === 'End') target = 6;
            else return;
            event.preventDefault();
            navigate((target + 7) % 7, true);
        });
    });
    window.addEventListener('popstate', () => select(dayFromUrl() ?? today));
    select(dayFromUrl() ?? today);
    const active = tabs.find(tab => tab.classList.contains('is-active'));
    if (active && list.scrollWidth > list.clientWidth) {
        const tabBounds = active.getBoundingClientRect();
        const listBounds = list.getBoundingClientRect();
        list.scrollBy({left: tabBounds.left + tabBounds.width / 2 - listBounds.left - listBounds.width / 2, behavior: 'instant'});
    }
})();
