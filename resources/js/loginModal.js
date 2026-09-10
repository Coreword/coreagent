// Shared module-level state for the guest login modal — a plain exported
// ref rather than provide/inject, since the pages that need to trigger it
// (Home.vue) define the slot content AuthenticatedLayout renders, so they
// sit *above* AuthenticatedLayout in the provide/inject ancestor chain, not
// below it — inject() there would never see AuthenticatedLayout's provide().
import { ref } from 'vue';

export const showLoginModal = ref(false);

export function openLoginModal() {
    showLoginModal.value = true;
}

export function closeLoginModal() {
    showLoginModal.value = false;
}
