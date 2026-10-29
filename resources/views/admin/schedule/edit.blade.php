@extends('layouts.admin')

@section('title', 'Jam Operasional : Admin MBG')
@section('page-title', 'Jam Operasional')

@section('content')
<div class="page-wrap admin-page-wrap page-wrap--narrow">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Jam operasional</span></div><h1 class="page-title">Jam operasional pengajuan</h1><p class="page-subtitle">Atur jadwal dalam Waktu Indonesia Tengah (WITA). Jadwal Event berdiri sendiri dan tidak mengubah layanan lainnya.</p></div>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Periksa kembali jadwal yang ditandai.</span></div>@endif

  <nav class="schedule-scope-nav" aria-label="Jenis jadwal">
    @foreach($scheduleGroups as $scope => $group)
      <a href="#schedule-{{ $scope }}"><span>{{ $group['title'] }}</span><strong class="admin-live-state {{ $group['siteIsOpen'] ? 'is-open' : 'is-closed' }}"><i></i>{{ $group['siteIsOpen'] ? 'Beroperasi' : 'Ditutup' }}</strong></a>
    @endforeach
  </nav>

  @foreach($scheduleGroups as $scope => $group)
    @include('admin.schedule.partials.group', ['scope' => $scope, 'group' => $group])
  @endforeach
</div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  document.querySelectorAll('[data-schedule-row]').forEach(row => {
    const open = row.querySelector('[data-open]');
    const allDay = row.querySelector('[data-all-day]');
    const times = row.querySelectorAll('[data-time]');
    const summary = row.querySelector('[data-schedule-summary]');
    const sync = () => {
      allDay.disabled = !open.checked;
      times.forEach(input => input.disabled = !open.checked || allDay.checked);
      summary.textContent = !open.checked ? 'Tutup' : (allDay.checked ? '24 jam' : `${times[0].value} - ${times[1].value}`);
      row.dataset.closed = String(!open.checked);
    };
    open.addEventListener('change', sync);
    allDay.addEventListener('change', sync);
    times.forEach(input => input.addEventListener('input', sync));
    sync();
  });

  document.querySelectorAll('[data-exception-form]').forEach(form => {
    const open = form.querySelector('[data-exception-open]');
    const allDay = form.querySelector('[data-exception-all-day]');
    const times = form.querySelectorAll('[data-exception-time]');
    const sync = () => {
      allDay.disabled = !open.checked;
      times.forEach(input => input.disabled = !open.checked || allDay.checked);
    };
    open.addEventListener('change', sync);
    allDay.addEventListener('change', sync);
    sync();
  });
</script>
@endpush
