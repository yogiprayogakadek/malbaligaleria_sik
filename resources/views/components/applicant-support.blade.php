@php
  $setting = \App\Models\ApplicantSupportSetting::current();
  $reference = $permit->permit_number ?? null;
  $routeName = request()->route()?->getName() ?? '';
  $pageType = str_contains($routeName, 'revision') ? 'halaman revisi' : (str_contains($routeName, 'status') || str_contains($routeName, 'show') ? 'halaman status' : (str_contains($routeName, 'success') ? 'halaman pengajuan berhasil' : 'formulir permohonan'));
  $message = 'Halo '.$setting->contact_name.', saya memerlukan bantuan pada '.$pageType.($reference ? ' untuk permohonan '.$reference : '').'.';
@endphp
@if($setting->isAvailable())
  <button type="button" class="applicant-support-trigger" data-applicant-support-open aria-haspopup="dialog" aria-controls="applicantSupportDialog"><svg aria-hidden="true"><use href="#i-help"/></svg><span>Butuh bantuan?</span></button>
  <dialog class="applicant-support-dialog" id="applicantSupportDialog" aria-labelledby="applicantSupportTitle">
    <div class="applicant-support-panel">
      <div><h2 id="applicantSupportTitle">Bantuan permohonan</h2><p>{{ $setting->contact_name }} dapat membantu jika Anda mengalami kendala saat mengisi atau menindaklanjuti permohonan.</p></div>
      <div class="applicant-support-actions">
        <a href="{{ route('portal.help') }}" class="btn-secondary">Buka pusat panduan</a>
        <a href="https://wa.me/{{ $setting->whatsapp_number }}?text={{ rawurlencode($message) }}" class="btn-primary" target="_blank" rel="noopener noreferrer">Hubungi IT Support melalui WhatsApp</a>
        <button type="button" class="btn-secondary" data-applicant-support-close>Tutup</button>
      </div>
    </div>
  </dialog>
@endif
