(() => {
  'use strict';
  const CART_KEY = 'sushiRelax.cart.v1';
  const CUSTOMER_KEY = 'sushiRelax.customer.v1';
  const readCart = () => { try {
    const parsed = JSON.parse(localStorage.getItem(CART_KEY)); if (!Array.isArray(parsed)) return [];
    return parsed.slice(0, 50).filter(row => row && typeof row.id === 'string' && typeof row.name === 'string' && Number.isInteger(row.price_minor) && row.price_minor >= 0 && Number.isInteger(row.quantity) && row.quantity >= 1 && row.quantity <= 30).map(row => ({ ...row,
      options: Array.isArray(row.options) ? row.options.slice(0, 20).filter(option => option && typeof option.id === 'string' && typeof option.name === 'string' && Number.isInteger(option.price_minor) && option.price_minor >= 0) : [],
      available_options: Array.isArray(row.available_options) ? row.available_options.slice(0, 20).filter(option => option && typeof option.id === 'string' && typeof option.name === 'string' && Number.isInteger(option.price_minor) && option.price_minor >= 0) : [],
      recommendations: Array.isArray(row.recommendations) ? row.recommendations.slice(0, 20).filter(item => item && typeof item.id === 'string' && typeof item.name === 'string' && Number.isInteger(item.price_minor) && item.price_minor >= 0) : [],
    }));
  } catch { return []; } };
  const saveCart = cart => { localStorage.setItem(CART_KEY, JSON.stringify(cart)); renderCart(); };
  const formatMoney = minor => new Intl.NumberFormat('uk-UA', { style: 'currency', currency: 'UAH', minimumFractionDigits: minor % 100 === 0 ? 0 : 2, maximumFractionDigits: minor % 100 === 0 ? 0 : 2 }).format(minor / 100);
  const safe = value => { const el = document.createElement('span'); el.textContent = value; return el.innerHTML; };
  let lastFocus = null;

  document.querySelectorAll('[data-year]').forEach(el => el.textContent = new Date().getFullYear());
  if (document.body.classList.contains('qr-mode')) {
    const table = new URLSearchParams(location.search).get('table');
    const tableQuery = /^\d{1,3}$/.test(table || '') && Number(table) > 0 ? `&table=${encodeURIComponent(String(Number(table)))}` : '';
    document.querySelectorAll('[data-checkout-link]').forEach(link => link.href = `/checkout.html?source=qr${tableQuery}`);
  }
  const navToggle = document.querySelector('.nav-toggle');
  const siteNav = document.querySelector('.site-nav');
  const navBackdrop = document.querySelector('.nav-backdrop');
  const navCloseButton = siteNav?.querySelector('.nav-close');
  const setNavOpen = (open, returnFocus = false) => {
    if (!navToggle || !siteNav) return;
    siteNav.classList.toggle('is-open', open);
    navToggle.setAttribute('aria-expanded', String(open));
    navToggle.setAttribute('aria-label', open ? 'Закрити меню' : 'Відкрити меню');
    if (navBackdrop) navBackdrop.hidden = !open;
    document.body.classList.toggle('nav-open', open);
    if (open) navCloseButton?.focus(); else if (returnFocus) navToggle.focus();
  };
  navToggle?.addEventListener('click', () => setNavOpen(navToggle.getAttribute('aria-expanded') !== 'true'));
  document.querySelectorAll('[data-nav-close]').forEach(control => control.addEventListener('click', () => setNavOpen(false, true)));
  siteNav?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setNavOpen(false)));
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && navToggle?.getAttribute('aria-expanded') === 'true') setNavOpen(false, true); });
  addEventListener('resize', () => { if (innerWidth > 900 && navToggle?.getAttribute('aria-expanded') === 'true') setNavOpen(false); });
  document.addEventListener('toggle', event => { if (event.target.matches?.('.contact-actions[open]')) document.querySelectorAll('.contact-actions[open]').forEach(details => { if (details !== event.target) details.open = false; }); }, true);
  document.addEventListener('click', event => { if (!event.target.closest('.contact-actions')) document.querySelectorAll('.contact-actions[open]').forEach(details => details.open = false); });

  document.querySelectorAll('.category-trigger').forEach(trigger => trigger.addEventListener('click', () => {
    const panel = trigger.nextElementSibling;
    const expanded = trigger.getAttribute('aria-expanded') === 'true';
    trigger.setAttribute('aria-expanded', String(!expanded));
    panel.hidden = expanded;
  }));

  const categoryLinks = [...document.querySelectorAll('[data-category-link]')];
  categoryLinks.forEach(link => link.addEventListener('click', () => {
    categoryLinks.forEach(item => item.removeAttribute('aria-current'));
    link.setAttribute('aria-current', 'true');
    const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
    const trigger = target?.querySelector('.category-trigger');
    const panel = target?.querySelector('.category-panel');
    if (trigger && panel) { trigger.setAttribute('aria-expanded', 'true'); panel.hidden = false; }
    link.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest', inline: 'center' });
  }));

  document.querySelectorAll('.qr-category-nav').forEach(nav => {
    const list = nav.querySelector('.qr-category-nav-list');
    const left = nav.querySelector('[data-category-scroll="left"]');
    const right = nav.querySelector('[data-category-scroll="right"]');
    if (!list || !left || !right) return;
    const updateScrollButtons = () => {
      const maxScroll = Math.max(0, list.scrollWidth - list.clientWidth);
      left.disabled = list.scrollLeft <= 2;
      right.disabled = list.scrollLeft >= maxScroll - 2;
    };
    const scrollCategories = direction => list.scrollBy({ left: direction * Math.max(220, list.clientWidth * .7), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    left.addEventListener('click', () => scrollCategories(-1));
    right.addEventListener('click', () => scrollCategories(1));
    list.addEventListener('scroll', updateScrollButtons, { passive: true });
    addEventListener('resize', updateScrollButtons);
    requestAnimationFrame(updateScrollButtons);
  });

  const revealHashProduct = () => {
    if (!location.hash.startsWith('#product-')) return;
    const card = document.getElementById(decodeURIComponent(location.hash.slice(1))); if (!card) return;
    const group = card.closest('[data-menu-group]'), trigger = group?.querySelector('.category-trigger'), panel = group?.querySelector('.category-panel');
    if (trigger && panel) { trigger.setAttribute('aria-expanded', 'true'); panel.hidden = false; }
    card.classList.remove('is-targeted'); void card.offsetWidth; card.classList.add('is-targeted');
    requestAnimationFrame(() => card.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }));
  };
  revealHashProduct();
  addEventListener('hashchange', revealHashProduct);

  const search = document.querySelector('[data-menu-search]');
  search?.addEventListener('input', () => {
    const query = search.value.trim().toLocaleLowerCase('uk'); let visibleTotal = 0;
    document.querySelectorAll('[data-menu-group]').forEach(group => {
      let visible = 0;
      group.querySelectorAll('[data-product]').forEach(card => { const show = card.dataset.search.includes(query); card.hidden = !show; if (show) visible++; });
      group.hidden = visible === 0; visibleTotal += visible;
      if (query && visible) { group.querySelector('.category-trigger').setAttribute('aria-expanded', 'true'); group.querySelector('.category-panel').hidden = false; }
    });
    const empty = document.querySelector('[data-no-results]'); if (empty) empty.hidden = visibleTotal > 0;
  });

  document.addEventListener('click', event => {
    const quantityButton = event.target.closest('[data-product-qty]');
    if (quantityButton) {
      const card = quantityButton.closest('[data-product]'), output = card?.querySelector('[data-product-quantity]'); if (!output) return;
      const current = Number(output.textContent) || 1, next = quantityButton.dataset.productQty === 'plus' ? Math.min(30, current + 1) : Math.max(1, current - 1);
      output.textContent = String(next); return;
    }
    const button = event.target.closest('[data-add-product]'); if (!button || button.disabled) return;
    const product = JSON.parse(button.dataset.addProduct); if (!Number.isInteger(product.price_minor)) return;
    const card = button.closest('[data-product]');
    const options = [...card.querySelectorAll('[data-product-option]:checked')].map(input => ({ id: input.value, name: input.dataset.name, price_minor: Number(input.dataset.price) }));
    const optionGroup = card.querySelector('[data-product-options]');
    if (product.options_required === true && !options.length) {
      optionGroup?.classList.add('has-error');
      const error = optionGroup?.querySelector('[data-product-option-error]'); if (error) error.hidden = false;
      optionGroup?.querySelector('[data-product-option]')?.focus();
      return;
    }
    const key = `${product.id}|${options.map(option=>option.id).sort().join(',')}`;
    const price = product.price_minor + options.reduce((sum,option)=>sum+option.price_minor,0);
    const quantity = Math.max(1, Math.min(30, Number(card.querySelector('[data-product-quantity]')?.textContent) || 1));
    const cart = readCart(); const existing = cart.find(row => row.key === key || (!row.key && row.id === product.id && !options.length));
    existing ? existing.quantity = Math.min(30, existing.quantity + quantity) : cart.push({ ...product, key, base_price_minor: product.price_minor, price_minor: price, options, quantity });
    const quantityOutput = card.querySelector('[data-product-quantity]'); if (quantityOutput) quantityOutput.textContent = '1';
    saveCart(cart);
    const shortcut = document.querySelector('.cart-fab');
    if (shortcut) {
      shortcut.classList.remove('is-bumped');
      void shortcut.offsetWidth;
      shortcut.classList.add('is-bumped');
    }
  });

  const sheet = document.querySelector('.cart-sheet'); const backdrop = document.querySelector('.sheet-backdrop');
  const sheetHandle = sheet?.querySelector('[data-cart-drag]');
  let lockedScrollY = 0;
  const pageSiblings = () => [...document.body.children].filter(el => el !== sheet && el !== backdrop && el.tagName !== 'SCRIPT');
  const lockPageScroll = () => {
    lockedScrollY = scrollY;
    document.body.classList.add('sheet-open');
    Object.assign(document.body.style, { position: 'fixed', top: `-${lockedScrollY}px`, left: '0', right: '0', width: '100%' });
  };
  const unlockPageScroll = () => {
    document.body.classList.remove('sheet-open');
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    const previousBehavior = document.documentElement.style.scrollBehavior;
    document.documentElement.style.scrollBehavior = 'auto';
    scrollTo(0, lockedScrollY);
    requestAnimationFrame(() => { document.documentElement.style.scrollBehavior = previousBehavior; });
  };
  function openCart(source) { if (!sheet) return; lastFocus = source || document.activeElement; sheet.classList.add('is-open'); sheet.setAttribute('aria-hidden', 'false'); backdrop.hidden = false; pageSiblings().forEach(el => el.inert = true); lockPageScroll(); sheet.querySelector('[data-cart-close]')?.focus(); }
  function closeCart() { if (!sheet || sheet.getAttribute('aria-hidden') === 'true') return; sheet.classList.remove('is-dragging'); sheet.style.transform = ''; if (backdrop) backdrop.style.opacity = ''; sheet.classList.remove('is-open'); sheet.setAttribute('aria-hidden', 'true'); backdrop.hidden = true; pageSiblings().forEach(el => el.inert = false); unlockPageScroll(); lastFocus?.focus(); }
  if (sheetHandle) {
    let dragStartY = 0, dragOffset = 0, dragStartedAt = 0;
    const resetDrag = () => { sheet.classList.remove('is-dragging'); sheet.style.transform = ''; if (backdrop) backdrop.style.opacity = ''; };
    sheetHandle.addEventListener('pointerdown', event => {
      if (event.button !== 0 || sheet.getAttribute('aria-hidden') === 'true') return;
      dragStartY = event.clientY; dragOffset = 0; dragStartedAt = performance.now();
      sheet.classList.add('is-dragging');
      sheetHandle.setPointerCapture(event.pointerId);
    });
    sheetHandle.addEventListener('pointermove', event => {
      if (!sheet.classList.contains('is-dragging')) return;
      dragOffset = Math.max(0, event.clientY - dragStartY);
      sheet.style.transform = `translate(-50%, ${dragOffset}px)`;
      if (backdrop) backdrop.style.opacity = String(Math.max(0, 1 - dragOffset / Math.max(240, sheet.offsetHeight * .7)));
    });
    const finishDrag = event => {
      if (!sheet.classList.contains('is-dragging')) return;
      if (sheetHandle.hasPointerCapture(event.pointerId)) sheetHandle.releasePointerCapture(event.pointerId);
      const duration = Math.max(1, performance.now() - dragStartedAt);
      const shouldClose = dragOffset > Math.min(140, sheet.offsetHeight * .18) || (dragOffset > 35 && dragOffset / duration > .55);
      shouldClose ? closeCart() : resetDrag();
    };
    sheetHandle.addEventListener('pointerup', finishDrag);
    sheetHandle.addEventListener('pointercancel', resetDrag);
  }
  document.querySelectorAll('[data-cart-open]').forEach(el => el.addEventListener('click', () => openCart(el)));
  document.querySelectorAll('[data-cart-close]').forEach(el => el.addEventListener('click', closeCart));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { closeCart(); document.querySelectorAll('.contact-actions[open]').forEach(details => { details.open = false; details.querySelector('summary')?.focus(); }); }
    if (event.key !== 'Tab' || !sheet || sheet.getAttribute('aria-hidden') === 'true') return;
    const focusable = [...sheet.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex]:not([tabindex="-1"])')].filter(el => !el.hidden);
    if (!focusable.length) { event.preventDefault(); sheet.focus(); return; }
    const first = focusable[0], last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });

  function renderCart() {
    const cart = readCart(), quantity = cart.reduce((sum, row) => sum + row.quantity, 0), total = cart.reduce((sum, row) => sum + row.price_minor * row.quantity, 0);
    document.querySelectorAll('[data-cart-count]').forEach(el => {
      el.textContent = quantity;
      el.hidden = quantity === 0;
    });
    document.querySelectorAll('.cart-fab').forEach(el => el.classList.toggle('has-items', quantity > 0));
    document.querySelectorAll('[data-cart-open]').forEach(el => el.setAttribute('aria-label', quantity ? 'Відкрити кошик, товарів: ' + quantity : 'Відкрити кошик'));
    document.querySelectorAll('[data-cart-lines]').forEach(el => el.textContent = cart.length);
    document.querySelectorAll('[data-cart-quantity]').forEach(el => el.textContent = quantity);
    document.querySelectorAll('[data-cart-total]').forEach(el => el.textContent = formatMoney(total));
    const knownWeights = cart.filter(row => Number.isInteger(row.weight_g));
    document.querySelectorAll('[data-cart-weight]').forEach(el => el.textContent = knownWeights.length === cart.length && cart.length ? knownWeights.reduce((sum,row)=>sum+row.weight_g*row.quantity,0)+' г' : '-');
    document.querySelectorAll('[data-checkout-link]').forEach(el => { el.setAttribute('aria-disabled', String(!cart.length)); el.tabIndex=cart.length?0:-1; });
    const container = document.querySelector('[data-cart-items]'); if (!container) return;
    if (!cart.length) { container.innerHTML = '<p class="empty-state">Кошик поки порожній.</p>'; return; }
    const rows = cart.map((row, index) => {
      const optionType = row.option_mode === 'single' ? 'radio' : 'checkbox';
      const optionEditor = row.available_options?.length ? `<fieldset class="cart-option-editor"><legend>${safe(row.option_label || 'Опції')}</legend>${row.available_options.map(option=>`<label><input type="${optionType}" ${optionType==='radio'?`name="cart-option-${index}"`:''} data-cart-option data-index="${index}" value="${safe(option.id)}" ${row.options.some(selected=>selected.id===option.id)?'checked':''}><span>${safe(option.name)}${option.price_minor>0?` · +${formatMoney(option.price_minor)}`:''}</span></label>`).join('')}</fieldset>` : '';
      const weightLabel = Number.isInteger(row.weight_g) ? ` · ${row.weight_g} г` : '';
      return `<article class="cart-item"><div><h3>${safe(row.name)}</h3>${row.options?.length?`<small>${row.options.map(option=>safe(option.name)).join(', ')}</small>`:''}<small>${formatMoney(row.price_minor)}${weightLabel}</small>${optionEditor}</div><div class="qty"><button data-cart-action="minus" data-index="${index}" aria-label="Зменшити кількість">−</button><span>${row.quantity}</span><button data-cart-action="plus" data-index="${index}" aria-label="Збільшити кількість">+</button></div><button class="icon-button" data-cart-action="remove" data-index="${index}" aria-label="Видалити ${safe(row.name)}">×</button></article>`;
    }).join('');
    const inCart = new Set(cart.map(row=>row.id)); const suggestions = [];
    cart.flatMap(row=>row.recommendations||[]).forEach(item=>{if(!inCart.has(item.id)&&!suggestions.some(entry=>entry.id===item.id))suggestions.push(item);});
    container.innerHTML = rows + (suggestions.length?`<section class="cart-recommendations" aria-labelledby="cart-recommendations-title"><h3 id="cart-recommendations-title">Разом із цим замовляють</h3>${suggestions.slice(0,3).map(item=>`<button type="button" data-add-recommendation='${safe(JSON.stringify(item))}'><span>${safe(item.name)}</span><strong>+ ${formatMoney(item.price_minor)}</strong></button>`).join('')}</section>`:'');
  }
  document.addEventListener('click', event => { const action = event.target.closest('[data-cart-action]'); if (!action) return; const cart = readCart(), index = Number(action.dataset.index); if (!cart[index]) return; if (action.dataset.cartAction === 'plus') cart[index].quantity=Math.min(30,cart[index].quantity+1); if (action.dataset.cartAction === 'minus') cart[index].quantity--; if (action.dataset.cartAction === 'remove' || cart[index].quantity < 1) cart.splice(index, 1); saveCart(cart); });
  document.addEventListener('click',event=>{const link=event.target.closest('[data-checkout-link][aria-disabled="true"]');if(link)event.preventDefault();});
  document.addEventListener('change', event => {
    const productOption=event.target.closest('[data-product-option]');if(productOption){const group=productOption.closest('[data-product-options]');group?.classList.remove('has-error');const error=group?.querySelector('[data-product-option-error]');if(error)error.hidden=true;}
    const input=event.target.closest('[data-cart-option]');if(!input)return;const cart=readCart(),index=Number(input.dataset.index),row=cart[index];if(!row||!Array.isArray(row.available_options))return;
    const chosen=row.option_mode==='single'?new Set([input.value]):new Set((row.options||[]).map(option=>option.id));if(row.option_mode!=='single'){input.checked?chosen.add(input.value):chosen.delete(input.value);}const oldOptionsTotal=(row.options||[]).reduce((sum,option)=>sum+option.price_minor,0);const base=Number.isInteger(row.base_price_minor)?row.base_price_minor:row.price_minor-oldOptionsTotal;
    row.options=row.available_options.filter(option=>chosen.has(option.id));row.price_minor=base+row.options.reduce((sum,option)=>sum+option.price_minor,0);row.base_price_minor=base;row.key=`${row.id}|${row.options.map(option=>option.id).sort().join(',')}`;
    const duplicateIndex=cart.findIndex((candidate,candidateIndex)=>candidateIndex!==index&&candidate.key===row.key);if(duplicateIndex>=0){cart[duplicateIndex].quantity=Math.min(30,cart[duplicateIndex].quantity+row.quantity);cart.splice(index,1);}saveCart(cart);
  });
  document.addEventListener('click', event => { const button=event.target.closest('[data-add-recommendation]'); if(!button)return; const item=JSON.parse(button.dataset.addRecommendation),cart=readCart(),existing=cart.find(row=>row.id===item.id&&!row.options?.length);existing?existing.quantity++:cart.push({...item,key:`${item.id}|`,base_price_minor:item.price_minor,options:[],recommendations:[],quantity:1});saveCart(cart); });
  window.SushiRelax = { readCart, saveCart, CUSTOMER_KEY, formatMoney, escapeHtml: safe };
  renderCart();

  const refreshMarketing = async () => {
    if (location.protocol === 'file:') return;
    try {
      const response = await fetch('/api/marketing.php', { headers: { Accept: 'application/json' } });
      if (!response.ok) return;
      const data = await response.json();
      const prices = new Map((data.products || []).map(product => [product.id, product]));
      document.querySelectorAll('[data-product-id]').forEach(card => {
        const price = prices.get(card.dataset.productId); if (!price) return;
        const display = card.querySelector('[data-price-display]');
        if (display) {
          display.replaceChildren();
          if (Number.isInteger(price.price_minor) && Number.isInteger(price.regular_price_minor) && price.price_minor < price.regular_price_minor) {
            const oldPrice = document.createElement('del'); oldPrice.textContent = formatMoney(price.regular_price_minor);
            const newPrice = document.createElement('ins'); newPrice.textContent = formatMoney(price.price_minor);
            display.append(oldPrice, ' ', newPrice);
          } else display.textContent = Number.isInteger(price.price_minor) ? formatMoney(price.price_minor) : 'Ціну уточнюємо';
        }
        const button = card.querySelector('[data-add-product]');
        if (button) { const payload = JSON.parse(button.dataset.addProduct); payload.price_minor = price.price_minor; button.dataset.addProduct = JSON.stringify(payload); button.disabled = !Number.isInteger(price.price_minor); }
      });
      const cart = readCart(); let cartChanged = false;
      cart.forEach(row => { const price = prices.get(row.id); if (!price) return; if (Number.isInteger(price.price_minor) && row.base_price_minor !== price.price_minor) { const optionsTotal = (row.options || []).reduce((sum, option) => sum + (Number(option.price_minor) || 0), 0); row.base_price_minor = price.price_minor; row.price_minor = price.price_minor + optionsTotal; cartChanged = true; } if (typeof price.delivery_available === 'boolean' && row.delivery_available !== price.delivery_available) { row.delivery_available = price.delivery_available; cartChanged = true; } });
      if (cartChanged) saveCart(cart);
      const orderStatus = document.querySelector('[data-order-status]');
      if (orderStatus && data.orders_status) { orderStatus.querySelector('.status-dot')?.classList.toggle('is-open', data.orders_status.open === true); const label = orderStatus.querySelector('strong'); if (label) label.textContent = data.orders_status.label; }
      const promoGrid = document.querySelector('[data-promo-products-grid]'), promoSection = document.querySelector('[data-promo-products-section]');
      if (promoGrid && promoSection) {
        promoGrid.replaceChildren();
        (data.promo_product_ids || []).forEach(id => { const source = document.querySelector(`#menu [data-product-id="${CSS.escape(id)}"]`); if (source) { const clone = source.cloneNode(true); clone.removeAttribute('id'); promoGrid.append(clone); } });
        promoSection.hidden = !promoGrid.children.length;
      }
      const bannerGrid = document.querySelector('[data-banner-grid]'), bannerSection = document.querySelector('[data-banner-section]');
      if (bannerGrid && bannerSection) {
        bannerGrid.replaceChildren();
        (data.banners || []).forEach(banner => {
          const link = document.createElement('a'); link.className = 'promo-banner'; link.href = banner.link || '#menu';
          const img = document.createElement('img'); img.src = banner.image; img.alt = ''; img.loading = 'lazy';
          const copy = document.createElement('span'), label = document.createElement('small'), title = document.createElement('strong');
          label.textContent = banner.period_label || 'Пропозиція'; title.textContent = banner.title; copy.append(label, title); link.append(img, copy); bannerGrid.append(link);
        });
        bannerSection.hidden = !bannerGrid.children.length;
      }
    } catch { /* Статична версія лишається робочою без API. */ }
  };
  refreshMarketing();

  const top = document.querySelector('.to-top');
  addEventListener('scroll', () => top?.classList.toggle('is-visible', scrollY > 700), { passive: true }); top?.addEventListener('click', () => scrollTo({ top: 0, behavior: 'smooth' }));
})();
