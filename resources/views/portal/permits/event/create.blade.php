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

    <section class="permit-section permit-section--full">
      <div class="permit-section-head"><div class="permit-section-icon amber"><svg><use href="#i-calendar"/></svg></div><div><h2>Jenis Surat Event</h2><p>Pilihan menentukan jam kegiatan yang diizinkan.</p></div></div>
      <div class="event-type-options" role="radiogroup" aria-label="Jenis surat event">
        <label class="event-type-option"><input type="radio" name="event_type" value="organizer" @checked(old('event_type') === 'organizer') required><span><strong>Surat Izin Penyelenggara Event / EO</strong><small>Kegiatan diperbolehkan pukul 07.00 - 22.00 WITA.</small></span></label>
        <label class="event-type-option"><input type="radio" name="event_type" value="tenant" @checked(old('event_type') === 'tenant') required><span><strong>Surat Izin Event Tenant</strong><small>Kegiatan diperbolehkan pukul 10.00 - 22.00 WITA.</small></span></label>
      </div>
      @error('event_type')<span class="pf-error">{{ $message }}</span>@enderror
    </section>

    <div class="permit-form-grid event-form-grid">
      <section class="permit-section">
        <div class="permit-section-head"><div class="permit-section-icon blue"><svg><use href="#i-user"/></svg></div><div><h2>Pemohon</h2><p>Identitas penyelenggara dan kontak yang bertanggung jawab.</p></div></div>
        <div class="pf"><label for="organizer_name">Nama EO / Tenant <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-building"/></svg><input id="organizer_name" name="organizer_name" value="{{ old('organizer_name', $user?->tenant_name) }}" minlength="2" maxlength="150" required></div>@error('organizer_name')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf"><label for="applicant_name">PIC / Penanggung Jawab <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-user"/></svg><input id="applicant_name" name="applicant_name" value="{{ old('applicant_name', $user?->name) }}" minlength="2" maxlength="120" autocomplete="name" required></div>@error('applicant_name')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="pf-row">
          <div class="pf"><label for="applicant_phone">Nomor WhatsApp <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-phone"/></svg><input id="applicant_phone" name="applicant_phone" type="tel" inputmode="tel" value="{{ old('applicant_phone', $user?->phone) }}" maxlength="25" autocomplete="tel" required></div>@error('applicant_phone')<span class="pf-error">{{ $message }}</span>@enderror</div>
          <div class="pf"><label for="applicant_email">Email <span class="req">*</span></label><div class="pf-wrap"><svg class="pf-icon"><use href="#i-mail"/></svg><input id="applicant_email" name="applicant_email" type="email" value="{{ old('applicant_email', $user?->email) }}" maxlength="120" autocomplete="email" required></div>@error('applicant_email')<span class="pf-error">{{ $message }}</span>@enderror</div>
        </div>
      </section>

      <section class="permit-section">
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

    <section class="permit-section permit-section--full">
      <div class="permit-section-head"><div class="permit-section-icon blue"><svg><use href="#i-grid"/></svg></div><div><h2>Kebutuhan Event</h2><p>Opsional. Pilih fasilitas dan akses yang perlu disiapkan.</p></div></div>
      <div class="event-requirement-groups">
        @foreach($requirements as $group => $items)
          <fieldset class="event-requirement-group"><legend>{{ $group }}</legend><div class="work-extra-options">@foreach($items as $item)<label><input type="checkbox" name="requirement_ids[]" value="{{ $item->id }}" @checked(in_array($item->id, old('requirement_ids', [])))><span>{{ $item->name }}</span></label>@endforeach</div></fieldset>
        @endforeach
      </div>
      @error('requirement_ids.*')<span class="pf-error">{{ $message }}</span>@enderror

      <label class="event-loading-option"><input type="hidden" name="includes_loading_material" value="0"><input id="includes_loading_material" type="checkbox" name="includes_loading_material" value="1" @checked(old('includes_loading_material'))><span><strong>Termasuk loading / unloading</strong><small>Jika dipilih, sistem akan menerbitkan surat tambahan loading / unloading dengan estimasi waktu pukul 22.00 - 21.00 WITA.</small></span></label>

      <div class="pf"><label for="notes">Rincian Pekerjaan <span class="req">*</span></label><textarea id="notes" name="notes" rows="5" minlength="10" maxlength="3000" placeholder="Jelaskan susunan kegiatan, pekerjaan yang dilakukan, kebutuhan area, dan informasi penting lainnya." required>{{ old('notes') }}</textarea>@error('notes')<span class="pf-error">{{ $message }}</span>@enderror</div>
    </section>

    <section class="permit-section permit-section--full">
      <div class="permit-section-head"><div class="permit-section-icon amber"><svg><use href="#i-shield-check"/></svg></div><div><h2>Ketentuan Event</h2><p>Ketentuan ini dicantumkan kembali pada surat resmi.</p></div></div>
      <div class="work-rules"><ol>@foreach(config('event-permits.notes') as $note)<li>{{ $note }}</li>@endforeach</ol></div>
      <label class="work-consent"><input type="checkbox" name="terms_accepted" value="1" @checked(old('terms_accepted')) required><span>Saya telah membaca, memahami, dan menyetujui seluruh ketentuan event di atas.</span></label>
      @error('terms_accepted')<span class="pf-error work-workers-error">{{ $message }}</span>@enderror
    </section>

    <div class="permit-submit-row"><p class="permit-submit-note">Permohonan akan dikirim kepada Divisi Event Promotion untuk diperiksa.</p><div class="permit-submit-actions"><a href="{{ route('portal.dashboard') }}" class="btn-secondary">Batal</a><button type="submit" class="btn-primary" id="eventSubmit"><span class="btn-label">Ajukan Surat Izin Event</span><span class="btn-spinner" aria-hidden="true"></span></button></div></div>
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
  const progress = document.getElementById('eventSubmitProgress');
  const iso = date => date.toISOString().slice(0, 10);
  const syncDates = () => {
    if (!start.value) return;
    const first = new Date(start.value + 'T00:00:00');
    const last = new Date(first);
    last.setDate(last.getDate() + 6);
    end.min = start.value;
    end.max = iso(last);
    if (end.value && (end.value < end.min || end.value > end.max)) end.value = '';
  };
  start.addEventListener('change', syncDates);
  syncDates();
  form.addEventListener('submit', event => {
    if (!form.checkValidity()) {
      event.preventDefault();
      form.reportValidity();
      form.querySelector(':invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    submit.disabled = true;
    submit.classList.add('loading');
    progress.hidden = false;
    document.body.classList.add('permit-submit-pending');
  });
})();
</script>
@endpush
