@extends('layouts.admin')

@section('title', 'Jam Operasional : Admin MBG')
@section('page-title', 'Jam Operasional')

@section('content')
<div class="page-wrap admin-page-wrap page-wrap--narrow">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Jam operasional</span></div><h1 class="page-title">Jam operasional portal</h1><p class="page-subtitle">Jadwal menggunakan Waktu Indonesia Tengah (WITA). Rentang yang melewati tengah malam tetap didukung.</p></div>
    <span class="admin-live-state {{ $siteIsOpen ? 'is-open' : 'is-closed' }}"><span></span>{{ $siteIsOpen ? 'Portal beroperasi' : 'Portal ditutup' }}</span>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Periksa kembali jadwal yang ditandai.</span></div>@endif

  <form action="{{ route('admin.schedule.update') }}" method="POST" class="admin-panel admin-schedule-form">
    @csrf @method('PUT')
    <div class="admin-panel-header"><div><h2>Jadwal mingguan</h2><p>Perubahan berlaku segera setelah disimpan.</p></div></div>
    <div class="admin-schedule-list">
      @foreach(config('operating-hours.days') as $dayNumber => $dayLabel)
        @php $schedule = $schedules->get($dayNumber); @endphp
        <div class="admin-schedule-row" data-schedule-row>
          <input type="hidden" name="days[{{ $dayNumber }}][day_of_week]" value="{{ $dayNumber }}">
          <div class="admin-schedule-day"><strong>{{ $dayLabel }}</strong><span data-schedule-summary></span></div>
          <label class="admin-check"><input type="hidden" name="days[{{ $dayNumber }}][is_open]" value="0"><input type="checkbox" name="days[{{ $dayNumber }}][is_open]" value="1" data-open {{ old("days.$dayNumber.is_open", $schedule?->is_open) ? 'checked' : '' }}><span>Buka</span></label>
          <label class="admin-check"><input type="hidden" name="days[{{ $dayNumber }}][is_24_hours]" value="0"><input type="checkbox" name="days[{{ $dayNumber }}][is_24_hours]" value="1" data-all-day {{ old("days.$dayNumber.is_24_hours", $schedule?->is_24_hours) ? 'checked' : '' }}><span>24 jam</span></label>
          <label class="admin-time-field"><span>Buka</span><input type="time" name="days[{{ $dayNumber }}][opens_at]" value="{{ old("days.$dayNumber.opens_at", $schedule?->opens_at ? substr($schedule->opens_at, 0, 5) : '08:00') }}" data-time></label>
          <label class="admin-time-field"><span>Tutup</span><input type="time" name="days[{{ $dayNumber }}][closes_at]" value="{{ old("days.$dayNumber.closes_at", $schedule?->closes_at ? substr($schedule->closes_at, 0, 5) : '22:00') }}" data-time></label>
          @error("days.$dayNumber.opens_at")<span class="admin-field-error schedule-error">{{ $message }}</span>@enderror
          @error("days.$dayNumber.closes_at")<span class="admin-field-error schedule-error">{{ $message }}</span>@enderror
        </div>
      @endforeach
    </div>
    <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan jadwal</button></div>
  </form>

  <section class="admin-panel schedule-exception-panel">
    <div class="admin-panel-header"><div><h2>Tanggal khusus</h2><p>Hari libur, maintenance, atau jam operasional yang berbeda dari jadwal mingguan.</p></div></div>
    <form action="{{ route('admin.schedule.exceptions.store') }}" method="POST" class="schedule-exception-form" data-exception-form>
      @csrf
      <label>Tanggal<input type="date" name="date" value="{{ old('date') }}" min="{{ today()->toDateString() }}" required></label>
      <label>Keterangan<input type="text" name="label" value="{{ old('label') }}" maxlength="120" placeholder="Contoh: Maintenance sistem" required></label>
      <label class="admin-check"><input type="hidden" name="is_open" value="0"><input type="checkbox" name="is_open" value="1" data-exception-open @checked(old('is_open'))><span>Portal dibuka</span></label>
      <label class="admin-check"><input type="hidden" name="is_24_hours" value="0"><input type="checkbox" name="is_24_hours" value="1" data-exception-all-day @checked(old('is_24_hours'))><span>24 jam</span></label>
      <label class="admin-time-field"><span>Buka</span><input type="time" name="opens_at" value="{{ old('opens_at', '08:00') }}" data-exception-time></label>
      <label class="admin-time-field"><span>Tutup</span><input type="time" name="closes_at" value="{{ old('closes_at', '22:00') }}" data-exception-time></label>
      <button type="submit" class="admin-primary-button"><svg><use href="#i-plus"/></svg>Tambah tanggal</button>
      @error('date')<span class="admin-field-error exception-form-error">{{ $message }}</span>@enderror
      @error('label')<span class="admin-field-error exception-form-error">{{ $message }}</span>@enderror
      @error('opens_at')<span class="admin-field-error exception-form-error">{{ $message }}</span>@enderror
      @error('closes_at')<span class="admin-field-error exception-form-error">{{ $message }}</span>@enderror
    </form>

    <div class="schedule-exception-list">
      @forelse($exceptions as $exception)
        <div class="schedule-exception-row">
          <time datetime="{{ $exception->date->toDateString() }}"><strong>{{ $exception->date->format('d M Y') }}</strong><span>{{ $exception->date->translatedFormat('l') }}</span></time>
          <div><strong>{{ $exception->label }}</strong><span>{{ ! $exception->is_open ? 'Portal ditutup' : ($exception->is_24_hours ? 'Buka 24 jam' : substr($exception->opens_at, 0, 5).' - '.substr($exception->closes_at, 0, 5).' WITA') }}</span></div>
          <form action="{{ route('admin.schedule.exceptions.destroy', $exception) }}" method="POST" data-confirm="Hapus jadwal khusus ini?">
            @csrf @method('DELETE')
            <button type="submit" class="icon-danger-button" aria-label="Hapus {{ $exception->label }}" title="Hapus"><svg><use href="#i-trash"/></svg></button>
          </form>
        </div>
      @empty
        <div class="admin-table-empty schedule-exception-empty"><svg><use href="#i-calendar"/></svg><strong>Belum ada tanggal khusus</strong><span>Jadwal mingguan digunakan untuk semua tanggal.</span></div>
      @endforelse
    </div>
  </section>
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

  const exceptionForm = document.querySelector('[data-exception-form]');
  if (exceptionForm) {
    const open = exceptionForm.querySelector('[data-exception-open]');
    const allDay = exceptionForm.querySelector('[data-exception-all-day]');
    const times = exceptionForm.querySelectorAll('[data-exception-time]');
    const syncException = () => {
      allDay.disabled = !open.checked;
      times.forEach(input => input.disabled = !open.checked || allDay.checked);
    };
    open.addEventListener('change', syncException);
    allDay.addEventListener('change', syncException);
    syncException();
  }
</script>
@endpush
