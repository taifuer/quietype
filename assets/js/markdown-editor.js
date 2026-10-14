(() => {
  'use strict';
  const settings = window.quietypeMarkdownEditor;
  const root = document.querySelector('.quietype-markdown-editor');
  const textarea = document.querySelector('#content');
  if (!settings || !root || !textarea) return;
  const nativeEditor = document.querySelector('#postdivrich');
  const panes = root.querySelector('.quietype-markdown-editor__panes');
  const frame = root.querySelector('iframe');
  const status = root.querySelector('[role="status"]');
  nativeEditor.before(root);
  panes.prepend(nativeEditor);
  document.body.classList.add('quietype-has-markdown-editor');
  textarea.setAttribute('aria-label', 'Markdown 原文');
  const editor = settings.editor && window.wp?.codeEditor
    ? wp.codeEditor.initialize(textarea, settings.editor).codemirror : null;
  editor?.setOption('extraKeys', { Tab: false, 'Shift-Tab': false });
  editor?.getInputField().setAttribute('aria-label', 'Markdown 原文');
  let mode = 'write';
  let timer;
  let controller;
  let revision = 0;
  let renderedSource = null;
  const source = () => editor ? editor.getValue() : textarea.value;

  const preview = async () => {
    if (mode === 'write' || source() === renderedSource) return;
    controller?.abort();
    controller = new AbortController();
    const current = ++revision;
    const text = source();
    status.textContent = '正在预览…';
    try {
      const response = await fetch(settings.url, {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        body: new URLSearchParams({
          action: 'quietype_markdown_preview', post_id: settings.postId,
          quietype_markdown_nonce: settings.nonce, content: text
        })
      });
      const result = await response.json();
      if (current !== revision) return;
      if (!response.ok || !result.success) throw new Error(result.data?.message || '预览失败，请重试。');
      const scroll = frame.contentWindow?.scrollY || 0;
      frame.addEventListener('load', () => frame.contentWindow?.scrollTo(0, scroll), { once: true });
      frame.srcdoc = result.data.document;
      renderedSource = text;
      status.textContent = '预览已更新；尚未保存的修改不会发布。';
    } catch (error) {
      if (error.name !== 'AbortError' && current === revision) status.textContent = error.message || '预览连接失败，请重试。';
    }
  };
  const changed = () => {
    editor?.save();
    clearTimeout(timer);
    // Invalidate any in-flight older preview immediately, not after debounce.
    ++revision;
    controller?.abort();
    timer = setTimeout(preview, 600);
  };
  if (editor) editor.on('change', changed);
  else textarea.addEventListener('input', changed);
  root.querySelectorAll('[data-md-view]').forEach((button) => button.addEventListener('click', () => {
    mode = button.dataset.mdView;
    root.dataset.view = mode;
    nativeEditor.hidden = mode === 'preview';
    frame.hidden = mode === 'write';
    root.querySelectorAll('[data-md-view]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
    if (mode !== 'preview') editor?.refresh();
    preview();
  }));

  const insert = (before, after = '', placeholder = '') => {
    if (mode === 'preview') root.querySelector('[data-md-view="write"]').click();
    if (editor) {
      editor.replaceSelection(before + (editor.getSelection() || placeholder) + after, 'around');
      editor.focus();
    } else {
      const selection = textarea.value.slice(textarea.selectionStart, textarea.selectionEnd);
      textarea.setRangeText(before + (selection || placeholder) + after, textarea.selectionStart, textarea.selectionEnd, 'select');
      textarea.focus();
      changed();
    }
  };
  const commands = {
    heading: ['\n## ', '\n', '标题'], bold: ['**', '**', '文字'], italic: ['*', '*', '文字'],
    link: ['[', '](https://example.com/)', '链接文字'], quote: ['\n> ', '\n', '引用内容'],
    list: ['\n- ', '\n', '列表项'], code: ['\n```text\n', '\n```\n', '代码'],
    table: ['\n| 标题 | 标题 |\n| --- | --- |\n| 内容 | 内容 |\n'],
    video: ['\n\n[video src="', '"]\n\n', 'https://example.com/demo.mp4'],
    math: ['\n$$\n', '\n$$\n', 'E = mc^2']
  };
  root.querySelectorAll('[data-md-command]').forEach((button) => button.addEventListener('click', () => {
    const command = button.dataset.mdCommand;
    if (command !== 'image') { insert(...commands[command]); return; }
    const media = wp.media({ title: '插入图片', library: { type: 'image' }, multiple: false, button: { text: '插入 Markdown' } });
    media.on('select', () => {
      const image = media.state().get('selection').first().toJSON();
      const alt = (image.alt || image.title || '').replace(/[\[\]\\\n]/g, '');
      insert(`\n![${alt}](${image.url.replace(/\(/g, '%28').replace(/\)/g, '%29')})\n`);
    });
    media.open();
  }));
  document.querySelector('#post')?.addEventListener('submit', () => editor?.save());
  // Core restores its textarea with execCommand, which cannot target a hidden
  // CodeMirror textarea. Preserve its title/excerpt/notice handling, then sync.
  document.addEventListener('click', (event) => {
    if (!editor || !event.target.closest('.restore-backup')) return;
    const backup = window.wp?.autosave?.local?.getSavedPostData();
    if (typeof backup?.content !== 'string') return;
    setTimeout(() => { editor.setValue(backup.content); editor.focus(); }, 0);
  }, true);
  // Let WordPress own autosave timing, post locks and draft/revision behavior.
  window.jQuery(document).on('heartbeat-send.quietypeMarkdown', (event, data) => {
    if (data.wp_autosave) {
      data.wp_autosave.content = source();
      data.wp_autosave.quietype_markdown_nonce = settings.nonce;
    }
  });
})();
