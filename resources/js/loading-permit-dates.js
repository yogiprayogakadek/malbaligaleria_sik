import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/flatpickr-mbg.css';

const startInput = document.getElementById('start_date');
const endInput = document.getElementById('end_date');
const maxDurationDays = Number.parseInt(endInput?.dataset.maxDurationDays ?? '', 10);

if (startInput && endInput && Number.isInteger(maxDurationDays) && maxDurationDays > 0) {
    const toIsoDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const addDays = (date, days) => {
        const result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    };

    let endPicker;

    const updateEndRange = (selectedDate) => {
        if (!selectedDate || !endPicker) return;

        const maxDate = addDays(selectedDate, maxDurationDays - 1);
        endPicker.set('minDate', selectedDate);
        endPicker.set('maxDate', maxDate);

        const currentEnd = endPicker.selectedDates[0];
        if (currentEnd && (currentEnd < selectedDate || currentEnd > maxDate)) {
            endPicker.setDate(maxDate, true);
        }

        endInput.min = toIsoDate(selectedDate);
        endInput.max = toIsoDate(maxDate);
    };

    endPicker = flatpickr(endInput, {
        locale: Indonesian,
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'j F Y',
        disableMobile: true,
        minDate: startInput.value || 'today',
        maxDate: startInput.value
            ? addDays(new Date(`${startInput.value}T00:00:00`), maxDurationDays - 1)
            : null,
    });

    flatpickr(startInput, {
        locale: Indonesian,
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'j F Y',
        disableMobile: true,
        minDate: 'today',
        onReady: (selectedDates) => updateEndRange(selectedDates[0]),
        onChange: (selectedDates) => updateEndRange(selectedDates[0]),
    });
}
