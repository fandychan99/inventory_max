import JsBarcode from 'jsbarcode';

const barcode = document.querySelector('[data-barcode-label]');
const error = document.querySelector('.label-error');
const printButton = document.querySelector('#print-label');

try {
    const lineColor = getComputedStyle(document.documentElement).getPropertyValue('--color-ink').trim();
    JsBarcode(barcode, barcode.dataset.code, {
        format: 'CODE128',
        lineColor,
        width: 2,
        height: 72,
        margin: 0,
        displayValue: false,
    });
} catch (exception) {
    barcode.classList.add('d-none');
    error.classList.remove('d-none');
    printButton.disabled = true;
}

printButton?.addEventListener('click', () => window.print());
