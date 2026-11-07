(() => {
  const retry = (image) => {
    if (!(image instanceof HTMLImageElement) || image.dataset.httpFallbackUsed !== undefined) return;
    const fallback = image.dataset.httpFallback;
    if (!fallback || !/^https?:\/\//i.test(fallback)) return;
    image.dataset.httpFallbackUsed = '1';
    image.src = fallback;
  };
  document.addEventListener('error', (event) => retry(event.target), true);
  document.querySelectorAll('img[data-http-fallback]').forEach((image) => {
    if (image.complete && image.naturalWidth === 0) retry(image);
  });
})();
