@extends('layouts.portal')

@section('title', 'Permohonan Surat Izin Event : Mal Bali Galeria')
@section('page-title', 'Surat Izin Event')

@section('content')
<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><a href="{{ route('portal.dashboard') }}">Beranda</a><svg><use href="#i-chevron-right"/></svg><span>Surat Izin Event</span></div>
      <h1 class="page-title">Permohonan Surat Izin Event</h1>
      <p class="page-subtitle">Pengajuan kegiatan Event Organizer atau tenant yang diperiksa oleh Divisi Event Promotion.</p>
    </div>
  </div>

  @if($errors->any())
    <div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><div><strong>Periksa kembali formulir:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
  @endif

  <form id="eventPermitForm" action="{{ route('event-permits.store') }}" method="POST" novalidate>
    @csrf
    <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">

    <div class="mobile-wizard-header" aria-label="Langkah pengajuan izin event">
      <div class="wizard-stepper">
        @foreach([1 => 'Pemohon', 2 => 'Detail', 3 => 'Kebutuhan', 4 => 'Konfirmasi'] as $step => $label)
          @if($step > 1)<div class="wizard-line" data-line="{{ $step - 1 }}"></div>@endif
          <button type="button" class="wizard-step-indicator {{ $step === 1 ? 'active' : '' }}" data-step="{{ $step }}" aria-label="Langkah {{ $step }}: {{ $label }}">
            <span class="wizard-circle"><span class="step-num">{{ $step }}</span><svg class="step-check"><use href="#i-check"/></svg></span>
            <span class="wizard-step-label">{{ $label }}</span>
          </button>
        @endforeach
      </div>
    </div>

    <div class="permit-form-grid event-form-grid">
      <section class="permit-section wizard-step active" data-wizard-step="1">
        <div class="permit-section-head"><div class="permit-section-icon blue"><svg><use href="#i-user"/></svg></div><div><h2>Jenis Surat &amp; Pemohon</h2><p>Pilih jenis kegiatan dan lengkapi kontak penanggung jawab.</p></div></div>
        <div class="pf"><label>Jenis Surat Event <span class="req">*</span></label><div class="event-type-options" role="radiogroup" aria-label="Jenis surat event">
          <label class="event-type-option"><input type="radio" name="event_type" value="organizer" @checked(old('event_type') === 'organizer') required><span><strong>Penyelenggara Event / EO</strong><small>Pukul 07.00 - 22.00 WITA.</small></span></label>
          <label class="event-type-option"><input type="radio" name="event_type" value="tenant" @checked(old('event_type') === 'tenant') required><span><strong>Event Tenant</strong><small>Pukul 10.00 - 22.00 WITA.</small></span></label>
        </div>@error('event_type')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf"><label for="organizer_name">Nama EO / Tenant <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-building"/></svg><input id="organizer_name" name="organizer_name" value="{{ old('organizer_name', $user?->tenant_name) }}" minlength="2" maxlength="150" required></div>@error('organizer_name')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf"><label for="applicant_name">PIC / Penanggung Jawab <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-user"/></svg><input id="applicant_name" name="applicant_name" value="{{ old('applicant_name', $user?->name) }}" minlength="2" maxlength="120" autocomplete="name" required></div>@error('applicant_name')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf-row">
          <div class="pf"><label for="applicant_phone">Nomor WhatsApp <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-phone"/></svg><input id="applicant_phone" name="applicant_phone" type="tel" inputmode="tel" value="{{ old('applicant_phone', $user?->phone) }}" maxlength="25" autocomplete="tel" required></div>@error('applicant_phone')<span class="pf-error">{{ $message }}</span>@enderror</div>
          <div class="pf"><label for="applicant_email">Email <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-mail"/></svg><input id="applicant_email" name="applicant_email" type="email" value="{{ old('applicant_email', $user?->email) }}" maxlength="120" autocomplete="email" required></div>@error('applicant_email')<span class="pf-error">{{ $message }}</span>@enderror</div>
        </div>
      </section>

      <section class="permit-section wizard-step" data-wizard-step="2">
        <div class="permit-section-head"><div class="permit-section-icon rose"><svg><use href="#i-location"/></svg></div><div><h2>Rincian Event</h2><p>Lokasi, kategori, jenis, dan masa berlaku kegiatan.</p></div></div>
        <div class="pf"><label for="work_location">Lokasi Pekerjaan / Event <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-location"/></svg><input id="work_location" name="work_location" value="{{ old('work_location') }}" minlength="2" maxlength="180" required></div>@error('work_location')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf"><label for="work_category">Kategori Pekerjaan <span class="req">*</span></label><div class="pf-wrap pf-select-wrap"><svg class="pf-icon"><use href="#i-calendar"/></svg><select id="work_category" name="work_category" required><option value="">Pilih kategori</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(old('work_category') === $value)>{{ $label }}</option>@endforeach</select><svg class="pf-select-arrow"><use href="#i-chevron-right"/></svg></div>@error('work_category')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf"><label for="work_type">Jenis Pekerjaan <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-wrench"/></svg><input id="work_type" name="work_type" value="{{ old('work_type') }}" minlength="3" maxlength="180" placeholder="Contoh: pemasangan panggung dan dekorasi" required></div>@error('work_type')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf-row">
          <div class="pf"><label for="start_date">Berlaku Mulai <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-calendar"/></svg><input id="start_date" name="start_date" type="date" value="{{ old('start_date') }}" min="{{ now()->toDateString() }}" required></div>@error('start_date')<span class="pf-error">{{ $message }}</span>@enderror</div>
          <div class="pf"><label for="end_date">Berlaku Sampai <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-calendar"/></svg><input id="end_date" name="end_date" type="date" value="{{ old('end_date') }}" min="{{ now()->toDateString() }}" required></div><span class="pf-hint">Maksimal 7 hari kalender termasuk tanggal mulai.</span>@error('end_date')<span class="pf-error">{{ $message }}</span>@enderror</div>
        </div>
      </section>
    </div>

    <section class="permit-section permit-section--full wizard-step" data-wizard-step="3">
      <div class="permit-section-head"><div class="permit-section-icon blue"><svg><use href="#i-grid"/></svg></div><div><h2>Kebutuhan Event</h2><p>Opsional. Pilih fasilitas dan akses yang perlu disiapkan.</p></div></div>
      <div class="event-requirement-groups">
        @foreach($requirements as $group => $items)
          <fieldset class="event-requirement-group">
            <legend>{{ $group }}</legend>
            <div class="work-extra-options">
              @foreach($items as $item)
                @php($isOtherRequirement = $group === 'Fasilitas' && $item->name === 'Lainnya')
                <label><input type="checkbox" name="requirement_ids[]" value="{{ $item->id }}" @checked(in_array($item->id, old('requirement_ids', []))) @if($isOtherRequirement) id="eventOtherRequirementToggle" @endif><span>{{ $item->name }}</span></label>
              @endforeach
            </div>
            @if($group === 'Fasilitas' && $items->contains(fn ($item) => $item->name === 'Lainnya'))
              <div class="pf event-other-requirement" id="eventOtherRequirementField" hidden>
                <label for="requirement_other">Fasilitas lainnya <span class="req">*</span></label>
                <div class="pf-wrap"><svg class="pf-icon"><use href="#i-edit"/></svg><input id="requirement_other" name="requirement_other" value="{{ old('requirement_other') }}" minlength="2" maxlength="160" placeholder="Contoh: tangga atau meja tambahan" disabled></div>
                @error('requirement_other')<span class="pf-error">{{ $message }}</span>@enderror
              </div>
            @endif
          </fieldset>
        @endforeach
      </div>
      @error('requirement_ids.*')<span class="pf-error">{{ $message }}</span>@enderror

      <label class="event-loading-option"><input type="hidden" name="includes_loading_material" value="0"><input id="includes_loading_material" type="checkbox" name="includes_loading_material" value="1" @checked(old('includes_loading_material'))><span><strong>Termasuk loading / unloading</strong><small>Jika dipilih, sistem akan menerbitkan surat tambahan loading / unloading dengan estimasi waktu pukul 22.00 - 09.00 WITA.</small></span></label>

      <div class="pf"><label for="notes">Rincian Pekerjaan <span class="req">*</span></label><textarea id="notes" name="notes" rows="5" minlength="10" maxlength="3000" placeholder="Jelaskan susunan kegiatan, pekerjaan yang dilakukan, kebutuhan area, dan informasi penting lainnya." required>{{ old('notes') }}</textarea>@error('notes')<span class="pf-error">{{ $message }}</span>@enderror</div>
    </section>

    <section class="permit-section permit-section--full wizard-step" data-wizard-step="4">
      <div class="permit-section-head"><div class="permit-section-icon amber"><svg><use href="#i-shield-check"/></svg></div><div><h2>Konfirmasi &amp; Ketentuan</h2><p>Periksa kembali data sebelum permohonan diajukan.</p></div></div>
      <div class="work-review-grid event-review-grid">
        <dl class="review-dl"><dt>Jenis surat</dt><dd id="eventReviewType">-</dd><dt>EO / Tenant</dt><dd data-event-review="organizer_name">-</dd><dt>Penanggung jawab</dt><dd data-event-review="applicant_name">-</dd><dt>Kontak</dt><dd id="eventReviewContact">-</dd></dl>
        <dl class="review-dl"><dt>Lokasi</dt><dd data-event-review="work_location">-</dd><dt>Kategori</dt><dd id="eventReviewCategory">-</dd><dt>Jenis pekerjaan</dt><dd data-event-review="work_type">-</dd><dt>Periode</dt><dd id="eventReviewPeriod">-</dd><dt>Kebutuhan</dt><dd id="eventReviewRequirements">-</dd></dl>
      </div>
      <div class="work-rules"><ol>@foreach(config('event-permits.notes') as $note)<li>{{ $note }}</li>@endforeach</ol></div>
      <label class="work-consent"><input type="checkbox" name="terms_accepted" value="1" @checked(old('terms_accepted')) required><span>Saya telah membaca, memahami, dan menyetujui seluruh ketentuan event di atas.</span></label>
      @error('terms_accepted')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
    </section>

    <div class="permit-submit-row"><p class="permit-submit-note">Permohonan akan dikirim kepada Divisi Event Promotion untuk diperiksa.</p><div class="permit-submit-actions"><a href="{{ route('portal.dashboard') }}" class="btn-secondary">Batal</a><button type="submit" class="btn-primary" id="eventSubmit"><span class="btn-label" data-idle-label="Ajukan Surat Izin Event">Ajukan Surat Izin Event</span><span class="btn-spinner" aria-hidden="true"></span></button></div></div>

    <div class="mobile-wizard-nav" id="mobileWizardNav">
      <button type="button" class="btn-wizard-prev" id="eventWizardPrev" hidden><svg><use href="#i-chevron-left"/></svg><span>Sebelumnya</span></button>
      <button type="button" class="btn-wizard-next" id="eventWizardNext"><span>Lanjut</span><svg><use href="#i-chevron-right"/></svg></button>
      <button type="submit" class="btn-wizard-submit" id="eventWizardSubmit" hidden><svg><use href="#i-check"/></svg><span class="btn-label" data-idle-label="Ajukan">Ajukan</span><span class="btn-spinner" aria-hidden="true"></span></button>
    </div>
  </form>
</div>

<div class="permit-submit-progress" id="eventSubmitProgress" role="status" aria-live="polite" hidden><div class="permit-submit-progress-panel"><div class="permit-submit-progress-spinner"></div><strong>Mengirim permohonan</strong><p>Jangan tutup halaman sampai proses selesai.</p></div></div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
  const form = document.getElementById('eventPermitForm');
  const start = document.getElementById('start_date');
  const end = document.getElementById('end_date');
  const submit = document.getElementById('eventSubmit');
  const mobileSubmit = document.getElementById('eventWizardSubmit');
  const previous = document.getElementById('eventWizardPrev');
  const next = document.getElementById('eventWizardNext');
  const progress = document.getElementById('eventSubmitProgress');
  const otherRequirementToggle = document.getElementById('eventOtherRequirementToggle');
  const otherRequirementField = document.getElementById('eventOtherRequirementField');
  const otherRequirementInput = document.getElementById('requirement_other');
  const totalSteps = 4;
  let currentStep = 1;

  const localDate = date => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const syncDates = () => {
    if (!start.value) return;
    const first = new Date(start.value + 'T00:00:00');
    const last = new Date(first);
    last.setDate(last.getDate() + 6);
    end.min = start.value;
    end.max = localDate(last);
    if (end.value && (end.value < end.min || end.value > end.max)) end.value = '';
  };

  const sectionForStep = step => form.querySelector(`[data-wizard-step="${step}"]`);

  const fieldName = input => ({
    organizer_name: 'Nama EO atau tenant',
    applicant_name: 'PIC atau penanggung jawab',
    applicant_phone: 'Nomor WhatsApp',
    applicant_email: 'Email',
    work_location: 'Lokasi event',
    work_category: 'Kategori pekerjaan',
    work_type: 'Jenis pekerjaan',
    start_date: 'Tanggal mulai',
    end_date: 'Tanggal selesai',
    notes: 'Rincian pekerjaan',
    requirement_other: 'Fasilitas lainnya',
  }[input.name] || 'Field ini');

  const syncOtherRequirement = () => {
    if (!otherRequirementToggle || !otherRequirementField || !otherRequirementInput) return;
    const visible = otherRequirementToggle.checked;
    otherRequirementField.hidden = !visible;
    otherRequirementInput.disabled = !visible;
    otherRequirementInput.required = visible;
    if (!visible) setFieldError(otherRequirementInput, '', false);
  };

  const normalizePhone = value => {
    let digits = value.replace(/\D+/g, '');
    if (digits.startsWith('0')) digits = `62${digits.slice(1)}`;
    else if (digits.startsWith('8')) digits = `62${digits}`;
    return digits;
  };

  const inputMessage = input => {
    input.setCustomValidity('');
    const value = input.value.trim();

    if (input.name === 'terms_accepted') {
      return input.checked ? '' : 'Anda harus menyetujui ketentuan Event sebelum mengajukan.';
    }
    if (input.required && !value) return `${fieldName(input)} wajib diisi.`;
    if (input.name === 'applicant_phone' && !/^62[1-9][0-9]{7,12}$/.test(normalizePhone(value))) {
      return 'Masukkan nomor WhatsApp Indonesia yang valid, misalnya 081234567890.';
    }
    if (input.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      return 'Masukkan alamat email yang valid.';
    }
    if (input === start && value && input.min && value < input.min) {
      return 'Tanggal mulai tidak boleh sebelum hari ini.';
    }
    if (input === end && value && start.value) {
      if (value < start.value) return 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
      const duration = Math.round((new Date(`${value}T00:00:00`) - new Date(`${start.value}T00:00:00`)) / 86400000);
      if (duration > 6) return 'Masa berlaku maksimal 7 hari kalender termasuk tanggal mulai.';
    }
    if (input.minLength > 0 && value.length < input.minLength) {
      return `${fieldName(input)} minimal ${input.minLength} karakter.`;
    }
    if (input.maxLength > 0 && value.length > input.maxLength) {
      return `${fieldName(input)} maksimal ${input.maxLength.toLocaleString('id-ID')} karakter.`;
    }
    return '';
  };

  const errorHost = input => input.name === 'terms_accepted'
    ? input.closest('.work-consent')
    : input.closest('.pf') || input.parentElement;

  const setFieldError = (input, message, show = true) => {
    input.setCustomValidity(message);
    input.classList.toggle('is-invalid', Boolean(message) && show);
    input.setAttribute('aria-invalid', String(Boolean(message) && show));
    const host = errorHost(input);
    host?.classList.toggle('is-invalid', Boolean(message) && show);
    let error = host?.querySelector(':scope > .pf-client-error');
    if (message && show) {
      if (!error) {
        error = document.createElement('span');
        error.className = 'pf-error pf-client-error';
        error.setAttribute('role', 'alert');
        host?.appendChild(error);
      }
      error.textContent = message;
    } else {
      error?.remove();
    }
    return !message;
  };

  const validateEventType = (show = true) => {
    const radios = [...form.querySelectorAll('[name="event_type"]')];
    const options = form.querySelector('.event-type-options');
    const valid = radios.some(radio => radio.checked);
    const message = valid ? '' : 'Pilih jenis Surat Izin Event.';
    radios.forEach(radio => {
      radio.setCustomValidity(message);
      radio.setAttribute('aria-invalid', String(!valid && show));
    });
    options.classList.toggle('is-invalid', !valid && show);
    const host = options.closest('.pf');
    let error = host.querySelector(':scope > .pf-client-error');
    if (!valid && show) {
      if (!error) {
        error = document.createElement('span');
        error.className = 'pf-error pf-client-error';
        error.setAttribute('role', 'alert');
        host.appendChild(error);
      }
      error.textContent = message;
    } else {
      error?.remove();
    }
    return valid;
  };

  const validateInput = (input, show = true) => {
    if (input.name === 'event_type') return validateEventType(show);
    return setFieldError(input, inputMessage(input), show);
  };

  const firstInvalidInStep = step => {
    const section = sectionForStep(step);
    return [...section.querySelectorAll('input, select, textarea')]
      .find(field => field.type !== 'hidden' && !field.checkValidity());
  };

  const stepIsValid = (step, showMessage = false) => {
    const section = sectionForStep(step);
    const fields = [...section.querySelectorAll('input, select, textarea')]
      .filter(field => field.type !== 'hidden');
    const valid = fields.map(field => validateInput(field, showMessage)).every(Boolean);
    const invalid = firstInvalidInStep(step);
    if (valid && !invalid) return true;
    if (showMessage) {
      invalid?.focus({ preventScroll: true });
      invalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return false;
  };

  const populateReview = () => {
    form.querySelectorAll('[data-event-review]').forEach(output => {
      output.textContent = document.getElementById(output.dataset.eventReview)?.value.trim() || '-';
    });

    const eventType = form.querySelector('[name="event_type"]:checked');
    document.getElementById('eventReviewType').textContent = eventType?.value === 'organizer'
      ? 'Penyelenggara Event / EO, 07.00 - 22.00 WITA'
      : eventType?.value === 'tenant' ? 'Event Tenant, 10.00 - 22.00 WITA' : '-';

    const phone = document.getElementById('applicant_phone').value.trim();
    const email = document.getElementById('applicant_email').value.trim();
    document.getElementById('eventReviewContact').textContent = [phone, email].filter(Boolean).join(' | ') || '-';

    const category = document.getElementById('work_category');
    document.getElementById('eventReviewCategory').textContent = category.value
      ? category.selectedOptions[0]?.textContent || '-'
      : '-';
    document.getElementById('eventReviewPeriod').textContent = start.value && end.value
      ? `${start.value} s.d. ${end.value}`
      : '-';

    const requirements = [...form.querySelectorAll('[name="requirement_ids[]"]:checked')]
      .map(input => input === otherRequirementToggle
        ? `Lainnya: ${otherRequirementInput?.value.trim() || 'belum dijelaskan'}`
        : input.closest('label')?.querySelector('span')?.textContent.trim())
      .filter(Boolean);
    if (document.getElementById('includes_loading_material').checked) requirements.push('Loading / unloading tambahan');
    document.getElementById('eventReviewRequirements').textContent = requirements.join(', ') || 'Tidak ada';
  };

  const setStep = step => {
    currentStep = Math.max(1, Math.min(totalSteps, step));
    form.querySelectorAll('.wizard-step').forEach(section => {
      section.classList.toggle('active', Number(section.dataset.wizardStep) === currentStep);
    });
    form.querySelectorAll('.wizard-step-indicator').forEach(indicator => {
      const number = Number(indicator.dataset.step);
      indicator.classList.toggle('active', number === currentStep);
      indicator.classList.toggle('completed', number < currentStep);
      indicator.setAttribute('aria-current', number === currentStep ? 'step' : 'false');
    });
    form.querySelectorAll('.wizard-line').forEach(line => {
      line.classList.toggle('completed', Number(line.dataset.line) < currentStep);
    });
    previous.hidden = currentStep === 1;
    next.hidden = currentStep === totalSteps;
    mobileSubmit.hidden = currentStep !== totalSteps;
    if (currentStep === totalSteps) populateReview();
    if (window.innerWidth < 860) {
      form.querySelector('.mobile-wizard-header')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  start.addEventListener('change', () => {
    syncDates();
    validateInput(start, true);
    if (end.value) validateInput(end, true);
  });
  otherRequirementToggle?.addEventListener('change', () => {
    syncOtherRequirement();
    if (otherRequirementToggle.checked) otherRequirementInput?.focus();
  });
  form.addEventListener('focusout', event => {
    if (!event.target.matches('input, select, textarea') || event.target.type === 'hidden') return;
    event.target.dataset.touched = 'true';
    validateInput(event.target, true);
  });
  form.addEventListener('input', event => {
    if (event.target.matches('input, select, textarea') && event.target.type !== 'hidden' && event.target.dataset.touched === 'true') {
      validateInput(event.target, true);
    }
    populateReview();
  });
  form.addEventListener('change', event => {
    if (event.target.matches('input, select, textarea') && event.target.type !== 'hidden') {
      event.target.dataset.touched = 'true';
      validateInput(event.target, true);
    }
    populateReview();
  });
  previous.addEventListener('click', () => setStep(currentStep - 1));
  next.addEventListener('click', () => {
    if (stepIsValid(currentStep, true)) setStep(currentStep + 1);
  });
  form.querySelectorAll('.wizard-step-indicator').forEach(indicator => {
    indicator.addEventListener('click', () => {
      const target = Number(indicator.dataset.step);
      if (target < currentStep || (target === currentStep + 1 && stepIsValid(currentStep, true))) setStep(target);
    });
  });

  syncDates();
  syncOtherRequirement();
  [...form.querySelectorAll('input, select, textarea')]
    .filter(field => field.type !== 'hidden')
    .forEach(field => validateInput(field, false));
  populateReview();
  const errorStep = form.querySelector('.wizard-step .pf-error')?.closest('.wizard-step');
  setStep(errorStep ? Number(errorStep.dataset.wizardStep) : 1);

  form.addEventListener('submit', event => {
    const invalidStep = [...Array(totalSteps)].map((_, index) => index + 1).find(step => !stepIsValid(step));
    if (invalidStep) {
      event.preventDefault();
      setStep(invalidStep);
      requestAnimationFrame(() => stepIsValid(invalidStep, true));
      return;
    }
    submit.disabled = true;
    submit.classList.add('loading');
    submit.querySelector('.btn-label').textContent = 'Mengajukan...';
    mobileSubmit.disabled = true;
    mobileSubmit.classList.add('loading');
    mobileSubmit.querySelector('.btn-label').textContent = 'Mengajukan...';
    progress.hidden = false;
    document.body.classList.add('permit-submit-pending');
  });
})();
</script>
@endpush
