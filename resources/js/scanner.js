import jsQR from 'jsqr';
import '../css/scanner.css';

const config = window.scannerConfig ?? {};
const tokenPattern = /^[a-f0-9]{64}$/;

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

let stream = null;
let videoTrack = null;
let animationFrame = null;
let scanning = false;
let lastToken = null;
let torchEnabled = false;

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
        return 'Kamera hanya dapat digunakan melalui HTTPS atau localhost. Gunakan token manual untuk melanjutkan.';
    }
    if (error?.name === 'NotAllowedError') {
        return 'Izin kamera ditolak. Aktifkan izin kamera pada browser atau gunakan token manual.';
    }
    if (error?.name === 'NotFoundError') {
        return 'Kamera tidak ditemukan pada perangkat ini. Gunakan token manual untuk melanjutkan.';
    }
    return 'Kamera tidak dapat digunakan. Periksa izin browser atau gunakan token manual.';
}

async function startCamera() {
    startCameraButton.disabled = true;
    cameraMessage.textContent = 'Menyiapkan kamera...';

    try {
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
        resumeScanning();
    } catch (error) {
        const message = cameraErrorMessage(error);
        openScanner(false);
        showResult({ valid: false, status: 'camera_error', message });
        document.getElementById('scannerManualToken')?.focus();
    } finally {
        startCameraButton.disabled = false;
        cameraMessage.textContent = 'Pindai QR pada surat izin menggunakan kamera perangkat. Gambar kamera diproses langsung di perangkat dan tidak dikirim ke server.';
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
    const isValid = data.valid === true;
    const isExpired = data.status === 'expired';
    const type = isValid ? 'valid' : (isExpired ? 'expired' : 'invalid');
    const title = isValid
        ? 'Surat izin valid'
        : (isExpired ? 'Masa berlaku berakhir' : (data.status === 'not_found' ? 'Surat tidak ditemukan' : 'Surat tidak valid'));

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
    if (data.expired_at) addField('Kedaluwarsa', data.expired_at, 'invalid');
}

async function verifyToken(token) {
    pauseScanning();
    showLoading();

    try {
        const url = new URL(config.verifyUrl, window.location.origin);
        url.searchParams.set('token', token);
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

function bindManualForm(formId, inputId, errorId, openShell) {
    const form = document.getElementById(formId);
    const input = document.getElementById(inputId);
    const error = document.getElementById(errorId);

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        const token = extractToken(input.value);
        input.setAttribute('aria-invalid', token ? 'false' : 'true');
        error.textContent = token ? '' : 'Masukkan token 64 karakter atau tautan QR yang valid.';
        if (!token) return;

        if (openShell) openScanner(false);
        verifyToken(token);
    });

    input?.addEventListener('input', () => {
        input.removeAttribute('aria-invalid');
        error.textContent = '';
    });
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
    resultPanel.hidden = true;
    resumeScanning();
});

bindManualForm('gateManualForm', 'gateManualToken', 'gateManualError', true);
bindManualForm('scannerManualForm', 'scannerManualToken', 'scannerManualError', false);

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
