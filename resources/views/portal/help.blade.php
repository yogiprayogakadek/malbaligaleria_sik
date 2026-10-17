@extends('layouts.portal')

@section('title', 'Bantuan & Panduan : Mal Bali Galeria')
@section('page-title', 'Bantuan')

@section('content')
<section class="view active" id="view-help" aria-labelledby="helpTitle">
  <div class="page-wrap help-page">
    <header class="help-header">
      <div class="help-header-copy">
        <p class="page-eyebrow">PUSAT BANTUAN</p>
        <h1 id="helpTitle" class="page-title">Panduan perizinan tenant</h1>
        <p class="page-subtitle">Temukan alur pengajuan, ketentuan waktu, dan cara memantau permohonan di satu halaman.</p>
      </div>
      <div class="help-header-note">
        <svg aria-hidden="true"><use href="#i-info"/></svg>
        <p>Simpan nomor referensi setelah mengirim formulir. Nomor tersebut diperlukan untuk memeriksa status tanpa masuk ke akun.</p>
      </div>
    </header>

    <nav class="help-shortcuts" aria-label="Akses cepat bantuan">
      <a href="{{ route('loading.create') }}" class="help-shortcut">
        <span class="help-shortcut-icon help-shortcut-icon--blue"><svg aria-hidden="true"><use href="#i-truck"/></svg></span>
        <span class="help-shortcut-copy"><strong>Loading / unloading</strong><small>Ajukan izin pergerakan barang</small></span>
        <svg class="help-shortcut-arrow" aria-hidden="true"><use href="#i-arrow-right"/></svg>
      </a>
      <a href="{{ route('work-permits.create') }}" class="help-shortcut">
        <span class="help-shortcut-icon help-shortcut-icon--violet"><svg aria-hidden="true"><use href="#i-wrench"/></svg></span>
        <span class="help-shortcut-copy"><strong>Surat izin kerja</strong><small>Ajukan pekerjaan tenant atau kontraktor</small></span>
        <svg class="help-shortcut-arrow" aria-hidden="true"><use href="#i-arrow-right"/></svg>
      </a>
      <a href="{{ route('portal.track') }}" class="help-shortcut">
        <span class="help-shortcut-icon help-shortcut-icon--green"><svg aria-hidden="true"><use href="#i-search"/></svg></span>
        <span class="help-shortcut-copy"><strong>Cek status</strong><small>Lihat progres dan tindak lanjut permohonan</small></span>
        <svg class="help-shortcut-arrow" aria-hidden="true"><use href="#i-arrow-right"/></svg>
      </a>
    </nav>

    <div class="help-layout">
      <div class="help-main">
        <section class="help-section" aria-labelledby="helpFlowTitle">
          <div class="help-section-heading">
            <p class="help-section-kicker">ALUR PERMOHONAN</p>
            <h2 id="helpFlowTitle">Dari pengajuan hingga surat terbit</h2>
          </div>
          <ol class="help-flow">
            <li>
              <span class="help-flow-number">1</span>
              <div><strong>Isi formulir dan unggah dokumen</strong><p>Lengkapi data penanggung jawab, jadwal, serta dokumen identitas yang diminta.</p></div>
            </li>
            <li>
              <span class="help-flow-number">2</span>
              <div><strong>Simpan nomor referensi</strong><p>Nomor referensi ditampilkan setelah pengajuan berhasil dan dikirim melalui kanal notifikasi yang tersedia.</p></div>
            </li>
            <li>
              <span class="help-flow-number">3</span>
              <div><strong>Pantau pemeriksaan validator</strong><p>Validator dapat menyetujui, menolak, atau meminta perbaikan pada data tertentu.</p></div>
            </li>
            <li>
              <span class="help-flow-number">4</span>
              <div><strong>Unduh surat yang disetujui</strong><p>Surat resmi memuat QR yang dapat dipindai petugas untuk memeriksa keaslian dan masa berlaku.</p></div>
            </li>
          </ol>
        </section>

        <section class="help-section help-faq-section" aria-labelledby="helpFaqTitle">
          <div class="help-section-heading">
            <p class="help-section-kicker">PERTANYAAN UMUM</p>
            <h2 id="helpFaqTitle">Informasi yang sering dibutuhkan</h2>
          </div>
          <div class="help-accordion">
            <details class="help-item" open>
              <summary><span>Kapan loading atau unloading dapat dilakukan?</span><svg aria-hidden="true"><use href="#i-chevron-right"/></svg></summary>
              <div class="help-body"><p>Waktu loading atau unloading hanya dapat dipilih mulai pukul 22.00 WITA hingga pukul 11.00 WITA pada hari berikutnya.</p></div>
            </details>
            <details class="help-item">
              <summary><span>Berapa lama masa berlaku permohonan?</span><svg aria-hidden="true"><use href="#i-chevron-right"/></svg></summary>
              <div class="help-body"><p>Izin loading atau unloading dapat diajukan maksimal untuk 3 hari kalender. Surat izin kerja dapat diajukan maksimal untuk 7 hari kalender, termasuk tanggal mulai.</p></div>
            </details>
            <details class="help-item">
              <summary><span>Bagaimana jika validator meminta perbaikan?</span><svg aria-hidden="true"><use href="#i-chevron-right"/></svg></summary>
              <div class="help-body"><p>Buka tautan status dari email atau WhatsApp, lalu perbaiki field yang ditandai. Data lain yang sudah benar tidak perlu diisi ulang.</p></div>
            </details>
            <details class="help-item">
              <summary><span>Dokumen apa yang perlu disiapkan untuk surat izin kerja?</span><svg aria-hidden="true"><use href="#i-chevron-right"/></svg></summary>
              <div class="help-body"><p>Siapkan KTP atau SIM. Surat tugas juga wajib untuk pekerjaan jaringan di rooftop dan perbaikan ATM. Ketentuan tambahan akan muncul sesuai pilihan pekerjaan pada formulir.</p></div>
            </details>
            <details class="help-item">
              <summary><span>Di mana surat izin yang sudah disetujui dapat diunduh?</span><svg aria-hidden="true"><use href="#i-chevron-right"/></svg></summary>
              <div class="help-body"><p>Buka halaman status permohonan melalui nomor referensi atau tautan notifikasi. Tombol unduh surat akan tersedia setelah status permohonan disetujui.</p></div>
            </details>
          </div>
        </section>
      </div>

      <aside class="help-aside" aria-label="Ketentuan penting">
        <section class="help-rules" aria-labelledby="helpRulesTitle">
          <div class="help-rules-heading">
            <span class="help-rules-icon"><svg aria-hidden="true"><use href="#i-clock"/></svg></span>
            <div><p>SEBELUM MENGAJUKAN</p><h2 id="helpRulesTitle">Periksa ketentuan waktu</h2></div>
          </div>
          <dl class="help-rule-list">
            <div><dt>Loading / unloading</dt><dd>22.00 sampai 11.00 WITA</dd></div>
            <div><dt>Durasi loading</dt><dd>Maksimal 3 hari kalender</dd></div>
            <div><dt>Durasi izin kerja</dt><dd>Maksimal 7 hari kalender</dd></div>
          </dl>
        </section>

        <section class="help-support" aria-labelledby="helpSupportTitle">
          <svg aria-hidden="true"><use href="#i-building"/></svg>
          <h2 id="helpSupportTitle">Perlu koordinasi langsung?</h2>
          <p>Untuk kebutuhan izin khusus atau kendala teknis, kunjungi meja Building Management di Ground Floor Mal Bali Galeria.</p>
        </section>
      </aside>
    </div>
  </div>
</section>
@endsection
