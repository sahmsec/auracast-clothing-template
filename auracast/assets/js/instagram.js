(() => {
  'use strict';
  document.querySelectorAll('[data-instagram-feed]').forEach(feed => {
    const items = JSON.parse(feed.querySelector('.instagram-feed-data').textContent);
    const viewport = feed.querySelector('.instagram-marquee');
    const track = feed.querySelector('.instagram-marquee-track');
    const originals = [...track.children];
    if (!items.length || !originals.length) return;
    const before = document.createDocumentFragment(), after = document.createDocumentFragment();
    originals.forEach(card => {
      for (const fragment of [before, after]) {
        const clone = card.cloneNode(true); clone.setAttribute('aria-hidden', 'true'); clone.tabIndex = -1; fragment.append(clone);
      }
    });
    track.prepend(before); track.append(after);
    const cards = [...track.children];
    cards.forEach((card, index) => card.style.setProperty('--reel-position', index));
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    let width = 0, height = 0, stride = 0, cycle = 0, offset = 0;
    let visible = false, paused = motion.matches, hovered = false, focused = false, dragging = false, modalOpen = false, frame = 0, last = 0;
    let startX = 0, startOffset = 0, moved = false, suppressClick = false, lastVideoUpdate = 0;
    const speed = Number(feed.dataset.speed) || 0;
    const pause = feed.querySelector('.instagram-pause');
    const playIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m8 4 13 8-13 8Z"/></svg>';
    const pauseIcon = pause.innerHTML;
    function pauseLabel() { pause.setAttribute('aria-pressed', String(paused)); pause.setAttribute('aria-label', paused ? 'Resume scrolling' : 'Pause scrolling'); pause.innerHTML = paused ? playIcon : pauseIcon; }
    pauseLabel();
    function draw() {
      track.style.transform = `translate3d(${-offset}px,0,0)`;
      cards.forEach((card, index) => {
        const distance = Math.abs(index * stride - offset + width / 2 - viewport.clientWidth / 2) / stride;
        card.style.transform = `scale(${1 - .15 * Math.min(1, distance)})`;
        card.style.zIndex = String(100 - Math.min(99, Math.round(distance * 10)));
      });
    }
    function normalize() { if (!cycle) return; while (offset < cycle - viewport.clientWidth / 2) offset += cycle; while (offset >= cycle * 2 - viewport.clientWidth / 2) offset -= cycle; }
    function layout() {
      const previousStride = stride, previousOffset = offset;
      const mobile = innerWidth < 768;
      width = Math.min(485, viewport.clientWidth * (mobile ? .374 : .44));
      height = mobile ? width * 16 / 9 : width * 1.25;
      stride = width * (mobile ? .836 : .827); cycle = stride * items.length;
      viewport.style.setProperty('--reel-width', `${width}px`); viewport.style.setProperty('--reel-height', `${height}px`); viewport.style.setProperty('--reel-stride', `${stride}px`);
      offset = previousStride ? previousOffset * stride / previousStride : cycle - (viewport.clientWidth - width) / 2;
      normalize(); draw(); updateVideos();
    }
    function updateVideos() {
      cards.forEach((card, index) => {
        const video = card.querySelector('video'); if (!video) return;
        const x = index * stride - offset;
        const onScreen = visible && !document.hidden && !modalOpen && !motion.matches && x + width > 0 && x < viewport.clientWidth;
        if (!onScreen) { video.pause(); return; }
        if (!video.src) {
          video.src = video.dataset.reelVideo; video.muted = true;
          video.addEventListener('playing', () => card.classList.add('is-playing'));
          video.addEventListener('error', () => card.classList.remove('is-playing'));
        }
        if (video.paused) video.play().catch(() => {});
      });
    }
    function tick(now) {
      frame = 0;
      const delta = last ? Math.min((now - last) / 1000, .1) : 0; last = now;
      if (!paused && !hovered && !focused && !dragging && !modalOpen && speed && items.length > 1) { offset += delta * speed; normalize(); draw(); }
      if (now - lastVideoUpdate > 350) { updateVideos(); lastVideoUpdate = now; }
      if (visible && !document.hidden) frame = requestAnimationFrame(tick);
    }
    function start() { if (visible && !document.hidden && !frame) { last = 0; frame = requestAnimationFrame(tick); } }
    new ResizeObserver(layout).observe(viewport);
    new IntersectionObserver(entries => { visible = entries[0].isIntersecting; if (!visible && frame) { cancelAnimationFrame(frame); frame = 0; } updateVideos(); start(); }, {threshold: .05}).observe(viewport);
    document.addEventListener('visibilitychange', () => { updateVideos(); start(); });
    motion.addEventListener('change', () => { paused = motion.matches; pauseLabel(); updateVideos(); });
    viewport.addEventListener('mouseenter', () => { hovered = true; }); viewport.addEventListener('mouseleave', () => { hovered = false; });
    feed.addEventListener('focusin', event => { focused = event.target.matches(':focus-visible'); }); feed.addEventListener('focusout', () => { requestAnimationFrame(() => { focused = feed.contains(document.activeElement) && document.activeElement.matches(':focus-visible'); }); });
    originals.forEach(card => card.addEventListener('focus', () => {
      if (!card.matches(':focus-visible')) return;
      offset = cards.indexOf(card) * stride - (viewport.clientWidth - width) / 2; viewport.scrollLeft = 0; draw(); updateVideos();
    }));
    pause.addEventListener('click', () => { paused = !paused; pauseLabel(); });
    function advance(direction) { offset += stride * direction; normalize(); draw(); updateVideos(); }
    feed.querySelector('.instagram-previous').addEventListener('click', () => advance(-1)); feed.querySelector('.instagram-next').addEventListener('click', () => advance(1));
    viewport.addEventListener('keydown', event => { if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') { event.preventDefault(); advance(event.key === 'ArrowRight' ? 1 : -1); } });
    viewport.addEventListener('pointerdown', event => { if (event.button !== 0) return; dragging = true; moved = false; startX = event.clientX; startOffset = offset; });
    viewport.addEventListener('pointermove', event => { if (!dragging) return; const distance = event.clientX - startX; if (Math.abs(distance) > 6) { moved = true; viewport.classList.add('is-dragging'); viewport.setPointerCapture(event.pointerId); offset = startOffset - distance; normalize(); draw(); } });
    function endDrag(event) { if (!dragging) return; dragging = false; viewport.classList.remove('is-dragging'); if (viewport.hasPointerCapture(event.pointerId)) viewport.releasePointerCapture(event.pointerId); suppressClick = moved; if (moved) setTimeout(() => { suppressClick = false; }, 0); updateVideos(); }
    viewport.addEventListener('pointerup', endDrag); viewport.addEventListener('pointercancel', endDrag);
    const dialog = document.createElement('dialog'); dialog.className = 'instagram-reel-dialog'; dialog.setAttribute('aria-label', 'Instagram post or reel'); document.body.append(dialog);
    let trigger = null;
    function close() { if (dialog.open) dialog.close(); }
    dialog.addEventListener('close', () => { modalOpen = false; dialog.querySelector('video')?.pause(); dialog.replaceChildren(); if (!document.querySelector('dialog[open]')) document.body.classList.remove('dialog-open'); trigger?.focus({preventScroll:true}); updateVideos(); });
    dialog.addEventListener('click', event => { if (event.target !== dialog) return; const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) close(); });
    function open(index, card) {
      const item = items[index]; if (!item) return;
      trigger = card; modalOpen = true; updateVideos();
      document.querySelectorAll('dialog[open]').forEach(other => other.close());
      dialog.replaceChildren();
      const closeButton = document.createElement('button'); closeButton.type = 'button'; closeButton.className = 'instagram-reel-dialog-close'; closeButton.setAttribute('aria-label', 'Close reel'); closeButton.textContent = '\u00d7'; closeButton.addEventListener('click', close);
      const content = document.createElement('div'); content.className = 'instagram-reel-dialog-content';
      const player = document.createElement('div'); player.className = 'instagram-reel-player';
      if (item.video) {
        const video = document.createElement('video'); video.src = item.video; video.controls = true; video.playsInline = true; video.autoplay = true; if (item.cover) video.poster = item.cover; player.append(video);
      } else if (item.link) {
        const embed = document.createElement('iframe'); embed.src = item.link + 'embed/'; embed.title = item.title; embed.allow = 'autoplay; encrypted-media'; embed.referrerPolicy = 'strict-origin-when-cross-origin'; player.append(embed);
      } else {
        const image = document.createElement('img'); image.src = item.cover; image.alt = item.title; player.append(image);
      }
      const details = document.createElement('div'); details.className = 'instagram-reel-details';
      if (item.caption) { const caption = document.createElement('p'); caption.textContent = item.caption; details.append(caption); }
      if (item.link) { const link = document.createElement('a'); link.href = item.link; link.target = '_blank'; link.rel = 'noopener noreferrer'; link.textContent = 'View on Instagram'; details.append(link); }
      content.append(player, details); dialog.append(closeButton, content); dialog.showModal(); document.body.classList.add('dialog-open');
    }
    viewport.addEventListener('click', event => { if (suppressClick) { event.preventDefault(); return; } const card = event.target.closest('[data-reel-index]'); if (card) open(Number(card.dataset.reelIndex), card); });
    layout();
  });
})();
