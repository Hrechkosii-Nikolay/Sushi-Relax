const customerSummary = (summary = '') => {
  const metadataMarker = '\n━━━━━━━━━━━━━━\n🔎 МЕТАДАНІ';
  return String(summary).split(metadataMarker, 1)[0].trim();
};

document.addEventListener('DOMContentLoaded', () => {
  try {
    const order = JSON.parse(sessionStorage.getItem('sushiRelax.lastOrder'));
    if (!order) {
      location.replace('/');
      return;
    }

    const fulfillmentLabels = { pickup: 'Самовивіз', dinein: 'У закладі', delivery: 'Доставка' };
    const fulfillmentRow = document.querySelector('[data-order-fulfillment-row]');
    if (order.source === 'qr') fulfillmentRow.hidden = true;
    else document.querySelector('[data-order-fulfillment]').textContent = fulfillmentLabels[order.fulfillment] || 'Не вказано';
    document.querySelector('[data-order-number]').textContent = order.order_number;
    document.querySelector('[data-order-copy]').textContent = customerSummary(order.summary);
    sessionStorage.removeItem('sushiRelax.lastOrder');
  } catch {
    location.replace('/');
  }
});
