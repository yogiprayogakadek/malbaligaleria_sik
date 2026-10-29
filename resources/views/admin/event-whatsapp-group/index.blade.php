@extends('layouts.admin')

@section('title', 'Notifikasi Grup Event : Admin MBG')
@section('page-title', 'Notifikasi Grup Event')

@section('content')
<div class="page-wrap admin-page-wrap event-group-page">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><span>Komunikasi</span><svg><use href="#i-chevron-right"/></svg><span>Grup Event</span></div><h1 class="page-title">Notifikasi grup Surat Izin Event</h1><p class="page-subtitle">Pesan dan PDF resmi hanya dikirim satu kali setelah EP menyetujui permohonan.</p></div>
    <span class="admin-live-state {{ $setting?->is_active ? 'is-open' : 'is-closed' }}"><span></span>{{ $setting?->is_active ? 'Pengiriman aktif' : 'Pengiriman nonaktif' }}</span>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><div><strong>Periksa konfigurasi berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

  <div class="event-group-scope-note"><svg><use href="#i-shield-check"/></svg><div><strong>Trigger tunggal: surat disetujui</strong><p>Revisi, penolakan, pengajuan baru, dan perubahan status lainnya tidak dikirim ke grup. Jika salah satu tahap gagal, sistem hanya mengulang bagian yang belum terkirim.</p></div></div>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Grup tujuan dan pesan utama</h2><p>Daftar grup hanya diambil saat diminta, bukan melalui polling. API key tetap tersimpan di server.</p></div><span class="admin-security-label"><svg><use href="#i-lock"/></svg>{{ $whatsappReady ? 'WAHA siap' : 'WAHA belum aktif' }}</span></div>
    <div class="event-group-directory-bar">
      <div>
        <strong>Direktori grup WAHA</strong>
        <span>
          @if($groupsSyncedAt)
            {{ count($syncedGroups) }} grup · diperbarui {{ \Carbon\Carbon::parse($groupsSyncedAt)->diffForHumans() }}
          @else
            Belum pernah disinkronkan
          @endif
        </span>
      </div>
      <div class="event-group-directory-actions">
        <form action="{{ route('admin.event-whatsapp-group.sync') }}" method="POST">@csrf<button type="submit" class="admin-secondary-button" @disabled(!$whatsappReady)><svg><use href="#i-search"/></svg>Sinkronkan grup</button></form>
        <form action="{{ route('admin.event-whatsapp-group.outbound-only') }}" method="POST" data-confirm="WAHA akan mengabaikan event dan penyimpanan pesan grup. Sesi dapat tersambung ulang sesaat. Lanjutkan?">@csrf<button type="submit" class="admin-secondary-button" @disabled(!$whatsappReady)><svg><use href="#i-lock"/></svg>Aktifkan outbound-only</button></form>
      </div>
    </div>
    <p class="event-group-directory-note">Mode outbound-only tidak menghapus akun dari grup. WAHA berhenti menyimpan dan meneruskan event grup, sementara pengiriman pesan tetap tersedia.</p>
    <form action="{{ route('admin.event-whatsapp-group.update') }}" method="POST" class="admin-form">
      @csrf @method('PUT')
      <div class="event-group-config-grid">
        <label class="event-group-directory-select"><span>Pilih grup dari WAHA</span><select name="synced_group_id" data-event-group-select><option value="">Gunakan ID grup manual</option>@foreach($syncedGroups as $group)<option value="{{ $group['id'] }}" data-name="{{ $group['name'] }}" @selected(old('synced_group_id', $setting?->group_id) === $group['id'])>{{ $group['name'] }}</option>@endforeach</select><small>Daftar berlaku 24 jam. Tekan sinkronkan jika grup baru belum terlihat.</small></label>
        <label><span>Nama grup</span><input name="group_name" value="{{ old('group_name', $setting?->group_name) }}" maxlength="120" placeholder="Contoh: Koordinasi Event MBG" data-event-group-name><small>Dipakai sebagai fallback jika grup belum dapat disinkronkan.</small></label>
        <label><span>ID grup manual</span><input name="group_id" value="{{ old('group_id', $setting?->group_id) }}" maxlength="120" placeholder="120363000000000000@g.us" autocomplete="off" data-event-group-id><small>Isi hanya jika memilih “Gunakan ID grup manual”.</small></label>
        <label class="event-group-template-field"><span>Pesan persetujuan</span><textarea name="base_message" rows="13" minlength="10" maxlength="3000" required>{{ old('base_message', $setting?->base_message ?? \App\Models\EventWhatsappGroupSetting::DEFAULT_MESSAGE) }}</textarea><small>Pesan tambahan dari rule masuk melalui <code>@{{conditional_messages}}</code>. Mention masuk melalui <code>@{{mentions}}</code>.</small></label>
      </div>
      <div class="event-group-variable-list" aria-label="Variabel template">@foreach($variables as $key => $label)<span><code>{{ chr(123).chr(123).$key.chr(125).chr(125) }}</code>{{ $label }}</span>@endforeach</div>
      <div class="admin-mail-options"><label class="admin-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $setting?->is_active ?? false))><span>Aktifkan pengiriman surat Event yang disetujui ke grup</span></label></div>
      <div class="admin-form-actions"><button class="admin-primary-button" type="submit"><svg><use href="#i-check"/></svg>Simpan konfigurasi grup</button></div>
    </form>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Limiter koneksi WAHA</h2><p>Batasi sinkronisasi grup dan perubahan mode sesi untuk mencegah permintaan berulang ke server WAHA.</p></div><span class="admin-security-label"><svg><use href="#i-shield-check"/></svg>Per akun admin</span></div>
    <form action="{{ route('admin.event-whatsapp-group.limiter') }}" method="POST" class="admin-form">
      @csrf @method('PUT')
      <div class="event-group-limiter-grid">
        <label><span>Sinkronisasi · maksimal percobaan</span><input type="number" name="group_sync_max_attempts" value="{{ old('group_sync_max_attempts', $setting?->group_sync_max_attempts ?? 2) }}" min="1" max="30" inputmode="numeric" required><small>Antara 1 sampai 30 permintaan.</small></label>
        <label><span>Sinkronisasi · periode</span><input type="number" name="group_sync_decay_seconds" value="{{ old('group_sync_decay_seconds', $setting?->group_sync_decay_seconds ?? 60) }}" min="10" max="3600" inputmode="numeric" required><small>Antara 10 sampai 3.600 detik.</small></label>
        <label><span>Outbound-only · maksimal percobaan</span><input type="number" name="outbound_max_attempts" value="{{ old('outbound_max_attempts', $setting?->outbound_max_attempts ?? 2) }}" min="1" max="10" inputmode="numeric" required><small>Antara 1 sampai 10 perubahan sesi.</small></label>
        <label><span>Outbound-only · periode</span><input type="number" name="outbound_decay_seconds" value="{{ old('outbound_decay_seconds', $setting?->outbound_decay_seconds ?? 60) }}" min="60" max="86400" inputmode="numeric" required><small>Antara 60 sampai 86.400 detik.</small></label>
        <label class="event-group-limiter-password"><span>Konfirmasi kata sandi admin</span><input type="password" name="current_password" maxlength="255" autocomplete="current-password" required><small>Diperlukan karena pengaturan memengaruhi koneksi WAHA.</small></label>
      </div>
      <div class="admin-form-actions"><button class="admin-primary-button" type="submit" @disabled(!$setting)><svg><use href="#i-check"/></svg>Simpan limiter</button>@unless($setting)<span class="event-group-form-hint">Simpan konfigurasi grup terlebih dahulu.</span>@endunless</div>
    </form>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Anggota yang dapat di-tag</h2><p>Nama dipakai di halaman admin. Sistem melakukan mention menggunakan nomor WhatsApp.</p></div><span class="admin-recipient-count">{{ $members->where('is_active', true)->count() }} aktif</span></div>
    <form action="{{ route('admin.event-whatsapp-group.members.store') }}" method="POST" class="event-group-inline-form">
      @csrf
      <label><span>Nama anggota</span><input name="name" maxlength="120" required placeholder="Nama atau jabatan"></label>
      <label><span>Nomor WhatsApp</span><input name="phone" maxlength="25" required inputmode="tel" placeholder="081234567890"></label>
      <label class="admin-check"><input type="hidden" name="mention_by_default" value="0"><input type="checkbox" name="mention_by_default" value="1"><span>Tag pada setiap persetujuan</span></label>
      <input type="hidden" name="is_active" value="1">
      <button class="admin-primary-button" type="submit"><svg><use href="#i-plus"/></svg>Tambah anggota</button>
    </form>
    <div class="event-group-member-list">
      @forelse($members as $member)
        <div class="event-group-member-row"><span class="admin-validator-avatar">{{ strtoupper(substr($member->name, 0, 1)) }}</span><div><strong>{{ $member->name }}</strong><small>{{ \App\Support\IndonesianPhoneNumber::mask($member->phone) }}{{ $member->mention_by_default ? ' · Selalu di-tag' : '' }}</small></div><span class="admin-status-pill {{ $member->is_active ? 'is-active' : 'is-inactive' }}">{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span><form action="{{ route('admin.event-whatsapp-group.members.destroy', $member) }}" method="POST" data-confirm="Hapus anggota ini dari daftar mention?">@csrf @method('DELETE')<button class="icon-danger-button" type="submit" aria-label="Hapus {{ $member->name }}"><svg><use href="#i-trash"/></svg></button></form></div>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-user"/></svg><strong>Belum ada anggota</strong><span>Tambahkan nomor yang memang menjadi anggota grup WhatsApp tujuan.</span></div>
      @endforelse
    </div>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Rule fasilitas dan akses</h2><p>Rule yang cocok menambahkan pesan dan mention ke satu notifikasi persetujuan yang sama.</p></div></div>
    <form action="{{ route('admin.event-whatsapp-group.rules.store') }}" method="POST" class="event-group-rule-form" data-event-rule-form>
      @csrf
      <label><span>Nama rule</span><input name="name" maxlength="120" required placeholder="Contoh: Koordinasi kelistrikan"></label>
      <label><span>Kelompok kondisi</span><select name="requirement_group" required data-event-rule-group><option value="">Pilih kelompok</option>@foreach($requirements->keys() as $group)<option value="{{ $group }}">{{ $group }}</option>@endforeach</select></label>
      <label><span>Pilihan spesifik</span><select name="event_requirement_id" data-event-rule-requirement><option value="">Semua pilihan dalam kelompok</option>@foreach($requirements as $group => $items)@foreach($items as $item)<option value="{{ $item->id }}" data-group="{{ $group }}">{{ $item->name }}</option>@endforeach @endforeach</select></label>
      <label><span>Urutan</span><input type="number" name="priority" value="100" min="1" max="999" required></label>
      <label class="event-group-rule-message"><span>Pesan tambahan</span><textarea name="message" rows="3" minlength="3" maxlength="1000" required placeholder="Pesan koordinasi yang ditambahkan jika kondisi terpenuhi."></textarea></label>
      <fieldset class="event-group-rule-members"><legend>Anggota yang di-tag</legend><div>@forelse($members->where('is_active', true) as $member)<label class="admin-check"><input type="checkbox" name="member_ids[]" value="{{ $member->id }}"><span>{{ $member->name }}</span></label>@empty<span>Tambahkan anggota terlebih dahulu.</span>@endforelse</div></fieldset>
      <input type="hidden" name="is_active" value="1">
      <div class="event-group-rule-submit"><button class="admin-primary-button" type="submit"><svg><use href="#i-plus"/></svg>Tambah rule</button></div>
    </form>
    <div class="event-group-rule-list">
      @forelse($rules as $rule)
        <article><div><span class="event-group-rule-order">{{ $rule->priority }}</span><h3>{{ $rule->name }}</h3><p>{{ $rule->requirement_group }}: {{ $rule->requirement?->name ?? 'semua pilihan' }}</p></div><p>{{ $rule->message }}</p><div class="event-group-rule-tags">@forelse($rule->members as $member)<span>{{ $member->name }}</span>@empty<span>Tanpa mention tambahan</span>@endforelse</div><form action="{{ route('admin.event-whatsapp-group.rules.destroy', $rule) }}" method="POST" data-confirm="Hapus rule notifikasi ini?">@csrf @method('DELETE')<button class="icon-danger-button" type="submit" aria-label="Hapus {{ $rule->name }}"><svg><use href="#i-trash"/></svg></button></form></article>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-edit"/></svg><strong>Belum ada rule</strong><span>Pesan utama tetap dapat dikirim tanpa rule tambahan.</span></div>
      @endforelse
    </div>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Riwayat pengiriman</h2><p>Status teks dan PDF dicatat terpisah. Nomor anggota dan isi pesan tidak ditampilkan.</p></div></div>
    <div class="admin-table-wrap"><table class="admin-table event-group-log-table"><thead><tr><th>Waktu</th><th>Surat</th><th>EO/Tenant</th><th>Pesan</th><th>PDF</th><th>Tindakan</th></tr></thead><tbody>
      @forelse($deliveries as $delivery)<tr><td>{{ $delivery->created_at->format('d M Y H:i') }}</td><td>{{ $delivery->permit?->permit_number ?? '-' }}</td><td>{{ $delivery->permit?->contractor_name ?? '-' }}</td><td><span class="whatsapp-log-status is-{{ $delivery->text_status }}">{{ ucfirst($delivery->text_status) }}</span></td><td><span class="whatsapp-log-status is-{{ $delivery->file_status }}">{{ ucfirst($delivery->file_status) }}</span></td><td>@if($delivery->text_status !== 'sent' || $delivery->file_status !== 'sent')<form action="{{ route('admin.event-whatsapp-group.deliveries.retry', $delivery) }}" method="POST">@csrf<button class="admin-secondary-button" type="submit">Kirim ulang</button></form>@else<span>{{ $delivery->sent_at?->format('H:i') }}</span>@endif</td></tr>@empty<tr><td colspan="6"><div class="admin-table-empty"><svg><use href="#i-phone"/></svg><strong>Belum ada pengiriman</strong><span>Riwayat muncul setelah Surat Izin Event pertama disetujui.</span></div></td></tr>@endforelse
    </tbody></table></div>
  </section>
</div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(() => {
  const groupSelect = document.querySelector('[data-event-group-select]');
  const groupName = document.querySelector('[data-event-group-name]');
  const groupId = document.querySelector('[data-event-group-id]');
  const applyGroup = () => {
    const option = groupSelect?.selectedOptions[0];
    if (!option?.value || !groupName || !groupId) return;
    groupName.value = option.dataset.name || '';
    groupId.value = option.value;
  };
  groupSelect?.addEventListener('change', applyGroup);
  applyGroup();

  const form = document.querySelector('[data-event-rule-form]');
  const group = form?.querySelector('[data-event-rule-group]');
  const requirement = form?.querySelector('[data-event-rule-requirement]');
  const sync = () => {
    if (!group || !requirement) return;
    [...requirement.options].forEach((option, index) => option.hidden = index > 0 && option.dataset.group !== group.value);
    if (requirement.selectedOptions[0]?.hidden) requirement.value = '';
  };
  group?.addEventListener('change', sync);
  sync();
})();
</script>
@endpush
