(() => {
  'use strict';
  const data = JSON.parse(document.getElementById('encore-data').textContent);
  const contacts = new Map(data.contacts.map(contact => [String(contact.id), contact]));
  const contactDialog = document.getElementById('encore-contact-dialog');
  const contactForm = document.getElementById('encore-contact-form');
  const logDialog = document.getElementById('encore-log-dialog');
  const logForm = document.getElementById('encore-log-form');
  const historyDialog = document.getElementById('encore-history-dialog');
  const fields = ['name','kind','contact_name','email','phone','city','estimated_value','fit_notes'];
  function track(feature, action) {
    const url = document.getElementById('encore-script')?.dataset.usageUrl;
    if (!url) return;
    // Usage metadata only; never send CRM names, notes, or message contents.
    fetch(url, {
      method:'POST', credentials:'same-origin', keepalive:true,
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({feature_key:feature,action_key:action,status:'success'})
    }).catch(() => {});
  }
  function fill(form, values, names) {
    names.forEach(name => {
      const control = form.elements.namedItem(name);
      if (!control) return;
      const value = String(values[name] ?? '');
      if (control instanceof HTMLSelectElement && value && !Array.from(control.options).some(option => option.value === value)) {
        control.add(new Option(value, value));
      }
      control.value = value;
    });
  }
  function openContact(id) {
    const contact = contacts.get(String(id));
    contactForm.reset();
    contactForm.elements.namedItem('action').value = contact ? 'edit_contact' : 'add_contact';
    contactForm.elements.namedItem('contact_id').value = contact ? contact.id : '';
    document.getElementById('encore-contact-title').textContent = contact ? 'Edit Contact' : 'Add Contact';
    if (contact) fill(contactForm, contact, fields);
    contactDialog.showModal();
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;
    if (button.hasAttribute('data-close')) button.closest('dialog').close();
    if (button.hasAttribute('data-add')) { openContact(''); track('encore_contacts','open_add'); }
    if (button.hasAttribute('data-edit')) { openContact(button.dataset.edit); track('encore_contacts','open_edit'); }
    if (button.hasAttribute('data-log')) {
      logForm.reset();
      logForm.elements.namedItem('contact_id').value = button.dataset.log;
      logDialog.showModal();
      track('encore_interactions','open_log');
    }
    if (button.hasAttribute('data-history')) {
      const id = button.dataset.history;
      const template = document.getElementById('encore-history-' + id);
      document.getElementById('encore-history-title').textContent = 'History: ' + contacts.get(id).name;
      document.getElementById('encore-history-content').replaceChildren(template.content.cloneNode(true));
      historyDialog.showModal();
      track('encore_history','view_contact_history');
    }
    if (button.hasAttribute('data-detail')) {
      const detail = button.closest('.encore-activity').querySelector('.encore-activity-detail');
      detail.hidden = !detail.hidden;
      button.textContent = detail.hidden ? 'View' : 'Hide';
      button.setAttribute('aria-expanded', String(!detail.hidden));
      if (!detail.hidden) track('encore_history','view_interaction_detail');
    }
  });
  document.querySelectorAll('[data-delete]').forEach(form => {
    form.addEventListener('submit', event => {
      if (!confirm('Delete this contact and its history?')) event.preventDefault();
    });
  });
  const table = document.querySelector('.encore-table');
  const rows = Array.from(table.tBodies[0].rows);
  document.getElementById('encore-search').addEventListener('input', event => {
    const term = event.target.value.trim().toLocaleLowerCase();
    rows.forEach(row => {
      row.hidden = !(row.cells[0].textContent + ' ' + row.cells[1].textContent).toLocaleLowerCase().includes(term);
    });
    document.getElementById('encore-no-results').hidden = rows.some(row => !row.hidden);
  });
  table.querySelectorAll('[data-sort]').forEach(button => {
    button.addEventListener('click', () => {
      const index = Number(button.dataset.sort);
      const ascending = button.parentElement.getAttribute('aria-sort') !== 'ascending';
      table.querySelectorAll('[aria-sort]').forEach(header => header.setAttribute('aria-sort','none'));
      button.parentElement.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
      rows.sort((a,b) => {
        const av = a.cells[index].dataset.sortValue || a.cells[index].textContent.trim();
        const bv = b.cells[index].dataset.sortValue || b.cells[index].textContent.trim();
        return av.localeCompare(bv, undefined, {numeric:true, sensitivity:'base'}) * (ascending ? 1 : -1);
      });
      table.tBodies[0].append(...rows);
    });
  });
  if (['add_contact','edit_contact'].includes(data.failedAction)) {
    openContact(data.failedAction === 'edit_contact' ? data.submitted.contact_id : '');
    fill(contactForm, data.submitted, fields);
  } else if (data.failedAction === 'log_interaction') {
    fill(logForm, data.submitted, ['contact_id','channel','direction','subject','summary','next_date']);
    logDialog.showModal();
  }
  if (data.failedAction && (contactDialog.open || logDialog.open)) {
    const error = document.querySelector('[role="alert"]').cloneNode(true);
    (contactDialog.open ? contactForm : logForm).prepend(error);
  }
})();
