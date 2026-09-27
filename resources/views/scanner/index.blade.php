<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#0f172a">
  <title>Scan Surat Izin : Mal Bali Galeria Security</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --safe-top:    env(safe-area-inset-top,    0px);
      --safe-bottom: env(safe-area-inset-bottom, 0px);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      height: 100%;
      overflow: hidden;
      background: #0f172a;
      font-family: 'Inter', -apple-system, sans-serif;
      color: #f1f5f9;
      -webkit-font-smoothing: antialiased;
    }

    /* ── Full-screen scanner shell ─────────────────────────────── */
    .scanner-shell {
      position: fixed;
      inset: 0;
      display: flex;
      flex-direction: column;
    }

    /* ── Video feed ────────────────────────────────────────────── */
    #cameraFeed {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      will-change: transform;
    }

    /* ── Dark overlay with cutout ──────────────────────────────── */
    .scanner-overlay {
      position: absolute;
      inset: 0;
      /* Four dark rectangles around the transparent center */
      background: transparent;
      box-shadow: none;
    }
    .scanner-overlay::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(10, 14, 26, 0.60);
      /* punch transparent square in center via clip-path */
      clip-path: polygon(
        0% 0%, 100% 0%, 100% 100%, 0% 100%,        /* outer rect */
        0% calc(50% + 130px),                         /* to BL corner of hole */
        calc(50% - 130px) calc(50% + 130px),           /* hole BL */
        calc(50% - 130px) calc(50% - 130px),           /* hole TL */
        calc(50% + 130px) calc(50% - 130px),           /* hole TR */
        calc(50% + 130px) calc(50% + 130px),           /* hole BR */
        0% calc(50% + 130px)                            /* back to edge */
      );
    }

    /* ── Scan frame ────────────────────────────────────────────── */
    .scan-frame {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 260px;
      height: 260px;
      pointer-events: none;
    }

    .scan-frame-corner {
      position: absolute;
      width: 22px;
      height: 22px;
      border-color: #3b82f6;
      border-style: solid;
      border-width: 0;
    }
    .scan-frame-corner--tl { top: 0; left: 0; border-top-width: 3px; border-left-width: 3px; border-top-left-radius: 4px; }
    .scan-frame-corner--tr { top: 0; right: 0; border-top-width: 3px; border-right-width: 3px; border-top-right-radius: 4px; }
    .scan-frame-corner--bl { bottom: 0; left: 0; border-bottom-width: 3px; border-left-width: 3px; border-bottom-left-radius: 4px; }
    .scan-frame-corner--br { bottom: 0; right: 0; border-bottom-width: 3px; border-right-width: 3px; border-bottom-right-radius: 4px; }

    /* Scanning laser line */
    .scan-laser {
      position: absolute;
      left: 8px;
      right: 8px;
      height: 2px;
      background: linear-gradient(90deg, transparent, #3b82f6, #60a5fa, #3b82f6, transparent);
      border-radius: 99px;
      box-shadow: 0 0 8px rgba(59, 130, 246, 0.8);
      top: 0;
      animation: laserScan 2.2s ease-in-out infinite;
    }

    @keyframes laserScan {
      0%   { top: 8px; opacity: 0; }
      5%   { opacity: 1; }
      95%  { opacity: 1; }
      100% { top: calc(100% - 10px); opacity: 0; }
    }

    /* ── Top bar ───────────────────────────────────────────────── */
    .scanner-topbar {
      position: relative;
      z-index: 10;
      padding: calc(var(--safe-top) + 16px) 20px 16px;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .scanner-logo { height: 32px; }
    .scanner-title {
      flex: 1;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: .01em;
    }
    .scanner-title span { display: block; font-size: 10px; font-weight: 500; color: #64748b; }

    .scanner-torch-btn {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(255,255,255,.12);
      border: none;
      display: grid;
      place-items: center;
      color: #e2e8f0;
      cursor: pointer;
      transition: background .15s;
      -webkit-backdrop-filter: blur(8px);
      backdrop-filter: blur(8px);
    }
    .scanner-torch-btn:hover { background: rgba(255,255,255,.2); }
    .scanner-torch-btn.on { background: #fbbf24; color: #0f172a; }
    .scanner-torch-btn svg { width: 18px; height: 18px; }

    /* ── Hint text ─────────────────────────────────────────────── */
    .scanner-hint {
      position: absolute;
      z-index: 10;
      top: 50%;
      left: 50%;
      transform: translate(-50%, calc(-50% + 155px));
      text-align: center;
      white-space: nowrap;
    }
    .scanner-hint span {
      display: inline-block;
      background: rgba(15, 23, 42, 0.75);
      -webkit-backdrop-filter: blur(8px);
      backdrop-filter: blur(8px);
      color: #94a3b8;
      font-size: 12px;
      font-weight: 500;
      padding: 7px 16px;
      border-radius: 99px;
      border: 1px solid rgba(255,255,255,.08);
    }

    /* ── Bottom panel ──────────────────────────────────────────── */
    .scanner-bottom {
      position: relative;
      z-index: 10;
      margin-top: auto;
      padding: 0 20px calc(var(--safe-bottom) + 24px);
    }

    /* ── Result card ───────────────────────────────────────────── */
    .result-card {
      background: rgba(15, 23, 42, 0.88);
      -webkit-backdrop-filter: blur(20px) saturate(180%);
      backdrop-filter: blur(20px) saturate(180%);
      border-radius: 20px;
      border: 1px solid rgba(255,255,255,.10);
      padding: 20px;
      transition: all .3s cubic-bezier(.4,0,.2,1);
      transform: translateY(20px);
      opacity: 0;
    }
    .result-card.show { transform: translateY(0); opacity: 1; }
    .result-card.hidden-card { display: none; }

    .result-status {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
    }
    .result-status-icon {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      flex: none;
    }
    .result-status-icon svg { width: 22px; height: 22px; }
    .result-status-icon--valid   { background: #14532d; }
    .result-status-icon--invalid { background: #7f1d1d; }
    .result-status-icon--expired { background: #78350f; }
    .result-status-icon--loading { background: #1e3a5f; }

    .result-status-text strong {
      display: block;
      font-size: 15px;
      font-weight: 700;
    }
    .result-status-text span { font-size: 12px; color: #94a3b8; }

    .result-fields { display: grid; gap: 6px; }
    .result-field {
      display: flex;
      justify-content: space-between;
      font-size: 12.5px;
      padding: 6px 0;
      border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .result-field:last-child { border-bottom: none; }
    .result-field-label { color: #64748b; }
    .result-field-value { font-weight: 600; text-align: right; max-width: 55%; }
    .result-field-value--valid   { color: #4ade80; }
    .result-field-value--invalid { color: #f87171; }

    .result-scan-again {
      margin-top: 16px;
      width: 100%;
      height: 44px;
      background: rgba(59,130,246,.18);
      border: 1px solid rgba(59,130,246,.35);
      border-radius: 12px;
      color: #60a5fa;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: background .15s;
    }
    .result-scan-again:hover { background: rgba(59,130,246,.28); }

    /* ── Permission / error state ─────────────────────────────── */
    .scanner-perm {
      position: absolute;
      inset: 0;
      z-index: 20;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 32px;
      background: #0f172a;
    }
    .scanner-perm svg { width: 52px; height: 52px; color: #3b82f6; margin-bottom: 20px; }
    .scanner-perm h2 { font-size: 20px; font-weight: 800; margin-bottom: 10px; }
    .scanner-perm p { font-size: 14px; color: #64748b; line-height: 1.65; margin-bottom: 24px; }
    .btn-allow-camera {
      background: #3b82f6;
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 13px 28px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      width: 100%;
      max-width: 280px;
      transition: background .15s;
    }
    .btn-allow-camera:hover { background: #2563eb; }

    /* ── State: scanning pulse on corners ─────────────────────── */
    .scanning .scan-frame-corner { animation: cornerPulse 1.8s ease-in-out infinite; }
    @keyframes cornerPulse {
      0%, 100% { border-color: #3b82f6; }
      50%       { border-color: #60a5fa; filter: drop-shadow(0 0 4px #3b82f6); }
    }

    /* ── Manual input fallback ─────────────────────────────────── */
    .scanner-manual {
      margin-top: 14px;
      display: flex;
      gap: 8px;
    }
    .scanner-manual input {
      flex: 1;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 13px;
      color: #e2e8f0;
      outline: none;
    }
    .scanner-credit {
      margin-top: 12px;
      color: #94a3b8;
      font-size: 9px;
      line-height: 1.5;
      text-align: center;
    }
    .scanner-credit--gate { position: absolute; right: 20px; bottom: calc(var(--safe-bottom) + 16px); left: 20px; }
    .scanner-manual input::placeholder { color: #475569; }
    .scanner-manual input:focus { border-color: #3b82f6; }
    .scanner-manual button {
      background: #3b82f6;
      border: none;
      border-radius: 10px;
      padding: 10px 14px;
      color: #fff;
      font-weight: 600;
      font-size: 13px;
      cursor: pointer;
      white-space: nowrap;
    }
  </style>
</head>
<body>

<!-- Permission gate -->
<div class="scanner-perm" id="permGate">
  <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
  </svg>
  <h2>Izinkan Akses Kamera</h2>
  <p>
    Untuk memindai barcode surat izin, aplikasi ini membutuhkan akses ke kamera perangkat Anda.
    Data kamera tidak dikirim ke server.
  </p>
  <button class="btn-allow-camera" id="startCameraBtn">Aktifkan Kamera</button>
  <p style="margin-top:12px; font-size:12px; color:#475569;">
    Tidak ada kamera? Masukkan token manual di bawah.
  </p>
  <footer class="scanner-credit scanner-credit--gate">&copy; {{ now()->year }} Mal Bali Galeria &middot; Dikembangkan oleh Yogi Prayoga</footer>
</div>

<!-- Scanner screen -->
<div class="scanner-shell" id="scannerShell" style="display:none;">

  <!-- Camera feed -->
  <video id="cameraFeed" autoplay playsinline muted></video>
  <canvas id="scanCanvas" style="display:none;"></canvas>

  <!-- Overlay -->
  <div class="scanner-overlay"></div>

  <!-- Scan frame -->
  <div class="scan-frame scanning" id="scanFrame">
    <div class="scan-frame-corner scan-frame-corner--tl"></div>
    <div class="scan-frame-corner scan-frame-corner--tr"></div>
    <div class="scan-frame-corner scan-frame-corner--bl"></div>
    <div class="scan-frame-corner scan-frame-corner--br"></div>
    <div class="scan-laser" id="scanLaser"></div>
  </div>

  <!-- Hint -->
  <div class="scanner-hint"><span>Arahkan QR code ke dalam kotak</span></div>

  <!-- Top bar -->
  <div class="scanner-topbar">
    <img src="{{ asset('logo.png') }}" alt="MBG" class="scanner-logo">
    <div class="scanner-title">
      Verifikasi Surat Izin
      <span>Mal Bali Galeria Security</span>
    </div>
    <button class="scanner-torch-btn" id="torchBtn" aria-label="Nyalakan lampu" title="Senter">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/>
      </svg>
    </button>
  </div>

  <!-- Bottom panel -->
  <div class="scanner-bottom">

    <!-- Result card -->
    <div class="result-card hidden-card" id="resultCard">
      <div class="result-status" id="resultStatus"></div>
      <div class="result-fields" id="resultFields"></div>
      <button class="result-scan-again" id="scanAgainBtn">Scan Berikutnya</button>
    </div>

    <!-- Manual token input -->
    <div class="scanner-manual" id="manualInput">
      <input type="text" id="manualToken" placeholder="Masukkan token surat..." autocomplete="off">
      <button id="manualVerifyBtn">Verifikasi</button>
    </div>

    <footer class="scanner-credit">&copy; {{ now()->year }} Mal Bali Galeria &middot; Dikembangkan oleh Yogi Prayoga</footer>

  </div>
</div>

<!-- jsQR for QR decoding -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

<script>
(function () {
  'use strict';

  const VERIFY_URL = '{{ route('scanner.verify') }}';

  const permGate     = document.getElementById('permGate');
  const scannerShell = document.getElementById('scannerShell');
  const startBtn     = document.getElementById('startCameraBtn');
  const video        = document.getElementById('cameraFeed');
  const canvas       = document.getElementById('scanCanvas');
  const ctx          = canvas.getContext('2d');
  const scanFrame    = document.getElementById('scanFrame');
  const resultCard   = document.getElementById('resultCard');
  const resultStatus = document.getElementById('resultStatus');
  const resultFields = document.getElementById('resultFields');
  const scanAgainBtn = document.getElementById('scanAgainBtn');
  const torchBtn     = document.getElementById('torchBtn');
  const manualToken  = document.getElementById('manualToken');
  const manualVerify = document.getElementById('manualVerifyBtn');

  let stream        = null;
  let scanLoop      = null;
  let torchOn       = false;
  let track         = null;
  let scanning      = true;
  let lastToken     = null;

  // ── Start Camera ────────────────────────────────────────────────────────────
  async function startCamera() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: { ideal: 'environment' },
          width:  { ideal: 1280 },
          height: { ideal: 720 },
        }
      });

      video.srcObject = stream;
      track = stream.getVideoTracks()[0];

      video.addEventListener('loadedmetadata', () => {
        permGate.style.display = 'none';
        scannerShell.style.display = 'flex';
        requestAnimationFrame(scanQR);
      });
    } catch (err) {
      permGate.querySelector('p').textContent =
        'Kamera tidak dapat diakses (' + err.message + '). Gunakan input manual di bawah, atau buka di browser yang mendukung.';
      // Still show scanner for manual input
      permGate.style.display = 'none';
      scannerShell.style.display = 'flex';
    }
  }

  startBtn?.addEventListener('click', startCamera);

  // ── Scan QR loop ────────────────────────────────────────────────────────────
  function scanQR() {
    if (!scanning) return;

    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width  = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: 'dontInvert',
      });

      if (code) {
        const token = extractToken(code.data);
        if (token && token !== lastToken) {
          lastToken = token;
          scanning = false;
          vibrate([100, 50, 100]);
          showLoadingResult();
          verifyToken(token);
          return;
        }
      }
    }

    scanLoop = requestAnimationFrame(scanQR);
  }

  function extractToken(url) {
    try {
      const u = new URL(url);
      return u.searchParams.get('token') || url;
    } catch {
      return url.length > 10 ? url : null;
    }
  }

  // ── Torch ───────────────────────────────────────────────────────────────────
  torchBtn?.addEventListener('click', async () => {
    if (!track) return;
    try {
      torchOn = !torchOn;
      await track.applyConstraints({ advanced: [{ torch: torchOn }] });
      torchBtn.classList.toggle('on', torchOn);
    } catch { /* torch not supported */ }
  });

  // ── Verify ──────────────────────────────────────────────────────────────────
  async function verifyToken(token) {
    try {
      const res = await fetch(`${VERIFY_URL}?token=${encodeURIComponent(token)}`, {
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      showResult(data, res.ok);
    } catch {
      showResult({ valid: false, status: 'error', message: 'Gagal menghubungi server. Periksa koneksi internet.' }, false);
    }
  }

  function showLoadingResult() {
    resultCard.classList.remove('hidden-card');
    requestAnimationFrame(() => resultCard.classList.add('show'));
    scanFrame.classList.remove('scanning');

    resultStatus.innerHTML = `
      <div class="result-status-icon result-status-icon--loading">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="22" height="22">
          <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"
                stroke-linecap="round"/>
        </svg>
      </div>
      <div class="result-status-text">
        <strong>Memverifikasi...</strong>
        <span>Menghubungi server</span>
      </div>`;
    resultFields.innerHTML = '';
  }

  function showResult(data, httpOk) {
    const isValid = data.valid === true;
    const iconClass = isValid ? 'valid' : (data.status === 'expired' ? 'expired' : 'invalid');

    const iconSvg = isValid
      ? `<svg fill="none" stroke="#4ade80" stroke-width="2.5" viewBox="0 0 24 24" width="22" height="22"><polyline stroke-linecap="round" stroke-linejoin="round" points="20 6 9 17 4 12"/></svg>`
      : `<svg fill="none" stroke="#f87171" stroke-width="2.5" viewBox="0 0 24 24" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>`;

    const statusLabel = isValid
      ? 'Surat Izin Valid'
      : (data.status === 'expired' ? 'Surat Sudah Kadaluarsa' : data.status === 'not_found' ? 'Surat Tidak Ditemukan' : 'Surat Tidak Valid');

    resultStatus.innerHTML = `
      <div class="result-status-icon result-status-icon--${iconClass}">${iconSvg}</div>
      <div class="result-status-text">
        <strong>${statusLabel}</strong>
        <span>${data.message}</span>
      </div>`;

    // Build fields
    let fieldsHtml = '';
    if (data.permit_number) {
      fieldsHtml += field('Nomor Surat', data.permit_number, isValid ? 'valid' : 'invalid');
    }
    if (data.tenant_name)  fieldsHtml += field('Tenant', data.tenant_name);
    if (data.direction)    fieldsHtml += field('Arah', data.direction);
    if (data.start_date)   fieldsHtml += field('Tanggal Mulai', data.start_date);
    if (data.end_date)     fieldsHtml += field('Tanggal Selesai', data.end_date);
    if (data.expires_at)   fieldsHtml += field('Berlaku Hingga', data.expires_at);
    if (data.expired_at)   fieldsHtml += field('Kadaluarsa', data.expired_at, 'invalid');

    resultFields.innerHTML = fieldsHtml;
  }

  function field(label, value, colorClass = '') {
    return `<div class="result-field">
      <span class="result-field-label">${label}</span>
      <span class="result-field-value${colorClass ? ' result-field-value--'+colorClass : ''}">${value}</span>
    </div>`;
  }

  function vibrate(pattern) {
    try { navigator.vibrate?.(pattern); } catch {}
  }

  // ── Scan Again ──────────────────────────────────────────────────────────────
  scanAgainBtn?.addEventListener('click', () => {
    lastToken = null;
    scanning  = true;
    scanFrame.classList.add('scanning');
    resultCard.classList.remove('show');
    setTimeout(() => resultCard.classList.add('hidden-card'), 300);
    requestAnimationFrame(scanQR);
  });

  // ── Manual input ────────────────────────────────────────────────────────────
  manualVerify?.addEventListener('click', () => {
    const token = manualToken.value.trim();
    if (!token) return;
    scanning = false;
    showLoadingResult();
    verifyToken(token);
  });

  manualToken?.addEventListener('keydown', e => {
    if (e.key === 'Enter') manualVerify.click();
  });

})();
</script>
</body>
</html>
