@extends('layouts.portal')

@section('title', 'Perbaiki ' . $permit->permit_number . ' : Mal Bali Galeria')
@section('page-title', 'Perbaikan Permohonan Loading')

@section('content')
@php
  $requested = $activeRevisions->keyBy('field_key');
  $backUrl = URL::temporarySignedRoute('loading.show', now()->addMinutes(30), ['permitNumber' => $permit->permit_number]);
@endphp
<div class="page-wrap page-wrap--narrow">
  <div class="page-header">
    <div><div class="page-breadcrumb"><a href="{{ $backUrl }}">Status Permohonan</a><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Perbaikan Data</span></div><h1 class="page-title">Perbaiki Permohonan Loading</h1><p class="page-subtitle">{{ $permit->permit_number }}</p></div>
    <a href="{{ $backUrl }}" class="validator-back-link"><svg><use href="#i-arrow-left"/></svg>Kembali</a>
  </div>

  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><div><strong>Periksa field berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

  <form action="{{ URL::temporarySignedRoute('loading.revision.update', now()->addMinutes(30), ['permitNumber' => $permit->permit_number]) }}" method="POST" enctype="multipart/form-data" class="revision-edit-form">
    @csrf

    @if($revision = $requested->get('tenant_name'))
      <x-revision-field :revision="$revision" label="Nama tenant / toko"><div class="pf"><input name="tenant_name" value="{{ old('tenant_name', $permit->tenant_name) }}" minlength="2" maxlength="120" required></div></x-revision-field>
    @endif
    @if($revision = $requested->get('applicant_name'))
      <x-revision-field :revision="$revision" label="Nama penanggung jawab"><div class="pf"><input name="applicant_name" value="{{ old('applicant_name', $permit->applicant_name) }}" minlength="2" maxlength="120" required></div></x-revision-field>
    @endif
    @if($revision = $requested->get('applicant_phone'))
      <x-revision-field :revision="$revision" label="Nomor WhatsApp"><div class="pf"><input name="applicant_phone" type="tel" inputmode="tel" value="{{ old('applicant_phone', $permit->applicant_phone) }}" maxlength="25" required></div></x-revision-field>
    @endif
    @if($revision = $requested->get('applicant_email'))
      <x-revision-field :revision="$revision" label="Email"><div class="pf"><input name="applicant_email" type="email" value="{{ old('applicant_email', $permit->applicant_email) }}" maxlength="120" required></div></x-revision-field>
    @endif
    @if($revision = $requested->get('direction'))
      <x-revision-field :revision="$revision" label="Arah pergerakan barang"><div class="pf-radio-group">@foreach(['in' => 'Barang Masuk', 'out' => 'Barang Keluar', 'both' => 'Masuk & Keluar'] as $value => $label)<label class="pf-radio"><input type="radio" name="direction" value="{{ $value }}" @checked(old('direction', $permit->direction) === $value) required><svg><use href="#i-truck"/></svg><span>{{ $label }}</span></label>@endforeach</div></x-revision-field>
    @endif
    @if($revision = $requested->get('movement_time'))
      <x-revision-field :revision="$revision" label="Waktu loading / unloading"><div class="pf"><input name="movement_time" type="time" value="{{ old('movement_time', substr((string) $permit->movement_time, 0, 5)) }}" required><span class="pf-hint">Pukul 22.00 sampai 11.00 WITA keesokan harinya.</span></div></x-revision-field>
    @endif
    @if($revision = $requested->get('start_date'))
      <x-revision-field :revision="$revision" label="Tanggal mulai"><div class="pf"><input name="start_date" type="date" value="{{ old('start_date', $permit->start_date->toDateString()) }}" min="{{ now()->toDateString() }}" required></div></x-revision-field>
    @endif
    @if($revision = $requested->get('end_date'))
      <x-revision-field :revision="$revision" label="Tanggal selesai"><div class="pf"><input name="end_date" type="date" value="{{ old('end_date', $permit->end_date->toDateString()) }}" min="{{ now()->toDateString() }}" required><span class="pf-hint">Maksimal 3 hari kalender termasuk tanggal mulai.</span></div></x-revision-field>
    @endif
    @if($revision = $requested->get('item_count'))
      <x-revision-field :revision="$revision" label="Jumlah barang"><div class="pf"><input name="item_count" type="number" min="1" max="9999" value="{{ old('item_count', $permit->item_count) }}" required></div></x-revision-field>
    @endif
    @if($revision = $requested->get('item_unit'))
      <x-revision-field :revision="$revision" label="Satuan barang"><div class="pf"><select name="item_unit" required><option value="pcs" @selected(old('item_unit', $permit->item_unit) === 'pcs')>pcs</option><option value="koli" @selected(old('item_unit', $permit->item_unit) === 'koli')>koli</option></select></div></x-revision-field>
    @endif
    @if($revision = $requested->get('item_description'))
      <x-revision-field :revision="$revision" label="Deskripsi barang"><div class="pf"><textarea name="item_description" rows="3" minlength="3" maxlength="500" required>{{ old('item_description', $permit->item_description) }}</textarea></div></x-revision-field>
    @endif
    @if($revision = $requested->get('id_doc'))
      <x-revision-field :revision="$revision" label="Dokumen KTP / SIM"><div class="pf"><div class="pf-radio-group">@foreach(['ktp' => 'KTP', 'sim' => 'SIM'] as $value => $label)<label class="pf-radio"><input type="radio" name="id_doc_type" value="{{ $value }}" @checked(old('id_doc_type', $permit->id_doc_type) === $value) required><svg><use href="#i-file"/></svg><span>{{ $label }}</span></label>@endforeach</div><input name="id_doc" type="file" accept="image/jpeg,image/jpg,image/png,image/webp" data-upload-error="{{ $errors->has('id_doc') ? 'true' : 'false' }}" data-optimize-image required><span class="pf-hint">JPG, PNG, atau WebP; maksimal 8 MB.</span></div></x-revision-field>
    @endif
    @if($revision = $requested->get('instruction_letter'))
      <x-revision-field :revision="$revision" label="Surat izin atau surat perintah"><div class="pf"><input name="instruction_letter" type="file" accept="application/pdf,image/jpeg,image/jpg,image/png,image/webp" data-upload-error="{{ $errors->has('instruction_letter') ? 'true' : 'false' }}" data-optimize-image required><span class="pf-hint">PDF, JPG, PNG, atau WebP; maksimal 8 MB.</span></div></x-revision-field>
    @endif

    <div class="permit-submit-actions revision-edit-actions"><a href="{{ $backUrl }}" class="btn-secondary">Batal</a><button type="submit" class="btn-primary"><svg><use href="#i-check"/></svg>Kirim Perbaikan</button></div>
  </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('form-image.js') }}?v={{ filemtime(public_path('form-image.js')) }}"></script>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.querySelectorAll('[data-optimize-image]').forEach(input => input.addEventListener('change', async () => {
  const file = input.files?.[0];
  input.setCustomValidity('');
  if (!file || file.type === 'application/pdf' || /\.pdf$/i.test(file.name)) return;
  try { await window.prepareUploadImage(input); }
  catch (error) { input.setCustomValidity(error.message || 'Foto tidak dapat diproses.'); input.reportValidity(); }
}));
</script>
@endpush
