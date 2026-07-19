(() => {
  'use strict';

  const root = document.documentElement;
  const nav = document.querySelector('.site-nav');
  const navToggle = document.querySelector('.nav-toggle');
  const navBackdrop = document.querySelector('.nav-backdrop');
  const topButton = document.querySelector('.top-button');
  const bottomButton = document.querySelector('.bottom-button');
  const search = document.querySelector('.site-search');
  const searchToggles = [...document.querySelectorAll('.search-toggle')];
  const backgroundToggle = document.querySelector('.reading-background__toggle');
  const backgroundOptions = document.querySelector('.reading-background__options');
  const backgroundButtons = [...document.querySelectorAll('.reading-background__options [data-reading-bg]')];
  const readingTools = document.querySelector('.reading-tools');
  const readingNavigation = document.querySelector('.reading-tools__navigation');
  const siteFooter = document.querySelector('.site-footer');
  const backgrounds = new Set(['paper', 'warm', 'green']);

  const setExpanded = (button, expanded) => {
    if (button) button.setAttribute('aria-expanded', String(expanded));
  };

  const setSearchExpanded = (expanded) => searchToggles.forEach((button) => setExpanded(button, expanded));

  const setNav = (open) => {
    if (!nav || !navToggle) return;
    nav.classList.toggle('open', open);
    document.body.classList.toggle('nav-open', open);
    navBackdrop?.toggleAttribute('hidden', !open);
    navToggle.setAttribute('aria-label', open ? '关闭菜单' : '打开菜单');
    setExpanded(navToggle, open);
    if (open && search) {
      search.toggleAttribute('hidden', true);
      setSearchExpanded(false);
    }
    if (!open) {
      nav.querySelectorAll('.submenu-open').forEach((item) => item.classList.remove('submenu-open'));
      nav.querySelectorAll('.submenu-toggle').forEach((button) => {
        button.setAttribute('aria-expanded', 'false');
        button.querySelector('span').textContent = '＋';
      });
    }
  };

  if (navToggle && nav) {
    navToggle.addEventListener('click', (event) => {
      const open = !nav.classList.contains('open');
      setNav(open);
      if (!open && event.detail > 0) navToggle.blur();
    });
    navBackdrop?.addEventListener('click', () => setNav(false));
    nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setNav(false)));
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') setNav(false);
    });
  }

  if (nav) {
    nav.querySelectorAll('.menu-item-has-children').forEach((item) => {
      const submenu = item.querySelector(':scope > .sub-menu');
      const link = item.querySelector(':scope > a');
      if (!submenu || !link) return;
      let button = item.querySelector(':scope > .submenu-toggle');
      if (!button) {
        button = document.createElement('button');
        button.type = 'button';
        button.className = 'submenu-toggle';
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', `展开${link.textContent.trim()}子菜单`);
        button.innerHTML = '<span aria-hidden="true">＋</span>';
        item.insertBefore(button, submenu);
      }
      button.addEventListener('click', () => {
        const open = !item.classList.contains('submenu-open');
        item.classList.toggle('submenu-open', open);
        button.setAttribute('aria-expanded', String(open));
        button.setAttribute('aria-label', `${open ? '收起' : '展开'}${link.textContent.trim()}子菜单`);
        button.querySelector('span').textContent = open ? '－' : '＋';
      });
    });
  }

  if (searchToggles.length && search) {
    searchToggles.forEach((searchToggle) => searchToggle.addEventListener('click', () => {
      const open = search.hasAttribute('hidden');
      search.toggleAttribute('hidden', !open);
      setSearchExpanded(open);
      if (open) setNav(false);
      if (open) search.querySelector('input')?.focus();
    }));
  }

  const setBackground = (name) => {
    if (!backgrounds.has(name)) name = 'paper';
    root.dataset.readingBg = name;
    try { localStorage.setItem('quietype-reading-bg', name); } catch (error) {}
    backgroundButtons.forEach((button) => {
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
    backgroundButtons.forEach((button) => {
      button.addEventListener('click', () => setBackground(button.dataset.readingBg));
      button.addEventListener('click', () => {
        backgroundOptions.toggleAttribute('hidden', true);
        setExpanded(backgroundToggle, false);
      });
    });
  }
  setBackground(root.dataset.readingBg);

  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (backgroundOptions && backgroundToggle && !target?.closest('.reading-background')) {
      backgroundOptions.toggleAttribute('hidden', true);
      setExpanded(backgroundToggle, false);
    }
    if (search && searchToggles.length && !target?.closest('.site-search, .search-toggle')) {
      search.toggleAttribute('hidden', true);
      setSearchExpanded(false);
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (backgroundOptions) backgroundOptions.toggleAttribute('hidden', true);
    if (search) search.toggleAttribute('hidden', true);
    setExpanded(backgroundToggle, false);
    setSearchExpanded(false);
  });

  const languageNames = {
    bash: 'Shell', shell: 'Shell', sh: 'Shell', javascript: 'JavaScript', js: 'JavaScript',
    typescript: 'TypeScript', ts: 'TypeScript', html: 'HTML', markup: 'HTML', css: 'CSS',
    php: 'PHP', python: 'Python', py: 'Python', java: 'Java', json: 'JSON', yaml: 'YAML',
    yml: 'YAML', sql: 'SQL', nginx: 'Nginx', docker: 'Dockerfile', markdown: 'Markdown', md: 'Markdown'
  };

  const enhanceCodeBlocks = () => document.querySelectorAll('.article-content pre').forEach((pre) => {
    let wrapper = pre.closest('.code-toolbar');
    if (!wrapper) {
      wrapper = document.createElement('div');
      wrapper.className = 'code-toolbar';
      pre.parentNode.insertBefore(wrapper, pre);
      wrapper.append(pre);
    }

    const code = pre.querySelector('code');
    const languageClass = [...new Set([...(pre.classList || []), ...(code?.classList || [])])]
      .find((name) => name.startsWith('language-'));
    if (languageClass && !wrapper.querySelector('.code-language, .toolbar-item span')) {
      const language = languageClass.replace('language-', '').toLowerCase();
      const label = document.createElement('span');
      label.className = 'code-language';
      label.textContent = languageNames[language] || language.toUpperCase();
      wrapper.classList.add('has-code-label');
      wrapper.prepend(label);
    }

    if (wrapper.querySelector('.copy-code, .toolbar-item button[data-copy-state], .copy-to-clipboard-button')) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'copy-code';
    button.textContent = '复制';
    button.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(code?.textContent || pre.textContent);
        button.textContent = '已复制';
      } catch (error) {
        button.textContent = '复制失败';
      }
      window.setTimeout(() => { button.textContent = '复制'; }, 1500);
    });
    wrapper.append(button);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.setTimeout(enhanceCodeBlocks));
  } else {
    window.setTimeout(enhanceCodeBlocks);
  }

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

  const progressBars = [...document.querySelectorAll('.site-reading-progress i')];
  const articleContent = document.querySelector('.article-content');
  const articleToc = document.querySelector('.article-toc');
  const updateScrollState = () => {
    const start = articleContent ? articleContent.offsetTop - window.innerHeight * 0.22 : 0;
    const end = articleContent ? articleContent.offsetTop + articleContent.offsetHeight - window.innerHeight * 0.78 : document.documentElement.scrollHeight - window.innerHeight;
    const height = Math.max(1, end - start);
    const percent = Math.min(100, Math.max(0, (window.scrollY - start) / height * 100));
    progressBars.forEach((progress) => { progress.style.width = `${percent}%`; });
    const maxScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
    const topThreshold = Math.min(560, Math.max(64, maxScroll * 0.35));
    if (topButton) topButton.toggleAttribute('hidden', window.scrollY < topThreshold);
    if (bottomButton) {
      const nearBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 120;
      bottomButton.toggleAttribute('hidden', nearBottom);
    }
    if (readingNavigation) {
      const showBoth = topButton && bottomButton && !topButton.hidden && !bottomButton.hidden;
      readingNavigation.classList.toggle('has-both', Boolean(showBoth));
      const hasVisibleNavigation = (topButton && !topButton.hidden) || (bottomButton && !bottomButton.hidden);
      readingNavigation.toggleAttribute('hidden', !hasVisibleNavigation);
    }
    if (articleToc && articleContent) {
      const pastArticle = window.scrollY > articleContent.offsetTop + articleContent.offsetHeight - window.innerHeight * 0.35;
      articleToc.classList.toggle('is-past-article', pastArticle);
    }
    if (readingTools && siteFooter) {
      const footerOverlap = Math.max(0, window.scrollY + window.innerHeight - siteFooter.offsetTop);
      readingTools.style.setProperty('--footer-overlap', `${footerOverlap}px`);
    }
  };
  updateScrollState();
  window.addEventListener('scroll', updateScrollState, { passive: true });
  topButton?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  bottomButton?.addEventListener('click', () => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' }));

})();
