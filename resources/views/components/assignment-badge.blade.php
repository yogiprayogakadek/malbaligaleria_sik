@props(['permit'])

@if($permit->assignee)
  <span class="assignment-badge {{ $permit->assigned_validator_id === Auth::id() ? 'is-mine' : '' }}">
    <svg><use href="#i-user"/></svg>{{ $permit->assigned_validator_id === Auth::id() ? 'Anda' : $permit->assignee->name }}
  </span>
@elseif(($permit instanceof \App\Models\LoadingPermit && $permit->status === 'pending') || ($permit instanceof \App\Models\WorkPermit && $permit->actionDivision()))
  <span class="assignment-badge is-open"><svg><use href="#i-user"/></svg>Belum diambil</span>
@endif
