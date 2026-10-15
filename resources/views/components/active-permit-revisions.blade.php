@props(['permit', 'actionUrl' => null])

@php
  $batchId = $permit->revisions->whereNull('resolved_at')->sortByDesc('id')->first()?->batch_id;
  $items = $batchId ? $permit->revisions->where('batch_id', $batchId)->whereNull('resolved_at')->sortBy('id') : collect();
  $labels = app(\App\Services\PermitRevisionService::class)->fieldsFor($permit);
@endphp

@if($permit->status === 'revision_required' && $items->isNotEmpty())
  <section class="active-revisions" aria-labelledby="activeRevisionsTitle">
    <div><h2 id="activeRevisionsTitle">Data Perlu Diperbaiki</h2><p>Periksa catatan validator pada setiap field sebelum mengirim ulang.</p></div>
    <dl>@foreach($items as $item)<dt>{{ $labels[$item->field_key] ?? $item->field_key }}</dt><dd>{{ $item->reason }}</dd>@endforeach</dl>
    @if($actionUrl)<a href="{{ $actionUrl }}" class="btn-primary"><svg><use href="#i-edit"/></svg>Perbaiki Data</a>@endif
  </section>
@endif
