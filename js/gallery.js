/* Yet Another Gallery Builder: lightbox for galleries inserted with the plugin */
'use strict';

(() => {
  const script = document.currentScript;
  const cls = (script && script.dataset.gallery) || 'photo-gallery';
  const labels = {
    prev: (script && script.dataset.prev) || 'Previous image',
    next: (script && script.dataset.next) || 'Next image',
    close: (script && script.dataset.close) || 'Close',
    of: (script && script.dataset.of) || '%1$s of %2$s',
  };

  const init = () => {
    const galleries = [...document.querySelectorAll(`ul.${CSS.escape(cls)}`)];
    if (!galleries.length) return;

    const box = document.createElement('div');
    box.className = 'yagb-lightbox';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    const mk = (tag, className, text, label) => {
      const n = document.createElement(tag);
      if (className) n.className = className;
      if (text) n.textContent = text;
      if (label) n.setAttribute('aria-label', label);
      if (tag === 'button') n.type = 'button';
      return n;
    };
    const prev = mk('button', 'yagb-prev', '‹', labels.prev);
    const img = mk('img');
    const next = mk('button', 'yagb-next', '›', labels.next);
    const close = mk('button', 'yagb-close', '×', labels.close);
    const caption = mk('p');
    box.append(prev, img, next, close, caption);
    document.body.append(box);

    let links = [];
    let at = 0;
    let lastFocus = null;

    const show = (i) => {
      at = (i + links.length) % links.length;
      const a = links[at];
      const thumb = a.querySelector('img');
      const alt = thumb ? thumb.alt : '';
      img.src = a.getAttribute('href');
      img.alt = alt;
      const count = labels.of.replace('%1$s', at + 1).replace('%2$s', links.length);
      caption.textContent = alt ? `${alt} (${count})` : count;
      prev.hidden = next.hidden = links.length < 2;
    };
    const open = (group, i, from) => {
      links = group;
      lastFocus = from;
      show(i);
      box.classList.add('is-open');
      document.documentElement.style.overflow = 'hidden';
      close.focus();
    };
    const shut = () => {
      box.classList.remove('is-open');
      document.documentElement.style.overflow = '';
      img.removeAttribute('src');
      if (lastFocus) lastFocus.focus();
    };

    for (const gallery of galleries) {
      const group = [...gallery.querySelectorAll('a[href]')];
      group.forEach((a, i) => {
        a.addEventListener('click', (e) => {
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button) return;
          e.preventDefault();
          open(group, i, a);
        });
      });
    }

    prev.addEventListener('click', () => show(at - 1));
    next.addEventListener('click', () => show(at + 1));
    close.addEventListener('click', shut);
    box.addEventListener('click', (e) => {
      if (e.target === box) shut();
    });
    document.addEventListener('keydown', (e) => {
      if (!box.classList.contains('is-open')) return;
      if (e.key === 'Escape') shut();
      else if (e.key === 'ArrowLeft') show(at - 1);
      else if (e.key === 'ArrowRight') show(at + 1);
      else if (e.key === 'Tab') {
        // keep focus inside the lightbox
        const focusables = [prev, next, close].filter((b) => !b.hidden);
        const idx = focusables.indexOf(document.activeElement);
        e.preventDefault();
        const step = e.shiftKey ? -1 : 1;
        focusables[(idx + step + focusables.length) % focusables.length].focus();
      }
    });
    let startX = null;
    box.addEventListener('touchstart', (e) => {
      startX = e.touches.length === 1 ? e.touches[0].clientX : null;
    }, { passive: true });
    box.addEventListener('touchend', (e) => {
      if (startX === null) return;
      const dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 50) show(at + (dx < 0 ? 1 : -1));
      startX = null;
    });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
