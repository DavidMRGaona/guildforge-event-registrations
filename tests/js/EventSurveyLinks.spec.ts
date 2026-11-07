import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import EventSurveyLinks from '../../resources/js/components/EventSurveyLinks.vue';
import type { EventSurveyLinks as Links } from '../../resources/js/types/registration';
import { page } from './support/page';

function links(overrides: Partial<Links> = {}): Links {
    return {
        manageUrl: null,
        surveys: [{ title: 'Valoración', url: '/encuestas/1', action: 'respond' }],
        guideId: 'surveys.respond',
        ...overrides,
    };
}

async function mountWith(data: Links | null): Promise<VueWrapper> {
    page.props = { auth: { user: { id: 'user-1' } } };
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => new Response(JSON.stringify({ data }), { status: 200 }))
    );

    const wrapper = mount(EventSurveyLinks, { props: { event: { id: 'event-1' } } });
    await flushPromises();

    return wrapper;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('EventSurveyLinks help link', () => {
    it('sits beside the survey links instead of on a line of its own', async () => {
        const wrapper = await mountWith(
            links({
                manageUrl: '/encuestas/gestionar',
                surveys: [
                    { title: 'Valoración', url: '/encuestas/1', action: 'respond' },
                    { title: 'Fechas', url: '/encuestas/2', action: 'view_results' },
                ],
            })
        );

        const row = wrapper.get('[data-testid="survey-links"]');
        const list = row.get('[data-testid="survey-link-list"]');
        expect(list.findAll('a')).toHaveLength(3);
        expect(row.get('[data-testid="help-link"]').element.parentElement).toBe(row.element);
        expect(list.find('[data-testid="help-link"]').exists()).toBe(false);
    });

    it('sits beside a single link too', async () => {
        const wrapper = await mountWith(links());

        const row = wrapper.get('[data-testid="survey-links"]');
        expect(row.get('[data-testid="survey-link-list"]').findAll('a')).toHaveLength(1);
        expect(row.get('[data-testid="help-link"]').attributes('data-guide')).toBe(
            'surveys.respond'
        );
    });

    it('is left out without a guide for this person', async () => {
        const wrapper = await mountWith(links({ guideId: null }));

        expect(wrapper.find('[data-testid="survey-links"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="help-link"]').exists()).toBe(false);
    });
});
