import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import 'admin-lte/dist/js/adminlte.min.js';

if (document.querySelector('[data-scan-page]')) {
    import('./scan.js');
}

if (document.querySelector('[data-barcode-label]')) {
    import('./label.js');
}
