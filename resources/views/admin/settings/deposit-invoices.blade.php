@extends('layouts.admin')

@section('title', 'Invoice Deposit : Admin MBG')
@section('page-title', 'Invoice Deposit')

@section('content')
@php
  $accounts = collect(old('accounts', $setting->accounts->map(fn($account) => ['bank_name' => $account->bank_name, 'account_number' => $account->account_number, 'account_holder' => $account->account_holder])->all()));
  if ($accounts->isEmpty()) $accounts->push(['bank_name' => '', 'account_number' => '', 'account_holder' => '']);
@endphp
<div class="page-wrap admin-page-wrap admin-form-page deposit-invoice-settings" data-deposit-invoice-settings>
  <div class="page-header admin-page-header"><div><div class="page-breadcrumb"><span>Finance</span><svg><use href="#i-chevron-right"/></svg><span>Invoice deposit</span></div><h1 class="page-title">Pengaturan invoice deposit</h1><p class="page-subtitle">Pilih cara penerbitan invoice dan tentukan rekening pembayaran yang ditampilkan kepada pemohon.</p></div></div>
  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif
  <form method="POST" action="{{ route('admin.settings.deposit-invoices.update') }}" class="admin-form admin-settings-form" data-disable-submit>
    @csrf @method('PUT')
    <section class="admin-panel deposit-mode-panel">
      <div class="admin-section-heading"><div><h2>Mode penerbitan</h2><p>Mode manual mempertahankan unggahan Finance. Mode otomatis menerbitkan PDF setelah Engineering menetapkan deposit.</p></div><span class="admin-status-pill {{ $setting->isAutomatic() ? 'is-active' : 'is-inactive' }}">{{ $setting->isAutomatic() ? 'Otomatis aktif' : 'Manual aktif' }}</span></div>
      <div class="deposit-mode-options">
        <label><input type="radio" name="mode" value="manual" @checked(old('mode', $setting->mode) === 'manual')><span><strong>Manual oleh Finance</strong><small>Finance mengunggah invoice sebelum pemohon dapat membayar.</small></span></label>
        <label><input type="radio" name="mode" value="automatic" @checked(old('mode', $setting->mode) === 'automatic')><span><strong>Otomatis oleh sistem</strong><small>PDF langsung diterbitkan dan pemohon diberi tahu.</small></span></label>
      </div>
      @if($backlogCount > 0)<div class="permit-alert permit-alert--warning"><svg><use href="#i-info"/></svg><span>{{ $backlogCount }} permohonan sedang menunggu invoice. Jika mode otomatis diaktifkan, semuanya dimasukkan ke antrean penerbitan.</span></div>@endif
    </section>
    <section class="admin-panel">
      <div class="admin-section-heading"><div><h2>Rekening pembayaran</h2><p>Tambahkan hingga 10 rekening. Semua rekening aktif dicetak pada invoice otomatis.</p></div><button type="button" class="admin-secondary-button" data-add-deposit-account><svg><use href="#i-plus"/></svg>Tambah rekening</button></div>
      <div class="deposit-account-list" data-deposit-account-list>
        @foreach($accounts as $index => $account)
          <fieldset class="deposit-account-row" data-deposit-account><legend>Rekening {{ $loop->iteration }}</legend><label><span>Nama bank</span><input name="accounts[{{ $index }}][bank_name]" value="{{ $account['bank_name'] ?? '' }}" maxlength="100" autocomplete="organization"></label><label><span>Nomor rekening</span><input name="accounts[{{ $index }}][account_number]" value="{{ $account['account_number'] ?? '' }}" maxlength="80" inputmode="numeric" autocomplete="off"></label><label><span>Nama pemilik rekening</span><input name="accounts[{{ $index }}][account_holder]" value="{{ $account['account_holder'] ?? '' }}" maxlength="150" autocomplete="name"></label><button type="button" class="icon-danger-button" data-remove-deposit-account aria-label="Hapus rekening {{ $loop->iteration }}"><svg><use href="#i-trash"/></svg></button></fieldset>
        @endforeach
      </div>
      <template data-deposit-account-template><fieldset class="deposit-account-row" data-deposit-account><legend>Rekening</legend><label><span>Nama bank</span><input name="accounts[__INDEX__][bank_name]" maxlength="100" autocomplete="organization"></label><label><span>Nomor rekening</span><input name="accounts[__INDEX__][account_number]" maxlength="80" inputmode="numeric" autocomplete="off"></label><label><span>Nama pemilik rekening</span><input name="accounts[__INDEX__][account_holder]" maxlength="150" autocomplete="name"></label><button type="button" class="icon-danger-button" data-remove-deposit-account aria-label="Hapus rekening"><svg><use href="#i-trash"/></svg></button></fieldset></template>
    </section>
    <section class="admin-panel">
      <div class="admin-section-heading"><div><h2>Instruksi dan konfirmasi</h2><p>Nomor invoice menggunakan pola DEP/Tahun/Bulan/Urutan dan direset setiap bulan.</p></div></div>
      <div class="admin-form-grid"><label class="admin-form-span"><span>Instruksi pembayaran <small>(opsional)</small></span><textarea name="payment_instructions" rows="4" maxlength="1000">{{ old('payment_instructions', $setting->payment_instructions) }}</textarea><small>Ditampilkan pada invoice otomatis.</small></label><label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label></div>
      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan pengaturan invoice</button></div>
    </section>
  </form>
</div>
@endsection
