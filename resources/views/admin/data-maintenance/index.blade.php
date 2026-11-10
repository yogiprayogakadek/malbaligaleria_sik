@extends('layouts.admin')

@section('title', 'Manajemen Data - Admin MBG')
@section('page-title', 'Manajemen Data')

@section('content')
<div class="page-wrap admin-page-wrap data-maintenance-page">
  <div class="page-header admin-page-header"><div><div class="page-breadcrumb"><span>Sistem</span><svg><use href="#i-chevron-right"/></svg><span>Manajemen data</span></div><h1 class="page-title">Penghapusan data operasional</h1><p class="page-subtitle">Hapus data tertentu atau seluruh isi dataset yang diizinkan. Tindakan tidak dapat dibatalkan.</p></div></div>
  <div class="permit-alert permit-alert--warning"><svg><use href="#i-info"/></svg><span>Akun, konfigurasi, audit log, serta histori keamanan tidak dapat dihapus dari halaman ini.</span></div>
  @if(in_array($dataset, ['email_logs', 'whatsapp_logs'], true))<div class="permit-alert permit-alert--info"><svg><use href="#i-info"/></svg><span>Log yang masih mengantre atau sedang diproses disembunyikan dan tidak dapat dihapus.</span></div>@endif
  @if(session('success'))<div class="permit-alert permit-alert--success"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if(session('production_reset_result') && session('production_reset_result.storage_cleanup_failed'))<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>Database sudah direset, tetapi beberapa direktori dokumen belum dapat dibersihkan. Periksa log server sebelum membuka layanan publik.</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  <nav class="data-maintenance-tabs" aria-label="Dataset yang dapat dikelola">
    @foreach($datasets as $key => $definition)
      <a href="{{ route('admin.data-maintenance.index', ['dataset' => $key]) }}" @class(['active' => $dataset === $key])><span>{{ $definition['label'] }}</span><strong>{{ $counts[$key] }}</strong></a>
    @endforeach
  </nav>

  <form method="POST" action="{{ route('admin.data-maintenance.destroy') }}" class="admin-panel data-maintenance-selection" data-confirm="Hapus data yang dipilih secara permanen?">
    @csrf @method('DELETE')
    <input type="hidden" name="dataset" value="{{ $dataset }}"><input type="hidden" name="delete_mode" value="selected">
    <div class="data-maintenance-toolbar"><label><input type="checkbox" data-maintenance-select-all><span>Pilih semua pada halaman ini</span></label><span>Maksimal 100 data per penghapusan</span></div>
    <div class="admin-table-wrap data-maintenance-table-wrap">
      <table class="admin-table data-maintenance-table">
        <thead><tr><th></th><th>Data</th><th>Keterangan</th><th>Status</th><th>Dibuat</th></tr></thead>
        <tbody>
          @forelse($records as $record)
            <tr>
              <td class="data-maintenance-check"><input type="checkbox" name="record_ids[]" value="{{ $record->id }}" data-maintenance-item aria-label="Pilih data {{ $record->id }}"></td>
              @switch($dataset)
                @case('loading')
                  <td data-label="Data"><strong>{{ $record->permit_number }}</strong><span>{{ $record->tenant_name }}</span></td><td data-label="Keterangan">{{ $record->direction_label }}</td><td data-label="Status"><span class="admin-status admin-status--{{ $record->status }}">{{ $record->status_label }}</span></td>
                  @break
                @case('work')
                  <td data-label="Data"><strong>{{ $record->permit_number }}</strong><span>{{ $record->contractor_name }}</span></td><td data-label="Keterangan">{{ $record->assigned_division === 'MEP' ? 'Engineering' : $record->assigned_division }} · {{ $record->work_category_label }}</td><td data-label="Status"><span class="admin-status admin-status--{{ $record->status }}">{{ $record->status_label }}</span></td>
                  @break
                @case('notifications')
                  <td data-label="Data"><strong>#{{ $record->id }} · {{ $record->title }}</strong><span>User ID {{ $record->user_id }}</span></td><td data-label="Keterangan">{{ Str::limit($record->body, 70) }}</td><td data-label="Status"><span class="admin-status admin-status--{{ $record->read_at ? 'approved' : 'pending' }}">{{ $record->read_at ? 'Dibaca' : 'Belum dibaca' }}</span></td>
                  @break
                @case('email_logs')
                  <td data-label="Data"><strong>#{{ $record->id }} · {{ $record->subject ?: 'Email sistem' }}</strong><span>{{ class_basename($record->notification_type) }}</span></td><td data-label="Keterangan">Percobaan {{ $record->attempts }}</td><td data-label="Status"><span class="admin-status admin-status--{{ $record->status === 'sent' ? 'approved' : 'rejected' }}">{{ ucfirst($record->status) }}</span></td>
                  @break
                @default
                  <td data-label="Data"><strong>#{{ $record->id }} · {{ $record->event }}</strong><span>{{ ucfirst($record->recipient_kind) }}</span></td><td data-label="Keterangan">{{ $record->permit_type }} #{{ $record->permit_id }}</td><td data-label="Status"><span class="admin-status admin-status--{{ $record->status === 'sent' ? 'approved' : 'pending' }}">{{ ucfirst($record->status) }}</span></td>
              @endswitch
              <td data-label="Dibuat"><time>{{ $record->created_at?->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') ?? '-' }} WITA</time></td>
            </tr>
          @empty
            <tr class="data-maintenance-empty"><td colspan="5"><div class="admin-table-empty"><svg><use href="#i-trash"/></svg><strong>Dataset ini kosong</strong><span>Tidak ada data yang dapat dihapus.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($records->hasPages())<div class="admin-pagination">{{ $records->links() }}</div>@endif
    @if($records->total() > 0)
      <div class="data-maintenance-confirm-grid"><label><span>Alasan penghapusan</span><textarea name="reason" rows="2" minlength="10" maxlength="500" required></textarea></label><label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label></div>
      <div class="admin-form-actions"><button class="admin-danger-button"><svg><use href="#i-trash"/></svg>Hapus data terpilih</button></div>
    @endif
  </form>

  @if($records->total() > 0)
    <section class="admin-panel data-maintenance-danger">
      <div class="admin-panel-header"><div><h2>Hapus seluruh dataset</h2><p>Semua {{ strtolower($datasets[$dataset]['label']) }} akan dihapus permanen, termasuk dokumen yang terkait.</p></div><span class="admin-security-label"><svg><use href="#i-lock"/></svg>Konfirmasi ganda</span></div>
      <form method="POST" action="{{ route('admin.data-maintenance.destroy') }}" data-confirm="Seluruh isi dataset akan dihapus permanen. Lanjutkan?">
        @csrf @method('DELETE')
        <input type="hidden" name="dataset" value="{{ $dataset }}"><input type="hidden" name="delete_mode" value="all">
        <div class="data-maintenance-confirm-grid"><label><span>Ketik HAPUS SEMUA</span><input name="confirmation" autocomplete="off" required></label><label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label><label class="data-maintenance-wide"><span>Alasan penghapusan</span><textarea name="reason" rows="2" minlength="10" maxlength="500" required></textarea></label></div>
        <div class="admin-form-actions"><button class="admin-danger-button"><svg><use href="#i-trash"/></svg>Hapus seluruh dataset</button></div>
      </form>
    </section>
  @endif

  @if($productionResetEnabled)
    <section class="admin-panel data-maintenance-danger production-reset-danger">
      <div class="admin-panel-header">
        <div>
          <h2>Reset data untuk go-live</h2>
          <p>Gunakan satu kali sebelum layanan produksi dibuka. Sistem membuat dan memverifikasi backup terenkripsi sebelum menghapus data.</p>
        </div>
        <span class="admin-security-label"><svg><use href="#i-lock"/></svg>Reset satu kali</span>
      </div>

      @if($productionResetCompleted)
        <div class="production-reset-complete" role="status">
          <svg aria-hidden="true"><use href="#i-check"/></svg>
          <div>
            <strong>Reset go-live telah selesai</strong>
            <p>Dijalankan pada {{ $productionResetCompleted->created_at?->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }} WITA. Matikan <code>PRODUCTION_DATA_RESET_ENABLED</code> pada server.</p>
          </div>
        </div>
      @else
        <div class="production-reset-summary">
          <div>
            <h3>Data yang dihapus</h3>
            <ul>
              <li>Permohonan, dokumen, notifikasi, dan histori proses</li>
              <li>Akun non-admin, tenant, delegasi, sesi, dan perangkat terkait</li>
              <li>Log komunikasi, scanner, antrean, alert, dan counter nomor surat</li>
            </ul>
          </div>
          <div>
            <h3>Data yang dipertahankan</h3>
            <ul>
              <li>Seluruh akun admin, 2FA, audit log, dan rantai integritas</li>
              <li>Konfigurasi WAHA, email, scanner, jadwal, SLA, dan keamanan</li>
              <li>Petugas scanner, aturan grup Event, pola nomor, dan template dokumen</li>
            </ul>
          </div>
        </div>

        @unless($backupConfigured)
          <div class="permit-alert permit-alert--error production-reset-readiness"><svg><use href="#i-info"/></svg><span>Reset dikunci. Aktifkan backup dan pasang kunci enkripsi yang valid pada server.</span></div>
        @endunless

        <form method="POST" action="{{ route('admin.data-maintenance.production-reset') }}" class="production-reset-form" data-confirm="Backup akan dibuat sebelum seluruh data operasional dihapus permanen. Lanjutkan?" data-disable-submit data-staff-action-progress="Membuat backup dan mereset data">
          @csrf @method('DELETE')
          <div class="data-maintenance-confirm-grid">
            <label>
              <span>Ketik RESET PRODUKSI</span>
              <input name="confirmation" value="{{ old('confirmation') }}" autocomplete="off" aria-describedby="production-reset-confirmation-help" required>
              <small id="production-reset-confirmation-help">Huruf dan spasi harus sama persis.</small>
              @error('confirmation')<span class="admin-field-error" role="alert">{{ $message }}</span>@enderror
            </label>
            <label>
              <span>Kata sandi admin</span>
              <input type="password" name="current_password" autocomplete="current-password" required>
              @error('current_password')<span class="admin-field-error" role="alert">{{ $message }}</span>@enderror
            </label>
            <label class="data-maintenance-wide">
              <span>Alasan reset</span>
              <textarea name="reason" rows="3" minlength="10" maxlength="500" required>{{ old('reason') }}</textarea>
              @error('reason')<span class="admin-field-error" role="alert">{{ $message }}</span>@enderror
              @error('production_reset')<span class="admin-field-error" role="alert">{{ $message }}</span>@enderror
            </label>
          </div>
          <div class="admin-form-actions production-reset-actions">
            <p>Jalankan saat pengajuan publik dan queue worker sudah dihentikan.</p>
            <button class="admin-danger-button" @disabled(!$backupConfigured)><svg><use href="#i-trash"/></svg>Buat backup dan reset data</button>
          </div>
        </form>
      @endif
    </section>
  @endif
</div>
@endsection
