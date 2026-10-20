@extends('layouts.admin')

@section('title', 'Template Pesan - Admin MBG')
@section('page-title', 'Template Pesan')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Komunikasi</span><svg><use href="#i-chevron-right"/></svg><span>Template pesan</span></div>
      <h1 class="page-title">Template notifikasi</h1>
      <p class="page-subtitle">Kelola teks operasional dan variabel yang boleh digunakan.</p>
    </div>
    <a href="{{ route('admin.message-templates.create') }}" class="admin-primary-button"><svg><use href="#i-plus"/></svg>Tambah template</a>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif

  <section class="admin-panel">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Template</th><th>Kunci</th><th>Kanal</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          @forelse($templates as $template)
            <tr>
              <td><strong>{{ $template->name }}</strong><span>{{ Str::limit($template->subject ?: $template->body, 70) }}</span></td>
              <td><code>{{ $template->key }}</code></td>
              <td>{{ strtoupper($template->channel) }}</td>
              <td><span class="admin-status admin-status--{{ $template->is_active ? 'approved' : 'rejected' }}">{{ $template->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td><a href="{{ route('admin.message-templates.edit', $template) }}" class="btn-dt-action btn-dt-action--view">Edit</a></td>
            </tr>
          @empty
            <tr><td colspan="5"><div class="admin-table-empty"><svg><use href="#i-edit"/></svg><strong>Belum ada template</strong><span>Tambahkan template untuk menyiapkan isi pesan operasional.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
