(() => {
  'use strict';

  const yearSections = [...document.querySelectorAll('.photo-year[data-expanded]')];
  if (!yearSections.length) return;

  const yearLinks = [...document.querySelectorAll('.photo-year-index a[aria-controls]')];
  let imageObserver = null;

  const revealImage = (image) => {
    const markLoaded = () => image.classList.add('is-photo-loaded');
    const sourceSet = image.dataset.srcset;
    const source = image.dataset.src;

    image.addEventListener('load', markLoaded, { once: true });
    image.addEventListener('error', markLoaded, { once: true });
    // IntersectionObserver already decides when this image may load. Asking the
    // browser to defer it again can leave Edge's native lazy placeholder tied
    // to the same URL that PhotoSwipe is trying to display.
    image.loading = 'eager';
    if (sourceSet) {
      image.srcset = sourceSet;
      delete image.dataset.srcset;
    }
    if (source) {
      image.src = source;
      delete image.dataset.src;
    }
    if (image.complete && image.naturalWidth > 0) markLoaded();
  };

  if ('IntersectionObserver' in window) {
    imageObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        imageObserver.unobserve(entry.target);
        revealImage(entry.target);
      });
    }, { rootMargin: '900px 0px' });
  }

  const activateYear = (section) => {
    section.querySelectorAll('img[data-src]').forEach((image) => {
      if (imageObserver) imageObserver.observe(image);
      else revealImage(image);
    });
  };

  const deactivateYear = (section) => {
    if (!imageObserver) return;
    section.querySelectorAll('img[data-src]').forEach((image) => imageObserver.unobserve(image));
  };

  const setYearExpanded = (section, expanded) => {
    const grid = section.querySelector('.photo-grid');
    const button = section.querySelector('.photo-year__toggle');
    const labelYear = button?.querySelector('span')?.textContent.trim() || '';
    if (!grid || !button) return;

    section.dataset.expanded = String(expanded);
    grid.hidden = !expanded;
    button.setAttribute('aria-expanded', String(expanded));
    button.setAttribute('aria-label', `${expanded ? '收起' : '展开'} ${labelYear} 年照片`);
    yearLinks
      .filter((link) => link.getAttribute('aria-controls') === grid.id)
      .forEach((link) => link.setAttribute('aria-expanded', String(expanded)));
    if (expanded) activateYear(section);
    else deactivateYear(section);
  };

  yearSections.forEach((section) => {
    const expanded = section.dataset.expanded === 'true';
    setYearExpanded(section, expanded);
    section.querySelector('.photo-year__toggle')?.addEventListener('click', () => {
      setYearExpanded(section, section.dataset.expanded !== 'true');
    });
  });

  yearLinks.forEach((link) => {
    link.addEventListener('click', () => {
      const section = document.getElementById(link.hash.slice(1));
      if (section) setYearExpanded(section, true);
    });
  });

  const revealHashTarget = () => {
    if (!window.location.hash) return;
    const target = document.getElementById(window.location.hash.slice(1));
    const section = target?.classList.contains('photo-year') ? target : target?.closest('.photo-year');
    if (!section) return;
    setYearExpanded(section, true);
    requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
  };

  revealHashTarget();
  window.addEventListener('hashchange', revealHashTarget);
})();
