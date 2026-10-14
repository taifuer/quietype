/* Local renderers for saved Markdown HTML, independent of the editing plugin. */
(() => {
  'use strict';
  const article = document.querySelector('.article-content');
  if (!article) return;
  const preview = document.body.classList.contains('quietype-markdown-preview');
  // A math-only preview has a Prism.manual configuration stub, not the library.
  if (window.Prism && typeof Prism.highlightElement === 'function') {
    if (Prism.plugins?.autoloader) {
      Prism.plugins.autoloader.languages_path = new URL('../vendor/prism/components/', document.currentScript.src).href;
    }
    article.querySelectorAll('pre > code[class*="language-"]').forEach((code) => {
      if (code.matches('.language-mermaid, .language-math, .language-latex, .language-katex')) return;
      code.parentElement.classList.add('line-numbers');
      Prism.highlightElement(code);
    });
  }
  if (window.katex) {
    const options = { throwOnError: false, trust: false, strict: 'ignore', maxExpand: 1000, maxSize: 20 };
    article.querySelectorAll('.quietype-math, .katex.math').forEach((node) => {
      if (node.querySelector('.katex-html')) return;
      katex.render(node.textContent, node, {
        ...options,
        displayMode: node.dataset.display === 'true' || node.classList.contains('multi-line')
      });
    });
  }
  if (preview) article.addEventListener('click', (event) => {
    const link = event.target.closest('a');
    if (link && !link.getAttribute('href')?.startsWith('#')) event.preventDefault();
  });
})();
