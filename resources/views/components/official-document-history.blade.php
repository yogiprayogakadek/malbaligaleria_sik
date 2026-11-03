@props(['permit'])

@php($versions = $permit->documentVersions)
<section class="official-version-panel" aria-labelledby="officialVersionTitle">
  <div class="official-version-heading">
    <div><h2 id="officialVersionTitle">Riwayat surat resmi</h2><p>Versi lama tetap tercatat untuk pemeriksaan audit, tetapi tidak dapat digunakan kembali.</p></div>
    <span>{{ $versions->count() + 1 }} versi</span>
  </div>
  <ol class="official-version-list">
    <li class="is-current">
      <span class="official-version-number">v{{ $versions->count() + 1 }}</span>
      <div><strong>Versi aktif</strong><small>{{ $permit->official_document_issued_at?->format('d M Y, H:i') }} WITA · ID {{ $permit->official_document_id }}</small><code>SHA-256 {{ substr($permit->official_document_sha256, 0, 16) }}...</code></div>
    </li>
    @foreach($versions as $version)
      <li>
        <span class="official-version-number">v{{ $version->version }}</span>
        <div><strong>Digantikan {{ $version->replaced_at->format('d M Y, H:i') }} WITA</strong><small>{{ $version->replacement_reason }} · {{ $version->replacer?->name ?: 'Akun dihapus' }}</small><code>ID {{ $version->document_id }} · SHA-256 {{ substr($version->sha256, 0, 16) }}...</code></div>
      </li>
    @endforeach
  </ol>
</section>
