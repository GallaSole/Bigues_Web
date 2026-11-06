(() => {
  async function loadImage(file) {
    const url = URL.createObjectURL(file);
    try {
      const img = new Image();
      await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = () => reject(new Error('No s\'ha pogut llegir aquesta imatge.'));
        img.src = url;
      });
      return img;
    } finally {
      setTimeout(() => URL.revokeObjectURL(url), 0);
    }
  }

  function drawToCanvas(source, maxSide) {
    let width = source.naturalWidth || source.width;
    let height = source.naturalHeight || source.height;
    const scale = Math.min(1, maxSide / Math.max(width, height));
    width = Math.max(1, Math.round(width * scale));
    height = Math.max(1, Math.round(height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d', { alpha: false });
    if (!ctx) throw new Error('No s\'ha pogut preparar la imatge.');

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
    ctx.imageSmoothingEnabled = true;
    if ('imageSmoothingQuality' in ctx) ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(source, 0, 0, width, height);

    return canvas;
  }

  function canvasToBlob(canvas, mime, quality) {
    return new Promise(resolve => {
      // Safari pot retornar un MIME inesperat encara que la codificació sigui correcta;
      // per això no decidim aquí si és WEBP o no.
      try {
        canvas.toBlob(blob => resolve(blob || null), mime, quality);
      } catch (_) {
        resolve(null);
      }
    });
  }

  async function blobIsWebp(blob) {
    if (!blob || blob.size < 12) return false;
    try {
      const bytes = new Uint8Array(await blob.slice(0, 12).arrayBuffer());
      return String.fromCharCode(...bytes.slice(0, 4)) === 'RIFF' &&
             String.fromCharCode(...bytes.slice(8, 12)) === 'WEBP';
    } catch (_) {
      return false;
    }
  }

  function dataUrlToBlob(dataUrl) {
    const parts = dataUrl.split(',');
    if (parts.length !== 2) return null;
    const match = /^data:([^;]+);base64$/i.exec(parts[0]);
    if (!match) return null;
    try {
      const binary = atob(parts[1]);
      const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
      return new Blob([bytes], { type: match[1] });
    } catch (_) {
      return null;
    }
  }

  async function tryWebp(canvas, quality) {
    // 1) Via preferida: toBlob().
    let blob = await canvasToBlob(canvas, 'image/webp', quality);
    if (await blobIsWebp(blob)) {
      return new Blob([blob], { type: 'image/webp' });
    }

    // 2) Fallback específic per Safari/WebKit: toDataURL().
    try {
      const dataUrl = canvas.toDataURL('image/webp', quality);
      if (dataUrl.startsWith('data:image/webp')) {
        blob = dataUrlToBlob(dataUrl);
        if (await blobIsWebp(blob)) {
          return new Blob([blob], { type: 'image/webp' });
        }
      }
    } catch (_) {}

    return null;
  }

  async function prepareImage(file) {
    const img = await loadImage(file);
    let canvas = drawToCanvas(img, 1800);

    // Primer intentem WEBP directament al dispositiu.
    let blob = await tryWebp(canvas, 0.90);
    if (blob && blob.size > 1500 * 1024) blob = await tryWebp(canvas, 0.84);

    if (blob && blob.size > 1500 * 1024 && Math.max(canvas.width, canvas.height) > 1600) {
      canvas = drawToCanvas(canvas, 1600);
      blob = await tryWebp(canvas, 0.86);
    }

    if (blob) {
      return {
        blob,
        name: 'resultats-' + Date.now() + '.webp',
        convertedInBrowser: true
      };
    }

    // Safari/WebKit fallback: NO bloquegem la publicació.
    // Generem un JPEG temporal, ja redimensionat/comprimit, i PHP el converteix a WEBP.
    let jpeg = await canvasToBlob(canvas, 'image/jpeg', 0.92);
    if (!jpeg) {
      try {
        jpeg = dataUrlToBlob(canvas.toDataURL('image/jpeg', 0.92));
      } catch (_) {}
    }

    if (!jpeg) {
      throw new Error('No s\'ha pogut preparar la imatge en aquest dispositiu.');
    }

    if (jpeg.size > 1500 * 1024) {
      jpeg = await canvasToBlob(canvas, 'image/jpeg', 0.86) || jpeg;
    }

    if (jpeg.size > 1500 * 1024 && Math.max(canvas.width, canvas.height) > 1600) {
      canvas = drawToCanvas(canvas, 1600);
      jpeg = await canvasToBlob(canvas, 'image/jpeg', 0.88) || jpeg;
    }

    return {
      blob: jpeg,
      name: 'resultats-' + Date.now() + '.jpg',
      convertedInBrowser: false
    };
  }

  function askConfirm(message) {
    const dialog = document.getElementById('confirmDialog');
    if (!dialog) return Promise.resolve(true);
    dialog.querySelector('[data-confirm-text]').textContent = message || 'Vols publicar-ho a la web?';
    return new Promise(resolve => {
      const done = value => { dialog.close(); resolve(value); };
      dialog.querySelector('[data-yes]').onclick = () => done(true);
      dialog.querySelector('[data-no]').onclick = () => done(false);
      dialog.oncancel = () => resolve(false);
      dialog.showModal();
    });
  }

  function showView(name) {
    document.querySelectorAll('[data-view]').forEach(section => {
      section.hidden = section.dataset.view !== name;
    });
    window.scrollTo(0, 0);
  }

  document.querySelectorAll('[data-go]').forEach(button => {
    button.addEventListener('click', () => showView(button.dataset.go));
  });

  function bindCrop(frame) {
    const img = frame.querySelector('img');
    const form = frame.closest('form');
    const hint = frame.parentElement.querySelector('.crop-hint');
    const inputX = form.elements.focus_x;
    const inputY = form.elements.focus_y;
    const clamp = value => Math.max(0, Math.min(100, value));
    let x = 50;
    let y = 50;

    const apply = () => {
      img.style.objectPosition = x + '% ' + y + '%';
      inputX.value = Math.round(x);
      inputY.value = Math.round(y);
    };

    // Quant la imatge es pot despllaçar en cada eix (en píxels) amb object-fit: cover.
    const overflow = () => {
      const scale = Math.max(frame.clientWidth / img.naturalWidth, frame.clientHeight / img.naturalHeight);
      return [img.naturalWidth * scale - frame.clientWidth, img.naturalHeight * scale - frame.clientHeight];
    };

    img.addEventListener('load', () => {
      x = 50;
      y = 50;
      apply();
      const [ox, oy] = overflow();
      if (hint) hint.hidden = ox < 1 && oy < 1;
    });

    let drag = null;
    frame.addEventListener('pointerdown', event => {
      drag = { px: event.clientX, py: event.clientY, x, y };
      frame.setPointerCapture(event.pointerId);
    });
    frame.addEventListener('pointermove', event => {
      if (!drag) return;
      const [ox, oy] = overflow();
      if (ox > 0) x = clamp(drag.x - (event.clientX - drag.px) / ox * 100);
      if (oy > 0) y = clamp(drag.y - (event.clientY - drag.py) / oy * 100);
      apply();
    });
    ['pointerup', 'pointercancel'].forEach(name => frame.addEventListener(name, () => { drag = null; }));
  }

  document.querySelectorAll('.crop-frame').forEach(bindCrop);

  const newsForm = document.getElementById('newsForm');
  if (newsForm) {
    const preview = document.getElementById('newsPreview');
    const titleField = newsForm.elements.title;
    const bodyField = newsForm.elements.body;
    const fileField = newsForm.elements.image;
    const previewImg = preview.querySelector('img');
    let shownFile = null;
    const refresh = () => {
      const file = fileField.files && fileField.files[0];
      if (file !== shownFile) {
        shownFile = file || null;
        previewImg.parentElement.hidden = !file;
        if (file) previewImg.src = URL.createObjectURL(file);
        else preview.querySelector('.crop-hint').hidden = true;
      }
      preview.querySelector('h3').textContent = titleField.value || 'Títol de la notícia';
      preview.querySelector('.news-preview-body').textContent = bodyField.value || 'El text de la notícia apareixerà aquí.';
    };
    [titleField, bodyField, fileField].forEach(field => field.addEventListener('input', refresh));
    refresh();
  }

  function bindUploadForm(form) {
    const input = form.querySelector('input[type="file"]');
    const previewWrap = form.querySelector('.preview-wrap');
    const preview = previewWrap && previewWrap.querySelector('img');
    const label = form.querySelector('.file-picker strong');
    const button = form.querySelector('button[type="submit"]');
    if (!input || !button) return;
    const buttonText = button.textContent;

    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) return;
      if (label) label.textContent = file.name || 'Imatge seleccionada';
      if (preview && previewWrap) {
        const url = URL.createObjectURL(file);
        preview.onload = () => URL.revokeObjectURL(url);
        preview.src = url;
        previewWrap.hidden = false;
      }
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const file = input.files && input.files[0];
      if (!file && input.required) return;
      if (!(await askConfirm(form.dataset.confirm))) return;

      button.disabled = true;

      try {
        const data = new FormData(form);
        data.set('ajax', '1');

        if (file) {
          button.textContent = 'Preparant imatge…';
          const prepared = await prepareImage(file);
          data.delete('image');
          data.append('image', prepared.blob, prepared.name);
        }

        button.textContent = 'Publicant…';

        const response = await fetch('admin.php', {
          method: 'POST',
          body: data,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'fetch' }
        });

        let payload = null;
        try { payload = await response.json(); } catch (_) {}

        if (!response.ok || !payload || !payload.ok) {
          throw new Error(payload && payload.error ? payload.error : 'No s\'ha pogut publicar.');
        }

        window.location.replace('admin.php?uploaded=1&_=' + Date.now());
      } catch (error) {
        alert(error && error.message ? error.message : 'No s\'ha pogut publicar.');
        button.disabled = false;
        button.textContent = buttonText;
      }
    });
  }

  document.querySelectorAll('form[data-upload]').forEach(bindUploadForm);

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('./sw.js').catch(() => {}));
  }
})();
