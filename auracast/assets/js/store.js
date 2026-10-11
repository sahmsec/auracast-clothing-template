/* Store enhancements. Core cart and checkout remain native WooCommerce. */
(() => {
  'use strict';
  const config = window.auracast || {};
  const storage = {
    get(key, fallback = null) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } },
    set(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* Storage may be unavailable in private browsing. */ } },
  };
  let wishlist = storage.get('auracast-wishlist', []);
  if (!Array.isArray(wishlist)) wishlist = [];
  wishlist = [...new Set(wishlist.map(Number).filter(id => Number.isSafeInteger(id) && id > 0))].slice(0, 100);
  let toastTimer;
  let quickViewController;
  const toast = message => {
    const element = document.querySelector('.toast');
    if (!element) return;
    element.textContent = message; element.classList.add('visible');
    clearTimeout(toastTimer); toastTimer = setTimeout(() => element.classList.remove('visible'), 3300);
  };
  function syncWishlist() {
    document.querySelectorAll('[data-wishlist]').forEach(button => {
      const saved = wishlist.includes(Number(button.dataset.wishlist));
      button.setAttribute('aria-pressed', String(saved));
    });
  }
  function closeDialog(dialog) {
    if (!dialog?.open) return;
    dialog.close();
    if (!document.querySelector('dialog[open]')) document.body.classList.remove('dialog-open');
  }
  function openDialog(id) {
    const dialog = document.getElementById(id);
    if (!dialog || typeof dialog.showModal !== 'function') return false;
    document.querySelectorAll('dialog[open]').forEach(closeDialog);
    dialog.showModal(); document.body.classList.add('dialog-open');
    return true;
  }
  async function request(action, values = {}, signal) {
    const body = new URLSearchParams({action, nonce: config.nonce || ''});
    for (const [key, value] of Object.entries(values)) {
      if (Array.isArray(value)) value.forEach(item => body.append(`${key}[]`, item));
      else body.set(key, value);
    }
    const response = await fetch(config.ajaxUrl, {method: 'POST', body, signal, credentials: 'same-origin'});
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.data?.message || config.labels?.error || 'Please try again.');
    return result.data;
  }
  async function loadWishlist() {
    const target = document.getElementById('wishlist-products');
    if (!target) return;
    const empty = document.getElementById('wishlist-empty');
    if (!wishlist.length) { target.replaceChildren(); empty.hidden = false; return; }
    empty.hidden = true;
    try {
      const result = await request('auracast_wishlist_products', {ids: wishlist});
      target.innerHTML = result.html; syncWishlist();
      if (!target.querySelector('.product-card')) empty.hidden = false;
    } catch (error) { target.textContent = error.message; }
  }
  document.addEventListener('click', async event => {
    const scroll = event.target.closest('[data-scroll-target]');
    if (scroll) { const target=document.getElementById(scroll.dataset.scrollTarget); target?.scrollBy({left:Number(scroll.dataset.direction)*target.clientWidth*.7,behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'}); }
    const dismiss = event.target.closest('[data-dismiss]');
    if (dismiss) {
      const target = document.getElementById(dismiss.dataset.dismiss);
      if (target) target.hidden = true;
      storage.set('auracast-announcement-dismissed', true);
    }
    const trigger = event.target.closest('[data-dialog]');
    if (trigger && openDialog(trigger.dataset.dialog)) { event.preventDefault(); if (trigger.dataset.dialog === 'newsletter-dialog') storage.set('auracast-newsletter-seen', true); }
    const close = event.target.closest('[data-close]');
    if (close) closeDialog(close.closest('dialog'));
    const saved = event.target.closest('[data-wishlist]');
    if (saved) {
      const id = Number(saved.dataset.wishlist);
      if (wishlist.includes(id)) { wishlist = wishlist.filter(item => item !== id); toast(config.labels?.removed || 'Removed from your wishlist'); }
      else if (wishlist.length < 100) { wishlist.push(id); toast(config.labels?.saved || 'Saved to your wishlist'); }
      else { toast('Your wishlist is full. Remove a favorite to add another.'); return; }
      storage.set('auracast-wishlist', wishlist); syncWishlist();
      await loadWishlist();
    }
    const quick = event.target.closest('[data-quick-view]');
    if (quick) {
      const target = document.getElementById('quick-view-content');
      if (!openDialog('quick-view-dialog')) return;
      quickViewController?.abort(); quickViewController = new AbortController();
      target.textContent = 'Loading product…'; target.style.padding = '40px';
      try { const result = await request('auracast_quick_view', {product_id: quick.dataset.quickView}, quickViewController.signal); target.style.padding = ''; target.innerHTML = result.html; }
      catch (error) { if (error.name !== 'AbortError') target.textContent = error.message; }
    }
    const share = event.target.closest('[data-share]');
    if (share) {
      try {
        if (navigator.share) await navigator.share({title: document.title, url: share.dataset.share});
        else if (navigator.clipboard) { await navigator.clipboard.writeText(share.dataset.share); toast('Product link copied'); }
        else { window.prompt('Copy this product link:', share.dataset.share); }
      } catch (error) { if (error.name !== 'AbortError') toast('Could not share this link.'); }
    }
    const mobileParent = event.target.closest('.mobile-nav .menu-item-has-children > a');
    if (mobileParent) { event.preventDefault(); const li = mobileParent.parentElement; li.classList.toggle('expanded'); mobileParent.setAttribute('aria-expanded', String(li.classList.contains('expanded'))); }
  });
  document.querySelectorAll('dialog').forEach(dialog => {
    dialog.addEventListener('click', event => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) closeDialog(dialog); } });
    dialog.addEventListener('close', () => { if (!document.querySelector('dialog[open]')) document.body.classList.remove('dialog-open'); if (dialog.id === 'newsletter-dialog') storage.set('auracast-newsletter-seen', true); });
    dialog.addEventListener('cancel', () => { if (dialog.id === 'quick-view-dialog') quickViewController?.abort(); });
  });
  document.querySelectorAll('.ajax-form').forEach(form => {
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (!form.reportValidity()) return;
      const button = form.querySelector('[type=submit]'), status = form.querySelector('.form-status');
      button.disabled = true; status.textContent = 'Sending…';
      try {
        const response = await fetch(config.ajaxUrl, {method: 'POST', body: new FormData(form), credentials: 'same-origin'});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data?.message || config.labels?.error || 'Please try again.');
        status.textContent = result.data.message; form.reset();
        if (form.dataset.formAction === 'auracast_newsletter') storage.set('auracast-newsletter-seen', true);
      } catch (error) { status.textContent = error.message; }
      finally { button.disabled = false; }
    });
  });
  if (storage.get('auracast-announcement-dismissed')) { const announcement = document.getElementById('announcement'); if (announcement) announcement.hidden = true; }
  if (config.popupEnabled && !storage.get('auracast-newsletter-seen')) {
    setTimeout(() => { if (!document.querySelector('dialog[open]')) { openDialog('newsletter-dialog'); storage.set('auracast-newsletter-seen', true); } }, config.popupDelay || 8000);
  }
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart', () => { document.querySelectorAll('dialog[open]').forEach(closeDialog); openDialog('cart-dialog'); toast('Added to your shopping bag'); });
  }
  document.querySelectorAll('.mobile-nav .menu-item-has-children > a').forEach(a => a.setAttribute('aria-expanded', 'false'));
  syncWishlist(); loadWishlist();
})();
