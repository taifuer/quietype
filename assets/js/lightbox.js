import PhotoSwipeLightbox from '../vendor/photoswipe/photoswipe-lightbox.esm.js';

const images = [...document.querySelectorAll('.article-content img:not(.emoji):not(.avatar):not(.no-lightbox), .photo-frame img')];

if (images.length) {
  const imageSource = (image) => {
    const link = image.closest('a');
    const isPhotoArchive = Boolean(image.closest('.photo-frame'));
    const declaredSource = link?.dataset.pswpSrc;
    const linkedImage = link && /\.(?:avif|gif|jpe?g|png|webp)(?:[?#].*)?$/i.test(link.href);
    const thumbnailReady = !isPhotoArchive && image.complete && image.naturalWidth > 0;
    const thumbnailSource = thumbnailReady ? (image.currentSrc || image.src) : '';
    const width = Number(link?.dataset.pswpWidth) || image.naturalWidth || Number(image.getAttribute('width')) || image.clientWidth || 1200;
    const height = Number(link?.dataset.pswpHeight) || image.naturalHeight || Number(image.getAttribute('height')) || image.clientHeight || Math.round(width * 0.75);

    return {
      src: declaredSource || (linkedImage ? link.href : (image.currentSrc || image.src)),
      msrc: thumbnailSource,
      width,
      height,
      alt: image.alt || '',
      element: thumbnailReady ? image : undefined,
      photoTitle: link?.dataset.photoTitle || '',
      photoMeta: link?.dataset.photoMeta || '',
      photoExif: link?.dataset.photoExif || '',
      photoDevice: link?.dataset.photoDevice || '',
      photoCaption: link?.dataset.photoCaption || '',
      photoOriginal: link?.dataset.photoOriginal || '',
      isPhotoArchive,
    };
  };

  const lightbox = new PhotoSwipeLightbox({
    pswpModule: () => import('../vendor/photoswipe/photoswipe.esm.js'),
    bgOpacity: 0.92,
    wheelToZoom: true,
    zoom: false,
    preload: [1, 1],
    imageClickAction(point) {
      if (this.gestures.supportsTouch) return;
      const slide = this.currSlide;
      if (slide?.isZoomable() && slide.zoomLevels.secondary !== slide.zoomLevels.initial) {
        slide.toggleZoom(point);
      } else if (this.options.clickToCloseNonZoomable) {
        this.close();
      }
    },
    tapAction: false,
    paddingFn: (viewportSize, itemData) => {
      const isMobile = viewportSize.x <= 720;
      const hasPhotoDetails = isMobile && Boolean(
        itemData.photoCaption
        || itemData.photoExif
        || itemData.photoDevice
        || itemData.photoOriginal
      );

      return {
        top: isMobile ? 44 : 48,
        bottom: hasPhotoDetails ? 138 : 48,
        left: isMobile ? 12 : 24,
        right: isMobile ? 12 : 24,
      };
    },
  });

  const historyStateKey = 'quietypeLightbox';
  let historyToken = '';
  let closingFromHistory = false;

  lightbox.on('afterInit', () => {
    const pswp = lightbox.pswp;
    let touchStart = null;
    const isControl = (target) => target instanceof Element && Boolean(target.closest('.pswp__button, .pswp__quietype-caption a'));
    pswp.scrollWrap?.addEventListener('pointerdown', (event) => {
      if (event.pointerType !== 'touch' || isControl(event.target)) return;
      touchStart = { x: event.clientX, y: event.clientY, time: performance.now() };
    }, true);
    pswp.scrollWrap?.addEventListener('pointerup', (event) => {
      if (!touchStart || event.pointerType !== 'touch' || isControl(event.target)) {
        touchStart = null;
        return;
      }
      const distance = Math.hypot(event.clientX - touchStart.x, event.clientY - touchStart.y);
      const duration = performance.now() - touchStart.time;
      touchStart = null;
      if (distance <= 10 && duration <= 600) pswp.element?.classList.toggle('pswp--ui-visible');
    }, true);
    pswp.scrollWrap?.addEventListener('pointercancel', () => {
      touchStart = null;
    }, true);

    historyToken = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    closingFromHistory = false;
    try {
      const currentState = history.state && typeof history.state === 'object' ? history.state : {};
      history.pushState({ ...currentState, [historyStateKey]: historyToken }, '', window.location.href);
    } catch (error) {
      historyToken = '';
    }
  });

  window.addEventListener('popstate', (event) => {
    if (!historyToken || event.state?.[historyStateKey] === historyToken) return;
    closingFromHistory = true;
    const pswp = lightbox.pswp;
    if (!pswp) return;
    if (pswp.opener?.isOpening) {
      const closeAfterOpening = () => {
        pswp.off('openingAnimationEnd', closeAfterOpening);
        pswp.close();
      };
      pswp.on('openingAnimationEnd', closeAfterOpening);
    } else {
      pswp.close();
    }
  });

  lightbox.on('close', () => {
    if (!closingFromHistory && historyToken && history.state?.[historyStateKey] === historyToken) {
      history.back();
    }
  });

  lightbox.on('destroy', () => {
    historyToken = '';
    closingFromHistory = false;
  });

  lightbox.on('contentLoadImage', ({ content }) => {
    if (content.data.isPhotoArchive && content.element) {
      content.element.referrerPolicy = 'no-referrer';
    }
  });

  lightbox.on('loadError', ({ slide }) => {
    if (!slide?.data?.isPhotoArchive || slide.data.quietypeRetried || !slide.data.src) return;
    slide.data.quietypeRetried = true;
    try {
      const retryUrl = new URL(slide.data.src, window.location.href);
      retryUrl.searchParams.set('quietype_retry', Date.now().toString());
      slide.data.src = retryUrl.href;
      requestAnimationFrame(() => lightbox.pswp?.refreshSlideContent(slide.index));
    } catch (error) {
      // Keep PhotoSwipe's native error state when the source is not a valid URL.
    }
  });

  lightbox.addFilter('thumbEl', (thumb, data) => data.element || thumb);
  lightbox.on('uiRegister', () => {
    const zoomBy = (factor) => {
      const { pswp } = lightbox;
      const slide = pswp?.currSlide;
      if (!slide) return;
      const minimum = slide.zoomLevels.initial;
      const maximum = slide.zoomLevels.max;
      const target = Math.min(maximum, Math.max(minimum, slide.currZoomLevel * factor));
      slide.zoomTo(target, { x: pswp.viewportSize.x / 2, y: pswp.viewportSize.y / 2 }, 180);
    };

    lightbox.pswp.ui.registerElement({
      name: 'zoom-out',
      className: 'pswp__button--quietype-zoom',
      order: 7,
      isButton: true,
      title: '缩小图片',
      html: '<svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"></circle><path d="M8 11h6M16 16l4 4"></path></svg>',
      onClick: () => zoomBy(1 / 1.5),
    });
    lightbox.pswp.ui.registerElement({
      name: 'zoom-in',
      className: 'pswp__button--quietype-zoom',
      order: 8,
      isButton: true,
      title: '放大图片',
      html: '<svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"></circle><path d="M8 11h6M11 8v6M16 16l4 4"></path></svg>',
      onClick: () => zoomBy(1.5),
    });
    lightbox.pswp.ui.registerElement({
      name: 'quietype-caption',
      className: 'pswp__quietype-caption',
      order: 9,
      isButton: false,
      appendTo: 'root',
      html: '<div class="pswp__quietype-caption-inner"><div><strong></strong><span></span></div><p></p><small></small><a target="_blank" rel="noopener noreferrer">查看原图 ↗</a></div>',
      onInit: (element, pswp) => {
        const title = element.querySelector('strong');
        const meta = element.querySelector('span');
        const caption = element.querySelector('p');
        const details = element.querySelector('small');
        const original = element.querySelector('a');
        const update = () => {
          const data = pswp.currSlide?.data || {};
          const detailParts = [data.photoExif, data.photoDevice].filter(Boolean);
          title.textContent = data.photoTitle || '';
          meta.textContent = data.photoMeta || '';
          caption.textContent = data.photoCaption || '';
          caption.hidden = !data.photoCaption;
          details.textContent = detailParts.join('  ·  ');
          details.hidden = !detailParts.length;
          original.href = data.photoOriginal || '';
          original.hidden = !data.photoOriginal;
          element.hidden = !data.photoTitle && !data.photoMeta && !data.photoCaption && !detailParts.length && !data.photoOriginal;
        };
        pswp.on('change', update);
        pswp.on('afterSetContent', update);
        pswp.on('contentActivate', update);
        update();
      },
    });
  });
  lightbox.init();

  images.forEach((image, index) => {
    const trigger = image.closest('a') || image;
    if (trigger === image) {
      trigger.tabIndex = 0;
      trigger.setAttribute('role', 'button');
    }
    trigger.setAttribute('aria-label', `${image.alt || '文章图片'}，点击放大`);
    const open = (event) => {
      event.preventDefault();
      lightbox.loadAndOpen(index, images.map(imageSource), { x: event.clientX || 0, y: event.clientY || 0 });
    };
    trigger.addEventListener('click', open);
    if (trigger === image) {
      trigger.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        open(event);
      });
    }
  });
}
