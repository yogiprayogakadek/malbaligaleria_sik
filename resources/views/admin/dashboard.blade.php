@extends('layouts.admin')

@section('title', 'Dashboard Admin : Mal Bali Galeria')
@section('page-title', 'Dashboard Admin')

@section('content')
<div class="page-wrap admin-page-wrap">
  <x-page-header
    title="Kendali operasional perizinan"
    subtitle="Pantau permohonan, akun validator, dan ketersediaan portal tenant."
    :breadcrumbs="[['label' => 'Administrasi'], ['label' => 'Ringkasan']]"
    class="admin-page-header">
    <span class="admin-live-state {{ $siteIsOpen ? 'is-open' : 'is-closed' }}"><span></span>{{ $siteIsOpen ? 'Portal beroperasi' : 'Portal ditutup' }}</span>
  </x-page-header>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif

  <section class="admin-attention" aria-labelledby="adminAttentionTitle">
    <div class="admin-attention-summary">
      <span class="admin-attention-icon"><svg><use href="#i-info"/></svg></span>
      <div><p>Perlu perhatian</p><strong id="adminAttentionTitle">{{ $counts['action_required'] }} hal</strong><span>Antrean dan gangguan yang masih membutuhkan tindakan admin atau validator.</span></div>
    </div>
    <div class="admin-attention-list">
      <a href="{{ route('admin.emergency-validation.index') }}" class="admin-attention-item {{ $counts['pending'] > 0 ? 'is-warning' : 'is-clear' }}">
        <span><svg><use href="#i-clock"/></svg></span><div><strong>{{ $counts['pending'] }} antrean menunggu</strong><small>{{ $counts['pending_by_type']['loading'] }} loading, {{ $counts['pending_by_type']['work'] }} izin kerja, {{ $counts['pending_by_type']['event'] }} event</small></div><svg class="admin-attention-arrow"><use href="#i-chevron-right"/></svg>
      </a>
      <a href="{{ route('admin.communication-logs.index', ['status' => 'failed']) }}" class="admin-attention-item {{ $counts['failed_communications'] > 0 ? 'is-danger' : 'is-clear' }}">
        <span><svg><use href="#i-mail"/></svg></span><div><strong>{{ $counts['failed_communications'] }} komunikasi gagal</strong><small>Periksa pengiriman email atau WhatsApp yang perlu dicoba ulang.</small></div><svg class="admin-attention-arrow"><use href="#i-chevron-right"/></svg>
      </a>
      <a href="{{ route('admin.health.index') }}" class="admin-attention-item {{ $counts['unresolved_alerts'] > 0 ? 'is-danger' : 'is-clear' }}">
        <span><svg><use href="#i-shield-check"/></svg></span><div><strong>{{ $counts['unresolved_alerts'] }} peringatan sistem</strong><small>Periksa layanan eksternal, queue, dan keamanan yang belum pulih.</small></div><svg class="admin-attention-arrow"><use href="#i-chevron-right"/></svg>
      </a>
    </div>
  </section>

  <div class="admin-operational-facts" aria-label="Kondisi operasional">
    <a href="{{ route('admin.validators.index') }}"><span>Validator aktif</span><strong>{{ $counts['active_validators'] }} dari {{ $counts['validators'] }}</strong></a>
    <a href="{{ route('admin.loading.index') }}"><span>Total data loading</span><strong>{{ $counts['loading'] }}</strong></a>
  </div>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Permohonan terbaru</h2><p>Enam pengajuan loading terakhir yang masuk.</p></div><a href="{{ route('admin.loading.index') }}" class="admin-text-link">Lihat semua</a></div>
    @include('admin.loading._list', ['permits' => $recentPermits, 'compactList' => true])
  </section>
</div>
@endsection
