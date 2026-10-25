<script setup lang="ts">
import { CircleCheck, CircleX, Clock } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { UserRegistration, RegistrationConfig } from '../types/registration';
import { getStateColor, isActiveState, isWaitingState } from '../types/registration';

interface Props {
    registration: UserRegistration;
    config?: RegistrationConfig | null;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();
function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(locale.value, { timeZone: VENUE_TIMEZONE });
}


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
                    <CircleCheck
                        v-if="statusIcon === 'check-circle'"
                        class="h-6 w-6"
                        aria-hidden="true"
                    />
                    <!-- Clock -->
                    <Clock v-else-if="statusIcon === 'clock'" class="h-6 w-6" aria-hidden="true" />
                    <!-- X circle -->
                    <CircleX v-else class="h-6 w-6" aria-hidden="true" />
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
                {{ formatDate(registration.confirmed_at) }}
            </p>
            <p v-else-if="registration.created_at">
                {{ t('eventRegistrations.fields.createdAt') }}:
                {{ formatDate(registration.created_at) }}
            </p>
        </div>
    </div>
</template>
