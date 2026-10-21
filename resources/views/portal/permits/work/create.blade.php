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
    <div class="permit-draft-tools" aria-live="polite">
      <span data-draft-status data-state="{{ $draftSavedAt ? 'restored' : '' }}">{{ $draftSavedAt ? 'Draft dipulihkan. Lampiran perlu dipilih ulang.' : 'Isian akan disimpan otomatis.' }}</span>
      <button type="button" class="permit-draft-clear" data-draft-clear {{ $draftSavedAt ? '' : 'hidden' }}>Buang draft</button>
    </div>
  </div>

  @if($errors->any())
    <div class="permit-alert permit-alert--error" role="alert">
      <svg><use href="#i-info"/></svg>
      <div><strong>Periksa kembali formulir:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    </div>
  @endif

  <form id="workPermitForm" action="{{ route('work-permits.store') }}" method="POST" enctype="multipart/form-data" novalidate
        data-permit-draft data-draft-save-url="{{ route('permit-drafts.store', 'work') }}"
        data-draft-clear-url="{{ route('permit-drafts.destroy', 'work') }}">
    @csrf
    <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
    <input type="hidden" name="draft_step" id="draftStep" value="{{ old('draft_step', data_get($draft, 'draft_step', 1)) }}">

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

        <div class="pf-row">
          <div class="pf" id="pf-contractor_name">
            <label for="contractor_name">Nama Kontraktor / Vendor <span class="req">*</span></label>
            <div class="pf-wrap"><svg class="pf-icon"><use href="#i-building"/></svg><input id="contractor_name" name="contractor_name" value="{{ old('contractor_name', data_get($draft, 'contractor_name')) }}" minlength="2" maxlength="150" autocomplete="organization" placeholder="Nama kontraktor atau vendor" required></div>
            @error('contractor_name')<span class="pf-error">{{ $message }}</span>@enderror
          </div>
          <div class="pf" id="pf-tenant_name">
            <label for="tenant_name">Nama Tenant <span class="req">*</span></label>
            <div class="pf-wrap"><svg class="pf-icon"><use href="#i-building"/></svg><input id="tenant_name" name="tenant_name" value="{{ old('tenant_name', data_get($draft, 'tenant_name', $user?->tenant_name)) }}" minlength="2" maxlength="150" placeholder="Nama toko atau unit tenant" required></div>
            @error('tenant_name')<span class="pf-error">{{ $message }}</span>@enderror
          </div>
        </div>

        <div class="pf" id="pf-applicant_name">
          <label for="applicant_name">Nama Penanggung Jawab <span class="req">*</span></label>
          <div class="pf-wrap"><svg class="pf-icon"><use href="#i-user"/></svg><input id="applicant_name" name="applicant_name" value="{{ old('applicant_name', data_get($draft, 'applicant_name', $user?->name)) }}" minlength="2" maxlength="120" autocomplete="name" placeholder="Nama lengkap penanggung jawab" required></div>
          @error('applicant_name')<span class="pf-error">{{ $message }}</span>@enderror
        </div>

        <div class="pf-row">
          <div class="pf" id="pf-applicant_phone">
            <label for="applicant_phone">Nomor WhatsApp <span class="req">*</span></label>
            <div class="pf-wrap"><svg class="pf-icon"><use href="#i-phone"/></svg><input id="applicant_phone" name="applicant_phone" type="tel" inputmode="tel" value="{{ old('applicant_phone', data_get($draft, 'applicant_phone', $user?->phone)) }}" maxlength="25" autocomplete="tel" placeholder="081234567890" required></div>
            @error('applicant_phone')<span class="pf-error">{{ $message }}</span>@enderror
          </div>
          <div class="pf" id="pf-applicant_email">
            <label for="applicant_email">Email <span class="req">*</span></label>
            <div class="pf-wrap"><svg class="pf-icon"><use href="#i-mail"/></svg><input id="applicant_email" name="applicant_email" type="email" value="{{ old('applicant_email', data_get($draft, 'applicant_email', $user?->email)) }}" maxlength="120" autocomplete="email" placeholder="nama@email.com" required></div>
            @error('applicant_email')<span class="pf-error">{{ $message }}</span>@enderror
          </div>
        </div>
      </section>

      <section class="permit-section wizard-step" data-wizard-step="2">
        <div class="permit-section-head">
          <div class="permit-section-icon blue"><svg><use href="#i-user"/></svg></div>
          <div><h2>Penanggung Jawab / Daftar Kerja</h2><p>Isi minimal satu nama. Nomor identitas tetap bersifat opsional.</p></div>
        </div>

        @php $workers = old('workers', data_get($draft, 'workers', [['name' => '', 'identity_number' => '']])); @endphp
        <div class="work-worker-list" id="workerList">
          <div class="work-worker-head" aria-hidden="true"><span>No.</span><span>Nama Penanggung Jawab / Pekerja</span><span>Nomor ID (Opsional)</span><span></span></div>
          @foreach($workers as $index => $worker)
            <div class="work-worker-row" data-worker-row>
              <span class="work-worker-number">{{ $loop->iteration }}</span>
              <label><span class="sr-only">Nama pekerja {{ $loop->iteration }}</span><input name="workers[{{ $index }}][name]" value="{{ $worker['name'] ?? '' }}" minlength="2" maxlength="120" placeholder="Nama lengkap"></label>
              <label><span class="sr-only">Nomor ID pekerja {{ $loop->iteration }}</span><input name="workers[{{ $index }}][identity_number]" value="{{ $worker['identity_number'] ?? '' }}" maxlength="50" placeholder="KTP/SIM/ID perusahaan"></label>
              <button type="button" class="work-worker-remove" data-remove-worker aria-label="Hapus pekerja" @disabled(count($workers) === 1)><svg><use href="#i-trash"/></svg></button>
            </div>
          @endforeach
        </div>
        @error('workers')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
        @error('workers.*.name')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
        @error('workers.*.identity_number')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
        <button type="button" class="btn-secondary btn-sm work-add-worker" id="addWorker"><svg><use href="#i-plus"/></svg>Tambah pekerja</button>
        <p class="pf-hint">Maksimal 50 baris. Pekerja yang dicantumkan tetap wajib membawa tanda pengenal saat bekerja.</p>
      </section>
    </div>

    <section class="permit-section permit-section--full wizard-step" data-wizard-step="3">
      <div class="permit-section-head">
        <div class="permit-section-icon rose"><svg><use href="#i-wrench"/></svg></div>
        <div><h2>Rincian Pekerjaan &amp; Dokumen</h2><p>Jadwal, lokasi, kebutuhan tambahan, dan identitas penanggung jawab.</p></div>
      </div>

      @php $selectedSchedules = old('work_schedules', data_get($draft, 'work_schedules', [])); @endphp
      <div class="pf" id="pf-work_schedules"><label>Waktu Kerja <span class="req">*</span> <span class="pf-optional">Boleh pilih keduanya</span></label><div class="pf-radio-group work-schedule-options" data-schedule-group>
        <label class="pf-radio {{ in_array('inside_store', $selectedSchedules, true) ? 'selected' : '' }}"><input type="checkbox" name="work_schedules[]" value="inside_store" @checked(in_array('inside_store', $selectedSchedules, true))><svg><use href="#i-clock"/></svg><span>Dalam toko<br><small>22.00 - 10.00 WITA</small></span></label>
        <label class="pf-radio {{ in_array('outside_store', $selectedSchedules, true) ? 'selected' : '' }}"><input type="checkbox" name="work_schedules[]" value="outside_store" @checked(in_array('outside_store', $selectedSchedules, true))><svg><use href="#i-clock"/></svg><span>Di luar toko<br><small>08.00 - 16.00 WITA</small></span></label>
      </div>@error('work_schedules')<span class="pf-error">{{ $message }}</span>@enderror @error('work_schedules.*')<span class="pf-error">{{ $message }}</span>@enderror</div>

      <div class="pf-row">
        <div class="pf"><label for="work_location">Lokasi Pekerjaan <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-building"/></svg><input id="work_location" name="work_location" value="{{ old('work_location', data_get($draft, 'work_location')) }}" minlength="2" maxlength="180" placeholder="Contoh: Unit GF-12 / Roof / Basement" required></div>@error('work_location')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf"><label for="work_category">Kategori Pekerjaan <span class="req">*</span></label><div class="pf-wrap pf-select-wrap"><svg class="pf-icon"><use href="#i-wrench"/></svg><select id="work_category" name="work_category" required><option value="">Pilih kategori</option>@foreach($workCategories as $value => $label)<option value="{{ $value }}" @selected(old('work_category', data_get($draft, 'work_category')) === $value)>{{ $label }}</option>@endforeach</select><svg class="pf-select-arrow" aria-hidden="true"><use href="#i-chevron-right"/></svg></div><span class="pf-hint" id="workCategoryHint">Kategori menentukan divisi pemeriksa secara otomatis.</span>@error('work_category')<span class="pf-error">{{ $message }}</span>@enderror</div>
      </div>

      <div class="pf"><label for="work_type">Uraian Pekerjaan <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-wrench"/></svg><input id="work_type" name="work_type" value="{{ old('work_type', data_get($draft, 'work_type')) }}" minlength="3" maxlength="180" placeholder="Jelaskan pekerjaan yang akan dilakukan" required></div>@error('work_type')<span class="pf-error">{{ $message }}</span>@enderror</div>

      <div class="work-conditional-options" id="otherWorkOptions" hidden>
        @php
          $selectedOtherWorkType = old('other_work_type', data_get($draft, 'other_work_type'));
          if (!$selectedOtherWorkType && old('includes_loading_material', data_get($draft, 'includes_loading_material'))) $selectedOtherWorkType = 'loading_material';
          if (old('is_rooftop_work', data_get($draft, 'is_rooftop_work'))) $selectedOtherWorkType = 'rooftop';
          if (old('is_atm_repair', data_get($draft, 'is_atm_repair'))) $selectedOtherWorkType = 'atm_repair';
        @endphp
        <div class="pf" id="otherWorkTypeField">
          <label>Rincian Pekerjaan Lainnya <span class="req">*</span></label>
          <div class="work-extra-options" id="otherWorkTypeOptions">
            @foreach(['loading_material' => 'Loading / unloading material', 'rooftop' => 'Pekerjaan di rooftop', 'atm_repair' => 'Perbaikan ATM', 'fit_out' => 'Fit Out / Renovasi', 'demolition' => 'Pembongkaran', 'other' => 'Lainnya'] as $value => $label)
              <label @if($value === 'rooftop') id="rooftopWorkOption" hidden @endif><input type="radio" name="other_work_type" value="{{ $value }}" @checked($selectedOtherWorkType === $value)><span>{{ $label }}</span></label>
            @endforeach
          </div>
          <input id="includes_loading_material" type="hidden" name="includes_loading_material" value="0">
          <input id="is_rooftop_work" type="hidden" name="is_rooftop_work" value="0">
          <input id="is_atm_repair" type="hidden" name="is_atm_repair" value="0">
          <span class="pf-hint">Pilih satu rincian yang paling sesuai dengan pekerjaan.</span>
          @error('other_work_type')<span class="pf-error">{{ $message }}</span>@enderror
        </div>

        <div class="pf" id="otherWorkDescriptionField" hidden>
          <label for="other_work_description">Rincian Pekerjaan <span class="req">*</span></label>
          <div class="pf-wrap"><svg class="pf-icon"><use href="#i-wrench"/></svg><input id="other_work_description" name="other_work_description" value="{{ old('other_work_description', data_get($draft, 'other_work_description')) }}" minlength="3" maxlength="180" placeholder="Jelaskan pekerjaan yang dilakukan"></div>
          @error('other_work_description')<span class="pf-error">{{ $message }}</span>@enderror
        </div>

        <div class="pf" id="rooftopNetworkOptions" hidden>
          <label for="rooftop_network_type">Jenis Pekerjaan Rooftop <span class="req">*</span></label>
          <div class="pf-wrap pf-select-wrap"><svg class="pf-icon"><use href="#i-wrench"/></svg><select id="rooftop_network_type" name="rooftop_network_type"><option value="">Pilih jenis pekerjaan</option><option value="internet_installation" @selected(old('rooftop_network_type', data_get($draft, 'rooftop_network_type')) === 'internet_installation')>Pemasangan internet</option><option value="bts_repair" @selected(old('rooftop_network_type', data_get($draft, 'rooftop_network_type')) === 'bts_repair')>Perbaikan BTS</option><option value="ac_cleaning" @selected(old('rooftop_network_type', data_get($draft, 'rooftop_network_type')) === 'ac_cleaning')>Maintenance AC</option></select><svg class="pf-select-arrow" aria-hidden="true"><use href="#i-chevron-right"/></svg></div>
          @error('rooftop_network_type')<span class="pf-error">{{ $message }}</span>@enderror
        </div>

        <div class="pf" id="assignmentLetterField" hidden>
          <label for="assignment_letter">Surat Tugas <span class="req">*</span></label>
          <input id="assignment_letter" name="assignment_letter" type="file" accept="application/pdf,image/jpeg,image/jpg,image/png,image/webp">
          <span class="pf-hint">Wajib untuk pemasangan internet, perbaikan BTS, atau perbaikan ATM. PDF, JPG, atau PNG; maksimal 8 MB.</span>
          @error('assignment_letter')<span class="pf-error">{{ $message }}</span>@enderror
        </div>
      </div>

      <div class="pf-row">
        <div class="pf"><label for="start_date">Berlaku Mulai <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-calendar"/></svg><input id="start_date" name="start_date" type="date" value="{{ old('start_date', data_get($draft, 'start_date')) }}" min="{{ now()->toDateString() }}" required></div>@error('start_date')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf"><label for="end_date">Berlaku Sampai <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-calendar"/></svg><input id="end_date" name="end_date" type="date" value="{{ old('end_date', data_get($draft, 'end_date')) }}" min="{{ now()->toDateString() }}" data-max-duration-days="7" required></div><span class="pf-hint" id="workDurationHint">Maksimal 7 hari kalender termasuk tanggal mulai.</span>@error('end_date')<span class="pf-error">{{ $message }}</span>@enderror</div>
      </div>

      <div class="pf"><label>Kebutuhan Tambahan</label><div class="work-extra-options">
        <label><input type="hidden" name="needs_water" value="0"><input type="checkbox" name="needs_water" value="1" @checked(old('needs_water', data_get($draft, 'needs_water')))><span>Air</span></label>
        <label><input type="hidden" name="needs_lighting" value="0"><input type="checkbox" name="needs_lighting" value="1" @checked(old('needs_lighting', data_get($draft, 'needs_lighting')))><span>Tambahan Penerangan</span></label>
      </div><p class="pf-hint">Untuk pekerjaan yang diperiksa Engineering, kebutuhan security deposit dan nominalnya ditentukan oleh Management setelah pemeriksaan.</p></div>

      <div class="pf"><label for="notes">Catatan <span class="pf-optional">Opsional</span></label><textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="Tuliskan kebutuhan teknis atau informasi khusus untuk pengelola gedung">{{ old('notes', data_get($draft, 'notes')) }}</textarea>@error('notes')<span class="pf-error">{{ $message }}</span>@enderror</div>

      <div class="work-document-section">
        <div class="pf"><label>Dokumen Penanggung Jawab <span class="req">*</span></label><div class="pf-radio-group work-document-types">
          @foreach(['ktp' => 'KTP', 'sim' => 'SIM'] as $value => $label)
            <label class="pf-radio {{ old('id_doc_type', data_get($draft, 'id_doc_type', 'ktp')) === $value ? 'selected' : '' }}"><input type="radio" name="id_doc_type" value="{{ $value }}" @checked(old('id_doc_type', data_get($draft, 'id_doc_type', 'ktp')) === $value) required><svg><use href="#i-file"/></svg><span>{{ $label }}</span></label>
          @endforeach
        </div></div>
        <div class="id-upload-area work-id-upload" id="workIdUpload" tabindex="0" role="button" aria-label="Unggah foto KTP atau SIM">
          <input id="id_doc" name="id_doc" type="file" class="id-upload-input" accept="image/jpeg,image/png,image/webp" required>
          <div class="id-upload-prompt" id="workUploadPrompt"><span class="id-upload-icon"><svg><use href="#i-file"/></svg></span><span class="id-upload-text"><strong>Unggah foto KTP / SIM</strong><span>JPG, PNG, atau WebP; maksimal 8 MB</span></span></div>
          <div class="id-upload-preview" id="workUploadPreview" hidden>
            <img id="workPreviewImg" src="" alt="Preview dokumen identitas">
            <div class="id-upload-preview-info">
              <span class="id-upload-preview-name" id="workPreviewName"></span>
              <button type="button" class="id-upload-remove" id="workUploadRemove" aria-label="Hapus foto identitas"><svg><use href="#i-trash"/></svg></button>
            </div>
          </div>
        </div>
        @error('id_doc')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
      </div>
    </section>

    <section class="permit-section permit-section--full wizard-step" data-wizard-step="4">
      <div class="permit-section-head"><div class="permit-section-icon amber"><svg><use href="#i-check"/></svg></div><div><h2>Ringkasan &amp; Peraturan</h2><p>Periksa data dan pahami ketentuan sebelum mengajukan.</p></div></div>

      <div class="work-review-grid">
        <dl class="review-dl"><dt>Kontraktor / Vendor</dt><dd data-review="contractor_name">-</dd><dt>Tenant</dt><dd data-review="tenant_name">-</dd><dt>Penanggung Jawab</dt><dd data-review="applicant_name">-</dd><dt>Kontak</dt><dd data-review="applicant_phone">-</dd><dt>Email</dt><dd data-review="applicant_email">-</dd><dt>Jumlah Pekerja</dt><dd id="reviewWorkerCount">-</dd></dl>
        <dl class="review-dl"><dt>Lokasi</dt><dd data-review="work_location">-</dd><dt>Kategori</dt><dd data-review="work_category">-</dd><dt>Uraian Pekerjaan</dt><dd data-review="work_type">-</dd><dt>Rincian</dt><dd id="reviewOtherWork">-</dd><dt>Waktu Kerja</dt><dd id="reviewSchedule">-</dd><dt>Periode</dt><dd id="reviewPeriod">-</dd><dt>Kebutuhan Tambahan</dt><dd id="reviewUtilities">-</dd><dt>Lampiran Identitas</dt><dd id="reviewIdentity">-</dd></dl>
      </div>

      <div class="work-rules" aria-labelledby="workRulesTitle">
        <h3 id="workRulesTitle"><svg><use href="#i-shield-check"/></svg>Peraturan Pekerjaan</h3>
        <ol>
          <li>Setiap pekerja wajib membawa KTP, SIM, atau tanda pengenal resmi selama bekerja.</li>
          <li>Kerusakan, kehilangan, atau kecelakaan akibat pekerjaan menjadi tanggung jawab tenant atau kontraktor.</li>
          <li>Dilarang merokok, berjudi, minum minuman beralkohol, dan menggunakan obat terlarang di area Mal Bali Galeria.</li>
          <li>Pekerjaan di dalam toko yang mengganggu operasional hanya boleh dilakukan pukul 22.00 - 10.00 WITA atau setelah toko tutup.</li>
          <li>Pekerjaan yang menimbulkan bau, debu, asap, atau percikan api wajib menggunakan penghisap dan partisi pelindung.</li>
          <li>Pekerjaan di roof atau basement dilakukan pukul 08.00 - 16.00 WITA. Hari Minggu tidak diperkenankan, kecuali penanganan gangguan darurat.</li>
          <li>Setelah pekerjaan selesai, seluruh pekerja wajib melapor dan menunjukkan tanda pengenal kepada petugas keamanan.</li>
          <li>Area kerja harus dijaga tetap bersih dan aman serta memenuhi ketentuan keselamatan kerja dan proteksi kebakaran.</li>
        </ol>
      </div>

      <label class="work-consent"><input type="checkbox" name="consent" value="1" @checked(old('consent')) required><span>Saya telah membaca, memahami, dan menyetujui seluruh peraturan pekerjaan di atas.</span></label>
      @error('consent')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
    </section>

    <div class="permit-submit-row">
      <p class="permit-submit-note">Data dan dokumen akan dikirim kepada pengelola gedung untuk diverifikasi.</p>
      <div class="permit-submit-actions"><a href="{{ route('portal.dashboard') }}" class="btn-secondary">Batal</a><button type="submit" class="btn-primary" id="workSubmit"><span class="btn-label">Ajukan Izin Kerja</span><span class="btn-spinner" aria-hidden="true"></span></button></div>
    </div>

    <div class="mobile-wizard-nav" id="mobileWizardNav">
      <button type="button" class="btn-wizard-prev" id="wizardPrevBtn" hidden><svg><use href="#i-chevron-left"/></svg><span>Sebelumnya</span></button>
      <button type="button" class="btn-wizard-next" id="wizardNextBtn"><span>Lanjut</span><svg><use href="#i-chevron-right"/></svg></button>
      <button type="submit" class="btn-wizard-submit" id="wizardSubmitBtn" hidden><svg><use href="#i-check"/></svg><span class="btn-label">Ajukan</span><span class="btn-spinner" aria-hidden="true"></span></button>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('form-image.js') }}?v={{ filemtime(public_path('form-image.js')) }}"></script>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
  const form = document.getElementById('workPermitForm');
  const workerList = document.getElementById('workerList');
  const addWorker = document.getElementById('addWorker');
  const startDate = document.getElementById('start_date');
  const endDate = document.getElementById('end_date');
  const durationHint = document.getElementById('workDurationHint');
  const fileInput = document.getElementById('id_doc');
  const uploadPrompt = document.getElementById('workUploadPrompt');
  const uploadPreview = document.getElementById('workUploadPreview');
  const previewImage = document.getElementById('workPreviewImg');
  const previewName = document.getElementById('workPreviewName');
  const removeUpload = document.getElementById('workUploadRemove');
  const workCategory = document.getElementById('work_category');
  const categoryHint = document.getElementById('workCategoryHint');
  const otherWorkOptions = document.getElementById('otherWorkOptions');
  const outsideSchedule = form.querySelector('[name="work_schedules[]"][value="outside_store"]');
  const rooftopWorkOption = document.getElementById('rooftopWorkOption');
  const rooftopWork = document.getElementById('is_rooftop_work');
  const atmRepair = document.getElementById('is_atm_repair');
  const rooftopNetworkOptions = document.getElementById('rooftopNetworkOptions');
  const rooftopNetworkType = document.getElementById('rooftop_network_type');
  const assignmentLetterField = document.getElementById('assignmentLetterField');
  const assignmentLetter = document.getElementById('assignment_letter');
  const categorySettings = @json($workCategorySettings);
  const durationSettings = {{ Illuminate\Support\Js::from([
    'default' => $durationSettings->default_days,
    'fit_out' => $durationSettings->fit_out_days,
    'demolition' => $durationSettings->demolition_days,
  ]) }};
  const otherWorkTypes = [...form.querySelectorAll('[name="other_work_type"]')];
  const otherWorkDescriptionField = document.getElementById('otherWorkDescriptionField');
  const otherWorkDescription = document.getElementById('other_work_description');

  const errorMessage = input => {
    const value = input.value.trim();
    if (input.type === 'checkbox' && input.required && !input.checked) return 'Bagian ini wajib disetujui.';
    if (input.required && !value) return 'Field ini wajib diisi.';
    if (input.validity.rangeUnderflow) return 'Tanggal tidak boleh sebelum batas yang ditentukan.';
    if (input.validity.rangeOverflow) return `Tanggal melebihi batas masa berlaku ${maxPermitDuration} hari.`;
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
      else if (days >= maxPermitDuration) message = `Masa izin maksimal ${maxPermitDuration} hari kalender termasuk tanggal mulai.`;
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
    const extension = file?.name.split('.').pop()?.toLowerCase();
    const supportedExtension = ['jpg', 'jpeg', 'png', 'webp'].includes(extension);
    let message = '';
    if (!file) message = 'Foto KTP atau SIM wajib diunggah.';
    else if ((file.type && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) || (!file.type && !supportedExtension)) message = 'Gunakan file JPG, PNG, atau WebP.';
    else if (file.size > 8 * 1024 * 1024) message = 'Ukuran dokumen maksimal 8 MB.';
    return setFieldError(fileInput, message, show);
  };

  const validateAssignmentLetter = (show = true) => {
    if (assignmentLetter.disabled) return setFieldError(assignmentLetter, '', show);
    const file = assignmentLetter.files[0];
    const extension = file?.name.split('.').pop()?.toLowerCase();
    let message = '';
    if (!file) message = 'Surat tugas wajib diunggah.';
    else if (!['pdf', 'jpg', 'jpeg', 'png'].includes(extension)) message = 'Gunakan file PDF, JPG, atau PNG.';
    else if (file.size > 8 * 1024 * 1024) message = 'Ukuran surat tugas maksimal 8 MB.';
    return setFieldError(assignmentLetter, message, show);
  };

  const validateWorkers = (show = true) => {
    const names = [...workerList.querySelectorAll('[name$="[name]"]')];
    const valid = names.some(input => input.value.trim().length >= 2);
    let error = workerList.parentElement.querySelector(':scope > .work-workers-client-error');
    if (!valid && show) {
      if (!error) {
        error = document.createElement('span');
        error.className = 'pf-error work-workers-error work-workers-client-error';
        error.setAttribute('role', 'alert');
        workerList.after(error);
      }
      error.textContent = 'Isi minimal satu nama penanggung jawab atau pekerja.';
    } else {
      error?.remove();
    }
    return valid;
  };

  const validateInput = (input, show = true) => {
    if (input === endDate) return validateDates(show);
    if (input === fileInput) return validateFile(show);
    if (input === assignmentLetter) return validateAssignmentLetter(show);
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
    row.innerHTML = `<span class="work-worker-number"></span><label><span class="sr-only">Nama pekerja</span><input minlength="2" maxlength="120" placeholder="Nama lengkap"></label><label><span class="sr-only">Nomor ID pekerja</span><input maxlength="50" placeholder="KTP/SIM/ID perusahaan"></label><button type="button" class="work-worker-remove" data-remove-worker><svg><use href="#i-trash"/></svg></button>`;
    workerList.appendChild(row);
    syncWorkers();
    populateReview();
    row.querySelector('input').focus();
  });

  workerList.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-worker]');
    if (!button || workerList.querySelectorAll('[data-worker-row]').length === 1) return;
    button.closest('[data-worker-row]').remove();
    syncWorkers();
    populateReview();
  });

  document.querySelectorAll('.pf-radio-group').forEach(group => group.addEventListener('change', event => {
    if (!event.target.matches('input[type="radio"], input[type="checkbox"]')) return;
    group.querySelectorAll('.pf-radio').forEach(label => {
      label.classList.toggle('selected', label.querySelector('input').checked);
    });
    if (group.matches('[data-schedule-group]')) validateSchedule(true);
  }));

  let maxPermitDuration = Number(endDate.dataset.maxDurationDays);
  const syncDateRange = () => {
    endDate.min = startDate.value;
    if (!startDate.value) {
      endDate.removeAttribute('max');
      return;
    }
    const max = new Date(`${startDate.value}T00:00:00`);
    max.setDate(max.getDate() + maxPermitDuration - 1);
    const year = max.getFullYear();
    const month = String(max.getMonth() + 1).padStart(2, '0');
    const day = String(max.getDate()).padStart(2, '0');
    const maxValue = `${year}-${month}-${day}`;
    endDate.max = maxValue;
    if (endDate.value && endDate.value < startDate.value) endDate.value = startDate.value;
    if (endDate.value && endDate.value > maxValue) {
      endDate.value = maxValue;
      window.showToast?.(`Tanggal selesai disesuaikan ke batas maksimal ${maxPermitDuration} hari.`, 'info');
    }
    validateDates(endDate.dataset.touched === 'true');
  };
  startDate.addEventListener('input', syncDateRange);
  startDate.addEventListener('change', syncDateRange);
  endDate.addEventListener('change', () => validateDates(true));

  const clearWorkUpload = () => {
    fileInput.value = '';
    previewImage.src = '';
    previewName.textContent = '';
    uploadPrompt.hidden = false;
    uploadPreview.hidden = true;
  };

  const showWorkPreview = file => {
    const reader = new FileReader();
    reader.addEventListener('load', event => {
      previewImage.src = event.target.result;
      previewName.textContent = file.name;
      uploadPrompt.hidden = true;
      uploadPreview.hidden = false;
    });
    reader.readAsDataURL(file);
  };

  fileInput.addEventListener('change', async () => {
    if (!fileInput.files[0]) return;
    try {
      await window.prepareIdentityImage(fileInput);
    } catch (error) {
      setFieldError(fileInput, error.message || 'Foto tidak dapat diproses.', true);
      clearWorkUpload();
      return;
    }

    const file = fileInput.files[0];
    if (!validateFile(true)) {
      fileInput.value = '';
      uploadPrompt.hidden = false;
      uploadPreview.hidden = true;
      return;
    }
    showWorkPreview(file);
  });
  assignmentLetter.addEventListener('change', async () => {
    const file = assignmentLetter.files?.[0];
    if (!file || file.type === 'application/pdf' || /\.pdf$/i.test(file.name)) return;
    try {
      await window.prepareIdentityImage(assignmentLetter);
      validateAssignmentLetter(true);
    } catch (error) {
      setFieldError(assignmentLetter, error.message || 'Foto surat tugas tidak dapat diproses.', true);
    }
  });
  removeUpload.addEventListener('click', event => {
    event.preventDefault();
    event.stopPropagation();
    clearWorkUpload();
    setFieldError(fileInput, 'Foto KTP atau SIM wajib diunggah.', true);
  });

  let currentStep = 1;
  const draftStep = document.getElementById('draftStep');
  const prev = document.getElementById('wizardPrevBtn');
  const next = document.getElementById('wizardNextBtn');
  const mobileSubmit = document.getElementById('wizardSubmitBtn');

  const stepValid = step => {
    const section = document.querySelector(`[data-wizard-step="${step}"]`);
    const fields = [...section.querySelectorAll('input, select, textarea')].filter(input => input.type !== 'hidden');
    const results = fields.map(input => validateInput(input, true));
    if (step === 2) results.push(validateWorkers(true));
    if (step === 3) results.push(validateSchedule(true), validateDates(true), validateFile(true), validateAssignmentLetter(true));
    const firstInvalid = fields.find(input => !input.checkValidity());
    return results.every(Boolean) && !firstInvalid;
  };

  const focusStepError = step => {
    const section = document.querySelector(`[data-wizard-step="${step}"]`);
    const invalid = section?.querySelector(':invalid, .is-invalid');
    const message = section?.querySelector('.pf-client-error, .pf-error')?.textContent?.trim()
      || invalid?.validationMessage
      || 'Periksa kembali data pada langkah ini.';
    requestAnimationFrame(() => {
      invalid?.focus({ preventScroll: true });
      invalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      window.showToast?.(message, 'error');
    });
  };

  const populateReview = () => {
    document.querySelectorAll('[data-review]').forEach(output => {
      const field = document.getElementById(output.dataset.review);
      output.textContent = field?.tagName === 'SELECT' ? field.selectedOptions[0]?.textContent || '-' : field?.value || '-';
    });
    const namedWorkers = [...workerList.querySelectorAll('[name$="[name]"]')].filter(input => input.value.trim()).length;
    document.getElementById('reviewWorkerCount').textContent = `${namedWorkers} orang`;
    const otherTypeLabels = { fit_out: 'Fit Out / Renovasi', demolition: 'Pembongkaran', other: otherWorkDescription.value.trim() || 'Lainnya' };
    const details = [];
    const selectedDetail = selectedOtherWorkType();
    if (workCategory.value === 'other' && otherTypeLabels[selectedDetail]) details.push(otherTypeLabels[selectedDetail]);
    if (selectedDetail === 'loading_material') details.push('Loading / unloading material');
    if (selectedDetail === 'rooftop') details.push(`Rooftop: ${rooftopNetworkType.selectedOptions[0]?.textContent || '-'}`);
    if (selectedDetail === 'atm_repair') details.push('Perbaikan ATM');
    document.getElementById('reviewOtherWork').textContent = details.join('; ') || '-';
    const scheduleLabels = [...form.querySelectorAll('[name="work_schedules[]"]:checked')].map(input => input.value === 'inside_store' ? 'Dalam toko, 22.00 - 10.00 WITA' : 'Di luar toko, 08.00 - 16.00 WITA');
    document.getElementById('reviewSchedule').textContent = scheduleLabels.join('; ') || '-';
    document.getElementById('reviewPeriod').textContent = startDate.value && endDate.value ? `${startDate.value} s.d. ${endDate.value}` : '-';
    const utilities = [];
    if (document.querySelector('[name="needs_water"]:checked')) utilities.push('Air');
    if (document.querySelector('[name="needs_lighting"]:checked')) utilities.push('Penerangan');
    document.getElementById('reviewUtilities').textContent = utilities.join(', ') || 'Tidak ada';
    document.getElementById('reviewIdentity').textContent = fileInput.files?.[0]?.name || '-';
  };

  const setStep = step => {
    currentStep = step;
    if (draftStep) draftStep.value = String(step);
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
  next.addEventListener('click', () => {
    if (stepValid(currentStep)) setStep(Math.min(4, currentStep + 1));
    else focusStepError(currentStep);
  });
  document.querySelectorAll('.wizard-step-indicator').forEach(indicator => indicator.addEventListener('click', () => {
    const target = Number(indicator.dataset.step);
    if (target < currentStep || (target === currentStep + 1 && stepValid(currentStep))) setStep(target);
  }));

  form.addEventListener('submit', event => {
    event.preventDefault();
    for (let step = 1; step <= 4; step += 1) {
      if (!stepValid(step)) {
        setStep(step);
        focusStepError(step);
        return;
      }
    }
    const desktopSubmit = document.getElementById('workSubmit');
    desktopSubmit.disabled = true;
    desktopSubmit.classList.add('loading');
    desktopSubmit.querySelector('.btn-label').textContent = 'Mengajukan...';
    mobileSubmit.disabled = true;
    mobileSubmit.classList.add('loading');
    mobileSubmit.querySelector('.btn-label').textContent = 'Mengajukan...';
    window.showPermitSubmitProgress?.('Mengirim surat izin kerja');
    form.dispatchEvent(new Event('permit-draft:submitting'));
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
    populateReview();
  });

  form.addEventListener('change', populateReview);

  workerList.addEventListener('input', event => {
    if (!event.target.matches('input')) return;
    event.target.dataset.touched = 'true';
    validateInput(event.target, true);
    validateWorkers(true);
  });

  const selectedOtherWorkType = () => form.querySelector('[name="other_work_type"]:checked')?.value || '';

  const syncDuration = () => {
    const configuredCategoryDuration = Number(categorySettings[workCategory.value]?.max_duration_days) || Number(durationSettings.default) || 7;
    const detailType = selectedOtherWorkType();
    maxPermitDuration = workCategory.value === 'other'
      ? Number(durationSettings[detailType] || durationSettings.default || 7)
      : configuredCategoryDuration;
    endDate.dataset.maxDurationDays = String(maxPermitDuration);
    durationHint.textContent = `Maksimal ${maxPermitDuration} hari kalender termasuk tanggal mulai.`;
    syncDateRange();
  };

  const syncCategoryHint = () => {
    if (!workCategory.value) {
      maxPermitDuration = Number(durationSettings.default) || 7;
      endDate.dataset.maxDurationDays = String(maxPermitDuration);
      durationHint.textContent = `Maksimal ${maxPermitDuration} hari kalender termasuk tanggal mulai.`;
      categoryHint.textContent = 'Kategori menentukan divisi pemeriksa secara otomatis.';
      syncDateRange();
      return;
    }
    const division = categorySettings[workCategory.value]?.division ?? 'MEP';
    const divisionLabel = division === 'MEP' ? 'Engineering' : division;
    categoryHint.textContent = `Permohonan ini akan diperiksa oleh divisi ${divisionLabel}.`;
    syncDuration();
  };

  const syncOtherWorkOptions = () => {
    const isOther = workCategory.value === 'other';
    const allowsRooftop = isOther && outsideSchedule.checked;
    let detailType = selectedOtherWorkType();
    otherWorkOptions.hidden = !isOther;
    otherWorkTypes.forEach(input => {
      input.disabled = !isOther;
      input.required = isOther;
    });
    rooftopWorkOption.hidden = !allowsRooftop;
    if (!isOther) {
      rooftopNetworkType.value = '';
      assignmentLetter.value = '';
      otherWorkTypes.forEach(input => { input.checked = false; });
      otherWorkDescription.value = '';
      detailType = '';
    }
    if (!allowsRooftop && detailType === 'rooftop') {
      otherWorkTypes.find(input => input.value === 'rooftop').checked = false;
      detailType = '';
    }

    document.getElementById('includes_loading_material').value = detailType === 'loading_material' ? '1' : '0';
    rooftopWork.value = detailType === 'rooftop' ? '1' : '0';
    atmRepair.value = detailType === 'atm_repair' ? '1' : '0';

    const needsNetworkType = allowsRooftop && detailType === 'rooftop';
    rooftopNetworkOptions.hidden = !needsNetworkType;
    rooftopNetworkType.disabled = !needsNetworkType;
    rooftopNetworkType.required = needsNetworkType;
    if (!needsNetworkType) rooftopNetworkType.value = '';

    const needsRooftopAssignment = detailType === 'rooftop' && ['internet_installation', 'bts_repair'].includes(rooftopNetworkType.value);
    const needsAssignment = isOther && (needsRooftopAssignment || detailType === 'atm_repair');
    assignmentLetterField.hidden = !needsAssignment;
    assignmentLetter.disabled = !needsAssignment;
    assignmentLetter.required = needsAssignment;
    if (!needsAssignment) assignmentLetter.value = '';

    const needsCustomDescription = isOther && selectedOtherWorkType() === 'other';
    otherWorkDescriptionField.hidden = !needsCustomDescription;
    otherWorkDescription.disabled = !needsCustomDescription;
    otherWorkDescription.required = needsCustomDescription;
    if (!needsCustomDescription) otherWorkDescription.value = '';
    syncDuration();
  };

  workCategory.addEventListener('change', () => {
    syncCategoryHint();
    syncOtherWorkOptions();
  });
  rooftopNetworkType.addEventListener('change', syncOtherWorkOptions);
  outsideSchedule.addEventListener('change', syncOtherWorkOptions);
  otherWorkTypes.forEach(input => input.addEventListener('change', syncOtherWorkOptions));
  syncCategoryHint();
  syncOtherWorkOptions();

  if (startDate.value) syncDateRange();
  syncWorkers();
  populateReview();
  const restoredStep = Math.min(3, Math.max(1, Number(draftStep?.value) || 1));
  setStep(restoredStep);
})();
</script>
<script src="{{ asset('permit-draft.js') }}"></script>
@endpush
