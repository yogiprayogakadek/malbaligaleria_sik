@extends('layouts.portal')

@section('title', 'Perbaiki ' . $permit->permit_number . ' : Mal Bali Galeria')
@section('page-title', 'Perbaikan Surat Izin Kerja')

@section('content')
@php $requested = $activeRevisions->keyBy('field_key'); @endphp
<div class="page-wrap page-wrap--narrow">
  <div class="page-header">
    <div><div class="page-breadcrumb"><a href="{{ route('work-permits.status', $permit->applicant_token) }}">Status Izin Kerja</a><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Perbaikan Data</span></div><h1 class="page-title">Perbaiki Surat Izin Kerja</h1><p class="page-subtitle">{{ $permit->permit_number }}</p></div>
    <a href="{{ route('work-permits.status', $permit->applicant_token) }}" class="validator-back-link"><svg><use href="#i-arrow-left"/></svg>Kembali</a>
  </div>

  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><div><strong>Periksa field berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

  <form action="{{ route('work-permits.revision.update', $permit->applicant_token) }}" method="POST" enctype="multipart/form-data" class="revision-edit-form">
    @csrf
    @foreach(['contractor_name' => ['Nama kontraktor / vendor', $permit->contractor_name, 150], 'tenant_name' => ['Nama tenant', $permit->tenant_name, 150], 'applicant_name' => ['Nama penanggung jawab', $permit->applicant_name, 120], 'applicant_phone' => ['Nomor WhatsApp', $permit->applicant_phone, 25], 'applicant_email' => ['Email', $permit->applicant_email, 120], 'work_location' => ['Lokasi pekerjaan', $permit->work_location, 180], 'work_type' => ['Uraian pekerjaan', $permit->work_type, 180]] as $field => [$label, $value, $max])
      @if($revision = $requested->get($field))
        <x-revision-field :revision="$revision" :label="$label"><div class="pf"><input name="{{ $field }}" type="{{ $field === 'applicant_email' ? 'email' : ($field === 'applicant_phone' ? 'tel' : 'text') }}" value="{{ old($field, $value) }}" maxlength="{{ $max }}" required></div></x-revision-field>
      @endif
    @endforeach

    @if($revision = $requested->get('workers'))
      <x-revision-field :revision="$revision" label="Penanggung Jawab / Daftar Kerja">
        @php $workers = old('workers', $permit->workers->map->only(['name', 'identity_number'])->all()); @endphp
        <div class="work-worker-list" id="revisionWorkerList">@foreach($workers as $index => $worker)<div class="work-worker-row" data-worker-row><span class="work-worker-number">{{ $loop->iteration }}</span><label><span class="sr-only">Nama penanggung jawab atau pekerja</span><input name="workers[{{ $index }}][name]" value="{{ $worker['name'] ?? '' }}" maxlength="120" placeholder="Nama lengkap"></label><label><span class="sr-only">Nomor ID</span><input name="workers[{{ $index }}][identity_number]" value="{{ $worker['identity_number'] ?? '' }}" maxlength="50" placeholder="Nomor ID (opsional)"></label><button type="button" class="work-worker-remove" data-remove-worker aria-label="Hapus pekerja"><svg><use href="#i-trash"/></svg></button></div>@endforeach</div>
        <button type="button" class="btn-secondary btn-sm" id="revisionAddWorker"><svg><use href="#i-plus"/></svg>Tambah pekerja</button>
      </x-revision-field>
    @endif

    @if($revision = $requested->get('work_schedules'))
      <x-revision-field :revision="$revision" label="Waktu kerja"><div class="pf-radio-group">@foreach(['inside_store' => 'Dalam toko, 22.00 - 10.00 WITA', 'outside_store' => 'Di luar toko, 08.00 - 16.00 WITA'] as $value => $label)<label class="pf-radio"><input type="checkbox" name="work_schedules[]" value="{{ $value }}" @checked(in_array($value, old('work_schedules', $permit->work_schedules), true))><svg><use href="#i-clock"/></svg><span>{{ $label }}</span></label>@endforeach</div></x-revision-field>
    @endif

    @if($revision = $requested->get('work_category'))
      <x-revision-field :revision="$revision" label="Kategori pekerjaan"><div class="pf"><select name="work_category" required>@foreach(\App\Models\WorkPermit::categoryOptions() as $value => $label)<option value="{{ $value }}" @selected(old('work_category', $permit->work_category) === $value)>{{ $label }}</option>@endforeach</select></div></x-revision-field>
    @endif

    @if($revision = $requested->get('work_options'))
      <x-revision-field :revision="$revision" label="Rincian pekerjaan lainnya">
        <div class="pf"><label>Jenis rincian pekerjaan</label><div class="pf-radio-group">@foreach(['fit_out' => 'Fit Out / Renovasi', 'demolition' => 'Pembongkaran', 'other' => 'Lainnya'] as $value => $label)<label class="pf-radio"><input type="radio" name="other_work_type" value="{{ $value }}" @checked(old('other_work_type', $permit->other_work_type) === $value) required><svg><use href="#i-wrench"/></svg><span>{{ $label }}</span></label>@endforeach</div></div>
        <div class="pf"><label for="revision_other_work_description">Rincian khusus</label><input id="revision_other_work_description" name="other_work_description" value="{{ old('other_work_description', $permit->other_work_description) }}" minlength="3" maxlength="180" placeholder="Isi jika memilih Lainnya"></div>
        <div class="work-extra-options"><label><input type="hidden" name="includes_loading_material" value="0"><input type="checkbox" name="includes_loading_material" value="1" @checked(old('includes_loading_material', $permit->includes_loading_material))><span>Termasuk loading / unloading material</span></label><label><input type="hidden" name="is_rooftop_work" value="0"><input type="checkbox" name="is_rooftop_work" value="1" @checked(old('is_rooftop_work', $permit->is_rooftop_work))><span>Pekerjaan di rooftop</span></label><label><input type="hidden" name="is_atm_repair" value="0"><input type="checkbox" name="is_atm_repair" value="1" @checked(old('is_atm_repair', $permit->is_atm_repair))><span>Perbaikan ATM</span></label></div>
        <div class="pf"><label for="revision_rooftop_type">Jenis pekerjaan rooftop</label><select id="revision_rooftop_type" name="rooftop_network_type"><option value="">Tidak dipilih</option>@foreach(['internet_installation' => 'Pemasangan internet', 'bts_repair' => 'Perbaikan BTS', 'ac_cleaning' => 'Maintenance AC'] as $value => $label)<option value="{{ $value }}" @selected(old('rooftop_network_type', $permit->rooftop_network_type) === $value)>{{ $label }}</option>@endforeach</select></div>
      </x-revision-field>
    @endif

    @foreach(['start_date' => ['Tanggal berlaku mulai', $permit->start_date], 'end_date' => ['Tanggal berlaku sampai', $permit->end_date]] as $field => [$label, $value])
      @if($revision = $requested->get($field))<x-revision-field :revision="$revision" :label="$label"><div class="pf"><input name="{{ $field }}" type="date" value="{{ old($field, $value->toDateString()) }}" min="{{ now()->toDateString() }}" required>@if($field === 'end_date')<span class="pf-hint">Batas tanggal selesai mengikuti rincian pekerjaan dan konfigurasi admin.</span>@endif</div></x-revision-field>@endif
    @endforeach

    @if($revision = $requested->get('needs_water'))
      <x-revision-field :revision="$revision" label="Kebutuhan air"><div class="work-extra-options"><label><input type="hidden" name="needs_water" value="0"><input type="checkbox" name="needs_water" value="1" @checked(old('needs_water', $permit->needs_water))><span>Memerlukan air</span></label></div></x-revision-field>
    @endif
    @if($revision = $requested->get('needs_lighting'))
      <x-revision-field :revision="$revision" label="Tambahan penerangan"><div class="work-extra-options"><label><input type="hidden" name="needs_lighting" value="0"><input type="checkbox" name="needs_lighting" value="1" @checked(old('needs_lighting', $permit->needs_lighting))><span>Memerlukan tambahan penerangan</span></label></div></x-revision-field>
    @endif
    @if($revision = $requested->get('notes'))
      <x-revision-field :revision="$revision" label="Catatan pemohon"><div class="pf"><textarea name="notes" rows="3" maxlength="1000">{{ old('notes', $permit->notes) }}</textarea></div></x-revision-field>
    @endif
    @if($revision = $requested->get('id_doc'))
      <x-revision-field :revision="$revision" label="Dokumen KTP / SIM"><div class="pf"><div class="pf-radio-group">@foreach(['ktp' => 'KTP', 'sim' => 'SIM'] as $value => $label)<label class="pf-radio"><input type="radio" name="id_doc_type" value="{{ $value }}" @checked(old('id_doc_type', $permit->id_doc_type) === $value) required><svg><use href="#i-file"/></svg><span>{{ $label }}</span></label>@endforeach</div><input name="id_doc" type="file" accept="image/jpeg,image/jpg,image/png,image/webp" data-optimize-image required><span class="pf-hint">JPG, PNG, atau WebP; maksimal 8 MB.</span></div></x-revision-field>
    @endif
    @if($revision = $requested->get('assignment_letter'))
      <x-revision-field :revision="$revision" label="Surat tugas"><div class="pf"><input name="assignment_letter" type="file" accept="application/pdf,image/jpeg,image/jpg,image/png,image/webp" data-optimize-image required><span class="pf-hint">PDF, JPG, PNG, atau WebP; maksimal 8 MB.</span></div></x-revision-field>
    @endif

    <div class="permit-submit-actions revision-edit-actions"><a href="{{ route('work-permits.status', $permit->applicant_token) }}" class="btn-secondary">Batal</a><button type="submit" class="btn-primary"><svg><use href="#i-check"/></svg>Kirim Perbaikan</button></div>
  </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('form-image.js') }}?v={{ filemtime(public_path('form-image.js')) }}"></script>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
  document.querySelectorAll('[data-optimize-image]').forEach(input => input.addEventListener('change', async () => {
    const file = input.files?.[0];
    input.setCustomValidity('');
    if (!file || file.type === 'application/pdf' || /\.pdf$/i.test(file.name)) return;
    try { await window.prepareUploadImage(input); }
    catch (error) { input.setCustomValidity(error.message || 'Foto tidak dapat diproses.'); input.reportValidity(); }
  }));
  const list = document.getElementById('revisionWorkerList');
  const add = document.getElementById('revisionAddWorker');
  if (!list || !add) return;
  const sync = () => [...list.querySelectorAll('[data-worker-row]')].forEach((row, index, rows) => {
    row.querySelector('.work-worker-number').textContent = index + 1;
    const inputs = row.querySelectorAll('input');
    inputs[0].name = `workers[${index}][name]`;
    inputs[1].name = `workers[${index}][identity_number]`;
    row.querySelector('[data-remove-worker]').disabled = rows.length === 1;
  });
  add.addEventListener('click', () => {
    if (list.querySelectorAll('[data-worker-row]').length >= 50) return;
    const row = document.createElement('div');
    row.className = 'work-worker-row';
    row.dataset.workerRow = '';
    row.innerHTML = '<span class="work-worker-number"></span><label><span class="sr-only">Nama penanggung jawab atau pekerja</span><input maxlength="120" placeholder="Nama lengkap"></label><label><span class="sr-only">Nomor ID</span><input maxlength="50" placeholder="Nomor ID (opsional)"></label><button type="button" class="work-worker-remove" data-remove-worker aria-label="Hapus pekerja"><svg><use href="#i-trash"/></svg></button>';
    list.appendChild(row);
    sync();
    row.querySelector('input').focus();
  });
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-worker]');
    if (!button || list.querySelectorAll('[data-worker-row]').length === 1) return;
    button.closest('[data-worker-row]').remove();
    sync();
  });
  sync();
})();
</script>
@endpush
