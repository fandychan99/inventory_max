import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

const form = document.querySelector('#scan-form');
const input = document.querySelector('#scan-code');
const startButton = document.querySelector('#camera-start');
const stopButton = document.querySelector('#camera-stop');
const status = document.querySelector('#camera-status');

let scanner;
let running = false;
let handled = false;
let startPromise;

function cameraErrorMessage(error) {
    const reason = `${error?.name ?? ''} ${error?.message ?? error ?? ''}`;

    if (/NotFoundError|DevicesNotFoundError|no cameras|no device|requested device not found/i.test(reason)) {
        return 'Kamera tidak ditemukan di perangkat ini. Gunakan HP berkamera melalui HTTPS atau scanner USB/Bluetooth.';
    }
    if (/NotAllowedError|PermissionDeniedError|SecurityError|permission|denied/i.test(reason)) {
        return 'Akses kamera ditolak browser. Izinkan kamera untuk situs ini di pengaturan browser, lalu coba lagi.';
    }
    if (/NotReadableError|TrackStartError|busy|in use/i.test(reason)) {
        return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi tersebut, lalu coba lagi.';
    }

    return 'Kamera gagal dibuka. Coba browser lain atau gunakan scanner USB/Bluetooth.';
}

if (window.matchMedia('(pointer: fine)').matches) {
    input?.focus();
    input?.select();
}

async function stopCamera() {
    if (!scanner || !running) return;

    try {
        await scanner.stop();
    } catch {
        // The browser may already have stopped the stream when the tab is hidden.
    } finally {
        try {
            scanner.clear();
        } catch {
            // A failed or interrupted start may leave no reader to clear.
        }
        running = false;
        startButton.disabled = false;
        stopButton.classList.add('d-none');
    }
}

startButton?.addEventListener('click', async () => {
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        status.textContent = 'Kamera HP memerlukan HTTPS. Gunakan alamat HTTPS atau scanner USB/Bluetooth.';
        return;
    }

    startButton.disabled = true;
    status.textContent = 'Meminta akses kamera…';
    handled = false;

    try {
        const cameras = await Html5Qrcode.getCameras();
        if (cameras.length === 0) throw new Error('No cameras found');
        const preferred = cameras.find(({ label }) => /back|rear|environment|belakang/i.test(label)) ?? cameras.at(-1);
        scanner = new Html5Qrcode('camera-reader', {
            formatsToSupport: [Html5QrcodeSupportedFormats.CODE_128, Html5QrcodeSupportedFormats.QR_CODE],
            verbose: false,
        });
        startPromise = scanner.start(
            preferred.id,
            { fps: 10 },
            async (decodedText) => {
                if (handled) return;
                const code = decodedText.trim();
                if (!/^[A-Za-z0-9._/-]{1,80}$/.test(code)) {
                    status.textContent = 'Kode terbaca, tetapi bukan kode barang atau lokasi yang valid.';
                    return;
                }
                handled = true;
                status.textContent = `Kode ${code} terbaca.`;
                input.value = code;
                try {
                    await startPromise;
                    running = true;
                    await stopCamera();
                    form.requestSubmit();
                } catch {
                    handled = false;
                    status.textContent = 'Pemindaian terhenti. Coba buka kamera lagi atau ketik kodenya.';
                }
            },
            () => {},
        );
        await startPromise;
        if (!handled) {
            running = true;
            stopButton.classList.remove('d-none');
            status.textContent = 'Arahkan kamera ke barcode pada label.';
        }
    } catch (error) {
        console.warn('Kamera scanner gagal dibuka:', error);
        try {
            scanner?.clear();
        } catch {
            // A failed start may leave the reader empty.
        }
        startButton.disabled = false;
        status.textContent = cameraErrorMessage(error);
    }
});

stopButton?.addEventListener('click', async () => {
    await stopCamera();
    status.textContent = 'Kamera ditutup.';
});

document.addEventListener('visibilitychange', () => {
    if (document.hidden && running) stopCamera();
});
