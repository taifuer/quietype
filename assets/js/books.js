(() => {
  'use strict';

  const yearSections = [...document.querySelectorAll('.book-year-shelf[data-expanded]')];
  if (!yearSections.length) return;

  const yearLinks = [...document.querySelectorAll('.book-year-index a[aria-controls]')];

  const setYearExpanded = (section, expanded) => {
    const grid = section.querySelector('.book-grid');
    const button = section.querySelector('.book-year-toggle');
    const labelYear = button?.querySelector('span')?.textContent.trim() || '';
    if (!grid || !button) return;

    section.dataset.expanded = String(expanded);
    grid.hidden = !expanded;
    button.setAttribute('aria-expanded', String(expanded));
    button.setAttribute('aria-label', `${expanded ? '收起' : '展开'} ${labelYear} 年书籍`);
    yearLinks
      .filter((link) => link.getAttribute('aria-controls') === grid.id)
      .forEach((link) => link.setAttribute('aria-expanded', String(expanded)));
  };

  yearSections.forEach((section) => {
    setYearExpanded(section, section.dataset.expanded === 'true');
    section.querySelector('.book-year-toggle')?.addEventListener('click', () => {
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
    const section = target?.classList.contains('book-year-shelf') ? target : target?.closest('.book-year-shelf');
    if (!section) return;
    setYearExpanded(section, true);
    requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
  };

  revealHashTarget();
  window.addEventListener('hashchange', revealHashTarget);
})();
