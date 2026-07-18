(() => {
  'use strict';

  const root = document.documentElement;
  const nav = document.querySelector('.site-nav');
  const navToggle = document.querySelector('.nav-toggle');
  const navBackdrop = document.querySelector('.nav-backdrop');
  const topButton = document.querySelector('.top-button');
  const search = document.querySelector('.site-search');
  const searchToggle = document.querySelector('.search-toggle');
  const backgroundToggle = document.querySelector('.reading-background__toggle');
  const backgroundOptions = document.querySelector('.reading-background__options');
  const backgrounds = new Set(['paper', 'warm', 'green', 'gray']);

  const setExpanded = (button, expanded) => {
    if (button) button.setAttribute('aria-expanded', String(expanded));
  };

  const setNav = (open) => {
    if (!nav || !navToggle) return;
    nav.classList.toggle('open', open);
    document.body.classList.toggle('nav-open', open);
    navBackdrop?.toggleAttribute('hidden', !open);
    navToggle.textContent = open ? '关闭' : '菜单';
    setExpanded(navToggle, open);
  };

  if (navToggle && nav) {
    navToggle.addEventListener('click', () => setNav(!nav.classList.contains('open')));
    navBackdrop?.addEventListener('click', () => setNav(false));
    nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setNav(false)));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') setNav(false);
    });
  }

  if (searchToggle && search) {
    searchToggle.addEventListener('click', () => {
      const open = search.hasAttribute('hidden');
      search.toggleAttribute('hidden', !open);
      setExpanded(searchToggle, open);
      if (open) search.querySelector('input')?.focus();
    });
  }

  const setBackground = (name) => {
    if (!backgrounds.has(name)) name = 'paper';
    root.dataset.readingBg = name;
    try { localStorage.setItem('quietype-reading-bg', name); } catch (error) {}
    document.querySelectorAll('[data-reading-bg]').forEach((button) => {
      button.classList.toggle('active', button.dataset.readingBg === name);
      button.setAttribute('aria-pressed', String(button.dataset.readingBg === name));
    });
  };

  if (backgroundToggle && backgroundOptions) {
    backgroundToggle.addEventListener('click', () => {
      const open = backgroundOptions.hasAttribute('hidden');
      backgroundOptions.toggleAttribute('hidden', !open);
      setExpanded(backgroundToggle, open);
    });
    document.querySelectorAll('[data-reading-bg]').forEach((button) => {
      button.addEventListener('click', () => setBackground(button.dataset.readingBg));
    });
  }
  setBackground(root.dataset.readingBg);

  document.querySelectorAll('.article-content pre').forEach((pre) => {
    if (pre.closest('.code-toolbar')) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'copy-code';
    button.textContent = '复制';
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(pre.textContent);
        button.textContent = '已复制';
      } catch (error) {
        button.textContent = '复制失败';
      }
      window.setTimeout(() => { button.textContent = '复制'; }, 1500);
    });
    pre.insertAdjacentElement('beforebegin', button);
    const wrapper = document.createElement('div');
    wrapper.className = 'code-toolbar';
    pre.parentNode.insertBefore(wrapper, button);
    wrapper.append(button, pre);
  });

  const tocLinks = [...document.querySelectorAll('.article-toc a')];
  const headings = tocLinks.map((link) => document.getElementById(decodeURIComponent(link.hash.slice(1)))).filter(Boolean);
  if (tocLinks.length && headings.length && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        tocLinks.forEach((link) => link.classList.toggle('active', link.hash === `#${entry.target.id}`));
      });
    }, { rootMargin: '-18% 0px -72% 0px' });
    headings.forEach((heading) => observer.observe(heading));
  }

  const progressBars = [...document.querySelectorAll('.reading-progress i, .site-reading-progress i')];
  const articleContent = document.querySelector('.article-content');
  const updateScrollState = () => {
    const start = articleContent ? articleContent.offsetTop - window.innerHeight * 0.22 : 0;
    const end = articleContent ? articleContent.offsetTop + articleContent.offsetHeight - window.innerHeight * 0.78 : document.documentElement.scrollHeight - window.innerHeight;
    const height = Math.max(1, end - start);
    const percent = Math.min(100, Math.max(0, (window.scrollY - start) / height * 100));
    progressBars.forEach((progress) => { progress.style.width = `${percent}%`; });
    if (topButton) topButton.toggleAttribute('hidden', window.scrollY < 560);
  };
  updateScrollState();
  window.addEventListener('scroll', updateScrollState, { passive: true });
  topButton?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
})();
