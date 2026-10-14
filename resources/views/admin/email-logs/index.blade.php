@extends('layouts.admin')

@section('title', 'Log Email : Admin MBG')
@section('page-title', 'Log Email')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Log email</span></div>
      <h1 class="page-title">Riwayat pengiriman email</h1>
      <p class="page-subtitle">Alamat penerima disamarkan. Isi email dan kredensial SMTP tidak disimpan di log.</p>
    </div>
  </div>

  @if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif

  <section class="admin-panel email-log-panel">
    <form method="GET" action="{{ route('admin.email-logs.index') }}" class="audit-filters">
      <label>Status
        <select name="status">
          <option value="">Semua status</option>
          <option value="sent" @selected($status === 'sent')>Terkirim</option>
          <option value="failed" @selected($status === 'failed')>Gagal</option>
          <option value="retrying" @selected($status === 'retrying')>Diantrikan ulang</option>
        </select>
      </label>
      <button type="submit" class="admin-primary-button"><svg><use href="#i-search"/></svg>Filter</button>
      @if($status)<a href="{{ route('admin.email-logs.index') }}" class="admin-secondary-button">Reset</a>@endif
    </form>

    <div class="admin-table-wrap email-log-table-wrap">
      <table class="admin-table email-log-table">
        <thead><tr><th>Waktu</th><th>Penerima</th><th>Permohonan</th><th>Jenis pesan</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
          @forelse($logs as $log)
            <tr>
              <td><strong>{{ $log->created_at->format('d M Y') }}</strong><small>{{ $log->created_at->format('H:i:s') }} WITA</small></td>
              <td><strong>{{ $log->maskedRecipient() }}</strong><small>Percobaan {{ $log->attempts }}</small></td>
              <td><strong>{{ $log->emailable?->permit_number ?? 'Data tidak tersedia' }}</strong><small>{{ class_basename($log->emailable_type) }}</small></td>
              <td><strong>{{ $log->subject ?: $log->event_key ?: 'Pembaruan status' }}</strong></td>
              <td>
                <span class="email-log-status email-log-status--{{ $log->status }}">{{ ['sent' => 'Terkirim', 'failed' => 'Gagal', 'retrying' => 'Diantrikan'][$log->status] ?? ucfirst($log->status) }}</span>
                @if($log->failure_reason)<small title="{{ $log->failure_reason }}">{{ Str::limit($log->failure_reason, 70) }}</small>@endif
              </td>
              <td>
                @if($log->status === 'failed')
                  <form method="POST" action="{{ route('admin.email-logs.retry', $log) }}" onsubmit="return confirm('Kirim ulang email ini?')">
                    @csrf
                    <button type="submit" class="admin-secondary-button">Kirim ulang</button>
                  </form>
                @else
                  <span class="email-log-no-action">-</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="admin-table-empty"><svg><use href="#i-mail"/></svg><strong>Belum ada log email</strong><span>Hasil pengiriman email pemohon akan muncul di sini.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="admin-mobile-list email-log-mobile-list">
      @forelse($logs as $log)
        <article class="email-log-mobile-row">
          <div><strong>{{ $log->emailable?->permit_number ?? 'Data tidak tersedia' }}</strong><span class="email-log-status email-log-status--{{ $log->status }}">{{ ['sent' => 'Terkirim', 'failed' => 'Gagal', 'retrying' => 'Diantrikan'][$log->status] ?? ucfirst($log->status) }}</span></div>
          <p>{{ $log->subject ?: $log->event_key ?: 'Pembaruan status' }}</p>
          <small>{{ $log->maskedRecipient() }} · {{ $log->created_at->format('d M Y, H:i') }} WITA</small>
          @if($log->status === 'failed')
            <form method="POST" action="{{ route('admin.email-logs.retry', $log) }}" onsubmit="return confirm('Kirim ulang email ini?')">
              @csrf
              <button type="submit" class="admin-secondary-button">Kirim ulang</button>
            </form>
          @endif
        </article>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-mail"/></svg><strong>Belum ada log email</strong><span>Hasil pengiriman email pemohon akan muncul di sini.</span></div>
      @endforelse
    </div>

    @if($logs->hasPages())
      <nav class="admin-pagination" aria-label="Navigasi halaman log email">
        @if($logs->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $logs->previousPageUrl() }}">Sebelumnya</a>@endif
        <small>Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</small>
        @if($logs->hasMorePages())<a href="{{ $logs->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
      </nav>
    @endif
  </section>
</div>
@endsection
