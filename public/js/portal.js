(() => {
  'use strict';

  const TYPES = {
    loading:    { title: 'Loading & unloading barang', subtitle: 'Ajukan izin perpindahan barang masuk atau keluar area.', details: 'Detail barang & kendaraan', code: 'LOG' },
    work:       { title: 'Surat izin kerja',           subtitle: 'Ajukan izin pekerjaan teknis atau operasional di area.', details: 'Detail pekerjaan',          code: 'KRJ' },
    exhibition: { title: 'Surat izin pameran',         subtitle: 'Ajukan izin aktivasi dan display pameran.',             details: 'Detail pameran',            code: 'PMR' },
    event:      { title: 'Surat izin event',           subtitle: 'Ajukan izin penyelenggaraan acara dan kegiatan.',       details: 'Detail event',              code: 'EVT' }
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

  const form = $('#requestForm');
  const sheet = $('#createSheet');
  const backdrop = $('#sheetBackdrop');
  let activeType = 'loading';
  let lastFocus = null;
  let toastTimer;

  // Temporary in-memory session store only (no database, no localStorage)
  let sessionRequests = [];

  function localToday() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  function dateLabel(value) {
    if (!value) return '-';
    const date = new Date(`${value}T12:00:00`);
    return Number.isNaN(date.getTime())
      ? '-'
      : new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
  }

  function phoneKey(value) {
    const digits = String(value || '').replace(/\D/g, '');
    return digits.startsWith('0') ? `62${digits.slice(1)}` : digits;
  }

  function notify(message) {
    const toast = $('#toast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3300);
  }

  const VIEW_TITLES = {
    home:    'Beranda',
    form:    'Formulir Permohonan',
    success: 'Permohonan Tersimpan',
    history: 'Permohonan Saya',
    track:   'Cek Status',
    help:    'Bantuan'
  };

  function navigate(view, writeHash = true) {
    if (!$('#view-' + view)) view = 'home';
    closeSheet();

    $$('.view').forEach(section => section.classList.toggle('active', section.id === 'view-' + view));

    const selected = view === 'success' ? 'history' : view;
    $$('[data-view-link]').forEach(button => {
      const isActive = button.dataset.viewLink === selected;
      button.classList.toggle('active', isActive);
      button.setAttribute('aria-selected', String(isActive));
    });

    const topbarTitle = $('#topbarTitle');
    if (topbarTitle) topbarTitle.textContent = VIEW_TITLES[view] || 'Mal Bali Galeria';

    if (writeHash) {
      const hash = view === 'home' ? '#beranda' : '#' + view;
      if (location.hash !== hash) history.replaceState(null, '', hash);
    }

    window.scrollTo({ top: 0, behavior: 'auto' });

    if (view === 'history') renderHistory();
    if (view === 'track') {
      const tr = $('#trackResult');
      if (tr) tr.replaceChildren();
    }
    const main = $('#mainContent');
    if (main) main.focus({ preventScroll: true });
  }

  function openSheet() {
    lastFocus = document.activeElement;
    if (backdrop) backdrop.hidden = false;
    if (sheet) sheet.hidden = false;
    document.body.style.overflow = 'hidden';
    const closeBtn = $('#closeSheet');
    if (closeBtn) closeBtn.focus();
  }

  function closeSheet() {
    if (!sheet || sheet.hidden) return;
    sheet.hidden = true;
    if (backdrop) backdrop.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus && lastFocus.isConnected) lastFocus.focus();
  }

  function addItem(item = {}) {
    const row = document.createElement('div');
    row.className = 'item-row';
    row.innerHTML = `
      <input class="item-name" aria-label="Nama barang" placeholder="Nama barang" maxlength="120">
      <input class="item-qty" aria-label="Jumlah barang" type="number" min="1" max="100000" placeholder="Jumlah">
      <button type="button" class="remove-item-btn" aria-label="Hapus barang">
        <svg><use href="#i-trash"/></svg>
      </button>
    `;
    $('.item-name', row).value = item.name || '';
    $('.item-qty', row).value = item.qty || '';
    $('#itemsList')?.append(row);
    syncItemButtons();
  }

  function syncItemButtons() {
    const rows = $$('.item-row');
    rows.forEach(row => {
      const btn = $('.remove-item-btn', row);
      if (btn) btn.disabled = rows.length === 1;
    });
  }

  function startForm(type) {
    if (!TYPES[type]) return;
    activeType = type;
    if (form) {
      form.reset();
      $$('.is-invalid', form).forEach(field => field.classList.remove('is-invalid'));
    }

    const permitTypeInput = $('#permitType');
    if (permitTypeInput) permitTypeInput.value = type;

    const formTitle = $('#formTitle');
    if (formTitle) formTitle.textContent = TYPES[type].title;

    const formSubtitle = $('#formSubtitle');
    if (formSubtitle) formSubtitle.textContent = TYPES[type].subtitle;

    const detailsHeading = $('#detailsHeading');
    if (detailsHeading) detailsHeading.textContent = TYPES[type].details;

    const groupMap = {
      loading:    'loadingFields',
      work:       'workFields',
      exhibition: 'exhibitionFields',
      event:      'eventFields'
    };

    $$('.type-fields').forEach(group => {
      const active = group.id === groupMap[type];
      group.hidden = !active;
      $$('input, select, textarea', group).forEach(field => field.disabled = !active);
    });

    const required = {
      loading:    ['movement', 'vehicle', 'driver'],
      work:       ['workCategory', 'workerCount', 'contractor', 'workDescription'],
      exhibition: ['exhibitionName', 'exhibitionDescription'],
      event:      ['eventName', 'attendees', 'eventDescription']
    };

    $$('.type-fields input, .type-fields select, .type-fields textarea').forEach(field => field.required = false);
    required[type]?.forEach(id => {
      const field = $('#' + id);
      if (field) field.required = true;
    });

    const itemsList = $('#itemsList');
    if (itemsList) itemsList.replaceChildren();
    if (type === 'loading') addItem();

    const startDate = $('#startDate');
    const endDate = $('#endDate');
    if (startDate) startDate.min = localToday();
    if (endDate) endDate.min = localToday();

    navigate('form');
  }

  function markInvalid(field, message) {
    field.classList.add('is-invalid');
    field.focus();
    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    notify(message);
  }

  function checkForm() {
    if (!form) return false;
    const fields = $$('input:not([type="hidden"]), select, textarea', form).filter(
      field => !field.disabled && !field.closest('.item-row')
    );

    for (const field of fields) {
      if (!field.checkValidity()) {
        markInvalid(field, field.id === 'consent' ? 'Setujui pernyataan kebenaran data.' : 'Lengkapi kolom wajib dengan data yang valid.');
        return false;
      }
    }

    const phone = $('#phone');
    if (phone && (phoneKey(phone.value).length < 10 || phoneKey(phone.value).length > 15)) {
      markInvalid(phone, 'Masukkan nomor WhatsApp yang valid (10-15 digit).');
      return false;
    }

    const startDate = $('#startDate');
    const endDate = $('#endDate');
    if (startDate && endDate && startDate.value > endDate.value) {
      markInvalid(endDate, 'Tanggal selesai harus sama atau setelah tanggal mulai.');
      return false;
    }

    const startTime = $('#startTime');
    const endTime = $('#endTime');
    if (startDate && endDate && startTime && endTime && startDate.value === endDate.value && startTime.value >= endTime.value) {
      markInvalid(endTime, 'Jam selesai harus setelah jam mulai.');
      return false;
    }

    if (activeType === 'loading') {
      const rows = $$('.item-row');
      if (rows.length === 0) {
        notify('Tambahkan minimal 1 item barang.');
        return false;
      }
      for (const row of rows) {
        for (const field of $$('.item-name, .item-qty', row)) {
          if (!field.value.trim() || !field.checkValidity()) {
            markInvalid(field, 'Isi nama dan jumlah setiap barang.');
            return false;
          }
        }
      }
    }

    return true;
  }

  function makeReference() {
    const year = new Date().getFullYear();
    const random = new Uint32Array(1);
    crypto.getRandomValues(random);
    return `MBG-${year}-${TYPES[activeType].code}-${String(random[0] % 1000000).padStart(6, '0')}`;
  }

  function submitRequest(event) {
    event.preventDefault();
    if (!checkForm()) return;

    const data = Object.fromEntries(new FormData(form).entries());
    const ref = makeReference();
    const request = {
      ...data,
      reference: ref,
      status: 'Menunggu peninjauan',
      submittedAt: new Date().toISOString(),
      items: activeType === 'loading'
        ? $$('.item-row').map(row => ({
            name: $('.item-name', row).value.trim(),
            qty: Number($('.item-qty', row).value)
          }))
        : []
    };

    // Save only to temporary in-memory session (no DB, no localStorage)
    sessionRequests.unshift(request);

    const refTarget = $('#successReference');
    if (refTarget) refTarget.textContent = request.reference;

    const metaTarget = $('#successMeta');
    if (metaTarget) metaTarget.textContent = `${TYPES[activeType].title} · ${dateLabel(request.startDate)}`;

    // Reset form fields to remain empty as requested
    form.reset();

    navigate('success');
  }

  function makeElement(tag, className, content) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (content !== undefined) element.textContent = content;
    return element;
  }

  function requestCard(request, showDetails = false) {
    const card = makeElement('article', showDetails ? 'result-card' : 'history-card');
    const top = makeElement('div', 'history-top');
    const heading = makeElement('div');
    heading.append(makeElement('span', 'history-ref', request.reference));
    heading.append(makeElement('h2', '', TYPES[request.permitType]?.title || 'Surat izin'));
    top.append(heading, makeElement('span', 'status-pill', request.status));
    card.append(top);

    if (showDetails) {
      card.append(makeElement('p', '', 'Permohonan berhasil tercatat pada sesi prototipe ini.'));
      const list = makeElement('dl');
      [
        ['Tenant', request.company],
        ['Penanggung jawab', request.contact],
        ['Area', request.location],
        ['Tanggal', `${dateLabel(request.startDate)}${request.startDate !== request.endDate ? ' s.d. ' + dateLabel(request.endDate) : ''}`],
        ['Waktu', `${request.startTime} - ${request.endTime} WITA`]
      ].forEach(([label, value]) => {
        list.append(makeElement('dt', '', label), makeElement('dd', '', value || '-'));
      });
      card.append(list);
    } else {
      const meta = makeElement('div', 'history-meta');
      const date = makeElement('span');
      date.innerHTML = '<svg><use href="#i-calendar"/></svg>';
      date.append(document.createTextNode(dateLabel(request.startDate)));
      const company = makeElement('span', '', request.company || '-');
      meta.append(date, company);
      card.append(meta);
    }
    return card;
  }

  function renderHistory() {
    const target = $('#historyList');
    if (!target) return;
    target.replaceChildren();

    if (!sessionRequests.length) {
      const empty = makeElement('div', 'empty-state');
      empty.innerHTML = `
        <div class="empty-state-icon"><svg><use href="#i-file"/></svg></div>
        <h2>Belum ada permohonan</h2>
        <p>Permohonan yang diajukan selama sesi ini akan tampil di sini.</p>
        <button type="button" class="btn-primary" data-start="loading">
          <svg><use href="#i-plus"/></svg> Buat permohonan pertama
        </button>
      `;
      empty.querySelector('[data-start]')?.addEventListener('click', () => startForm('loading'));
      target.append(empty);
      return;
    }

    sessionRequests.forEach(request => target.append(requestCard(request)));
  }

  function trackRequest(event) {
    event.preventDefault();
    const reference = $('#trackReference')?.value.trim().toUpperCase() || '';
    const phone = phoneKey($('#trackPhone')?.value || '');
    const found = sessionRequests.find(entry => entry.reference === reference && phoneKey(entry.phone) === phone);
    const result = $('#trackResult');
    if (!result) return;
    result.replaceChildren();

    if (found) {
      result.append(requestCard(found, true));
    } else {
      const notFound = makeElement('div', 'result-card');
      notFound.append(makeElement('h2', '', 'Permohonan tidak ditemukan'));
      notFound.append(makeElement('p', '', 'Periksa kembali nomor permohonan dan nomor WhatsApp yang didaftarkan.'));
      result.append(notFound);
    }
  }

  /* ── Event Bindings ───────────────────────────────────────── */

  // Navigation clicks
  $$('[data-view-link]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const targetView = btn.dataset.viewLink;
      if (targetView) navigate(targetView);
    });
  });

  // Start permit form buttons
  $$('[data-start]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const type = btn.dataset.start;
      if (type) startForm(type);
    });
  });

  // Forms submit
  if (form) form.addEventListener('submit', submitRequest);
  const trackForm = $('#trackForm');
  if (trackForm) trackForm.addEventListener('submit', trackRequest);

  // Dynamic items list
  $('#addItem')?.addEventListener('click', () => addItem());
  $('#itemsList')?.addEventListener('click', e => {
    const removeBtn = e.target.closest('.remove-item-btn');
    if (removeBtn) {
      const row = removeBtn.closest('.item-row');
      if (row && $$('.item-row').length > 1) {
        row.remove();
        syncItemButtons();
      }
    }
  });

  // Copy reference button in success view
  $('#copyReference')?.addEventListener('click', async () => {
    const text = $('#successReference')?.textContent || '';
    if (!text) return;
    try {
      await navigator.clipboard.writeText(text);
      notify('Nomor referensi berhasil disalin ke papan klip.');
    } catch {
      notify('Gagal menyalin otomatis. Silakan salin teks secara manual.');
    }
  });

  // Sheet openers / closers
  $('#createShortcut')?.addEventListener('click', openSheet);
  $('#closeSheet')?.addEventListener('click', closeSheet);
  backdrop?.addEventListener('click', closeSheet);

  window.addEventListener('keydown', e => {
    if (e.key === 'Escape' && sheet && !sheet.hidden) {
      closeSheet();
    }
  });

  // Real-time invalid clear
  document.addEventListener('input', e => {
    if (e.target.matches('input, select, textarea')) {
      e.target.classList.remove('is-invalid');
    }
  });

  // URL hash change
  window.addEventListener('hashchange', () => {
    const target = {
      '#beranda':  'home',
      '#form':     'form',
      '#history':  'history',
      '#track':    'track',
      '#help':     'help'
    }[location.hash] || 'home';
    navigate(target, false);
  });

  /* ── Boot ─────────────────────────────────────────────────── */
  const todayLabel = $('#todayLabel');
  if (todayLabel) {
    todayLabel.textContent = new Intl.DateTimeFormat('id-ID', {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
    }).format(new Date());
  }

  const initialView = {
    '#beranda':  'home',
    '#form':     'form',
    '#history':  'history',
    '#track':    'track',
    '#help':     'help'
  }[location.hash] || 'home';

  navigate(initialView, false);

})();
