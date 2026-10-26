export default {
    eventRegistrationsEligibility: {
        membersOnly: 'Members only',
        membersOnlyNotice: 'Only members of the association can register.',
    },
    eventRegistrationsCapacity: {
        confirmed: '{count} confirmed | {count} confirmed',
    },
    eventRegistrationsCancellation: {
        closed: {
            eventStarted:
                'The event has already started: you can no longer cancel your registration',
            deadlinePassed:
                'The cancellation deadline has passed: you can no longer cancel your registration',
        },
    },
    eventRegistrationsProfile: {
        tabLabel: 'My registrations',
        title: 'My registrations',
        loading: 'Loading your registrations…',
        upcoming: 'Upcoming events',
        past: 'Past events',
        unavailable: 'Unavailable events',
        empty: 'You have not registered for any event yet.',
        browseEvents: 'Browse events',
        unavailableEvent: 'Event not available',
        waitingListPosition: 'Position {position} on the waiting list',
        retry: 'Try again',
        reload: 'Reload the page',
        verificationRequired: 'To see your registrations, verify your email.',
        errors: {
            loadFailed: 'Your registrations could not be loaded.',
            sessionExpired: 'Your session has expired. Reload the page to sign in again.',
        },
        states: {
            pending: 'Pending',
            confirmed: 'Confirmed',
            waiting_list: 'Waiting list',
            cancelled: 'Cancelled',
            rejected: 'Rejected',
        },
    },
};
