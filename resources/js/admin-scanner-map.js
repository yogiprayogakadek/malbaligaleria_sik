import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

const form = document.querySelector('[data-scanner-settings]');

if (form) {
    const defaultCenter = [-8.7212345, 115.1845678];
    const modes = [...form.querySelectorAll('[name="access_mode"]')];
    const fields = form.querySelector('[data-location-fields]');
    const latitudeInput = form.elements.latitude;
    const longitudeInput = form.elements.longitude;
    const radiusInput = form.elements.radius_meters;
    const locationInputs = [...form.querySelectorAll('[data-location-input]')];
    const locationButton = form.querySelector('[data-use-location]');
    const feedback = form.querySelector('[data-location-feedback]');
    const mapElement = form.querySelector('[data-scanner-map]');

    const initialLatitude = Number.parseFloat(latitudeInput.value);
    const initialLongitude = Number.parseFloat(longitudeInput.value);
    const hasInitialPoint = Number.isFinite(initialLatitude) && Number.isFinite(initialLongitude);
    const initialPoint = hasInitialPoint ? [initialLatitude, initialLongitude] : defaultCenter;

    const map = L.map(mapElement, {
        center: initialPoint,
        zoom: hasInitialPoint ? 18 : 17,
        zoomControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const marker = L.marker(initialPoint, {
        draggable: true,
        keyboard: true,
        title: 'Titik pusat area scanner',
    }).addTo(map);

    const radiusCircle = L.circle(initialPoint, {
        radius: Number.parseInt(radiusInput.value, 10) || 200,
        color: '#2563eb',
        fillColor: '#3b82f6',
        fillOpacity: 0.12,
        weight: 2,
    }).addTo(map);

    function setFeedback(message, state = '') {
        feedback.textContent = message;
        feedback.dataset.state = state;
    }

    function setPoint(latitude, longitude, message = 'Titik area scanner diperbarui dari peta.') {
        const point = L.latLng(latitude, longitude);
        latitudeInput.value = point.lat.toFixed(7);
        longitudeInput.value = point.lng.toFixed(7);
        marker.setLatLng(point);
        radiusCircle.setLatLng(point);
        setFeedback(message, 'success');
    }

    function syncPointFromInputs() {
        const latitude = Number.parseFloat(latitudeInput.value);
        const longitude = Number.parseFloat(longitudeInput.value);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
        if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return;

        const point = L.latLng(latitude, longitude);
        marker.setLatLng(point);
        radiusCircle.setLatLng(point);
        map.panTo(point);
    }

    function syncMode() {
        const restricted = form.querySelector('[name="access_mode"]:checked')?.value === 'geofence';
        fields.hidden = !restricted;
        locationInputs.forEach((input) => {
            input.required = restricted;
            input.disabled = !restricted;
        });

        if (restricted) {
            window.setTimeout(() => {
                map.invalidateSize();
                map.panTo(marker.getLatLng());
            }, 0);
        }
    }

    map.on('click', (event) => setPoint(event.latlng.lat, event.latlng.lng));
    marker.on('dragend', () => {
        const point = marker.getLatLng();
        setPoint(point.lat, point.lng, 'Titik area scanner diperbarui dari marker.');
    });

    [latitudeInput, longitudeInput].forEach((input) => {
        input.addEventListener('change', syncPointFromInputs);
    });

    radiusInput.addEventListener('input', () => {
        const radius = Number.parseInt(radiusInput.value, 10);
        if (Number.isFinite(radius) && radius > 0) radiusCircle.setRadius(radius);
    });

    modes.forEach((input) => input.addEventListener('change', syncMode));
    syncMode();

    locationButton?.addEventListener('click', () => {
        if (!window.isSecureContext || !navigator.geolocation) {
            setFeedback('Lokasi perangkat memerlukan HTTPS atau localhost.', 'error');
            return;
        }

        locationButton.disabled = true;
        setFeedback('Mengambil lokasi perangkat...', 'loading');

        navigator.geolocation.getCurrentPosition((position) => {
            setPoint(
                position.coords.latitude,
                position.coords.longitude,
                `Lokasi terisi dengan akurasi sekitar ${Math.ceil(position.coords.accuracy)} meter.`,
            );
            map.setView(marker.getLatLng(), 18);
            locationButton.disabled = false;
        }, () => {
            setFeedback('Lokasi tidak dapat diambil. Periksa izin lokasi browser.', 'error');
            locationButton.disabled = false;
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
    });
}
