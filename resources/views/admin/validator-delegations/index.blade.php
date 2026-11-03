@extends('layouts.admin')

@section('title', 'Validator Pengganti - Admin MBG')
@section('page-title', 'Validator Pengganti')

@section('content')
<div class="page-wrap admin-page-wrap validator-delegation-page">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><span>Perizinan</span><svg><use href="#i-chevron-right"/></svg><span>Validator pengganti</span></div><h1 class="page-title">Jadwal validator pengganti</h1><p class="page-subtitle">Berikan akses divisi sementara tanpa mengubah role atau divisi utama akun.</p></div>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><div><strong>Periksa kembali data:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

  <section class="admin-panel delegation-create-panel">
    <div class="admin-panel-header"><div><h2>Tambah jadwal pengganti</h2><p>Akses mulai dan berakhir otomatis berdasarkan tanggal WITA.</p></div><span class="admin-security-label"><svg><use href="#i-lock"/></svg>Maksimal 90 hari</span></div>
    <form method="POST" action="{{ route('admin.validator-delegations.store') }}" class="delegation-form">
      @csrf
      <div class="delegation-form-grid">
        <label><span>Akun validator</span><select name="validator_id" required><option value="">Pilih validator aktif</option>@foreach($validators as $validator)<option value="{{ $validator->id }}" @selected((string)old('validator_id') === (string)$validator->id)>{{ $validator->name }} · {{ \App\Models\ValidatorDelegation::divisionLabel($validator->division) }}</option>@endforeach</select></label>
        <label><span>Akses divisi sementara</span><select name="division" required><option value="">Pilih divisi</option>@foreach($divisions as $value => $label)<option value="{{ $value }}" @selected(old('division') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label><span>Mulai</span><input type="date" name="starts_on" min="{{ today()->toDateString() }}" value="{{ old('starts_on', today()->toDateString()) }}" required></label>
        <label><span>Selesai</span><input type="date" name="ends_on" min="{{ old('starts_on', today()->toDateString()) }}" value="{{ old('ends_on', today()->addDays(7)->toDateString()) }}" required></label>
        <label class="delegation-form-wide"><span>Alasan penugasan</span><textarea name="reason" rows="3" minlength="10" maxlength="500" required placeholder="Contoh: menggantikan validator Engineering selama cuti.">{{ old('reason') }}</textarea></label>
        <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label>
      </div>
      <div class="delegation-form-actions"><p>Validator hanya memperoleh akses pemeriksaan pada periode yang dipilih. Akses akun asal tetap berlaku.</p><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan jadwal</button></div>
    </form>
  </section>

  <section class="admin-panel delegation-list-panel">
    <div class="admin-panel-header"><div><h2>Riwayat delegasi</h2><p>Jadwal aktif, mendatang, selesai, dan dihentikan tetap tersimpan untuk audit.</p></div></div>
    <div class="admin-table-wrap">
      <table class="admin-table delegation-table">
        <thead><tr><th>Validator</th><th>Akses sementara</th><th>Periode</th><th>Status</th><th>Alasan</th><th>Tindakan</th></tr></thead>
        <tbody>
          @forelse($delegations as $delegation)
            @php
              $status = $delegation->revoked_at ? 'Dihentikan' : ($delegation->starts_on->gt(today()) ? 'Terjadwal' : ($delegation->ends_on->lt(today()) ? 'Selesai' : 'Aktif'));
              $tone = $status === 'Aktif' ? 'approved' : ($status === 'Terjadwal' ? 'pending' : ($status === 'Dihentikan' ? 'rejected' : 'neutral'));
            @endphp
            <tr>
              <td data-label="Validator"><strong>{{ $delegation->validator?->name ?: 'Akun dihapus' }}</strong><small>Divisi utama: {{ \App\Models\ValidatorDelegation::divisionLabel($delegation->validator?->division ?? '-') }}</small></td>
              <td data-label="Akses sementara">{{ \App\Models\ValidatorDelegation::divisionLabel($delegation->division) }}</td>
              <td data-label="Periode">{{ $delegation->starts_on->format('d M Y') }}<br>s.d. {{ $delegation->ends_on->format('d M Y') }}</td>
              <td data-label="Status"><span class="admin-status admin-status--{{ $tone }}">{{ $status }}</span></td>
              <td data-label="Alasan"><span class="delegation-reason">{{ $delegation->reason }}</span><small>Dibuat oleh {{ $delegation->creator?->name ?: 'Akun dihapus' }}</small></td>
              <td data-label="Tindakan">
                @if(!$delegation->revoked_at && !$delegation->ends_on->lt(today()))
                  <form method="POST" action="{{ route('admin.validator-delegations.revoke', $delegation) }}" class="delegation-revoke-form" data-confirm="Hentikan akses validator pengganti ini sekarang?">@csrf @method('PATCH')<input type="password" name="current_password" placeholder="Kata sandi" autocomplete="current-password" required><button type="submit" class="admin-status-button danger">Hentikan</button></form>
                @else
                  <span class="delegation-finished">Tidak ada tindakan</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6"><div class="admin-table-empty"><svg><use href="#i-user"/></svg><strong>Belum ada validator pengganti</strong><span>Jadwal yang dibuat akan muncul di tabel ini.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($delegations->hasPages())<div class="admin-pagination">{{ $delegations->links() }}</div>@endif
  </section>
</div>
@endsection
