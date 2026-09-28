@extends(Auth::user()->isAdmin() ? 'layouts.admin' : 'layouts.validator')

@section('title', $permit->permit_number . ' : Surat Izin Kerja')
@section('page-title', 'Detail Surat Izin Kerja')

@php
  $isFinance = Auth::user()->isValidator() && Auth::user()->division === 'FIN';
  $isMep = Auth::user()->isValidator() && Auth::user()->division === 'MEP';
  $isTr = Auth::user()->isValidator() && Auth::user()->division === 'TR';
  $isSecretary = Auth::user()->isSecretary();
  $backUrl = Auth::user()->isAdmin() ? route('admin.dashboard') : ($isSecretary ? route('secretary.index', ['status' => 'work']) : ($isFinance ? route('finance.index') : ($isTr ? route('tr.work-permits.index') : route('mep.index'))));
  $backLabel = Auth::user()->isAdmin() ? 'Dashboard Admin' : ($isSecretary ? 'Dashboard Secretary' : ($isFinance ? 'Dashboard Finance' : ($isTr ? 'Izin Kerja TR' : 'Dashboard MEP')));
@endphp

@section('content')
<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><a href="{{ $backUrl }}">{{ $backLabel }}</a><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Surat Izin Kerja</span></div>
      <h1 class="page-title">{{ $permit->permit_number }}</h1>
      <p class="page-subtitle">Diajukan {{ $permit->created_at->format('d M Y, H:i') }} WITA</p>
    </div>
    <a href="{{ $backUrl }}" class="validator-back-link"><svg><use href="#i-arrow-left"/></svg>Kembali</a>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><div><strong>Periksa kembali data:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

  <div class="permit-alert permit-alert--info" role="status"><svg><use href="#i-info"/></svg><span>Status saat ini: <strong>{{ $permit->status_label }}</strong></span></div>

  <div class="tr-review-grid">
    <section class="detail-card">
      <h2 class="detail-card-title">Detail Permohonan</h2>
      <dl class="detail-dl">
        <dt>Kontraktor / Tenant</dt><dd>{{ $permit->contractor_name }}</dd>
        @unless($isFinance)
          <dt>Penanggung Jawab</dt><dd>{{ $permit->applicant_name }}</dd>
          <dt>Nomor WhatsApp</dt><dd>{{ $permit->applicant_phone }}</dd>
          @if($permit->applicant_email)<dt>Email</dt><dd>{{ $permit->applicant_email }}</dd>@endif
        @endunless
        <dt>Lokasi Pekerjaan</dt><dd>{{ $permit->work_location }}</dd>
        <dt>Kategori</dt><dd>{{ $permit->work_category_label }}</dd>
        <dt>Uraian Pekerjaan</dt><dd>{{ $permit->work_type }}</dd>
        <dt>Divisi Pemeriksa</dt><dd>{{ $permit->assigned_division }}</dd>
        <dt>Waktu Kerja</dt><dd>{{ $permit->work_schedule_label }}</dd>
        <dt>Periode</dt><dd>{{ $permit->start_date->format('d M Y') }} s.d. {{ $permit->end_date->format('d M Y') }}</dd>
        <dt>Kebutuhan Air</dt><dd>{{ $permit->needs_water ? 'Ya' : 'Tidak' }}</dd>
        <dt>Catatan</dt><dd>{{ $permit->notes ?: '-' }}</dd>
        @if($permit->assigned_division === 'MEP')
          <dt>Security Deposit</dt><dd>{{ $permit->deposit_required === null ? 'Belum ditentukan MEP' : ($permit->deposit_required ? 'Diperlukan' : 'Tidak diperlukan') }}</dd>
        @endif
        @if($permit->assigned_division === 'MEP' && $permit->deposit_required)
          <dt>Nominal Deposit</dt><dd><strong>Rp {{ number_format((float) $permit->deposit_amount, 0, ',', '.') }}</strong></dd>
          <dt>Catatan Deposit</dt><dd>{{ $permit->deposit_notes ?: '-' }}</dd>
        @endif
      </dl>

      @if(!$isFinance && !$isSecretary)<div class="tr-id-doc-preview">
        <div class="tr-id-doc-label">Dokumen Identitas ({{ strtoupper($permit->id_doc_type) }})</div>
        <a href="{{ URL::temporarySignedRoute('staff.work-permits.document', now()->addMinutes(5), ['token' => $permit->public_token]) }}" target="_blank" rel="noopener noreferrer" class="tr-id-doc-link"><svg><use href="#i-shield-check"/></svg>Lihat Dokumen Identitas</a>
      </div>@endif

      @if($permit->payment_proof_path && !$isSecretary)
        <div class="tr-id-doc-preview">
          <div class="tr-id-doc-label">Bukti Pembayaran Deposit</div>
          <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('staff.work-permits.payment-proof', now()->addMinutes(5), ['token' => $permit->public_token]) }}" target="_blank" rel="noopener noreferrer" class="tr-id-doc-link"><svg><use href="#i-file"/></svg>Lihat Bukti Pembayaran</a>
        </div>
      @endif
      @if($permit->refund_proof_path && !$isFinance && !$isSecretary)
        <div class="tr-id-doc-preview">
          <div class="tr-id-doc-label">Bukti Pengembalian Deposit</div>
          <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('staff.work-permits.refund-proof', now()->addMinutes(5), ['token' => $permit->public_token]) }}" target="_blank" rel="noopener noreferrer" class="tr-id-doc-link"><svg><use href="#i-file"/></svg>Lihat Bukti Pengembalian</a>
        </div>
      @endif
    </section>

    @unless($isFinance)<section class="detail-card">
      <h2 class="detail-card-title">Daftar Pekerja</h2>
      <div class="work-worker-list">
        <div class="work-worker-head" aria-hidden="true"><span>No.</span><span>Nama Pekerja</span><span>Nomor ID</span><span></span></div>
        @foreach($permit->workers as $worker)
          <div class="work-worker-row work-worker-row--static"><span class="work-worker-number">{{ $loop->iteration }}</span><span>{{ $worker->name }}</span><span>{{ $worker->identity_number ?: 'Tidak diisi' }}</span><span></span></div>
        @endforeach
      </div>
    </section>@endunless
  </div>

  @if($isTr)
    <section class="detail-card work-flow-action">
      <h2 class="detail-card-title">Keputusan TR</h2>
      @if($permit->status === 'tr_review')
        <form action="{{ route('tr.work-permits.decision', $permit->public_token) }}" method="POST" class="tr-decision-form">
          @csrf
          <div class="pf"><label for="tr_review_notes">Catatan Pemeriksaan</label><textarea id="tr_review_notes" name="review_notes" rows="3" maxlength="1000" placeholder="Alasan wajib diisi jika permohonan ditolak"></textarea></div>
          <div class="permit-submit-actions"><button type="submit" name="decision" value="approved" class="btn-tr-approve"><svg><use href="#i-check"/></svg>Setujui Permohonan</button><button type="submit" name="decision" value="rejected" class="btn-tr-reject"><svg><use href="#i-info"/></svg>Tolak Permohonan</button></div>
        </form>
      @else
        <p class="page-subtitle">Permohonan ini sudah diproses oleh TR.</p>
      @endif
    </section>
  @elseif($isMep)
    <section class="detail-card work-flow-action">
      <h2 class="detail-card-title">Tindakan MEP</h2>
      @if($permit->status === 'mep_review')
        <form action="{{ route('mep.work-permits.deposit', $permit->public_token) }}" method="POST" class="tr-decision-form" data-deposit-decision>
          @csrf
          <div class="pf"><label>Keputusan Security Deposit <span class="req">*</span></label><div class="pf-radio-group">
            <label class="pf-radio"><input type="radio" name="deposit_required" value="0" required><svg><use href="#i-check"/></svg><span>Tidak diperlukan</span></label>
            <label class="pf-radio"><input type="radio" name="deposit_required" value="1" required><svg><use href="#i-lock"/></svg><span>Diperlukan</span></label>
          </div></div>
          <div class="pf" data-deposit-amount hidden><label for="deposit_amount">Nominal Deposit <span class="req">*</span></label><div class="pf-wrap"><span class="pf-prefix">Rp</span><input id="deposit_amount" name="deposit_amount" type="number" min="1" max="999999999999.99" step="1" inputmode="numeric" placeholder="Contoh: 5000000"></div></div>
          <div class="pf"><label for="deposit_notes">Catatan MEP</label><textarea id="deposit_notes" name="deposit_notes" rows="3" maxlength="1000" placeholder="Dasar penetapan deposit atau catatan pemeriksaan"></textarea></div>
          <button type="submit" class="btn-primary"><svg><use href="#i-check"/></svg>Simpan Keputusan MEP</button>
        </form>
      @elseif($permit->status === 'mep_final_review')
        <form action="{{ route('mep.work-permits.decision', $permit->public_token) }}" method="POST" class="tr-decision-form">
          @csrf
          <div class="pf"><label for="review_notes">Catatan Keputusan</label><textarea id="review_notes" name="review_notes" rows="3" maxlength="1000"></textarea></div>
          <div class="permit-submit-actions"><button type="submit" name="decision" value="approved" class="btn-tr-approve"><svg><use href="#i-check"/></svg>Setujui Permohonan</button><button type="submit" name="decision" value="rejected" class="btn-tr-reject"><svg><use href="#i-info"/></svg>Tolak Permohonan</button></div>
        </form>
      @elseif($permit->status === 'approved')
        <form action="{{ route('mep.work-permits.complete', $permit->public_token) }}" method="POST">@csrf<button type="submit" class="btn-primary"><svg><use href="#i-check"/></svg>Tandai Pekerjaan Selesai</button></form>
      @elseif($permit->status === 'completed' && $permit->deposit_required)
        <form action="{{ route('mep.work-permits.refund.start', $permit->public_token) }}" method="POST">@csrf<button type="submit" class="btn-primary"><svg><use href="#i-clock"/></svg>Mulai Pengembalian Deposit</button></form>
      @elseif($permit->status === 'refund_processing')
        <form action="{{ route('mep.work-permits.refund.finish', $permit->public_token) }}" method="POST" enctype="multipart/form-data" class="tr-decision-form">
          @csrf
          <div class="pf"><label for="refund_amount">Nominal Dikembalikan <span class="req">*</span></label><input id="refund_amount" name="refund_amount" type="number" min="1" max="{{ $permit->deposit_amount }}" value="{{ old('refund_amount', $permit->deposit_amount) }}" required></div>
          <div class="pf"><label for="refund_proof">Bukti Transfer Pengembalian <span class="req">*</span></label><input id="refund_proof" name="refund_proof" type="file" accept="image/jpeg,image/png,application/pdf" required><span class="pf-hint">JPG, PNG, atau PDF; maksimal 5 MB.</span></div>
          <div class="pf"><label for="refund_notes">Catatan Pengembalian</label><textarea id="refund_notes" name="refund_notes" rows="3" maxlength="1000"></textarea></div>
          <button type="submit" class="btn-primary"><svg><use href="#i-check"/></svg>Catat Deposit Dikembalikan</button>
        </form>
      @else
        <p class="page-subtitle">Tidak ada tindakan MEP yang diperlukan pada status ini.</p>
      @endif
    </section>
  @elseif($isFinance)
    <section class="detail-card work-flow-action">
      <h2 class="detail-card-title">Verifikasi Finance</h2>
      @if($permit->status === 'payment_review')
        <p class="page-subtitle">Pastikan dana sebesar <strong>Rp {{ number_format((float) $permit->deposit_amount, 0, ',', '.') }}</strong> benar-benar masuk ke rekening sebelum mengonfirmasi.</p>
        <form action="{{ route('finance.work-permits.verify', $permit->public_token) }}" method="POST" class="tr-decision-form">
          @csrf
          <div class="pf"><label for="finance_notes">Catatan Finance</label><textarea id="finance_notes" name="notes" rows="3" maxlength="1000"></textarea></div>
          <div class="permit-submit-actions"><button type="submit" name="decision" value="verified" class="btn-tr-approve"><svg><use href="#i-check"/></svg>Dana Sudah Masuk</button><button type="submit" name="decision" value="revision" class="btn-tr-reject"><svg><use href="#i-info"/></svg>Minta Bukti Ulang</button></div>
        </form>
      @else
        <p class="page-subtitle">Pembayaran tidak sedang menunggu verifikasi.</p>
      @endif
    </section>
  @endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-deposit-decision] input[name="deposit_required"]').forEach(input => input.addEventListener('change', () => {
  const amount = document.querySelector('[data-deposit-amount]');
  const amountInput = document.getElementById('deposit_amount');
  const required = input.checked && input.value === '1';
  amount.hidden = !required;
  amountInput.required = required;
  if (!required) amountInput.value = '';
}));
</script>
@endpush
