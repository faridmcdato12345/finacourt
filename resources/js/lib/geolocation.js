const locationOptions = Object.freeze({
    enableHighAccuracy: true,
    timeout: 15000,
    maximumAge: 0,
});

export function detectCurrentCoordinates(geolocation) {
    if (!geolocation || typeof geolocation.getCurrentPosition !== 'function') {
        return Promise.reject({ code: 'unsupported' });
    }

    return new Promise((resolve, reject) => {
        geolocation.getCurrentPosition(
            (position) => {
                const latitude = Number(position?.coords?.latitude);
                const longitude = Number(position?.coords?.longitude);
                const accuracy = Number(position?.coords?.accuracy);

                if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                    reject({ code: 'invalid' });

                    return;
                }

                resolve({
                    latitude: latitude.toFixed(7),
                    longitude: longitude.toFixed(7),
                    accuracy: Number.isFinite(accuracy) && accuracy >= 0 ? Math.round(accuracy) : null,
                });
            },
            reject,
            locationOptions,
        );
    });
}

export function locationErrorMessage(error) {
    switch (error?.code) {
        case 1:
            return 'Location permission was not allowed. You can allow it in your browser settings, or enter the map numbers yourself.';
        case 2:
            return 'Your device could not find its location. Move somewhere with a clearer signal and try again.';
        case 3:
            return 'Finding your location took too long. Please try again or enter the map numbers yourself.';
        case 'unsupported':
            return 'This browser cannot detect your location. You can still enter the map numbers yourself.';
        default:
            return 'We could not detect your location. Please try again or enter the map numbers yourself.';
    }
}
