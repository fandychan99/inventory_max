import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

const form = document.querySelector('#scan-form');
const input = document.querySelector('#scan-code');
const startButton = document.querySelector('#camera-start');
const stopButton = document.querySelector('#camera-stop');
const cameraSelect = document.querySelector('#camera-select');
const imageInput = document.querySelector('#barcode-image');
const status = document.querySelector('#camera-status');

let scanner;
let startPromise;
let starting = false;
let running = false;
let handled = false;

function reader() {
    scanner ??= new Html5Qrcode('camera-reader', {
        formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.QR_CODE],
        verbose: false,
    });

    return scanner;
}

function cameraErrorMessage(error) {
    const reason = `${error?.name ?? ''} ${error?.message ?? error ?? ''}`;

    if (/NotFoundError|DevicesNotFoundError|no cameras|no device|requested device not found/i.test(reason)) {
        return 'Kamera tidak ditemukan. Periksa webcam laptop atau pilih gambar barcode.';
    }
    if (/NotAllowedError|PermissionDeniedError|SecurityError|permission|denied/i.test(reason)) {
        return 'Akses kamera ditolak. Izinkan kamera untuk situs ini di browser dan pengaturan privasi perangkat.';
    }
    if (/NotReadableError|TrackStartError|busy|in use/i.test(reason)) {
        return 'Kamera sedang dipakai aplikasi lain atau dinonaktifkan. Tutup aplikasi lain dan periksa penutup webcam.';
    }
    if (/OverconstrainedError|ConstraintNotSatisfiedError/i.test(reason)) {
        return 'Kamera yang dipilih tidak tersedia. Pilih kamera lain atau buka ulang kamera otomatis.';
    }

    return 'Kamera gagal dibuka. Periksa izin browser dan kamera perangkat, atau unggah gambar barcode.';
}

function validCode(raw) {
    const code = raw.trim();
    if (!/^[A-Za-z0-9._/-]{1,80}$/.test(code)) {
        status.textContent = 'Kode terbaca, tetapi isinya bukan kode barang atau lokasi yang valid.';
        return null;
    }

    return code;
}

function submitCode(code) {
    input.value = code;
    status.textContent = `Kode ${code} terbaca. Membuka hasil…`;
    form.requestSubmit();
}

if (window.matchMedia('(pointer: fine)').matches) {
    input?.focus();
    input?.select();
}

async function refreshCameraChoices() {
    if (!navigator.mediaDevices?.enumerateDevices) return;

    try {
        const cameras = (await navigator.mediaDevices.enumerateDevices()).filter(({ kind }) => kind === 'videoinput');
        cameraSelect.replaceChildren();
        if (cameras.length < 2) {
            cameraSelect.classList.add('d-none');
            return;
        }

        cameraSelect.add(new Option('Kamera otomatis', ''));
        cameras.forEach((camera, index) => {
            cameraSelect.add(new Option(camera.label || `Kamera ${index + 1}`, camera.deviceId));
        });
        const currentId = running ? reader().getRunningTrackSettings().deviceId : '';
        cameraSelect.value = currentId || '';
        cameraSelect.classList.remove('d-none');
    } catch {
        cameraSelect.classList.add('d-none');
    }
}

async function stopCamera() {
    if (starting) {
        try { await startPromise; } catch { /* A failed start has no stream to stop. */ }
    }
    if (running || scanner?.isScanning) {
        try { await reader().stop(); } catch { /* The browser may already have stopped the stream. */ }
    }
    try { scanner?.clear(); } catch { /* A failed start may leave no reader to clear. */ }
    running = false;
    starting = false;
    startButton.disabled = false;
    imageInput.disabled = false;
    stopButton.classList.add('d-none');
}

async function startCamera(deviceId = '') {
    if (starting || running) return;
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        status.textContent = 'Kamera browser memerlukan HTTPS atau localhost. Unggah gambar barcode tetap dapat digunakan.';
        return;
    }

    starting = true;
    handled = false;
    startButton.disabled = true;
    imageInput.disabled = true;
    status.textContent = 'Meminta akses kamera…';

    try {
        reader().clear();
        const camera = deviceId
            ? { deviceId: { exact: deviceId } }
            : { facingMode: window.matchMedia('(pointer: coarse)').matches ? 'environment' : 'user' };
        startPromise = reader().start(camera, { fps: 10 }, async (decodedText) => {
            if (handled) return;
            const code = validCode(decodedText);
            if (!code) return;

            handled = true;
            try {
                await startPromise;
                await stopCamera();
                submitCode(code);
            } catch {
                handled = false;
                status.textContent = 'Pemindaian terhenti. Coba buka kamera lagi atau unggah gambar.';
            }
        }, () => {});
        await startPromise;
        if (handled || !reader().isScanning) return;
        running = true;
        stopButton.classList.remove('d-none');
        status.textContent = 'Arahkan kamera ke barcode Code 128 atau QR pada label.';
        await refreshCameraChoices();
    } catch (error) {
        console.warn('Kamera scanner gagal dibuka:', error);
        try { scanner?.clear(); } catch { /* No reader may have been created. */ }
        status.textContent = cameraErrorMessage(error);
        await refreshCameraChoices();
    } finally {
        starting = false;
        imageInput.disabled = false;
        if (!running) startButton.disabled = false;
    }
}

startButton?.addEventListener('click', () => startCamera(cameraSelect.value));

stopButton?.addEventListener('click', async () => {
    await stopCamera();
    status.textContent = 'Kamera ditutup. Anda dapat memilih gambar barcode.';
});

cameraSelect?.addEventListener('change', async () => {
    if (!running) return;
    const deviceId = cameraSelect.value;
    await stopCamera();
    await startCamera(deviceId);
});

imageInput?.addEventListener('change', async () => {
    const file = imageInput.files?.[0];
    if (!file) return;
    imageInput.value = '';
    if (!/^image\/(png|jpeg|webp|gif)$/i.test(file.type) || file.size > 10 * 1024 * 1024) {
        status.textContent = 'Pilih gambar PNG, JPG, WebP, atau GIF berukuran maksimal 10 MB.';
        return;
    }

    await stopCamera();
    startButton.disabled = true;
    imageInput.disabled = true;
    cameraSelect.disabled = true;
    status.textContent = 'Membaca barcode dari gambar…';

    try {
        const code = validCode(await reader().scanFile(file, true));
        if (code) submitCode(code);
    } catch {
        status.textContent = 'Barcode tidak terbaca. Gunakan foto yang tajam dan tidak terpotong, lalu coba lagi.';
    } finally {
        startButton.disabled = false;
        imageInput.disabled = false;
        cameraSelect.disabled = false;
    }
});

document.addEventListener('visibilitychange', () => {
    if (document.hidden && (running || starting)) stopCamera();
});
