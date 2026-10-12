/* Native variation selection, product bundles, gallery and cart quantity controls. */
(() => {
  'use strict';
  const $ = window.jQuery;
  const form = document.querySelector('.auracast-product-form');
  const create = (tag, className, text) => { const node = document.createElement(tag); if (className) node.className = className; if (text !== undefined) node.textContent = text; return node; };
  const money = value => `${catalog?.currency || 'Tk'} ${Number(value).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 2})}`;
  let catalog;
  try { catalog = JSON.parse(document.getElementById('auracast-bundle-catalog')?.textContent || 'null'); } catch { catalog = null; }
  const current = catalog?.products.find(product => product.id === catalog.current);
  let bundleQuantity = 1;
  let selectedSlot = 0;
  let entries = [];
  let chosenProducts = new Map();
  const discountFor = qty => qty >= 5 ? 15 : qty >= 4 ? 12 : qty >= 3 ? 10 : qty >= 2 ? 5 : 0;
  const variationLabel = variation => Object.values(variation.attributes).map(value => value.toUpperCase()).filter(Boolean).join(' / ');

  function selectedCurrent() {
    const variationId = Number(form?.querySelector('[name="variation_id"]')?.value || 0);
    const variant = current?.variants.find(variation => variation.id === variationId);
    return {id: current?.id, variation: variant?.id || 0, attributes: variant?.attributes || {}, custom: false};
  }
  function entryProduct(entry) { return catalog?.products.find(product => product.id === entry.id); }
  function entryPrice(entry) { const product = entryProduct(entry); return product?.variants.find(variant => variant.id === entry.variation)?.price ?? product?.price ?? 0; }
  function showStatus(message) {
    if (!form) return;
    let status = form.querySelector('.auracast-product-status');
    if (!status) { status = create('p', 'auracast-product-status'); status.setAttribute('role', 'alert'); form.querySelector('.auracast-single-buttons')?.after(status); }
    status.textContent = message;
  }
  function synchronizeVariationChips() {
    if (!form) return;
    form.querySelectorAll('.auracast-attribute').forEach(group => {
      const select = group.querySelector('select');
      if (!select) return;
      group.querySelectorAll('[data-variation-option]').forEach(button => {
        const selected = button.dataset.variationOption === select.value;
        const option = Array.from(select.options).find(item => item.value === button.dataset.variationOption);
        button.classList.toggle('is-selected', selected);
        button.setAttribute('aria-pressed', String(selected));
        button.disabled = !option || option.disabled;
      });
    });
    document.querySelectorAll('[data-bundle-size-chips] [data-mirror-size]').forEach(button => {
      const select = form.querySelector('.auracast-attribute select');
      button.classList.toggle('is-selected', button.dataset.mirrorSize === select?.value);
      button.setAttribute('aria-pressed', String(button.dataset.mirrorSize === select?.value));
    });
  }
  function refreshBundleEntries() {
    if (!current) return;
    const selection = selectedCurrent();
    entries = Array.from({length: Math.max(0, bundleQuantity - 1)}, (_, index) => chosenProducts.get(`${bundleQuantity}:${index}`) || {...selection});
    const hidden = form.querySelector('[name="auracast_bundle_items"]');
    const quantity = form.querySelector('input.qty');
    if (hidden) hidden.value = entries.length ? JSON.stringify(entries.map(({id, variation, attributes}) => ({id, variation, attributes}))) : '';
    if (quantity) quantity.value = '1';
    form.querySelectorAll('[data-bundle-items]').forEach(container => {
      const active = Number(container.dataset.bundleItems) === bundleQuantity && bundleQuantity > 1;
      container.hidden = !active;
      container.replaceChildren();
      if (!active) return;
      const rows = [{...selection}, ...entries];
      rows.forEach((entry, index) => {
        const product = entryProduct(entry);
        if (!product) return;
        const row = create('div', 'bundle-item-row');
        const img = create('img'); img.src = product.image; img.alt = product.name; row.append(img);
        const copy = create('div', 'bundle-item-copy'); copy.append(create('strong', '', product.name));
        if (product.variants.length) {
          const select = create('select'); select.setAttribute('aria-label', `Size for bundle item ${index + 1}`);
          const placeholder = create('option', '', 'Choose size'); placeholder.value = '0'; select.append(placeholder);
          product.variants.forEach(variant => { const option = create('option', '', variationLabel(variant)); option.value = variant.id; select.append(option); });
          select.value = String(entry.variation);
          select.addEventListener('change', () => {
            const variant = product.variants.find(item => item.id === Number(select.value));
            if (index === 0) {
              if (!variant) return;
              for (const [name, value] of Object.entries(variant.attributes)) {
                const native = form.querySelector(`select[name="${CSS.escape(name)}"]`);
                if (native) { native.value = value; if ($) $(native).trigger('change'); else native.dispatchEvent(new Event('change', {bubbles:true})); }
              }
            } else {
              chosenProducts.set(`${bundleQuantity}:${index - 1}`, {id: product.id, variation: variant?.id || 0, attributes: variant?.attributes || {}, custom: true});
              refreshBundleEntries();
            }
          });
          copy.append(select);
        }
        if (index > 0) { const change = create('button', '', 'Choose product'); change.type = 'button'; change.dataset.bundleSlot = String(index - 1); copy.append(change); }
        row.append(copy, create('span', '', money(entryPrice(entry) * (1 - discountFor(bundleQuantity)/100)))); container.append(row);
      });
    });
    const preview = form.querySelector('.auracast-bundle-duo-preview'); if (preview) preview.hidden = bundleQuantity === 2;
  }
  function selectBundle(quantity) {
    bundleQuantity = quantity;
    form.querySelectorAll('[data-bundle]').forEach(card => {
      const selected = Number(card.dataset.bundle) === quantity;
      card.classList.toggle('is-selected', selected);
      const radio = card.querySelector('input[type=radio]'); if (radio) radio.checked = selected;
    });
    showStatus(''); refreshBundleEntries();
  }
  function openProducts(slot) {
    selectedSlot = slot;
    const dialog = document.getElementById('auracast-bundle-products');
    if (!dialog || !catalog) return;
    renderProductChooser(''); dialog.querySelector('[data-bundle-search]').value = '';
    document.querySelectorAll('dialog[open]').forEach(item => item.close());
    dialog.showModal(); document.body.classList.add('dialog-open');
  }
  function renderProductChooser(query) {
    const target = document.querySelector('[data-bundle-products]'); if (!target) return;
    target.replaceChildren();
    catalog.products.filter(product => product.name.toLowerCase().includes(query.toLowerCase())).forEach(product => {
      const card = create('article', 'bundle-catalog-product');
      const img = create('img'); img.src = product.image; img.alt = product.name; img.loading = 'lazy'; card.append(img, create('h3', '', product.name), create('p', '', money(product.price)));
      let select;
      if (product.variants.length) {
        select = create('select'); select.setAttribute('aria-label', `Choose size for ${product.name}`);
        product.variants.forEach(variant => { const option = create('option', '', variationLabel(variant)); option.value = variant.id; select.append(option); }); card.append(select);
      }
      const add = create('button', '', 'Choose product'); add.type = 'button';
      add.addEventListener('click', () => {
        const variant = product.variants.find(item => item.id === Number(select?.value));
        chosenProducts.set(`${bundleQuantity}:${selectedSlot}`, {id: product.id, variation: variant?.id || 0, attributes: variant?.attributes || {}, custom: true});
        refreshBundleEntries(); document.getElementById('auracast-bundle-products').close();
      });
      card.append(add); target.append(card);
    });
    if (!target.children.length) target.append(create('p', '', 'No products found.'));
  }

  if (form) {
    form.querySelectorAll('[data-variation-option]').forEach(button => button.addEventListener('click', () => {
      const select = button.closest('.auracast-attribute').querySelector('select');
      select.value = button.dataset.variationOption;
      if ($) $(select).trigger('change'); else select.dispatchEvent(new Event('change', {bubbles:true}));
      showStatus(''); synchronizeVariationChips();
    }));
    form.querySelectorAll('.auracast-attribute select').forEach(select => {
      select.addEventListener('change', synchronizeVariationChips);
      if (!select.value) { const firstChip = select.closest('.auracast-attribute').querySelector('[data-variation-option]'); const first = [...select.options].find(option => option.value === firstChip?.dataset.variationOption && !option.disabled) || [...select.options].find(option => option.value && !option.disabled); if (first) select.value = first.value; }
    });
    const nativeSize = form.querySelector('.auracast-attribute');
    const mirror = form.querySelector('[data-bundle-size-chips]');
    nativeSize?.querySelectorAll('[data-variation-option]').forEach(button => {
      const copy = create('button', '', button.textContent); copy.type = 'button'; copy.dataset.mirrorSize = button.dataset.variationOption;
      copy.addEventListener('click', () => button.click()); mirror?.append(copy);
    });
    form.querySelectorAll('input[name="auracast_bundle"]').forEach(radio => radio.addEventListener('change', () => selectBundle(Number(radio.value))));
    form.addEventListener('click', event => {
      const choose = event.target.closest('[data-bundle-choose]');
      if (choose) { selectBundle(Number(choose.dataset.bundleChoose)); openProducts(0); }
      const slot = event.target.closest('[data-bundle-slot]'); if (slot) openProducts(Number(slot.dataset.bundleSlot));
      const card = event.target.closest('[data-bundle]');
      if (card && !event.target.closest('button,select,input,label,a') && Number(card.dataset.bundle) !== bundleQuantity) selectBundle(Number(card.dataset.bundle));
      const purchase = event.target.closest('[data-product-purchase]');
      if (!purchase) return;
      const mode = purchase.dataset.productPurchase;
      const single = mode.startsWith('single');
      if (single) selectBundle(1);
      const buyInput = form.querySelector('[name="auracast_buy_now"]'); if (buyInput) buyInput.value = mode.endsWith('buy') ? '1' : '0';
      const variationId = form.querySelector('[name="variation_id"]');
      if (variationId && !Number(variationId.value)) { showStatus('Please choose an available size.'); form.querySelector('.auracast-size-chip:not(:disabled)')?.focus(); return; }
      form.querySelector('.single_add_to_cart_button')?.click();
    });
    form.addEventListener('submit', event => {
      const variationId = form.querySelector('[name="variation_id"]');
      if ((variationId && !Number(variationId.value)) || entries.some(entry => entryProduct(entry)?.variants.length && !entry.variation)) { event.preventDefault(); showStatus('Please choose an available size for every product.'); return; }
      const isBuyNow = form.querySelector('[name="auracast_buy_now"]')?.value === '1';
      try { sessionStorage.setItem('auracast-open-cart-after-add', isBuyNow ? '0' : '1'); } catch {}
    });
    const search = document.querySelector('[data-bundle-search]'); search?.addEventListener('input', () => renderProductChooser(search.value));
    if ($) {
      $(form).on('found_variation', () => { synchronizeVariationChips(); refreshBundleEntries(); showStatus(''); });
      $(form).on('woocommerce_update_variation_values reset_data', synchronizeVariationChips);
      $(form).find('.auracast-attribute select').trigger('change');
      setTimeout(() => { $(form).trigger('check_variations'); synchronizeVariationChips(); }, 150);
    }
    synchronizeVariationChips();
    try {
      if (sessionStorage.getItem('auracast-open-cart-after-add') === '1' && document.querySelector('.woocommerce-message')) {
        sessionStorage.removeItem('auracast-open-cart-after-add');
        const dialog = document.getElementById('cart-dialog');
        if (dialog && !document.querySelector('dialog[open]')) { dialog.showModal(); document.body.classList.add('dialog-open'); }
      }
    } catch {}
  }

  document.querySelectorAll('[data-product-gallery]').forEach(gallery => {
    const main = gallery.querySelector('.auracast-gallery-image');
    const lightbox = gallery.querySelector('.auracast-gallery-lightbox');
    gallery.querySelectorAll('[data-gallery-src]').forEach(button => button.addEventListener('click', () => {
      main.src = button.dataset.gallerySrc; main.removeAttribute('srcset'); main.alt = button.dataset.galleryAlt;
      gallery.querySelectorAll('[data-gallery-src]').forEach(item => { const active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', String(active)); });
    }));
    gallery.querySelector('[data-gallery-zoom]')?.addEventListener('click', () => {
      lightbox.querySelector('img').src = main.src; lightbox.querySelector('img').alt = main.alt;
      lightbox.showModal(); document.body.classList.add('dialog-open');
    });
    let startX;
    gallery.querySelector('.auracast-gallery-main')?.addEventListener('touchstart', event => { startX = event.touches[0].clientX; }, {passive:true});
    gallery.querySelector('.auracast-gallery-main')?.addEventListener('touchend', event => {
      const distance = event.changedTouches[0].clientX - startX;
      if (Math.abs(distance) < 45) return;
      const buttons = [...gallery.querySelectorAll('[data-gallery-src]')];
      const index = buttons.findIndex(button => button.classList.contains('is-active'));
      buttons[(index + (distance < 0 ? 1 : -1) + buttons.length) % buttons.length]?.click();
    }, {passive:true});
  });

  let cartUpdateTimer;
  document.querySelectorAll('[data-reviews-tab]').forEach(button => button.addEventListener('click', () => {
    const section = button.closest('.auracast-customer-reviews');
    section.querySelectorAll('[data-reviews-tab]').forEach(tab => tab.setAttribute('aria-selected', String(tab === button)));
    section.querySelectorAll('[data-review-panel]').forEach(panel => { panel.hidden = panel.dataset.reviewPanel !== button.dataset.reviewsTab; });
  }));
  document.querySelectorAll('[data-write-review]').forEach(button => button.addEventListener('click', () => {
    const section = button.closest('.auracast-customer-reviews'); section.classList.toggle('reviews-form-open');
    const opened = section.classList.contains('reviews-form-open'); button.textContent = opened ? 'Cancel review' : 'Write a review';
    if (opened) section.querySelector('#review_form_wrapper input, #review_form_wrapper textarea')?.focus();
  }));
  document.querySelectorAll('[data-review-sort]').forEach(select => select.addEventListener('change', () => {
    const section = select.closest('.auracast-customer-reviews');
    for (const panel of section.querySelectorAll('[data-review-panel]')) {
      const list = panel.querySelector('ol.commentlist') || panel;
      const reviews = [...list.children].filter(node => node.matches('li.review,li.comment,.auracast-store-review'));
      const rating = review => Number(review.querySelector('.star-rating span')?.style.width.replace('%', '') || 0);
      const date = review => Date.parse(review.querySelector('time')?.getAttribute('datetime') || '') || 0;
      const pictures = review => review.querySelectorAll('.description img,.auracast-store-review>div img').length;
      const videos = review => review.querySelectorAll('video,iframe').length;
      reviews.sort((a,b) => select.value === 'highest' ? rating(b)-rating(a) || date(b)-date(a) : select.value === 'lowest' ? rating(a)-rating(b) || date(b)-date(a) : select.value === 'pictures-first' ? pictures(b)-pictures(a) || date(b)-date(a) : select.value === 'videos-first' ? videos(b)-videos(a) || date(b)-date(a) : date(b)-date(a));
      reviews.forEach(review => { review.hidden = select.value === 'pictures' && !pictures(review); list.append(review); });
    }
  }));
  function updateCartQuantity(form) {
    clearTimeout(cartUpdateTimer);
    cartUpdateTimer = setTimeout(() => {
      const button = form.querySelector('[name="update_cart"]');
      if (!button) return;
      button.disabled = false;
      button.click();
    }, 350);
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-cart-quantity]'); if (!button) return;
    const input = button.closest('.auracast-cart-quantity').querySelector('input.qty');
    const min = Number(input.min || 0), max = input.max ? Number(input.max) : Infinity;
    const value = Math.min(max, Math.max(min, Number(input.value || 0) + Number(button.dataset.cartQuantity)));
    if (value === Number(input.value)) return;
    input.value = String(value); input.dispatchEvent(new Event('change', {bubbles:true}));
  });
  document.addEventListener('change', event => {
    const input = event.target.closest('.auracast-cart-form input.qty'); if (!input) return;
    updateCartQuantity(input.closest('form'));
  });
})();
