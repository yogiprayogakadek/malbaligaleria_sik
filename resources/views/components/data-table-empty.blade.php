@props([
  'icon' => 'file',
  'title',
  'message',
])

<div {{ $attributes->class(['dt-empty-state']) }}>
  <span class="dt-empty-icon" aria-hidden="true">
    <svg><use href="#i-{{ $icon }}"/></svg>
  </span>
  <h3>{{ $title }}</h3>
  <p>{{ $message }}</p>
</div>
