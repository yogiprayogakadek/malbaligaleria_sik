@extends('layouts.admin')

@section('title', 'Dashboard Admin : Mal Bali Galeria')
@section('page-title', 'Dashboard Admin')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Ringkasan</span></div>
      <h1 class="page-title">Kendali operasional perizinan</h1>
      <p class="page-subtitle">Pantau permohonan, akun validator, dan ketersediaan portal tenant.</p>
    </div>
    <span class="admin-live-state {{ $siteIsOpen ? 'is-open' : 'is-closed' }}"><span></span>{{ $siteIsOpen ? 'Portal beroperasi' : 'Portal ditutup' }}</span>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif

  <section class="validator-metrics" aria-label="Ringkasan administrasi">
    <a href="{{ route('admin.loading.index') }}" class="validator-metric validator-metric--all"><span class="validator-metric-icon"><svg><use href="#i-file"/></svg></span><span class="validator-metric-copy"><span>Total loading</span><strong>{{ $counts['loading'] }}</strong></span></a>
    <a href="{{ route('admin.loading.index', ['status' => 'pending']) }}" class="validator-metric validator-metric--pending"><span class="validator-metric-icon"><svg><use href="#i-clock"/></svg></span><span class="validator-metric-copy"><span>Menunggu</span><strong>{{ $counts['pending'] }}</strong></span></a>
    <a href="{{ route('admin.validators.index') }}" class="validator-metric validator-metric--approved"><span class="validator-metric-icon"><svg><use href="#i-user"/></svg></span><span class="validator-metric-copy"><span>Validator aktif</span><strong>{{ $counts['active_validators'] }}</strong></span></a>
    <a href="{{ route('admin.validators.index') }}" class="validator-metric validator-metric--rejected"><span class="validator-metric-icon"><svg><use href="#i-user"/></svg></span><span class="validator-metric-copy"><span>Total validator</span><strong>{{ $counts['validators'] }}</strong></span></a>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Permohonan terbaru</h2><p>Enam pengajuan loading terakhir yang masuk.</p></div><a href="{{ route('admin.loading.index') }}" class="admin-text-link">Lihat semua</a></div>
    @include('admin.loading._list', ['permits' => $recentPermits, 'compactList' => true])
  </section>
</div>
@endsection
