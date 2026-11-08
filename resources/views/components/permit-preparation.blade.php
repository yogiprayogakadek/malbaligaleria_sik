@props([
  'duration' => '5-10 menit',
  'documents' => [],
  'validity' => null,
  'autosave' => true,
])

<aside class="permit-preparation" aria-labelledby="permitPreparationTitle">
  <div class="permit-preparation-heading">
    <svg aria-hidden="true"><use href="#i-info"/></svg>
    <div>
      <h2 id="permitPreparationTitle">Siapkan sebelum mengisi</h2>
      <p>Informasi ini membantu Anda menyelesaikan permohonan tanpa berhenti di tengah proses.</p>
    </div>
  </div>
  <dl class="permit-preparation-list">
    <div><dt>Waktu pengisian</dt><dd>{{ $duration }}</dd></div>
    @if($validity)<div><dt>Masa berlaku</dt><dd>{{ $validity }}</dd></div>@endif
    <div><dt>Dokumen</dt><dd>{{ $documents ? implode(', ', $documents) : 'Tidak ada lampiran wajib' }}</dd></div>
    <div><dt>Penyimpanan</dt><dd>{{ $autosave ? 'Isian disimpan otomatis di perangkat ini' : 'Selesaikan dalam satu sesi' }}</dd></div>
  </dl>
</aside>
