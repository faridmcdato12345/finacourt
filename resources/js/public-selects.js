import { createApp } from 'vue';
import PublicDateIsland from './Components/PublicDateIsland.vue';
import PublicNumberIsland from './Components/PublicNumberIsland.vue';
import PublicSelectIsland from './Components/PublicSelectIsland.vue';
import './loyalty-carousel';

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
    const onlineFeeAmount = element.querySelector('[data-online-service-fee-amount]');
    const onlineFeeNote = element.querySelector('[data-online-fee-note]');
    const payAtVenueFeeNote = element.querySelector('[data-pay-at-venue-fee-note]');
    const playerTotal = element.querySelector('[data-player-total]');
    const paymentLabel = element.querySelector('[data-payment-choice-label]');
    const paymentDetail = element.querySelector('[data-payment-choice-detail]');
    const loyaltyDiscount = element.querySelector('[data-loyalty-discount]');
    const onlineCourtPrice = element.querySelector('[data-online-court-price]');
    const payAtVenueCourtPrice = element.querySelector('[data-pay-at-venue-court-price]');
    const onlineDealSaving = element.querySelector('[data-online-deal-saving]');
    const payAtVenueDealSaving = element.querySelector('[data-pay-at-venue-deal-saving]');
    const loyaltyChoice = element.querySelector('[data-loyalty-reward-choice]');

    const updatePricing = () => {
        const selectedOption = element.querySelector('input[name="payment_option"]:checked');
        const isOnline = selectedOption?.value === 'online';
        if (loyaltyChoice) loyaltyChoice.disabled = !isOnline;
        const useReward = isOnline && loyaltyChoice?.checked;
        const fee = useReward ? element.dataset.loyaltyFee : element.dataset.onlineFee;

        if (onlineFee) onlineFee.hidden = !isOnline || fee === '₱0.00';
        if (onlineFeeAmount) onlineFeeAmount.textContent = fee;
        if (onlineFeeNote) onlineFeeNote.hidden = !isOnline || fee === '₱0.00';
        if (payAtVenueFeeNote) payAtVenueFeeNote.hidden = isOnline;
        if (loyaltyDiscount) loyaltyDiscount.hidden = !useReward;
        if (onlineCourtPrice) onlineCourtPrice.hidden = !isOnline;
        if (payAtVenueCourtPrice) payAtVenueCourtPrice.hidden = isOnline;
        if (onlineDealSaving) onlineDealSaving.hidden = !isOnline;
        if (payAtVenueDealSaving) payAtVenueDealSaving.hidden = isOnline;
        if (playerTotal) {
            playerTotal.textContent = useReward ? element.dataset.loyaltyTotal
                : (isOnline ? element.dataset.onlineTotal : element.dataset.payAtVenueTotal);
        }
        if (paymentLabel) paymentLabel.textContent = isOnline ? 'Pay online' : 'Pay at venue';
        if (paymentDetail) paymentDetail.textContent = isOnline ? 'Secure checkout' : 'At the venue';
    };

    paymentOptions.forEach((option) => option.addEventListener('change', updatePricing));
    if (loyaltyChoice) loyaltyChoice.addEventListener('change', updatePricing);
    updatePricing();
});
