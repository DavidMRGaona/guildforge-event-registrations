import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PastEventsSection from '../../resources/js/components/PastEventsSection.vue';
import type { ProfileEvent } from '../../resources/js/types/registration';

function event(overrides: Partial<ProfileEvent> = {}): ProfileEvent {
    return {
        title: 'Torneo de cartas',
        url: '/eventos/torneo-de-cartas',
        startsAt: '2029-11-08T18:30:00+01:00',
        location: 'Local de la asociación',
        ...overrides,
    };
}

describe('PastEventsSection', () => {
    it('lists every event as a link to its page, in the order it receives them', () => {
        const wrapper = mount(PastEventsSection, {
            props: {
                events: [
                    event(),
                    event({ title: 'Jornada de rol', url: '/eventos/jornada-de-rol' }),
                ],
            },
        });

        const links = wrapper.findAll('li a');
        expect(links.map((link) => link.text())).toEqual(['Torneo de cartas', 'Jornada de rol']);
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/eventos/torneo-de-cartas',
            '/eventos/jornada-de-rol',
        ]);
    });

    it('draws the day with its year, since a past event may be from another one', () => {
        const wrapper = mount(PastEventsSection, { props: { events: [event()] } });

        const time = wrapper.get('time');
        expect(time.attributes('datetime')).toBe('2029-11-08T18:30:00+01:00');
        expect(time.text()).toContain('2029');
        expect(time.text()).toContain('8');
        expect(time.text()).not.toContain('18:30');
    });

    it('draws the place when there is one and nothing when there is not', () => {
        const withPlace = mount(PastEventsSection, { props: { events: [event()] } });
        const withoutPlace = mount(PastEventsSection, {
            props: { events: [event({ location: null })] },
        });

        expect(withPlace.text()).toContain('Local de la asociación');
        expect(withoutPlace.text()).not.toContain('Local');
        expect(withoutPlace.findAll('li p')).toHaveLength(1);
    });

    it('leaves out the date when the server sent none or an unreadable one', () => {
        const wrapper = mount(PastEventsSection, {
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
        const wrapper = mount(PastEventsSection, {
            props: { events: [event({ title: '<img src=x onerror=alert(1)> Rol' })] },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.get('li a').text()).toBe('<img src=x onerror=alert(1)> Rol');
    });
});
