(() => {
  const config = window.quietypePhotoAdmin;
  const lookupButton = document.querySelector('#quietype-photo-lookup');
  const confirmButton = document.querySelector('#quietype-photo-confirm');
  const lookupInput = document.querySelector('#quietype_photo_lookup_url');
  const status = document.querySelector('#quietype-photo-lookup-status');
  const preview = document.querySelector('#quietype-photo-preview');
  const previewImage = document.querySelector('#quietype-photo-preview-image');
  let pending = null;

  if (!config || !lookupButton || !confirmButton || !lookupInput || !status || !preview) return;

  const field = (name) => document.querySelector(`#quietype_photo_${name}`);
  const text = (selector, value) => {
    const element = document.querySelector(selector);
    if (element) element.textContent = value || '未识别到拍摄参数';
  };

  const fileSize = (bytes) => {
    const size = Number(bytes) || 0;
    if (!size) return '';
    if (size < 1024 * 1024) return `${Math.max(1, Math.round(size / 1024))} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  };

  const showPreview = (data) => {
    pending = data;
    preview.hidden = false;
    previewImage.removeAttribute('src');
    previewImage.hidden = true;
    if (!data.is_oversized) {
      previewImage.src = data.url;
      previewImage.alt = '远程照片预览';
      previewImage.hidden = false;
      previewImage.addEventListener('error', () => { previewImage.hidden = true; }, { once: true });
    }
    const dimensions = data.width && data.height ? `${data.width} × ${data.height}` : '尺寸未识别';
    text('#quietype-photo-preview-size', [dimensions, fileSize(data.file_size)].filter(Boolean).join(' · '));
    text('#quietype-photo-preview-exif', [data.focal_length, data.aperture, data.shutter_speed, data.iso ? `ISO ${data.iso}` : ''].filter(Boolean).join(' · '));
    text('#quietype-photo-preview-device', [data.camera, data.lens].filter(Boolean).join(' · '));
  };

  lookupButton.addEventListener('click', async () => {
    const url = lookupInput.value.trim();
    if (!url) {
      status.textContent = '请先填写图片地址。';
      lookupInput.focus();
      return;
    }
    lookupButton.disabled = true;
    preview.hidden = true;
    pending = null;
    status.textContent = '正在读取…';
    const body = new URLSearchParams({ action: 'quietype_lookup_photo', nonce: config.nonce, url });
    try {
      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body,
      });
      const result = await response.json();
      if (!result.success) throw new Error(result.data?.message || '没有识别到图片信息。');
      showPreview(result.data);
      status.textContent = result.data.is_oversized
        ? '图片超过 10 MB，已跳过浏览器预览；建议将压缩图作为展示图、原文件填入原图地址。'
        : '读取完成，请确认后填入。';
    } catch (error) {
      status.textContent = error.message || '读取失败，请手工填写。';
    } finally {
      lookupButton.disabled = false;
    }
  });

  confirmButton.addEventListener('click', () => {
    if (!pending) return;
    const values = {
      image_url: pending.url,
      width: pending.width,
      height: pending.height,
      captured_date: pending.captured_date,
      focal_length: pending.focal_length,
      aperture: pending.aperture,
      shutter_speed: pending.shutter_speed,
      iso: pending.iso,
      camera: pending.camera,
      lens: pending.lens,
    };
    Object.entries(values).forEach(([name, value]) => {
      const input = field(name);
      if (input && value) input.value = value;
    });
    status.textContent = '图片信息已填入表单，保存后生效。';
  });
})();
