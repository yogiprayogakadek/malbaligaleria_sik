<div class="admin-inline-actions communication-actions">
  @if($log['detail_url'])
    <a class="admin-secondary-button" href="{{ $log['detail_url'] }}">
      <svg><use href="#i-eye"/></svg>
      Buka
    </a>
  @endif

  @if($log['channel'] === 'email' && $log['can_retry'])
    <form method="POST" action="{{ route('admin.email-logs.retry', $log['id']) }}" data-confirm="Kirim ulang email ini?">
      @csrf
      <button class="admin-secondary-button" type="submit">Kirim ulang</button>
    </form>
  @elseif($log['channel'] === 'whatsapp' && $log['can_retry'])
    <form method="POST" action="{{ route('admin.communication-logs.whatsapp.retry', $log['id']) }}" data-confirm="Kirim ulang pesan WhatsApp ini?">
      @csrf
      <button class="admin-secondary-button" type="submit">Kirim ulang</button>
    </form>

    @if($log['can_fallback'])
      <form method="POST" action="{{ route('admin.communication-logs.whatsapp.fallback', $log['id']) }}" data-confirm="Kirim pembaruan melalui email pemohon?">
        @csrf
        <button class="admin-secondary-button" type="submit">Alihkan ke email</button>
      </form>
    @endif
  @endif

  @if(! $log['detail_url'] && ! $log['can_retry'])
    <span class="communication-no-action">Tidak ada aksi</span>
  @endif
</div>
