@extends('layouts.validator')

@section('title', $permit->permit_number . ' : Secretary')
@section('page-title', 'Detail Loading dan Unloading')

@section('content')
<div class="page-wrap page-wrap--narrow">
  <a href="{{ route('secretary.index', ['status' => 'loading']) }}" class="validator-back-link"><svg><use href="#i-arrow-left"/></svg>Kembali ke data loading</a>

  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><a href="{{ route('secretary.index') }}">Secretary</a><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Loading dan Unloading</span></div>
      <h1 class="page-title">{{ $permit->permit_number }}</h1>
      <p class="page-subtitle">Diajukan {{ $permit->created_at->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }} WITA</p>
    </div>
    <span class="dt-badge-status dt-status-{{ $permit->status }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span>
  </div>

  <section class="detail-card">
    <h2 class="detail-card-title">Tenant dan pemohon</h2>
    <dl class="detail-dl">
      <dt>Tenant</dt><dd>{{ $permit->tenant_name }}</dd>
      <dt>Nama pemohon</dt><dd>{{ $permit->applicant_name }}</dd>
      <dt>Nomor telepon</dt><dd>{{ $permit->applicant_phone }}</dd>
      <dt>Email</dt><dd>{{ $permit->applicant_email ?: '-' }}</dd>
    </dl>
  </section>

  <section class="detail-card">
    <h2 class="detail-card-title">Rincian pergerakan barang</h2>
    <dl class="detail-dl">
      <dt>Arah</dt><dd>{{ $permit->direction_label }}</dd>
      <dt>Periode</dt><dd>{{ $permit->start_date->format('d M Y') }} s.d. {{ $permit->end_date->format('d M Y') }}</dd>
      <dt>{{ $permit->movement_time_field_label }}</dt><dd>{{ $permit->movement_time_label }}</dd>
      <dt>Jumlah</dt><dd>{{ $permit->item_count }} {{ $permit->item_unit }}</dd>
      <dt>Nomor kendaraan</dt><dd>{{ $permit->vehicle_plate ?: '-' }}</dd>
      <dt>Deskripsi barang</dt><dd>{{ $permit->item_description }}</dd>
    </dl>
  </section>

  @if($permit->reviewed_at)
    <section class="detail-card">
      <h2 class="detail-card-title">Hasil pemeriksaan</h2>
      <dl class="detail-dl">
        <dt>Validator</dt><dd>{{ $permit->reviewer?->name ?? '-' }}</dd>
        <dt>Waktu pemeriksaan</dt><dd>{{ $permit->reviewed_at->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }} WITA</dd>
        <dt>Catatan</dt><dd>{{ $permit->review_notes ?: 'Tidak ada catatan.' }}</dd>
      </dl>
    </section>
  @endif
</div>
@endsection
