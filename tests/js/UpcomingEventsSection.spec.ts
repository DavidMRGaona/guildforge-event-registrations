import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import UpcomingEventsSection from '../../resources/js/components/UpcomingEventsSection.vue';
import type { ProfileUpcomingEvent } from '../../resources/js/types/registration';

function event(overrides: Partial<ProfileUpcomingEvent> = {}): ProfileUpcomingEvent {
    return {
        title: 'Jornada de rol',
        url: '/eventos/jornada-de-rol',
        startsAt: '2030-06-25T14:00:00+02:00',
        location: 'Local de la asociación',
        ...overrides,
    };
}

describe('UpcomingEventsSection', () => {
    it('lists every event as a link to its page, in the order it receives them', () => {
        const wrapper = mount(UpcomingEventsSection, {
            props: {
                events: [
                    event(),
                    event({ title: 'Torneo de cartas', url: '/eventos/torneo-de-cartas' }),
                ],
            },
        });

        const links = wrapper.findAll('li a');
        expect(links.map((link) => link.text())).toEqual(['Jornada de rol', 'Torneo de cartas']);
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/eventos/jornada-de-rol',
            '/eventos/torneo-de-cartas',
        ]);
    });

    it('draws the start in the display zone and keeps the instant in the time element', () => {
        const wrapper = mount(UpcomingEventsSection, { props: { events: [event()] } });

        const time = wrapper.get('time');
        expect(time.attributes('datetime')).toBe('2030-06-25T14:00:00+02:00');
        expect(time.text()).toContain('14:00');
    });

    it('draws the place when there is one and nothing when there is not', () => {
        const withPlace = mount(UpcomingEventsSection, { props: { events: [event()] } });
        const withoutPlace = mount(UpcomingEventsSection, {
            props: { events: [event({ location: null })] },
        });

        expect(withPlace.text()).toContain('Local de la asociación');
        expect(withoutPlace.text()).not.toContain('Local');
        expect(withoutPlace.findAll('li p')).toHaveLength(1);
    });

    it('leaves out the date when the server sent none or an unreadable one', () => {
        const wrapper = mount(UpcomingEventsSection, {
            props: {
                events: [
                    event({ startsAt: null }),
                    event({ title: 'Otro', url: '/eventos/otro', startsAt: 'not a date' }),
                ],
            },
        });

        expect(wrapper.find('time').exists()).toBe(false);
        expect(wrapper.findAll('li')).toHaveLength(2);
    });

    it('draws a title as text, never as HTML', () => {
        const wrapper = mount(UpcomingEventsSection, {
            props: { events: [event({ title: '<img src=x onerror=alert(1)> Rol' })] },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.get('li a').text()).toBe('<img src=x onerror=alert(1)> Rol');
    });
});
