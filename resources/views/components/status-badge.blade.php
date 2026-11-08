@props([
  'status',
  'label' => null,
  'icon' => null,
])

@php
  $normalized = strtolower(str_replace([' ', '_'], '-', $status));
  $defaults = [
    'approved' => ['Disetujui', 'check'],
    'success' => ['Berhasil', 'check'],
    'pending' => ['Menunggu', 'clock'],
    'warning' => ['Perlu perhatian', 'info'],
    'revision' => ['Perlu perbaikan', 'edit'],
    'rejected' => ['Ditolak', 'x'],
    'danger' => ['Bermasalah', 'info'],
    'active' => ['Aktif', 'check'],
    'inactive' => ['Nonaktif', 'x'],
  ];
  [$defaultLabel, $defaultIcon] = $defaults[$normalized] ?? [ucwords(str_replace('-', ' ', $normalized)), 'info'];
@endphp

<span {{ $attributes->class(['status-badge', "status-badge--{$normalized}"]) }}>
  <svg aria-hidden="true"><use href="#i-{{ $icon ?? $defaultIcon }}"/></svg>
  <span>{{ $label ?? $defaultLabel }}</span>
</span>
