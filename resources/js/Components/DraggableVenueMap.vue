<script setup>
import 'leaflet/dist/leaflet.css';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { normalizeMapCoordinates } from '../lib/venue-map';

const props = defineProps({
    latitude: { type: [String, Number], required: true },
    longitude: { type: [String, Number], required: true },
    tileUrl: { type: String, required: true },
});

const emit = defineEmits(['change']);
const mapElement = ref(null);
const loadError = ref('');
let leaflet;
let map;
let marker;
let destroyed = false;

function publishPosition(position, action) {
    const coordinates = normalizeMapCoordinates(position.lat, position.lng);
    if (!coordinates) return;

    emit('change', {
        latitude: coordinates.latitudeValue,
        longitude: coordinates.longitudeValue,
        action,
    });
}

async function createMap() {
    const coordinates = normalizeMapCoordinates(props.latitude, props.longitude);
    if (!coordinates || !mapElement.value) return;

    try {
        const leafletModule = await import('leaflet');
        if (destroyed || !mapElement.value) return;

        leaflet = leafletModule.default ?? leafletModule;
        const position = [coordinates.latitude, coordinates.longitude];
        map = leaflet.map(mapElement.value, {
            center: position,
            zoom: 18,
            scrollWheelZoom: false,
        });

        leaflet.tileLayer(props.tileUrl, {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
            maxZoom: 19,
        }).addTo(map);

        const pinIcon = leaflet.divIcon({
            className: 'venue-location-marker',
            html: '<span class="venue-location-marker__pin"><span class="venue-location-marker__dot"></span></span>',
            iconSize: [40, 46],
            iconAnchor: [20, 44],
        });

        marker = leaflet.marker(position, {
            draggable: true,
            title: 'Drag this pin to the venue entrance',
            icon: pinIcon,
        }).addTo(map);

        marker.getElement()?.setAttribute('aria-label', 'Venue location pin. Drag it to the venue entrance.');

        marker.on('dragend', () => publishPosition(marker.getLatLng(), 'drag'));
        map.on('click', (event) => {
            marker.setLatLng(event.latlng);
            publishPosition(event.latlng, 'click');
        });

        await nextTick();
        map.invalidateSize();
    } catch (error) {
        loadError.value = 'The interactive map could not load. You can still enter the map numbers above.';
    }
}

watch(
    () => [props.latitude, props.longitude],
    ([latitude, longitude]) => {
        if (!map || !marker) return;

        const coordinates = normalizeMapCoordinates(latitude, longitude);
        if (!coordinates) return;

        const current = marker.getLatLng();
        if (Math.abs(current.lat - coordinates.latitude) < 0.0000001
            && Math.abs(current.lng - coordinates.longitude) < 0.0000001) return;

        const position = [coordinates.latitude, coordinates.longitude];
        marker.setLatLng(position);
        map.panTo(position);
    },
);

onMounted(createMap);

onBeforeUnmount(() => {
    destroyed = true;
    map?.remove();
    map = null;
    marker = null;
    leaflet = null;
});
</script>

<template>
    <div>
        <div
            ref="mapElement"
            class="h-80 w-full bg-slate-100"
            role="region"
            aria-label="Venue location map. Click the map or drag the pin to the venue entrance."
        ></div>
        <p v-if="loadError" class="border-t border-slate-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ loadError }}</p>
    </div>
</template>

<style>
.venue-location-marker {
    border: 0;
    background: transparent;
}

.venue-location-marker__pin {
    position: relative;
    display: block;
    width: 36px;
    height: 36px;
    transform: rotate(-45deg);
    border: 3px solid white;
    border-radius: 50% 50% 50% 0;
    background: #17895a;
    box-shadow: 0 5px 14px rgb(8 41 30 / 35%);
}

.venue-location-marker__dot {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 12px;
    height: 12px;
    transform: translate(-50%, -50%);
    border-radius: 9999px;
    background: white;
    box-shadow: inset 0 0 0 3px #82ddb0;
}

.venue-location-marker.leaflet-drag-target .venue-location-marker__pin {
    box-shadow: 0 9px 20px rgb(8 41 30 / 42%);
}
</style>
