@props(['type'])

<div
  hidden
  data-form-presence-tracker
  data-form-type="{{ $type }}"
  data-heartbeat-url="{{ route('form-presence.heartbeat', $type) }}"
  data-leave-url="{{ route('form-presence.leave', $type) }}"
></div>
