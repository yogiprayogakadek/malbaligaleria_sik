@extends('layouts.admin')

@section('title', 'Timeline Proses : Admin MBG')
@section('page-title', 'Timeline Proses')

@section('content')
@php
  $duration = static fn (int $milliseconds): string => $milliseconds >= 1000
    ? number_format($milliseconds / 1000, 2, ',', '.').' detik'
    : $milliseconds.' ms';
@endphp
<div class="page-wrap admin-page-wrap operation-trace-page">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><span>Monitoring</span><svg><use href="#i-chevron-right"/></svg><span>Timeline proses</span></div><h1 class="page-title">Timeline proses permohonan</h1><p class="page-subtitle">Temukan tahap yang lambat tanpa membuka data pribadi atau isi dokumen pemohon.</p></div>
  </div>

  <section class="operation-trace-metrics" aria-label="Ringkasan 24 jam terakhir">
    <div><span>Proses tercatat</span><strong>{{ number_format($metrics['count'], 0, ',', '.') }}</strong><small>24 jam terakhir</small></div>
    <div><span>Rata-rata</span><strong>{{ $duration($metrics['average_ms']) }}</strong><small>seluruh pengajuan</small></div>
    <div><span>P95</span><strong>{{ $duration($metrics['p95_ms']) }}</strong><small>95% selesai di bawah nilai ini</small></div>
    <div><span>Gagal</span><strong>{{ number_format($metrics['failed'], 0, ',', '.') }}</strong><small>perlu diperiksa</small></div>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Riwayat pengajuan</h2><p>Urutan terbaru. Durasi tahap paling lambat ditampilkan agar bottleneck cepat terlihat.</p></div></div>
    <form method="GET" action="{{ route('admin.operation-traces.index') }}" class="operation-trace-filters">
      <label><span>Jenis proses</span><select name="operation"><option value="">Semua jenis</option>@foreach($operationLabels as $value => $label)@if($value !== 'permit_submission')<option value="{{ $value }}" @selected(($filters['operation'] ?? '') === $value)>{{ $label }}</option>@endif @endforeach</select></label>
      <label><span>Status</span><select name="status"><option value="">Semua status</option><option value="success" @selected(($filters['status'] ?? '') === 'success')>Berhasil</option><option value="failed" @selected(($filters['status'] ?? '') === 'failed')>Gagal</option></select></label>
      <button type="submit" class="admin-secondary-button"><svg><use href="#i-search"/></svg>Terapkan filter</button>
      @if(array_filter($filters))<a href="{{ route('admin.operation-traces.index') }}" class="admin-text-link">Reset</a>@endif
    </form>

    <div class="admin-table-wrap">
      <table class="admin-table operation-trace-table">
        <thead><tr><th>Waktu</th><th>Proses</th><th>Status</th><th>Total</th><th>Tahap paling lambat</th><th>Database</th><th>Detail</th></tr></thead>
        <tbody>
          @forelse($traces as $trace)
            @php($reference = $trace->loadingPermit?->permit_number ?? $trace->workPermit?->permit_number)
            <tr>
              <td><strong>{{ $trace->started_at->format('d M Y') }}</strong><small>{{ $trace->started_at->format('H:i:s') }} WITA</small></td>
              <td><strong>{{ $operationLabels[$trace->operation] ?? $trace->operation }}</strong><small>{{ $reference ?: 'Belum terhubung ke surat' }}</small></td>
              <td><span class="operation-trace-status is-{{ $trace->status }}">{{ $trace->status === 'success' ? 'Berhasil' : 'Gagal' }}</span></td>
              <td><strong>{{ $duration($trace->total_ms) }}</strong><small>{{ $trace->upload_count }} file · {{ number_format($trace->upload_bytes / 1024, 1, ',', '.') }} KB</small></td>
              <td><strong>{{ $trace->slowest_step ?: 'Tidak tersedia' }}</strong><small>{{ $duration($trace->slowest_step_ms) }}</small></td>
              <td><strong>{{ $trace->query_count }} query</strong><small>{{ $duration($trace->query_ms) }}</small></td>
              <td><a class="admin-secondary-button operation-trace-detail-link" href="{{ route('admin.operation-traces.show', $trace) }}">Lihat rincian</a></td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="admin-table-empty"><svg><use href="#i-clock"/></svg><strong>Belum ada proses yang tercatat</strong><span>Timeline muncul setelah pengajuan Loading/Unloading, Surat Izin Kerja, atau Surat Izin Event dikirim.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($traces->hasPages())<div class="admin-pagination">{{ $traces->links() }}</div>@endif
  </section>
</div>
@endsection
