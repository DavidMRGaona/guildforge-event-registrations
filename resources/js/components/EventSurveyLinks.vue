<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import type { EventSurveyLinks } from '../types/registration';

interface EventProp {
    id: string;
    [key: string]: unknown;
}

interface Props {
    event?: EventProp;
}

const props = defineProps<Props>();
const page = usePage();
const { t } = useI18n();

const links = ref<EventSurveyLinks | null>(null);

const isAuthenticated = computed(() => {
    const auth = page.props.auth as { user: unknown } | undefined;
    return auth?.user !== null && auth?.user !== undefined;
});

// Visitors have no surveys: only a signed-in person is asked about
onMounted(async () => {
    const eventId = props.event?.id;
    if (!eventId || !isAuthenticated.value) return;

    try {
        const response = await fetch(`/eventos/${eventId}/encuestas`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;
        const body = (await response.json()) as { data: EventSurveyLinks | null };
        links.value = body.data;
    } catch (e) {
        // Survey links are an extra: the page works the same without them
        console.error('Failed to fetch event survey links:', e);
    }
});
</script>

<template>
    <!-- Plain links: the surveys' pages belong to another module -->
    <div v-if="links" class="mt-3 flex flex-col gap-2">
        <a
            v-if="links.manageUrl"
            :href="links.manageUrl"
            class="inline-flex min-h-[44px] items-center justify-center rounded-md border border-default px-4 py-2 text-sm font-medium text-base-primary transition-colors hover:bg-muted focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
        >
            {{ t('eventRegistrationsSurveys.manage') }}
        </a>
        <a
            v-for="survey in links.surveys"
            :key="survey.url"
            :href="survey.url"
            class="inline-flex min-h-[44px] items-center justify-center rounded-md px-4 py-2 text-center text-sm font-medium break-words transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface"
            :class="
                survey.action === 'respond'
                    ? 'bg-primary text-white hover:bg-primary-700'
                    : 'border border-default text-base-primary hover:bg-muted'
            "
        >
            {{
                survey.action === 'respond'
                    ? t('eventRegistrationsSurveys.respond', { title: survey.title })
                    : t('eventRegistrationsSurveys.results', { title: survey.title })
            }}
        </a>
    </div>
</template>
