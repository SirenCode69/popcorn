document.querySelectorAll('.pc-comments[data-vote-url]').forEach((section) => {
  const groups = [...section.querySelectorAll('[data-comment-votes]')];
  if (!groups.length) return;
  const format = new Intl.NumberFormat('en-US');
  let nonce = '';
  let token = '';
  let loading;

  const request = async (action, values) => {
    const response = await fetch(section.dataset.voteUrl, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      body: new URLSearchParams({ action, post_id: section.dataset.postId, ...values }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.data?.message || 'ثبت رأی انجام نشد. دوباره تلاش کنید.');
    return result.data;
  };

  const update = (id, data) => {
    groups.filter((group) => group.dataset.commentId === String(id)).forEach((group) => {
      group.querySelectorAll('[data-comment-vote]').forEach((button) => {
        const like = Number(button.dataset.commentVote) === 1;
        const count = format.format(Number(data[like ? 'likes' : 'dislikes']) || 0);
        button.setAttribute('aria-pressed', String(Number(data.selected) === Number(button.dataset.commentVote)));
        button.setAttribute('aria-label', `${like ? 'لایک' : 'دیسلایک'}، ${count} رأی`);
        button.querySelector('[data-vote-count]').textContent = count;
      });
    });
  };

  const initialize = () => {
    if (loading) return loading;
    loading = (async () => {
      const ids = [...new Set(groups.map((group) => group.dataset.commentId))];
      for (let offset = 0; offset < ids.length; offset += 100) {
        const result = await request('popcorn_comment_votes_state', { comment_ids: ids.slice(offset, offset + 100).join(',') });
        nonce = result.nonce;
        token = result.token;
        Object.entries(result.votes || {}).forEach(([id, data]) => update(id, data));
      }
    })().finally(() => { loading = null; });
    return loading;
  };

  const buttons = groups.flatMap((group) => [...group.querySelectorAll('button')]);
  buttons.forEach((button) => { button.disabled = true; });
  initialize().catch((error) => {
    nonce = '';
    token = '';
    groups.forEach((group) => { group.querySelector('[data-comment-vote-status]').textContent = error.message; });
  }).finally(() => buttons.forEach((button) => { button.disabled = false; }));

  section.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-comment-vote]');
    if (!button || !section.contains(button)) return;
    const group = button.closest('[data-comment-votes]');
    const status = group.querySelector('[data-comment-vote-status]');
    if (group.getAttribute('aria-busy') === 'true') return;
    group.setAttribute('aria-busy', 'true');
    const controls = [...group.querySelectorAll('button')];
    controls.forEach((control) => { control.disabled = true; });
    status.textContent = '';
    try {
      if (!nonce || !token) await initialize();
      const vote = button.getAttribute('aria-pressed') === 'true' ? 0 : Number(button.dataset.commentVote);
      const result = await request('popcorn_comment_vote', { nonce, token, comment_id: group.dataset.commentId, vote: String(vote) });
      update(result.comment_id, result.vote);
      status.textContent = vote === 0 ? 'رأی شما برداشته شد.' : 'رأی شما ثبت شد.';
    } catch (error) {
      nonce = '';
      token = '';
      status.textContent = error.message;
    } finally {
      group.removeAttribute('aria-busy');
      controls.forEach((control) => { control.disabled = false; });
    }
  });
});
