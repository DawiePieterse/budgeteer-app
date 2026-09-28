// Reads a statement PDF on this device and puts its text in the form, so only the
// transactions are sent to the server. A PDF password is used here and never sent.
import * as pdfjs from '../vendor/pdfjs/pdf.min.js';
import { statementText } from './statement-text.js';

pdfjs.GlobalWorkerOptions.workerSrc = new URL('../vendor/pdfjs/pdf.worker.min.js', import.meta.url).href;

const form = document.getElementById('statement-form');
const file = document.getElementById('statement-file');
const passwordBox = document.getElementById('statement-password');
const password = document.getElementById('statement-password-input');
const text = document.getElementById('statement-text');
const message = document.getElementById('statement-message');
const submit = document.getElementById('statement-submit');

form.addEventListener('submit', async (event) => {
    if (text.value !== '') return; // already read: let the form go
    event.preventDefault();
    const chosen = file.files[0];
    if (!chosen) return;

    submit.disabled = true;
    message.textContent = 'Reading the statement…';
    try {
        const data = new Uint8Array(await chosen.arrayBuffer());
        const pdf = await pdfjs.getDocument({ data, password: password.value || undefined, isEvalSupported: false }).promise;
        text.value = JSON.stringify(await statementText(pdf));
        message.textContent = 'Checking the transactions…';
        form.submit();
    } catch (error) {
        submit.disabled = false;
        if (error?.name === 'PasswordException') {
            passwordBox.hidden = false;
            password.focus();
            message.textContent = password.value ? 'That password did not open the PDF.' : 'Type the PDF password, then read it again.';
        } else {
            message.textContent = 'This file could not be read as a PDF.';
        }
    }
});

file.addEventListener('change', () => {
    text.value = '';
    message.textContent = '';
});
