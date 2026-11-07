(() => {
  if (!window.wp?.plugins?.registerPlugin || !window.wp?.editor?.PluginSidebar) return;
  const { createElement: h, useEffect, useState } = wp.element;
  const { Button, Notice, Spinner, TextareaControl } = wp.components;
  const { PluginSidebar } = wp.editor;
  const icon = h('svg', { viewBox: '0 0 24 24', width: 24, height: 24, fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true }, h('path', { d: 'M4 5h16v11H9l-5 4V5Z' }), h('path', { d: 'M8 9h8M8 12h6' }));
  const request = (path, method = 'GET', data) => wp.apiFetch({ path, method, data, headers: { 'X-WP-Nonce': window.popcornNoticeSettings?.nonce || '' } });
  const errorText = (error) => error?.message || 'درخواست انجام نشد. دوباره تلاش کنید.';

  function NoticeSidebar() {
    const postId = wp.data.useSelect((select) => select('core/editor')?.getCurrentPostId(), []);
    const status = wp.data.useSelect((select) => select('core/editor')?.getEditedPostAttribute('status'), []);
    const [items, setItems] = useState([]);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [editingId, setEditingId] = useState(0);
    const [editText, setEditText] = useState('');

    useEffect(() => {
      if (!postId) return;
      let active = true;
      setLoading(true);
      request('/popcorn/v1/notices?post_id=' + postId).then((data) => {
        if (!active) return;
        setItems(data.items || []);
        setHasMore(Boolean(data.has_more));
        setPage(1);
        setError('');
      }).catch((err) => { if (active) setError(errorText(err)); }).finally(() => { if (active) setLoading(false); });
      return () => { active = false; };
    }, [postId]);

    const publish = async () => {
      if (!message.trim() || busy || !postId) return;
      setBusy(true);
      setError('');
      try {
        const item = await request('/popcorn/v1/notices', 'POST', { post_id: postId, message });
        setItems((current) => [item, ...current].slice(0, 20));
        setMessage('');
      } catch (err) {
        setError(errorText(err));
      } finally {
        setBusy(false);
      }
    };

    const loadMore = async () => {
      if (loading || !hasMore) return;
      const nextPage = page + 1;
      setLoading(true);
      setError('');
      try {
        const data = await request('/popcorn/v1/notices?post_id=' + postId + '&page=' + nextPage);
        setItems((current) => [...current, ...(data.items || [])]);
        setPage(nextPage);
        setHasMore(Boolean(data.has_more));
      } catch (err) {
        setError(errorText(err));
      } finally {
        setLoading(false);
      }
    };

    const update = async (id, data) => {
      if (busy) return;
      setBusy(true);
      setError('');
      try {
        const updated = await request('/popcorn/v1/notices/' + id, 'PUT', data);
        setItems((current) => current.map((item) => item.id === id ? updated : item));
        setEditingId(0);
      } catch (err) {
        setError(errorText(err));
      } finally {
        setBusy(false);
      }
    };

    const remove = async (id) => {
      if (busy || !window.confirm('این اطلاعیه حذف شود؟')) return;
      setBusy(true);
      setError('');
      try {
        await request('/popcorn/v1/notices/' + id, 'DELETE');
        setItems((current) => current.filter((item) => item.id !== id));
      } catch (err) {
        setError(errorText(err));
      } finally {
        setBusy(false);
      }
    };

    return h(PluginSidebar, { name: 'popcorn-notices', title: 'اطلاعیه‌ها', icon, className: 'pc-notice-sidebar' },
      h('div', { className: 'pc-notice-sidebar__body', dir: 'rtl' },
        h('h2', null, 'اطلاعیه‌های این اثر'),
        h('p', { className: 'pc-notice-sidebar__help' }, 'اطلاعیهٔ تازه بلافاصله ثبت می‌شود و در صفحهٔ همین فیلم یا سریال نمایش داده می‌شود.'),
        status !== 'publish' && h('p', { className: 'pc-notice-sidebar__draft' }, 'نمایش عمومی اطلاعیه پس از انتشار اثر آغاز می‌شود.'),
        error && h(Notice, { status: 'error', isDismissible: true, onRemove: () => setError('') }, error),
        h(TextareaControl, { label: 'متن اطلاعیه', value: message, rows: 4, maxLength: 2000, onChange: setMessage, placeholder: 'مثلاً این هفته به دلیل تعطیلات پخش نمی‌شود.' }),
        h(Button, { variant: 'primary', isBusy: busy, disabled: busy || !message.trim() || !postId, onClick: publish }, 'ارسال اطلاعیه'),
        h('h3', { className: 'pc-notice-sidebar__list-title' }, 'اطلاعیه‌های ثبت‌شده'),
        loading && !items.length && h(Spinner),
        !loading && !items.length && h('p', { className: 'pc-notice-sidebar__empty' }, 'هنوز اطلاعیه‌ای ثبت نشده است.'),
        h('div', { className: 'pc-notice-sidebar__list' }, items.map((item) => h('article', { className: 'pc-notice-sidebar__item', key: item.id },
          h('div', { className: 'pc-notice-sidebar__meta' }, h('time', null, item.created_label), h('span', { className: item.is_active ? 'is-active' : 'is-hidden' }, item.is_active ? 'نمایش عمومی' : 'پنهان')),
          editingId === item.id ? h('div', null,
            h(TextareaControl, { label: 'ویرایش متن', value: editText, rows: 4, maxLength: 2000, onChange: setEditText }),
            h('div', { className: 'pc-notice-sidebar__actions' },
              h(Button, { variant: 'primary', disabled: busy || !editText.trim(), onClick: () => update(item.id, { message: editText }) }, 'ذخیره'),
              h(Button, { variant: 'secondary', onClick: () => setEditingId(0) }, 'انصراف')))
            : h('p', { className: 'pc-notice-sidebar__message' }, item.message),
          editingId !== item.id && h('div', { className: 'pc-notice-sidebar__actions' },
            h(Button, { variant: 'secondary', disabled: busy, onClick: () => { setEditingId(item.id); setEditText(item.message); } }, 'ویرایش'),
            h(Button, { variant: 'secondary', disabled: busy, onClick: () => update(item.id, { is_active: !item.is_active }) }, item.is_active ? 'پنهان کردن' : 'نمایش دادن'),
            h(Button, { variant: 'tertiary', isDestructive: true, disabled: busy, onClick: () => remove(item.id) }, 'حذف'))))),
        hasMore && h(Button, { variant: 'secondary', disabled: loading, onClick: loadMore }, loading ? 'در حال دریافت…' : 'نمایش اطلاعیه‌های بیشتر')));
  }

  wp.plugins.registerPlugin('popcorn-notices-sidebar', { render: NoticeSidebar, icon });
})();
