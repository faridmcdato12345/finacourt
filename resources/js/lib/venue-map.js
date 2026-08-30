export function normalizeMapCoordinates(latitude, longitude) {
    if (latitude === '' || latitude === null || latitude === undefined
        || longitude === '' || longitude === null || longitude === undefined) {
        return null;
    }

    const parsedLatitude = Number(latitude);
    const parsedLongitude = Number(longitude);

    if (!Number.isFinite(parsedLatitude) || !Number.isFinite(parsedLongitude)
        || parsedLatitude < -90 || parsedLatitude > 90
        || parsedLongitude < -180 || parsedLongitude > 180) {
        return null;
    }

    return {
        latitude: parsedLatitude,
        longitude: parsedLongitude,
        latitudeValue: parsedLatitude.toFixed(7),
        longitudeValue: parsedLongitude.toFixed(7),
    };
}
