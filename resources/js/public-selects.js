import { createApp } from 'vue';
import PublicDateIsland from './Components/PublicDateIsland.vue';
import PublicNumberIsland from './Components/PublicNumberIsland.vue';
import PublicSelectIsland from './Components/PublicSelectIsland.vue';

document.querySelectorAll('[data-public-select]').forEach((element) => {
    const configElement = element.querySelector('[data-public-select-config]');

    if (!configElement) return;

    try {
        const config = JSON.parse(configElement.textContent);
        createApp(PublicSelectIsland, { config }).mount(element);
    } catch (error) {
        console.error('Unable to enhance marketplace select.', error);
    }
});

document.querySelectorAll('[data-public-date]').forEach((element) => {
    const configElement = element.querySelector('[data-public-date-config]');

    if (!configElement) return;

    try {
        const config = JSON.parse(configElement.textContent);
        createApp(PublicDateIsland, { config }).mount(element);
    } catch (error) {
        console.error('Unable to enhance marketplace date field.', error);
    }
});

document.querySelectorAll('[data-public-number]').forEach((element) => {
    const configElement = element.querySelector('[data-public-number-config]');

    if (!configElement) return;

    try {
        const config = JSON.parse(configElement.textContent);
        createApp(PublicNumberIsland, { config }).mount(element);
    } catch (error) {
        console.error('Unable to enhance marketplace number field.', error);
    }
});
