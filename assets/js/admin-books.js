(() => {
  const lookupButton = document.querySelector('#quietype-book-lookup');
  const confirmButton = document.querySelector('#quietype-book-confirm');
  const subjectField = document.querySelector('#quietype_book_douban_input');
  const status = document.querySelector('#quietype-book-lookup-status');
  const preview = document.querySelector('#quietype-book-preview');
  let pendingBook = null;

  if (!lookupButton || !confirmButton || !subjectField || !status || !preview || !window.quietypeBookLookup) return;

  const field = (id) => document.querySelector(`#${id}`);
  const setValue = (id, value) => {
    const input = field(id);
    if (input) input.value = value || '';
  };
  const setTitle = (title) => {
    if (!title) return;

    const editor = window.wp?.data?.dispatch?.('core/editor');
    if (editor?.editPost) editor.editPost({ title });

    document.querySelectorAll('#title, .editor-post-title__input').forEach((titleField) => {
      if ('value' in titleField) titleField.value = title;
      else titleField.textContent = title;
      titleField.dispatchEvent(new Event('input', { bubbles: true }));
      titleField.dispatchEvent(new Event('change', { bubbles: true }));
    });

    const titlePrompt = document.querySelector('#title-prompt-text');
    if (titlePrompt) {
      titlePrompt.classList.add('screen-reader-text');
      titlePrompt.hidden = true;
    }
  };
  const showPreview = (book) => {
    const cover = field('quietype-book-preview-cover');
    field('quietype-book-preview-title').textContent = book.title;
    field('quietype-book-preview-meta').textContent = [book.authors, book.publisher, book.publication_year].filter(Boolean).join(' · ');
    field('quietype-book-preview-extra').textContent = [book.douban_rating ? `豆瓣 ${book.douban_rating}` : '', book.isbn ? `ISBN ${book.isbn}` : ''].filter(Boolean).join(' · ');
    if (book.cover_url) {
      cover.addEventListener('error', () => {
        cover.removeAttribute('src');
        cover.hidden = true;
        status.textContent = '书籍资料已读取，封面无法直接预览；确认后仍会由服务器尝试导入。';
      }, { once: true });
      cover.src = book.cover_url;
      cover.alt = `《${book.title}》封面预览`;
      cover.hidden = false;
    } else {
      cover.removeAttribute('src');
      cover.hidden = true;
    }
    preview.hidden = false;
  };

  lookupButton.addEventListener('click', async () => {
    const subject = subjectField.value.trim();
    pendingBook = null;
    preview.hidden = true;
    if (!subject) {
      status.textContent = '请先填写豆瓣链接或条目 ID。';
      return;
    }
    lookupButton.disabled = true;
    status.textContent = '正在读取豆瓣资料…';
    const body = new FormData();
    body.append('action', 'quietype_lookup_book');
    body.append('nonce', window.quietypeBookLookup.nonce);
    body.append('subject', subject);
    try {
      const response = await fetch(window.quietypeBookLookup.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body
      });
      const payload = await response.json();
      if (!payload.success) throw new Error(payload.data?.message || '没有找到书籍资料。');
      pendingBook = payload.data;
      showPreview(pendingBook);
      status.textContent = '已读取预览，请核对后点击“确认填入”。';
    } catch (error) {
      status.textContent = error.message;
    } finally {
      lookupButton.disabled = false;
    }
  });

  confirmButton.addEventListener('click', () => {
    if (!pendingBook) return;
    setTitle(pendingBook.title);
    setValue('quietype_book_authors', pendingBook.authors);
    setValue('quietype_book_publisher', pendingBook.publisher);
    setValue('quietype_book_publication_year', pendingBook.publication_year);
    setValue('quietype_book_isbn', pendingBook.isbn);
    setValue('quietype_book_douban_rating', pendingBook.douban_rating);
    setValue('quietype_book_douban_url', pendingBook.douban_url);
    setValue('quietype_book_douban_id', pendingBook.douban_id);
    setValue('quietype_book_import_source_url', pendingBook.cover_url);
    subjectField.value = pendingBook.douban_url;

    const importPanel = field('quietype-book-import');
    const cover = field('quietype-book-cover-preview');
    if (pendingBook.cover_url) {
      cover.addEventListener('error', () => {
        cover.removeAttribute('src');
        cover.hidden = true;
      }, { once: true });
      cover.src = pendingBook.cover_url;
      cover.alt = `《${pendingBook.title}》封面预览`;
      cover.hidden = false;
      importPanel.hidden = false;
      field('quietype_book_import_cover').checked = true;
    } else {
      importPanel.hidden = true;
    }
    status.textContent = '资料已填入表单；请继续核对分类、标签、阅读状态、阅读月份和短评，再发布或更新。';
  });
})();
