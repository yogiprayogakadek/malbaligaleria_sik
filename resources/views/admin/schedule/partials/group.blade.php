@php
  $schedules = $group['schedules'];
  $exceptions = $group['exceptions'];
  $useOld = old('scope') === $scope;
@endphp

<section class="schedule-scope-section" id="schedule-{{ $scope }}">
  <div class="schedule-scope-heading">
    <div><h2>{{ $group['title'] }}</h2><p>{{ $group['description'] }}</p></div>
    <span class="admin-live-state {{ $group['siteIsOpen'] ? 'is-open' : 'is-closed' }}"><span></span>{{ $group['siteIsOpen'] ? 'Sedang beroperasi' : 'Sedang ditutup' }}</span>
  </div>

  <form action="{{ route('admin.schedule.update') }}" method="POST" class="admin-panel admin-schedule-form">
    @csrf @method('PUT')
    <input type="hidden" name="scope" value="{{ $scope }}">
    <div class="admin-panel-header"><div><h3>Jadwal mingguan</h3><p>Perubahan hanya berlaku untuk {{ $group['title'] }}.</p></div></div>
    <div class="admin-schedule-list">
      @foreach(config('operating-hours.days') as $dayNumber => $dayLabel)
        @php $schedule = $schedules->get($dayNumber); @endphp
        <div class="admin-schedule-row" data-schedule-row>
          <input type="hidden" name="days[{{ $dayNumber }}][day_of_week]" value="{{ $dayNumber }}">
          <div class="admin-schedule-day"><strong>{{ $dayLabel }}</strong><span data-schedule-summary></span></div>
          <label class="admin-check"><input type="hidden" name="days[{{ $dayNumber }}][is_open]" value="0"><input type="checkbox" name="days[{{ $dayNumber }}][is_open]" value="1" data-open @checked($useOld ? old("days.$dayNumber.is_open") : $schedule?->is_open)><span>Buka</span></label>
          <label class="admin-check"><input type="hidden" name="days[{{ $dayNumber }}][is_24_hours]" value="0"><input type="checkbox" name="days[{{ $dayNumber }}][is_24_hours]" value="1" data-all-day @checked($useOld ? old("days.$dayNumber.is_24_hours") : $schedule?->is_24_hours)><span>24 jam</span></label>
          <label class="admin-time-field"><span>Buka</span><input type="time" name="days[{{ $dayNumber }}][opens_at]" value="{{ $useOld ? old("days.$dayNumber.opens_at", '08:00') : ($schedule?->opens_at ? substr($schedule->opens_at, 0, 5) : '08:00') }}" data-time></label>
          <label class="admin-time-field"><span>Tutup</span><input type="time" name="days[{{ $dayNumber }}][closes_at]" value="{{ $useOld ? old("days.$dayNumber.closes_at", '22:00') : ($schedule?->closes_at ? substr($schedule->closes_at, 0, 5) : '22:00') }}" data-time></label>
          @if($useOld)
            @error("days.$dayNumber.opens_at")<span class="admin-field-error schedule-error">{{ $message }}</span>@enderror
            @error("days.$dayNumber.closes_at")<span class="admin-field-error schedule-error">{{ $message }}</span>@enderror
          @endif
        </div>
      @endforeach
    </div>
    <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan jadwal {{ $scope === 'event' ? 'Event' : 'umum' }}</button></div>
  </form>

  <div class="admin-panel schedule-exception-panel">
    <div class="admin-panel-header"><div><h3>Tanggal khusus</h3><p>Hari libur, maintenance, atau jam berbeda untuk {{ $group['title'] }}.</p></div></div>
    <form action="{{ route('admin.schedule.exceptions.store') }}" method="POST" class="schedule-exception-form" data-exception-form>
      @csrf
      <input type="hidden" name="scope" value="{{ $scope }}">
      <label>Tanggal<input type="date" name="date" value="{{ $useOld ? old('date') : '' }}" min="{{ today()->toDateString() }}" required></label>
      <label>Keterangan<input type="text" name="label" value="{{ $useOld ? old('label') : '' }}" maxlength="120" placeholder="Contoh: Maintenance sistem" required></label>
      <label class="admin-check"><input type="hidden" name="is_open" value="0"><input type="checkbox" name="is_open" value="1" data-exception-open @checked($useOld && old('is_open'))><span>Layanan dibuka</span></label>
      <label class="admin-check"><input type="hidden" name="is_24_hours" value="0"><input type="checkbox" name="is_24_hours" value="1" data-exception-all-day @checked($useOld && old('is_24_hours'))><span>24 jam</span></label>
      <label class="admin-time-field"><span>Buka</span><input type="time" name="opens_at" value="{{ $useOld ? old('opens_at', '08:00') : '08:00' }}" data-exception-time></label>
      <label class="admin-time-field"><span>Tutup</span><input type="time" name="closes_at" value="{{ $useOld ? old('closes_at', '22:00') : '22:00' }}" data-exception-time></label>
      <button type="submit" class="admin-primary-button"><svg><use href="#i-plus"/></svg>Tambah tanggal</button>
      @if($useOld)
        @error('date')<span class="admin-field-error exception-form-error">{{ $message }}</span>@enderror
        @error('label')<span class="admin-field-error exception-form-error">{{ $message }}</span>@enderror
        @error('opens_at')<span class="admin-field-error exception-form-error">{{ $message }}</span>@enderror
        @error('closes_at')<span class="admin-field-error exception-form-error">{{ $message }}</span>@enderror
      @endif
    </form>

    <div class="schedule-exception-list">
      @forelse($exceptions as $exception)
        <div class="schedule-exception-row">
          <time datetime="{{ $exception->date->toDateString() }}"><strong>{{ $exception->date->format('d M Y') }}</strong><span>{{ $exception->date->translatedFormat('l') }}</span></time>
          <div><strong>{{ $exception->label }}</strong><span>{{ ! $exception->is_open ? 'Layanan ditutup' : ($exception->is_24_hours ? 'Buka 24 jam' : substr($exception->opens_at, 0, 5).' - '.substr($exception->closes_at, 0, 5).' WITA') }}</span></div>
          <form action="{{ route('admin.schedule.exceptions.destroy', $exception) }}" method="POST" data-confirm="Hapus jadwal khusus ini?">
            @csrf @method('DELETE')
            <button type="submit" class="icon-danger-button" aria-label="Hapus {{ $exception->label }}" title="Hapus"><svg><use href="#i-trash"/></svg></button>
          </form>
        </div>
      @empty
        <div class="admin-table-empty schedule-exception-empty"><svg><use href="#i-calendar"/></svg><strong>Belum ada tanggal khusus</strong><span>Jadwal mingguan digunakan untuk semua tanggal.</span></div>
      @endforelse
    </div>
  </div>
</section>
