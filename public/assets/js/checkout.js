document.addEventListener('DOMContentLoaded', () => {
  const api = window.SushiRelax;
  const form = document.querySelector('#checkout-form');
  if (!api || !form) return;

  const cart = api.readCart();
  const list = document.querySelector('[data-checkout-items]');
  const promoInput = document.querySelector('#promo-code');
  const status = document.querySelector('[data-form-status]');
  const submitButton = form.querySelector('[data-submit-order]');
  let backendOrdersAvailable = form.dataset.ordersEnabled === '1';
  let serverSubmission = backendOrdersAvailable;
  let currentStep = 1;
  let maxUnlockedStep = 1;
  let currentQuote = null;
  let submitting = false;
  const checkoutStartedAt = Date.now();
  const checkoutParams = new URLSearchParams(location.search);
  const checkoutSource = checkoutParams.get('source') === 'qr' ? 'qr' : 'site';
  const isQrOrder = checkoutSource === 'qr';
  const reviewHelp = document.querySelector('[data-review-help]');
  if (reviewHelp) reviewHelp.hidden = isQrOrder;

  const field = name => form.querySelector(`[name="${name}"]`);
  const money = value => api.formatMoney(Number(value) || 0);
  const cartTotal = () => cart.reduce((sum, row) => sum + row.price_minor * row.quantity, 0);
  const quoteItems = () => cart.map(row => ({ id: row.id, quantity: row.quantity, options: (row.options || []).map(option => option.id) }));
  const isoDate = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const dateLabel = value => {
    const date = new Date(`${value}T12:00:00`);
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
  };
  const intervalLabel = value => {
    if (!/^\d{2}:\d{2}$/.test(value)) return value;
    const [hours, minutes] = value.split(':').map(Number), end = hours * 60 + minutes + 30;
    return `${value}–${String(Math.floor(end / 60)).padStart(2, '0')}:${String(end % 60).padStart(2, '0')}`;
  };
  const fulfillmentLabel = value => ({ pickup: 'Самовивіз', dinein: 'У закладі', delivery: 'Доставка' }[value] || 'Не вибрано');
  const deliveryEarliestMinutes = 11 * 60;
  const filterSlotsForFulfillment = options => {
    if (form.querySelector('[name="fulfillment"]:checked').value !== 'delivery') return options;
    return options.filter(slot => {
      const match = /^(\d{2}):(\d{2})$/.exec(slot.value || '');
      return match && Number(match[1]) * 60 + Number(match[2]) >= deliveryEarliestMinutes;
    });
  };

  list.innerHTML = cart.length ? cart.map(row => `<li><span>${row.quantity} × ${api.escapeHtml(row.name)}${row.options?.length ? `<small>${row.options.map(option => api.escapeHtml(String(option.name || ''))).join(', ')}</small>` : ''}</span><strong>${money(row.price_minor * row.quantity)}${Number.isInteger(row.weight_g) ? ` · ${row.weight_g * row.quantity} г` : ''}</strong></li>`).join('') : '<li class="empty-state">Кошик порожній. Поверніться до меню та додайте позиції.</li>';
  document.querySelector('[data-checkout-subtotal]').textContent = money(cartTotal());
  document.querySelector('[data-checkout-total]').textContent = money(cartTotal());
  const firstStepButton = form.querySelector('[data-checkout-step="1"] [data-next-step]');
  if (!cart.length && firstStepButton) firstStepButton.textContent = 'Додати страви до кошика';
  const syncStepNavigation = () => {
    document.querySelectorAll('[data-step-indicator]').forEach(item => {
      const number = Number(item.dataset.stepIndicator);
      const button = item.querySelector('[data-step-nav]');
      if (button) button.disabled = number > maxUnlockedStep;
    });
  };

  const showStep = step => {
    currentStep = Math.max(1, Math.min(3, step));
    document.querySelectorAll('[data-checkout-step]').forEach(panel => { panel.hidden = Number(panel.dataset.checkoutStep) !== currentStep; });
    document.querySelectorAll('[data-step-indicator]').forEach(item => {
      const number = Number(item.dataset.stepIndicator);
      item.classList.toggle('is-active', number === currentStep);
      item.classList.toggle('is-complete', number < currentStep);
      if (number === currentStep) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
    });
    syncStepNavigation();
    form.querySelector(`[data-checkout-step="${currentStep}"] h2`)?.focus({ preventScroll: true });
    scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  };

  const applyQuote = result => {
    currentQuote = result;
    document.querySelector('[data-checkout-subtotal]').textContent = money(result.subtotal_minor);
    document.querySelector('[data-checkout-total]').textContent = money(result.total_minor);
    const discountRow = document.querySelector('[data-discount-row]');
    discountRow.hidden = !result.discount_minor;
    document.querySelector('[data-checkout-discount]').textContent = `− ${money(result.discount_minor)}`;
    const giftRow = document.querySelector('[data-gift-row]');
    giftRow.hidden = !result.gift;
    document.querySelector('[data-checkout-gift]').textContent = result.gift?.name || '';
    const deliveryRow = document.querySelector('[data-delivery-row]');
    const deliverySelected = form.querySelector('[name="fulfillment"]:checked').value === 'delivery';
    deliveryRow.hidden = !deliverySelected && !result.delivery_minor;
    document.querySelector('[data-checkout-delivery]').textContent = deliverySelected
      ? 'Безкоштовно в межах міста'
      : money(result.delivery_minor);
  };

  const localQuote = () => ({ subtotal_minor: cartTotal(), discount_minor: 0, delivery_minor: 0, total_minor: cartTotal(), gift: null, promo_code: null });
  async function refreshQuote(showMessage = false) {
    const promo = promoInput.value.trim();
    try {
      const response = await fetch('/api/quote.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ items: quoteItems(), fulfillment: form.querySelector('[name="fulfillment"]:checked').value, promo_code: promo }) });
      if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('API недоступний');
      const result = await response.json();
      if (!result.ok) throw new Error(result.error || 'Не вдалося розрахувати замовлення.');
      applyQuote(result);
      if (showMessage) document.querySelector('[data-promo-status]').textContent = result.promo_code ? 'Промокод застосовано.' : promo ? 'Промокод не застосовано.' : '';
      return result;
    } catch (error) {
      const result = localQuote(); applyQuote(result);
      if (showMessage) document.querySelector('[data-promo-status]').textContent = promo ? 'Промокод перевірить адміністратор під час підтвердження.' : '';
      return result;
    }
  }

  const nameInput = field('name'), phoneInput = field('phone'), personsInput = field('persons'), tableNumberInput = field('table_number'), dateInput = field('date'), timeInput = field('time'), commentInput = field('comment'), privacyInput = field('privacy');
  if (isQrOrder) {
    form.querySelectorAll('[name="fulfillment"]').forEach(input => {
      const dineIn = input.value === 'dinein';
      input.checked = dineIn;
      input.disabled = !dineIn;
      if (!dineIn) input.closest('label').hidden = true;
    });
    document.querySelector('[data-date-field]').hidden = true;
    document.querySelector('[data-time-field]').hidden = true;
    document.querySelector('[data-table-field]').hidden = false;
    tableNumberInput.required = true;
    const tableFromQr = checkoutParams.get('table');
    if (/^\d{1,3}$/.test(tableFromQr || '') && Number(tableFromQr) > 0) tableNumberInput.value = String(Number(tableFromQr));
    dateInput.required = false;
    timeInput.required = false;
    const stepTwoButton = document.querySelector('[data-step-indicator="2"] [data-step-nav]');
    if (stepTwoButton) stepTwoButton.innerHTML = '<span>2</span>Контакти';
  }
  const formatUkrainianPhone = (value, keepPrefix = false) => {
    let digits = String(value || '').replace(/\D/g, '');
    if (digits.startsWith('380')) digits = digits.slice(3);
    if (digits.startsWith('0')) digits = digits.slice(1);
    digits = digits.slice(0, 9);
    if (!digits) return keepPrefix ? '+380 ' : '';
    const groups = [digits.slice(0, 2), digits.slice(2, 5), digits.slice(5, 7), digits.slice(7, 9)].filter(Boolean);
    return `+380 ${groups.join(' ')}`;
  };

  phoneInput.addEventListener('focus', () => {
    if (!phoneInput.value) phoneInput.value = '+380 ';
  });
  phoneInput.addEventListener('input', () => {
    phoneInput.value = formatUkrainianPhone(phoneInput.value, true);
    phoneInput.setCustomValidity('');
  });
  phoneInput.addEventListener('blur', () => {
    phoneInput.value = formatUkrainianPhone(phoneInput.value);
  });

  let saved = {};
  try { const candidate = JSON.parse(localStorage.getItem(api.CUSTOMER_KEY) || '{}'); if (candidate && typeof candidate === 'object') saved = candidate; } catch {}
  nameInput.value = typeof saved.name === 'string' ? saved.name : '';
  phoneInput.value = typeof saved.phone === 'string' ? formatUkrainianPhone(saved.phone) : '';
  personsInput.value = Number.isInteger(Number(saved.persons)) && Number(saved.persons) >= 1 && Number(saved.persons) <= 20 ? String(Number(saved.persons)) : '';
  commentInput.value = typeof saved.comment === 'string' ? saved.comment.slice(0, 500) : '';
  privacyInput.checked = saved.privacy === true;
  const savedFulfillment = checkoutSource === 'qr' ? 'dinein' : (['pickup', 'dinein', 'delivery'].includes(saved.fulfillment) ? saved.fulfillment : 'pickup');
  const savedFulfillmentInput = form.querySelector(`[name="fulfillment"][value="${savedFulfillment}"]`);
  if (savedFulfillmentInput) savedFulfillmentInput.checked = true;
  const saveCustomerState = (changes = {}) => {
    saved = {
      ...saved,
      name: nameInput.value.trim(),
      phone: phoneInput.value.trim(),
      persons: Number(personsInput.value) || null,
      date: dateInput.value,
      time: timeInput.value,
      comment: commentInput.value,
      privacy: privacyInput.checked,
      fulfillment: form.querySelector('[name="fulfillment"]:checked').value,
      ...changes
    };
    try { localStorage.setItem(api.CUSTOMER_KEY, JSON.stringify(saved)); } catch {}
  };

  const today = new Date(), lastDate = new Date(today);
  lastDate.setDate(lastDate.getDate() + 7);
  dateInput.min = form.dataset.minDate || isoDate(today);
  dateInput.max = form.dataset.maxDate || isoDate(lastDate);
  const savedDateIsAvailable = typeof saved.date === 'string' && saved.date >= dateInput.min && saved.date <= dateInput.max;
  dateInput.value = savedDateIsAvailable ? saved.date : dateInput.min;
  let savedTimeToRestore = savedDateIsAvailable && typeof saved.time === 'string' ? saved.time : '';
  dateInput.addEventListener('click', () => {
    if (typeof dateInput.showPicker !== 'function') return;
    try { dateInput.showPicker(); } catch {}
  });

  const localSlots = selectedDate => {
    const now = new Date(), sameDay = selectedDate === isoDate(now), slots = [];
    for (let minutes = 10 * 60; minutes < 21 * 60; minutes += 30) {
      if (sameDay && minutes < now.getHours() * 60 + now.getMinutes() + 40) continue;
      const value = `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
      slots.push({ value, label: intervalLabel(value) });
    }
    return slots;
  };

  const renderSlots = options => {
    timeInput.replaceChildren(new Option(options.length ? 'Оберіть час' : 'На цю дату немає доступного часу', ''));
    options.forEach(slot => timeInput.add(new Option(slot.label, slot.value)));
    timeInput.disabled = options.length === 0;
    const readyTimeNote = document.querySelector('[data-ready-time-note]');
    if (readyTimeNote) {
      const fulfillment = form.querySelector('[name="fulfillment"]:checked').value;
      const sameDay = dateInput.value === isoDate(new Date());
      if (!options.length) readyTimeNote.textContent = 'На цю дату доступного часу немає.';
      else if (!sameDay) readyTimeNote.textContent = `Перший доступний час: ${options[0].label}.`;
      else if (fulfillment === 'pickup') readyTimeNote.textContent = `Час приготування - 40–60 хвилин. Найближчий час: ${options[0].label}.`;
      else if (fulfillment === 'delivery') readyTimeNote.textContent = `Найближчий доступний час доставки: ${options[0].label}.`;
      else readyTimeNote.textContent = `Найближчий доступний час: ${options[0].label}.`;
    }
  };

  async function loadSlots() {
    timeInput.disabled = true;
    timeInput.replaceChildren(new Option('Завантаження…', ''));
    try {
      const response = await fetch(`/api/slots.php?date=${encodeURIComponent(dateInput.value)}`, { headers: { Accept: 'application/json' } });
      if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('API недоступний');
      const result = await response.json();
      if (result.orders_enabled && (result.slot_options || result.slots || []).length) {
        backendOrdersAvailable = true;
        serverSubmission = form.querySelector('[name="fulfillment"]:checked').value !== 'delivery' || form.dataset.deliveryEnabled === '1';
        renderSlots(filterSlotsForFulfillment(result.slot_options || result.slots.map(slot => ({ value: slot, label: intervalLabel(slot) }))));
      } else {
        backendOrdersAvailable = false; serverSubmission = false;
        renderSlots(filterSlotsForFulfillment(localSlots(dateInput.value)));
      }
    } catch {
      backendOrdersAvailable = false; serverSubmission = false;
      renderSlots(filterSlotsForFulfillment(localSlots(dateInput.value)));
    }
    if (savedTimeToRestore && Array.from(timeInput.options).some(option => option.value === savedTimeToRestore)) {
      timeInput.value = savedTimeToRestore;
    }
    savedTimeToRestore = '';
    submitButton.textContent = 'Надіслати замовлення';
  }
  dateInput.addEventListener('change', () => {
    savedTimeToRestore = '';
    saveCustomerState({ date: dateInput.value, time: '' });
    loadSlots();
  });
  timeInput.addEventListener('change', () => saveCustomerState());
  personsInput.addEventListener('change', () => saveCustomerState());
  commentInput.addEventListener('input', () => saveCustomerState());
  privacyInput.addEventListener('change', () => saveCustomerState());
  if (isQrOrder) {
    dateInput.value = '';
    timeInput.value = '';
    dateInput.disabled = true;
    timeInput.disabled = true;
  } else {
    loadSlots();
  }

  const syncFulfillment = () => {
    const fulfillment = form.querySelector('[name="fulfillment"]:checked').value;
    const delivery = fulfillment === 'delivery', addressField = document.querySelector('[data-address-field]'), addressInput = field('address');
    const fulfillmentNote = document.querySelector('[data-fulfillment-note]');
    const fulfillmentNotes = {
      pickup: 'Для самовивозу діє передплата 50%. Заберіть замовлення у Sushi Relax за адресою: м. Гадяч, вул. Соборна, 13а. На наступному кроці оберіть дату та час готовності.',
      dinein: checkoutSource === 'qr' ? 'Ви оформлюєте замовлення через QR-меню в закладі. Перевірте склад кошика та вкажіть контактні дані - замовлення одразу надійде адміністратору.' : 'Оберіть дату, час і кількість персон - ми підготуємо замовлення до вашого приходу. Можливість розміщення у закладі підтвердить адміністратор.',
      delivery: 'Мінімальна сума замовлення для доставки - 800 грн. У межах міста доставка безкоштовна, за межі міста - на таксі за тарифом перевізника. Доставку можна замовити не раніше 11:00. Для доставки діє передплата.'
    };
    if (fulfillmentNote) fulfillmentNote.textContent = fulfillmentNotes[fulfillment] || '';
    addressField.hidden = !delivery; addressInput.required = delivery;
    if (!delivery) addressInput.value = '';
    serverSubmission = backendOrdersAvailable && (!delivery || form.dataset.deliveryEnabled === '1');
    submitButton.textContent = 'Надіслати замовлення';
    refreshQuote(false);
  };
  form.querySelectorAll('[name="fulfillment"]').forEach(radio => radio.addEventListener('change', () => {
    maxUnlockedStep = Math.min(maxUnlockedStep, 2);
    saveCustomerState();
    syncFulfillment();
    if (!isQrOrder) {
      savedTimeToRestore = '';
      timeInput.value = '';
      loadSlots();
    }
    syncStepNavigation();
  }));
  syncFulfillment();

  const validateDetails = () => {
    nameInput.setCustomValidity(nameInput.value.trim().length >= 2 ? '' : 'Вкажіть ім’я - щонайменше 2 символи.');
    phoneInput.setCustomValidity(/^380\d{9}$/.test(phoneInput.value.replace(/\D/g, '')) ? '' : 'Вкажіть повний номер у форматі +380 XX XXX XX XX.');
    personsInput.setCustomValidity(Number.isInteger(Number(personsInput.value)) && Number(personsInput.value) >= 1 && Number(personsInput.value) <= 20 ? '' : 'Оберіть кількість персон.');
    const controls = [nameInput, phoneInput, personsInput, field('privacy')];
    if (isQrOrder) controls.push(tableNumberInput);
    if (!isQrOrder) controls.push(dateInput, timeInput);
    if (form.querySelector('[name="fulfillment"]:checked').value === 'delivery') controls.push(field('address'));
    const invalid = controls.find(control => !control.checkValidity());
    if (invalid) { invalid.reportValidity(); invalid.focus(); return false; }
    return true;
  };

  const reviewOrder = () => {
    const review = document.querySelector('[data-order-review]');
    const fulfillment = form.querySelector('[name="fulfillment"]:checked').value;
    const compactQrReview = checkoutSource === 'qr' && fulfillment === 'dinein';
    const rows = compactQrReview
      ? [['Стіл №', tableNumberInput.value], ['Ім’я', nameInput.value.trim()], ['Телефон', phoneInput.value.trim()], ['Кількість персон', personsInput.value]]
      : [['Отримання', fulfillmentLabel(fulfillment)], ['Ім’я', nameInput.value.trim()], ['Телефон', phoneInput.value.trim()], ['Кількість персон', personsInput.value]];
    if (!compactQrReview) rows.push(['Дата', dateLabel(dateInput.value)], ['Час', intervalLabel(timeInput.value)]);
    if (fulfillment === 'delivery') rows.splice(2, 0, ['Адреса', field('address').value.trim()]);
    if (!compactQrReview && fulfillment !== 'dinein') rows.push(['Оплата', fulfillment === 'pickup' ? 'Передплата 50% після підтвердження замовлення' : 'Передплата після підтвердження замовлення']);
    if (field('comment').value.trim()) rows.push(['Коментар', field('comment').value.trim()]);
    if (form.querySelector('[name="fulfillment"]:checked').value === 'delivery' && form.dataset.deliveryEnabled !== '1') rows.push(['Вартість доставки', 'Уточнить адміністратор']);
    rows.push(['До сплати', money((currentQuote || localQuote()).total_minor)]);
    review.innerHTML = rows.map(([label, value]) => `<div><dt>${api.escapeHtml(label)}</dt><dd>${api.escapeHtml(value)}</dd></div>`).join('');
  };

  form.querySelectorAll('[data-next-step]').forEach(button => button.addEventListener('click', async () => {
    if (!cart.length) { location.href = '/#menu'; return; }
    if (currentStep === 1) maxUnlockedStep = Math.max(maxUnlockedStep, 2);
    if (currentStep === 2) {
      if (!validateDetails()) return;
      await refreshQuote(false);
      reviewOrder();
      maxUnlockedStep = 3;
    }
    showStep(currentStep + 1);
  }));
  form.querySelectorAll('[data-prev-step]').forEach(button => button.addEventListener('click', () => showStep(currentStep - 1)));
  document.querySelectorAll('[data-step-nav]').forEach(button => button.addEventListener('click', async () => {
    const targetStep = Number(button.dataset.stepNav);
    const completeScreen = document.querySelector('[data-checkout-complete]');
    const returningFromComplete = !completeScreen.hidden;
    if ((targetStep === currentStep && !returningFromComplete) || targetStep > maxUnlockedStep) return;
    if (returningFromComplete) {
      document.querySelector('[data-checkout-layout]').hidden = false;
      completeScreen.hidden = true;
      document.querySelector('.checkout-steps').hidden = false;
    }
    if (targetStep === 3) {
      if (!validateDetails()) { showStep(2); return; }
      await refreshQuote(false);
      reviewOrder();
    }
    showStep(targetStep);
  }));

  const detailsPanel = form.querySelector('[data-checkout-step="2"]');
  const relockReviewStep = () => {
    if (maxUnlockedStep < 3) return;
    maxUnlockedStep = 2;
    syncStepNavigation();
  };
  detailsPanel.addEventListener('input', relockReviewStep);
  detailsPanel.addEventListener('change', relockReviewStep);
  document.querySelector('[data-apply-promo]').addEventListener('click', async () => { await refreshQuote(true); if (currentStep === 3) reviewOrder(); });

  const buildDraft = () => {
    const fulfillment = form.querySelector('[name="fulfillment"]:checked').value;
    const compactQrDraft = checkoutSource === 'qr' && fulfillment === 'dinein';
    const lines = ['ЗАМОВЛЕННЯ SUSHI RELAX'];
    if (compactQrDraft) lines.push(`Стіл № ${tableNumberInput.value}`);
    if (!compactQrDraft) lines.push(`Отримання: ${fulfillmentLabel(fulfillment).toLowerCase()}`);
    if (!compactQrDraft) lines.push(`Дата і час: ${dateLabel(dateInput.value)}, ${intervalLabel(timeInput.value)}`);
    lines.push(`Ім’я: ${nameInput.value.trim()}`, `Телефон: ${phoneInput.value.trim()}`, `Кількість персон: ${personsInput.value}`);
    if (fulfillment === 'delivery') lines.push(`Адреса: ${field('address').value.trim()}`);
    if (!compactQrDraft && fulfillment !== 'dinein') lines.push(fulfillment === 'pickup' ? 'Оплата: передплата 50% після підтвердження замовлення' : 'Оплата: передплата після підтвердження замовлення');
    lines.push('', 'СКЛАД:');
    cart.forEach(row => { lines.push(`• ${row.quantity} × ${row.name} - ${money(row.price_minor * row.quantity)}`); if (row.options?.length) lines.push(`  Додатково: ${row.options.map(option => option.name).join(', ')}`); });
    if (promoInput.value.trim()) lines.push(`Промокод: ${promoInput.value.trim()} (перевірити)`);
    if (fulfillment === 'delivery' && form.dataset.deliveryEnabled !== '1') lines.push('Вартість доставки: уточнить адміністратор');
    if (!compactQrDraft) lines.push(`РАЗОМ: ${money((currentQuote || localQuote()).total_minor)}`);
    if (field('comment').value.trim()) lines.push(`Коментар: ${field('comment').value.trim()}`);
    return lines.join('\n');
  };

  const requestIdFor = payload => {
    const key = 'sushiRelax.pendingOrder.v1', fingerprint = JSON.stringify(payload);
    try { const savedRequest = JSON.parse(sessionStorage.getItem(key) || 'null'); if (savedRequest?.fingerprint === fingerprint && savedRequest?.id) return savedRequest.id; } catch {}
    const id = crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    sessionStorage.setItem(key, JSON.stringify({ id, fingerprint })); return id;
  };

  const showDraft = () => {
    const draft = buildDraft();
    sessionStorage.setItem('sushiRelax.orderDraft', draft);
    document.querySelector('[data-order-draft]').textContent = draft;
    document.querySelector('[data-checkout-layout]').hidden = true;
    document.querySelector('.checkout-steps').hidden = false;
    document.querySelector('[data-checkout-complete]').hidden = false;
    document.querySelector('[data-checkout-complete]').scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!cart.length || !validateDetails() || submitting) return;
    saveCustomerState();
    if (!serverSubmission) {
      status.textContent = 'Для надсилання замовлення відкрийте сайт через PHP-сервер. Live Server на порту 5500 не виконує PHP.';
      return;
    }
    submitting = true; submitButton.disabled = true; submitButton.textContent = 'Надсилаємо…'; status.textContent = 'Надсилаємо замовлення адміністратору…';
    const payload = { items: quoteItems(), promo_code: promoInput.value.trim(), fulfillment: form.querySelector('[name="fulfillment"]:checked').value, source: checkoutSource, table_number: isQrOrder ? Number(tableNumberInput.value) : null, name: nameInput.value.trim(), phone: phoneInput.value.trim(), persons: Number(personsInput.value), address: field('address').value.trim(), date: dateInput.value, time: timeInput.value, comment: field('comment').value.trim(), website: field('website').value, privacy: field('privacy').checked, time_on_page_seconds: Math.min(86400, Math.max(0, Math.floor((Date.now() - checkoutStartedAt) / 1000))) };
    try {
      const response = await fetch('/api/order.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Request-ID': requestIdFor(payload) }, body: JSON.stringify(payload) });
      const result = await response.json(); if (!response.ok) throw new Error(result.error || 'Не вдалося надіслати замовлення.');
      sessionStorage.removeItem('sushiRelax.pendingOrder.v1'); api.saveCart([]); sessionStorage.setItem('sushiRelax.lastOrder', JSON.stringify(result)); location.href = '/success.html';
    } catch (error) {
      status.textContent = `${error.message} Кошик збережено.`; submitting = false; submitButton.disabled = false; submitButton.textContent = 'Надіслати замовлення';
    }
  });

  document.querySelector('[data-copy-order]').addEventListener('click', async event => {
    const draft = document.querySelector('[data-order-draft]').textContent;
    try { await navigator.clipboard.writeText(draft); event.currentTarget.textContent = 'Скопійовано ✓'; }
    catch { event.currentTarget.textContent = 'Виділіть текст вище'; }
  });

  refreshQuote(false);
  showStep(1);
});
