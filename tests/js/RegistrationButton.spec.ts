import { describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import RegistrationButton from '../../resources/js/components/RegistrationButton.vue';
import type { RegistrationConfig } from '../../resources/js/types/registration';

const guideVisible = vi.hoisted(() => ({ value: true }));

// Whether the person may read the sign-up guide is the server's call: each test decides it
vi.mock('@/composables/useKnowledgeVisibility', async () => {
    const { ref } = await import('vue');

    return {
        useKnowledgeVisibility: (): { isVisible: () => boolean; ready: { value: boolean } } => ({
            isVisible: (): boolean => guideVisible.value,
            ready: ref(true),
        }),
    };
});

function config(overrides: Partial<RegistrationConfig> = {}): RegistrationConfig {
    return {
        event_id: 'event-1',
        registration_enabled: true,
        max_participants: 12,
        waiting_list_enabled: false,
        max_waiting_list: null,
        registration_opens_at: null,
        registration_closes_at: null,
        cancellation_deadline: null,
        requires_confirmation: false,
        requires_payment: false,
        members_only: false,
        custom_fields: [],
        confirmation_message: null,
        current_participants: 0,
        current_waiting_list: 0,
        available_spots: 12,
        is_open: true,
        is_full: false,
        can_cancel: false,
        cancellation_closed_reason: null,
        meets_members_only: true,
        ...overrides,
    };
}

function mountButton(initialConfig: RegistrationConfig): VueWrapper {
    return mount(RegistrationButton, {
        props: { eventId: 'event-1', initialConfig },
    });
}

describe('RegistrationButton help link', () => {
    it('sits at the end of the participant count', () => {
        guideVisible.value = true;
        const button = mountButton(config());

        const count = button.get('[data-testid="registration-capacity"]');
        expect(count.text()).toContain('0 / 12 confirmados');
        expect(count.find('[data-testid="help-link"]').exists()).toBe(true);
        expect(button.findAll('[data-testid="help-link"]')).toHaveLength(1);
    });

    it('keeps a line of its own when there is no count to show', () => {
        guideVisible.value = true;
        const button = mountButton(config({ max_participants: null, current_participants: 0 }));

        expect(button.find('[data-testid="registration-capacity"]').text()).toBe('?');
        expect(button.findAll('[data-testid="help-link"]')).toHaveLength(1);
    });

    it('is left out when the person may not read the guide', () => {
        guideVisible.value = false;
        const button = mountButton(config());

        expect(button.get('[data-testid="registration-capacity"]').text()).toContain(
            '0 / 12 confirmados'
        );
        expect(button.find('[data-testid="help-link"]').exists()).toBe(false);
    });
});
