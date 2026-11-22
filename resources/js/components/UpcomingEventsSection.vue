<script setup lang="ts">
import ProfileEventList from './ProfileEventList.vue';
import type { ProfileEvent } from '../types/registration';

/**
 * «Próximos eventos» (`event-registrations.upcoming`) on a public profile: the events the owner is
 * confirmed in that have not ended, soonest first. The day and the hour tell when to be there.
 */
defineProps<{
    events: ProfileEvent[];
}>();

const dateFormat: Intl.DateTimeFormatOptions = {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
};
</script>

<template>
    <ProfileEventList :events="events" :date-format="dateFormat" data-testid="upcoming-events" />
</template>
