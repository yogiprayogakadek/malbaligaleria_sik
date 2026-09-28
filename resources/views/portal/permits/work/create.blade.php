@extends('layouts.portal')

@section('title', 'Permohonan Surat Izin Kerja : Mal Bali Galeria')
@section('page-title', 'Surat Izin Kerja')

@section('content')
<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><a href="{{ route('portal.dashboard') }}">Beranda</a><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Surat Izin Kerja</span></div>
      <h1 class="page-title">Permohonan Surat Izin Kerja</h1>
      <p class="page-subtitle">Izin pekerjaan tenant atau kontraktor di area toko, roof, basement, dan parkir Mal Bali Galeria.</p>
    </div>
  </div>

  @if($errors->any())
    <div class="permit-alert permit-alert--error" role="alert">
      <svg><use href="#i-info"/></svg>
      <div><strong>Periksa kembali formulir:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </div>
  @endif

  <form id="workPermitForm" action="{{ route('work-permits.store') }}" method="POST" enctype="multipart/form-data" novalidate>
    @csrf

    <div class="mobile-wizard-header" aria-label="Langkah pengajuan izin kerja">
      <div class="wizard-stepper">
        @foreach([1 => 'Pemohon', 2 => 'Pekerja', 3 => 'Pekerjaan', 4 => 'Ringkasan'] as $step => $label)
          @if($step > 1)<div class="wizard-line" data-line="{{ $step - 1 }}"></div>@endif
          <div class="wizard-step-indicator {{ $step === 1 ? 'active' : '' }}" data-step="{{ $step }}">
            <div class="wizard-circle"><span class="step-num">{{ $step }}</span><svg class="step-check"><use href="#i-check"/></svg></div>
            <span class="wizard-step-label">{{ $label }}</span>
          </div>
        @endforeach
      </div>
    </div>

    <div class="permit-form-grid">
      <section class="permit-section wizard-step active" data-wizard-step="1">
        <div class="permit-section-head">
          <div class="permit-section-icon blue"><svg><use href="#i-building"/></svg></div>
          <div><h2>Kontraktor &amp; Penanggung Jawab</h2><p>Kontak utama yang bertanggung jawab atas pekerjaan.</p></div>
        </div>

        <div class="pf" id="pf-contractor_name">
          <label for="contractor_name">Nama Kontraktor / Tenant <span class="req">*</span></label>
          <div class="pf-wrap"><svg class="pf-icon"><use href="#i-building"/></svg><input id="contractor_name" name="contractor_name" value="{{ old('contractor_name', $user?->tenant_name) }}" minlength="2" maxlength="150" autocomplete="organization" placeholder="Nama tenant, vendor, atau kontraktor" required></div>
          @error('contractor_name')<span class="pf-error">{{ $message }}</span>@enderror
        </div>

        <div class="pf" id="pf-applicant_name">
          <label for="applicant_name">Nama Penanggung Jawab <span class="req">*</span></label>
          <div class="pf-wrap"><svg class="pf-icon"><use href="#i-user"/></svg><input id="applicant_name" name="applicant_name" value="{{ old('applicant_name', $user?->name) }}" minlength="2" maxlength="120" autocomplete="name" placeholder="Nama lengkap penanggung jawab" required></div>
          @error('applicant_name')<span class="pf-error">{{ $message }}</span>@enderror
        </div>

        <div class="pf-row">
          <div class="pf" id="pf-applicant_phone">
            <label for="applicant_phone">Nomor WhatsApp <span class="req">*</span></label>
            <div class="pf-wrap"><svg class="pf-icon"><use href="#i-phone"/></svg><input id="applicant_phone" name="applicant_phone" type="tel" inputmode="tel" value="{{ old('applicant_phone', $user?->phone) }}" maxlength="25" autocomplete="tel" placeholder="081234567890" required></div>
            @error('applicant_phone')<span class="pf-error">{{ $message }}</span>@enderror
          </div>
          <div class="pf" id="pf-applicant_email">
            <label for="applicant_email">Email <span class="pf-optional">Opsional</span></label>
            <div class="pf-wrap"><svg class="pf-icon"><use href="#i-mail"/></svg><input id="applicant_email" name="applicant_email" type="email" value="{{ old('applicant_email', $user?->email) }}" maxlength="120" autocomplete="email" placeholder="nama@email.com"></div>
            @error('applicant_email')<span class="pf-error">{{ $message }}</span>@enderror
          </div>
        </div>
      </section>

      <section class="permit-section wizard-step" data-wizard-step="2">
        <div class="permit-section-head">
          <div class="permit-section-icon blue"><svg><use href="#i-user"/></svg></div>
          <div><h2>Daftar Pekerja</h2><p>Nama wajib diisi. Nomor ID boleh dikosongkan.</p></div>
        </div>

        @php $workers = old('workers', [['name' => '', 'identity_number' => '']]); @endphp
        <div class="work-worker-list" id="workerList">
          <div class="work-worker-head" aria-hidden="true"><span>No.</span><span>Nama Pekerja</span><span>Nomor ID (Opsional)</span><span></span></div>
          @foreach($workers as $index => $worker)
            <div class="work-worker-row" data-worker-row>
              <span class="work-worker-number">{{ $loop->iteration }}</span>
              <label><span class="sr-only">Nama pekerja {{ $loop->iteration }}</span><input name="workers[{{ $index }}][name]" value="{{ $worker['name'] ?? '' }}" minlength="2" maxlength="120" placeholder="Nama lengkap" required></label>
              <label><span class="sr-only">Nomor ID pekerja {{ $loop->iteration }}</span><input name="workers[{{ $index }}][identity_number]" value="{{ $worker['identity_number'] ?? '' }}" maxlength="50" placeholder="KTP/SIM/ID perusahaan"></label>
              <button type="button" class="work-worker-remove" data-remove-worker aria-label="Hapus pekerja" @disabled(count($workers) === 1)><svg><use href="#i-trash"/></svg></button>
            </div>
          @endforeach
        </div>
        @error('workers')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
        @error('workers.*.name')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
        @error('workers.*.identity_number')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
        <button type="button" class="btn-secondary btn-sm work-add-worker" id="addWorker"><svg><use href="#i-plus"/></svg>Tambah pekerja</button>
        <p class="pf-hint">Maksimal 50 pekerja. Setiap pekerja wajib membawa tanda pengenal saat bekerja.</p>
      </section>
    </div>

    <section class="permit-section permit-section--full wizard-step" data-wizard-step="3">
      <div class="permit-section-head">
        <div class="permit-section-icon rose"><svg><use href="#i-wrench"/></svg></div>
        <div><h2>Rincian Pekerjaan &amp; Dokumen</h2><p>Jadwal, lokasi, kebutuhan tambahan, dan identitas penanggung jawab.</p></div>
      </div>

      <div class="pf-row">
        <div class="pf"><label for="work_location">Lokasi Pekerjaan <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-building"/></svg><input id="work_location" name="work_location" value="{{ old('work_location') }}" minlength="2" maxlength="180" placeholder="Contoh: Unit GF-12 / Roof / Basement" required></div>@error('work_location')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf"><label for="work_category">Kategori Pekerjaan <span class="req">*</span></label><div class="pf-wrap pf-select-wrap"><svg class="pf-icon"><use href="#i-wrench"/></svg><select id="work_category" name="work_category" required><option value="">Pilih kategori</option>@foreach($workCategories as $value => $label)<option value="{{ $value }}" @selected(old('work_category') === $value)>{{ $label }}</option>@endforeach</select><svg class="pf-select-arrow" aria-hidden="true"><use href="#i-chevron-right"/></svg></div><span class="pf-hint" id="workCategoryHint">Kategori menentukan divisi pemeriksa secara otomatis.</span>@error('work_category')<span class="pf-error">{{ $message }}</span>@enderror</div>
      </div>

      <div class="pf"><label for="work_type">Uraian Pekerjaan <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-wrench"/></svg><input id="work_type" name="work_type" value="{{ old('work_type') }}" minlength="3" maxlength="180" placeholder="Jelaskan pekerjaan yang akan dilakukan" required></div>@error('work_type')<span class="pf-error">{{ $message }}</span>@enderror</div>

      @php $selectedSchedules = old('work_schedules', []); @endphp
      <div class="pf" id="pf-work_schedules"><label>Waktu Kerja <span class="req">*</span> <span class="pf-optional">Boleh pilih keduanya</span></label><div class="pf-radio-group work-schedule-options" data-schedule-group>
        <label class="pf-radio {{ in_array('inside_store', $selectedSchedules, true) ? 'selected' : '' }}"><input type="checkbox" name="work_schedules[]" value="inside_store" @checked(in_array('inside_store', $selectedSchedules, true))><svg><use href="#i-clock"/></svg><span>Dalam toko<br><small>22.00 - 11.00 WITA</small></span></label>
        <label class="pf-radio {{ in_array('outside_store', $selectedSchedules, true) ? 'selected' : '' }}"><input type="checkbox" name="work_schedules[]" value="outside_store" @checked(in_array('outside_store', $selectedSchedules, true))><svg><use href="#i-clock"/></svg><span>Di luar toko<br><small>08.00 - 16.00 WITA</small></span></label>
      </div>@error('work_schedules')<span class="pf-error">{{ $message }}</span>@enderror @error('work_schedules.*')<span class="pf-error">{{ $message }}</span>@enderror</div>

      <div class="pf-row">
        <div class="pf"><label for="start_date">Berlaku Mulai <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-calendar"/></svg><input id="start_date" name="start_date" type="date" value="{{ old('start_date') }}" min="{{ now()->toDateString() }}" required></div>@error('start_date')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf"><label for="end_date">Berlaku Sampai <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-calendar"/></svg><input id="end_date" name="end_date" type="date" value="{{ old('end_date') }}" min="{{ now()->toDateString() }}" required></div><span class="pf-hint">Maksimal 7 hari kalender termasuk tanggal mulai.</span>@error('end_date')<span class="pf-error">{{ $message }}</span>@enderror</div>
      </div>

      <div class="pf"><label>Kebutuhan Tambahan</label><div class="work-extra-options">
        <label><input type="hidden" name="needs_water" value="0"><input type="checkbox" name="needs_water" value="1" @checked(old('needs_water'))><span>Air</span></label>
      </div><p class="pf-hint">Untuk pekerjaan yang diperiksa MEP, kebutuhan security deposit dan nominalnya ditentukan setelah pemeriksaan.</p></div>

      <div class="pf"><label for="notes">Catatan <span class="pf-optional">Opsional</span></label><textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="Tuliskan kebutuhan teknis atau informasi khusus untuk pengelola gedung">{{ old('notes') }}</textarea>@error('notes')<span class="pf-error">{{ $message }}</span>@enderror</div>

      <div class="work-document-section">
        <div class="pf"><label>Dokumen Penanggung Jawab <span class="req">*</span></label><div class="pf-radio-group work-document-types">
          @foreach(['ktp' => 'KTP', 'sim' => 'SIM'] as $value => $label)
            <label class="pf-radio {{ old('id_doc_type', 'ktp') === $value ? 'selected' : '' }}"><input type="radio" name="id_doc_type" value="{{ $value }}" @checked(old('id_doc_type', 'ktp') === $value) required><svg><use href="#i-file"/></svg><span>{{ $label }}</span></label>
          @endforeach
        </div></div>
        <label class="id-upload-area work-id-upload" id="workIdUpload">
          <input id="id_doc" name="id_doc" type="file" class="id-upload-input" accept="image/jpeg,image/png" required>
          <span class="id-upload-prompt" id="workUploadPrompt"><span class="id-upload-icon"><svg><use href="#i-file"/></svg></span><span class="id-upload-text"><strong>Unggah foto KTP / SIM</strong><span>JPG atau PNG, maksimal 4 MB</span></span></span>
          <span class="work-upload-file" id="workUploadFile" hidden><svg><use href="#i-check"/></svg><span></span></span>
        </label>
        @error('id_doc')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
      </div>
    </section>

    <section class="permit-section permit-section--full wizard-step" data-wizard-step="4">
      <div class="permit-section-head"><div class="permit-section-icon amber"><svg><use href="#i-check"/></svg></div><div><h2>Ringkasan &amp; Peraturan</h2><p>Periksa data dan pahami ketentuan sebelum mengajukan.</p></div></div>

      <div class="work-review-grid">
        <dl class="review-dl"><dt>Kontraktor / Tenant</dt><dd data-review="contractor_name">-</dd><dt>Penanggung Jawab</dt><dd data-review="applicant_name">-</dd><dt>Kontak</dt><dd data-review="applicant_phone">-</dd><dt>Jumlah Pekerja</dt><dd id="reviewWorkerCount">-</dd></dl>
        <dl class="review-dl"><dt>Lokasi</dt><dd data-review="work_location">-</dd><dt>Kategori</dt><dd data-review="work_category">-</dd><dt>Uraian Pekerjaan</dt><dd data-review="work_type">-</dd><dt>Waktu Kerja</dt><dd id="reviewSchedule">-</dd><dt>Periode</dt><dd id="reviewPeriod">-</dd></dl>
      </div>

      <div class="work-rules" aria-labelledby="workRulesTitle">
        <h3 id="workRulesTitle"><svg><use href="#i-shield-check"/></svg>Peraturan Pekerjaan</h3>
        <ol>
          <li>Setiap pekerja wajib membawa KTP, SIM, atau tanda pengenal resmi selama bekerja.</li>
          <li>Kerusakan, kehilangan, atau kecelakaan akibat pekerjaan menjadi tanggung jawab tenant atau kontraktor.</li>
          <li>Dilarang merokok, berjudi, minum minuman beralkohol, dan menggunakan obat terlarang di area Mal Bali Galeria.</li>
          <li>Pekerjaan di dalam toko yang mengganggu operasional hanya boleh dilakukan pukul 22.00 - 09.00 WITA atau setelah toko tutup.</li>
          <li>Pekerjaan yang menimbulkan bau, debu, asap, atau percikan api wajib menggunakan penghisap dan partisi pelindung.</li>
          <li>Pekerjaan di roof atau basement dilakukan pukul 08.00 - 16.00 WITA. Hari Minggu tidak diperkenankan, kecuali penanganan gangguan darurat.</li>
          <li>Setelah pekerjaan selesai, seluruh pekerja wajib melapor dan menunjukkan tanda pengenal kepada petugas keamanan.</li>
          <li>Area kerja harus dijaga tetap bersih dan aman serta memenuhi ketentuan keselamatan kerja dan proteksi kebakaran.</li>
        </ol>
      </div>

      <label class="work-consent"><input type="checkbox" name="consent" value="1" required><span>Saya telah membaca, memahami, dan menyetujui seluruh peraturan pekerjaan di atas.</span></label>
      @error('consent')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
    </section>

    <div class="permit-submit-row">
      <p class="permit-submit-note">Data dan dokumen akan dikirim kepada pengelola gedung untuk diverifikasi.</p>
      <div class="permit-submit-actions"><a href="{{ route('portal.dashboard') }}" class="btn-secondary">Batal</a><button type="submit" class="btn-primary" id="workSubmit"><span class="btn-label">Ajukan Izin Kerja</span></button></div>
    </div>

    <div class="mobile-wizard-nav" id="mobileWizardNav">
      <button type="button" class="btn-wizard-prev" id="wizardPrevBtn" hidden><svg><use href="#i-chevron-left"/></svg><span>Sebelumnya</span></button>
      <button type="button" class="btn-wizard-next" id="wizardNextBtn"><span>Lanjut</span><svg><use href="#i-chevron-right"/></svg></button>
      <button type="submit" class="btn-wizard-submit" id="wizardSubmitBtn" hidden><svg><use href="#i-check"/></svg><span>Ajukan</span></button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const form = document.getElementById('workPermitForm');
  const workerList = document.getElementById('workerList');
  const addWorker = document.getElementById('addWorker');
  const startDate = document.getElementById('start_date');
  const endDate = document.getElementById('end_date');
  const fileInput = document.getElementById('id_doc');
  const uploadPrompt = document.getElementById('workUploadPrompt');
  const uploadFile = document.getElementById('workUploadFile');
  const workCategory = document.getElementById('work_category');
  const categoryHint = document.getElementById('workCategoryHint');
  const trCategories = new Set(['general_cleaning', 'flyer_distribution', 'stock_opname', 'pest_control']);

  const errorMessage = input => {
    const value = input.value.trim();
    if (input.type === 'checkbox' && input.required && !input.checked) return 'Bagian ini wajib disetujui.';
    if (input.required && !value) return 'Field ini wajib diisi.';
    if (input.validity.rangeUnderflow) return 'Tanggal tidak boleh sebelum batas yang ditentukan.';
    if (input.validity.rangeOverflow) return 'Tanggal melebihi batas masa berlaku 7 hari.';
    if (input.type === 'email' && value && input.validity.typeMismatch) return 'Masukkan alamat email yang valid.';
    if (input.type === 'tel' && value && !/^[\d+\-\s()]{9,25}$/.test(value)) return 'Masukkan nomor WhatsApp yang valid.';
    if (input.name.endsWith('[identity_number]') && value && !/^[A-Za-z0-9.\/\-\s]+$/.test(value)) return 'Nomor ID memuat karakter yang tidak diizinkan.';
    if (input.minLength > 0 && value && value.length < input.minLength) return `Minimal ${input.minLength} karakter.`;
    if (input.maxLength > 0 && value.length > input.maxLength) return `Maksimal ${input.maxLength} karakter.`;
    return '';
  };

  const errorHost = input => input.closest('.pf') || input.closest('label') || input.parentElement;

  const setFieldError = (input, message, show = true) => {
    input.setCustomValidity(message);
    input.classList.toggle('is-invalid', Boolean(message) && show);
    input.setAttribute('aria-invalid', String(Boolean(message) && show));
    const host = errorHost(input);
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

  const validateDates = (show = true) => {
    let message = '';
    if (startDate.value && endDate.value) {
      const start = new Date(`${startDate.value}T00:00:00`);
      const end = new Date(`${endDate.value}T00:00:00`);
      const days = Math.round((end - start) / 86400000);
      if (days < 0) message = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
      else if (days > 6) message = 'Masa izin maksimal 7 hari kalender termasuk tanggal mulai.';
    }
    return setFieldError(endDate, message || errorMessage(endDate), show);
  };

  const validateSchedule = (show = true) => {
    const group = document.querySelector('[data-schedule-group]');
    const valid = Boolean(group.querySelector('input:checked'));
    group.classList.toggle('is-invalid', !valid && show);
    let error = group.parentElement.querySelector(':scope > .pf-client-error');
    if (!valid && show) {
      if (!error) {
        error = document.createElement('span');
        error.className = 'pf-error pf-client-error';
        error.setAttribute('role', 'alert');
        group.after(error);
      }
      error.textContent = 'Pilih minimal satu waktu kerja.';
    } else {
      error?.remove();
    }
    return valid;
  };

  const validateFile = (show = true) => {
    const file = fileInput.files[0];
    let message = '';
    if (!file) message = 'Foto KTP atau SIM wajib diunggah.';
    else if (!['image/jpeg', 'image/png'].includes(file.type)) message = 'Gunakan file JPG atau PNG.';
    else if (file.size > 4 * 1024 * 1024) message = 'Ukuran dokumen maksimal 4 MB.';
    return setFieldError(fileInput, message, show);
  };

  const validateInput = (input, show = true) => {
    if (input === endDate) return validateDates(show);
    if (input === fileInput) return validateFile(show);
    return setFieldError(input, errorMessage(input), show);
  };

  const syncWorkers = () => {
    const rows = [...workerList.querySelectorAll('[data-worker-row]')];
    rows.forEach((row, index) => {
      row.querySelector('.work-worker-number').textContent = index + 1;
      const inputs = row.querySelectorAll('input');
      inputs[0].name = `workers[${index}][name]`;
      inputs[1].name = `workers[${index}][identity_number]`;
      row.querySelectorAll('.sr-only')[0].textContent = `Nama pekerja ${index + 1}`;
      row.querySelectorAll('.sr-only')[1].textContent = `Nomor ID pekerja ${index + 1}`;
      const remove = row.querySelector('[data-remove-worker]');
      remove.disabled = rows.length === 1;
      remove.setAttribute('aria-label', `Hapus pekerja ${index + 1}`);
    });
  };

  addWorker.addEventListener('click', () => {
    if (workerList.querySelectorAll('[data-worker-row]').length >= 50) return;
    const row = document.createElement('div');
    row.className = 'work-worker-row';
    row.dataset.workerRow = '';
    row.innerHTML = `<span class="work-worker-number"></span><label><span class="sr-only">Nama pekerja</span><input minlength="2" maxlength="120" placeholder="Nama lengkap" required></label><label><span class="sr-only">Nomor ID pekerja</span><input maxlength="50" placeholder="KTP/SIM/ID perusahaan"></label><button type="button" class="work-worker-remove" data-remove-worker><svg><use href="#i-trash"/></svg></button>`;
    workerList.appendChild(row);
    syncWorkers();
    row.querySelector('input').focus();
  });

  workerList.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-worker]');
    if (!button || workerList.querySelectorAll('[data-worker-row]').length === 1) return;
    button.closest('[data-worker-row]').remove();
    syncWorkers();
  });

  document.querySelectorAll('.pf-radio-group').forEach(group => group.addEventListener('change', event => {
    if (!event.target.matches('input[type="radio"], input[type="checkbox"]')) return;
    group.querySelectorAll('.pf-radio').forEach(label => {
      label.classList.toggle('selected', label.querySelector('input').checked);
    });
    if (group.matches('[data-schedule-group]')) validateSchedule(true);
  }));

  const syncDateRange = () => {
    endDate.min = startDate.value;
    if (!startDate.value) {
      endDate.removeAttribute('max');
      return;
    }
    const max = new Date(`${startDate.value}T00:00:00`);
    max.setDate(max.getDate() + 6);
    const year = max.getFullYear();
    const month = String(max.getMonth() + 1).padStart(2, '0');
    const day = String(max.getDate()).padStart(2, '0');
    endDate.max = `${year}-${month}-${day}`;
    if (endDate.value && endDate.value < startDate.value) endDate.value = startDate.value;
    validateDates(endDate.dataset.touched === 'true');
  };
  startDate.addEventListener('change', syncDateRange);
  endDate.addEventListener('change', () => validateDates(true));

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!validateFile(true)) {
      fileInput.value = '';
      uploadPrompt.hidden = false;
      uploadFile.hidden = true;
      return;
    }
    uploadPrompt.hidden = true;
    uploadFile.hidden = false;
    uploadFile.querySelector('span').textContent = file.name;
  });

  let currentStep = 1;
  const prev = document.getElementById('wizardPrevBtn');
  const next = document.getElementById('wizardNextBtn');
  const mobileSubmit = document.getElementById('wizardSubmitBtn');

  const stepValid = step => {
    const section = document.querySelector(`[data-wizard-step="${step}"]`);
    const fields = [...section.querySelectorAll('input, select, textarea')].filter(input => input.type !== 'hidden');
    const results = fields.map(input => validateInput(input, true));
    if (step === 3) results.push(validateSchedule(true), validateDates(true), validateFile(true));
    const firstInvalid = fields.find(input => !input.checkValidity());
    firstInvalid?.focus();
    return results.every(Boolean) && !firstInvalid;
  };

  const populateReview = () => {
    document.querySelectorAll('[data-review]').forEach(output => {
      const field = document.getElementById(output.dataset.review);
      output.textContent = field?.tagName === 'SELECT' ? field.selectedOptions[0]?.textContent || '-' : field?.value || '-';
    });
    document.getElementById('reviewWorkerCount').textContent = `${workerList.querySelectorAll('[data-worker-row]').length} orang`;
    const scheduleLabels = [...form.querySelectorAll('[name="work_schedules[]"]:checked')].map(input => input.value === 'inside_store' ? 'Dalam toko, 22.00 - 11.00 WITA' : 'Di luar toko, 08.00 - 16.00 WITA');
    document.getElementById('reviewSchedule').textContent = scheduleLabels.join('; ') || '-';
    document.getElementById('reviewPeriod').textContent = startDate.value && endDate.value ? `${startDate.value} s.d. ${endDate.value}` : '-';
  };

  const setStep = step => {
    currentStep = step;
    document.querySelectorAll('.wizard-step').forEach(section => section.classList.toggle('active', Number(section.dataset.wizardStep) === step));
    document.querySelectorAll('.wizard-step-indicator').forEach(indicator => {
      const number = Number(indicator.dataset.step);
      indicator.classList.toggle('active', number === step);
      indicator.classList.toggle('completed', number < step);
    });
    document.querySelectorAll('.wizard-line').forEach(line => line.classList.toggle('completed', Number(line.dataset.line) < step));
    prev.hidden = step === 1;
    next.hidden = step === 4;
    mobileSubmit.hidden = step !== 4;
    if (step === 4) populateReview();
    if (window.innerWidth < 860) document.querySelector('.mobile-wizard-header')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  prev.addEventListener('click', () => setStep(Math.max(1, currentStep - 1)));
  next.addEventListener('click', () => { if (stepValid(currentStep)) setStep(Math.min(4, currentStep + 1)); });
  document.querySelectorAll('.wizard-step-indicator').forEach(indicator => indicator.addEventListener('click', () => {
    const target = Number(indicator.dataset.step);
    if (target < currentStep || (target === currentStep + 1 && stepValid(currentStep))) setStep(target);
  }));

  form.addEventListener('submit', event => {
    event.preventDefault();
    for (let step = 1; step <= 4; step += 1) {
      if (!stepValid(step)) {
        setStep(step);
        window.showToast?.('Periksa kembali field yang ditandai.', 'error');
        return;
      }
    }
    document.getElementById('workSubmit').disabled = true;
    mobileSubmit.disabled = true;
    form.submit();
  });

  form.addEventListener('focusout', event => {
    if (!event.target.matches('input, select, textarea') || event.target.type === 'hidden') return;
    event.target.dataset.touched = 'true';
    validateInput(event.target, true);
  });

  form.addEventListener('input', event => {
    if (!event.target.matches('input, select, textarea') || event.target.type === 'hidden') return;
    if (event.target.dataset.touched === 'true') validateInput(event.target, true);
  });

  workerList.addEventListener('input', event => {
    if (!event.target.matches('input')) return;
    event.target.dataset.touched = 'true';
    validateInput(event.target, true);
  });

  const syncCategoryHint = () => {
    if (!workCategory.value) {
      categoryHint.textContent = 'Kategori menentukan divisi pemeriksa secara otomatis.';
      return;
    }
    categoryHint.textContent = trCategories.has(workCategory.value)
      ? 'Permohonan ini akan diperiksa oleh divisi TR.'
      : 'Permohonan ini akan diperiksa oleh divisi MEP.';
  };
  workCategory.addEventListener('change', syncCategoryHint);
  syncCategoryHint();

  if (startDate.value) syncDateRange();
  syncWorkers();
  setStep(1);
})();
</script>
@endpush
