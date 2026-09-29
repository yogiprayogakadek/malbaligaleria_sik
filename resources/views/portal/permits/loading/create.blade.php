@extends('layouts.portal')

@section('title', 'Permohonan Loading Barang : Mal Bali Galeria')
@section('page-title', 'Pengajuan Loading')

@section('content')
<div class="page-wrap">

  <div class="page-header">
    <div>
      <div class="page-breadcrumb">
        <a href="{{ route('portal.dashboard') }}">Beranda</a>
        <svg width="14" height="14"><use href="#i-chevron-right"/></svg>
        <span>Pengajuan Loading</span>
      </div>
      <h1 class="page-title">Permohonan Loading Barang</h1>
      <p class="page-subtitle">Izin perpindahan barang masuk atau keluar area gedung Mal Bali Galeria.</p>
    </div>
  </div>

  @if ($errors->any())
    <div class="permit-alert permit-alert--error" role="alert">
      <svg aria-hidden="true"><use href="#i-info"/></svg>
      <div>
        <strong>Periksa isian formulir:</strong>
        <ul>
          @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  <form id="loadingForm" action="{{ route('loading.store') }}" method="POST"
        enctype="multipart/form-data" novalidate>
    @csrf

    {{-- ─── STEPPER HEADER (MOBILE WIZARD) ──────────────── --}}
    <div class="mobile-wizard-header" aria-label="Langkah pengajuan">
      <div class="wizard-stepper">
        <div class="wizard-step-indicator active" data-step="1">
          <div class="wizard-circle"><span class="step-num">1</span><svg class="step-check"><use href="#i-check"/></svg></div>
          <span class="wizard-step-label">Pemohon</span>
        </div>
        <div class="wizard-line" data-line="1"></div>
        <div class="wizard-step-indicator" data-step="2">
          <div class="wizard-circle"><span class="step-num">2</span><svg class="step-check"><use href="#i-check"/></svg></div>
          <span class="wizard-step-label">Detail</span>
        </div>
        <div class="wizard-line" data-line="2"></div>
        <div class="wizard-step-indicator" data-step="3">
          <div class="wizard-circle"><span class="step-num">3</span><svg class="step-check"><use href="#i-check"/></svg></div>
          <span class="wizard-step-label">Dokumen</span>
        </div>
        <div class="wizard-line" data-line="3"></div>
        <div class="wizard-step-indicator" data-step="4">
          <div class="wizard-circle"><span class="step-num">4</span><svg class="step-check"><use href="#i-check"/></svg></div>
          <span class="wizard-step-label">Ringkasan</span>
        </div>
      </div>
    </div>

    <div class="permit-grid">

      {{-- ─── LANGKAH 1: Data Pemohon ─────────────────── --}}
      <div class="permit-section wizard-step active" data-wizard-step="1">
        <div class="permit-section-head">
          <div class="permit-section-icon blue">
            <svg><use href="#i-user"/></svg>
          </div>
          <div>
            <h2>Data Pemohon</h2>
            <p>Informasi tenant dan penanggung jawab permohonan.</p>
          </div>
        </div>

        {{-- Nama Tenant --}}
        <div class="pf" id="pf-tenant_name">
          <label for="tenant_name">Nama Tenant / Toko <span class="req">*</span></label>
          <div class="pf-wrap">
            <svg class="pf-icon"><use href="#i-building"/></svg>
            <input id="tenant_name" name="tenant_name" type="text"
                   value="{{ old('tenant_name', $user?->tenant_name) }}"
                   placeholder="Nama tenant/toko di Mal Bali Galeria"
                   {{ $user ? 'readonly' : 'required' }}
                   data-validate="tenant_name"
                   aria-describedby="err-tenant_name">
            @if($user)
              <span class="pf-badge-locked" title="Data diambil dari akun Anda">
                <svg width="12" height="12"><use href="#i-shield-check"/></svg>
              </span>
            @endif
          </div>
          <span class="pf-error" id="err-tenant_name" role="alert" aria-live="polite">
            {{ $errors->first('tenant_name') }}
          </span>
        </div>

        {{-- Nama PIC --}}
        <div class="pf" id="pf-applicant_name">
          <label for="applicant_name">Nama Penanggung Jawab (PIC) <span class="req">*</span></label>
          <div class="pf-wrap">
            <svg class="pf-icon"><use href="#i-user"/></svg>
            <input id="applicant_name" name="applicant_name" type="text"
                   value="{{ old('applicant_name', $user?->name) }}"
                   placeholder="Nama lengkap penanggung jawab"
                   data-validate="applicant_name"
                   aria-describedby="err-applicant_name" required>
          </div>
          <span class="pf-error" id="err-applicant_name" role="alert" aria-live="polite">
            {{ $errors->first('applicant_name') }}
          </span>
        </div>

        {{-- Nomor HP --}}
        <div class="pf" id="pf-applicant_phone">
          <label for="applicant_phone">Nomor HP / WhatsApp <span class="req">*</span></label>
          <div class="pf-wrap">
            <svg class="pf-icon"><use href="#i-phone"/></svg>
            <input id="applicant_phone" name="applicant_phone" type="tel"
                   value="{{ old('applicant_phone', $user?->phone) }}"
                   placeholder="081234567890"
                   data-validate="applicant_phone"
                   aria-describedby="err-applicant_phone" required>
          </div>
          <span class="pf-error" id="err-applicant_phone" role="alert" aria-live="polite">
            {{ $errors->first('applicant_phone') }}
          </span>
        </div>

        {{-- Email --}}
        <div class="pf" id="pf-applicant_email">
          <label for="applicant_email">Email <small>(opsional)</small></label>
          <div class="pf-wrap">
            <svg class="pf-icon"><use href="#i-mail"/></svg>
            <input id="applicant_email" name="applicant_email" type="email"
                   value="{{ old('applicant_email', $user?->email) }}"
                   placeholder="email@tenant.com"
                   data-validate="applicant_email"
                   aria-describedby="err-applicant_email">
          </div>
          <span class="pf-error" id="err-applicant_email" role="alert" aria-live="polite">
            {{ $errors->first('applicant_email') }}
          </span>
        </div>
      </div>

      {{-- ─── LANGKAH 2: Detail Loading ──────────────── --}}
      <div class="permit-section wizard-step" data-wizard-step="2">
        <div class="permit-section-head">
          <div class="permit-section-icon blue">
            <svg><use href="#i-box"/></svg>
          </div>
          <div>
            <h2>Detail Loading Barang</h2>
            <p>Rincian jadwal dan barang yang akan dipindahkan.</p>
          </div>
        </div>

        {{-- Arah Loading --}}
        <div class="pf" id="pf-direction">
          <label>Arah Pergerakan Barang <span class="req">*</span></label>
          <div class="pf-radio-group" role="group" aria-labelledby="lbl-direction">
            @foreach(['in' => ['Barang Masuk', 'i-arrow-right'], 'out' => ['Barang Keluar', 'i-arrow-left'], 'both' => ['Masuk & Keluar', 'i-arrow-right']] as $val => [$label, $icon])
              <label class="pf-radio {{ old('direction') === $val ? 'selected' : '' }}">
                <input type="radio" name="direction" value="{{ $val }}"
                       {{ old('direction') === $val ? 'checked' : '' }}
                       data-validate="direction">
                <svg><use href="#{{ $icon }}"/></svg>
                <span>{{ $label }}</span>
              </label>
            @endforeach
          </div>
          <span class="pf-error" id="err-direction" role="alert" aria-live="polite">
            {{ $errors->first('direction') }}
          </span>
        </div>

        {{-- Waktu Loading / Unloading --}}
        <div class="pf" id="pf-movement_time">
          <label for="movement_time" id="movement-time-label">Waktu Loading / Unloading <span class="req">*</span></label>
          <div class="pf-wrap">
            <svg class="pf-icon"><use href="#i-clock"/></svg>
            <input id="movement_time" name="movement_time" type="time"
                   value="{{ old('movement_time') }}"
                   step="60"
                   data-validate="movement_time"
                   aria-describedby="hint-movement_time err-movement_time" required>
          </div>
          <span class="pf-hint" id="hint-movement_time">Diizinkan pukul 22.00 sampai 11.00 WITA keesokan harinya.</span>
          <span class="pf-error" id="err-movement_time" role="alert" aria-live="polite">
            {{ $errors->first('movement_time') }}
          </span>
        </div>

        {{-- Tanggal Mulai & Selesai --}}
        <div class="pf-row">
          <div class="pf" id="pf-start_date">
            <label for="start_date">Tanggal Mulai <span class="req">*</span></label>
            <div class="pf-wrap">
              <svg class="pf-icon"><use href="#i-calendar"/></svg>
              <input id="start_date" name="start_date" type="date"
                     value="{{ old('start_date') }}"
                     min="{{ date('Y-m-d') }}"
                     data-validate="start_date"
                     aria-describedby="err-start_date" required>
            </div>
            <span class="pf-error" id="err-start_date" role="alert" aria-live="polite">
              {{ $errors->first('start_date') }}
            </span>
          </div>

          <div class="pf" id="pf-end_date">
            <label for="end_date">Tanggal Selesai <span class="req">*</span></label>
            <div class="pf-wrap">
              <svg class="pf-icon"><use href="#i-calendar"/></svg>
              <input id="end_date" name="end_date" type="date"
                     value="{{ old('end_date') }}"
                     min="{{ date('Y-m-d') }}"
                     data-validate="end_date"
                     aria-describedby="err-end_date" required>
            </div>
            <span class="pf-error" id="err-end_date" role="alert" aria-live="polite">
              {{ $errors->first('end_date') }}
            </span>
            <span class="pf-hint" id="hint-end_date">Maksimal 3 hari dari tanggal mulai (contoh: tgl 1 s.d. 3).</span>
          </div>
        </div>

        {{-- Jumlah & Satuan Barang --}}
        <div class="pf-row">
          <div class="pf" id="pf-item_count">
            <label for="item_count">Jumlah Barang <span class="req">*</span></label>
            <div class="pf-wrap">
              <svg class="pf-icon"><use href="#i-box"/></svg>
              <input id="item_count" name="item_count" type="number"
                     value="{{ old('item_count') }}"
                     placeholder="Contoh: 12" min="1" max="9999"
                     data-validate="item_count"
                     aria-describedby="err-item_count" required>
            </div>
            <span class="pf-error" id="err-item_count" role="alert" aria-live="polite">
              {{ $errors->first('item_count') }}
            </span>
          </div>

          <div class="pf" id="pf-item_unit">
            <label for="item_unit">Satuan Barang <span class="req">*</span></label>
            <div class="pf-wrap">
              <svg class="pf-icon"><use href="#i-grid"/></svg>
              <input id="item_unit" name="item_unit" type="text"
                     value="{{ old('item_unit', 'pcs') }}"
                     placeholder="koli, pcs, dus"
                     list="unit-suggestions"
                     data-validate="item_unit"
                     aria-describedby="err-item_unit" required>
              <datalist id="unit-suggestions">
                <option value="pcs">
                <option value="koli">
                <option value="dus">
                <option value="box">
                <option value="unit">
                <option value="karton">
                <option value="pallet">
              </datalist>
            </div>
            <span class="pf-error" id="err-item_unit" role="alert" aria-live="polite">
              {{ $errors->first('item_unit') }}
            </span>
          </div>
        </div>

        {{-- Keterangan Barang (opsional) --}}
        <div class="pf" id="pf-item_description">
          <label for="item_description">Keterangan / Deskripsi Barang <small>(opsional)</small></label>
          <textarea id="item_description" name="item_description"
                    placeholder="Contoh: 5 dus minuman kemasan, 7 karton makanan ringan, 2 unit display stand"
                    data-validate="item_description"
                    aria-describedby="err-item_description"
                    rows="3" maxlength="500">{{ old('item_description') }}</textarea>
          <div class="pf-char-count">
            <span id="desc-count">0</span>/500
          </div>
          <span class="pf-error" id="err-item_description" role="alert" aria-live="polite">
            {{ $errors->first('item_description') }}
          </span>
        </div>
      </div>

    </div>{{-- /.permit-grid --}}

    {{-- ─── UPLOAD KTP / SIM ──────────────────────────────────────────── --}}
    <div class="permit-section permit-section--full wizard-step" data-wizard-step="3">
      <div class="permit-section-head">
        <div class="permit-section-icon rose">
          <svg><use href="#i-shield-check"/></svg>
        </div>
        <div>
          <h2>Dokumen Identitas</h2>
          <p>Upload foto KTP atau SIM penanggung jawab yang jelas dan terbaca (landscape/horizontal).</p>
        </div>
      </div>

      {{-- Jenis Dokumen --}}
      <div class="pf" id="pf-id_doc_type">
        <label>Jenis Dokumen Identitas <span class="req">*</span></label>
        <div class="pf-radio-group pf-radio-group--sm">
          <label class="pf-radio {{ old('id_doc_type', 'ktp') === 'ktp' ? 'selected' : '' }}">
            <input type="radio" name="id_doc_type" value="ktp"
                   {{ old('id_doc_type', 'ktp') === 'ktp' ? 'checked' : '' }}
                   data-validate="id_doc_type">
            <svg><use href="#i-user"/></svg>
            <span>KTP</span>
          </label>
          <label class="pf-radio {{ old('id_doc_type') === 'sim' ? 'selected' : '' }}">
            <input type="radio" name="id_doc_type" value="sim"
                   {{ old('id_doc_type') === 'sim' ? 'checked' : '' }}
                   data-validate="id_doc_type">
            <svg><use href="#i-arrow-right"/></svg>
            <span>SIM</span>
          </label>
        </div>
      </div>

      {{-- Upload Area --}}
      <div class="pf" id="pf-id_doc">
        <div class="id-upload-area" id="idUploadArea" tabindex="0" role="button"
             aria-label="Area upload foto KTP/SIM">
          <input id="id_doc" name="id_doc" type="file"
                 accept="image/jpeg,image/jpg,image/png"
                 class="id-upload-input"
                 data-validate="id_doc"
                 aria-describedby="err-id_doc" required>
          <div class="id-upload-prompt" id="idUploadPrompt">
            <div class="id-upload-icon">
              <svg><use href="#i-shield-check"/></svg>
            </div>
            <div class="id-upload-text">
              <strong>Klik atau seret foto ke sini</strong>
              <span>JPG atau PNG, maks. 4MB, orientasi horizontal</span>
            </div>
          </div>
          <div class="id-upload-preview" id="idUploadPreview" hidden>
            <img id="idPreviewImg" src="" alt="Preview dokumen identitas">
            <div class="id-upload-preview-info">
              <span id="idPreviewName"></span>
              <button type="button" class="id-upload-remove" id="idUploadRemove" aria-label="Hapus file">
                <svg><use href="#i-trash"/></svg>
              </button>
            </div>
          </div>
        </div>
        <span class="pf-error" id="err-id_doc" role="alert" aria-live="polite">
          {{ $errors->first('id_doc') }}
        </span>
        <div class="id-upload-validation" id="idValidationFeedback" hidden>
          <svg aria-hidden="true"><use href="#i-info"/></svg>
          <span id="idValidationMsg"></span>
        </div>
      </div>
    </div>

    {{-- ─── LANGKAH 4: Ringkasan & Konfirmasi ──────────────────────────── --}}
    <div class="permit-section permit-section--full wizard-step" data-wizard-step="4">
      <div class="permit-section-head">
        <div class="permit-section-icon amber">
          <svg><use href="#i-check"/></svg>
        </div>
        <div>
          <h2>Ringkasan Permohonan</h2>
          <p>Periksa kembali semua data sebelum mengajukan.</p>
        </div>
      </div>

      {{-- Review: Data Pemohon --}}
      <div class="review-section">
        <div class="review-section-title">
          <svg><use href="#i-user"/></svg>
          Data Pemohon
          <button type="button" class="review-edit-link" data-goto-step="1">Ubah</button>
        </div>
        <dl class="review-dl">
          <dt>Nama Tenant</dt><dd id="rv-tenant_name">—</dd>
          <dt>Nama PIC</dt><dd id="rv-applicant_name">—</dd>
          <dt>Nomor HP</dt><dd id="rv-applicant_phone">—</dd>
          <dt>Email</dt><dd id="rv-applicant_email">—</dd>
        </dl>
      </div>

      {{-- Review: Detail Loading --}}
      <div class="review-section">
        <div class="review-section-title">
          <svg><use href="#i-box"/></svg>
          Detail Loading
          <button type="button" class="review-edit-link" data-goto-step="2">Ubah</button>
        </div>
        <dl class="review-dl">
          <dt>Arah</dt><dd id="rv-direction">—</dd>
          <dt id="rv-movement_time_label">Waktu Loading / Unloading</dt><dd id="rv-movement_time">—</dd>
          <dt>Tanggal Mulai</dt><dd id="rv-start_date">—</dd>
          <dt>Tanggal Selesai</dt><dd id="rv-end_date">—</dd>
          <dt>Jumlah Barang</dt><dd id="rv-item_count">—</dd>
          <dt>Keterangan</dt><dd id="rv-item_description">—</dd>
        </dl>
      </div>

      {{-- Review: Dokumen Identitas --}}
      <div class="review-section">
        <div class="review-section-title">
          <svg><use href="#i-shield-check"/></svg>
          Dokumen Identitas
          <button type="button" class="review-edit-link" data-goto-step="3">Ubah</button>
        </div>
        <dl class="review-dl">
          <dt>Jenis Dokumen</dt><dd id="rv-id_doc_type">—</dd>
        </dl>
        <div class="review-doc-preview" id="rv-doc-preview" hidden>
          <svg><use href="#i-shield-check"/></svg>
          <span id="rv-doc-name">—</span>
        </div>
      </div>

      <p class="review-agree-note">
        Dengan mengajukan permohonan ini, Anda menyatakan bahwa seluruh informasi di atas benar
        dan Anda bertanggung jawab penuh atas kegiatan loading yang dilakukan.
      </p>
    </div>

    {{-- ─── SUBMIT (Desktop only) ──────────────────────────────────────────── --}}
    <div class="permit-submit-row">
      <p class="permit-submit-note">
        Dengan mengajukan permohonan ini, Anda menyatakan bahwa informasi yang diberikan benar
        dan bertanggung jawab atas kegiatan loading yang dilakukan.
      </p>
      <div class="permit-submit-actions">
        <a href="{{ route('portal.dashboard') }}" class="btn-secondary">Batal</a>
        <button type="submit" class="btn-primary" id="submitBtn" disabled>
          <span class="btn-label">Ajukan Permohonan</span>
          <span class="btn-spinner" aria-hidden="true"></span>
        </button>
      </div>
    </div>

    {{-- ─── MOBILE WIZARD BOTTOM BAR ────────────────────────────────────── --}}
    <div class="mobile-wizard-nav" id="mobileWizardNav">
      <button type="button" class="btn-wizard-prev" id="wizardPrevBtn" style="display:none;">
        <svg><use href="#i-chevron-left"/></svg>
        <span>Sebelumnya</span>
      </button>
      <button type="button" class="btn-wizard-next" id="wizardNextBtn">
        <span>Lanjut</span>
        <svg><use href="#i-chevron-right"/></svg>
      </button>
      <button type="submit" class="btn-wizard-submit" id="wizardSubmitBtn" style="display:none;" disabled>
        <svg><use href="#i-check"/></svg>
        <span>Ajukan Permohonan</span>
      </button>
    </div>

  </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  // ── Tanggal: max 3 hari dari start ─────────────────────────────────────────
  const startInput = document.getElementById('start_date');
  const endInput   = document.getElementById('end_date');

  function updateEndDateConstraints() {
    if (!startInput.value) return;
    const start = new Date(startInput.value + 'T00:00:00');

    // Min end = start date
    endInput.min = startInput.value;

    // Max end = start + 2 days (inclusive 3 days: tgl 1 s.d. 3)
    const maxEnd = new Date(start);
    maxEnd.setDate(maxEnd.getDate() + 2);
    const yyyy = maxEnd.getFullYear();
    const mm = String(maxEnd.getMonth() + 1).padStart(2, '0');
    const dd = String(maxEnd.getDate()).padStart(2, '0');
    const maxStr = `${yyyy}-${mm}-${dd}`;
    endInput.max = maxStr;

    // Reset if current end_date violates new max
    if (endInput.value && endInput.value > maxStr) {
      endInput.value = maxStr;
      showEndDateHint('Tanggal selesai disesuaikan ke maksimal 3 hari.');
    } else {
      clearEndDateHint();
    }
    validateField(endInput);
  }

  function showEndDateHint(msg) {
    const hint = document.getElementById('hint-end_date');
    if (hint) { hint.textContent = msg; hint.classList.add('pf-hint--warn'); }
  }

  function clearEndDateHint() {
    const hint = document.getElementById('hint-end_date');
    if (hint) { hint.textContent = 'Maksimal 3 hari dari tanggal mulai (contoh: tgl 1 s.d. 3).'; hint.classList.remove('pf-hint--warn'); }
  }

  startInput?.addEventListener('change', updateEndDateConstraints);

  // ── Radio styling ──────────────────────────────────────────────────────────
  function syncMovementTimeLabel() {
    const direction = document.querySelector('[name="direction"]:checked')?.value;
    const labels = { in: 'Waktu Unloading', out: 'Waktu Loading', both: 'Waktu Loading & Unloading' };
    const label = document.getElementById('movement-time-label');
    if (label) label.firstChild.textContent = `${labels[direction] || 'Waktu Loading / Unloading'} `;
  }

  document.querySelectorAll('.pf-radio-group').forEach(group => {
    group.querySelectorAll('input[type="radio"]').forEach(radio => {
      radio.addEventListener('change', () => {
        group.querySelectorAll('.pf-radio').forEach(lbl => lbl.classList.remove('selected'));
        if (radio.checked) radio.closest('.pf-radio').classList.add('selected');
        validateField(radio);
        if (radio.name === 'direction') syncMovementTimeLabel();
      });
    });
  });
  syncMovementTimeLabel();

  // ── Char counter ───────────────────────────────────────────────────────────
  const descTextarea = document.getElementById('item_description');
  const descCount    = document.getElementById('desc-count');
  if (descTextarea && descCount) {
    descTextarea.addEventListener('input', () => {
      descCount.textContent = descTextarea.value.length;
    });
    descCount.textContent = descTextarea.value.length;
  }

  // ── KTP/SIM Upload & Preview ───────────────────────────────────────────────
  const fileInput   = document.getElementById('id_doc');
  const uploadArea  = document.getElementById('idUploadArea');
  const prompt      = document.getElementById('idUploadPrompt');
  const preview     = document.getElementById('idUploadPreview');
  const previewImg  = document.getElementById('idPreviewImg');
  const previewName = document.getElementById('idPreviewName');
  const removeBtn   = document.getElementById('idUploadRemove');
  const validFb     = document.getElementById('idValidationFeedback');
  const validMsg    = document.getElementById('idValidationMsg');

  function showPreview(file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      previewImg.src = e.target.result;
      previewName.textContent = file.name;
      prompt.hidden = true;
      preview.hidden = false;
      uploadArea.classList.add('has-file');

      // Client-side dimension/ratio check
      const img = new Image();
      img.onload = () => {
        const ratio = img.width / img.height;
        if (img.width < 200 || img.height < 100) {
          showValidationFeedback('Gambar terlalu kecil. Gunakan foto yang lebih jelas.', 'warn');
        } else if (ratio < 1.2 || ratio > 2.5) {
          showValidationFeedback('Proporsi gambar tidak sesuai KTP/SIM. Pastikan foto diambil horizontal/landscape.', 'warn');
        } else {
          showValidationFeedback('Dimensi gambar sesuai format KTP/SIM.', 'ok');
        }
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  function showValidationFeedback(msg, type) {
    validFb.hidden = false;
    validMsg.textContent = msg;
    validFb.className = 'id-upload-validation id-upload-validation--' + type;
  }

  function clearUpload() {
    fileInput.value = '';
    previewImg.src = '';
    previewName.textContent = '';
    prompt.hidden = false;
    preview.hidden = true;
    uploadArea.classList.remove('has-file');
    validFb.hidden = true;
    fieldStates['id_doc'] = 'empty';
    updateSubmitButton();
  }

  fileInput?.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;

    // Size check
    if (file.size > 4 * 1024 * 1024) {
      setFieldError('id_doc', 'Ukuran file melebihi 4MB. Kompres gambar terlebih dahulu.');
      clearUpload();
      return;
    }
    clearFieldError('id_doc');
    showPreview(file);
    fieldStates['id_doc'] = 'valid';
    updateSubmitButton();
  });

  removeBtn?.addEventListener('click', clearUpload);

  // Drag & drop
  uploadArea?.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
  uploadArea?.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
  uploadArea?.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('drag-over');
    const file = e.dataTransfer?.files[0];
    if (file && file.type.startsWith('image/')) {
      const dt = new DataTransfer();
      dt.items.add(file);
      fileInput.files = dt.files;
      fileInput.dispatchEvent(new Event('change'));
    }
  });

  // Keyboard: open file dialog on Space/Enter
  uploadArea?.addEventListener('keydown', (e) => {
    if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); fileInput.click(); }
  });

  // ── Generic field validation ───────────────────────────────────────────────
  const fieldStates = {};

  const RULES = {
    'tenant_name':      v => !v ? 'Nama tenant wajib diisi.' : v.length < 2 ? 'Nama terlalu singkat.' : null,
    'applicant_name':   v => !v ? 'Nama PIC wajib diisi.' : v.length < 2 ? 'Nama terlalu singkat.' : null,
    'applicant_phone':  v => !v ? 'Nomor HP wajib diisi.' : !/^[\d\+\-\s\(\)]{9,25}$/.test(v) ? 'Format nomor HP tidak valid.' : null,
    'applicant_email':  v => v && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? 'Format email tidak valid.' : null,
    'direction':        (_, el) => !document.querySelector('[name="direction"]:checked') ? 'Arah pergerakan wajib dipilih.' : null,
    'movement_time': v => {
      if (!v) return 'Waktu loading atau unloading wajib diisi.';
      if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(v)) return 'Format waktu tidak valid.';

      const [hours, minutes] = v.split(':').map(Number);
      const totalMinutes = (hours * 60) + minutes;

      return totalMinutes > (11 * 60) && totalMinutes < (22 * 60)
        ? 'Waktu hanya diizinkan pukul 22.00 sampai 11.00 WITA.'
        : null;
    },
    'start_date':       v => !v ? 'Tanggal mulai wajib diisi.' : new Date(v + 'T00:00:00') < new Date(new Date().toDateString()) ? 'Tanggal tidak boleh sebelum hari ini.' : null,
    'end_date':         v => {
      if (!v) return 'Tanggal selesai wajib diisi.';
      const s = startInput?.value;
      if (s && v < s) return 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
      if (s) {
        const diff = Math.round((new Date(v + 'T00:00:00') - new Date(s + 'T00:00:00')) / 86400000);
        if (diff > 2) return 'Tanggal selesai maksimal 3 hari dari tanggal mulai (contoh: tgl 1 s.d. 3).';
      }
      return null;
    },
    'item_count':       v => !v ? 'Jumlah barang wajib diisi.' : (parseInt(v) < 1 || parseInt(v) > 9999) ? 'Jumlah harus antara 1-9999.' : null,
    'item_unit':        v => !v ? 'Satuan barang wajib diisi.' : v.length > 30 ? 'Maksimal 30 karakter.' : null,
    'item_description': v => v && v.length > 500 ? 'Maksimal 500 karakter.' : null,
    'id_doc_type':      (_, el) => !document.querySelector('[name="id_doc_type"]:checked') ? 'Pilih jenis dokumen.' : null,
    'id_doc':           () => null, // Handled separately via file input
  };

  function setFieldError(rule, msg) {
    const errEl = document.getElementById('err-' + rule);
    const wrap  = document.getElementById('pf-' + rule);
    if (errEl) errEl.textContent = msg;
    wrap?.classList.add('pf--invalid');
    wrap?.classList.remove('pf--valid');
    fieldStates[rule] = 'invalid';
    updateSubmitButton();
  }

  function clearFieldError(rule) {
    const errEl = document.getElementById('err-' + rule);
    const wrap  = document.getElementById('pf-' + rule);
    if (errEl) errEl.textContent = '';
    wrap?.classList.remove('pf--invalid');
    fieldStates[rule] = 'valid';
    updateSubmitButton();
  }

  function validateField(input) {
    const rule = input.dataset.validate;
    if (!rule || !RULES[rule]) return;

    const val = (input.type === 'radio' || input.type === 'checkbox') ? input.value : input.value.trim();
    const error = RULES[rule](val, input);

    if (error) setFieldError(rule, error);
    else { clearFieldError(rule); document.getElementById('pf-' + rule)?.classList.add('pf--valid'); }
  }

  function updateSubmitButton() {
    const desktopBtn = document.getElementById('submitBtn');
    const mobileBtn  = document.getElementById('wizardSubmitBtn');
    const required = ['tenant_name','applicant_name','applicant_phone','direction','start_date','end_date','item_count','item_unit','id_doc'];
    const allValid = required.every(r => fieldStates[r] === 'valid');
    if (desktopBtn) desktopBtn.disabled = !allValid;
    if (mobileBtn)  mobileBtn.disabled = !allValid;
  }

  document.querySelectorAll('[data-validate]').forEach(input => {
    const isRadioOrCheck = (input.type === 'radio' || input.type === 'checkbox');
    const ev = isRadioOrCheck ? 'change' : 'input';
    input.addEventListener(ev, () => validateField(input));
    input.addEventListener('blur', () => validateField(input));
    if (isRadioOrCheck ? input.checked : Boolean(input.value)) {
      validateField(input);
    }
  });

  // ── Mobile Wizard Logic ───────────────────────────────────────────────────
  let currentStep = 1;
  const totalSteps = 4;

  const stepRequiredFields = {
    1: ['tenant_name', 'applicant_name', 'applicant_phone'],
    2: ['direction', 'start_date', 'end_date', 'item_count', 'item_unit'],
    3: ['id_doc'],
    4: [] // Review step — no new required fields
  };

  const prevBtn = document.getElementById('wizardPrevBtn');
  const nextBtn = document.getElementById('wizardNextBtn');
  const wizardSubmitBtn = document.getElementById('wizardSubmitBtn');

  // Populate review fields when entering step 4
  function populateReview() {
    const directionMap = { in: 'Barang Masuk', out: 'Barang Keluar', both: 'Masuk & Keluar' };
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };

    set('rv-tenant_name',     document.getElementById('tenant_name')?.value);
    set('rv-applicant_name',  document.getElementById('applicant_name')?.value);
    set('rv-applicant_phone', document.getElementById('applicant_phone')?.value);
    const email = document.getElementById('applicant_email')?.value;
    set('rv-applicant_email', email || '-');

    const dirVal = document.querySelector('[name="direction"]:checked')?.value;
    set('rv-direction', dirVal ? directionMap[dirVal] : '—');
    const movementLabels = { in: 'Waktu Unloading', out: 'Waktu Loading', both: 'Waktu Loading & Unloading' };
    set('rv-movement_time_label', movementLabels[dirVal] || 'Waktu Loading / Unloading');
    const movementTime = document.getElementById('movement_time')?.value;
    set('rv-movement_time', movementTime ? `${movementTime} WITA` : '—');

    const startDate = document.getElementById('start_date')?.value;
    const endDate   = document.getElementById('end_date')?.value;
    const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '—';
    set('rv-start_date', fmtDate(startDate));
    set('rv-end_date',   fmtDate(endDate));

    const count = document.getElementById('item_count')?.value;
    const unit  = document.getElementById('item_unit')?.value || 'pcs';
    set('rv-item_count', count ? `${count} ${unit}` : '—');

    const desc = document.getElementById('item_description')?.value;
    set('rv-item_description', desc || '-');

    const docType = document.querySelector('[name="id_doc_type"]:checked')?.value;
    set('rv-id_doc_type', docType ? docType.toUpperCase() : '—');

    const docFile = fileInput?.files[0];
    const docPreview = document.getElementById('rv-doc-preview');
    const docName    = document.getElementById('rv-doc-name');
    if (docFile && docPreview && docName) {
      docName.textContent = docFile.name;
      docPreview.hidden = false;
    } else if (docPreview) {
      docPreview.hidden = true;
    }
  }

  function setStep(step) {
    if (step < 1 || step > totalSteps) return;
    currentStep = step;

    if (step === 4) {
      populateReview();
      updateSubmitButton();
    }

    // Show/hide wizard-step cards on mobile
    document.querySelectorAll('.wizard-step').forEach(el => {
      const stepNum = parseInt(el.getAttribute('data-wizard-step'), 10);
      el.classList.toggle('active', stepNum === currentStep);
    });

    // Update wizard stepper indicators in header
    document.querySelectorAll('.wizard-step-indicator').forEach(ind => {
      const s = parseInt(ind.getAttribute('data-step'), 10);
      ind.classList.remove('active', 'completed');
      if (s === currentStep) ind.classList.add('active');
      else if (s < currentStep) ind.classList.add('completed');
    });

    // Update connector lines
    document.querySelectorAll('.wizard-line').forEach(line => {
      const l = parseInt(line.getAttribute('data-line'), 10);
      line.classList.toggle('completed', l < currentStep);
    });

    // Button visibility
    if (prevBtn) prevBtn.style.display = (currentStep > 1) ? 'inline-flex' : 'none';
    if (nextBtn) nextBtn.style.display = (currentStep < totalSteps) ? 'inline-flex' : 'none';
    if (wizardSubmitBtn) wizardSubmitBtn.style.display = (currentStep === totalSteps) ? 'inline-flex' : 'none';

    // Scroll to wizard header on mobile
    if (window.innerWidth < 860) {
      document.querySelector('.mobile-wizard-header')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function validateStep(step) {
    const requiredList = stepRequiredFields[step] || [];
    let isValid = true;
    requiredList.forEach(rule => {
      if (rule === 'id_doc') {
        if (!fileInput?.files[0]) {
          setFieldError('id_doc', 'Upload foto KTP atau SIM wajib dilampirkan.');
          isValid = false;
        }
      } else {
        const input = document.querySelector(`[data-validate="${rule}"]`);
        if (input) {
          validateField(input);
          if (fieldStates[rule] !== 'valid') isValid = false;
        }
      }
    });
    return isValid;
  }

  prevBtn?.addEventListener('click', () => {
    if (currentStep > 1) setStep(currentStep - 1);
  });

  nextBtn?.addEventListener('click', () => {
    if (validateStep(currentStep)) setStep(currentStep + 1);
  });

  // Review "Ubah" links jump to specific step
  document.querySelectorAll('[data-goto-step]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = parseInt(btn.getAttribute('data-goto-step'), 10);
      if (target >= 1 && target < currentStep) setStep(target);
    });
  });

  // Clicking completed step indicators in the header
  document.querySelectorAll('.wizard-step-indicator').forEach(ind => {
    ind.addEventListener('click', () => {
      const targetStep = parseInt(ind.getAttribute('data-step'), 10);
      if (targetStep < currentStep) {
        setStep(targetStep);
      } else if (targetStep === currentStep + 1 && validateStep(currentStep)) {
        setStep(targetStep);
      }
    });
    ind.style.cursor = 'pointer';
  });

  // Initialize step 1
  setStep(1);


  // ── Form submit ────────────────────────────────────────────────────────────
  document.getElementById('loadingForm')?.addEventListener('submit', (e) => {
    let hasError = false;
    document.querySelectorAll('[data-validate]').forEach(input => {
      validateField(input);
      const rule = input.dataset.validate;
      if (rule && fieldStates[rule] === 'invalid') hasError = true;
    });
    if (!fileInput?.files[0]) {
      setFieldError('id_doc', 'Upload foto KTP atau SIM wajib dilampirkan.');
      hasError = true;
    }
    if (hasError) {
      e.preventDefault();
      // On mobile jump to first step with an error
      for (let s = 1; s <= 3; s++) {
        const reqs = stepRequiredFields[s] || [];
        const hasStepErr = reqs.some(r => r === 'id_doc'
          ? !fileInput?.files[0]
          : fieldStates[r] !== 'valid'
        );
        if (hasStepErr) {
          setStep(s);
          break;
        }
      }
      return;
    }

    // Show loading state on desktop and mobile submit buttons
    const desktopBtn = document.getElementById('submitBtn');
    if (desktopBtn) {
      desktopBtn.classList.add('loading');
      desktopBtn.disabled = true;
      desktopBtn.querySelector('.btn-label').textContent = 'Mengajukan...';
    }
    if (wizardSubmitBtn) {
      wizardSubmitBtn.disabled = true;
      wizardSubmitBtn.querySelector('span').textContent = 'Mengajukan...';
    }
  });

})();
</script>
@endpush
