@extends('layouts.admin')

@section('title', 'Rincian Timeline : Admin MBG')
@section('page-title', 'Rincian Timeline')

@section('content')
@php
  $duration = static fn (float|int $milliseconds): string => $milliseconds >= 1000
    ? number_format($milliseconds / 1000, 2, ',', '.').' detik ('.number_format($milliseconds, 0, ',', '.').' ms)'
    : number_format($milliseconds, 2, ',', '.').' ms';
  $reference = $trace->loadingPermit?->permit_number ?? $trace->workPermit?->permit_number;
@endphp
<div class="page-wrap admin-page-wrap operation-trace-page">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><a href="{{ route('admin.operation-traces.index') }}">Timeline proses</a><svg><use href="#i-chevron-right"/></svg><span>Rincian</span></div><h1 class="page-title">{{ $operationLabels[$trace->operation] ?? $trace->operation }}</h1><p class="page-subtitle">Request {{ $trace->request_id }} · {{ $trace->started_at->format('d M Y, H:i:s') }} WITA</p></div>
    <a href="{{ route('admin.operation-traces.index') }}" class="admin-secondary-button"><svg><use href="#i-arrow-left"/></svg>Kembali</a>
  </div>

  <section class="operation-trace-summary">
    <div><span>Status</span><strong class="operation-trace-status is-{{ $trace->status }}">{{ $trace->status === 'success' ? 'Berhasil' : 'Gagal' }}</strong></div>
    <div><span>Total proses</span><strong>{{ $duration($trace->total_ms) }}</strong></div>
    <div><span>Surat</span><strong>{{ $reference ?: 'Belum terhubung' }}</strong></div>
    <div><span>Respons HTTP</span><strong>{{ $trace->response_status ?? 'Tidak tersedia' }}</strong></div>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Urutan waktu proses</h2><p>Durasi adalah waktu aktual dari awal hingga akhir setiap tahap pada server.</p></div><span class="admin-security-label"><svg><use href="#i-clock"/></svg>{{ count($trace->steps ?? []) }} tahap</span></div>
    <ol class="operation-step-list">
      @forelse($trace->steps ?? [] as $index => $step)
        <li class="{{ ($step['name'] ?? null) === $trace->slowest_step ? 'is-bottleneck' : '' }} {{ ($step['status'] ?? 'success') === 'failed' ? 'is-failed' : '' }}">
          <span class="operation-step-index">{{ $index + 1 }}</span>
          <div class="operation-step-content">
            <div><strong>{{ $step['name'] ?? 'Tahap tanpa nama' }}</strong>@if(($step['name'] ?? null) === $trace->slowest_step)<span class="operation-bottleneck-label">Paling lambat</span>@endif</div>
            <progress max="{{ max(1, $trace->total_ms) }}" value="{{ min($trace->total_ms, (float) ($step['duration_ms'] ?? 0)) }}">{{ $step['duration_ms'] ?? 0 }} ms</progress>
            @if(isset($step['error']))<small>Gagal pada {{ $step['error'] }}</small>@endif
          </div>
          <strong class="operation-step-duration">{{ $duration((float) ($step['duration_ms'] ?? 0)) }}</strong>
        </li>
      @empty
        <li class="operation-step-empty">Tidak ada rincian tahap yang tersimpan.</li>
      @endforelse
    </ol>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Indikator teknis</h2><p>Gunakan bagian ini untuk membedakan masalah database, upload, dan proses aplikasi.</p></div></div>
    <dl class="operation-technical-list">
      <div><dt>Query database</dt><dd>{{ $trace->query_count }} query dalam {{ $duration($trace->query_ms) }}</dd></div>
      <div><dt>File diterima</dt><dd>{{ $trace->upload_count }} file · {{ number_format($trace->upload_bytes / 1024, 1, ',', '.') }} KB</dd></div>
      <div><dt>Route</dt><dd>{{ $trace->route_name ?: 'Tidak tersedia' }}</dd></div>
      <div><dt>Aktor</dt><dd>{{ $trace->actor?->name ?? 'Pemohon tanpa login' }}</dd></div>
    </dl>
  </section>
</div>
@endsection
