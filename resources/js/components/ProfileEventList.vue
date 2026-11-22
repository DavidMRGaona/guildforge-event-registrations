<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { ProfileEvent } from '../types/registration';

/**
 * The list both profile sections draw («Próximos eventos», «Últimos eventos asistidos»): the events
 * the server chose for the person looking, each with its link, start and place. The card and its
 * title are the core's. Every text is drawn as text and every link comes from the event's route.
 */
const props = defineProps<{
    events: ProfileEvent[];
    /** How the start is written: the section knows whether the hour or the year matters */
    dateFormat: Intl.DateTimeFormatOptions;
}>();

const { locale } = useI18n();

// Drawn in the site's display zone, the one the server used to split upcoming from past
const formatter = computed(
    () => new Intl.DateTimeFormat(locale.value, { ...props.dateFormat, timeZone: VENUE_TIMEZONE })
);

function formatStart(startsAt: string | null): string {
    if (startsAt === null) return '';
    const date = new Date(startsAt);

    return Number.isNaN(date.getTime()) ? '' : formatter.value.format(date);
}
</script>

<template>
    <ul class="space-y-3">
        <li v-for="event in events" :key="event.url" class="min-w-0">
            <Link
                :href="event.url"
                class="block rounded-sm text-sm font-medium break-words text-base-primary hover:text-primary-600 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
            >
                {{ event.title }}
            </Link>
            <p v-if="formatStart(event.startsAt) !== ''" class="mt-0.5 text-xs text-base-secondary">
                <time :datetime="event.startsAt ?? undefined">{{
                    formatStart(event.startsAt)
                }}</time>
            </p>
            <p v-if="event.location" class="text-xs break-words text-base-secondary">
                {{ event.location }}
            </p>
        </li>
    </ul>
</template>
