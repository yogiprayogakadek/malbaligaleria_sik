import jsQR from 'jsqr';
import '../css/scanner.css';

const config = window.scannerConfig ?? {};
const tokenPattern = /^[A-Za-z0-9]{64}$/;

const permissionGate = document.getElementById('permissionGate');
const scannerShell = document.getElementById('scannerShell');
const startCameraButton = document.getElementById('startCameraButton');
const cameraMessage = document.getElementById('cameraMessage');
const video = document.getElementById('cameraFeed');
const canvas = document.getElementById('scanCanvas');
const context = canvas?.getContext('2d', { willReadFrequently: true });
const scanFrame = document.getElementById('scanFrame');
const scannerHint = document.getElementById('scannerHint');
const resultPanel = document.getElementById('resultPanel');
const resultStatus = document.getElementById('resultStatus');
const resultFields = document.getElementById('resultFields');
const scanAgainButton = document.getElementById('scanAgainButton');
const torchButton = document.getElementById('torchButton');
const locationNotice = document.getElementById('locationNotice');
const locationMessage = document.getElementById('locationMessage');
const gateActions = document.getElementById('gateActions');
const checkInButton = document.getElementById('checkInButton');
const checkOutButton = document.getElementById('checkOutButton');
const gateActionMessage = document.getElementById('gateActionMessage');
const evidenceCapture = document.getElementById('evidenceCapture');
const scannerDock = document.querySelector('.scanner-dock');
const evidenceCanvas = document.getElementById('evidenceCanvas');
const evidencePreview = document.getElementById('evidencePreview');
const captureEvidenceButton = document.getElementById('captureEvidenceButton');
const retakeEvidenceButton = document.getElementById('retakeEvidenceButton');
const evidenceCameraMode = document.getElementById('evidenceCameraMode');
const evidenceCameraMessage = document.getElementById('evidenceCameraMessage');
const confirmEvidenceButton = document.getElementById('confirmEvidenceButton');
const cancelEvidenceButton = document.getElementById('cancelEvidenceButton');

let stream = null;
let videoTrack = null;
let animationFrame = null;
let scanning = false;
let lastToken = null;
let torchEnabled = false;
let currentPosition = null;
let currentVerifiedToken = null;
let currentGateChallenge = null;
let evidenceBlob = null;
let evidencePreviewUrl = null;
let evidenceCameraActive = false;
let evidenceCaptureReadyAt = 0;
let evidenceCaptureArmTimer = null;
let evidenceShutterGesture = false;
let evidenceCameraOpening = false;
const initialToken = extractToken(config.initialToken);

if (window.isSecureContext && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}

function setChildren(element, ...children) {
    element.replaceChildren(...children);
}

function createIcon(type) {
    const wrapper = document.createElement('span');
    wrapper.className = `result-status-icon result-status-icon--${type}`;
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    if (type === 'valid') {
        path.setAttribute('d', 'm5 12 4 4L19 6');
    } else if (type === 'loading') {
        path.setAttribute('d', 'M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.1 2.1m8.6 8.6 2.1 2.1m0-12.8-2.1 2.1m-8.6 8.6-2.1 2.1');
    } else if (type === 'expired') {
        path.setAttribute('d', 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z');
    } else {
        path.setAttribute('d', 'M6 6l12 12M18 6 6 18');
    }

    svg.append(path);
    wrapper.append(svg);
    return wrapper;
}

function createStatus(type, title, message) {
    const copy = document.createElement('span');
    copy.className = 'result-status-copy';

    const strong = document.createElement('strong');
    strong.textContent = title;
    const detail = document.createElement('span');
    detail.textContent = message;
    copy.append(strong, detail);

    setChildren(resultStatus, createIcon(type), copy);
}

function addField(label, value, tone = '') {
    const row = document.createElement('div');
    row.className = 'result-field';
    const term = document.createElement('dt');
    term.textContent = label;
    const description = document.createElement('dd');
    description.textContent = String(value);
    if (tone) description.classList.add(`result-field-value--${tone}`);
    row.append(term, description);
    resultFields.append(row);
}

function openScanner(cameraAvailable = true) {
    permissionGate.hidden = true;
    scannerShell.hidden = false;
    scannerShell.classList.toggle('camera-unavailable', !cameraAvailable);
}

function cameraErrorMessage(error) {
    if (!window.isSecureContext) {
        return 'Kamera hanya dapat digunakan melalui HTTPS atau localhost.';
    }
    if (error?.name === 'NotAllowedError') {
        return 'Izin kamera ditolak. Aktifkan izin kamera untuk situs ini.';
    }
    if (error?.name === 'NotFoundError') {
        return 'Kamera tidak ditemukan pada perangkat ini.';
    }
    return 'Kamera tidak dapat digunakan. Periksa izin browser perangkat.';
}

function locationErrorMessage(error) {
    if (!window.isSecureContext) {
        return 'Lokasi memerlukan HTTPS. Buka scanner melalui koneksi HTTPS.';
    }
    if (error?.code === 1) {
        return 'Izin lokasi ditolak. Aktifkan lokasi presisi untuk situs ini.';
    }
    if (error?.code === 3) {
        return 'Lokasi belum ditemukan. Berada di area terbuka lalu coba kembali.';
    }
    return 'Lokasi perangkat tidak tersedia. Periksa layanan lokasi lalu coba kembali.';
}

function setLocationState(message, state = '') {
    if (!locationNotice || !locationMessage) return;
    locationMessage.textContent = message;
    locationNotice.dataset.state = state;
}

async function acquireLocation(force = false) {
    if (!config.requiresLocation) return null;

    const isFresh = currentPosition && (Date.now() - currentPosition.timestamp) < 60000;
    if (!force && isFresh) return currentPosition;

    if (!navigator.geolocation) {
        setLocationState('Perangkat ini tidak mendukung layanan lokasi.', 'error');
        return null;
    }

    setLocationState('Memeriksa lokasi perangkat...', 'loading');

    try {
        currentPosition = await new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 30000,
            });
        });
        setLocationState('Lokasi ditemukan. Scanner siap digunakan.', 'ready');
        return currentPosition;
    } catch (error) {
        setLocationState(locationErrorMessage(error), 'error');
        return null;
    }
}

async function startCamera() {
    startCameraButton.disabled = true;
    cameraMessage.textContent = 'Menyiapkan kamera...';
    let locationBlocked = false;

    try {
        if (config.requiresLocation && !await acquireLocation(true)) {
            locationBlocked = true;
            cameraMessage.textContent = 'Scanner belum dapat digunakan karena lokasi perangkat tidak tersedia.';
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            throw new DOMException('Camera API unavailable', 'NotSupportedError');
        }

        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
            audio: false,
        });

        videoTrack = stream.getVideoTracks()[0] ?? null;
        video.srcObject = stream;
        await video.play();
        openScanner(true);
        configureTorch();
        if (initialToken) {
            await verifyToken(initialToken);
        } else {
            resumeScanning();
        }
    } catch (error) {
        const message = cameraErrorMessage(error);
        openScanner(false);
        showResult({ valid: false, status: 'camera_error', message });
    } finally {
        startCameraButton.disabled = false;
        if (!locationBlocked) {
            cameraMessage.textContent = 'Pindai QR pada surat izin menggunakan kamera perangkat.';
        }
    }
}

function configureTorch() {
    const capabilities = videoTrack?.getCapabilities?.();
    const torchAvailable = Boolean(capabilities && 'torch' in capabilities && capabilities.torch);
    torchButton.hidden = !torchAvailable;
}

function extractToken(value) {
    const rawValue = String(value ?? '').trim();
    if (tokenPattern.test(rawValue)) return rawValue;

    try {
        const url = new URL(rawValue);
        const token = url.searchParams.get('token') ?? '';
        return tokenPattern.test(token) ? token : null;
    } catch {
        return null;
    }
}

function scanCameraFrame() {
    if (!scanning || !context || video.readyState < HTMLMediaElement.HAVE_ENOUGH_DATA) {
        if (scanning) animationFrame = requestAnimationFrame(scanCameraFrame);
        return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' });
    const token = code ? extractToken(code.data) : null;

    if (token && token !== lastToken) {
        lastToken = token;
        pauseScanning();
        navigator.vibrate?.([80, 45, 80]);
        verifyToken(token);
        return;
    }

    animationFrame = requestAnimationFrame(scanCameraFrame);
}

function resumeScanning() {
    if (!stream) return;
    scanning = true;
    scanFrame.classList.add('scanning');
    scannerHint.textContent = 'Arahkan QR ke dalam bingkai';
    cancelAnimationFrame(animationFrame);
    animationFrame = requestAnimationFrame(scanCameraFrame);
}

function pauseScanning() {
    scanning = false;
    scanFrame.classList.remove('scanning');
    cancelAnimationFrame(animationFrame);
}

function showLoading() {
    resultPanel.hidden = false;
    scanAgainButton.hidden = true;
    createStatus('loading', 'Memverifikasi surat', 'Mencocokkan token dengan data permohonan.');
    resultFields.replaceChildren();
}

function showResult(data) {
    leaveEvidenceCamera(false);
    const isValid = data.valid === true;
    const isExpired = data.status === 'expired';
    const isRevoked = data.status === 'revoked';
    const type = isValid ? 'valid' : (isExpired ? 'expired' : 'invalid');
    const title = isValid
        ? 'Surat izin valid'
        : (isExpired
            ? 'Masa berlaku berakhir'
            : (isRevoked
                ? 'Surat izin dicabut'
            : (data.status === 'integrity_error'
                ? 'Integritas dokumen bermasalah'
            : (data.status === 'not_found'
                ? 'Surat tidak ditemukan'
                : (data.status?.startsWith('location_') ? 'Akses lokasi ditolak' : 'Surat tidak valid')))));

    resultPanel.hidden = false;
    scanAgainButton.hidden = !stream;
    createStatus(type, title, data.message ?? 'Verifikasi tidak dapat diselesaikan.');
    resultFields.replaceChildren();

    if (data.permit_number) addField('Nomor surat', data.permit_number, isValid ? 'valid' : 'invalid');
    if (data.tenant_name) addField('Tenant', data.tenant_name);
    if (data.direction) addField('Arah', data.direction);
    if (data.start_date) addField('Tanggal mulai', data.start_date);
    if (data.end_date) addField('Tanggal selesai', data.end_date);
    if (data.movement_time) addField(data.movement_label || 'Waktu loading / unloading', data.movement_time);
    if (data.expires_at) addField('Berlaku hingga', data.expires_at);
    if (data.document_id) addField('ID dokumen', data.document_id);
    if (data.document_fingerprint) addField('Sidik dokumen', data.document_fingerprint);
    if (data.expired_at) addField('Kedaluwarsa', data.expired_at, 'invalid');

    resetEvidence();
    currentGateChallenge = data.gate_challenge ?? null;
    const needsEvidence = isValid && data.permit_type === 'loading' && Boolean(currentGateChallenge);
    evidenceCapture.hidden = !needsEvidence;
    gateActions.hidden = true;
    if (needsEvidence) {
        checkInButton.hidden = data.gate_status === 'check_in';
        checkOutButton.hidden = data.gate_status !== 'check_in';
        gateActionMessage.textContent = data.gate_status === 'check_in'
            ? 'Ambil foto kondisi barang atau kendaraan sebelum mencatat keluar.'
            : 'Ambil foto kondisi barang atau kendaraan sebelum mencatat masuk.';
    }
}

async function verifyToken(token) {
    pauseScanning();

    const position = await acquireLocation();
    if (config.requiresLocation && !position) {
        if (!permissionGate.hidden) return;
        showResult({ valid: false, status: 'location_required', message: locationMessage?.textContent });
        return;
    }

    if (!permissionGate.hidden) openScanner(false);
    showLoading();
    currentVerifiedToken = token;

    try {
        const url = new URL(config.verifyUrl, window.location.origin);
        url.searchParams.set('token', token);
        if (position) {
            url.searchParams.set('latitude', position.coords.latitude.toFixed(7));
            url.searchParams.set('longitude', position.coords.longitude.toFixed(7));
            url.searchParams.set('accuracy', Math.ceil(position.coords.accuracy));
        }
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const data = await response.json();
        showResult(data);
    } catch {
        showResult({ valid: false, status: 'error', message: 'Server tidak dapat dijangkau. Periksa koneksi lalu coba kembali.' });
    }
}

async function recordGateEvent(event) {
    if (!currentVerifiedToken || !currentGateChallenge || !evidenceBlob) return;
    const position = await acquireLocation();
    if (config.requiresLocation && !position) return;

    checkInButton.disabled = true;
    checkOutButton.disabled = true;
    gateActionMessage.textContent = 'Menyimpan aktivitas...';
    try {
        const payload = new FormData();
        payload.append('token', currentVerifiedToken);
        payload.append('event', event);
        payload.append('challenge', currentGateChallenge);
        payload.append('evidence_photo', evidenceBlob, 'bukti-loading.jpg');
        if (position) {
            payload.append('latitude', position.coords.latitude);
            payload.append('longitude', position.coords.longitude);
            payload.append('accuracy', position.coords.accuracy);
        }
        const response = await fetch(config.gateEventUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
            body: payload,
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Aktivitas tidak dapat disimpan.');
        gateActionMessage.textContent = `${data.message} ${data.occurred_at}`;
        checkInButton.hidden = data.gate_status === 'check_in';
        checkOutButton.hidden = data.gate_status !== 'check_in';
        currentGateChallenge = null;
        evidenceCapture.hidden = true;
        gateActions.hidden = true;
    } catch (error) {
        gateActionMessage.textContent = error.message || 'Aktivitas tidak dapat disimpan.';
    } finally {
        checkInButton.disabled = false;
        checkOutButton.disabled = false;
    }
}

function resetEvidence() {
    evidenceBlob = null;
    if (evidencePreviewUrl) URL.revokeObjectURL(evidencePreviewUrl);
    evidencePreviewUrl = null;
    if (evidencePreview) {
        evidencePreview.hidden = true;
        evidencePreview.removeAttribute('src');
    }
    if (captureEvidenceButton) captureEvidenceButton.hidden = false;
    if (retakeEvidenceButton) retakeEvidenceButton.hidden = true;
}

function enterEvidenceCamera() {
    if (!stream || !evidenceCameraMode || !currentGateChallenge) return;
    window.clearTimeout(evidenceCaptureArmTimer);
    evidenceCameraOpening = false;
    evidenceCameraActive = true;
    evidenceShutterGesture = false;
    evidenceCaptureReadyAt = Number.POSITIVE_INFINITY;
    pauseScanning();
    resultPanel.hidden = true;
    if (scannerDock) {
        scannerDock.hidden = true;
        scannerDock.inert = true;
    }
    scanFrame.hidden = true;
    scannerHint.hidden = true;
    scannerShell.classList.add('evidence-camera-active');
    evidenceCameraMode.hidden = false;
    evidenceCameraMessage.textContent = 'Kamera dibuka. Arahkan ke barang atau kendaraan.';
    if (confirmEvidenceButton) confirmEvidenceButton.disabled = true;

    // A short arm delay prevents the tap that opens the camera from being
    // retargeted to the shutter after the result card disappears on mobile.
    evidenceCaptureArmTimer = window.setTimeout(() => {
        if (!evidenceCameraActive) return;
        evidenceCaptureReadyAt = performance.now();
        if (confirmEvidenceButton) confirmEvidenceButton.disabled = false;
        evidenceCameraMessage.textContent = 'Tekan tombol putih untuk mengambil foto.';
    }, 650);
}

function leaveEvidenceCamera(showResultPanel = true) {
    if (!evidenceCameraMode) return;
    window.clearTimeout(evidenceCaptureArmTimer);
    evidenceCameraActive = false;
    evidenceShutterGesture = false;
    evidenceCaptureReadyAt = 0;
    evidenceCameraMode.hidden = true;
    scannerShell.classList.remove('evidence-camera-active');
    if (scannerDock) {
        scannerDock.hidden = false;
        scannerDock.inert = false;
    }
    scanFrame.hidden = false;
    scannerHint.hidden = false;
    if (showResultPanel) {
        resultPanel.hidden = false;
        captureEvidenceButton?.focus();
    }
}

async function captureEvidence(event) {
    if (!evidenceCameraActive || performance.now() < evidenceCaptureReadyAt) return;
    const keyboardActivation = event?.detail === 0;
    if (!evidenceShutterGesture && !keyboardActivation) return;
    evidenceShutterGesture = false;
    if (!video.videoWidth || !video.videoHeight || !evidenceCanvas) return;
    confirmEvidenceButton.disabled = true;
    evidenceCameraMessage.textContent = 'Memproses foto...';
    const maxEdge = 1920;
    const scale = Math.min(1, maxEdge / Math.max(video.videoWidth, video.videoHeight));
    evidenceCanvas.width = Math.round(video.videoWidth * scale);
    evidenceCanvas.height = Math.round(video.videoHeight * scale);
    evidenceCanvas.getContext('2d').drawImage(video, 0, 0, evidenceCanvas.width, evidenceCanvas.height);
    evidenceBlob = await new Promise((resolve) => evidenceCanvas.toBlob(resolve, 'image/jpeg', .86));
    if (!evidenceBlob) {
        evidenceCameraMessage.textContent = 'Foto tidak dapat diproses. Silakan coba kembali.';
        confirmEvidenceButton.disabled = false;
        return;
    }
    evidencePreviewUrl = URL.createObjectURL(evidenceBlob);
    evidencePreview.src = evidencePreviewUrl;
    evidencePreview.hidden = false;
    captureEvidenceButton.hidden = true;
    retakeEvidenceButton.hidden = false;
    gateActions.hidden = false;
    gateActionMessage.textContent = 'Periksa foto sebelum menyimpan aktivitas.';
    confirmEvidenceButton.disabled = false;
    leaveEvidenceCamera(true);
}

startCameraButton?.addEventListener('click', startCamera);

torchButton?.addEventListener('click', async () => {
    if (!videoTrack) return;
    try {
        torchEnabled = !torchEnabled;
        await videoTrack.applyConstraints({ advanced: [{ torch: torchEnabled }] });
        torchButton.classList.toggle('is-active', torchEnabled);
        torchButton.setAttribute('aria-pressed', String(torchEnabled));
        torchButton.setAttribute('aria-label', torchEnabled ? 'Matikan lampu' : 'Nyalakan lampu');
    } catch {
        torchButton.hidden = true;
    }
});

scanAgainButton?.addEventListener('click', () => {
    lastToken = null;
    leaveEvidenceCamera(false);
    resultPanel.hidden = true;
    resetEvidence();
    resumeScanning();
});

checkInButton?.addEventListener('click', () => recordGateEvent('check_in'));
checkOutButton?.addEventListener('click', () => recordGateEvent('check_out'));
captureEvidenceButton?.addEventListener('click', event => {
    event.preventDefault();
    event.stopPropagation();
    if (evidenceCameraOpening || evidenceCameraActive) return;
    evidenceCameraOpening = true;
    captureEvidenceButton.disabled = true;

    window.setTimeout(() => {
        evidenceCameraOpening = false;
        captureEvidenceButton.disabled = false;
        enterEvidenceCamera();
    }, 0);
});
confirmEvidenceButton?.addEventListener('pointerdown', () => {
    evidenceShutterGesture = evidenceCameraActive
        && performance.now() >= evidenceCaptureReadyAt
        && !confirmEvidenceButton.disabled;
});
confirmEvidenceButton?.addEventListener('click', captureEvidence);
cancelEvidenceButton?.addEventListener('click', () => leaveEvidenceCamera(true));
retakeEvidenceButton?.addEventListener('click', () => {
    resetEvidence();
    enterEvidenceCamera();
});

document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        pauseScanning();
    } else if (stream && resultPanel.hidden) {
        resumeScanning();
    }
});

window.addEventListener('pagehide', () => {
    pauseScanning();
    stream?.getTracks().forEach((track) => track.stop());
});
