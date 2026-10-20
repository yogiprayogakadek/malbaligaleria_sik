@extends('layouts.admin')

@section('title', 'Detail Loading : Admin MBG')
@section('page-title', 'Detail Loading Barang')

@section('content')
<div class="page-wrap admin-page-wrap page-wrap--narrow">
  <div class="page-header admin-detail-heading">
    <div><span class="admin-status admin-status--{{ $permit->revoked_at ? 'revoked' : $permit->status }}">{{ $permit->status_label }}</span><h1>{{ $permit->permit_number }}</h1><p>Diajukan {{ $permit->created_at->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }} WITA</p></div>
    <div class="page-actions">
      @if($permit->status === 'approved' && !$permit->revoked_at)
        <x-permit-letter-link :href="route('loading.letter.preview', $permit->permit_number)" />
      @endif
      <a href="{{ route('admin.loading.index', ['status' => $permit->status]) }}" class="validator-back-link"><svg><use href="#i-arrow-left"/></svg>Kembali ke data loading</a>
    </div>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if(session('info'))<div class="permit-alert permit-alert--info" role="status"><svg><use href="#i-info"/></svg><span>{{ session('info') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif
  @if($permit->revoked_at)
    <div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span><strong>Surat izin telah dicabut.</strong> {{ $permit->revocation_reason }} Dicabut {{ $permit->revoked_at->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }} WITA oleh {{ $permit->revoker?->name ?? 'petugas' }}.</span></div>
  @endif
  <section class="admin-panel admin-detail-section">
    <div class="admin-panel-header"><div><h2>Tenant dan pemohon</h2><p>Identitas pihak yang mengajukan izin.</p></div></div>
    <dl class="admin-detail-grid">
      <div><dt>Tenant</dt><dd>{{ $permit->tenant_name }}</dd></div>
      <div><dt>Nama pemohon</dt><dd>{{ $permit->applicant_name }}</dd></div>
      <div><dt>Nomor telepon</dt><dd>{{ $permit->applicant_phone }}</dd></div>
      <div><dt>Email</dt><dd>{{ $permit->applicant_email ?: '-' }}</dd></div>
    </dl>
  </section>

  <section class="admin-panel admin-detail-section">
    <div class="admin-panel-header"><div><h2>Rincian pergerakan barang</h2><p>Jadwal dan muatan yang diajukan.</p></div></div>
    <dl class="admin-detail-grid">
      <div><dt>Arah</dt><dd>{{ $permit->direction_label }}</dd></div>
      <div><dt>Periode</dt><dd>{{ $permit->start_date->format('d M Y') }} - {{ $permit->end_date->format('d M Y') }}</dd></div>
      <div><dt>{{ $permit->movement_time_field_label }}</dt><dd>{{ $permit->movement_time_label }}</dd></div>
      <div><dt>Jumlah</dt><dd>{{ $permit->item_count }} {{ $permit->item_unit }}</dd></div>
      <div><dt>Nomor kendaraan</dt><dd>{{ $permit->vehicle_plate ?: '-' }}</dd></div>
      <div class="admin-detail-wide"><dt>Deskripsi barang</dt><dd>{{ $permit->item_description }}</dd></div>
    </dl>
    <div class="admin-document-row">
      <span><strong>Dokumen identitas</strong><small>Tautan aman berlaku selama 5 menit.</small></span>
      <a href="{{ URL::temporarySignedRoute('admin.loading.document.preview', now()->addMinutes(5), ['documentToken' => $permit->document_token]) }}" class="admin-secondary-button"><svg><use href="#i-eye"/></svg>Lihat dokumen</a>
      @if($permit->instruction_letter_path)<a href="{{ URL::temporarySignedRoute('admin.loading.instruction-letter.preview', now()->addMinutes(5), ['documentToken' => $permit->document_token]) }}" class="admin-secondary-button"><svg><use href="#i-file"/></svg>Surat izin/perintah</a>@endif
    </div>
  </section>

  @if($permit->reviewed_at)
    <section class="admin-panel admin-detail-section">
      <div class="admin-panel-header"><div><h2>Hasil pemeriksaan</h2><p>Keputusan yang dicatat oleh validator.</p></div></div>
      <dl class="admin-detail-grid">
        <div><dt>Validator</dt><dd>{{ $permit->reviewer?->name ?? '-' }}</dd></div>
        <div><dt>Waktu pemeriksaan</dt><dd>{{ $permit->reviewed_at->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }} WITA</dd></div>
        <div class="admin-detail-wide"><dt>Catatan</dt><dd>{{ $permit->review_notes ?: 'Tidak ada catatan.' }}</dd></div>
        @if($permit->official_document_id)
          <div><dt>ID dokumen resmi</dt><dd><code>{{ $permit->official_document_id }}</code></dd></div>
          <div><dt>Sidik SHA-256</dt><dd><code>{{ substr($permit->official_document_sha256, 0, 16) }}...</code></dd></div>
        @endif
      </dl>
    </section>
  @endif

  @if($permit->status === 'approved' && !$permit->revoked_at)
    <section class="permit-revoke-panel" aria-labelledby="revokeLoadingTitle">
      <div><h2 id="revokeLoadingTitle">Cabut surat izin</h2><p>QR dan unduhan surat akan langsung dinonaktifkan. Riwayat persetujuan tetap tersimpan.</p></div>
      <form action="{{ route('staff.permit-revocations.loading', $permit->permit_number) }}" method="POST" data-confirm="Cabut surat izin ini? QR dan dokumen resmi tidak dapat digunakan lagi.">
        @csrf
        <label for="revocation_reason">Alasan pencabutan <span class="req">*</span></label>
        <textarea id="revocation_reason" name="revocation_reason" rows="3" minlength="10" maxlength="1000" required placeholder="Jelaskan alasan operasional atau keamanan secara spesifik.">{{ old('revocation_reason') }}</textarea>
        <button type="submit" class="btn-tr-reject"><svg><use href="#i-lock"/></svg>Cabut surat</button>
      </form>
    </section>
  @endif

  <x-communication-timeline :items="$communicationTimeline" />
</div>
@endsection
