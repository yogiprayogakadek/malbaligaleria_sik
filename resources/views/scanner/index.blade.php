<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#f1f5f9">
  <meta name="robots" content="noindex, nofollow">
  <title>Verifikasi Surat Izin : Mal Bali Galeria</title>
  <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  @vite('resources/js/scanner.js')
</head>
<body>
  <main class="scanner-gate" id="permissionGate">
    <header class="gate-header">
      <a href="{{ route('portal.dashboard') }}" class="gate-brand" aria-label="Kembali ke beranda">
        <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria">
      </a>
      <a href="{{ route('portal.dashboard') }}" class="gate-back">
        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        <span>Beranda</span>
      </a>
    </header>

    <section class="gate-content" aria-labelledby="gateTitle">
      <div class="gate-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M3 9V5a2 2 0 0 1 2-2h4M15 3h4a2 2 0 0 1 2 2v4M21 15v4a2 2 0 0 1-2 2h-4M9 21H5a2 2 0 0 1-2-2v-4"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
      </div>
      <p class="gate-eyebrow">Security MBG</p>
      <h1 id="gateTitle">Verifikasi surat izin</h1>
      <p class="gate-copy" id="cameraMessage">Pindai QR pada surat izin menggunakan kamera perangkat. Gambar kamera diproses langsung di perangkat dan tidak dikirim ke server.</p>

      @if($scannerSetting->requiresLocation())
        <div class="location-notice" id="locationNotice" role="status" aria-live="polite">
          <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
          <span><strong>Lokasi diperlukan</strong><small id="locationMessage">Scanner dibatasi pada area yang ditentukan pengelola.</small></span>
        </div>
      @endif

      <button class="primary-button" id="startCameraButton" type="button">
        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6.8 6.2 5.2 7.2l-1.1.2A2.2 2.2 0 0 0 2.3 9.6V18a2.3 2.3 0 0 0 2.2 2.3h15a2.3 2.3 0 0 0 2.3-2.3V9.6a2.2 2.2 0 0 0-1.9-2.2l-1.1-.2-1.6-1a2.2 2.2 0 0 0-1.7-1H8.5a2.2 2.2 0 0 0-1.7 1Z"/><circle cx="12" cy="13" r="4"/></svg>
        <span>Aktifkan kamera</span>
      </button>

      <div class="gate-divider"><span>atau gunakan token</span></div>
      <form class="manual-form manual-form--gate" id="gateManualForm" novalidate>
        <label for="gateManualToken">Token verifikasi</label>
        <div class="manual-row">
          <input id="gateManualToken" type="text" maxlength="512" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Tempel token atau tautan QR">
          <button type="submit">Verifikasi</button>
        </div>
        <span class="manual-error" id="gateManualError" role="alert" aria-live="polite"></span>
      </form>
    </section>

    <footer class="scanner-credit">&copy; {{ now()->year }} Mal Bali Galeria &middot; Dikembangkan oleh Yogi Prayoga</footer>
  </main>

  <main class="scanner-shell" id="scannerShell" hidden>
    <video id="cameraFeed" autoplay playsinline muted aria-label="Pratinjau kamera"></video>
    <canvas id="scanCanvas" hidden></canvas>

    <header class="scanner-topbar">
      <a href="{{ route('portal.dashboard') }}" class="scanner-brand" aria-label="Kembali ke beranda">
        <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria">
        <span><strong>Verifikasi Surat Izin</strong><small>Mal Bali Galeria Security</small></span>
      </a>
      <button class="icon-button" id="torchButton" type="button" aria-label="Nyalakan lampu" aria-pressed="false" hidden>
        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9 2h6l1 5-2 3v10a2 2 0 0 1-4 0V10L8 7l1-5Z"/><path d="M9 5h6M10 13h4"/></svg>
      </button>
    </header>

    <div class="scan-frame scanning" id="scanFrame" aria-hidden="true">
      <span class="scan-corner scan-corner--tl"></span>
      <span class="scan-corner scan-corner--tr"></span>
      <span class="scan-corner scan-corner--bl"></span>
      <span class="scan-corner scan-corner--br"></span>
      <span class="scan-line"></span>
    </div>
    <p class="scanner-hint" id="scannerHint">Arahkan QR ke dalam bingkai</p>

    <section class="scanner-dock" aria-label="Hasil verifikasi">
      <div class="result-panel" id="resultPanel" hidden>
        <div class="result-status" id="resultStatus" role="status" aria-live="polite"></div>
        <dl class="result-fields" id="resultFields"></dl>
        <button class="secondary-button" id="scanAgainButton" type="button">Pindai berikutnya</button>
      </div>

      <form class="manual-form" id="scannerManualForm" novalidate>
        <label for="scannerManualToken">Verifikasi dengan token</label>
        <div class="manual-row">
          <input id="scannerManualToken" type="text" maxlength="512" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Tempel token atau tautan QR">
          <button type="submit">Verifikasi</button>
        </div>
        <span class="manual-error" id="scannerManualError" role="alert" aria-live="polite"></span>
      </form>

      <footer class="scanner-credit">&copy; {{ now()->year }} Mal Bali Galeria &middot; Dikembangkan oleh Yogi Prayoga</footer>
    </section>
  </main>

  <script>
    window.scannerConfig = Object.freeze({
      verifyUrl: @json(route('scanner.verify')),
      requiresLocation: @json($scannerSetting->requiresLocation()),
      initialToken: @json(request()->query('token')),
    });
  </script>
</body>
</html>
