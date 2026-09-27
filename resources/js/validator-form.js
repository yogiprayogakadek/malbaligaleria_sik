const forms = document.querySelectorAll('[data-validator-form]');

forms.forEach((form) => {
    const availabilityUrl = form.dataset.availabilityUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const remoteState = new Map();
    const timers = new Map();
    const requests = new Map();

    form.querySelectorAll('input, select').forEach((field) => {
        field.addEventListener('input', () => {
            validateField(form, field);

            if (field.matches('[data-availability-field]')) {
                remoteState.delete(field.name);
                clearTimeout(timers.get(field.name));
                requests.get(field.name)?.abort();

                if (localMessage(form, field)) return;

                timers.set(field.name, window.setTimeout(() => {
                    checkAvailability(form, field, availabilityUrl, csrf, remoteState, requests);
                }, 450));
            }
        });

        field.addEventListener('blur', () => validateField(form, field));
        field.addEventListener('change', () => validateField(form, field));
    });

    form.addEventListener('submit', async (event) => {
        if (form.dataset.submitting === 'true') return;

        event.preventDefault();
        const fields = [...form.querySelectorAll('input:not([type="hidden"]), select')];
        const locallyValid = fields.map((field) => validateField(form, field)).every(Boolean);

        if (!locallyValid) {
            fields.find((field) => localMessage(form, field))?.focus();
            return;
        }

        const availabilityFields = [...form.querySelectorAll('[data-availability-field]')];
        const availability = await Promise.all(availabilityFields.map((field) => (
            checkAvailability(form, field, availabilityUrl, csrf, remoteState, requests)
        )));

        if (availability.includes(false)) {
            availabilityFields.find((field) => remoteState.get(field.name) === false)?.focus();
            return;
        }

        form.dataset.submitting = 'true';
        const submitButton = form.querySelector('[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        form.submit();
    });
});

function validateField(form, field) {
    const message = localMessage(form, field);
    setFeedback(field, message, message ? 'error' : 'valid');

    if (field.name === 'password') {
        const confirmation = form.elements.password_confirmation;
        if (confirmation?.value) validateField(form, confirmation);
    }

    return !message;
}

function localMessage(form, field) {
    const value = field.value.trim();

    if (field.required && !value) return 'Kolom ini wajib diisi.';
    if (!value) return '';

    if (field.name === 'name' && value.length < 3) return 'Nama minimal 3 karakter.';
    if (field.name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Masukkan alamat email yang valid.';
    if (field.name === 'phone' && (!/^[0-9+()\-\s]+$/.test(value) || value.length < 9)) return 'Masukkan nomor telepon minimal 9 karakter.';
    if (field.name === 'password' && !isStrongPassword(field.value)) return 'Gunakan minimal 12 karakter dengan huruf besar, huruf kecil, angka, dan simbol.';

    if (field.name === 'password_confirmation') {
        if (!form.elements.password.value) return 'Isi kata sandi terlebih dahulu.';
        if (field.value !== form.elements.password.value) return 'Konfirmasi kata sandi belum sama.';
    }

    return '';
}

function isStrongPassword(value) {
    return value.length >= 12
        && /[a-z]/.test(value)
        && /[A-Z]/.test(value)
        && /[0-9]/.test(value)
        && /[^A-Za-z0-9]/.test(value);
}

async function checkAvailability(form, field, url, csrf, remoteState, requests) {
    if (localMessage(form, field)) return false;

    requests.get(field.name)?.abort();
    const controller = new AbortController();
    requests.set(field.name, controller);
    setFeedback(field, 'Memeriksa ketersediaan...', 'checking');

    try {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ field: field.name, value: field.value }),
            signal: controller.signal,
        });

        if (!response.ok) throw new Error(`Availability check failed: ${response.status}`);

        const result = await response.json();
        remoteState.set(field.name, Boolean(result.available));
        setFeedback(field, result.message, result.available ? 'valid' : 'error');

        return Boolean(result.available);
    } catch (error) {
        if (error.name === 'AbortError') return remoteState.get(field.name) ?? true;

        remoteState.delete(field.name);
        setFeedback(field, 'Pemeriksaan otomatis gagal. Data tetap diperiksa saat disimpan.', 'checking');

        return true;
    } finally {
        if (requests.get(field.name) === controller) requests.delete(field.name);
    }
}

function setFeedback(field, message, state) {
    const feedback = document.getElementById(`${field.name}-feedback`);
    if (!feedback) return;

    feedback.textContent = message;
    feedback.classList.toggle('is-error', state === 'error');
    feedback.classList.toggle('is-valid', state === 'valid' && Boolean(message));
    feedback.classList.toggle('is-checking', state === 'checking');
    field.setAttribute('aria-invalid', String(state === 'error'));
}
