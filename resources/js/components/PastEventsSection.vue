<script setup lang="ts">
import ProfileEventList from './ProfileEventList.vue';
import type { ProfileEvent } from '../types/registration';

/**
 * «Últimos eventos asistidos» (`event-registrations.past`) on a public profile: the latest events
 * that ended with the owner confirmed in them, latest first. The hour no longer matters, but the
 * year does: one of them may be from another year.
 */
defineProps<{
    events: ProfileEvent[];
}>();

const dateFormat: Intl.DateTimeFormatOptions = {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
};
</script>

<template>
    <ProfileEventList :events="events" :date-format="dateFormat" data-testid="past-events" />
</template>
