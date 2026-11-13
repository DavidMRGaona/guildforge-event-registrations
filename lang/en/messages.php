<?php

declare(strict_types=1);

return [
    // Navigation
    'navigation' => 'Registrations',
    'navigation_group' => 'Content',

    // States
    'states' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'waiting_list' => 'Waiting list',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
    ],

    // Buttons and actions
    'register' => 'Register',
    'cancel_registration' => 'Cancel registration',
    'registration_closed' => 'Registration closed',
    'event_full' => 'Event full',
    'join_waiting_list' => 'Join waiting list',
    'your_position' => 'Your position: :position',

    // Fields
    'fields' => [
        'user' => 'User',
        'email' => 'Email',
        'state' => 'Status',
        'position' => 'Position',
        'confirmed_at' => 'Confirmed at',
        'created_at' => 'Registration date',
        'admin_notes' => 'Admin notes',
        'notes' => 'Notes',
        'form_data' => 'Additional data',
    ],

    // Actions
    'actions' => [
        'confirm' => 'Confirm',
        'reject' => 'Reject',
        'move_to_waiting' => 'Move to waiting list',
        'stats' => 'Statistics',
        'confirm_selected' => 'Confirm selected',
        'reject_selected' => 'Reject selected',
        'export' => 'Export',
    ],

    // Surveys (through the core's survey subject contract)
    'surveys' => [
        'source_label' => 'Event',
        'attributes' => [
            'members_only' => 'Members only',
        ],
    ],

    // Bulk actions
    'bulk' => [
        'confirm_description' => 'Are you sure you want to confirm the selected registrations?',
        'reject_description' => 'Are you sure you want to reject the selected registrations?',
        'confirmed_count' => '{1} 1 registration confirmed.|[2,*] :count registrations confirmed.',
        'rejected_count' => '{1} 1 registration rejected.|[2,*] :count registrations rejected.',
        'skipped_cannot_confirm' => '{1} 1 skipped that could not be confirmed (cancelled, rejected or already confirmed).|[2,*] :count skipped that could not be confirmed (cancelled, rejected or already confirmed).',
        'skipped_cannot_reject' => '{1} 1 skipped that could not be rejected (cancelled or already rejected).|[2,*] :count skipped that could not be rejected (cancelled or already rejected).',
        'skipped_full' => '{1} 1 skipped for lack of spots.|[2,*] :count skipped for lack of spots.',
    ],

    // Modal confirmations
    'modal' => [
        'confirm_description' => 'Are you sure you want to confirm this registration?',
        'reject_description' => 'Are you sure you want to reject this registration?',
        'move_to_waiting_description' => 'Are you sure you want to move this registration to the waiting list?',
    ],

    // Notifications
    'notifications' => [
        'confirmed' => 'Registration confirmed',
        'rejected' => 'Registration rejected',
        'moved_to_waiting' => 'Moved to waiting list',
        'bulk_confirmed' => 'Registrations confirmed',
        'none_to_confirm' => 'No registrations to confirm',
        'none_to_reject' => 'No registrations to reject',
        'bulk_rejected' => 'Registrations rejected',
        'event_full' => 'No free spots left: the event is full.',
    ],

    // Stats
    'stats' => [
        'confirmed' => 'Confirmed',
        'confirmed_description' => 'Total confirmed registrations',
        'waiting_list' => 'Waiting list',
        'waiting_list_description' => 'Total on waiting list',
        'pending' => 'Pending',
        'pending_description' => 'Registrations pending confirmation',
    ],

    // Config page
    'config' => [
        'title' => 'Configuration',
        'description' => 'Configure registration options for this event',
        'general' => 'General',
        'registration_enabled' => 'Registration enabled',
        'capacity' => 'Capacity',
        'max_participants' => 'Maximum participants',
        'max_participants_help' => 'Leave empty for unlimited',
        'waiting_list_enabled' => 'Waiting list enabled',
        'max_waiting_list' => 'Maximum on waiting list',
        'max_waiting_list_help' => 'Leave empty for unlimited',
        'dates' => 'Dates',
        'registration_opens_at' => 'Registration opens',
        'registration_closes_at' => 'Registration closes',
        'cancellation_deadline' => 'Cancellation deadline',
        'not_after_event_start' => 'Cannot be later than the event start (:date)',
        'options' => 'Options',
        'requires_confirmation' => 'Requires manual confirmation',
        'requires_confirmation_help' => 'Registrations remain pending until an admin confirms them',
        'members_only' => 'Members only',
        'members_only_help' => 'Only association members can register: users holding the association member role chosen in Site Settings → Authentication.',
        'notifications' => 'Notifications',
        'notification_email' => 'Notification email',
        'notification_email_help' => 'Receive a notification when someone registers for this event',
        'confirmation_message' => 'Confirmation message',
        'confirmation_message_help' => 'Added to the email sent to whoever gets a spot (confirmation, confirmed sign-up or promotion from the waiting list). Plain text, up to 500 characters.',
        'save' => 'Save configuration',
        'saved' => 'Configuration saved',
        'create' => 'Configure registrations',
        'edit_title' => 'Edit registration configuration',
        'empty_title' => 'No configuration',
        'empty_description' => 'Configure registration settings for this event',
    ],

    // Export
    'export' => [
        'deleted_user' => 'Deleted user',
    ],

    // Errors
    'errors' => [
        'registration_closed' => 'Registration is closed',
        'event_full' => 'Event is full',
        'already_registered' => 'You are already registered for this event',
        'registration_rejected' => 'Your registration for this event was rejected. If you think this is a mistake, contact the organisers.',
        'not_found' => 'Registration not found',
        'cannot_cancel' => 'Cannot cancel registration',
        'unauthenticated' => 'You must log in',
        'members_only' => 'Only association members can register for this event',
        'event_started' => 'The event has already started',
        'cannot_cancel_started' => 'You cannot cancel your registration: the event has already started',
        'event_not_found' => 'Event not found',
    ],

    // Success
    'success' => [
        'registered' => 'You have been registered successfully',
        'cancelled' => 'Registration cancelled',
    ],

    // Validation
    'validation' => [
        'form_data_array' => 'Form data must be valid',
        'notes_max' => 'Notes cannot exceed 500 characters',
    ],

    // Permissions
    'permissions' => [
        'view_any' => 'View registrations list',
        'view' => 'View registration detail',
        'create' => 'Create registrations',
        'update' => 'Edit registrations',
        'delete' => 'Delete registrations',
        'manage_config' => 'Manage registration configuration',
        'export' => 'Export registrations',
        'manage_surveys' => 'Manage event surveys',
    ],

    // Mail preferences
    'mail' => [
        'section' => 'Event registrations',
        'subject_event' => 'Event',
        'registrations' => [
            'label' => 'My registrations',
            'description' => 'Sign-up, confirmation, rejection, cancellation and a free place on the waiting list.',
            'reason' => 'They confirm something you did or change your plans.',
        ],
        'reminders' => [
            'label' => 'Event reminders',
            'description' => 'A notice a few hours before an event you have a place in.',
        ],
        'management' => [
            'label' => 'New registrations',
            'description' => 'A notice on the site when someone signs up for an event, for whoever manages registrations.',
        ],
    ],

    // Notices in the bell
    'notices' => [
        'registered' => 'Your registration for ":event" is pending review',
        'registered_confirmed' => 'Your registration for ":event" is confirmed',
        'registered_waiting_list' => 'You are on the waiting list for ":event"',
        'confirmed' => 'Your registration for ":event" has been confirmed',
        'promoted' => 'A spot opened up for you in ":event"',
        'cancelled' => 'Your registration for ":event" has been cancelled',
        'rejected' => 'Your registration for ":event" was not accepted',
        'reminder' => 'Reminder: ":event" starts soon',
        'manager_registered' => ':user signed up for ":event"',
        'manager_registered_grouped' => '{0} :user signed up for ":event"|{1} :user and 1 more signed up for ":event"|[2,*] :user and :count more signed up for ":event"',
        'manager_state' => 'State: :state',
        'manager_state_position' => 'State: :state (position :position)',
    ],

    // Emails
    'emails' => [
        'greeting' => 'Hello :name,',
        'event_details' => 'Event details:',
        'date' => 'Date',
        'location' => 'Location',
        'view_event' => 'View event',
        'organiser_message' => 'Message from the organiser',
        'view_events' => 'View events',

        // Confirmation email
        'confirmed_subject' => 'Registration confirmed: :event',
        'confirmed_body' => 'Your registration for ":event" has been confirmed.',

        // Waiting list email
        'waiting_list_subject' => 'Waiting list: :event',
        'your_position' => 'Your position on the waiting list is: **:position**',
        'waiting_list_info' => 'We will notify you if a spot becomes available.',

        // Promotion email
        'promoted_subject' => 'Spot available!: :event',
        'promoted_body' => 'Good news! A spot has become available for ":event".',
        'promoted_info' => 'Your registration has been automatically confirmed.',

        // Cancellation email
        'cancelled_subject' => 'Registration cancelled: :event',
        'cancelled_body' => 'Your registration for ":event" has been cancelled.',
        'cancelled_info' => 'If you wish to register again, you can do so from the event page.',

        // Registration email
        'registered_subject' => 'Registration received: :event',
        'registered_body' => 'Your registration for ":event" has been received.',
        'registered_pending' => 'Your registration is pending confirmation by an administrator.',
        'registered_confirmed_body' => 'You have registered for ":event" and your seat is confirmed.',
        'registered_waiting_list_body' => 'You have joined the waiting list for ":event".',

        // Rejection email
        'rejected_subject' => 'Registration rejected: :event',
        'rejected_body' => 'We regret to inform you that your registration for ":event" has been rejected.',

        // Admin notification email
        'admin_greeting' => 'Hello,',
        'admin_new_registration_subject' => 'New registration: :event',
        'admin_new_registration_body' => ':user (:email) has registered for ":event".',
        'admin_registration_state' => 'Registration state: **:state**',
        'admin_waiting_list_position' => 'Waiting list position: **:position**',
        'admin_requires_confirmation' => 'This registration requires manual confirmation.',
        'admin_view_registrations' => 'View registrations',

        // Reminder email
        'reminder_subject' => 'Reminder: :event, :date',
        'reminder_body' => 'This is a reminder of your registration for ":event", which starts on :date.',
        'reminder_cancel' => 'If you can no longer come, cancel your registration from the event page to free the seat.',
    ],

    // Settings
    'settings' => [
        'general' => 'General',
        'default_registration_enabled' => 'Registration enabled by default',
        'default_registration_enabled_help' => 'If enabled, registration will be enabled by default on new events',
        'default_waiting_list' => 'Waiting list enabled by default',
        'default_requires_confirmation' => 'Requires confirmation by default',
        'default_max_participants' => 'Default maximum participants',
        'default_max_waiting_list' => 'Default maximum on waiting list',
        'emails' => 'Emails',
        'send_confirmation_email' => 'Send confirmation email',
        'send_promotion_email' => 'Send promotion email',
        'send_cancellation_email' => 'Send cancellation email',
        'send_reminder_email' => 'Send a reminder before the event',
        'send_reminder_email_help' => 'An email to every confirmed registration the given hours before the start. Off by default.',
        'reminder_hours_before' => 'Reminder lead time (hours)',
        'send_registration_email' => 'Send registration email',
    ],
    'proposal_request' => [
        'label' => 'Registrations',
        'question' => 'Do you want registrations?',
        'capacity' => 'Capacity',
        'capacity_help' => 'Maximum number of people. Leave empty for no limit.',
        'waiting_list' => 'Waiting list',
        'already_configured' => 'The event already has registrations configured.',
        'without_capacity' => 'Registrations with no capacity limit',
        'with_capacity' => 'Registrations with a capacity of :capacity',
        'with_capacity_and_waiting_list' => 'Registrations with a capacity of :capacity and a waiting list',
    ],
];
