@props(['href'])

<a href="{{ $href }}" {{ $attributes->class(['btn-primary', 'permit-letter-action']) }}>
  <svg><use href="#i-file"/></svg>
  <span>Lihat surat izin</span>
</a>
