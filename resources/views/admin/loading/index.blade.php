@extends('layouts.admin')

@section('title', 'Data Loading Barang : Admin MBG')
@section('page-title', 'Data Loading Barang')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header"><div><div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Loading barang</span></div><h1 class="page-title">Data loading barang</h1><p class="page-subtitle">Seluruh pengajuan berada dalam satu menu dengan status yang terpisah.</p></div></div>

  <nav class="admin-status-tabs" aria-label="Filter status loading">
    @foreach(['all' => 'Semua', 'pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label)
      <a href="{{ route('admin.loading.index', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}" @if($status === $key) aria-current="page" @endif><span>{{ $label }}</span><strong>{{ $counts[$key] }}</strong></a>
    @endforeach
  </nav>

  <section class="admin-panel" id="validatorPermitResults" data-validator-queue-status="{{ $status }}">
    <div class="admin-panel-header"><div><h2>{{ ['all' => 'Semua permohonan', 'pending' => 'Menunggu pemeriksaan', 'approved' => 'Permohonan disetujui', 'rejected' => 'Permohonan ditolak'][$status] }}</h2><p>{{ $permits->total() }} data ditemukan.</p></div></div>
    @include('admin.loading._list', ['permits' => $permits])
    @if($permits->hasPages())
      <nav class="admin-pagination" aria-label="Navigasi halaman">
        @if($permits->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $permits->previousPageUrl() }}">Sebelumnya</a>@endif
        <small>Halaman {{ $permits->currentPage() }} dari {{ $permits->lastPage() }}</small>
        @if($permits->hasMorePages())<a href="{{ $permits->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
      </nav>
    @endif
  </section>
</div>
@endsection
