const popcornPersonBulkInitialize = () => {
  const config = window.popcornPersonBulkTranslation;
  const openButton = document.querySelector('.pc-person-bulk__open');
  const dialog = document.querySelector('.pc-person-bulk');
  const addButton = document.querySelector('.wrap > .page-title-action');
  if (!config || !openButton || !dialog || !addButton) return;
  addButton.after(openButton);
  openButton.hidden = false;
  const start = dialog.querySelector('[data-bulk-start]');
  const pause = dialog.querySelector('[data-bulk-pause]');
  const reset = dialog.querySelector('[data-bulk-reset]');
  const refresh = dialog.querySelector('[data-bulk-refresh]');
  const size = dialog.querySelector('select');
  const status = dialog.querySelector('[data-bulk-status]');
  const errorBox = dialog.querySelector('[data-bulk-error]');
  const progress = dialog.querySelector('progress');
  const percent = dialog.querySelector('[data-bulk-percent]');
  const log = dialog.querySelector('[data-bulk-log]');
  let job = null;
  let running = false;
  let loading = false;
  let stopRequested = false;

  const request = async (mode, extra = {}) => {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), 35000);
    try {
      const response = await fetch(config.url, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams({ action: 'popcorn_person_bulk_translate', nonce: config.nonce, mode, revision: job?.revision ?? -1, ...extra }),
      });
      let data;
      try { data = await response.json(); } catch { data = null; }
      if (!response.ok || !data?.success) {
        const error = new Error(data?.data?.message || 'پاسخ سرور ناموفق بود.');
        error.status = response.status;
        throw error;
      }
      job = data.data.job;
      return data.data;
    } finally {
      window.clearTimeout(timer);
    }
  };

  const render = () => {
    const value = job?.done ? 100 : job?.total ? Math.min(100, Math.floor(job.processed / job.total * 100)) : 0;
    progress.value = value;
    percent.textContent = value + '%';
    dialog.querySelectorAll('[data-bulk-count]').forEach((element) => { element.textContent = String(job?.[element.dataset.bulkCount] ?? 0); });
    size.disabled = running || Boolean(job && !job.done);
    if (job && !job.done) size.value = String(job.size);
    start.disabled = loading || running;
    start.textContent = job && !job.done ? 'ادامهٔ ترجمه' : 'شروع ترجمه';
    pause.hidden = !running;
    pause.disabled = stopRequested;
    reset.hidden = !job || running;
    refresh.hidden = !job?.processed || running;
    reset.disabled = loading;
    dialog.setAttribute('aria-busy', running || loading ? 'true' : 'false');
    if (job?.done) {
      status.textContent = job.total ? 'بررسی همهٔ هنرمندان تمام شد.' : 'هنرمندی برای بررسی وجود ندارد.';
    } else if (job?.current) {
      const field = job.current.field === 'persian_name' ? 'نام فارسی' : 'زندگی‌نامهٔ فارسی';
      status.textContent = (stopRequested ? 'در حال توقف؛ پایان درخواست جاری… ' : running ? 'در حال ترجمه: ' : 'متوقف شده: ') + job.current.name + ' — ' + field + '، بخش ' + job.current.chunk;
    } else if (job) {
      status.textContent = (running ? 'در حال بررسی ' : 'آمادهٔ ادامه؛ ') + job.processed + ' از ' + job.total + ' هنرمند — دسته‌های ' + job.size + 'تایی';
    }
    const rows = (job?.logs ?? []).slice().reverse().map((entry) => {
      const row = document.createElement('tr');
      row.dataset.result = entry.status;
      const nameCell = document.createElement('td');
      if (entry.post_id) {
        const link = document.createElement('a');
        const url = new URL('post.php', config.url);
        url.searchParams.set('post', entry.post_id);
        url.searchParams.set('action', 'edit');
        link.href = url.href;
        link.textContent = entry.name;
        nameCell.append(link);
      } else {
        nameCell.textContent = entry.name;
      }
      const message = document.createElement('td');
      message.textContent = entry.message;
      row.append(nameCell, message);
      return row;
    });
    log.replaceChildren(...rows);
  };

  const skipFailedRequest = async () => {
    const deadline = Date.now() + 95000;
    while (Date.now() < deadline) {
      try {
        await request('skip');
        return;
      } catch (error) {
        if (error.status !== 409) throw error;
        status.textContent = 'در انتظار پایان درخواست قبلی؛ سپس ادامهٔ کار…';
        await new Promise((resolve) => window.setTimeout(resolve, 2000));
      }
    }
    throw new Error('درخواست قبلی هنوز در حال اجراست؛ کمی بعد ادامه دهید.');
  };

  const run = async (restart = false) => {
    if (running || loading) return;
    running = true;
    stopRequested = false;
    errorBox.textContent = '';
    render();
    try {
      if (restart || !job || job.done) await request('start', { size: size.value, reset: restart ? '1' : '0' });
      render();
      while (!stopRequested && !job.done) {
        try {
          await request('step');
        } catch (error) {
          if (error.status === 403 || error.status === 400 || error.status === 409) throw error;
          errorBox.textContent = 'خطای ارتباط یا سرور؛ از این هنرمند عبور می‌کنیم.';
          await skipFailedRequest();
        }
        render();
      }
    } catch (error) {
      errorBox.textContent = error.status === 403 ? 'اعتبار دسترسی پایان یافته است؛ صفحه را بازخوانی کنید و ادامه دهید.' : error.message;
    } finally {
      running = false;
      stopRequested = false;
      render();
    }
  };

  openButton.addEventListener('click', async () => {
    dialog.showModal();
    if (running || loading) return;
    loading = true;
    render();
    errorBox.textContent = '';
    try { await request('status'); } catch (error) { errorBox.textContent = error.message; }
    loading = false;
    render();
  });
  start.addEventListener('click', () => run());
  reset.addEventListener('click', () => run(true));
  pause.addEventListener('click', () => { stopRequested = true; render(); });
  dialog.querySelector('[data-bulk-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => { stopRequested = true; openButton.focus(); });
  window.addEventListener('beforeunload', (event) => {
    if (!running) return;
    event.preventDefault();
    event.returnValue = '';
  });
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', popcornPersonBulkInitialize, { once: true });
} else {
  popcornPersonBulkInitialize();
}
