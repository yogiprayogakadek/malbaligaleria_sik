@props([
  'total',
  'perPageRaw' => 10,
  'placeholder' => 'Cari permohonan...',
  'label' => 'Cari permohonan',
])

<div class="dt-toolbar">
  <div class="dt-header-left">
    <h2 class="dt-title">Daftar Permohonan</h2>
    <span class="dt-count">&middot; <span data-staff-record-count>{{ $total }}</span> data</span>
  </div>
  <div class="dt-header-right">
    <div class="dt-perpage-wrap">
      <label class="dt-perpage-label">Tampilkan
        <select class="dt-select dt-perpage-select" data-staff-per-page aria-label="Jumlah data per halaman">
          @foreach([5, 10, 20, 50, 100, 'all'] as $option)
            <option value="{{ $option }}" @selected((string) $perPageRaw === (string) $option)>{{ $option === 'all' ? 'Semua' : $option }}</option>
          @endforeach
        </select>
      </label>
    </div>
    <div class="dt-search-wrap">
      <svg><use href="#i-search"/></svg>
      <input type="search" class="dt-search-input" data-staff-search placeholder="{{ $placeholder }}" aria-label="{{ $label }}">
    </div>
    <div class="dt-density-group" role="group" aria-label="Kerapatan tabel">
      <button type="button" class="dt-density-btn active" data-staff-density="comfortable">Normal</button>
      <button type="button" class="dt-density-btn" data-staff-density="compact">Ringkas</button>
    </div>
    <button type="button" class="dt-btn-export" data-staff-export title="Unduh CSV">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
      <span>Unduh CSV</span>
    </button>
  </div>
</div>
