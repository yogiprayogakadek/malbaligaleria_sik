@extends('layouts.admin')

@section('title', 'Pencarian Perizinan : Admin MBG')
@section('page-title', 'Pencarian Perizinan')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Pencarian</span></div>
      <h1 class="page-title">Cari data perizinan</h1>
      <p class="page-subtitle">Temukan permohonan berdasarkan nomor surat, tenant, PIC, telepon, kategori, atau status.</p>
    </div>
  </div>

  <section class="admin-panel permit-search-panel">
    <form action="{{ route('admin.search.store') }}" method="POST" class="permit-search-form" role="search">
      @csrf
      <label class="permit-search-query" for="permitSearchQuery">
        <span>Kata pencarian</span>
        <span class="permit-search-input"><svg><use href="#i-search"/></svg><input id="permitSearchQuery" name="q" type="search" value="{{ $search }}" minlength="2" maxlength="100" placeholder="Nomor surat, tenant, PIC, telepon..." autocomplete="off"></span>
        @error('q')<span class="pf-error">{{ $message }}</span>@enderror
      </label>
      <label><span>Jenis surat</span><select name="type"><option value="all" @selected($type === 'all')>Semua jenis</option><option value="loading" @selected($type === 'loading')>Loading / Unloading</option><option value="work" @selected($type === 'work')>Surat Izin Kerja</option></select></label>
      <label><span>Status</span><select name="status"><option value="all" @selected($status === 'all')>Semua status</option>@foreach($statusOptions as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label>
      <div class="permit-search-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-search"/></svg>Cari</button>@if($search !== '' || $type !== 'all' || $status !== 'all')<a href="{{ route('admin.search') }}" class="admin-secondary-button">Reset</a>@endif</div>
    </form>
  </section>

  <section class="admin-panel permit-search-results" aria-live="polite">
    <div class="admin-panel-header">
      <div><h2>Hasil pencarian</h2><p>@if($results){{ $results->total() }} data ditemukan. Kata pencarian: {{ $search }}.@else Masukkan minimal 2 karakter untuk memulai pencarian.@endif</p></div>
    </div>

    @if(!$results)
      <x-data-table-empty icon="search" title="Belum ada pencarian" message="Hasil dari loading, unloading, dan surat izin kerja akan ditampilkan di sini." />
    @elseif($results->isEmpty())
      <x-data-table-empty icon="search" title="Data tidak ditemukan" message="Coba kata lain atau ubah filter jenis surat dan status." />
    @else
      <div class="admin-table-wrap">
        <table class="admin-table permit-search-table">
          <thead><tr><th>Nomor surat</th><th>Tenant / kontraktor</th><th>PIC</th><th>Jenis / kategori</th><th>Status</th><th>Diajukan</th><th><span class="sr-only">Tindakan</span></th></tr></thead>
          <tbody>@foreach($results as $permit)<tr>
            <td><code>{{ $permit->permit_number }}</code><span>{{ $permit->assigned_division === 'MEP' ? 'Engineering' : $permit->assigned_division }}</span></td>
            <td><strong>{{ $permit->entity_name }}</strong><span>{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</span></td>
            <td><strong>{{ $permit->applicant_name }}</strong><span>{{ $permit->phone_masked }}</span></td>
            <td><strong>{{ $permit->type_label }}</strong><span>{{ $permit->category_label }}</span></td>
            <td><span class="admin-status admin-status--{{ $permit->status }}">{{ $permit->status_label }}</span></td>
            <td>{{ $permit->created_at->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }}</td>
            <td><a href="{{ $permit->detail_url }}" class="admin-row-action" aria-label="Lihat {{ $permit->permit_number }}"><svg><use href="#i-chevron-right"/></svg></a></td>
          </tr>@endforeach</tbody>
        </table>
      </div>

      <div class="admin-mobile-list permit-search-mobile-list">
        @foreach($results as $permit)
          <a href="{{ $permit->detail_url }}" class="admin-mobile-row">
            <span class="admin-mobile-row-main"><strong>{{ $permit->entity_name }}</strong><code>{{ $permit->permit_number }}</code><small>{{ $permit->type_label }} · {{ $permit->category_label }}</small><small>{{ $permit->applicant_name }} · {{ $permit->phone_masked }}</small></span>
            <span><span class="admin-status admin-status--{{ $permit->status }}">{{ $permit->status_label }}</span><small>{{ $permit->created_at->timezone(config('operating-hours.timezone'))->format('d M Y') }}</small></span>
          </a>
        @endforeach
      </div>

      @if($results->hasPages())<nav class="admin-pagination" aria-label="Navigasi hasil pencarian">@if($results->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $results->previousPageUrl() }}">Sebelumnya</a>@endif<small>Halaman {{ $results->currentPage() }} dari {{ $results->lastPage() }}</small>@if($results->hasMorePages())<a href="{{ $results->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif</nav>@endif
    @endif
  </section>
</div>
@endsection
