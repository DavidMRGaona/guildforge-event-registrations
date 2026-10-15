<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import { useVerificationGate } from '@/composables/useVerificationGate';
import type {
    MyRegistration,
    MyRegistrationsResponse,
    RegistrationState,
} from '../types/registration';

type LoadState = 'loading' | 'ready' | 'error' | 'session-expired' | 'verification-required';

const { t, locale } = useI18n();
const gate = useVerificationGate();

const registrations = ref<MyRegistration[]>([]);
const loadState = ref<LoadState>('loading');
const retryButton = ref<HTMLButtonElement | null>(null);
const heading = ref<HTMLElement | null>(null);

const STATE_CLASSES: Record<RegistrationState, string> = {
    confirmed: 'bg-success-light text-success',
    pending: 'bg-warning-light text-warning',
    waiting_list: 'bg-info-light text-info',
    rejected: 'bg-error-light text-error',
    cancelled: 'bg-muted text-base-secondary',
};

function isMyRegistrationsResponse(body: unknown): body is MyRegistrationsResponse {
    return typeof body === 'object' && body !== null && Array.isArray((body as { data?: unknown }).data);
}

async function fetchRegistrations(): Promise<void> {
    // The server refuses the list while the account must verify its email: say why instead
    if (gate.blocked.value) {
        loadState.value = 'verification-required';

        return;
    }

    loadState.value = 'loading';

    try {
        const response = await fetch('/mis-inscripciones', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        // The state changed after the page loaded (e.g. in another tab)
        if (gate.isVerificationRequired(response)) {
            loadState.value = 'verification-required';

            return;
        }

        // 401 without a session, 419 when the session token is no longer valid
        if (response.status === 401 || response.status === 419) {
            loadState.value = 'session-expired';

            return;
        }

        const isJson = response.headers.get('content-type')?.includes('application/json') ?? false;
        const body: unknown = response.ok && isJson ? await response.json() : null;

        if (!isMyRegistrationsResponse(body)) {
            throw new Error(`Unexpected response (${response.status})`);
        }

        registrations.value = body.data;
        loadState.value = 'ready';
    } catch {
        loadState.value = 'error';
    }
}

// The Retry button unmounts while loading: put focus back on it if the retry fails
// again, or on the section heading once the result is shown
async function retry(): Promise<void> {
    await fetchRegistrations();
    await nextTick();

    if (loadState.value === 'error') {
        retryButton.value?.focus();
    } else {
        heading.value?.focus();
    }
}

function reloadPage(): void {
    window.location.reload();
}

function startTime(registration: MyRegistration): number {
    return registration.event ? new Date(registration.event.start_date).getTime() : Number.NEGATIVE_INFINITY;
}

const upcoming = computed<MyRegistration[]>(() => {
    const now = Date.now();

    return registrations.value
        .filter((registration) => registration.event !== null && new Date(registration.event.end_date).getTime() >= now)
        .sort((a, b) => startTime(a) - startTime(b));
});

const past = computed<MyRegistration[]>(() => {
    const now = Date.now();

    return registrations.value
        .filter((registration) => registration.event !== null && new Date(registration.event.end_date).getTime() < now)
        .sort((a, b) => startTime(b) - startTime(a));
});

// Registrations whose event is not published: they cannot be dated, so they are neither upcoming nor past
const unavailable = computed<MyRegistration[]>(() =>
    registrations.value.filter((registration) => registration.event === null),
);

const groups = computed(() =>
    [
        { id: 'upcoming', title: t('eventRegistrationsProfile.upcoming'), items: upcoming.value },
        { id: 'past', title: t('eventRegistrationsProfile.past'), items: past.value },
        { id: 'unavailable', title: t('eventRegistrationsProfile.unavailable'), items: unavailable.value },
    ].filter((group) => group.items.length > 0),
);

function formatDate(iso: string): string {
    return new Date(iso).toLocaleString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        dateStyle: 'long',
        timeStyle: 'short',
    });
}

function stateLabel(state: RegistrationState): string {
    return t(`eventRegistrationsProfile.states.${state}`);
}

onMounted(() => {
    void fetchRegistrations();
});
</script>

<template>
    <section class="space-y-6" aria-labelledby="event-registrations-profile-title">
        <h2
            id="event-registrations-profile-title"
            ref="heading"
            tabindex="-1"
            class="text-lg font-semibold text-base-primary focus:outline-none"
        >
            {{ t('eventRegistrationsProfile.title') }}
        </h2>

        <!-- Always mounted, so screen readers announce the text when it changes -->
        <p role="status" class="sr-only">
            {{ loadState === 'loading' ? t('eventRegistrationsProfile.loading') : '' }}
        </p>

        <!-- Loading -->
        <div
            v-if="loadState === 'loading'"
            class="flex items-center justify-center gap-3 py-12 text-base-muted"
            aria-hidden="true"
        >
            <svg class="h-6 w-6 animate-spin text-primary" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span>{{ t('eventRegistrationsProfile.loading') }}</span>
        </div>

        <!-- Session expired -->
        <div
            v-else-if="loadState === 'session-expired'"
            role="alert"
            class="rounded-lg bg-warning-light p-4 text-sm text-warning"
        >
            <p>{{ t('eventRegistrationsProfile.errors.sessionExpired') }}</p>
            <button
                type="button"
                class="mt-3 rounded-md border border-default bg-surface px-3 py-2 text-sm font-medium text-base-primary hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
                @click="reloadPage"
            >
                {{ t('eventRegistrationsProfile.reload') }}
            </button>
        </div>

        <!-- Email not verified -->
        <div
            v-else-if="loadState === 'verification-required'"
            class="rounded-lg bg-warning-light p-4 text-sm text-base-primary"
        >
            <p>
                {{ t('eventRegistrationsProfile.verificationRequired') }}
                <Link
                    :href="gate.noticeUrl"
                    class="font-semibold underline underline-offset-2 hover:no-underline"
                >
                    {{ gate.linkLabel.value }}
                    <span aria-hidden="true">→</span>
                </Link>
            </p>
        </div>

        <!-- Error -->
        <div
            v-else-if="loadState === 'error'"
            role="alert"
            class="rounded-lg bg-error-light p-4 text-sm text-error"
        >
            <p>{{ t('eventRegistrationsProfile.errors.loadFailed') }}</p>
            <button
                ref="retryButton"
                type="button"
                class="mt-3 rounded-md border border-default bg-surface px-3 py-2 text-sm font-medium text-base-primary hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
                @click="retry"
            >
                {{ t('eventRegistrationsProfile.retry') }}
            </button>
        </div>

        <!-- Empty -->
        <div
            v-else-if="registrations.length === 0"
            class="rounded-lg border border-dashed border-default bg-muted p-8 text-center"
        >
            <svg
                class="mx-auto h-12 w-12 text-base-muted"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
            </svg>
            <p class="mt-4 text-sm text-base-secondary">
                {{ t('eventRegistrationsProfile.empty') }}
            </p>
            <Link
                href="/eventos"
                class="mt-4 inline-block rounded-md text-sm font-semibold text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
            >
                {{ t('eventRegistrationsProfile.browseEvents') }}
            </Link>
        </div>

        <!-- Registrations -->
        <template v-else>
            <section
                v-for="group in groups"
                :key="group.id"
                :aria-labelledby="`event-registrations-profile-${group.id}`"
            >
                <h3
                    :id="`event-registrations-profile-${group.id}`"
                    class="mb-3 text-base font-semibold text-base-primary"
                >
                    {{ group.title }}
                </h3>

                <ul role="list" class="space-y-3">
                    <li
                        v-for="registration in group.items"
                        :key="registration.id"
                        class="flex flex-col gap-3 rounded-lg border border-default bg-surface p-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-base-primary">
                                <Link
                                    v-if="registration.event"
                                    :href="registration.event.url"
                                    class="rounded-sm hover:text-primary-600 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
                                >
                                    {{ registration.event.title }}
                                </Link>
                                <span v-else class="text-base-muted">
                                    {{ t('eventRegistrationsProfile.unavailableEvent') }}
                                </span>
                            </p>
                            <p v-if="registration.event" class="mt-1 text-sm text-base-secondary">
                                <time :datetime="registration.event.start_date">
                                    {{ formatDate(registration.event.start_date) }}
                                </time>
                                <template v-if="registration.event.location">
                                    · {{ registration.event.location }}
                                </template>
                            </p>
                            <p
                                v-if="registration.state === 'waiting_list' && registration.position !== null"
                                class="mt-1 text-sm text-info"
                            >
                                {{ t('eventRegistrationsProfile.waitingListPosition', { position: registration.position }) }}
                            </p>
                        </div>

                        <span
                            :class="[
                                'inline-flex shrink-0 items-center self-start rounded-full px-2.5 py-1 text-xs font-medium',
                                STATE_CLASSES[registration.state],
                            ]"
                        >
                            {{ stateLabel(registration.state) }}
                        </span>
                    </li>
                </ul>
            </section>
        </template>
    </section>
</template>
