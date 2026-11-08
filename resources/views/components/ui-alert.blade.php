@props([
  'type' => 'info',
  'title' => null,
  'icon' => null,
])

@php
  $icons = ['success' => 'check', 'warning' => 'info', 'danger' => 'info', 'blocking' => 'shield-check', 'info' => 'info'];
  $role = in_array($type, ['danger', 'blocking'], true) ? 'alert' : 'status';
@endphp

<div {{ $attributes->class(['ui-alert', "ui-alert--{$type}"]) }} role="{{ $role }}">
  <svg aria-hidden="true"><use href="#i-{{ $icon ?? $icons[$type] ?? 'info' }}"/></svg>
  <div class="ui-alert-copy">
    @if($title)<strong>{{ $title }}</strong>@endif
    {{ $slot }}
  </div>
</div>
