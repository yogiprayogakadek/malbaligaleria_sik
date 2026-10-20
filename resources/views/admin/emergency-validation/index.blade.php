@extends('layouts.admin')

@section('title', 'Validasi Darurat - Admin MBG')
@section('page-title', 'Validasi Darurat')

@section('content')
<div class="page-wrap admin-page-wrap emergency-validation-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Perizinan</span><svg><use href="#i-chevron-right"/></svg><span>Validasi darurat</span></div>
      <h1 class="page-title">Keputusan pengganti validator</h1>
      <p class="page-subtitle">Gunakan hanya saat divisi terkait tidak dapat memproses antrean. Alasan dan identitas admin disimpan pada audit.</p>
    </div>
  </div>

  <div class="permit-alert permit-alert--warning"><svg><use href="#i-info"/></svg><span>Tindakan pada halaman ini langsung mengubah status permohonan dan mengirim pemberitahuan kepada pemohon.</span></div>
  @if(session('success'))<div class="permit-alert permit-alert--success"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Antrean yang dapat divalidasi</h2><p>{{ $items->count() }} permohonan menunggu keputusan divisi.</p></div><span class="admin-security-label"><svg><use href="#i-lock"/></svg>Konfirmasi per tindakan</span></div>
    <div class="emergency-validation-list">
      @forelse($items as $item)
        @php($permit = $item['permit'])
        <details class="emergency-validation-item" @if($errors->any() && old('permit_key') === $item['kind'].':'.$permit->id) open @endif>
          <summary>
            <span class="emergency-validation-type"><svg><use href="#{{ $item['kind'] === 'loading' ? 'i-truck' : 'i-wrench' }}"/></svg></span>
            <span><strong>{{ $permit->permit_number }}</strong><small>{{ $item['applicant'] ?: 'Pemohon tanpa nama tenant' }} · {{ $item['kind'] === 'loading' ? 'Loading / unloading' : 'Surat izin kerja' }}</small></span>
            <span class="admin-status admin-status--pending">{{ $item['division'] }} · {{ $permit->status_label }}</span>
            <svg class="emergency-validation-chevron"><use href="#i-chevron-right"/></svg>
          </summary>
          <form method="POST" action="{{ $item['kind'] === 'loading' ? route('admin.emergency-validation.loading', $permit) : route('admin.emergency-validation.work', $permit) }}" class="emergency-validation-form" data-emergency-form>
            @csrf
            <input type="hidden" name="permit_key" value="{{ $item['kind'] }}:{{ $permit->id }}">
            <label><span>Keputusan</span><select name="decision" required data-emergency-decision>
              <option value="">Pilih keputusan</option>
              @if($item['kind'] === 'work' && $permit->status === 'mep_review')
                <option value="approved">Setujui tanpa deposit</option><option value="require_deposit">Minta security deposit</option><option value="rejected">Tolak permohonan</option>
              @elseif($item['kind'] === 'work' && $permit->status === 'payment_review')
                <option value="verified">Pembayaran terverifikasi</option><option value="revision">Minta perbaikan bukti</option>
              @else
                <option value="approved">Setujui permohonan</option><option value="rejected">Tolak permohonan</option>
              @endif
            </select></label>
            @if($item['kind'] === 'work' && $permit->status === 'mep_review')<label class="emergency-deposit-field" data-emergency-deposit hidden><span>Nominal deposit</span><input type="number" name="deposit_amount" min="1" max="999999999999.99" inputmode="numeric"></label>@endif
            <label class="emergency-validation-span"><span>Catatan keputusan</span><textarea name="review_notes" rows="3" maxlength="1000" placeholder="Wajib untuk penolakan atau permintaan perbaikan."></textarea></label>
            <label class="emergency-validation-span"><span>Alasan mengambil alih validasi</span><textarea name="emergency_reason" rows="3" minlength="10" maxlength="1000" required placeholder="Jelaskan kondisi operasional yang membuat admin perlu mengambil keputusan."></textarea></label>
            <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label>
            <div class="emergency-validation-submit"><button class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan keputusan admin</button></div>
          </form>
        </details>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-check"/></svg><strong>Tidak ada antrean untuk divalidasi</strong><span>Permohonan baru akan muncul saat menunggu keputusan validator.</span></div>
      @endforelse
    </div>
  </section>
</div>
@endsection
