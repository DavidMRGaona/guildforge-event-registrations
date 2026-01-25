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

    // Bulk actions
    'bulk' => [
        'confirm_description' => 'Are you sure you want to confirm the selected registrations?',
        'reject_description' => 'Are you sure you want to reject the selected registrations?',
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
        'options' => 'Options',
        'requires_confirmation' => 'Requires manual confirmation',
        'requires_confirmation_help' => 'Registrations remain pending until an admin confirms them',
        'requires_payment' => 'Requires payment',
        'requires_payment_help' => 'User must pay to confirm registration',
        'members_only' => 'Members only',
        'members_only_help' => 'Only members can register',
        'messages' => 'Messages',
        'confirmation_message' => 'Confirmation message',
        'confirmation_message_help' => 'Custom message to include in the confirmation email',
        'notifications' => 'Notifications',
        'notification_email' => 'Notification email',
        'notification_email_help' => 'Receive a notification when someone registers for this event',
        'save' => 'Save configuration',
        'saved' => 'Configuration saved',
        'create' => 'Configure registrations',
        'edit_title' => 'Edit registration configuration',
        'empty_title' => 'No configuration',
        'empty_description' => 'Configure registration settings for this event',
    ],

    // Errors
    'errors' => [
        'registration_closed' => 'Registration is closed',
        'event_full' => 'Event is full',
        'already_registered' => 'You are already registered for this event',
        'not_found' => 'Registration not found',
        'cannot_cancel' => 'Cannot cancel registration',
        'unauthenticated' => 'You must log in',
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
    ],

    // Emails
    'emails' => [
        'greeting' => 'Hello :name,',
        'salutation' => 'Best regards,',
        'event_details' => 'Event details:',
        'date' => 'Date',
        'location' => 'Location',
        'view_event' => 'View event',
        'view_events' => 'View events',

        // Confirmation email
        'confirmed_subject' => 'Registration confirmed: :event',
        'confirmed_body' => 'Your registration for ":event" has been confirmed.',

        // Waiting list email
        'waiting_list_subject' => 'Waiting list: :event',
        'waiting_list_body' => 'You have been added to the waiting list for ":event".',
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
        'registered_confirmed' => 'Your registration has been automatically confirmed.',

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
        'send_waiting_list_email' => 'Send waiting list email',
        'send_promotion_email' => 'Send promotion email',
        'send_cancellation_email' => 'Send cancellation email',
        'send_registration_email' => 'Send registration email',
        'send_rejection_email' => 'Send rejection email',
    ],
];
