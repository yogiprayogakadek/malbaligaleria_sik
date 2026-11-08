@props(['note' => null])

<div {{ $attributes->class(['action-bar']) }}>
  @if($note)<p class="action-bar-note">{{ $note }}</p>@endif
  <div class="action-bar-actions">{{ $slot }}</div>
</div>
