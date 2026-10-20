@extends('layouts.admin')

@section('title', 'Kategori Izin Kerja : Admin MBG')
@section('page-title', 'Kategori Izin Kerja')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header"><div><div class="page-breadcrumb"><span>Perizinan</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Kategori izin kerja</span></div><h1 class="page-title">Kategori dan divisi pemeriksa</h1><p class="page-subtitle">Atur tujuan pemeriksaan dan durasi maksimum setiap kategori Surat Izin Kerja.</p></div><a href="{{ route('admin.work-categories.create') }}" class="admin-primary-button"><svg><use href="#i-plus"/></svg>Tambah kategori</a></div>
  @if(session('success'))<div class="permit-alert permit-alert--success"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  <section class="admin-panel"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Kategori</th><th>Kode</th><th>Divisi</th><th>Durasi</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    @foreach($categories as $category)<tr><td><strong>{{ $category->name }}</strong></td><td><code>{{ $category->code }}</code></td><td><span class="badge-tag">{{ $category->division }}</span></td><td>{{ $category->max_duration_days }} hari</td><td><span class="admin-status admin-status--{{ $category->is_active ? 'active' : 'inactive' }}">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td><a href="{{ route('admin.work-categories.edit', $category) }}" class="btn-dt-action btn-dt-action--view">Edit</a></td></tr>@endforeach
  </tbody></table></div></section>
</div>
@endsection
