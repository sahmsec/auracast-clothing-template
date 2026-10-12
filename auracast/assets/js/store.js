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
    if (id === 'search-dialog') dialog.style.top = `${Math.max(0, document.querySelector('.site-header')?.getBoundingClientRect().top || 0)}px`;
    dialog.showModal(); document.body.classList.add('dialog-open');
    if (id === 'search-dialog') document.getElementById('product-search')?.focus();
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
  let miniCartBusy = false;
  async function updateMiniCart(item, quantity) {
    if (miniCartBusy || !item) return;
    const input = item.querySelector('.mini-cart-quantity-input');
    if (!Number.isInteger(quantity) || quantity < 0 || (input.max && quantity > Number(input.max))) { toast('Choose an available quantity.'); return; }
    miniCartBusy = true; item.setAttribute('aria-busy', 'true');
    const buttons = item.querySelectorAll('button,input'); buttons.forEach(button => button.disabled = true);
    try {
      const response = await fetch(config.ajaxUrl, {method:'POST',credentials:'same-origin',body:new URLSearchParams({action:'auracast_update_mini_cart',nonce:config.nonce,key:item.dataset.miniItem,quantity:String(quantity)})});
      const result = await response.json();
      if (!response.ok || !result.fragments) throw new Error(result.data?.message || 'Could not update your cart.');
      for (const [selector,html] of Object.entries(result.fragments)) document.querySelectorAll(selector).forEach(element => { element.outerHTML = html; });
      if (window.jQuery) window.jQuery(document.body).trigger('wc_fragments_refreshed');
    } catch (error) { toast(error.message); }
    finally { miniCartBusy = false; item.removeAttribute('aria-busy'); buttons.forEach(button => button.disabled = false); }
  }
  document.addEventListener('change', event => {
    if (event.target.matches('.mini-cart-quantity-input')) updateMiniCart(event.target.closest('[data-mini-item]'), Number(event.target.value));
  });
  document.addEventListener('click', async event => {
    const communityImage = event.target.closest('[data-community-image]');
    if (communityImage && openDialog('community-image-dialog')) { event.preventDefault(); document.querySelector('#community-image-dialog img').src = communityImage.dataset.communityImage; }
    const miniQuantity = event.target.closest('[data-mini-quantity]');
    if (miniQuantity) { const item = miniQuantity.closest('[data-mini-item]'); const input = item.querySelector('.mini-cart-quantity-input'); await updateMiniCart(item, Number(input.value) + Number(miniQuantity.dataset.miniQuantity)); }
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
  document.querySelectorAll('[data-model-view]').forEach(input => input.addEventListener('change', () => {
    document.querySelector('.woocommerce-main')?.classList.toggle('show-model-images', input.checked);
  }));
  document.querySelectorAll('.collection-facet').forEach(facet => {
    facet.addEventListener('toggle', () => {
      if (facet.open) document.querySelectorAll('.collection-facet[open]').forEach(other => { if (other !== facet) other.open = false; });
    });
    facet.querySelector('[data-close-facet]')?.addEventListener('click', () => { facet.open = false; facet.querySelector('summary').focus(); });
  });
  document.addEventListener('click', event => {
    document.querySelectorAll('.collection-facet[open]').forEach(facet => { if (!facet.contains(event.target)) facet.open = false; });
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      document.querySelectorAll('.collection-facet[open]').forEach(facet => { facet.open = false; facet.querySelector('summary').focus(); });
      const search = document.getElementById('search-dialog');
      if (search?.open) { event.preventDefault(); closeDialog(search); }
    }
  });
  document.querySelectorAll('[data-product-rail]').forEach(rail => {
    const dots = document.querySelector(`[data-rail-dots="${rail.id}"]`);
    if (!dots) return;
    const count = Math.ceil(rail.children.length / 4);
    for (let i = 0; i < count; i++) {
      const button = document.createElement('button');
      button.type = 'button'; button.setAttribute('aria-label', `Product page ${i + 1}`);
      button.addEventListener('click', () => {
        const card = rail.children[i * 4];
        if (card) rail.scrollTo({left: card.offsetLeft - rail.children[0].offsetLeft, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
      });
      dots.append(button);
    }
    const sync = () => {
      const secondPage = rail.children[4];
      const stride = secondPage ? secondPage.offsetLeft - rail.children[0].offsetLeft : rail.clientWidth;
      const active = Math.max(0, Math.min(count - 1, Math.round(rail.scrollLeft / Math.max(1, stride))));
      [...dots.children].forEach((dot, i) => { dot.classList.toggle('is-active', i === active); dot.setAttribute('aria-current', String(i === active)); });
    };
    rail.addEventListener('scroll', sync, {passive: true}); window.addEventListener('resize', sync); sync();
    rail.addEventListener('keydown', event => {
      if (event.target !== rail || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
      event.preventDefault(); rail.scrollBy({left: (event.key === 'ArrowRight' ? 1 : -1) * (rail.clientWidth - 16), behavior: 'smooth'});
    });
  });
  const searchInput = document.getElementById('product-search');
  if (searchInput) {
    const results = document.createElement('div');
    results.id = 'predictive-search-results'; results.className = 'predictive-search-results'; results.hidden = true; results.setAttribute('aria-live', 'polite');
    searchInput.closest('.search-form').append(results); searchInput.setAttribute('aria-controls', results.id); searchInput.setAttribute('aria-expanded', 'false');
    let searchTimer, searchController;
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer); searchController?.abort();
      const query = searchInput.value.trim();
      if (query.length < 2) { results.hidden = true; results.replaceChildren(); searchInput.setAttribute('aria-expanded', 'false'); return; }
      searchTimer = setTimeout(async () => {
        searchController = new AbortController();
        try { const data = await request('auracast_search_products', {query}, searchController.signal); results.innerHTML = data.html; results.hidden = !data.html; searchInput.setAttribute('aria-expanded', String(!!data.html)); }
        catch (error) { if (error.name !== 'AbortError') { results.hidden = true; searchInput.setAttribute('aria-expanded', 'false'); } }
      }, 200);
    });
    document.getElementById('search-dialog').addEventListener('close', () => { clearTimeout(searchTimer); searchController?.abort(); });
  }
  const syncEmptyCounts = () => document.querySelectorAll('.cart-count').forEach(count => count.toggleAttribute('data-empty', count.textContent.trim() === '0'));
  new MutationObserver(syncEmptyCounts).observe(document.querySelector('.header-actions') || document.body, {childList:true,subtree:true,characterData:true});
  syncEmptyCounts();
  if (config.popupEnabled && !storage.get('auracast-newsletter-seen')) {
    setTimeout(() => { if (!document.querySelector('dialog[open]')) { openDialog('newsletter-dialog'); storage.set('auracast-newsletter-seen', true); } }, config.popupDelay || 8000);
  }
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart', () => { document.querySelectorAll('dialog[open]').forEach(closeDialog); openDialog('cart-dialog'); toast('Added to your shopping bag'); });
  }
  document.querySelectorAll('.mobile-nav .menu-item-has-children > a').forEach(a => a.setAttribute('aria-expanded', 'false'));
  syncWishlist(); loadWishlist();
})();
