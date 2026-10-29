@extends('layouts.master')

@section('title', $title . ' : Mal Bali Galeria')
@section('body-class', 'document-preview-page')

@section('body')
<main class="document-preview-shell">
  <header class="document-preview-header">
    <button type="button" class="document-preview-back" data-document-back data-fallback-url="{{ $backUrl }}">
      <svg><use href="#i-arrow-left"/></svg>
      <span>Kembali</span>
    </button>
    <div class="document-preview-heading">
      <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria">
      <div><h1>{{ $title }}</h1><p>{{ $reference }}</p></div>
    </div>
    @if($downloadUrl)
      <a href="{{ $downloadUrl }}" class="document-preview-download">
        <svg><use href="#i-file"/></svg>
        <span>Unduh</span>
      </a>
    @endif
  </header>

  <section class="document-preview-content" aria-label="Pratinjau {{ $title }}">
    @if($isImage)
      <img src="{{ $sourceUrl }}" alt="{{ $title }}" class="document-preview-image">
    @else
      <iframe src="{{ $sourceUrl }}" title="{{ $title }}" class="document-preview-frame" data-document-pdf-frame></iframe>
      <div class="document-preview-android" data-document-android-fallback hidden>
        <span class="document-preview-android-icon" aria-hidden="true"><svg><use href="#i-file"/></svg></span>
        <h2>Buka surat di penampil PDF</h2>
        <p>Android tidak selalu dapat menampilkan PDF di dalam aplikasi. Buka surat secara langsung agar pratinjau tidak diblokir.</p>
        <a href="{{ $sourceUrl }}" target="_blank" rel="noopener" class="document-preview-open">Buka surat</a>
      </div>
    @endif
  </section>
</main>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.querySelector('[data-document-back]')?.addEventListener('click', event => {
  const fallbackUrl = event.currentTarget.dataset.fallbackUrl;
  if (window.history.length > 1) window.history.back();
  else window.location.assign(fallbackUrl);
});

if (/Android/i.test(navigator.userAgent)) {
  const frame = document.querySelector('[data-document-pdf-frame]');
  const fallback = document.querySelector('[data-document-android-fallback]');
  if (frame && fallback) {
    frame.removeAttribute('src');
    frame.hidden = true;
    fallback.hidden = false;
  }
}
</script>
@endpush
