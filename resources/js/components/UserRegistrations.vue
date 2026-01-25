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
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400`;
        case 'yellow':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400`;
        case 'blue':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400`;
        case 'red':
            return `${baseClasses} bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-stone-700 dark:text-stone-300`;
    }
}

onMounted(() => {
    fetchRegistrations();
});
</script>

<template>
    <div class="user-registrations">
        <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-stone-100">
            {{ t('eventRegistrations.myRegistrations') }}
        </h2>

        <!-- Loading state -->
        <div
            v-if="loading"
            class="flex items-center justify-center py-8"
        >
            <svg
                class="h-8 w-8 animate-spin text-amber-600"
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
            class="rounded-lg bg-red-50 p-4 text-center text-red-800 dark:bg-red-900/20 dark:text-red-400"
        >
            {{ error }}
        </div>

        <!-- Empty state -->
        <div
            v-else-if="registrations.length === 0"
            class="rounded-lg bg-gray-50 p-8 text-center dark:bg-stone-800/50"
        >
            <svg
                class="mx-auto h-12 w-12 text-gray-400 dark:text-stone-500"
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
            <p class="mt-4 text-gray-500 dark:text-stone-400">
                {{ t('common.no_results') }}
            </p>
            <a
                href="/eventos"
                class="mt-4 inline-block text-amber-600 hover:text-amber-700 dark:text-amber-500 dark:hover:text-amber-400"
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
                class="rounded-lg border border-gray-200 bg-white p-4 dark:border-stone-700 dark:bg-stone-800"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-stone-100">
                            {{ registration.event_id }}
                        </h3>
                        <span :class="getStateClasses(registration.state)">
                            {{ registration.state_label }}
                        </span>
                    </div>

                    <div class="text-right text-sm text-gray-500 dark:text-stone-400">
                        <p v-if="registration.created_at">
                            {{ new Date(registration.created_at).toLocaleDateString() }}
                        </p>
                        <p
                            v-if="registration.state === 'waiting_list' && registration.position"
                            class="font-medium text-blue-600 dark:text-blue-400"
                        >
                            #{{ registration.position }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
