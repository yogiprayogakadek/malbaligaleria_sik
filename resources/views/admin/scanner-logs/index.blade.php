@extends('layouts.admin')

@section('title', 'Log Scanner : Admin MBG')
@section('page-title', 'Log Scanner')

@php
  $resultLabels = [
    'active' => 'Valid', 'revoked' => 'Dicabut', 'expired' => 'Kedaluwarsa',
    'not_approved' => 'Belum disetujui', 'not_found' => 'Tidak ditemukan', 'invalid' => 'Token tidak valid', 'integrity_error' => 'Integritas bermasalah',
    'location_required' => 'Lokasi diperlukan', 'location_inaccurate' => 'Lokasi tidak akurat', 'location_denied' => 'Di luar area',
    'blocked' => 'Diblokir sementara',
  ];
@endphp

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Sistem</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Log scanner</span></div>
      <h1 class="page-title">Riwayat verifikasi QR</h1>
      <p class="page-subtitle">Token asli tidak disimpan. Lokasi, alamat IP, dan perangkat dicatat terenkripsi untuk pemeriksaan keamanan.</p>
    </div>
  </div>

  <div class="system-summary scanner-log-summary" aria-label="Ringkasan pemindaian">
    <div><span>Total pemindaian</span><strong>{{ number_format($totalLogs) }}</strong></div>
    <div><span>Pemindaian hari ini</span><strong>{{ number_format($todayLogs) }}</strong></div>
    <div><span>Ditolak hari ini</span><strong>{{ number_format($failedToday) }}</strong></div>
    <div class="{{ $riskEventsToday > 0 ? 'is-warning' : '' }}"><span>Sinyal risiko hari ini</span><strong>{{ number_format($riskEventsToday) }}</strong></div>
    <div class="{{ $integrityErrorsToday > 0 ? 'is-error' : '' }}"><span>Masalah integritas</span><strong>{{ number_format($integrityErrorsToday) }}</strong></div>
  </div>

  @if($repeatedSources->isNotEmpty())
    <section class="admin-panel scanner-risk-panel" aria-labelledby="scanner-risk-title">
      <div class="admin-panel-heading">
        <div><h2 id="scanner-risk-title">Aktivitas yang perlu diperiksa</h2><p>Sumber dengan sedikitnya 5 hasil berisiko dalam 24 jam terakhir.</p></div>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Alamat IP</th><th>Jumlah</th><th>Hasil dominan</th><th>Terakhir</th></tr></thead>
          <tbody>
            @foreach($repeatedSources as $source)
              @php arsort($source['results']); $dominant = array_key_first($source['results']); @endphp
              <tr>
                <td><strong>{{ $source['ip_address'] ?: 'Tidak tersedia' }}</strong></td>
                <td>{{ $source['attempts'] }} kejadian</td>
                <td>{{ $resultLabels[$dominant] ?? $dominant }}</td>
                <td>{{ $source['last_seen_at']->format('d M Y, H:i') }} WITA</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  @endif

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @error('current_password')<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $message }}</span></div>@enderror

  @if($activeBlocks->isNotEmpty())
    <section class="admin-panel scanner-block-panel" aria-labelledby="scanner-block-title">
      <div class="admin-panel-heading"><div><h2 id="scanner-block-title">Sumber diblokir</h2><p>Blokir dapat dibuka setelah kata sandi administrator dikonfirmasi.</p></div></div>
      <div class="scanner-block-list">
        @foreach($activeBlocks as $block)
          <article>
            <div><strong>{{ $block->ip_address }}</strong><span>{{ $block->trigger_count }} kejadian · sampai {{ $block->blocked_until->format('d M Y, H:i') }} WITA</span></div>
            <form action="{{ route('admin.scanner-blocks.destroy', $block) }}" method="POST">
              @csrf @method('DELETE')
              <label><span class="sr-only">Kata sandi saat ini</span><input type="password" name="current_password" placeholder="Kata sandi admin" autocomplete="current-password" required></label>
              <button type="submit" class="admin-secondary-button">Buka blokir</button>
            </form>
          </article>
        @endforeach
      </div>
    </section>
  @endif

  <section class="admin-panel audit-panel">
    <form method="GET" action="{{ route('admin.scanner-logs.index') }}" class="audit-filters">
      <label>Hasil
        <select name="result">
          <option value="">Semua hasil</option>
          @foreach($results as $option)<option value="{{ $option }}" @selected($result === $option)>{{ $resultLabels[$option] }}</option>@endforeach
        </select>
      </label>
      <label>Jenis surat
        <select name="type">
          <option value="">Semua surat</option>
          <option value="loading" @selected($type === 'loading')>Loading / unloading</option>
          <option value="work" @selected($type === 'work')>Surat izin kerja</option>
        </select>
      </label>
      <button type="submit" class="admin-primary-button"><svg><use href="#i-search"/></svg>Filter</button>
      @if($result || $type)<a href="{{ route('admin.scanner-logs.index') }}" class="admin-secondary-button">Reset</a>@endif
    </form>

    <div class="admin-table-wrap audit-table-wrap">
      <table class="admin-table audit-table">
        <thead><tr><th>Waktu</th><th>Hasil</th><th>Surat</th><th>Sumber</th><th>Lokasi</th><th>Token</th></tr></thead>
        <tbody>
          @forelse($logs as $log)
            @php
              $reference = $log->permit_type === 'loading' ? ($loadingReferences[$log->permit_id] ?? null) : ($workReferences[$log->permit_id] ?? null);
              $tone = $log->result === 'active' ? 'created' : (in_array($log->result, ['revoked', 'integrity_error'], true) ? 'deleted' : 'viewed');
            @endphp
            <tr>
              <td><strong>{{ $log->created_at->format('d M Y') }}</strong><small>{{ $log->created_at->format('H:i:s') }} WITA</small></td>
              <td><span class="audit-event audit-event--{{ $tone }}">{{ $resultLabels[$log->result] ?? $log->result }}</span><small>HTTP {{ $log->status_code }}</small></td>
              <td><strong>{{ $reference ?? 'Tidak teridentifikasi' }}</strong><small>{{ $log->permit_type ? ($log->permit_type === 'loading' ? 'Loading / unloading' : 'Surat izin kerja') : '-' }}</small></td>
              <td><strong>{{ $log->user?->name ?? 'Akses publik' }}</strong><small>{{ $log->ip_address ?? '-' }}</small></td>
              <td><strong>{{ $log->latitude && $log->longitude ? 'Tercatat' : 'Tidak tersedia' }}</strong><small>{{ $log->accuracy ? 'Akurasi '.$log->accuracy.' m' : '-' }}</small></td>
              <td><code>{{ substr($log->token_hash, 0, 12) }}...</code></td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="admin-table-empty"><svg><use href="#i-scan"/></svg><strong>Belum ada pemindaian</strong><span>Riwayat verifikasi QR akan muncul di sini.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="admin-mobile-list audit-mobile-list">
      @forelse($logs as $log)
        @php
          $reference = $log->permit_type === 'loading' ? ($loadingReferences[$log->permit_id] ?? null) : ($workReferences[$log->permit_id] ?? null);
          $tone = $log->result === 'active' ? 'created' : (in_array($log->result, ['revoked', 'integrity_error'], true) ? 'deleted' : 'viewed');
        @endphp
        <article class="audit-mobile-row">
          <div><span class="audit-event audit-event--{{ $tone }}">{{ $resultLabels[$log->result] ?? $log->result }}</span><time>{{ $log->created_at->format('d M Y, H:i') }} WITA</time></div>
          <strong>{{ $reference ?? 'Surat tidak teridentifikasi' }}</strong>
          <span>{{ $log->user?->name ?? 'Akses publik' }} · HTTP {{ $log->status_code }}</span>
        </article>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-scan"/></svg><strong>Belum ada pemindaian</strong><span>Riwayat verifikasi QR akan muncul di sini.</span></div>
      @endforelse
    </div>

    @if($logs->hasPages())
      <nav class="admin-pagination" aria-label="Navigasi log scanner">
        @if($logs->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $logs->previousPageUrl() }}">Sebelumnya</a>@endif
        <small>Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</small>
        @if($logs->hasMorePages())<a href="{{ $logs->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
      </nav>
    @endif
  </section>
</div>
@endsection
