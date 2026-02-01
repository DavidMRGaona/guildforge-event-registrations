<script setup lang="ts">
import { ref, computed, onMounted, Teleport, Transition } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import type {
    RegistrationConfig,
    UserRegistration,
    RegistrationFormData,
} from '../types/registration';
import { isActiveState, isWaitingState, isFinalState } from '../types/registration';
import RegistrationStatus from './RegistrationStatus.vue';
import RegistrationForm from './RegistrationForm.vue';

interface EventProp {
    id: string;
    [key: string]: unknown;
}

interface Props {
    eventId?: string;
    event?: EventProp;
    initialConfig?: RegistrationConfig | null;
    initialRegistration?: UserRegistration | null;
}

const props = defineProps<Props>();
const page = usePage();

// Support both eventId prop directly or extracting from event object (for slot system)
const resolvedEventId = computed(() => props.eventId ?? props.event?.id ?? '');

// Only render if:
// 1. Event ID is available
// 2. Config has finished loading
// 3. Registration is enabled in the config
// 4. If authenticated, registration status has finished loading
const shouldRender = computed(() => {
    if (!resolvedEventId.value) return false;
    // While config is loading, don't render yet
    if (configLoading.value) return false;
    // If config is null after loading, don't render
    if (config.value === null) return false;
    // Only render if registration is explicitly enabled
    if (!config.value.registration_enabled) return false;
    // If user is authenticated, wait for registration status to load
    if (isAuthenticated.value && registrationLoading.value) return false;
    return true;
});

// Check if registration period has passed (enabled but closed)
const isRegistrationPeriodPassed = computed(() => {
    if (!config.value) return false;
    if (!config.value.registration_enabled) return false;
    // is_open is false when registration_closes_at has passed or registration_opens_at hasn't arrived
    return !config.value.is_open;
});

const { t } = useI18n();

// Get CSRF token from meta tag
const getCsrfToken = (): string => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
};

const config = ref<RegistrationConfig | null>(props.initialConfig ?? null);
const registration = ref<UserRegistration | null>(props.initialRegistration ?? null);
const loading = ref(false);
const configLoading = ref(!props.initialConfig);
const error = ref<string | null>(null);
const showForm = ref(false);
const showCancelConfirm = ref(false);

// Use Inertia's shared auth data to check authentication
const isAuthenticated = computed(() => {
    const auth = page.props.auth as { user: unknown } | undefined;
    return auth?.user !== null && auth?.user !== undefined;
});

// Initialize registration loading based on auth state and initial props
const registrationLoading = ref(isAuthenticated.value && !props.initialRegistration);

const canRegister = computed(() => {
    if (!config.value) return false;
    if (!config.value.is_open) return false;
    if (registration.value && !isFinalState(registration.value.state)) return false;
    return true;
});

const canJoinWaitingList = computed(() => {
    if (!config.value) return false;
    if (!config.value.is_open) return false;
    if (!config.value.is_full) return false;
    if (!config.value.waiting_list_enabled) return false;
    if (registration.value && !isFinalState(registration.value.state)) return false;
    return true;
});

const canCancel = computed(() => {
    if (!registration.value) return false;
    return !isFinalState(registration.value.state);
});

const buttonText = computed(() => {
    if (!config.value) return '';

    if (registration.value) {
        if (isActiveState(registration.value.state)) {
            return t('eventRegistrations.cancelRegistration');
        }
        if (isWaitingState(registration.value.state)) {
            return t('eventRegistrations.cancelRegistration');
        }
    }

    if (!config.value.is_open) {
        return t('eventRegistrations.registrationClosed');
    }

    if (config.value.is_full) {
        if (config.value.waiting_list_enabled) {
            return t('eventRegistrations.joinWaitingList');
        }
        return t('eventRegistrations.eventFull');
    }

    return t('eventRegistrations.register');
});

const buttonDisabled = computed(() => {
    if (loading.value) return true;
    if (!config.value) return true;
    // If registration period has passed, button is disabled but visible
    if (isRegistrationPeriodPassed.value) return true;
    if (!config.value.is_open) return true;
    if (config.value.is_full && !config.value.waiting_list_enabled) return true;
    if (registration.value && !canCancel.value) return true;
    return false;
});

async function fetchStatus(): Promise<void> {
    if (!resolvedEventId.value) return;

    try {
        const response = await fetch(`/eventos/${resolvedEventId.value}/inscripcion/estado`);
        const data = await response.json();
        config.value = data.data;
    } catch (e) {
        console.error('Failed to fetch registration status:', e);
    } finally {
        configLoading.value = false;
    }
}

async function fetchRegistration(): Promise<void> {
    if (!isAuthenticated.value || !resolvedEventId.value) return;

    registrationLoading.value = true;
    try {
        const response = await fetch(`/eventos/${resolvedEventId.value}/inscripcion`, {
            credentials: 'include',
        });
        const data = await response.json();
        registration.value = data.data;
    } catch (e) {
        console.error('Failed to fetch user registration:', e);
    } finally {
        registrationLoading.value = false;
    }
}

async function handleRegister(formData: RegistrationFormData): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/eventos/${resolvedEventId.value}/inscripcion`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
            body: JSON.stringify(formData),
        });

        const data = await response.json();

        if (!response.ok) {
            error.value = data.message;
            return;
        }

        registration.value = data.data;
        showForm.value = false;
        await fetchStatus();
    } catch (e) {
        error.value = t('eventRegistrations.errors.registrationClosed');
    } finally {
        loading.value = false;
    }
}

function promptCancel(): void {
    showCancelConfirm.value = true;
}

async function handleCancel(): Promise<void> {
    showCancelConfirm.value = false;
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/eventos/${resolvedEventId.value}/inscripcion`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'include',
        });

        const data = await response.json();

        if (!response.ok) {
            error.value = data.message;
            return;
        }

        registration.value = null;
        await fetchStatus();
    } catch (e) {
        error.value = t('eventRegistrations.errors.cannotCancel');
    } finally {
        loading.value = false;
    }
}

function handleClick(): void {
    if (!isAuthenticated.value) {
        window.location.href = '/iniciar-sesion?redirect=' + encodeURIComponent(window.location.pathname);
        return;
    }

    if (registration.value && canCancel.value) {
        promptCancel();
        return;
    }

    if (config.value?.custom_fields && config.value.custom_fields.length > 0) {
        showForm.value = true;
        return;
    }

    handleRegister({ form_data: {} });
}

onMounted(async () => {
    if (!props.initialConfig) {
        await fetchStatus();
    }
    if (!props.initialRegistration) {
        await fetchRegistration();
    }
});
</script>

<template>
    <div v-if="shouldRender" class="registration-button-container">
        <!-- Current registration status -->
        <RegistrationStatus
            v-if="registration"
            :registration="registration"
            :config="config"
            class="mb-4"
        />

        <!-- Error message -->
        <div
            v-if="error"
            class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            {{ error }}
        </div>

        <!-- Registration form modal -->
        <RegistrationForm
            v-if="showForm && config"
            :custom-fields="config.custom_fields"
            :loading="loading"
            @submit="handleRegister"
            @cancel="showForm = false"
        />

        <!-- Main action button -->
        <button
            v-if="!showForm"
            type="button"
            :disabled="buttonDisabled"
            class="w-full rounded-lg px-6 py-3 text-center font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
            :class="{
                'bg-amber-600 text-white hover:bg-amber-700 focus:ring-amber-500':
                    canRegister || canJoinWaitingList,
                'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500': canCancel,
                'cursor-not-allowed bg-gray-300 text-gray-500 dark:bg-stone-700 dark:text-stone-500':
                    buttonDisabled,
            }"
            @click="handleClick"
        >
            <span v-if="loading" class="flex items-center justify-center">
                <svg
                    class="mr-2 h-5 w-5 animate-spin"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
                {{ $t('common.loading') }}
            </span>
            <span v-else>{{ buttonText }}</span>
        </button>

        <!-- Capacity info -->
        <div
            v-if="config && config.max_participants"
            class="mt-2 text-center text-sm text-gray-500 dark:text-stone-400"
        >
            {{ config.current_participants }} / {{ config.max_participants }}
            {{ $t('eventRegistrations.stats.confirmed').toLowerCase() }}
            <template v-if="config.waiting_list_enabled && config.current_waiting_list > 0">
                · {{ config.current_waiting_list }}
                {{ $t('eventRegistrations.states.waitingList').toLowerCase() }}
            </template>
        </div>

        <!-- Cancel confirmation modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showCancelConfirm"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    @click.self="showCancelConfirm = false"
                >
                    <Transition
                        enter-active-class="transition-all duration-200"
                        enter-from-class="scale-95 opacity-0"
                        enter-to-class="scale-100 opacity-100"
                        leave-active-class="transition-all duration-150"
                        leave-from-class="scale-100 opacity-100"
                        leave-to-class="scale-95 opacity-0"
                    >
                        <div
                            v-if="showCancelConfirm"
                            class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-stone-800"
                        >
                            <!-- Icon -->
                            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                                <svg
                                    class="h-6 w-6 text-red-600 dark:text-red-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            </div>

                            <!-- Title -->
                            <h3 class="mb-2 text-center text-lg font-semibold text-gray-900 dark:text-stone-100">
                                {{ $t('eventRegistrations.cancelRegistration') }}
                            </h3>

                            <!-- Message -->
                            <p class="mb-6 text-center text-sm text-gray-600 dark:text-stone-400">
                                {{ $t('eventRegistrations.confirmCancel') }}
                            </p>

                            <!-- Buttons -->
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 dark:border-stone-600 dark:text-stone-300 dark:hover:bg-stone-700"
                                    @click="showCancelConfirm = false"
                                >
                                    {{ $t('common.cancel') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                    @click="handleCancel"
                                >
                                    {{ $t('buttons.confirm') }}
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
