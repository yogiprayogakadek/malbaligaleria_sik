@extends('layouts.admin')

@section('title', 'Template Surat - Admin MBG')
@section('page-title', 'Template Surat')

@section('content')
<div class="page-wrap admin-page-wrap document-template-page">
  <div class="page-header admin-page-header"><div><div class="page-breadcrumb"><span>Sistem</span><svg><use href="#i-chevron-right"/></svg><span>Template surat</span></div><h1 class="page-title">Template dokumen resmi</h1><p class="page-subtitle">Kelola sumber DOCX, koneksi converter privat, dan renderer untuk surat yang baru diterbitkan.</p></div></div>

  @if(session('success'))<div class="permit-alert permit-alert--success"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if(session('converter_error'))<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>{{ session('converter_error') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  <section class="admin-panel document-converter-panel">
    <div class="admin-panel-header"><div><h2>Document converter</h2><p>Koneksi hanya menerima IPv4 privat dan tidak mengikuti redirect.</p></div><span class="admin-security-label"><svg><use href="#i-lock"/></svg>Private network</span></div>
    <form method="POST" action="{{ route('admin.settings.document-templates.update') }}" class="document-converter-form">
      @csrf @method('PUT')
      <div class="document-converter-grid">
        <label><span>Status converter</span><select name="converter_enabled"><option value="1" @selected((int)old('converter_enabled',$setting->converter_enabled)===1)>Aktif</option><option value="0" @selected((int)old('converter_enabled',$setting->converter_enabled)===0)>Nonaktif</option></select></label>
        <label><span>Protokol</span><select name="converter_scheme"><option value="http" @selected(old('converter_scheme',$setting->converter_scheme)==='http')>HTTP jaringan privat</option><option value="https" @selected(old('converter_scheme',$setting->converter_scheme)==='https')>HTTPS</option></select></label>
        <label><span>IP converter</span><input name="converter_host" inputmode="decimal" value="{{ old('converter_host',$setting->converter_host) }}" placeholder="192.168.0.249" required></label>
        <label><span>Port API</span><input name="converter_port" type="number" min="1" max="65535" value="{{ old('converter_port',$setting->converter_port) }}" required></label>
        <label><span>Timeout</span><div class="monitoring-input-unit"><input name="converter_timeout" type="number" min="10" max="120" value="{{ old('converter_timeout',$setting->converter_timeout) }}" required><span>detik</span></div></label>
        <label><span>API token baru</span><input name="converter_token" type="password" minlength="32" maxlength="512" autocomplete="new-password"><small>{{ filled($setting->converter_token) ? 'Token sudah tersimpan. Kosongkan untuk mempertahankannya.' : 'Gunakan token acak minimal 32 karakter.' }}</small></label>
      </div>

      <div class="document-renderer-section">
        <div><strong>Renderer per jenis surat</strong><p>Template bawaan menggunakan desain PDF yang sudah berjalan saat ini.</p></div>
        <div class="document-renderer-grid">
          @foreach($permitTypes as $type => $label)
            <label><span>{{ $label }}</span><select name="{{ $type }}_renderer"><option value="default" @selected(old($type.'_renderer',$setting->rendererFor($type))==='default')>Template bawaan</option><option value="libreoffice" @selected(old($type.'_renderer',$setting->rendererFor($type))==='libreoffice')>DOCX + LibreOffice</option></select></label>
          @endforeach
        </div>
      </div>

      <label class="document-fallback-option"><input type="hidden" name="fallback_enabled" value="0"><input type="checkbox" name="fallback_enabled" value="1" @checked((bool)old('fallback_enabled',$setting->fallback_enabled))><span><strong>Fallback otomatis ke template bawaan</strong><small>Surat tetap diterbitkan memakai renderer lama jika converter atau template DOCX bermasalah.</small></span></label>
      <div class="document-sensitive-row"><label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label><button class="admin-primary-button" type="submit"><svg><use href="#i-check"/></svg>Simpan konfigurasi</button></div>
    </form>

    <div class="document-test-row">
      <div><strong>Uji koneksi converter</strong><p>{{ $setting->last_tested_at ? 'Terakhir diuji '.$setting->last_tested_at->format('d M Y, H:i').' WITA. '.$setting->last_test_message : 'Belum pernah diuji.' }}</p></div>
      <form method="POST" action="{{ route('admin.settings.document-templates.test') }}">@csrf<label><span class="sr-only">Kata sandi admin</span><input type="password" name="current_password" placeholder="Kata sandi admin" autocomplete="current-password" required></label><button class="admin-secondary-button" type="submit"><svg><use href="#i-shield-check"/></svg>Uji koneksi</button></form>
    </div>
  </section>

  <div class="document-template-layout">
    @foreach($permitTypes as $type => $label)
      @php($typeTemplates = $templates->get($type, collect()))
      @php($activeTemplate = $typeTemplates->firstWhere('is_active', true))
      <section class="admin-panel document-template-panel">
        <div class="admin-panel-header"><div><h2>{{ $label }}</h2><p>{{ $activeTemplate ? 'Versi '.$activeTemplate->version.' sedang aktif.' : 'Belum ada template DOCX.' }}</p></div>@if($activeTemplate)<span class="document-template-active">Aktif</span>@endif</div>
        <form method="POST" action="{{ route('admin.settings.document-templates.store') }}" enctype="multipart/form-data" class="document-template-upload">
          @csrf
          <input type="hidden" name="permit_type" value="{{ $type }}">
          <label class="document-template-file"><input type="file" name="template" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" data-upload-error="{{ $errors->has('template') ? 'true' : 'false' }}" required><span><svg><use href="#i-file"/></svg><strong>Pilih template DOCX</strong><small>Maksimal 10 MB, tanpa macro atau resource eksternal.</small></span></label>
          <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label>
          <button class="admin-primary-button" type="submit"><svg><use href="#i-file"/></svg>Periksa dan unggah</button>
        </form>

        <details class="document-placeholder-help"><summary>Placeholder tersedia</summary><div>@foreach($placeholders[$type] as $placeholder)<code>${{ '{'.$placeholder.'}' }}</code>@endforeach</div></details>

        <div class="document-template-history">
          @forelse($typeTemplates as $template)
            <article class="document-template-version {{ $template->is_active ? 'is-active' : '' }}">
              <div><strong>Versi {{ $template->version }} · {{ $template->original_name }}</strong><small>{{ number_format($template->size / 1024, 0, ',', '.') }} KB · {{ $template->created_at->format('d M Y, H:i') }} WITA · {{ $template->uploader?->name ?: 'Akun dihapus' }}</small><span>{{ count($template->placeholders) }} placeholder · SHA-256 {{ substr($template->sha256, 0, 12) }}…</span></div>
              <div class="document-template-actions"><a href="{{ route('admin.settings.document-templates.download',$template) }}" class="admin-secondary-button">Unduh</a>@unless($template->is_active)<form method="POST" action="{{ route('admin.settings.document-templates.activate',$template) }}">@csrf @method('PATCH')<input type="password" name="current_password" placeholder="Kata sandi" autocomplete="current-password" required><button class="admin-secondary-button" type="submit">Aktifkan</button></form>@endunless</div>
            </article>
          @empty
            <div class="admin-table-empty"><svg><use href="#i-file"/></svg><strong>Belum ada template</strong><span>Template bawaan tetap digunakan sampai DOCX diunggah dan renderer diaktifkan.</span></div>
          @endforelse
        </div>
      </section>
    @endforeach
  </div>
</div>
@endsection
