(() => {
  'use strict';

  const article = document.querySelector('.article-content');
  if (!article) return;
  const root = document.documentElement;
  const toc = document.querySelector('.article-toc');
  const inlineToc = document.querySelector('.mobile-toc');
  const desktopLinks = [...document.querySelectorAll('.article-toc a')];
  const headings = desktopLinks.map((link) => document.getElementById(decodeURIComponent(link.hash.slice(1))));
  const compact = matchMedia('(max-width: 1380px)');
  let activeId = '';
  let scheduled = false;
  let recentJump;
  let trigger;
  let dialog;

  article.querySelectorAll('.article-table').forEach((table) => {
    const updateTable = () => {
      const overflow = table.scrollWidth > table.clientWidth + 1;
      table.tabIndex = overflow ? 0 : -1;
      table.setAttribute('aria-label', overflow ? '数据表格，可横向滚动' : '数据表格');
    };
    updateTable();
    if ('ResizeObserver' in window) new ResizeObserver(updateTable).observe(table);
  });

  const revealIn = (container, item) => {
    if (!container || !item || container.clientHeight === 0) return;
    const bounds = container.getBoundingClientRect();
    const target = item.getBoundingClientRect();
    if (target.top < bounds.top + 8) container.scrollTop += target.top - bounds.top - 8;
    else if (target.bottom > bounds.bottom - 8) container.scrollTop += target.bottom - bounds.bottom + 8;
  };

  const setActive = (id) => {
    if (id === activeId) return;
    activeId = id;
    document.querySelectorAll('.article-toc a, .mobile-toc a, .toc-dialog nav a').forEach((link) => {
      const active = decodeURIComponent(link.hash.slice(1)) === id;
      link.classList.toggle('active', active);
      if (active) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
    revealIn(toc?.querySelector('nav'), toc?.querySelector('a.active'));
  };

  const jump = (event) => {
    const link = event.target.closest('a[href^="#"]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button > 0) return;
    const heading = document.getElementById(decodeURIComponent(link.hash.slice(1)));
    if (!heading) return;
    event.preventDefault();
    if (inlineToc) inlineToc.open = false;
    if (dialog?.open) {
      dialog.close();
      root.classList.remove('reading-dialog-open');
    }
    history.pushState(null, '', link.hash);
    requestAnimationFrame(() => {
      heading.setAttribute('tabindex', '-1');
      heading.focus({ preventScroll: true });
      recentJump = { heading, until: performance.now() + 1500 };
      heading.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
      setActive(heading.id);
    });
  };
  inlineToc?.addEventListener('click', jump);
  toc?.addEventListener('click', jump);

  if (desktopLinks.length) {
    const tooltip = document.createElement('div');
    tooltip.className = 'toc-tooltip';
    tooltip.hidden = true;
    tooltip.setAttribute('aria-hidden', 'true');
    document.body.append(tooltip);
    const hideTooltip = () => { tooltip.hidden = true; };
    desktopLinks.forEach((link) => {
      const showTooltip = () => {
        if (compact.matches || link.scrollWidth <= link.clientWidth + 1) return;
        tooltip.textContent = link.textContent;
        tooltip.hidden = false;
        const box = link.getBoundingClientRect();
        const width = tooltip.offsetWidth;
        tooltip.style.left = `${Math.max(16, Math.min(box.left, innerWidth - width - 16))}px`;
        tooltip.style.top = `${Math.max(16, Math.min(box.bottom + 6, innerHeight - tooltip.offsetHeight - 16))}px`;
      };
      link.addEventListener('mouseenter', showTooltip);
      link.addEventListener('focus', showTooltip);
      link.addEventListener('mouseleave', hideTooltip);
      link.addEventListener('blur', hideTooltip);
      link.addEventListener('click', hideTooltip);
    });
    window.addEventListener('scroll', hideTooltip, { passive: true });
    toc?.querySelector('nav')?.addEventListener('scroll', hideTooltip, { passive: true });
    window.addEventListener('resize', hideTooltip, { passive: true });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') hideTooltip(); });
  }

  if (inlineToc && 'HTMLDialogElement' in window) {
    trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'tool-button reading-toc-toggle';
    trigger.setAttribute('aria-label', '打开文章目录');
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-controls', 'reading-toc-dialog');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.dataset.label = '目录';
    trigger.hidden = true;
    trigger.innerHTML = '<svg class="quietype-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>';
    document.querySelector('.reading-tools')?.prepend(trigger);
    dialog = document.createElement('dialog');
    dialog.id = 'reading-toc-dialog';
    dialog.className = 'reading-dialog toc-dialog';
    dialog.setAttribute('aria-labelledby', 'reading-toc-title');
    dialog.innerHTML = '<header class="reading-dialog__header"><strong id="reading-toc-title">文章目录</strong><button type="button" class="reading-dialog__close" aria-label="关闭文章目录">×</button></header>';
    dialog.append(inlineToc.querySelector('nav').cloneNode(true));
    document.body.append(dialog);
    dialog.querySelector('nav').addEventListener('click', jump);
    dialog.querySelector('button').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => {
      root.classList.remove('reading-dialog-open');
      trigger.setAttribute('aria-expanded', 'false');
    });
    trigger.addEventListener('click', (event) => {
      if (event.detail > 0) trigger.blur();
      dialog.showModal();
      root.classList.add('reading-dialog-open');
      trigger.setAttribute('aria-expanded', 'true');
      const selected = dialog.querySelector('a.active') || dialog.querySelector('nav a');
      selected?.focus({ preventScroll: true });
      revealIn(dialog.querySelector('nav'), selected);
    });
    compact.addEventListener('change', () => { if (!compact.matches && dialog.open) dialog.close(); });
  }

  const update = () => {
    scheduled = false;
    const headerBottom = document.querySelector('.site-header')?.getBoundingClientRect().bottom || 72;
    let current = headings.find(Boolean);
    for (const heading of headings) {
      if (heading && heading.getBoundingClientRect().top <= headerBottom + 48) current = heading;
    }
    if (current) setActive(current.id);
    if (trigger) trigger.hidden = !compact.matches || inlineToc.getBoundingClientRect().bottom > headerBottom || article.getBoundingClientRect().bottom < headerBottom;
  };
  const schedule = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(update);
  };
  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule, { passive: true });
  window.addEventListener('load', schedule, { once: true });
  // A diagram above the destination can finish rendering just after a jump.
  // Briefly keep that explicit destination aligned; any new user action cancels it.
  for (const type of ['wheel', 'touchstart', 'pointerdown', 'keydown']) {
    window.addEventListener(type, () => { recentJump = null; }, { passive: true });
  }
  if ('ResizeObserver' in window) new ResizeObserver(() => {
    if (recentJump && performance.now() < recentJump.until) {
      const top = recentJump.heading.getBoundingClientRect().top;
      const offset = parseFloat(getComputedStyle(recentJump.heading).scrollMarginTop) || 88;
      if (Math.abs(top - offset) > 2) window.scrollBy({ top: top - offset, behavior: 'instant' });
    }
    schedule();
  }).observe(article);
  update();
})();
