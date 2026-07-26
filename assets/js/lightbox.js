import PhotoSwipeLightbox from '../vendor/photoswipe/photoswipe-lightbox.esm.js';

const images = [...document.querySelectorAll('.article-content img:not(.emoji):not(.avatar):not(.no-lightbox), .photo-frame img')];

if (images.length) {
  const imageSource = (image) => {
    const link = image.closest('a');
    const declaredSource = link?.dataset.pswpSrc;
    const linkedImage = link && /\.(?:avif|gif|jpe?g|png|webp)(?:[?#].*)?$/i.test(link.href);
    const width = Number(link?.dataset.pswpWidth) || image.naturalWidth || Number(image.getAttribute('width')) || image.clientWidth || 1200;
    const height = Number(link?.dataset.pswpHeight) || image.naturalHeight || Number(image.getAttribute('height')) || image.clientHeight || Math.round(width * 0.75);

    return {
      src: declaredSource || (linkedImage ? link.href : (image.currentSrc || image.src)),
      msrc: image.currentSrc || image.src,
      width,
      height,
      alt: image.alt || '',
      element: image,
      photoTitle: link?.dataset.photoTitle || '',
      photoMeta: link?.dataset.photoMeta || '',
      photoExif: link?.dataset.photoExif || '',
      photoDevice: link?.dataset.photoDevice || '',
      photoCaption: link?.dataset.photoCaption || '',
      photoOriginal: link?.dataset.photoOriginal || '',
    };
  };

  const lightbox = new PhotoSwipeLightbox({
    pswpModule: () => import('../vendor/photoswipe/photoswipe.esm.js'),
    bgOpacity: 0.92,
    wheelToZoom: true,
    zoom: false,
    paddingFn: () => ({ top: 48, bottom: 48, left: 24, right: 24 }),
  });

  lightbox.addFilter('thumbEl', (thumb, data) => data.element || thumb);
  lightbox.addFilter('placeholderSrc', (src, slide) => slide.data.msrc || src);
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
          details.textContent = detailParts.join('  ·  ');
          original.href = data.photoOriginal || '';
          original.hidden = !data.photoOriginal;
          element.hidden = !data.photoTitle && !data.photoMeta && !data.photoCaption && !detailParts.length && !data.photoOriginal;
        };
        pswp.on('change', update);
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
