document.addEventListener('DOMContentLoaded', () => {
  'use strict';
  const form = document.querySelector('#reservation-form');
  if (!form) return;
  const nameInput = form.elements.name;
  const phoneInput = form.elements.phone;
  const personsInput = form.elements.persons;
  const dateInput = form.elements.date;
  const timeInput = form.elements.time;
  const commentInput = form.elements.comment;
  const privacyInput = form.elements.privacy;
  const status = form.querySelector('[data-reservation-status]');
  const submitButton = form.querySelector('[data-submit-reservation]');
  const startedAt = Date.now();
  const customerKey = window.SushiRelax?.CUSTOMER_KEY || 'sushiRelax.customer.v1';
  const isoDate = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const formatReservationDateTime = (dateValue, timeValue) => {
    const [year, month, day] = String(dateValue).split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const formattedDate = new Intl.DateTimeFormat('uk-UA', {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    }).format(date);
    return `${formattedDate}, ${timeValue}`;
  };
  const formatPhone = (value, keepPrefix = false) => {
    let digits = String(value || '').replace(/\D/g, '');
    if (digits.startsWith('380')) digits = digits.slice(3);
    if (digits.startsWith('0')) digits = digits.slice(1);
    digits = digits.slice(0, 9);
    if (!digits) return keepPrefix ? '+380 ' : '';
    return `+380 ${[digits.slice(0, 2), digits.slice(2, 5), digits.slice(5, 7), digits.slice(7, 9)].filter(Boolean).join(' ')}`;
  };
  let saved = {};
  try { const value = JSON.parse(localStorage.getItem(customerKey) || '{}'); if (value && typeof value === 'object') saved = value; } catch {}
  nameInput.value = typeof saved.name === 'string' ? saved.name : '';
  phoneInput.value = typeof saved.phone === 'string' ? formatPhone(saved.phone) : '';
  personsInput.value = Number(saved.persons) >= 1 && Number(saved.persons) <= 20 ? String(Number(saved.persons)) : '';
  dateInput.min = form.dataset.minDate || isoDate(new Date());
  dateInput.max = form.dataset.maxDate || dateInput.min;
  dateInput.value = typeof saved.reservation_date === 'string' && saved.reservation_date >= dateInput.min && saved.reservation_date <= dateInput.max ? saved.reservation_date : dateInput.min;
  const saveCustomer = () => {
    try { localStorage.setItem(customerKey, JSON.stringify({ ...saved, name: nameInput.value.trim(), phone: phoneInput.value.trim(), persons: Number(personsInput.value) || null, reservation_date: dateInput.value })); } catch {}
  };
  phoneInput.addEventListener('focus', () => { if (!phoneInput.value) phoneInput.value = '+380 '; });
  phoneInput.addEventListener('input', () => { phoneInput.value = formatPhone(phoneInput.value, true); phoneInput.setCustomValidity(''); });
  phoneInput.addEventListener('blur', () => { phoneInput.value = formatPhone(phoneInput.value); saveCustomer(); });
  nameInput.addEventListener('input', saveCustomer);
  personsInput.addEventListener('change', saveCustomer);
  const loadSlots = async () => {
    timeInput.disabled = true;
    timeInput.replaceChildren(new Option('Завантаження…', ''));
    try {
      const response = await fetch(`/api/slots.php?date=${encodeURIComponent(dateInput.value)}`, { headers: { Accept: 'application/json' } });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Не вдалося завантажити час.');
      const slots = result.slot_options || [];
      timeInput.replaceChildren(new Option(slots.length ? 'Оберіть час' : 'На цю дату немає доступного часу', ''));
      slots.forEach(slot => timeInput.add(new Option(slot.label, slot.value)));
      timeInput.disabled = slots.length === 0;
      status.textContent = '';
    } catch (error) {
      timeInput.replaceChildren(new Option('Час тимчасово недоступний', ''));
      status.textContent = error.message;
    }
  };
  dateInput.addEventListener('change', () => { saveCustomer(); loadSlots(); });
  loadSlots();
  const validate = () => {
    nameInput.setCustomValidity(nameInput.value.trim().length >= 2 ? '' : 'Вкажіть ім’я - щонайменше 2 символи.');
    phoneInput.setCustomValidity(/^380\d{9}$/.test(phoneInput.value.replace(/\D/g, '')) ? '' : 'Вкажіть повний номер у форматі +380 XX XXX XX XX.');
    const invalid = [nameInput, phoneInput, personsInput, dateInput, timeInput, privacyInput].find(control => !control.checkValidity());
    if (invalid) { invalid.reportValidity(); invalid.focus(); return false; }
    return true;
  };
  const requestId = payload => {
    const key = 'sushiRelax.pendingReservation.v1', fingerprint = JSON.stringify(payload);
    try { const pending = JSON.parse(sessionStorage.getItem(key) || 'null'); if (pending?.fingerprint === fingerprint && pending?.id) return pending.id; } catch {}
    const id = crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    sessionStorage.setItem(key, JSON.stringify({ id, fingerprint }));
    return id;
  };
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!validate() || submitButton.disabled) return;
    saveCustomer();
    const payload = { name: nameInput.value.trim(), phone: phoneInput.value.trim(), persons: Number(personsInput.value), date: dateInput.value, time: timeInput.value, comment: commentInput.value.trim(), privacy: privacyInput.checked, website: form.elements.website.value, time_on_page_seconds: Math.min(86400, Math.floor((Date.now() - startedAt) / 1000)) };
    submitButton.disabled = true; submitButton.textContent = 'Надсилаємо…'; status.textContent = 'Надсилаємо замовлення адміністратору…';
    try {
      const response = await fetch('/api/reservation.php', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Request-ID': requestId(payload) }, body: JSON.stringify(payload) });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'Не вдалося надіслати замовлення.');
      sessionStorage.removeItem('sushiRelax.pendingReservation.v1');
      const success = document.querySelector('[data-reservation-success]');
      document.body.classList.add('reservation-complete');
      success.hidden = false;
      success.querySelector('[data-reservation-datetime]').textContent = formatReservationDateTime(payload.date, payload.time);
      success.focus({ preventScroll: true });
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
      status.textContent = `${error.message} Спробуйте ще раз або зателефонуйте нам.`;
      submitButton.disabled = false; submitButton.textContent = 'Надіслати замовлення';
    }
  });
});
