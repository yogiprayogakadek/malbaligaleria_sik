@props(['items'])

<section class="communication-timeline" aria-labelledby="communicationTimelineTitle">
  <div class="communication-timeline-head">
    <div><h2 id="communicationTimelineTitle">Aktivitas dan komunikasi</h2><p>Status serta antrean pengiriman notifikasi untuk permohonan ini.</p></div>
  </div>
  <ol>
    @forelse($items as $item)
      @php
        $channelLabel = match($item['channel']) {
          'email' => 'Email',
          'whatsapp' => 'WhatsApp',
          'push' => 'Aplikasi / push',
          default => 'Status',
        };
      @endphp
      <li class="communication-event communication-event--{{ $item['channel'] }}">
        <span class="communication-event-mark"><svg><use href="#i-{{ match($item['channel']) { 'email' => 'mail', 'whatsapp' => 'phone', 'push' => 'bell', default => 'clock' } }}"/></svg></span>
        <div class="communication-event-body">
          <div class="communication-event-meta"><span>{{ $channelLabel }}</span><time datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->format('d M Y, H:i') }} WITA</time></div>
          <strong>{{ $item['title'] }}</strong>
          @if($item['description'])<p>{{ $item['description'] }}</p>@endif
          @if($item['actor'])<small>Oleh {{ $item['actor'] }}</small>@endif
        </div>
        <span class="communication-event-status status-{{ $item['status'] }}">{{ match($item['status']) { 'sent' => 'Terkirim', 'failed' => 'Gagal', 'queued', 'retrying' => 'Antrean', 'read' => 'Dibaca', 'created' => 'Dibuat', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', default => ucfirst(str_replace('_', ' ', $item['status'])) } }}</span>
      </li>
    @empty
      <li class="communication-timeline-empty">Belum ada aktivitas yang tercatat.</li>
    @endforelse
  </ol>
</section>
