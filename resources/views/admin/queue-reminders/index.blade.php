@extends('layouts.admin')

@section('title', 'Pengingat Antrean - Admin MBG')
@section('page-title', 'Pengingat Antrean')

@section('content')
<div class="page-wrap admin-page-wrap queue-reminder-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Komunikasi</span><svg><use href="#i-chevron-right"/></svg><span>Pengingat antrean</span></div>
      <h1 class="page-title">Kirim ulang notifikasi validator</h1>
      <p class="page-subtitle">Pilih antrean yang perlu segera diperiksa. Antrean yang sama hanya dapat diingatkan sekali dalam lima menit.</p>
    </div>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  <nav class="admin-status-tabs" aria-label="Jenis antrean">
    @foreach(['all' => 'Semua', 'loading' => 'Loading / unloading', 'work' => 'Izin kerja'] as $value => $label)
      <a href="{{ route('admin.queue-reminders.index', ['type' => $value]) }}" @class(['active' => $type === $value])><span>{{ $label }}</span><strong>{{ $counts[$value] }}</strong></a>
    @endforeach
  </nav>

  <form method="POST" action="{{ route('admin.queue-reminders.store') }}" class="admin-panel queue-reminder-form">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="queue-reminder-toolbar">
      <label class="queue-select-all"><input type="checkbox" data-queue-select-all><span>Pilih semua pada halaman ini</span></label>
      <span>{{ $items->total() }} antrean menunggu tindakan</span>
    </div>
    <div class="admin-table-wrap queue-reminder-table-wrap">
      <table class="admin-table queue-reminder-table">
        <thead><tr><th aria-label="Pilih"></th><th>Permohonan</th><th>Jenis</th><th>Divisi</th><th>Status</th><th>Masuk antrean</th></tr></thead>
        <tbody>
          @forelse($items as $item)
            <tr>
              <td class="queue-reminder-check"><input type="checkbox" name="queue_items[]" value="{{ $item['key'] }}" data-queue-item aria-label="Pilih {{ $item['permit']->permit_number }}"></td>
              <td data-label="Permohonan"><strong>{{ $item['permit']->permit_number }}</strong><span>{{ $item['permit'] instanceof \App\Models\LoadingPermit ? $item['permit']->tenant_name : $item['permit']->contractor_name }}</span></td>
              <td data-label="Jenis">{{ $item['type'] }}</td>
              <td data-label="Divisi"><span class="admin-status admin-status--pending">{{ $item['division'] }}</span></td>
              <td data-label="Status">{{ $item['permit']->status_label }}</td>
              <td data-label="Masuk antrean"><time>{{ $item['permit']->sla_started_at?->format('d M Y, H:i') ?? '-' }} WITA</time></td>
            </tr>
          @empty
            <tr class="queue-reminder-empty-row"><td colspan="6"><div class="admin-table-empty"><svg><use href="#i-check"/></svg><strong>Tidak ada antrean aktif</strong><span>Permohonan yang menunggu validator akan muncul di sini.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($items->hasPages())<div class="admin-pagination">{{ $items->links() }}</div>@endif
    @if($items->total() > 0)
      <div class="queue-reminder-compose-header"><div><strong>Pengiriman pengingat</strong><span>Pesan dikirim sebagai notifikasi realtime dan push ke validator terkait.</span></div><span class="admin-security-label"><svg><use href="#i-lock"/></svg>Terverifikasi</span></div>
      <div class="queue-reminder-compose">
        <label><span>Pesan tambahan <small>Opsional</small></span><textarea name="message" rows="3" maxlength="500" placeholder="Contoh: Mohon prioritaskan pemeriksaan antrean hari ini.">{{ old('message') }}</textarea></label>
        <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label>
      </div>
      <div class="admin-form-actions queue-reminder-actions">
        <button name="selection_mode" value="selected" class="admin-primary-button"><svg><use href="#i-mail"/></svg>Kirim ke yang dipilih</button>
        <button name="selection_mode" value="all" class="admin-secondary-button"><svg><use href="#i-bell"/></svg>Kirim ke semua hasil filter</button>
      </div>
    @endif
  </form>
</div>
@endsection
