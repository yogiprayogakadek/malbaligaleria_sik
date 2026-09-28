@extends('layouts.validator')

@section('title', 'Verifikasi Loading Barang : Dashboard TR : Mal Bali Galeria')
@section('page-title', 'Dashboard Validator TR')
@section('page-description', 'Kelola antrean izin loading dan unloading tenant')

@php
  $avatarColors = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#4f46e5', '#ea580c'];
  $getInitials = static function ($str) {
    $words = preg_split('/\s+/', trim($str));
    $in = '';
    foreach (array_slice($words, 0, 2) as $w) {
      $in .= strtoupper(substr($w, 0, 1));
    }
    return $in ?: 'TR';
  };
@endphp

@section('content')
<div class="page-wrap">

  <div class="page-header">
    <div>
      <div class="page-breadcrumb">
        <span>Tenant Relationship</span>
        <svg width="14" height="14"><use href="#i-chevron-right"/></svg>
        <span>Dashboard</span>
      </div>
      <h1 class="page-title">Antrean verifikasi loading</h1>
      <p class="page-subtitle">Periksa data pemohon, dokumen identitas, dan jadwal pergerakan barang sebelum mengambil keputusan.</p>
    </div>
    <a href="{{ route('tr.work-permits.index') }}" class="btn-secondary"><svg><use href="#i-wrench"/></svg>Antrean Izin Kerja</a>
  </div>

  @if($status === 'all')
    <section class="validator-metrics" aria-label="Ringkasan permohonan">
      <a href="{{ route('tr.index', ['status' => 'all']) }}" class="validator-metric validator-metric--all">
        <span class="validator-metric-icon"><svg><use href="#i-file"/></svg></span>
        <span class="validator-metric-copy"><span>Total permohonan</span><strong>{{ $counts['all'] }}</strong></span>
      </a>
      <a href="{{ route('tr.index', ['status' => 'pending']) }}" class="validator-metric validator-metric--pending">
        <span class="validator-metric-icon"><svg><use href="#i-clock"/></svg></span>
        <span class="validator-metric-copy"><span>Perlu diperiksa</span><strong>{{ $counts['pending'] }}</strong></span>
      </a>
      <a href="{{ route('tr.index', ['status' => 'approved']) }}" class="validator-metric validator-metric--approved">
        <span class="validator-metric-icon"><svg><use href="#i-check"/></svg></span>
        <span class="validator-metric-copy"><span>Disetujui</span><strong>{{ $counts['approved'] }}</strong></span>
      </a>
      <a href="{{ route('tr.index', ['status' => 'rejected']) }}" class="validator-metric validator-metric--rejected">
        <span class="validator-metric-icon"><svg><use href="#i-info"/></svg></span>
        <span class="validator-metric-copy"><span>Ditolak</span><strong>{{ $counts['rejected'] }}</strong></span>
      </a>
    </section>
  @endif

  {{-- Flash message --}}
  @if(session('success'))
    <div class="permit-alert permit-alert--success" role="alert">
      <svg><use href="#i-check"/></svg>
      <span>{{ session('success') }}</span>
    </div>
  @endif
  @if(session('info'))
    <div class="permit-alert permit-alert--info" role="alert">
      <svg><use href="#i-info"/></svg>
      <span>{{ session('info') }}</span>
    </div>
  @endif

  {{-- ─── MODERN DATA TABLE CONTAINER ────────────────────────────────── --}}
  <div class="dt-card" id="validatorPermitResults" data-validator-queue-status="{{ $status }}">

    {{-- Toolbar Header --}}
    <div class="dt-toolbar">
      <div class="dt-header-left">
        <h2 class="dt-title">Daftar Permohonan</h2>
        <span class="dt-count">&middot; <span id="dtRecordsCount">{{ $permits->total() }}</span> data</span>
      </div>

      <div class="dt-header-right">
        {{-- Per-page selector --}}
        <div class="dt-perpage-wrap">
          <label for="dtPerPage" class="dt-perpage-label">Tampilkan</label>
          <select id="dtPerPage" class="dt-select dt-perpage-select"
                  data-base-url="{{ route('tr.index') }}"
                  data-status="{{ $status }}"
                  aria-label="Jumlah data per halaman">
            @foreach([5, 10, 20, 50, 100, 'all'] as $opt)
              <option value="{{ $opt }}" {{ (string)$perPageRaw === (string)$opt ? 'selected' : '' }}>
                {{ $opt === 'all' ? 'Semua' : $opt }}
              </option>
            @endforeach
          </select>
          <span class="dt-perpage-label">data</span>
        </div>

        {{-- Search input --}}
        <div class="dt-search-wrap">
          <svg><use href="#i-search"/></svg>
          <input type="text" id="dtSearchInput" class="dt-search-input"
                 placeholder="Cari tenant / nomor surat..."
                 aria-label="Cari permohonan">
        </div>

        {{-- Direction filter --}}
        <select id="dtFilterDirection" class="dt-select" aria-label="Filter arah pergerakan">
          <option value="">Semua arah</option>
          <option value="in">Barang Masuk</option>
          <option value="out">Barang Keluar</option>
          <option value="both">Masuk & Keluar</option>
        </select>

        {{-- Density toggle --}}
        <div class="dt-density-group" role="group" aria-label="Kerapatan tabel">
          <button type="button" class="dt-density-btn active" id="btnDensityComfortable" data-density="comfortable">Normal</button>
          <button type="button" class="dt-density-btn" id="btnDensityCompact" data-density="compact">Ringkas</button>
        </div>

        {{-- Export button --}}
        <button type="button" class="dt-btn-export" id="dtExportBtn" title="Unduh CSV">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
          </svg>
          <span>Unduh CSV</span>
        </button>
      </div>
    </div>

    @if($permits->isEmpty())
      <div class="tr-empty">
        <svg width="40" height="40"><use href="#i-box"/></svg>
        <p>Tidak ada permohonan {{ $status === 'pending' ? 'yang menunggu verifikasi' : ($status === 'approved' ? 'yang disetujui' : ($status === 'rejected' ? 'yang ditolak' : '')) }}.</p>
      </div>
    @else

      {{-- ─── DESKTOP TABLE ────────────────────────────────────────── --}}
      <div class="dt-table-responsive">
        <table class="dt-table" id="dtTable">
          <thead>
            <tr>
              <th class="sortable" data-sort="name">
                TENANT / PEMOHON <span class="dt-sort-icon">⇅</span>
              </th>
              <th class="sortable" data-sort="permit">
                NOMOR SURAT <span class="dt-sort-icon">⇅</span>
              </th>
              <th class="sortable" data-sort="direction">
                ARAH <span class="dt-sort-icon">⇅</span>
              </th>
              <th class="sortable" data-sort="status">
                STATUS <span class="dt-sort-icon">⇅</span>
              </th>
              <th class="sortable" data-sort="date">
                PERIODE / TANGGAL <span class="dt-sort-icon">⇅</span>
              </th>
              <th>TINDAKAN</th>
            </tr>
          </thead>
          <tbody>
            @foreach($permits as $index => $permit)
            @php
              $colorIndex = abs(crc32($permit->tenant_name)) % count($avatarColors);
              $bgColor = $avatarColors[$colorIndex];
              $initials = $getInitials($permit->tenant_name);
            @endphp
            <tr class="dt-row status-{{ $permit->status }}"
                id="row-{{ $permit->id }}"
                data-name="{{ strtolower($permit->tenant_name . ' ' . $permit->applicant_name) }}"
                data-permit="{{ strtolower($permit->permit_number) }}"
                data-direction="{{ $permit->direction }}"
                data-status="{{ $permit->status }}"
                data-date="{{ $permit->start_date->format('Y-m-d') }}">
              
              {{-- Name & Avatar --}}
              <td>
                <div class="dt-user-cell">
                  <div class="dt-avatar" style="background-color: {{ $bgColor }};">
                    {{ $initials }}
                  </div>
                  <div class="dt-user-info">
                    <span class="dt-user-name">{{ $permit->tenant_name }}</span>
                    <span class="dt-user-sub">{{ $permit->applicant_name }} · {{ $permit->applicant_phone }}</span>
                  </div>
                </div>
              </td>

              {{-- Permit Number --}}
              <td>
                <span class="dt-mono-ref">{{ $permit->permit_number }}</span>
                <div class="dt-user-sub">{{ $permit->created_at->format('d M Y, H:i') }}</div>
              </td>

              {{-- Role / Direction --}}
              <td>
                <span class="dt-badge-role role-{{ $permit->direction }}">
                  {{ $permit->direction_label }}
                </span>
              </td>

              {{-- Status --}}
              <td>
                @if($permit->status === 'approved')
                  <span class="dt-badge-status dt-status-approved">
                    <span class="dt-dot"></span> Disetujui
                  </span>
                @elseif($permit->status === 'rejected')
                  <span class="dt-badge-status dt-status-rejected">
                    <span class="dt-dot"></span> Ditolak
                  </span>
                @else
                  <span class="dt-badge-status dt-status-pending">
                    <span class="dt-dot"></span> Menunggu
                  </span>
                @endif
              </td>

              {{-- Tanggal --}}
              <td>
                <span style="font-weight: 500; color: #334155;">
                  {{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}
                </span>
                <div class="dt-user-sub">{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}</div>
              </td>

              {{-- Actions --}}
              <td>
                <div class="dt-actions-cell">
                  @if($permit->status === 'pending')
                    <a href="{{ route('tr.show', $permit->permit_number) }}" class="btn-dt-action btn-dt-action--primary">
                      Periksa
                    </a>
                  @else
                    <a href="{{ route('tr.show', $permit->permit_number) }}" class="btn-dt-action btn-dt-action--view">
                      Lihat
                    </a>
                  @endif

                  <button type="button" class="btn-dt-detail"
                          data-target="expand-{{ $permit->id }}"
                          aria-label="Rincian cepat">
                    <span>Detail</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                  </button>
                </div>
              </td>
            </tr>

            {{-- Expandable Row (Desktop) --}}
            <tr class="dt-expand-row" id="expand-{{ $permit->id }}">
              <td colspan="6">
                <div class="dt-expand-content">
                  <div class="dt-expand-item">
                    <span class="dt-expand-label">PIC & Kontak</span>
                    <span class="dt-expand-val">{{ $permit->applicant_name }} ({{ $permit->applicant_phone }})</span>
                  </div>
                  <div class="dt-expand-item">
                    <span class="dt-expand-label">Email PIC</span>
                    <span class="dt-expand-val">{{ $permit->applicant_email ?: '-' }}</span>
                  </div>
                  <div class="dt-expand-item">
                    <span class="dt-expand-label">Rincian Barang</span>
                    <span class="dt-expand-val">{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }} — {{ $permit->item_description ?: 'Tidak ada catatan barang' }}</span>
                  </div>
                  <div class="dt-expand-item">
                    <span class="dt-expand-label">Dokumen Identitas</span>
                    <span class="dt-expand-val">
                      <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('tr.id-doc', now()->addMinutes(5), ['documentToken' => $permit->document_token]) }}" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline;">
                        Lihat {{ strtoupper($permit->id_doc_type) }}
                      </a>
                    </span>
                  </div>
                  @if($permit->review_notes)
                    <div class="dt-expand-item" style="grid-column: 1 / -1;">
                      <span class="dt-expand-label">Catatan Reviewer</span>
                      <span class="dt-expand-val" style="color: #475569; font-weight: 500;">{{ $permit->review_notes }}</span>
                    </div>
                  @endif
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- ─── MOBILE CARD VIEW (< 768px) ───────────────────────────── --}}
      <div class="dt-mobile-list" id="dtMobileList">
        @foreach($permits as $permit)
        @php
          $colorIndex = abs(crc32($permit->tenant_name)) % count($avatarColors);
          $bgColor = $avatarColors[$colorIndex];
          $initials = $getInitials($permit->tenant_name);
        @endphp
        <div class="dt-mobile-card status-{{ $permit->status }}"
             data-name="{{ strtolower($permit->tenant_name . ' ' . $permit->applicant_name) }}"
             data-permit="{{ strtolower($permit->permit_number) }}"
             data-direction="{{ $permit->direction }}"
             data-status="{{ $permit->status }}">
          
          <div class="dt-mobile-card-header">
            <div class="dt-avatar" style="background-color: {{ $bgColor }};">
              {{ $initials }}
            </div>
            <div class="dt-mobile-card-main">
              <div class="dt-mobile-card-title-row">
                <span class="dt-mobile-card-title">{{ $permit->tenant_name }}</span>
                @if($permit->status === 'approved')
                  <span class="dt-badge-status dt-status-approved"><span class="dt-dot"></span> Disetujui</span>
                @elseif($permit->status === 'rejected')
                  <span class="dt-badge-status dt-status-rejected"><span class="dt-dot"></span> Ditolak</span>
                @else
                  <span class="dt-badge-status dt-status-pending"><span class="dt-dot"></span> Menunggu</span>
                @endif
              </div>
              <div class="dt-mobile-card-meta">
                <span class="dt-mono-ref">{{ $permit->permit_number }}</span>
                <span class="dt-badge-role role-{{ $permit->direction }}">{{ $permit->direction_label }}</span>
              </div>
            </div>
          </div>

          {{-- Mobile Card Footer --}}
          <div class="dt-mobile-card-footer">
            <button type="button" class="dt-btn-mobile-detail" data-toggle-mobile="mob-detail-{{ $permit->id }}">
              <span>Detail</span>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
            </button>

            @if($permit->status === 'pending')
              <a href="{{ route('tr.show', $permit->permit_number) }}" class="btn-dt-action btn-dt-action--primary" style="padding: 7px 16px;">
                Periksa &amp; Proses
              </a>
            @else
              <a href="{{ route('tr.show', $permit->permit_number) }}" class="btn-dt-action btn-dt-action--view" style="padding: 7px 16px;">
                Lihat Detail
              </a>
            @endif
          </div>

          {{-- Mobile Expandable Accordion --}}
          <div class="dt-mobile-detail-panel" id="mob-detail-{{ $permit->id }}">
            <dl class="dt-mobile-detail-grid">
              <div class="dt-mobile-detail-row">
                <dt>PIC Pemohon</dt>
                <dd>{{ $permit->applicant_name }}</dd>
              </div>
              <div class="dt-mobile-detail-row">
                <dt>Nomor HP</dt>
                <dd><a href="tel:{{ $permit->applicant_phone }}" style="color: #2563eb; text-decoration: none;">{{ $permit->applicant_phone }}</a></dd>
              </div>
              <div class="dt-mobile-detail-row">
                <dt>Periode</dt>
                <dd>{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</dd>
              </div>
              <div class="dt-mobile-detail-row">
                <dt>Jumlah Barang</dt>
                <dd>{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}</dd>
              </div>
              <div class="dt-mobile-detail-row">
                <dt>Keterangan</dt>
                <dd>{{ $permit->item_description ?: '-' }}</dd>
              </div>
              <div class="dt-mobile-detail-row">
                <dt>Dokumen ID</dt>
                <dd>
                  <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('tr.id-doc', now()->addMinutes(5), ['documentToken' => $permit->document_token]) }}" target="_blank" rel="noopener noreferrer" style="color: #2563eb;">
                    Buka {{ strtoupper($permit->id_doc_type) }}
                  </a>
                </dd>
              </div>
              @if($permit->review_notes)
                <div class="dt-mobile-detail-row">
                  <dt>Catatan pemeriksaan</dt>
                  <dd style="color: #b45309;">{{ $permit->review_notes }}</dd>
                </div>
              @endif
              <div class="dt-mobile-detail-row">
                <dt>Diajukan</dt>
                <dd>{{ $permit->created_at->format('d M Y, H:i') }}</dd>
              </div>
            </dl>

            <a href="{{ route('tr.show', $permit->permit_number) }}" class="btn-primary" style="width: 100%; justify-content: center; height: 38px; font-size: 13px;">
              {{ $permit->status === 'pending' ? 'Buka Halaman Pemeriksaan' : 'Lihat Detail Permohonan' }}
            </a>
          </div>

        </div>
        @endforeach
      </div>

      {{-- ─── FOOTER & PAGINATION ──────────────────────────────────── --}}
      <div class="dt-footer">
        <div class="dt-footer-info">
          Menampilkan
          <strong>{{ $permits->firstItem() ?? 0 }}</strong>–<strong>{{ $permits->lastItem() ?? $permits->count() }}</strong>
          dari <strong>{{ $permits->total() }}</strong> permohonan
        </div>

        <div class="dt-pagination-nav" role="navigation" aria-label="Navigasi halaman">
          {{-- First Page --}}
          @if ($permits->onFirstPage())
            <span class="dt-page-btn disabled" aria-disabled="true" title="Halaman pertama">«</span>
          @else
            <a href="{{ $permits->url(1) }}" class="dt-page-btn" title="Halaman pertama">«</a>
          @endif

          {{-- Prev Page --}}
          @if ($permits->onFirstPage())
            <span class="dt-page-btn disabled" aria-disabled="true" title="Sebelumnya">‹ Prev</span>
          @else
            <a href="{{ $permits->previousPageUrl() }}" class="dt-page-btn" rel="prev" title="Sebelumnya">‹ Prev</a>
          @endif

          {{-- Page Numbers (show up to 5 around current) --}}
          @php
            $start = max(1, $permits->currentPage() - 2);
            $end   = min($permits->lastPage(), $permits->currentPage() + 2);
          @endphp

          @if ($start > 1)
            <a href="{{ $permits->url(1) }}" class="dt-page-btn">1</a>
            @if ($start > 2)
              <span class="dt-page-ellipsis">…</span>
            @endif
          @endif

          @foreach ($permits->getUrlRange($start, $end) as $page => $url)
            @if ($page == $permits->currentPage())
              <span class="dt-page-btn active" aria-current="page">{{ $page }}</span>
            @else
              <a href="{{ $url }}" class="dt-page-btn">{{ $page }}</a>
            @endif
          @endforeach

          @if ($end < $permits->lastPage())
            @if ($end < $permits->lastPage() - 1)
              <span class="dt-page-ellipsis">…</span>
            @endif
            <a href="{{ $permits->url($permits->lastPage()) }}" class="dt-page-btn">{{ $permits->lastPage() }}</a>
          @endif

          {{-- Next Page --}}
          @if ($permits->hasMorePages())
            <a href="{{ $permits->nextPageUrl() }}" class="dt-page-btn" rel="next" title="Berikutnya">Next ›</a>
          @else
            <span class="dt-page-btn disabled" aria-disabled="true" title="Berikutnya">Next ›</span>
          @endif

          {{-- Last Page --}}
          @if ($permits->hasMorePages())
            <a href="{{ $permits->url($permits->lastPage()) }}" class="dt-page-btn" title="Halaman terakhir">»</a>
          @else
            <span class="dt-page-btn disabled" aria-disabled="true" title="Halaman terakhir">»</span>
          @endif
        </div>
      </div>

    @endif

  </div>

</div>
@endsection

@push('scripts')
<script>
window.initValidatorTable = function () {
  'use strict';

  // ── Per-Page Selector ─────────────────────────────────────────────────────
  const perPageSel = document.getElementById('dtPerPage');
  perPageSel?.addEventListener('change', function () {
    const baseUrl = this.dataset.baseUrl;
    const status  = this.dataset.status;
    const perPage = this.value;
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('status', status);
    url.searchParams.set('per_page', perPage);
    window.location.href = url.toString();
  });

  // ── Density Toggle ────────────────────────────────────────────────────────
  const table = document.getElementById('dtTable');
  const btnComfortable = document.getElementById('btnDensityComfortable');
  const btnCompact = document.getElementById('btnDensityCompact');

  btnComfortable?.addEventListener('click', () => {
    btnComfortable.classList.add('active');
    btnCompact?.classList.remove('active');
    table?.classList.remove('dt-table--compact');
  });

  btnCompact?.addEventListener('click', () => {
    btnCompact.classList.add('active');
    btnComfortable?.classList.remove('active');
    table?.classList.add('dt-table--compact');
  });

  // ── Desktop Row Expansion (Detail) ────────────────────────────────────────
  document.querySelectorAll('.btn-dt-detail').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const expandRow = document.getElementById(targetId);
      if (!expandRow) return;

      const isOpen = expandRow.classList.contains('open');
      expandRow.classList.toggle('open', !isOpen);
      btn.classList.toggle('expanded', !isOpen);
    });
  });

  // ── Mobile Accordion Expansion ────────────────────────────────────────────
  document.querySelectorAll('.dt-btn-mobile-detail').forEach(btn => {
    btn.addEventListener('click', () => {
      const panelId = btn.getAttribute('data-toggle-mobile');
      const panel = document.getElementById(panelId);
      if (!panel) return;

      const isOpen = panel.classList.contains('open');
      panel.classList.toggle('open', !isOpen);
      btn.classList.toggle('active', !isOpen);
    });
  });

  // ── Live Search & Filter ──────────────────────────────────────────────────
  const searchInput = document.getElementById('dtSearchInput');
  const filterDir = document.getElementById('dtFilterDirection');
  const recordsCount = document.getElementById('dtRecordsCount');

  function applyFilters() {
    const q = (searchInput?.value || '').trim().toLowerCase();
    const dir = (filterDir?.value || '').toLowerCase();

    let visibleCount = 0;

    // Filter desktop rows
    document.querySelectorAll('.dt-table tbody tr.dt-row').forEach(row => {
      const name = row.getAttribute('data-name') || '';
      const permit = row.getAttribute('data-permit') || '';
      const rowDir = row.getAttribute('data-direction') || '';

      const matchesSearch = !q || name.includes(q) || permit.includes(q);
      const matchesDir = !dir || rowDir === dir;

      const isVisible = matchesSearch && matchesDir;
      row.style.display = isVisible ? '' : 'none';

      // Hide corresponding expand row if parent is hidden
      const expandRow = row.nextElementSibling;
      if (expandRow && expandRow.classList.contains('dt-expand-row') && !isVisible) {
        expandRow.classList.remove('open');
      }

      if (isVisible) visibleCount++;
    });

    // Filter mobile cards
    document.querySelectorAll('.dt-mobile-card').forEach(card => {
      const name = card.getAttribute('data-name') || '';
      const permit = card.getAttribute('data-permit') || '';
      const cardDir = card.getAttribute('data-direction') || '';

      const matchesSearch = !q || name.includes(q) || permit.includes(q);
      const matchesDir = !dir || cardDir === dir;

      card.style.display = (matchesSearch && matchesDir) ? '' : 'none';
    });

    if (recordsCount) {
      recordsCount.textContent = visibleCount;
    }
  }

  searchInput?.addEventListener('input', applyFilters);
  filterDir?.addEventListener('change', applyFilters);

  // ── Table Sorting (Desktop) ───────────────────────────────────────────────
  let sortDir = {};
  document.querySelectorAll('.dt-table th.sortable').forEach(th => {
    th.addEventListener('click', () => {
      const key = th.getAttribute('data-sort');
      const isAsc = !sortDir[key];
      sortDir[key] = isAsc;

      const tbody = table?.querySelector('tbody');
      if (!tbody) return;

      const rows = Array.from(tbody.querySelectorAll('tr.dt-row'));
      rows.sort((a, b) => {
        let valA = a.getAttribute('data-' + key) || '';
        let valB = b.getAttribute('data-' + key) || '';
        return isAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
      });

      // Re-append sorted rows and their expand rows
      rows.forEach(r => {
        const expand = document.getElementById('expand-' + r.id.replace('row-', ''));
        tbody.appendChild(r);
        if (expand) tbody.appendChild(expand);
      });

      // Update indicator
      th.querySelector('.dt-sort-icon').textContent = isAsc ? '▲' : '▼';
    });
  });

  // ── Export CSV ─────────────────────────────────────────────────────────────
  document.getElementById('dtExportBtn')?.addEventListener('click', () => {
    const rows = Array.from(document.querySelectorAll('.dt-table tbody tr.dt-row'))
      .filter(r => r.style.display !== 'none');

    if (rows.length === 0) {
      alert('Tidak ada data yang dapat diekspor.');
      return;
    }

    let csvContent = 'data:text/csv;charset=utf-8,';
    csvContent += 'Nomor Surat,Tenant,PIC,No HP,Arah,Status,Tanggal Mulai,Tanggal Selesai\n';

    rows.forEach(r => {
      const permit = r.querySelector('.dt-mono-ref')?.textContent.trim() || '';
      const tenant = r.querySelector('.dt-user-name')?.textContent.trim() || '';
      const sub = r.querySelector('.dt-user-sub')?.textContent.trim() || '';
      const dir = r.querySelector('.dt-badge-role')?.textContent.trim() || '';
      const status = r.querySelector('.dt-badge-status')?.textContent.trim() || '';
      const date = r.querySelector('td:nth-child(5) span')?.textContent.trim() || '';

      csvContent += `"${permit}","${tenant}","${sub}","${dir}","${status}","${date}"\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `permohonan-loading-${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  });

};

window.initValidatorTable();
</script>
@endpush
