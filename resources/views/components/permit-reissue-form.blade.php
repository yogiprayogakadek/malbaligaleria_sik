@props(['action'])

<section class="permit-reissue-panel" aria-labelledby="reissuePermitTitle">
  <div><h2 id="reissuePermitTitle">Terbitkan ulang surat</h2><p>Gunakan hanya jika surat resmi mengandung kesalahan. Sistem menonaktifkan QR lama dan menyimpan versinya untuk audit.</p></div>
  <form action="{{ $action }}" method="POST" data-confirm="Terbitkan ulang surat ini? QR dan dokumen versi sebelumnya tidak akan berlaku lagi." data-staff-action-progress="Menerbitkan ulang surat">
    @csrf
    <label for="reissue_reason">Alasan penerbitan ulang <span class="req">*</span></label>
    <textarea id="reissue_reason" name="reason" rows="3" minlength="10" maxlength="1000" required placeholder="Jelaskan kesalahan pada surat dan alasan penggantiannya.">{{ old('reason') }}</textarea>
    <label for="reissue_password">Kata sandi admin <span class="req">*</span></label>
    <input id="reissue_password" type="password" name="current_password" autocomplete="current-password" required>
    <button type="submit" class="btn-primary"><svg><use href="#i-file"/></svg>Terbitkan surat pengganti</button>
  </form>
</section>
