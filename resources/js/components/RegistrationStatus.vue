<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { UserRegistration, RegistrationConfig } from '../types/registration';
import { getStateColor, isActiveState, isWaitingState } from '../types/registration';

interface Props {
    registration: UserRegistration;
    config?: RegistrationConfig | null;
}

const props = defineProps<Props>();

const { t } = useI18n();

const stateClasses = computed(() => {
    const color = getStateColor(props.registration.state);
    const baseClasses = 'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium';

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
});

const statusIcon = computed(() => {
    if (isActiveState(props.registration.state)) {
        return 'check-circle';
    }
    if (isWaitingState(props.registration.state)) {
        return 'clock';
    }
    return 'x-circle';
});

const showPosition = computed(() => {
    return props.registration.state === 'waiting_list' && props.registration.position !== null;
});
</script>

<template>
    <div class="rounded-lg bg-gray-50 p-4 dark:bg-stone-800/50">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <!-- Status icon -->
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-full"
                    :class="{
                        'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400':
                            statusIcon === 'check-circle',
                        'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400':
                            statusIcon === 'clock',
                        'bg-gray-100 text-gray-600 dark:bg-stone-700 dark:text-stone-400':
                            statusIcon === 'x-circle',
                    }"
                >
                    <!-- Check circle -->
                    <svg
                        v-if="statusIcon === 'check-circle'"
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <!-- Clock -->
                    <svg
                        v-else-if="statusIcon === 'clock'"
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <!-- X circle -->
                    <svg
                        v-else
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>

                <div>
                    <p class="font-medium text-gray-900 dark:text-stone-100">
                        {{ t('eventRegistrations.yourStatus') }}
                    </p>
                    <span :class="stateClasses">
                        {{ registration.state_label }}
                    </span>
                </div>
            </div>

            <!-- Position in waiting list -->
            <div
                v-if="showPosition"
                class="text-right"
            >
                <p class="text-sm text-gray-500 dark:text-stone-400">
                    {{ t('eventRegistrations.fields.position') }}
                </p>
                <p class="text-2xl font-bold text-gray-900 dark:text-stone-100">
                    #{{ registration.position }}
                </p>
            </div>
        </div>

        <!-- Registration details -->
        <div
            v-if="registration.confirmed_at || registration.created_at"
            class="mt-3 border-t border-gray-200 pt-3 text-sm text-gray-500 dark:border-stone-700 dark:text-stone-400"
        >
            <p v-if="registration.confirmed_at">
                {{ t('eventRegistrations.fields.confirmedAt') }}:
                {{ new Date(registration.confirmed_at).toLocaleDateString() }}
            </p>
            <p v-else-if="registration.created_at">
                {{ t('eventRegistrations.fields.createdAt') }}:
                {{ new Date(registration.created_at).toLocaleDateString() }}
            </p>
        </div>
    </div>
</template>
