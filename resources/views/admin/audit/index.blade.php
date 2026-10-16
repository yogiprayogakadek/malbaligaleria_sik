@extends('layouts.admin')

@section('title', 'Audit Log : Admin MBG')
@section('page-title', 'Audit Log')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Audit log</span></div>
      <h1 class="page-title">Riwayat perubahan sistem</h1>
      <p class="page-subtitle">Catatan ini bersifat hanya-baca dan tidak dapat diubah melalui aplikasi.</p>
    </div>
  </div>

  <div class="system-summary" aria-label="Ringkasan audit">
    <div><span>Total catatan</span><strong>{{ number_format($totalLogs) }}</strong></div>
    <div><span>Aktivitas hari ini</span><strong>{{ number_format($todayLogs) }}</strong></div>
  </div>

  <section class="admin-panel audit-panel">
    <form method="GET" action="{{ route('admin.audit.index') }}" class="audit-filters">
      <label>Aktivitas
        <select name="event">
          <option value="">Semua aktivitas</option>
          <option value="created" @selected($event === 'created')>Data dibuat</option>
          <option value="updated" @selected($event === 'updated')>Data diubah</option>
          <option value="deleted" @selected($event === 'deleted')>Data dihapus</option>
          <option value="viewed" @selected($event === 'viewed')>Dokumen dilihat</option>
          <option value="retried" @selected($event === 'retried')>Antrean dicoba ulang</option>
        </select>
      </label>
      <label>Modul
        <select name="model">
          <option value="">Semua modul</option>
          @foreach(['user' => 'Akun pengguna', 'loading' => 'Loading barang', 'work' => 'Surat izin kerja', 'schedule' => 'Jam operasional', 'exception' => 'Jadwal khusus', 'scanner' => 'Scanner', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'sla' => 'SLA antrean', 'retention' => 'Retensi dokumen', 'queue' => 'Antrean gagal'] as $value => $label)
            <option value="{{ $value }}" @selected($model === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <button type="submit" class="admin-primary-button"><svg><use href="#i-search"/></svg>Filter</button>
      @if($event || $model)<a href="{{ route('admin.audit.index') }}" class="admin-secondary-button">Reset</a>@endif
    </form>

    <div class="admin-table-wrap audit-table-wrap">
      <table class="admin-table audit-table">
        <thead><tr><th>Waktu</th><th>Pelaku</th><th>Aktivitas</th><th>Modul</th><th>Rincian</th><th>Alamat IP</th></tr></thead>
        <tbody>
          @forelse($logs as $log)
            @php
              $changes = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? [])));
              $eventLabel = ['created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus', 'viewed' => 'Dilihat', 'retried' => 'Dicoba ulang'][$log->event] ?? ucfirst($log->event);
            @endphp
            <tr>
              <td><strong>{{ $log->created_at->format('d M Y') }}</strong><small>{{ $log->created_at->format('H:i:s') }} WITA</small></td>
              <td><strong>{{ $log->actor?->name ?? 'Sistem' }}</strong><small>{{ $log->actor?->role ?? 'otomatis' }}</small></td>
              <td><span class="audit-event audit-event--{{ $log->event }}">{{ $eventLabel }}</span></td>
              <td><strong>{{ $modelLabels[$log->auditable_type] ?? class_basename($log->auditable_type) }}</strong><small>ID {{ $log->auditable_id }}</small></td>
              <td>
                <details class="audit-details">
                  <summary>{{ count($changes) }} field</summary>
                  <dl>
                    @foreach($changes as $field)
                      <div><dt>{{ str_replace('_', ' ', $field) }}</dt><dd>{{ json_encode(($log->old_values ?? [])[$field] ?? null, JSON_UNESCAPED_UNICODE) }} → {{ json_encode(($log->new_values ?? [])[$field] ?? null, JSON_UNESCAPED_UNICODE) }}</dd></div>
                    @endforeach
                  </dl>
                </details>
              </td>
              <td><code>{{ $log->ip_address ?? '-' }}</code></td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="admin-table-empty"><svg><use href="#i-file"/></svg><strong>Belum ada catatan audit</strong><span>Aktivitas perubahan data akan muncul di sini.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="admin-mobile-list audit-mobile-list">
      @forelse($logs as $log)
        @php $eventLabel = ['created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus', 'viewed' => 'Dilihat', 'retried' => 'Dicoba ulang'][$log->event] ?? ucfirst($log->event); @endphp
        <article class="audit-mobile-row">
          <div><span class="audit-event audit-event--{{ $log->event }}">{{ $eventLabel }}</span><time>{{ $log->created_at->format('d M Y, H:i') }} WITA</time></div>
          <strong>{{ $modelLabels[$log->auditable_type] ?? class_basename($log->auditable_type) }} · ID {{ $log->auditable_id }}</strong>
          <span>{{ $log->actor?->name ?? 'Sistem' }}</span>
        </article>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-file"/></svg><strong>Belum ada catatan audit</strong><span>Aktivitas perubahan data akan muncul di sini.</span></div>
      @endforelse
    </div>

    @if($logs->hasPages())
      <nav class="admin-pagination" aria-label="Navigasi halaman audit">
        @if($logs->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $logs->previousPageUrl() }}">Sebelumnya</a>@endif
        <small>Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</small>
        @if($logs->hasMorePages())<a href="{{ $logs->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
      </nav>
    @endif
  </section>
</div>
@endsection
