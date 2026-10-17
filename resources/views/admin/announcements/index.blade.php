@extends('layouts.admin')

@section('title', 'Pengumuman Massal - Admin MBG')
@section('page-title', 'Pengumuman Massal')

@section('content')
<div class="page-wrap admin-page-wrap announcement-page">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Komunikasi</span></div><h1 class="page-title">Kirim pengumuman</h1><p class="page-subtitle">Kirim informasi operasional kepada akun aktif atau kontak pemohon yang pernah tercatat.</p></div>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><div><strong>Pengumuman belum dapat dikirim.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

  <div class="announcement-layout">
    <section class="admin-panel announcement-compose">
      <div class="admin-panel-header"><div><h2>Isi dan tujuan</h2><p>Satu kampanye diproses dalam satu waktu untuk menjaga laju pengiriman.</p></div><span class="admin-security-label"><svg><use href="#i-lock"/></svg>Konfirmasi admin</span></div>
      <form action="{{ route('admin.announcements.store') }}" method="POST" class="admin-form announcement-form" data-announcement-form>
        @csrf
        <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">

        <fieldset class="announcement-options">
          <legend>Kanal pengiriman</legend>
          <label><input type="checkbox" name="channels[]" value="whatsapp" @checked(in_array('whatsapp', old('channels', []), true))><span class="announcement-option-icon"><svg><use href="#i-phone"/></svg></span><span><strong>WhatsApp</strong><small>Dikirim bertahap melalui WAHA.</small></span></label>
          <label><input type="checkbox" name="channels[]" value="email" @checked(in_array('email', old('channels', []), true))><span class="announcement-option-icon"><svg><use href="#i-mail"/></svg></span><span><strong>Email</strong><small>Menggunakan konfigurasi SMTP aktif.</small></span></label>
        </fieldset>

        <fieldset class="announcement-options">
          <legend>Kelompok penerima</legend>
          <label><input type="checkbox" name="audiences[]" value="accounts" @checked(in_array('accounts', old('audiences', []), true))><span class="announcement-option-icon"><svg><use href="#i-user"/></svg></span><span><strong>Akun terdaftar</strong><small>{{ number_format($accountCount, 0, ',', '.') }} akun aktif. Hanya kontak valid yang dipakai.</small></span></label>
          <label><input type="checkbox" name="audiences[]" value="applicants" @checked(in_array('applicants', old('audiences', []), true))><span class="announcement-option-icon"><svg><use href="#i-file"/></svg></span><span><strong>Riwayat pemohon</strong><small>{{ number_format($applicantCount, 0, ',', '.') }} permohonan. Kontak yang sama otomatis digabung.</small></span></label>
        </fieldset>

        <div class="announcement-fields">
          <label><span>Judul pengumuman</span><input name="title" value="{{ old('title') }}" required minlength="4" maxlength="150" autocomplete="off" placeholder="Contoh: Perubahan jam operasional"></label>
          <label><span>Isi pengumuman</span><textarea name="message" rows="7" required minlength="10" maxlength="1000" data-announcement-message placeholder="Tuliskan informasi yang perlu diketahui penerima.">{{ old('message') }}</textarea><small><span data-announcement-count>{{ mb_strlen(old('message', '')) }}</span>/1000 karakter</small></label>
          <label><span>Kata sandi admin saat ini</span><input type="password" name="current_password" required maxlength="255" autocomplete="current-password"><small>Pengiriman massal selalu memerlukan konfirmasi ulang.</small></label>
        </div>

        <label class="announcement-consent"><input type="checkbox" name="authorized_contact" value="1" @checked(old('authorized_contact'))><span>Saya memastikan informasi ini resmi, relevan dengan layanan Mal Bali Galeria, dan penerima memiliki hubungan akun atau riwayat permohonan dengan sistem.</span></label>

        <div class="announcement-safety-note"><svg><use href="#i-info"/></svg><p>WhatsApp dikirim berurutan dengan jeda minimal {{ max(15, (int) config('announcements.whatsapp_interval_seconds', 20)) }} detik dan variasi waktu kecil. Hindari isi promosi berulang, tautan mencurigakan, dan pengiriman terlalu sering. Pengaturan ini mengurangi risiko pembatasan, tetapi tidak menjamin akun terbebas dari kebijakan WhatsApp.</p></div>
        <div class="admin-form-actions"><button type="submit" class="admin-primary-button" data-announcement-submit><svg><use href="#i-arrow-right"/></svg>Masukkan ke antrean</button></div>
      </form>
    </section>

    <aside class="announcement-side">
      <section class="admin-panel"><div class="admin-panel-header"><div><h2>Kampanye terakhir</h2><p>Status diperbarui oleh queue worker.</p></div></div>
        <div class="announcement-campaign-list">
          @forelse($campaigns as $campaign)
            <article class="announcement-campaign">
              <div class="announcement-campaign-head"><div><strong>{{ $campaign->title }}</strong><span>{{ $campaign->created_at->format('d M Y, H:i') }} WITA · {{ $campaign->creator->name }}</span></div><span class="announcement-status is-{{ $campaign->status }}">{{ match($campaign->status) { 'queued' => 'Mengantre', 'processing' => 'Berjalan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', default => ucfirst($campaign->status) } }}</span></div>
              <dl><div><dt>Tujuan</dt><dd>{{ $campaign->recipient_count }}</dd></div><div><dt>Terkirim</dt><dd>{{ $campaign->sent_count }}</dd></div><div><dt>Gagal</dt><dd>{{ $campaign->failed_count }}</dd></div></dl>
              <div class="announcement-channel-list">@foreach($campaign->channels as $channel)<span><svg><use href="#i-{{ $channel === 'whatsapp' ? 'phone' : 'mail' }}"/></svg>{{ ucfirst($channel) }}</span>@endforeach</div>
              @if(in_array($campaign->status, ['queued', 'processing'], true))<form action="{{ route('admin.announcements.cancel', $campaign) }}" method="POST" data-confirm="Batalkan seluruh pesan yang masih mengantre?">@csrf @method('PATCH')<button type="submit" class="admin-secondary-button"><svg><use href="#i-x"/></svg>Batalkan antrean</button></form>@endif
            </article>
          @empty
            <div class="admin-table-empty"><svg><use href="#i-mail"/></svg><strong>Belum ada pengumuman</strong><span>Kampanye pertama akan muncul di sini.</span></div>
          @endforelse
        </div>
        @if($campaigns->hasPages())<div class="pagination-wrap">{{ $campaigns->links() }}</div>@endif
      </section>
    </aside>
  </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
const announcementMessage = document.querySelector('[data-announcement-message]');
const announcementCount = document.querySelector('[data-announcement-count]');
announcementMessage?.addEventListener('input', () => { if (announcementCount) announcementCount.textContent = announcementMessage.value.length; });
document.querySelector('[data-announcement-form]')?.addEventListener('submit', event => {
  const button = event.currentTarget.querySelector('[data-announcement-submit]');
  if (!button || button.disabled) return;
  button.disabled = true;
  button.lastChild.textContent = ' Menyiapkan antrean...';
});
</script>
@endpush
