/* Load the pinned, self-contained upstream bundle near a visible diagram. */
const article = document.querySelector('.article-content');
const root = document.documentElement;
const candidates = [...(article?.querySelectorAll('pre.mermaid, div.mermaid, pre.language-mermaid, pre:has(code.language-mermaid)') || [])];
let library;
let queue = Promise.resolve();
let revision = 0;
let expanded;
let scale = 1;
let dialog;
const diagrams = [];

const loadMermaid = () => {
  if (!library) library = new Promise((resolve, reject) => {
    // This upstream build is a classic script: importing it as an ES module
    // would hide the global variable its export shim expects.
    const script = document.createElement('script');
    script.src = new URL('../vendor/mermaid/mermaid.min.js?v=11.17.2', import.meta.url).href;
    script.async = true;
    script.onload = () => window.mermaid ? resolve(window.mermaid) : reject(new Error('Renderer unavailable'));
    script.onerror = () => { script.remove(); reject(new Error('Renderer unavailable')); };
    document.head.append(script);
  }).catch((error) => { library = null; throw error; });
  return library;
};

const applyZoom = (nextScale) => {
  if (!expanded) return;
  const viewport = dialog.querySelector('.diagram-viewport');
  const svg = expanded.canvas.querySelector('svg');
  if (!svg) return;
  const box = svg.viewBox.baseVal;
  scale = Math.max(.1, Math.min(4, nextScale));
  svg.style.width = `${box.width * scale}px`;
  svg.style.height = `${box.height * scale}px`;
  svg.style.maxWidth = 'none';
  expanded.canvas.style.width = `${Math.max(viewport.clientWidth, box.width * scale + 32)}px`;
  expanded.canvas.style.height = `${Math.max(viewport.clientHeight, box.height * scale + 32)}px`;
  dialog.querySelector('[data-zoom="out"]').disabled = scale <= .1;
  dialog.querySelector('[data-zoom="in"]').disabled = scale >= 4;
};

const fit = () => {
  const svg = expanded?.canvas.querySelector('svg');
  if (!svg) return;
  const viewport = dialog.querySelector('.diagram-viewport');
  const box = svg.viewBox.baseVal;
  applyZoom(Math.min(1, (viewport.clientWidth - 32) / box.width, (viewport.clientHeight - 32) / box.height));
  viewport.scrollTo(0, 0);
};

const showExpanded = (item) => {
  if (!('HTMLDialogElement' in window)) return;
  if (!dialog) {
    dialog = document.createElement('dialog');
    dialog.className = 'reading-dialog diagram-dialog';
    dialog.setAttribute('aria-labelledby', 'diagram-dialog-title');
    dialog.innerHTML = '<header class="reading-dialog__header"><strong id="diagram-dialog-title">查看图表</strong><div class="diagram-actions"><button type="button" data-zoom="out" aria-label="缩小图表">−</button><button type="button" data-zoom="in" aria-label="放大图表">＋</button><button type="button" data-zoom="fit">适应屏幕</button><button type="button" class="reading-dialog__close" aria-label="关闭图表">×</button></div></header><div class="diagram-viewport" tabindex="0" role="region" aria-label="图表，可滚动查看"></div>';
    document.body.append(dialog);
    dialog.querySelector('[data-zoom="out"]').addEventListener('click', () => applyZoom(scale / 1.25));
    dialog.querySelector('[data-zoom="in"]').addEventListener('click', () => applyZoom(scale * 1.25));
    dialog.querySelector('[data-zoom="fit"]').addEventListener('click', fit);
    dialog.querySelector('.reading-dialog__close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.querySelector('.diagram-viewport').addEventListener('wheel', (event) => {
      if (!event.ctrlKey) return;
      event.preventDefault();
      applyZoom(scale * (event.deltaY < 0 ? 1.1 : 1 / 1.1));
    }, { passive: false });
    dialog.addEventListener('close', () => {
      if (!expanded) return;
      expanded.canvas.style.width = '';
      expanded.canvas.style.height = '';
      const svg = expanded.canvas.querySelector('svg');
      if (svg) svg.style.cssText = expanded.originalStyle;
      expanded.figure.insertBefore(expanded.canvas, expanded.sourcePanel);
      expanded = null;
      root.classList.remove('reading-dialog-open');
    });
    window.addEventListener('resize', () => { if (dialog.open) fit(); }, { passive: true });
  }
  expanded = item;
  item.originalStyle = item.canvas.querySelector('svg').style.cssText;
  dialog.querySelector('.diagram-viewport').append(item.canvas);
  dialog.showModal();
  root.classList.add('reading-dialog-open');
  requestAnimationFrame(() => {
    fit();
    // Opening should actually enlarge a dense mobile diagram. The explicit
    // fit control remains available for an overview of the entire chart.
    if (scale < .85) applyZoom(.85);
  });
};

const draw = async (item) => {
  const firstRender = !item.ready;
  try {
    if (item.source.length > 50000) throw new Error('Diagram is too large');
    const mermaid = await loadMermaid();
    const styles = getComputedStyle(root);
    const color = (name) => styles.getPropertyValue(name).trim();
    mermaid.initialize({
      startOnLoad: false,
      securityLevel: 'strict',
      maxTextSize: 50000,
      maxEdges: 500,
      suppressErrorRendering: true,
      theme: 'base',
      htmlLabels: false,
      flowchart: { htmlLabels: false, curve: 'linear' },
      secure: ['secure', 'securityLevel', 'startOnLoad', 'maxTextSize', 'maxEdges', 'suppressErrorRendering', 'dompurifyConfig', 'htmlLabels'],
      themeVariables: {
        background: color('--paper'), primaryColor: color('--code'), primaryTextColor: color('--text'),
        primaryBorderColor: color('--muted'), secondaryColor: color('--accent-soft'), tertiaryColor: color('--paper'),
        lineColor: color('--muted'), textColor: color('--text'), noteBkgColor: color('--accent-soft'),
        noteTextColor: color('--text'), noteBorderColor: color('--muted'), edgeLabelBackground: color('--paper'),
        fontFamily: styles.getPropertyValue('--sans').trim(), fontSize: '16px'
      }
    });
    if (!await mermaid.parse(item.source, { suppressErrors: true })) throw new Error('Invalid diagram');
    const { svg } = await mermaid.render(`quietype-diagram-${++revision}`, item.source);
    item.canvas.innerHTML = svg;
    const rendered = item.canvas.querySelector('svg');
    if (!rendered) throw new Error('No diagram output');
    if (!rendered.hasAttribute('aria-label') && !rendered.hasAttribute('aria-labelledby')) rendered.setAttribute('aria-label', '文章图表');
    item.canvas.hidden = false;
    item.expand.hidden = false;
    item.status.hidden = true;
    item.ready = true;
    if (firstRender) item.sourcePanel.open = false;
    item.figure.dataset.state = 'ready';
    if (expanded === item) { item.originalStyle = rendered.style.cssText; fit(); }
  } catch (error) {
    if (!item.ready) {
      item.figure.dataset.state = 'error';
      item.status.textContent = '图表暂未绘制，可以查看下方源码。';
      item.sourcePanel.open = true;
    }
  }
};

const enqueue = (item) => {
  if (item.queued) return;
  item.queued = true;
  queue = queue.then(async () => { item.queued = false; await draw(item); });
};

for (const node of candidates) {
  if (node.closest('.mermaid-figure')) continue;
  const source = (node.querySelector('code') || node).textContent.trim();
  const figure = document.createElement('figure');
  figure.className = 'mermaid-figure';
  figure.dataset.state = 'pending';
  const canvas = document.createElement('div');
  canvas.className = 'mermaid-canvas';
  canvas.hidden = true;
  const toolbar = document.createElement('div');
  toolbar.className = 'mermaid-toolbar';
  const status = document.createElement('span');
  status.className = 'mermaid-status';
  status.textContent = '图表加载中…';
  status.setAttribute('role', 'status');
  const expand = document.createElement('button');
  expand.type = 'button';
  expand.className = 'mermaid-expand';
  expand.textContent = '放大查看';
  expand.setAttribute('aria-haspopup', 'dialog');
  expand.hidden = true;
  toolbar.append(status, expand);
  const sourcePanel = document.createElement('details');
  sourcePanel.className = 'mermaid-source-panel';
  sourcePanel.open = true;
  const summary = document.createElement('summary');
  summary.textContent = '图表源码';
  const pre = document.createElement('pre');
  pre.tabIndex = 0;
  pre.textContent = source;
  sourcePanel.append(summary, pre);
  figure.append(toolbar, canvas, sourcePanel);
  node.replaceWith(figure);
  const item = { source, figure, canvas, expand, status, sourcePanel, ready: false };
  expand.addEventListener('click', () => showExpanded(item));
  diagrams.push(item);
}

if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (!entry.isIntersecting) continue;
      const item = diagrams.find((diagram) => diagram.figure === entry.target);
      if (item) enqueue(item);
      observer.unobserve(entry.target);
    }
  }, { rootMargin: '600px 0px' });
  diagrams.forEach((item) => observer.observe(item.figure));
} else diagrams.forEach(enqueue);

new MutationObserver(() => diagrams.filter((item) => item.ready).forEach(enqueue))
  .observe(root, { attributes: true, attributeFilter: ['data-reading-bg'] });
