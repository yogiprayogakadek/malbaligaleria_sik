@extends($layout)

@section('title', 'Bukti Akses Loading : Mal Bali Galeria')
@section('page-title', 'Bukti Akses Loading')

@section('content')
<div class="page-wrap gate-evidence-page">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb">
        <span>Loading / unloading</span>
        <svg width="14" height="14"><use href="#i-chevron-right"/></svg>
        <span>Bukti akses</span>
      </div>
      <h1 class="page-title">Riwayat akses barang</h1>
      <p class="page-subtitle">Foto, waktu, dan petugas security yang mencatat barang masuk atau keluar.</p>
    </div>
    <a href="{{ route($backRoute) }}" class="btn-secondary">
      <svg><use href="#i-arrow-left"/></svg>
      Kembali
    </a>
  </div>

  <section class="gate-evidence-summary" aria-label="Ringkasan aktivitas akses">
    <div><span>Aktivitas ditemukan</span><strong>{{ number_format($summary['total']) }}</strong></div>
    <div><span>Hari ini</span><strong>{{ number_format($summary['today']) }}</strong></div>
    <div class="is-entry"><span>Barang masuk</span><strong>{{ number_format($summary['check_in']) }}</strong></div>
    <div class="is-exit"><span>Barang keluar</span><strong>{{ number_format($summary['check_out']) }}</strong></div>
  </section>

  <section class="gate-evidence-panel">
    <form class="gate-evidence-filters" method="GET" action="{{ route($routePrefix.'.index') }}">
      <label class="gate-evidence-search">
        Cari
        <span>
          <svg><use href="#i-search"/></svg>
          <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Nomor surat, tenant, nama atau ID petugas">
        </span>
      </label>
      <label>
        Aktivitas
        <select name="event">
          <option value="">Semua aktivitas</option>
          <option value="check_in" @selected($filters['event'] === 'check_in')>Barang masuk</option>
          <option value="check_out" @selected($filters['event'] === 'check_out')>Barang keluar</option>
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
      <div class="gate-evidence-filter-actions">
        <button type="submit" class="btn-primary"><svg><use href="#i-search"/></svg>Terapkan</button>
        <a href="{{ route($routePrefix.'.index') }}" class="btn-secondary">Reset</a>
      </div>
    </form>

    <div class="gate-evidence-table-wrap">
      <table class="gate-evidence-table">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Surat / tenant</th>
            <th>Petugas security</th>
            <th>Aktivitas</th>
            <th>Bukti</th>
          </tr>
        </thead>
        <tbody>
          @forelse($events as $event)
            <tr>
              <td><strong>{{ $event->occurred_at->format('d M Y') }}</strong><small>{{ $event->occurred_at->format('H:i:s') }} WITA</small></td>
              <td>
                @if($event->loadingPermit)
                  <a href="{{ route($permitRoute, $event->loadingPermit->permit_number) }}">{{ $event->loadingPermit->permit_number }}</a>
                  <small>{{ $event->loadingPermit->tenant_name }}</small>
                @else
                  <strong>Data surat tidak tersedia</strong><small>Referensi #{{ $event->permit_id }}</small>
                @endif
              </td>
              <td><strong>{{ $event->securityOfficer?->name ?? 'Tidak tercatat' }}</strong><small>{{ $event->securityOfficer?->officer_id ?? '-' }}</small></td>
              <td><span class="gate-evidence-status gate-evidence-status--{{ $event->event === 'check_in' ? 'in' : 'out' }}">{{ $event->event === 'check_in' ? 'Barang masuk' : 'Barang keluar' }}</span></td>
              <td>
                @if($event->evidence_path)
                  <a href="{{ route($routePrefix.'.evidence', $event) }}" target="_blank" rel="noopener" class="gate-evidence-view"><svg><use href="#i-eye"/></svg>Lihat foto</a>
                @else
                  <span class="gate-evidence-missing">Tidak tersedia</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5"><x-data-table-empty icon="gallery" title="Belum ada bukti akses" message="Foto yang dikirim petugas security akan muncul di sini." /></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="gate-evidence-mobile-list">
      @forelse($events as $event)
        <article>
          <header>
            <span class="gate-evidence-status gate-evidence-status--{{ $event->event === 'check_in' ? 'in' : 'out' }}">{{ $event->event === 'check_in' ? 'Barang masuk' : 'Barang keluar' }}</span>
            <time>{{ $event->occurred_at->format('d M Y, H:i') }} WITA</time>
          </header>
          <div>
            @if($event->loadingPermit)
              <a href="{{ route($permitRoute, $event->loadingPermit->permit_number) }}">{{ $event->loadingPermit->permit_number }}</a>
              <span>{{ $event->loadingPermit->tenant_name }}</span>
            @else
              <strong>Data surat tidak tersedia</strong>
            @endif
          </div>
          <dl>
            <div><dt>Petugas</dt><dd>{{ $event->securityOfficer?->name ?? 'Tidak tercatat' }}</dd></div>
            <div><dt>Nomor ID</dt><dd>{{ $event->securityOfficer?->officer_id ?? '-' }}</dd></div>
          </dl>
          @if($event->evidence_path)
            <a href="{{ route($routePrefix.'.evidence', $event) }}" target="_blank" rel="noopener" class="gate-evidence-view"><svg><use href="#i-eye"/></svg>Lihat foto bukti</a>
          @endif
        </article>
      @empty
        <x-data-table-empty icon="gallery" title="Belum ada bukti akses" message="Foto yang dikirim petugas security akan muncul di sini." />
      @endforelse
    </div>

    @if($events->hasPages())
      <div class="dt-footer">{{ $events->links() }}</div>
    @endif
  </section>
</div>
@endsection
