@extends('layouts.admin')

@section('title', 'Aktivitas Form : Admin MBG')
@section('page-title', 'Aktivitas Form')

@section('content')
<div class="page-wrap admin-page-wrap form-presence-page" data-form-presence-admin data-snapshot-url="{{ route('admin.form-presence.snapshot') }}">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Monitoring</span><svg><use href="#i-chevron-right"/></svg><span>Aktivitas form</span></div>
      <h1 class="page-title">Pengunjung yang sedang mengisi form</h1>
      <p class="page-subtitle">Perkiraan jumlah sesi browser aktif pada setiap jenis permohonan, diperbarui tanpa membaca isi formulir.</p>
    </div>
    <div class="form-presence-connection" aria-live="polite">
      <span class="form-presence-live-dot" aria-hidden="true"></span>
      <span data-presence-status>Menyiapkan realtime</span>
    </div>
  </div>

  <section class="form-presence-total" aria-label="Total pengunjung aktif">
    <div>
      <span>Total sedang mengisi</span>
      <strong data-presence-total>{{ number_format($snapshot['total'], 0, ',', '.') }}</strong>
      <small>seluruh formulir permohonan</small>
    </div>
    <p>Angka menunjukkan sesi browser aktif. Satu browser tetap dihitung satu meskipun membuka beberapa tab.</p>
  </section>

  <section class="admin-panel form-presence-panel">
    <div class="admin-panel-header">
      <div><h2>Aktivitas per formulir</h2><p>Tab yang ditutup akan hilang dari hitungan. Koneksi yang terputus dibersihkan otomatis.</p></div>
      <span class="form-presence-updated">Diperbarui <time data-presence-updated-at>{{ now()->format('H:i:s') }}</time> WITA</span>
    </div>
    <div class="form-presence-list">
      @foreach($formTypes as $type => $label)
        <article class="form-presence-row">
          <span class="form-presence-icon" aria-hidden="true"><svg><use href="#{{ $type === 'loading' ? 'i-truck' : ($type === 'work' ? 'i-wrench' : 'i-calendar') }}"/></svg></span>
          <div><h3>{{ $label }}</h3><p>{{ $type === 'loading' ? 'Form izin keluar atau masuk barang' : ($type === 'work' ? 'Form pekerjaan tenant atau kontraktor' : 'Form kegiatan EO atau tenant') }}</p></div>
          <div class="form-presence-value"><strong data-presence-count="{{ $type }}">{{ number_format($snapshot['counts'][$type], 0, ',', '.') }}</strong><span>sedang mengisi</span></div>
        </article>
      @endforeach
    </div>
  </section>

  <aside class="form-presence-privacy">
    <svg aria-hidden="true"><use href="#i-shield-check"/></svg>
    <div><strong>Data pribadi tidak direkam</strong><p>Monitoring ini tidak menyimpan nama, email, nomor WhatsApp, isi form, maupun alamat IP.</p></div>
  </aside>
</div>
@endsection
