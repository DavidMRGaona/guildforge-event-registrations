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
            return `${baseClasses} bg-success-light text-success`;
        case 'yellow':
            return `${baseClasses} bg-warning-light text-warning`;
        case 'blue':
            return `${baseClasses} bg-info-light text-info`;
        case 'red':
            return `${baseClasses} bg-error-light text-error`;
        default:
            return `${baseClasses} bg-surface text-base-secondary`;
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
    <div class="rounded-lg bg-surface p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <!-- Status icon -->
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-full"
                    :class="{
                        'bg-success-light text-success':
                            statusIcon === 'check-circle',
                        'bg-info-light text-info':
                            statusIcon === 'clock',
                        'bg-surface text-base-muted':
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
                    <p class="font-medium text-base-primary">
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
                <p class="text-sm text-base-muted">
                    {{ t('eventRegistrations.fields.position') }}
                </p>
                <p class="text-2xl font-bold text-base-primary">
                    #{{ registration.position }}
                </p>
            </div>
        </div>

        <!-- Registration details -->
        <div
            v-if="registration.confirmed_at || registration.created_at"
            class="mt-3 border-t border-default pt-3 text-sm text-base-muted"
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
