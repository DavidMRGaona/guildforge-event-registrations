<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { ref, computed, onMounted, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { Link, usePage } from '@inertiajs/vue3';
import { csrfHeaders, isCsrfFailure } from '@/utils/csrf';
import { useVerificationGate } from '@/composables/useVerificationGate';
import type {
    RegistrationConfig,
    UserRegistration,
    RegistrationFormData,
} from '../types/registration';
import { isFinalState } from '../types/registration';
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
const gate = useVerificationGate();
// Unique per instance: the reason the button is disabled while the email is not verified
const verificationNoticeId = useId();

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

// Set when the server says the account must verify its email although the page did not
// (the state changed after the page loaded, e.g. in another tab)
const verificationRequiredByServer = ref(false);

// While the account must verify its email the server refuses the registration routes, so
// the registration is not read and register or cancel cannot be sent
const mustVerifyEmail = computed(() => gate.blocked.value || verificationRequiredByServer.value);

// Initialize registration loading based on auth state and initial props
const registrationLoading = ref(
    isAuthenticated.value && !props.initialRegistration && !gate.blocked.value
);

const hasActiveRegistration = computed(
    () => !!registration.value && !isFinalState(registration.value.state)
);

// Signed-in users who are not association members cannot register to a members-only event;
// the server refuses them too. Guests keep the button, which sends them to the login page.
const isBlockedByMembersOnly = computed(() => {
    if (!config.value || !config.value.members_only) return false;
    if (!isAuthenticated.value) return false;
    if (hasActiveRegistration.value) return false;
    return config.value.meets_members_only === false;
});

// Shown while registration is open, to anyone without an active registration
const showMembersOnlyNotice = computed(
    () =>
        !!config.value &&
        config.value.members_only &&
        config.value.is_open &&
        !hasActiveRegistration.value
);

// Without a limit the count only shows once someone has registered
const showCapacity = computed(
    () =>
        !!config.value &&
        (config.value.max_participants !== null || config.value.current_participants > 0)
);

const membersOnlyNoticeId = computed(() => `members-only-notice-${resolvedEventId.value}`);

const canRegister = computed(() => {
    if (!config.value) return false;
    if (!config.value.is_open) return false;
    if (isBlockedByMembersOnly.value) return false;
    if (hasActiveRegistration.value) return false;
    return true;
});

const canJoinWaitingList = computed(() => {
    if (!config.value) return false;
    if (!config.value.is_open) return false;
    if (!config.value.is_full) return false;
    if (!config.value.waiting_list_enabled) return false;
    if (isBlockedByMembersOnly.value) return false;
    if (registration.value && !isFinalState(registration.value.state)) return false;
    return true;
});

// The server decides the cancellation window: the deadline and the start of the event
const canCancel = computed(() => hasActiveRegistration.value && config.value?.can_cancel === true);

// An active registration that can no longer be cancelled: the main button is not rendered and
// RegistrationStatus alone tells the user their state
const isCancellationClosed = computed(() => hasActiveRegistration.value && !canCancel.value);

const buttonText = computed(() => {
    if (!config.value) return '';

    // Only rendered for an active registration while it can be cancelled (see isCancellationClosed)
    if (hasActiveRegistration.value) {
        return t('eventRegistrations.cancelRegistration');
    }

    if (!config.value.is_open) {
        return t('eventRegistrations.registrationClosed');
    }

    if (isBlockedByMembersOnly.value) {
        return t('eventRegistrationsEligibility.membersOnly');
    }

    if (config.value.is_full) {
        if (config.value.waiting_list_enabled) {
            return t('eventRegistrations.joinWaitingList');
        }
        return t('eventRegistrations.eventFull');
    }

    return t('eventRegistrations.register');
});

// Disabled for reasons other than the email verification
const isUnavailable = computed(() => {
    if (!config.value) return true;
    // An active registration can only be cancelled: the cancellation window decides, not the registration one
    if (hasActiveRegistration.value) return !canCancel.value;
    // If registration period has passed, button is disabled but visible
    if (isRegistrationPeriodPassed.value) return true;
    if (!config.value.is_open) return true;
    if (isBlockedByMembersOnly.value) return true;
    if (config.value.is_full && !config.value.waiting_list_enabled) return true;
    // A cancelled or rejected registration keeps the button disabled, as before
    if (registration.value) return true;
    return false;
});

// Why the button is disabled while the account must verify its email; null when the button
// would be disabled anyway (closed, full, members only) or the account need not verify
const verificationReason = computed<string | null>(() => {
    if (!mustVerifyEmail.value || isUnavailable.value) return null;
    return canCancel.value ? gate.reason('cancelRegistration') : gate.reason('register');
});

const buttonDisabled = computed(
    () => loading.value || isUnavailable.value || verificationReason.value !== null
);

const describedBy = computed<string | undefined>(() => {
    const ids: string[] = [];
    if (showMembersOnlyNotice.value) ids.push(membersOnlyNoticeId.value);
    if (verificationReason.value !== null) ids.push(verificationNoticeId);
    return ids.length > 0 ? ids.join(' ') : undefined;
});

async function fetchStatus(): Promise<void> {
    if (!resolvedEventId.value) return;

    try {
        const response = await fetch(`/eventos/${resolvedEventId.value}/inscripcion/estado`);
        // An error answer (404, 429, 500) carries no config: keep it null so the button hides
        const data = response.ok ? await response.json() : null;
        config.value = data?.data ?? null;
    } catch (e) {
        console.error('Failed to fetch registration status:', e);
    } finally {
        configLoading.value = false;
    }
}

async function fetchRegistration(): Promise<void> {
    if (!isAuthenticated.value || !resolvedEventId.value) return;
    if (gate.blocked.value) return;

    registrationLoading.value = true;
    try {
        const response = await fetch(`/eventos/${resolvedEventId.value}/inscripcion`, {
            credentials: 'include',
        });

        // Followed the redirect to the verification notice: show the reason, not an error
        if (gate.isVerificationRequired(response)) {
            verificationRequiredByServer.value = true;
            return;
        }

        const data = response.ok ? await response.json() : null;
        registration.value = data?.data ?? null;
    } catch (e) {
        console.error('Failed to fetch user registration:', e);
    } finally {
        registrationLoading.value = false;
    }
}

async function handleRegister(formData: RegistrationFormData): Promise<void> {
    if (mustVerifyEmail.value) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/eventos/${resolvedEventId.value}/inscripcion`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            credentials: 'include',
            body: JSON.stringify(formData),
        });

        if (gate.isVerificationRequired(response)) {
            gate.goToNotice();
            return;
        }

        if (isCsrfFailure(response)) {
            error.value = t('eventRegistrations.errors.sessionExpired');
            return;
        }

        const data = await response.json();

        if (!response.ok) {
            error.value = data.message;
            return;
        }

        registration.value = data.data;
        showForm.value = false;
        await fetchStatus();
    } catch {
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
    if (mustVerifyEmail.value) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/eventos/${resolvedEventId.value}/inscripcion`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                ...csrfHeaders(),
            },
            credentials: 'include',
        });

        if (gate.isVerificationRequired(response)) {
            gate.goToNotice();
            return;
        }

        if (isCsrfFailure(response)) {
            error.value = t('eventRegistrations.errors.sessionExpired');
            return;
        }

        const data = await response.json();

        if (!response.ok) {
            error.value = data.message;
            // The cancellation window may have closed while the page was open
            await fetchStatus();
            return;
        }

        registration.value = null;
        await fetchStatus();
    } catch {
        error.value = t('eventRegistrations.errors.cannotCancel');
    } finally {
        loading.value = false;
    }
}

function handleClick(): void {
    if (!isAuthenticated.value) {
        window.location.href =
            '/iniciar-sesion?redirect=' + encodeURIComponent(window.location.pathname);
        return;
    }

    if (mustVerifyEmail.value) return;

    // An active registration is never registered again; it is cancelled only inside the window
    if (hasActiveRegistration.value) {
        if (canCancel.value) promptCancel();
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
        <div v-if="error" class="mb-4 rounded-lg bg-error-light p-4 text-sm text-error">
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
            v-if="!showForm && !isCancellationClosed"
            type="button"
            :disabled="buttonDisabled"
            :aria-describedby="describedBy"
            class="w-full rounded-lg px-6 py-3 text-center font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
            :class="{
                'bg-primary text-white hover:bg-primary-700 focus:ring-primary-500':
                    (canRegister || canJoinWaitingList) && verificationReason === null,
                'bg-error text-white hover:opacity-90 focus:ring-2 focus:ring-primary':
                    canCancel && verificationReason === null,
                'cursor-not-allowed bg-muted text-base-muted': buttonDisabled,
            }"
            @click="handleClick"
        >
            <span v-if="loading" class="flex items-center justify-center">
                <LoadingSpinner class="mr-2" size="sm" color="text-current" />
                {{ $t('common.loading') }}
            </span>
            <span v-else>{{ buttonText }}</span>
        </button>

        <!-- Why the button is disabled while the email is not verified -->
        <p
            v-if="verificationReason !== null"
            :id="verificationNoticeId"
            class="mt-2 rounded-md bg-warning-light p-3 text-center text-sm text-base-primary"
        >
            {{ verificationReason }}
            <Link
                :href="gate.noticeUrl"
                class="font-semibold underline underline-offset-2 hover:no-underline"
            >
                {{ gate.linkLabel.value }}
                <span aria-hidden="true">→</span>
            </Link>
        </p>

        <!-- Members-only notice -->
        <p
            v-if="showMembersOnlyNotice"
            :id="membersOnlyNoticeId"
            class="mt-2 text-center text-sm text-base-muted"
        >
            {{ $t('eventRegistrationsEligibility.membersOnlyNotice') }}
        </p>

        <!-- Capacity info: out of the limit when there is one -->
        <div v-if="config && showCapacity" class="mt-2 text-center text-sm text-base-muted">
            <template v-if="config.max_participants !== null">
                {{ config.current_participants }} / {{ config.max_participants }}
                {{ $t('eventRegistrations.stats.confirmed').toLowerCase() }}
            </template>
            <template v-else>
                {{ t('eventRegistrationsCapacity.confirmed', config.current_participants) }}
            </template>
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
                            class="w-full max-w-md rounded-xl bg-surface p-6 shadow-2xl"
                        >
                            <!-- Icon -->
                            <div
                                class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-error-light"
                            >
                                <TriangleAlert class="h-6 w-6 text-error" aria-hidden="true" />
                            </div>

                            <!-- Title -->
                            <h3 class="mb-2 text-center text-lg font-semibold text-base-primary">
                                {{ $t('eventRegistrations.cancelRegistration') }}
                            </h3>

                            <!-- Message -->
                            <p class="mb-6 text-center text-sm text-base-muted">
                                {{ $t('eventRegistrations.confirmCancel') }}
                            </p>

                            <!-- Buttons -->
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border border-default px-4 py-2.5 text-sm font-medium text-base-secondary transition-colors hover:bg-muted focus:outline-none focus:ring-2 focus:ring-primary"
                                    @click="showCancelConfirm = false"
                                >
                                    {{ $t('common.cancel') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg bg-error px-4 py-2.5 text-sm font-medium text-white transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary"
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
