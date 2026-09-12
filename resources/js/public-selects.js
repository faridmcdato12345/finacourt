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

document.querySelectorAll('[data-booking-payment-pricing]').forEach((element) => {
    const paymentOptions = element.querySelectorAll('input[name="payment_option"]');

    if (paymentOptions.length === 0) return;

    const onlineFee = element.querySelector('[data-online-service-fee]');
    const onlineFeeNote = element.querySelector('[data-online-fee-note]');
    const payAtVenueFeeNote = element.querySelector('[data-pay-at-venue-fee-note]');
    const playerTotal = element.querySelector('[data-player-total]');
    const paymentLabel = element.querySelector('[data-payment-choice-label]');
    const paymentDetail = element.querySelector('[data-payment-choice-detail]');

    const updatePricing = () => {
        const selectedOption = element.querySelector('input[name="payment_option"]:checked');
        const isOnline = selectedOption?.value === 'online';

        if (onlineFee) onlineFee.hidden = !isOnline;
        if (onlineFeeNote) onlineFeeNote.hidden = !isOnline;
        if (payAtVenueFeeNote) payAtVenueFeeNote.hidden = isOnline;
        if (playerTotal) {
            playerTotal.textContent = isOnline
                ? element.dataset.onlineTotal
                : element.dataset.payAtVenueTotal;
        }
        if (paymentLabel) paymentLabel.textContent = isOnline ? 'Pay online' : 'Pay at venue';
        if (paymentDetail) paymentDetail.textContent = isOnline ? 'Secure checkout' : 'At the venue';
    };

    paymentOptions.forEach((option) => option.addEventListener('change', updatePricing));
    updatePricing();
});
