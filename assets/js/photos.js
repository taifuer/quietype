(() => {
  'use strict';

  const yearSections = [...document.querySelectorAll('.photo-year[data-expanded]')];
  if (!yearSections.length) return;

  const yearLinks = [...document.querySelectorAll('.photo-year-index a[aria-controls]')];
  let imageObserver = null;
  const maximumRetries = 2;

  const retrySource = (source, attempt) => {
    try {
      const url = new URL(source, window.location.href);
      url.searchParams.set('quietype_grid_retry', `${Date.now()}-${attempt}`);
      return url.href;
    } catch (error) {
      return source;
    }
  };

  const markLoaded = (image) => {
    image.classList.add('is-photo-loaded');
    image.closest('.photo-frame')?.classList.remove('is-photo-error');
  };

  const markFailed = (image) => {
    image.classList.remove('is-photo-loaded');
    image.closest('.photo-frame')?.classList.add('is-photo-error');
  };

  const watchDirectImage = (image) => {
    if (image.dataset.photoRetryReady === 'true') return;
    image.dataset.photoRetryReady = 'true';
    let source = image.currentSrc || image.src;
    if (!source) return;
    let retries = 0;
    let usedFallback = false;

    const onLoad = () => {
      markLoaded(image);
      image.removeEventListener('load', onLoad);
      image.removeEventListener('error', onError);
    };
    const onError = () => {
      if (retries < maximumRetries) {
        retries += 1;
        image.removeAttribute('srcset');
        window.setTimeout(() => {
          image.src = retrySource(source, retries);
        }, retries * 180);
        return;
      }
      if (!usedFallback && image.dataset.photoFallback) {
        usedFallback = true;
        source = image.dataset.photoFallback;
        retries = 0;
        delete image.dataset.photoFallback;
        image.removeAttribute('srcset');
        image.src = source;
        return;
      }
      markFailed(image);
      image.removeEventListener('load', onLoad);
      image.removeEventListener('error', onError);
    };

    image.addEventListener('load', onLoad);
    image.addEventListener('error', onError);
    if (image.complete) {
      if (image.naturalWidth > 0) onLoad();
      else onError();
    }
  };

  const revealImage = (image) => {
    if (image.dataset.photoLoading === 'true') return;
    const sourceSet = image.dataset.srcset;
    let source = image.dataset.src;
    if (!source) return;
    image.dataset.photoLoading = 'true';
    let retries = 0;
    let usedFallback = false;

    const onLoad = () => {
      markLoaded(image);
      delete image.dataset.src;
      delete image.dataset.srcset;
      delete image.dataset.photoLoading;
      image.removeEventListener('load', onLoad);
      image.removeEventListener('error', onError);
    };
    const onError = () => {
      if (retries < maximumRetries) {
        retries += 1;
        image.removeAttribute('srcset');
        window.setTimeout(() => {
          image.src = retrySource(source, retries);
        }, retries * 180);
        return;
      }
      if (!usedFallback && image.dataset.photoFallback) {
        usedFallback = true;
        source = image.dataset.photoFallback;
        retries = 0;
        delete image.dataset.photoFallback;
        image.removeAttribute('srcset');
        image.src = source;
        return;
      }
      markFailed(image);
      delete image.dataset.photoLoading;
      image.removeEventListener('load', onLoad);
      image.removeEventListener('error', onError);
    };

    image.addEventListener('load', onLoad);
    image.addEventListener('error', onError);
    if (sourceSet) {
      image.srcset = sourceSet;
    }
    image.src = source;
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

  document.querySelectorAll('.photo-frame img[src]:not([data-src])').forEach(watchDirectImage);

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
