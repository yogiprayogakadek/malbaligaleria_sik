@extends('layouts.admin')

@section('title', 'Pusat Pengiriman : Admin MBG')
@section('page-title', 'Pusat Pengiriman')

@section('content')
<div class="page-wrap admin-page-wrap communication-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb">
        <span>Komunikasi</span>
        <svg><use href="#i-chevron-right"/></svg>
        <span>Pusat pengiriman</span>
      </div>
      <h1 class="page-title">Riwayat pengiriman</h1>
      <p class="page-subtitle">Pantau email, WhatsApp, dan notifikasi akun dari satu halaman.</p>
    </div>
  </div>

  @if(session('success'))
    <div class="permit-alert permit-alert--success">
      <svg><use href="#i-check"/></svg>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  <section class="communication-summary" aria-label="Ringkasan pengiriman berdasarkan filter">
    <div>
      <span>Aktivitas ditemukan</span>
      <strong>{{ number_format($summary['total']) }}</strong>
    </div>
    <div class="is-success">
      <span>Terkirim atau dibaca</span>
      <strong>{{ number_format($summary['successful']) }}</strong>
    </div>
    <div class="is-pending">
      <span>Menunggu tindakan</span>
      <strong>{{ number_format($summary['pending']) }}</strong>
    </div>
    <div class="is-failed">
      <span>Gagal atau dilewati</span>
      <strong>{{ number_format($summary['failed']) }}</strong>
    </div>
  </section>

  <section class="admin-panel communication-panel">
    <form class="communication-filters" method="GET">
      <label class="communication-filter-search">
        Cari
        <span class="communication-search-input">
          <svg><use href="#i-search"/></svg>
          <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Nomor surat, penerima, atau pesan">
        </span>
      </label>

      <label>
        Kanal
        <select name="channel">
          <option value="">Semua kanal</option>
          <option value="email" @selected($filters['channel'] === 'email')>Email</option>
          <option value="whatsapp" @selected($filters['channel'] === 'whatsapp')>WhatsApp</option>
          <option value="app" @selected($filters['channel'] === 'app')>Notifikasi aplikasi</option>
        </select>
      </label>

      <label>
        Status
        <select name="status">
          <option value="">Semua status</option>
          @foreach([
            'sent' => 'Diterima kanal',
            'read' => 'Dibaca',
            'unread' => 'Belum dibaca',
            'queued' => 'Mengantre',
            'processing' => 'Diproses',
            'retrying' => 'Dikirim ulang',
            'failed' => 'Gagal',
            'skipped' => 'Dilewati',
          ] as $value => $label)
            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>

      <label>
        Jenis permohonan
        <select name="permit_type">
          <option value="">Semua jenis</option>
          <option value="loading" @selected($filters['permit_type'] === 'loading')>Loading / unloading</option>
          <option value="work" @selected($filters['permit_type'] === 'work')>Surat izin kerja</option>
          <option value="event" @selected($filters['permit_type'] === 'event')>Surat izin event</option>
        </select>
      </label>

      <label>
        Dari tanggal
        <input type="date" name="date_from" value="{{ $filters['date_from'] }}">
      </label>

      <label>
        Sampai tanggal
        <input type="date" name="date_to" value="{{ $filters['date_to'] }}">
      </label>

      <div class="communication-filter-actions">
        <button class="admin-primary-button" type="submit">
          <svg><use href="#i-search"/></svg>
          Terapkan
        </button>
        <a class="admin-secondary-button" href="{{ route('admin.communication-logs.index') }}">Reset</a>
      </div>
    </form>

    <div class="communication-note">
      <svg><use href="#i-info"/></svg>
      <p>Status <strong>Diterima kanal</strong> berarti server email atau WhatsApp menerima pesan. Status <strong>Dibaca</strong> hanya tersedia untuk notifikasi di dalam aplikasi.</p>
    </div>

    <div class="admin-table-wrap communication-table-wrap">
      <table class="admin-table communication-table">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Kanal</th>
            <th>Penerima</th>
            <th>Pesan</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($logs as $log)
            <tr>
              <td>
                <strong>{{ $log['created_at']->format('d M Y') }}</strong>
                <small>{{ $log['created_at']->format('H:i:s') }} WITA</small>
              </td>
              <td>
                <span class="communication-channel communication-channel--{{ $log['channel'] }}">{{ $log['channel_label'] }}</span>
              </td>
              <td>
                <strong>{{ $log['recipient'] }}</strong>
                @if($log['attempts'] !== null)
                  <small>{{ $log['attempts'] }} percobaan</small>
                @endif
              </td>
              <td>
                <strong>{{ $log['subject'] ?: 'Pembaruan' }}</strong>
                <small>{{ $log['permit_label'] }}{{ $log['reference'] ? ' · '.$log['reference'] : '' }}</small>
                @if($log['failure'])
                  <details class="communication-error">
                    <summary>Lihat alasan gagal</summary>
                    <p>{{ $log['failure'] }}</p>
                  </details>
                @endif
              </td>
              <td>
                <span class="communication-status communication-status--{{ $log['status'] }}">{{ $log['status_label'] }}</span>
              </td>
              <td>
                @include('admin.communication-logs.partials.actions', ['log' => $log])
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6">
                <x-data-table-empty icon="mail" title="Belum ada aktivitas" message="Ubah filter atau tunggu pengiriman berikutnya masuk."/>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="admin-mobile-list communication-mobile-list">
      @forelse($logs as $log)
        <article class="communication-mobile-row">
          <header>
            <span class="communication-channel communication-channel--{{ $log['channel'] }}">{{ $log['channel_label'] }}</span>
            <span class="communication-status communication-status--{{ $log['status'] }}">{{ $log['status_label'] }}</span>
          </header>
          <div>
            <strong>{{ $log['subject'] ?: 'Pembaruan' }}</strong>
            <p>{{ $log['permit_label'] }}{{ $log['reference'] ? ' · '.$log['reference'] : '' }}</p>
          </div>
          <dl>
            <div><dt>Penerima</dt><dd>{{ $log['recipient'] }}</dd></div>
            <div><dt>Waktu</dt><dd>{{ $log['created_at']->format('d M Y, H:i') }} WITA</dd></div>
            @if($log['attempts'] !== null)
              <div><dt>Percobaan</dt><dd>{{ $log['attempts'] }} kali</dd></div>
            @endif
          </dl>
          @if($log['failure'])
            <details class="communication-error">
              <summary>Lihat alasan gagal</summary>
              <p>{{ $log['failure'] }}</p>
            </details>
          @endif
          @include('admin.communication-logs.partials.actions', ['log' => $log])
        </article>
      @empty
        <x-data-table-empty icon="mail" title="Belum ada aktivitas" message="Ubah filter atau tunggu pengiriman berikutnya masuk."/>
      @endforelse
    </div>

    <div class="dt-footer">{{ $logs->links() }}</div>
  </section>
</div>
@endsection
