<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import type { UserRegistration } from '../types/registration';
import { getStateColor } from '../types/registration';

const { t } = useI18n();

const registrations = ref<UserRegistration[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

async function fetchRegistrations(): Promise<void> {
    try {
        const response = await fetch('/api/my-registrations', {
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error('Failed to fetch registrations');
        }

        const data = await response.json();
        registrations.value = data.data;
    } catch (e) {
        error.value = t('eventRegistrations.errors.notFound');
    } finally {
        loading.value = false;
    }
}

function getStateClasses(state: string): string {
    const color = getStateColor(state as 'pending' | 'confirmed' | 'waiting_list' | 'cancelled' | 'rejected');
    const baseClasses = 'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium';

    switch (color) {
        case 'green':
            return `${baseClasses} bg-success-light text-success`;
        case 'yellow':
            return `${baseClasses} bg-warning-light text-warning`;
        case 'blue':
            return `${baseClasses} bg-info-light text-info`;
        case 'red':
            return `${baseClasses} bg-error-light text-error`;
        default:
            return `${baseClasses} bg-muted text-base-secondary`;
    }
}

onMounted(() => {
    fetchRegistrations();
});
</script>

<template>
    <div class="user-registrations">
        <h2 class="mb-4 text-xl font-semibold text-base-primary">
            {{ t('eventRegistrations.myRegistrations') }}
        </h2>

        <!-- Loading state -->
        <div
            v-if="loading"
            class="flex items-center justify-center py-8"
        >
            <svg
                class="h-8 w-8 animate-spin text-primary"
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
        </div>

        <!-- Error state -->
        <div
            v-else-if="error"
            class="rounded-lg bg-error-light p-4 text-center text-error"
        >
            {{ error }}
        </div>

        <!-- Empty state -->
        <div
            v-else-if="registrations.length === 0"
            class="rounded-lg bg-muted p-8 text-center"
        >
            <svg
                class="mx-auto h-12 w-12 text-base-muted"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
            </svg>
            <p class="mt-4 text-base-muted">
                {{ t('common.no_results') }}
            </p>
            <a
                href="/eventos"
                class="mt-4 inline-block text-primary hover:underline"
            >
                {{ t('common.viewAll') }} {{ t('common.events').toLowerCase() }}
            </a>
        </div>

        <!-- Registrations list -->
        <div
            v-else
            class="space-y-4"
        >
            <div
                v-for="registration in registrations"
                :key="registration.id"
                class="rounded-lg border border-default bg-surface p-4"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="font-medium text-base-primary">
                            {{ registration.event_id }}
                        </h3>
                        <span :class="getStateClasses(registration.state)">
                            {{ registration.state_label }}
                        </span>
                    </div>

                    <div class="text-right text-sm text-base-muted">
                        <p v-if="registration.created_at">
                            {{ new Date(registration.created_at).toLocaleDateString() }}
                        </p>
                        <p
                            v-if="registration.state === 'waiting_list' && registration.position"
                            class="font-medium text-info"
                        >
                            #{{ registration.position }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
