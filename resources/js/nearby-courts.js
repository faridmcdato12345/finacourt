import { detectCurrentCoordinates } from './lib/geolocation.js';

const automaticNearbyPreference = 'finacourt.nearby.auto';

export function nearbySearchCoordinates(coordinates) {
    const latitude = Number(coordinates?.latitude);
    const longitude = Number(coordinates?.longitude);

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null;

    // Neighborhood-level precision is enough to rank courts and avoids
    // sending an unnecessarily precise device location in the query string.
    return {
        latitude: latitude.toFixed(4),
        longitude: longitude.toFixed(4),
    };
}

export function nearbyLocationErrorMessage(error) {
    switch (error?.code) {
        case 1:
            return 'Location access is blocked. Allow it in your browser settings to search nearby courts.';
        case 2:
            return 'Your device could not determine its location. Check your location service and try again.';
        case 3:
            return 'Finding your location took too long. Please try again.';
        case 'unsupported':
            return 'This browser cannot detect your location. You can still filter courts by city.';
        default:
            return 'We could not detect your location. Please try again or filter by city.';
    }
}

export function shouldAutomaticallyLocate({ hasCoordinates, permissionState, rememberedPreference }) {
    if (hasCoordinates) return false;
    if (rememberedPreference === false) return false;

    return permissionState === 'granted' || rememberedPreference === true;
}

async function geolocationPermissionState(navigatorRef) {
    if (!navigatorRef?.permissions?.query) return 'unknown';

    try {
        return (await navigatorRef.permissions.query({ name: 'geolocation' })).state;
    } catch {
        return 'unknown';
    }
}

function automaticPreference(storage) {
    try {
        const preference = storage?.getItem(automaticNearbyPreference);

        if (preference === '1') return true;
        if (preference === '0') return false;

        return null;
    } catch {
        return null;
    }
}

function savePreference(storage, enabled) {
    try {
        storage?.setItem(automaticNearbyPreference, enabled ? '1' : '0');
    } catch {
        // Nearby search still works when private browsing blocks storage.
    }
}

export function initNearbyCourtSearch(
    documentRef = globalThis.document,
    navigatorRef = globalThis.navigator,
    storage = globalThis.localStorage,
) {
    if (!documentRef) return;

    documentRef.querySelectorAll('[data-nearby-courts]').forEach(async (element) => {
        const form = element.closest('form');
        const button = element.querySelector('[data-use-current-location]');
        const latitudeInput = element.querySelector('[data-nearby-latitude]');
        const longitudeInput = element.querySelector('[data-nearby-longitude]');
        const radiusInput = element.querySelector('[data-nearby-radius]');
        const status = element.querySelector('[data-nearby-status]');
        const clearButton = element.querySelector('[data-clear-current-location]');

        if (!form || !button || !latitudeInput || !longitudeInput || !radiusInput || !status) return;

        const hasCoordinates = Boolean(latitudeInput.value && longitudeInput.value);
        let locating = false;

        const locate = async ({ remember = false } = {}) => {
            if (locating) return;

            locating = true;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            status.textContent = 'Finding your location…';

            try {
                const detected = await detectCurrentCoordinates(navigatorRef?.geolocation);
                const coordinates = nearbySearchCoordinates(detected);

                if (!coordinates) throw { code: 'invalid' };

                latitudeInput.value = coordinates.latitude;
                longitudeInput.value = coordinates.longitude;
                radiusInput.disabled = false;
                status.textContent = 'Location found. Sorting the nearest courts…';
                if (remember) savePreference(storage, true);
                form.requestSubmit();
            } catch (error) {
                status.textContent = nearbyLocationErrorMessage(error);
                button.disabled = false;
                button.removeAttribute('aria-busy');
                locating = false;
            }
        };

        button.addEventListener('click', () => locate({ remember: true }));
        clearButton?.addEventListener('click', () => {
            savePreference(storage, false);
            latitudeInput.value = '';
            longitudeInput.value = '';
            radiusInput.disabled = true;
            status.textContent = 'Nearby search removed. Showing courts from every location…';
            form.requestSubmit();
        });

        const permissionState = await geolocationPermissionState(navigatorRef);
        if (shouldAutomaticallyLocate({
            hasCoordinates,
            permissionState,
            rememberedPreference: automaticPreference(storage),
        })) {
            locate();
        } else if (!hasCoordinates && permissionState === 'denied') {
            status.textContent = nearbyLocationErrorMessage({ code: 1 });
        }
    });
}

initNearbyCourtSearch();
