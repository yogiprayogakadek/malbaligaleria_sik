@extends('layouts.portal')

@section('title', 'Perbaiki ' . $permit->permit_number . ' : Mal Bali Galeria')
@section('page-title', 'Perbaikan Surat Izin Event')

@section('content')
@php
  $requested = $activeRevisions->keyBy('field_key');
  $selectedRequirementNames = collect($permit->event_requirements)->pluck('name');
  $otherRequirementValue = $selectedRequirementNames->first(fn ($name) => str_starts_with($name, 'Lainnya: '));
  $otherRequirementValue = $otherRequirementValue ? trim(str($otherRequirementValue)->after('Lainnya: ')->toString()) : '';
  $oldRequirementIds = old('requirement_ids');
@endphp
<div class="page-wrap page-wrap--narrow">
  <div class="page-header">
    <div><div class="page-breadcrumb"><a href="{{ route('event-permits.status', $permit->applicant_token) }}">Status Event</a><svg><use href="#i-chevron-right"/></svg><span>Perbaikan Data</span></div><h1 class="page-title">Perbaiki Surat Izin Event</h1><p class="page-subtitle">{{ $permit->permit_number }}</p></div>
    <a href="{{ route('event-permits.status', $permit->applicant_token) }}" class="validator-back-link"><svg><use href="#i-arrow-left"/></svg>Kembali</a>
  </div>

  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><div><strong>Periksa field berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

  <form action="{{ route('event-permits.revision.update', $permit->applicant_token) }}" method="POST" class="revision-edit-form" id="eventRevisionForm">
    @csrf

    @if($revision = $requested->get('event_type'))
      <x-revision-field :revision="$revision" label="Jenis surat Event"><div class="event-type-options">@foreach(['organizer' => 'Penyelenggara Event / EO', 'tenant' => 'Event Tenant'] as $value => $label)<label class="event-type-option"><input type="radio" name="event_type" value="{{ $value }}" @checked(old('event_type', $permit->event_type) === $value) required><span><strong>{{ $label }}</strong></span></label>@endforeach</div></x-revision-field>
    @endif

    @foreach(['contractor_name' => ['Nama EO / tenant', $permit->contractor_name, 150], 'applicant_name' => ['PIC / penanggung jawab', $permit->applicant_name, 120], 'applicant_phone' => ['Nomor WhatsApp', $permit->applicant_phone, 25], 'applicant_email' => ['Email', $permit->applicant_email, 120], 'work_location' => ['Lokasi pekerjaan / Event', $permit->work_location, 180], 'work_type' => ['Jenis pekerjaan', $permit->work_type, 180]] as $field => [$label, $value, $max])
      @if($revision = $requested->get($field))
        <x-revision-field :revision="$revision" :label="$label"><div class="pf"><input name="{{ $field }}" type="{{ $field === 'applicant_email' ? 'email' : ($field === 'applicant_phone' ? 'tel' : 'text') }}" value="{{ old($field, $value) }}" maxlength="{{ $max }}" required></div></x-revision-field>
      @endif
    @endforeach

    @if($revision = $requested->get('work_category'))
      <x-revision-field :revision="$revision" label="Kategori pekerjaan"><div class="pf"><select name="work_category" required>@foreach(config('event-permits.categories') as $value => $label)<option value="{{ $value }}" @selected(old('work_category', $permit->work_category) === $value)>{{ $label }}</option>@endforeach</select></div></x-revision-field>
    @endif

    @foreach(['start_date' => ['Tanggal berlaku mulai', $permit->start_date], 'end_date' => ['Tanggal berlaku sampai', $permit->end_date]] as $field => [$label, $value])
      @if($revision = $requested->get($field))<x-revision-field :revision="$revision" :label="$label"><div class="pf"><input id="event_revision_{{ $field }}" name="{{ $field }}" type="date" value="{{ old($field, $value->toDateString()) }}" min="{{ now()->toDateString() }}" required>@if($field === 'end_date')<span class="pf-hint">Maksimal 7 hari kalender termasuk tanggal mulai.</span>@endif</div></x-revision-field>@endif
    @endforeach

    @if($revision = $requested->get('event_requirements'))
      <x-revision-field :revision="$revision" label="Kebutuhan Event">
        <div class="event-requirement-groups">
          @foreach($requirements as $group => $items)
            <fieldset class="event-requirement-group"><legend>{{ $group }}</legend><div class="work-extra-options">
              @foreach($items as $item)
                @php($isOther = $group === 'Fasilitas' && $item->name === 'Lainnya')
                <label><input type="checkbox" name="requirement_ids[]" value="{{ $item->id }}" @checked($oldRequirementIds !== null ? in_array($item->id, array_map('intval', $oldRequirementIds), true) : ($isOther ? filled($otherRequirementValue) : $selectedRequirementNames->contains($item->name))) @if($isOther) id="eventRevisionOtherToggle" @endif><span>{{ $item->name }}</span></label>
              @endforeach
            </div>
            @if($group === 'Fasilitas' && $items->contains(fn ($item) => $item->name === 'Lainnya'))<div class="pf event-other-requirement" id="eventRevisionOtherField" hidden><label for="eventRevisionOther">Fasilitas lainnya <span class="req">*</span></label><input id="eventRevisionOther" name="requirement_other" value="{{ old('requirement_other', $otherRequirementValue) }}" minlength="2" maxlength="160" placeholder="Contoh: tangga atau meja tambahan" disabled></div>@endif
          </fieldset>
          @endforeach
        </div>
      </x-revision-field>
    @endif

    @if($revision = $requested->get('includes_loading_material'))
      <x-revision-field :revision="$revision" label="Surat tambahan loading / unloading"><label class="event-loading-option"><input type="hidden" name="includes_loading_material" value="0"><input type="checkbox" name="includes_loading_material" value="1" @checked(old('includes_loading_material', $permit->includes_loading_material))><span><strong>Termasuk loading / unloading</strong><small>Sistem akan menerbitkan surat tambahan bersama Surat Izin Event.</small></span></label></x-revision-field>
    @endif

    @if($revision = $requested->get('notes'))
      <x-revision-field :revision="$revision" label="Rincian pekerjaan"><div class="pf"><textarea name="notes" rows="5" minlength="10" maxlength="3000" required>{{ old('notes', $permit->notes) }}</textarea></div></x-revision-field>
    @endif

    <div class="permit-submit-actions revision-edit-actions"><a href="{{ route('event-permits.status', $permit->applicant_token) }}" class="btn-secondary">Batal</a><button type="submit" class="btn-primary"><svg><use href="#i-check"/></svg>Kirim Perbaikan</button></div>
  </form>
</div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
  const toggle = document.getElementById('eventRevisionOtherToggle');
  const field = document.getElementById('eventRevisionOtherField');
  const input = document.getElementById('eventRevisionOther');
  const start = document.getElementById('event_revision_start_date');
  const end = document.getElementById('event_revision_end_date');
  const localDate = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  const syncOther = () => {
    if (!toggle || !field || !input) return;
    field.hidden = !toggle.checked;
    input.disabled = !toggle.checked;
    input.required = toggle.checked;
  };
  const syncDates = () => {
    if (!start || !end || !start.value) return;
    const maximum = new Date(`${start.value}T00:00:00`);
    maximum.setDate(maximum.getDate() + 6);
    end.min = start.value;
    end.max = localDate(maximum);
    if (end.value < end.min || end.value > end.max) end.value = '';
  };
  toggle?.addEventListener('change', syncOther);
  start?.addEventListener('change', syncDates);
  syncOther();
  syncDates();
})();
</script>
@endpush
