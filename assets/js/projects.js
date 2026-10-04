(() => {
  document.querySelectorAll('.project-preview img').forEach((image) => {
    const preview = image.closest('.project-preview');
    const unavailable = () => preview.classList.add('is-unavailable');
    image.addEventListener('error', unavailable);
    image.addEventListener('load', () => preview.classList.remove('is-unavailable'));
    // Safari can report complete=true for an unrequested lazy/srcset image.
    // Only recover a missed error event when a request actually finished.
    const finished = performance.getEntriesByName(image.currentSrc || image.src)
      .some((entry) => entry.entryType === 'resource' && entry.responseEnd > 0);
    if (image.complete && !image.naturalWidth && finished) unavailable();
  });
})();
