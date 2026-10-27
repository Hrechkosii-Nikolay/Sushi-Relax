(() => {
  'use strict';
  let sequence = 0;
  const rowContainer = type => document.querySelector(`[data-admin-rows="${type}"]`);
  const syncLimits = type => {
    const container = rowContainer(type), button = document.querySelector(`[data-add-admin-row="${type}"]`);
    if (!container || !button) return;
    const maximum = Number(container.dataset.maxRows || 0);
    button.disabled = maximum > 0 && container.querySelectorAll('[data-admin-row]').length >= maximum;
  };
  document.addEventListener('click', event => {
    const addOption = event.target.closest('[data-add-product-option]');
    if (addOption) {
      const editor = addOption.closest('[data-product-options-editor]'), template = document.querySelector('#admin-product-option-template');
      if (!editor || !template) return;
      const productId = addOption.dataset.addProductOption, key = `new-${Date.now()}-${sequence++}`, fragment = template.content.cloneNode(true);
      fragment.querySelectorAll('[data-option-field]').forEach(field => field.name = `product[${productId}][options][${key}][${field.dataset.optionField}]`);
      editor.querySelector('[data-product-option-rows]')?.append(fragment);
      editor.querySelector('[data-product-option-rows]')?.lastElementChild?.querySelector('input:not([type="hidden"])')?.focus();
      return;
    }
    const removeOption = event.target.closest('[data-remove-product-option]');
    if (removeOption) { removeOption.closest('[data-product-option-row]')?.remove(); return; }
    const add = event.target.closest('[data-add-admin-row]');
    if (add) {
      const type = add.dataset.addAdminRow, container = rowContainer(type), template = document.querySelector(`#admin-${type}-template`);
      if (!container || !template || add.disabled) return;
      const fragment = template.content.cloneNode(true), key = `new-${Date.now()}-${sequence++}`;
      fragment.querySelectorAll('[data-field]').forEach(field => field.name = `settings[${type}][${key}][${field.dataset.field}]`);
      container.append(fragment); syncLimits(type);
      container.lastElementChild?.querySelector('input,select')?.focus();
      return;
    }
    const remove = event.target.closest('[data-remove-admin-row]');
    if (remove) { const container = remove.closest('[data-admin-rows]'); remove.closest('[data-admin-row]')?.remove(); if (container) syncLimits(container.dataset.adminRows); }
  });
  document.addEventListener('input', event => {
    if (!event.target.matches('[data-recommendation-search]')) return;
    const query = event.target.value.trim().toLocaleLowerCase('uk'), picker = event.target.closest('.recommendation-picker');
    picker?.querySelectorAll('[data-recommendation-item]').forEach(item => item.hidden = !item.textContent.toLocaleLowerCase('uk').includes(query));
  });
  document.addEventListener('change', event => {
    if (!event.target.matches('.recommendation-list input[type="checkbox"]')) return;
    const picker = event.target.closest('.recommendation-picker'), count = picker?.querySelector('[data-recommendation-count]');
    if (count) count.textContent = picker.querySelectorAll('.recommendation-list input:checked').length;
  });
  document.querySelectorAll('[data-admin-rows]').forEach(container => syncLimits(container.dataset.adminRows));
})();
