@props(['permit'])

@if($permit->is_sla_overdue)
  <span class="sla-overdue-badge" title="Melewati batas waktu pemeriksaan">SLA lewat</span>
@endif
