/*global dotclear */
'use strict';

/*
 * Yet Another Gallery Builder: "Photo gallery" button for the legacy editor.
 *
 * Opens a dialog to pick photos, write their descriptions and order them,
 * then uploads them one by one to the media manager (REST service
 * yagbUpload) and inserts plain HTML at the cursor:
 *
 *   <h4>Heading</h4>
 *   <ul class="photo-gallery">
 *   <li><a href="/public/folder/name-01.jpg"><img src="/public/folder/.name-01_s.jpg" alt="…" width="240" height="160" loading="lazy"></a></li>
 *   …
 *   </ul>
 */
(() => {
  const data = dotclear.getData('yagb');
  if (!data || !data.config) return;

  const cfg = data.config;
  const msg = data.msg;

  /* Helpers
  -------------------------------------------------------- */
  const fmt = (s, ...args) => args.reduce((acc, v, i) => acc.replace(`%${i + 1}$s`, v).replace('%s', v), s);

  const escapeHTML = (s) =>
    String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  const slug = (s) =>
    String(s)
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 60)
      .replace(/-+$/, '');

  const humanSize = (bytes) => {
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
    return `${Math.round(bytes / 1024)} KB`;
  };

  const el = (tag, attrs = {}, ...children) => {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
      if (value === null || value === undefined || value === false) continue;
      if (key === 'text') node.textContent = value;
      else if (key === 'className') node.className = value;
      else node.setAttribute(key, value === true ? '' : value);
    }
    for (const child of children) if (child) node.append(child);
    return node;
  };

  /**
   * JPEG EXIF orientation (1-8), or 1 if none/unknown.
   */
  const jpegOrientation = async (file) => {
    if (file.type !== 'image/jpeg') return 1;
    try {
      const view = new DataView(await file.slice(0, 131072).arrayBuffer());
      if (view.getUint16(0) !== 0xffd8) return 1;
      let offset = 2;
      while (offset + 4 < view.byteLength) {
        const marker = view.getUint16(offset);
        const length = view.getUint16(offset + 2);
        if (marker === 0xffe1 && view.getUint32(offset + 4) === 0x45786966) {
          const tiff = offset + 10;
          const little = view.getUint16(tiff) === 0x4949;
          const ifd = tiff + view.getUint32(tiff + 4, little);
          const count = view.getUint16(ifd, little);
          for (let i = 0; i < count; i++) {
            const entry = ifd + 2 + i * 12;
            if (view.getUint16(entry, little) === 0x0112) return view.getUint16(entry + 8, little);
          }
          return 1;
        }
        if ((marker & 0xff00) !== 0xff00) return 1;
        offset += 2 + length;
      }
    } catch (_e) {
      // unreadable: treat as upright
    }
    return 1;
  };

  /**
   * Resize to the longest edge (and bake the EXIF orientation in).
   * Returns the original file when nothing needs doing.
   */
  const prepare = async (file, max, quality) => {
    const orientation = await jpegOrientation(file);
    let bitmap;
    try {
      bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch (_e) {
      return file;
    }
    const { width, height } = bitmap;
    const longest = Math.max(width, height);
    if ((max === 0 || longest <= max) && orientation <= 1) {
      bitmap.close();
      return file;
    }
    const scale = max > 0 && longest > max ? max / longest : 1;
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();
    const type = file.type === 'image/png' || file.type === 'image/webp' ? file.type : 'image/jpeg';
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, quality / 100));
    return blob ?? file;
  };

  /* Gallery HTML
  -------------------------------------------------------- */
  const buildHTML = (heading, photos) => {
    const lines = [];
    if (heading && cfg.heading) lines.push(`<${cfg.heading}>${escapeHTML(heading)}</${cfg.heading}>`);
    lines.push(`<ul class="${escapeHTML(cfg.cssClass)}">`);
    for (const p of photos) {
      const size = p.width && p.height ? ` width="${p.width}" height="${p.height}"` : '';
      lines.push(
        `<li><a href="${escapeHTML(p.url)}"><img src="${escapeHTML(p.thumb)}" alt="${escapeHTML(p.alt)}"${size} loading="lazy"></a></li>`,
      );
    }
    lines.push('</ul>');
    return lines.join('\n');
  };

  /* Insertion point, captured when the button is clicked
  -------------------------------------------------------- */
  const capture = (tb, mode) => {
    if (mode === 'wysiwyg') {
      const sel = tb.iwin.getSelection();
      return { tb, mode, range: sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null };
    }
    return { tb, mode, start: tb.textarea.selectionStart, end: tb.textarea.selectionEnd };
  };

  const insert = (at, html) => {
    const { tb, mode } = at;
    if (mode === 'wysiwyg') {
      const doc = tb.iwin.document;
      let { range } = at;
      if (!range || !doc.body.contains(range.startContainer)) {
        range = doc.createRange();
        range.selectNodeContents(doc.body);
        range.collapse(false);
      }
      const fragment = range.createContextualFragment(html);
      const last = fragment.lastChild;
      range.deleteContents();
      range.insertNode(fragment);
      if (last) {
        const sel = tb.iwin.getSelection();
        const after = doc.createRange();
        after.setStartAfter(last);
        after.collapse(true);
        sel.removeAllRanges();
        sel.addRange(after);
      }
      tb.iwin.focus();
      return;
    }
    const snippet = mode === 'wiki' ? `///html\n${html}\n///` : html;
    const ta = tb.textarea;
    const before = ta.value.slice(0, at.start);
    const after = ta.value.slice(at.end);
    const pre = before === '' || before.endsWith('\n\n') ? '' : before.endsWith('\n') ? '\n' : '\n\n';
    const post = after === '' || after.startsWith('\n\n') ? '' : after.startsWith('\n') ? '\n' : '\n\n';
    ta.value = before + pre + snippet + post + after;
    const caret = (before + pre + snippet).length;
    ta.focus();
    ta.setSelectionRange(caret, caret);
    ta.dispatchEvent(new Event('input', { bubbles: true }));
  };

  /* Dialog
  -------------------------------------------------------- */
  let dialog = null;
  let ui = null;
  let items = [];
  let target = null;
  let busy = false;

  const postField = (name) => document.querySelector(`#entry-form [name="${name}"], form [name="${name}"]`);

  const defaultBasename = () => {
    const title = document.querySelector('#post_title')?.value || '';
    const url = document.querySelector('#post_url')?.value || '';
    const base = slug(url.split('/').pop() || title);
    if (!base) return '';
    return /(^|-)photos?(-|$)/.test(base) ? base : `${base}-photos`;
  };

  const altFor = (item) => {
    const suffix = ui.suffix.value.trim();
    const desc = item.desc.trim();
    if (!desc) return suffix;
    if (!suffix) return desc;
    return `${desc} ${suffix}`;
  };

  const setStatus = (text, isError = false) => {
    ui.status.textContent = text;
    ui.status.classList.toggle('error', isError);
  };

  const renderList = () => {
    ui.list.replaceChildren();
    items.forEach((item, index) => {
      const desc = el('input', {
        type: 'text',
        className: 'yagb-desc',
        value: item.desc,
        placeholder: msg.description,
        'aria-label': `${msg.description} ${index + 1}`,
      });
      const alt = el('span', { className: 'yagb-alt form-note' });
      const updateAlt = () => {
        alt.textContent = `${msg.altPreview} ${altFor(item) || '—'}`;
      };
      desc.addEventListener('input', () => {
        item.desc = desc.value;
        updateAlt();
      });
      updateAlt();

      const up = el('button', { type: 'button', className: 'yagb-up', title: msg.moveUp, 'aria-label': msg.moveUp, text: '↑' });
      const down = el('button', { type: 'button', className: 'yagb-down', title: msg.moveDown, 'aria-label': msg.moveDown, text: '↓' });
      const remove = el('button', { type: 'button', className: 'yagb-remove', title: msg.remove, 'aria-label': msg.remove, text: '×' });
      remove.hidden = Boolean(item.result);
      up.disabled = index === 0;
      down.disabled = index === items.length - 1;
      up.addEventListener('click', () => move(index, index - 1));
      down.addEventListener('click', () => move(index, index + 1));
      remove.addEventListener('click', () => {
        URL.revokeObjectURL(items[index].preview);
        items.splice(index, 1);
        renderList();
      });

      const li = el(
        'li',
        { draggable: 'true', 'data-index': String(index) },
        el('span', { className: 'yagb-num', text: String(index + 1).padStart(2, '0') }),
        el('img', { src: item.preview, alt: '', className: 'yagb-thumb' }),
        el('div', { className: 'yagb-text' }, desc, alt, el('span', { className: 'yagb-file form-note', text: `${item.file.name} · ${humanSize(item.file.size)}` })),
        el('div', { className: 'yagb-actions' }, up, down, remove),
      );
      li.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('text/plain', String(index));
        e.dataTransfer.effectAllowed = 'move';
        li.classList.add('yagb-dragging');
      });
      li.addEventListener('dragend', () => li.classList.remove('yagb-dragging'));
      li.addEventListener('dragover', (e) => {
        if (e.dataTransfer.types.includes('Files')) return;
        e.preventDefault();
        li.classList.add('yagb-over');
      });
      li.addEventListener('dragleave', () => li.classList.remove('yagb-over'));
      li.addEventListener('drop', (e) => {
        if (e.dataTransfer.types.includes('Files')) return;
        e.preventDefault();
        e.stopPropagation();
        li.classList.remove('yagb-over');
        const from = Number.parseInt(e.dataTransfer.getData('text/plain'), 10);
        if (!Number.isNaN(from)) move(from, index);
      });
      ui.list.append(li);
    });
    ui.insert.disabled = items.length === 0 || busy;
  };

  const move = (from, to) => {
    if (to < 0 || to >= items.length || from === to) return;
    const [item] = items.splice(from, 1);
    items.splice(to, 0, item);
    renderList();
    ui.list.querySelectorAll('li')[to]?.querySelector('.yagb-desc')?.focus();
  };

  const addFiles = (files) => {
    const problems = [];
    for (const file of files) {
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        problems.push(fmt(msg.notImage, file.name));
        continue;
      }
      // Files under the server limit, or that will be resized, are accepted
      if (cfg.maxUpload > 0 && file.size > cfg.maxUpload && Number(ui.max.value) === 0) {
        problems.push(fmt(msg.tooBig, file.name, humanSize(cfg.maxUpload)));
        continue;
      }
      items.push({ file, desc: '', preview: URL.createObjectURL(file) });
    }
    renderList();
    setStatus(problems.join(' '), problems.length > 0);
  };

  const buildDialog = () => {
    dialog = el('dialog', { id: 'yagb-dialog', className: 'yagb-dialog', 'aria-labelledby': 'yagb-title' });

    const field = (id, label, input, help) =>
      el('p', { className: 'yagb-field' }, el('label', { for: id, text: label }), input, help ? el('span', { className: 'form-note', text: help }) : null);

    const heading = el('input', { type: 'text', id: 'yagb-heading', size: '60' });
    const folder = el('input', { type: 'text', id: 'yagb-folder', list: 'yagb-folders', size: '30', value: cfg.folder });
    const folders = el('datalist', { id: 'yagb-folders' });
    for (const f of cfg.folders) folders.append(el('option', { value: f }));
    const basename = el('input', { type: 'text', id: 'yagb-basename', size: '40' });
    const suffix = el('input', { type: 'text', id: 'yagb-suffix', size: '60' });
    suffix.addEventListener('input', () => renderList());

    const max = el('select', { id: 'yagb-max' });
    const sizes = [...new Set([cfg.maxSize, 1200, 1600, 2000, 2400].filter((v) => v > 0))].sort((a, b) => a - b);
    for (const s of sizes) max.append(el('option', { value: String(s), text: `${s} px`, selected: s === cfg.maxSize }));
    max.append(el('option', { value: '0', text: msg.keep, selected: cfg.maxSize === 0 }));

    const files = el('input', { type: 'file', id: 'yagb-files', multiple: true, accept: 'image/jpeg,image/png,image/webp', className: 'yagb-input', tabindex: '-1', 'aria-hidden': 'true' });
    const chooser = el('button', { type: 'button', className: 'yagb-choose', text: msg.choose });
    chooser.addEventListener('click', () => files.click());
    const drop = el('div', { className: 'yagb-drop' }, el('p', { text: msg.drop }), chooser, files);
    files.addEventListener('change', () => {
      addFiles([...files.files]);
      files.value = '';
    });
    drop.addEventListener('dragover', (e) => {
      if (!e.dataTransfer.types.includes('Files')) return;
      e.preventDefault();
      drop.classList.add('yagb-drop-over');
    });
    drop.addEventListener('dragleave', () => drop.classList.remove('yagb-drop-over'));
    drop.addEventListener('drop', (e) => {
      if (!e.dataTransfer.types.includes('Files')) return;
      e.preventDefault();
      drop.classList.remove('yagb-drop-over');
      addFiles([...e.dataTransfer.files]);
    });

    const list = el('ol', { className: 'yagb-list' });
    const status = el('p', { className: 'yagb-status', 'aria-live': 'polite' });
    const progress = el('progress', { className: 'yagb-progress', max: '100', value: '0', hidden: true });
    const insertBtn = el('button', { type: 'button', className: 'submit', text: msg.insert });
    const closeBtn = el('button', { type: 'button', className: 'yagb-close', title: msg.close, 'aria-label': msg.close, text: '×' });

    dialog.append(
      el('div', { className: 'yagb-header' }, el('h3', { id: 'yagb-title', text: msg.title }), closeBtn),
      el(
        'div',
        { className: 'yagb-fields' },
        cfg.heading ? field('yagb-heading', msg.heading, heading) : null,
        field('yagb-folder', msg.folder, folder, msg.folderHelp),
        folders,
        field('yagb-basename', msg.basename, basename, msg.basenameHelp),
        field('yagb-suffix', msg.suffix, suffix, msg.suffixHelp),
        field('yagb-max', msg.maxSize, max),
      ),
      drop,
      list,
      progress,
      status,
      el('p', { className: 'form-buttons yagb-buttons' }, insertBtn),
    );
    document.body.append(dialog);

    ui = { heading, folder, basename, suffix, max, list, status, progress, insert: insertBtn };

    const dismiss = () => {
      if (busy) return;
      // Keep photos that were already uploaded: insert them rather than leave them unused
      if (items.some((item) => item.result)) finish();
      else dialog.close();
    };
    closeBtn.addEventListener('click', dismiss);
    // Esc does the same as the close button
    dialog.addEventListener('cancel', (e) => {
      e.preventDefault();
      dismiss();
    });
    dialog.addEventListener('close', () => {
      for (const item of items) URL.revokeObjectURL(item.preview);
      items = [];
    });
    insertBtn.addEventListener('click', () => run());
  };

  const open = (tb, mode) => {
    if (!dialog) buildDialog();
    target = capture(tb, mode);
    items = [];
    ui.heading.value = '';
    ui.basename.value = defaultBasename();
    ui.progress.hidden = true;
    setStatus('');
    renderList();
    dialog.showModal();
    (cfg.heading ? ui.heading : ui.folder).focus();
  };

  const uploadOne = async (item, index, common) => {
    const blob = await prepare(item.file, common.max, cfg.quality);
    const form = new FormData();
    form.append('f', 'yagbUpload');
    form.append('json', '1');
    form.append('xd_check', dotclear.nonce);
    form.append('file', blob, item.file.name);
    form.append('folder', common.folder);
    form.append('basename', common.basename);
    form.append('index', String(index + 1));
    form.append('title', altFor(item));
    form.append('thumb', cfg.thumbSize);
    if (common.postId) form.append('post_id', common.postId);

    const response = await fetch(dotclear.servicesUri, { method: 'POST', body: form, credentials: 'same-origin' });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const json = await response.json();
    if (!json.success) throw new Error(json.message || 'error');
    return json.payload;
  };

  const setControls = (enabled) => {
    for (const control of dialog.querySelectorAll('input, select, .yagb-actions button, .yagb-choose, .yagb-close')) {
      control.disabled = !enabled;
      control.classList.toggle('disabled', !enabled);
    }
  };

  const finish = () => {
    const photos = items.filter((item) => item.result).map((item) => ({ ...item.result, alt: altFor(item) }));
    if (photos.length > 0) insert(target, buildHTML(ui.heading.value.trim(), photos));
    dialog.close();
  };

  const run = async () => {
    if (busy) return;
    if (items.length === 0) {
      setStatus(msg.noPhotos, true);
      return;
    }
    const basename = slug(ui.basename.value);
    if (!basename) {
      setStatus(msg.noBasename, true);
      ui.basename.focus();
      return;
    }
    ui.basename.value = basename;
    const missing = items.filter((item) => altFor(item) === '').length;
    if (missing > 0 && !globalThis.confirm(fmt(msg.emptyAlt, String(missing)))) return;

    const idField = postField('id');
    const common = {
      folder: ui.folder.value.trim(),
      basename,
      max: Number(ui.max.value),
      postId: idField && /^\d+$/.test(idField.value) ? idField.value : '',
    };

    busy = true;
    ui.insert.disabled = true;
    setControls(false);
    ui.progress.hidden = false;
    ui.progress.max = items.length;

    // Photos already uploaded by a previous attempt are not sent again
    for (let i = 0; i < items.length; i++) {
      const item = items[i];
      ui.progress.value = i;
      if (item.result) continue;
      setStatus(fmt(msg.uploading, String(i + 1), String(items.length)));
      try {
        item.result = await uploadOne(item, i, common);
      } catch (e) {
        busy = false;
        setControls(true);
        renderList();
        setStatus(`${fmt(msg.failed, item.file.name, e.message)} ${msg.retry}`, true);
        return;
      }
    }
    ui.progress.value = items.length;
    busy = false;
    finish();
  };

  /* Toolbar button
  -------------------------------------------------------- */
  // This script may load before the editor's own scripts: add the button as soon as
  // dotclear.ToolBar exists, and before the editor draws its toolbars (on DOM ready).
  const setup = () => {
    if (dotclear.ToolBar === undefined || dotclear.ToolBar.prototype.elements.yagb) return;
    dotclear.ToolBar.prototype.elements.yagb = {
      group: 'media',
      type: 'button',
      title: msg.button,
      icon: cfg.icon,
      icon_dark: cfg.iconDark,
      context: 'post',
      fn: {},
    };
    for (const mode of ['xhtml', 'wysiwyg', 'wiki', 'markdown']) {
      dotclear.ToolBar.prototype.elements.yagb.fn[mode] = function () {
        open(this, mode);
      };
    }
  };

  if (dotclear.ToolBar === undefined) {
    // Registered now, so it runs before the editor's own ready handler
    dotclear.ready(setup);
  } else {
    setup();
  }
})();
