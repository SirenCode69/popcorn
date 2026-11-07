document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-pc-player]').forEach(function (player) {
    const find = (name) => player.querySelector('[data-player-' + name + ']');
    const video = player.querySelector('video');
    const status = find('status');
    const sourceButtons = player.querySelectorAll('[data-player-source]');
    const speed = find('speed');
    const retry = find('retry');
    const seek = find('seek');
    const volume = find('volume');
    const subtitle = find('subtitle');
    const captions = find('captions');
    const loading = player.querySelector('.pc-player__loading');
    let sources, subtitles;
    try { sources = JSON.parse(player.dataset.sources); subtitles = JSON.parse(player.dataset.subtitles); } catch (error) { return; }
    if (!sources.length) return;
    const read = (key, fallback) => { try { return localStorage.getItem('popcorn-player-' + key) || fallback; } catch (error) { return fallback; } };
    const write = (key, value) => { try { localStorage.setItem('popcorn-player-' + key, value); } catch (error) {} };
    const time = function (value) {
      value = Math.max(0, Math.floor(Number.isFinite(value) ? value : 0));
      const hours = Math.floor(value / 3600);
      return (hours ? hours + ':' : '') + String(Math.floor(value / 60) % 60).padStart(hours ? 2 : 1, '0') + ':' + String(value % 60).padStart(2, '0');
    };
    let index = 0;
    let httpTried = false;
    let compatibleTried = false;
    let pendingTime = Number(player.dataset.resume) || 0;
    let resume = false;
    let changing = false;
    let hideTimer;
    let pane = '';
    const modalBackground = new Map();
    let lastSave = 0;
    let lastPosition = -1;
    let sequence = Date.now();
    let trackLoading;
    let trackReady = false;
    let subtitleGeneration = 0;
    const blobs = [];
    const tracks = [];
    video.volume = Math.max(0, Math.min(1, Number(read('volume', '1')) || 0));
    const savedSpeed = read('speed', '1');
    if (Array.from(speed.options).some((option) => option.value === savedSpeed)) speed.value = savedSpeed;
    const saveProgress = function (urgent) {
      if (!player.dataset.progressUrl || changing || !Number.isFinite(video.duration) || video.duration <= 0 || video.readyState < 1) return;
      if (!urgent && (Date.now() - lastSave < 10000 || Math.abs(video.currentTime - lastPosition) < 1)) return;
      if (Math.abs(video.currentTime - lastPosition) < .1) return;
      lastSave = Date.now();
      lastPosition = video.currentTime;
      sequence = Math.max(sequence + 1, Date.now());
      const data = new URLSearchParams({action:'popcorn_playback_progress',nonce:player.dataset.progressNonce,playback:player.dataset.playback,position:String(Math.min(video.currentTime, video.duration)),duration:String(video.duration),sequence:String(sequence)});
      if (urgent && navigator.sendBeacon) {
        if (navigator.sendBeacon(player.dataset.progressUrl, data)) return;
      }
      fetch(player.dataset.progressUrl, {method:'POST',body:data,credentials:'same-origin',keepalive:urgent}).catch(function () {});
    };
    const show = function () {
      player.classList.remove('pc-player--idle');
      clearTimeout(hideTimer);
      if (!video.paused && !pane) hideTimer = setTimeout(function () {
        if (!player.contains(document.activeElement) || document.activeElement === player) player.classList.add('pc-player--idle');
      }, 3000);
    };
    const update = function () {
      player.classList.toggle('pc-player--playing', !video.paused);
      player.classList.toggle('pc-player--muted', video.muted || video.volume === 0);
      find('play').setAttribute('aria-label', video.paused ? 'پخش' : 'توقف');
      find('play').title = video.paused ? 'پخش' : 'توقف';
      const muteLabel = video.muted || video.volume === 0 ? 'وصل صدا' : 'قطع صدا';
      find('mute').setAttribute('aria-label', muteLabel);
      find('mute').title = muteLabel;
      volume.value = video.muted ? '0' : String(video.volume);
      find('time').textContent = time(video.currentTime) + ' / ' + time(video.duration);
      seek.disabled = !Number.isFinite(video.duration) || video.duration <= 0;
      const percent = seek.disabled ? 0 : video.currentTime / video.duration * 100;
      seek.value = String(Math.round(percent * 10));
      seek.setAttribute('aria-valuetext', time(video.currentTime) + ' از ' + time(video.duration));
      seek.style.setProperty('--played', percent + '%');
      if (video.buffered.length && !seek.disabled) find('buffer').style.width = Math.min(100, video.buffered.end(video.buffered.length - 1) / video.duration * 100) + '%';
    };
    const play = function () {
      player.focus({preventScroll:true});
      show();
      if (video.paused) {
        video.play().catch(function (error) {
          if (error.name !== 'AbortError' && !video.error) status.textContent = error.name === 'NotAllowedError' ? '' : 'برای شروع پخش دوباره دکمهٔ پخش را بزنید.';
        });
      } else video.pause();
    };
    const load = function (next, url, autoplay) {
      const position = Number.isFinite(video.currentTime) ? video.currentTime : 0;
      pendingTime = pendingTime || position;
      resume = autoplay;
      changing = true;
      index = next;
      sourceButtons.forEach((button) => button.setAttribute('aria-pressed', String(Number(button.dataset.playerSource) === next)));
      retry.hidden = true;
      loading.hidden = false;
      video.src = url || sources[next].url;
      video.load();
    };
    const jump = function (seconds) {
      if (Number.isFinite(video.duration)) video.currentTime = Math.max(0, Math.min(video.duration, video.currentTime + seconds));
      show();
    };
    const closePanes = function () {
      const restoreFocus = pane === 'episodes' || pane === 'quality' ? pane : '';
      player.querySelectorAll('[data-player-pane]').forEach((panel) => { panel.hidden = true; });
      player.querySelectorAll('[data-player-panel]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
      modalBackground.forEach((inert, element) => { element.inert = inert; });
      modalBackground.clear();
      pane = '';
      if (restoreFocus) player.querySelector('[data-player-panel="' + restoreFocus + '"]')?.focus({preventScroll:true});
      show();
    };
    player.querySelectorAll('[data-player-panel]').forEach(function (button) {
      button.addEventListener('click', function () {
        const name = button.dataset.playerPanel;
        const open = pane !== name;
        closePanes();
        if (open) {
          pane = name;
          const panel = player.querySelector('[data-player-pane="' + name + '"]');
          panel.hidden = false;
          button.setAttribute('aria-expanded', 'true');
          if (name === 'quality') {
            const bar = player.querySelector('.pc-player__bottom');
            panel.style.bottom = (player.getBoundingClientRect().bottom - bar.getBoundingClientRect().top + 10) + 'px';
          }
          if (name === 'episodes') {
            Array.from(player.children).forEach(function (element) {
              if (element === panel) return;
              modalBackground.set(element, element.inert);
              element.inert = true;
            });
          }
          const focusTarget = panel.querySelector('[data-player-source][aria-pressed="true"]') || panel.querySelector('button,select,input,a');
          focusTarget?.focus({preventScroll:true});
          show();
        }
      });
    });
    player.addEventListener('click', function (event) {
      if (!event.target.closest('[data-player-pane], [data-player-panel]')) closePanes();
    });
    player.querySelectorAll('[data-player-close-pane]').forEach((button) => button.addEventListener('click', closePanes));
    find('play').addEventListener('click', play);
    find('center').addEventListener('click', play);
    video.addEventListener('click', play);
    find('rewind').addEventListener('click', () => jump(-10));
    find('forward').addEventListener('click', () => jump(10));
    find('mute').addEventListener('click', function () {
      video.muted = !video.muted;
      if (!video.muted && video.volume === 0) video.volume = .5;
    });
    volume.addEventListener('input', function () {
      video.volume = Number(volume.value);
      video.muted = video.volume === 0;
      write('volume', volume.value);
    });
    seek.addEventListener('input', function () {
      if (Number.isFinite(video.duration)) video.currentTime = Number(seek.value) / 1000 * video.duration;
    });
    sourceButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        const next = Number(button.dataset.playerSource);
        if (!Number.isInteger(next) || !sources[next]) return;
        if (next !== index) {
          saveProgress(true);
          httpTried = false;
          compatibleTried = false;
          status.textContent = '';
          load(next, '', !video.paused);
        }
        closePanes();
      });
    });
    speed.addEventListener('change', function () {
      video.playbackRate = Number(speed.value);
      write('speed', speed.value);
    });
    find('restart')?.addEventListener('click', function () {
      pendingTime = 0;
      if (video.readyState >= 1) video.currentTime = 0;
      status.textContent = '';
      find('restart').hidden = true;
    });
    video.addEventListener('loadedmetadata', function () {
      if (sources[index].format === 'mkv' && !video.videoWidth && !video.videoHeight) {
        video.pause();
        handleFailure(4);
        return;
      }
      if (pendingTime > 0 && Number.isFinite(video.duration)) video.currentTime = Math.min(pendingTime, Math.max(0, video.duration - .1));
      pendingTime = 0;
      changing = false;
      loading.hidden = true;
      video.playbackRate = Number(speed.value);
      if (resume) video.play().catch(function () {});
      resume = false;
      retry.hidden = true;
      update();
    });
    const handleFailure = function (code) {
      changing = false;
      loading.hidden = true;
      const compatible = sources.findIndex((source, position) => position !== index && source.format === 'mp4');
      if ((code === 3 || code === 4 || code === 2) && compatible >= 0 && !compatibleTried) {
        compatibleTried = true;
        httpTried = false;
        status.textContent = 'در حال استفاده از نسخهٔ جایگزین MP4…';
        load(compatible, '', true);
        return;
      }
      if ((code === 2 || code === 4) && sources[index].httpFallback && !httpTried && location.protocol !== 'https:') {
        httpTried = true;
        status.textContent = 'در حال بررسی اتصال جایگزین…';
        load(index, sources[index].httpFallback, true);
        return;
      }
      status.textContent = code === 3 || code === 4 ? 'این فایل در مرورگر شما پخش نمی‌شود. نسخهٔ MP4 یا مرورگر دیگری را امتحان کنید.' : 'فایل پخش در دسترس نیست یا اعتبار لینک تمام شده است. دوباره تلاش کنید یا صفحه را تازه‌سازی کنید.';
      retry.hidden = false;
      show();
    };
    video.addEventListener('error', function () { handleFailure(video.error?.code); });
    retry.addEventListener('click', function () {
      httpTried = false;
      compatibleTried = false;
      status.textContent = '';
      load(index, '', true);
    });
    ['play','pause','volumechange','durationchange','progress'].forEach((event) => video.addEventListener(event, update));
    video.addEventListener('play', show);
    video.addEventListener('pause', function () { show(); saveProgress(true); });
    video.addEventListener('timeupdate', function () { update(); saveProgress(false); });
    video.addEventListener('waiting', function () { loading.hidden = false; });
    video.addEventListener('playing', function () { player.classList.add('pc-player--started'); status.textContent = ''; loading.hidden = true; show(); });
    video.addEventListener('canplay', function () { loading.hidden = true; });
    video.addEventListener('ended', function () {
      player.classList.remove('pc-player--started');
      saveProgress(true);
      if (player.dataset.next && find('auto-next')?.checked) location.assign(player.dataset.next);
    });
    const full = async function () {
      try {
        if (document.fullscreenElement) await document.exitFullscreen();
        else if (player.requestFullscreen) await player.requestFullscreen();
        else if (video.webkitEnterFullscreen) video.webkitEnterFullscreen();
      } catch (error) { status.textContent = 'تمام‌صفحه در این مرورگر در دسترس نیست.'; }
    };
    find('fullscreen').addEventListener('click', full);
    document.addEventListener('fullscreenchange', function () { find('fullscreen').setAttribute('aria-label', document.fullscreenElement ? 'خروج از تمام‌صفحه' : 'تمام‌صفحه'); });
    const pip = find('pip');
    if (document.pictureInPictureEnabled && video.requestPictureInPicture) {
      pip.hidden = false;
      pip.addEventListener('click', async function () {
        try {
          if (document.pictureInPictureElement) await document.exitPictureInPicture();
          else if (video.readyState >= 1) await video.requestPictureInPicture();
          else status.textContent = 'ابتدا پخش ویدیو را شروع کنید.';
        } catch (error) { status.textContent = 'تصویر در تصویر برای این فایل در دسترس نیست.'; }
      });
    }
    const autoNext = find('auto-next');
    if (autoNext) {
      autoNext.checked = read('auto-next', '1') === '1';
      autoNext.addEventListener('change', () => write('auto-next', autoNext.checked ? '1' : '0'));
    }
    const renderCaptions = function () {
      captions.replaceChildren();
      tracks.forEach(function (track) {
        if (track.track.mode !== 'hidden') return;
        Array.from(track.track.activeCues || []).forEach(function (cue) {
          const line = document.createElement('span');
          line.dir = 'auto';
          line.textContent = cue.text.replace(/<[^>]+>/g, '');
          captions.append(line);
        });
      });
    };
    const loadTracks = function () {
      if (trackLoading) return trackLoading;
      trackLoading = Promise.allSettled(subtitles.map(async function (item) {
        const response = await fetch(item.url, {credentials:'same-origin'});
        if (!response.ok) throw new Error('subtitle');
        let text = (await response.text()).replace(/^\uFEFF/, '').replace(/\r/g, '');
        if (!/^WEBVTT/.test(text)) text = 'WEBVTT\n\n' + text.replace(/(\d{2}:\d{2}:\d{2}),(\d{3})/g, '$1.$2');
        const url = URL.createObjectURL(new Blob([text], {type:'text/vtt'}));
        blobs.push(url);
        const track = document.createElement('track');
        track.kind = 'subtitles';
        track.label = item.label;
        track.srclang = item.language;
        track.src = url;
        const ready = new Promise(function (resolve, reject) {
          track.addEventListener('load', resolve, {once:true});
          track.addEventListener('error', reject, {once:true});
        });
        video.append(track);
        track.track.mode = 'hidden';
        await ready;
        track.track.mode = 'disabled';
        track.track.addEventListener('cuechange', renderCaptions);
        return track;
      })).then(function (results) {
        if (results.some((result) => result.status === 'rejected')) throw new Error('subtitle');
        tracks.push(...results.map((result) => result.value)); trackReady = true;
      }).catch(function (error) {
        player.querySelectorAll('track').forEach((track) => track.remove());
        blobs.splice(0).forEach((url) => URL.revokeObjectURL(url));
        trackLoading = null;
        throw error;
      });
      return trackLoading;
    };
    const setSubtitle = async function () {
      const generation = ++subtitleGeneration;
      if (subtitle.value === 'off') {
        tracks.forEach((track) => { track.track.mode = 'disabled'; });
        captions.replaceChildren();
        write('subtitle', 'off');
        status.textContent = '';
        return;
      }
      try {
        status.textContent = 'در حال دریافت زیرنویس…';
        if (!trackReady) await loadTracks();
        if (generation !== subtitleGeneration) return;
        tracks.forEach((track) => { track.track.mode = 'disabled'; });
        if (subtitle.value === 'both') {
          ['fa','en'].forEach(function (language) {
            const track = tracks.find((entry) => entry.srclang === language);
            if (track) track.track.mode = 'hidden';
          });
        } else if (tracks[Number(subtitle.value)]) tracks[Number(subtitle.value)].track.mode = 'hidden';
        write('subtitle', subtitle.value === 'both' ? 'both' : subtitles[Number(subtitle.value)]?.language || 'off');
        status.textContent = '';
        renderCaptions();
      } catch (error) { status.textContent = 'دریافت زیرنویس انجام نشد. دوباره انتخاب کنید.'; }
    };
    if (subtitle) {
      subtitle.addEventListener('change', setSubtitle);
      const size = find('subtitle-size');
      const color = find('subtitle-color');
      const sizes = {small:'.8',medium:'1',large:'1.25',xlarge:'1.5'};
      const colors = {white:'#fff',yellow:'#ffe66d',lime:'#b5ee66',accent:'var(--pc-accent)'};
      const preferences = function () {
        captions.style.setProperty('--subtitle-size', sizes[size.value] || '1');
        captions.style.setProperty('--subtitle-color', colors[color.value] || '#fff');
        write('subtitle-size', size.value);
        write('subtitle-color', color.value);
      };
      size.value = sizes[read('subtitle-size', '')] ? read('subtitle-size', '') : 'medium';
      color.value = colors[read('subtitle-color', '')] ? read('subtitle-color', '') : 'white';
      size.addEventListener('change', preferences);
      color.addEventListener('change', preferences);
      preferences();
      video.addEventListener('loadedmetadata', function () {
        if (subtitle.value !== 'off') return;
        const saved = read('subtitle', 'off');
        const at = subtitles.findIndex((entry) => entry.language === saved);
        if (saved === 'both' && Array.from(subtitle.options).some((option) => option.value === 'both')) subtitle.value = 'both';
        else if (at >= 0) subtitle.value = String(at);
        if (subtitle.value !== 'off') setSubtitle();
      }, {once:true});
    }
    ['mousemove','pointerdown','focusin'].forEach((event) => player.addEventListener(event, show));
    player.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && pane) { event.preventDefault(); closePanes(); return; }
      if (pane === 'episodes') {
        if (event.key === 'Tab') {
          const panel = player.querySelector('[data-player-pane="episodes"]');
          const controls = Array.from(panel.querySelectorAll('button,a[href]'));
          const first = controls[0];
          const last = controls[controls.length - 1];
          if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) { event.preventDefault(); last?.focus(); }
          else if (!event.shiftKey && (document.activeElement === last || document.activeElement === panel)) { event.preventDefault(); first?.focus(); }
        }
        return;
      }
      if (event.target.closest('input,select,button,a') || event.altKey || event.ctrlKey || event.metaKey) return;
      const key = event.key.toLowerCase();
      if ([' ','k','arrowleft','arrowright','arrowup','arrowdown','f','m'].includes(key)) event.preventDefault();
      if (key === ' ' || key === 'k') play();
      if (key === 'arrowleft') jump(-5);
      if (key === 'arrowright') jump(5);
      if (key === 'arrowup') video.volume = Math.min(1, video.volume + .05);
      if (key === 'arrowdown') video.volume = Math.max(0, video.volume - .05);
      if (key === 'm') video.muted = !video.muted;
      if (key === 'f') full();
      show();
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) saveProgress(true); });
    window.addEventListener('pagehide', function () { saveProgress(true); });
    player.classList.add('pc-player--ready');
    video.controls = false;
    if (pendingTime > 0) status.textContent = 'ادامهٔ تماشا از ' + time(pendingTime);
    update();
    find('center').click();
  });
});
