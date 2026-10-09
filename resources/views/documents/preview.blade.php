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
      <iframe src="{{ $sourceUrl }}" title="{{ $title }}" class="document-preview-frame"></iframe>
    @endif
  </section>
</main>
@endsection

@push('scripts')
<script>
document.querySelector('[data-document-back]')?.addEventListener('click', event => {
  const fallbackUrl = event.currentTarget.dataset.fallbackUrl;
  if (window.history.length > 1) window.history.back();
  else window.location.assign(fallbackUrl);
});
</script>
@endpush
