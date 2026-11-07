function popcornStartSliders(root = document) {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function initializeSlider(element) {
    if (typeof Swiper === 'undefined' || !element.classList.contains('swiper') || element.classList.contains('swiper-initialized')) return;
    const hero = element.dataset.pcSlider === 'hero';
    const section = element.closest('.pc-slider-section');
    const navigation = section.querySelector('.pc-slider__navigation');
    const thumbs = [...element.querySelectorAll('[data-slide-index]')];
    const updateControls = function () {
      if (navigation) navigation.hidden = this.isLocked;
      thumbs.forEach((thumb) => {
        const active = Number(thumb.dataset.slideIndex) === this.realIndex;
        thumb.classList.toggle('is-active', active);
        thumb.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
    };
    const options = {
      direction: 'horizontal',
      watchOverflow: true,
      speed: reducedMotion ? 0 : hero ? 650 : 420,
      a11y: {
        enabled: true,
        prevSlideMessage: 'اسلاید قبلی',
        nextSlideMessage: 'اسلاید بعدی',
        firstSlideMessage: 'اولین اسلاید',
        lastSlideMessage: 'آخرین اسلاید',
        slideLabelMessage: 'اسلاید {{index}} از {{slidesLength}}',
      },
      on: { init: updateControls, slideChange: updateControls, lock: updateControls, unlock: updateControls },
    };
    if (hero) {
      options.loop = true;
      options.autoplay = reducedMotion ? false : { delay: 6000, disableOnInteraction: false, pauseOnMouseEnter: true };
      options.keyboard = { enabled: true, onlyInViewport: true };
    } else {
      options.slidesPerView = 'auto';
      options.grabCursor = true;
      options.freeMode = { enabled: true };
      options.spaceBetween = 12;
      options.breakpoints = { 640: { spaceBetween: 16 } };
    }
    if (navigation) {
      options.navigation = { prevEl: section.querySelector('[data-pc-slider-prev]'), nextEl: section.querySelector('[data-pc-slider-next]') };
    }
    const slider = new Swiper(element, options);
    thumbs.forEach((thumb) => thumb.addEventListener('click', () => slider.slideToLoop(Number(thumb.dataset.slideIndex))));
  }

  async function loadSlider(section) {
    if (section.dataset.pcSliderState === 'loading' || section.dataset.pcSliderState === 'loaded') return;
    const slider = section.querySelector('[data-pc-slider]');
    const status = section.querySelector('[data-pc-slider-status]');
    const loading = status.querySelector('[data-pc-slider-loading]');
    const retry = status.querySelector('[data-pc-slider-retry]');
    const empty = section.querySelector('[data-pc-slider-empty]');
    section.dataset.pcSliderState = 'loading';
    section.setAttribute('aria-busy', 'true');
    loading.hidden = false;
    retry.hidden = true;
    try {
      const url = new URL(section.dataset.pcSliderUrl, window.location.href);
      const response = await fetch(url.href, { credentials: 'omit', cache: section.dataset.pcSliderCache === 'plugin' ? 'default' : 'no-store', headers: { Accept: 'application/json' } });
      if (!response.ok) throw new Error('Slider request failed');
      const result = await response.json();
      if (!result || !Number.isInteger(result.count) || typeof result.html !== 'string') throw new Error('Invalid slider response');
      if (result.count) {
        slider.innerHTML = result.html;
        if (result.count > 1) slider.classList.add('swiper');
        initializeSlider(slider);
      } else {
        if (section.hasAttribute('data-pc-slider-hide-empty')) section.hidden = true;
        slider.hidden = true;
        if (empty) empty.hidden = false;
      }
      section.dataset.pcSliderState = 'loaded';
    } catch (error) {
      section.dataset.pcSliderState = 'error';
      loading.hidden = true;
      retry.hidden = false;
    } finally {
      section.removeAttribute('aria-busy');
    }
  }

  root.querySelectorAll('[data-pc-slider]').forEach((element) => {
    if (!element.closest('[data-pc-slider-url]')) initializeSlider(element);
  });
  const asyncSections = [...root.querySelectorAll('[data-pc-slider-url]')].filter((section) => !section.dataset.pcSliderObserved);
  asyncSections.forEach((section) => {
    section.dataset.pcSliderObserved = '1';
    section.querySelector('[data-pc-slider-retry]').addEventListener('click', () => loadSlider(section));
  });
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        observer.unobserve(entry.target);
        loadSlider(entry.target);
      });
    }, { rootMargin: '600px 0px' });
    asyncSections.forEach((section) => observer.observe(section));
  } else {
    asyncSections.forEach(loadSlider);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => popcornStartSliders(), { once: true });
} else {
  popcornStartSliders();
}
document.addEventListener('popcorn:archive-updated', (event) => popcornStartSliders(event.detail.root));
