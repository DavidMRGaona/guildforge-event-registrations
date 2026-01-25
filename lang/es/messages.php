<?php

declare(strict_types=1);

return [
    // Navigation
    'navigation' => 'Inscripciones',
    'navigation_group' => 'Contenido',

    // States
    'states' => [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'waiting_list' => 'Lista de espera',
        'cancelled' => 'Cancelado',
        'rejected' => 'Rechazado',
    ],

    // Buttons and actions
    'register' => 'Inscribirse',
    'cancel_registration' => 'Cancelar inscripción',
    'registration_closed' => 'Inscripciones cerradas',
    'event_full' => 'Evento completo',
    'join_waiting_list' => 'Unirse a lista de espera',
    'your_position' => 'Tu posición: :position',

    // Fields
    'fields' => [
        'user' => 'Usuario',
        'email' => 'Email',
        'state' => 'Estado',
        'position' => 'Posición',
        'confirmed_at' => 'Confirmado el',
        'created_at' => 'Fecha de inscripción',
        'admin_notes' => 'Notas del administrador',
        'notes' => 'Notas',
        'form_data' => 'Datos adicionales',
    ],

    // Actions
    'actions' => [
        'confirm' => 'Confirmar',
        'reject' => 'Rechazar',
        'move_to_waiting' => 'Mover a lista de espera',
        'stats' => 'Estadísticas',
        'confirm_selected' => 'Confirmar seleccionados',
        'reject_selected' => 'Rechazar seleccionados',
        'export' => 'Exportar',
    ],

    // Bulk actions
    'bulk' => [
        'confirm_description' => '¿Estás seguro de que quieres confirmar las inscripciones seleccionadas?',
        'reject_description' => '¿Estás seguro de que quieres rechazar las inscripciones seleccionadas?',
    ],

    // Modal confirmations
    'modal' => [
        'confirm_description' => '¿Estás seguro de que quieres confirmar esta inscripción?',
        'reject_description' => '¿Estás seguro de que quieres rechazar esta inscripción?',
        'move_to_waiting_description' => '¿Estás seguro de que quieres mover esta inscripción a la lista de espera?',
    ],

    // Notifications
    'notifications' => [
        'confirmed' => 'Inscripción confirmada',
        'rejected' => 'Inscripción rechazada',
        'moved_to_waiting' => 'Movido a lista de espera',
        'bulk_confirmed' => 'Inscripciones confirmadas',
        'none_to_confirm' => 'No hay inscripciones para confirmar',
        'none_to_reject' => 'No hay inscripciones para rechazar',
    ],

    // Stats
    'stats' => [
        'confirmed' => 'Confirmados',
        'confirmed_description' => 'Total de inscripciones confirmadas',
        'waiting_list' => 'Lista de espera',
        'waiting_list_description' => 'Total en lista de espera',
        'pending' => 'Pendientes',
        'pending_description' => 'Inscripciones pendientes de confirmar',
    ],

    // Config page
    'config' => [
        'title' => 'Configuración',
        'description' => 'Configura las opciones de inscripción para este evento',
        'general' => 'General',
        'registration_enabled' => 'Inscripciones habilitadas',
        'capacity' => 'Capacidad',
        'max_participants' => 'Máximo de participantes',
        'max_participants_help' => 'Dejar vacío para sin límite',
        'waiting_list_enabled' => 'Lista de espera habilitada',
        'max_waiting_list' => 'Máximo en lista de espera',
        'max_waiting_list_help' => 'Dejar vacío para sin límite',
        'dates' => 'Fechas',
        'registration_opens_at' => 'Apertura de inscripciones',
        'registration_closes_at' => 'Cierre de inscripciones',
        'cancellation_deadline' => 'Fecha límite de cancelación',
        'options' => 'Opciones',
        'requires_confirmation' => 'Requiere confirmación manual',
        'requires_confirmation_help' => 'Las inscripciones quedan pendientes hasta que un administrador las confirme',
        'requires_payment' => 'Requiere pago',
        'requires_payment_help' => 'El usuario debe realizar el pago para confirmar la inscripción',
        'members_only' => 'Solo socios',
        'members_only_help' => 'Solo los socios pueden inscribirse',
        'messages' => 'Mensajes',
        'confirmation_message' => 'Mensaje de confirmación',
        'confirmation_message_help' => 'Mensaje personalizado que se incluirá en el email de confirmación',
        'notifications' => 'Notificaciones',
        'notification_email' => 'Email de notificación',
        'notification_email_help' => 'Recibe una notificación cuando alguien se inscribe al evento',
        'save' => 'Guardar configuración',
        'saved' => 'Configuración guardada',
        'create' => 'Configurar inscripciones',
        'edit_title' => 'Editar configuración de inscripciones',
        'empty_title' => 'Sin configuración',
        'empty_description' => 'Configura los ajustes de inscripción para este evento',
    ],

    // Errors
    'errors' => [
        'registration_closed' => 'Las inscripciones están cerradas',
        'event_full' => 'El evento está completo',
        'already_registered' => 'Ya estás inscrito en este evento',
        'not_found' => 'Inscripción no encontrada',
        'cannot_cancel' => 'No se puede cancelar la inscripción',
        'unauthenticated' => 'Debes iniciar sesión',
    ],

    // Success
    'success' => [
        'registered' => 'Te has inscrito correctamente',
        'cancelled' => 'Inscripción cancelada',
    ],

    // Validation
    'validation' => [
        'form_data_array' => 'Los datos del formulario deben ser válidos',
        'notes_max' => 'Las notas no pueden superar los 500 caracteres',
    ],

    // Permissions
    'permissions' => [
        'view_any' => 'Ver listado de inscripciones',
        'view' => 'Ver detalle de inscripción',
        'create' => 'Crear inscripciones',
        'update' => 'Editar inscripciones',
        'delete' => 'Eliminar inscripciones',
        'manage_config' => 'Gestionar configuración de inscripciones',
        'export' => 'Exportar inscripciones',
    ],

    // Emails
    'emails' => [
        'greeting' => 'Hola :name,',
        'salutation' => 'Saludos',
        'event_details' => 'Detalles del evento:',
        'date' => 'Fecha',
        'location' => 'Ubicación',
        'view_event' => 'Ver evento',
        'view_events' => 'Ver eventos',

        // Confirmation email
        'confirmed_subject' => 'Inscripción confirmada: :event',
        'confirmed_body' => 'Tu inscripción al evento ":event" ha sido confirmada.',

        // Waiting list email
        'waiting_list_subject' => 'Lista de espera: :event',
        'waiting_list_body' => 'Te has añadido a la lista de espera del evento ":event".',
        'your_position' => 'Tu posición en la lista de espera es: **:position**',
        'waiting_list_info' => 'Te notificaremos si se libera una plaza.',

        // Promotion email
        'promoted_subject' => '¡Plaza disponible!: :event',
        'promoted_body' => '¡Buenas noticias! Se ha liberado una plaza en el evento ":event".',
        'promoted_info' => 'Tu inscripción ha sido confirmada automáticamente.',

        // Cancellation email
        'cancelled_subject' => 'Inscripción cancelada: :event',
        'cancelled_body' => 'Tu inscripción al evento ":event" ha sido cancelada.',
        'cancelled_info' => 'Si deseas volver a inscribirte, puedes hacerlo desde la página del evento.',

        // Registration email
        'registered_subject' => 'Inscripción recibida: :event',
        'registered_body' => 'Tu inscripción al evento ":event" ha sido recibida.',
        'registered_pending' => 'Tu inscripción está pendiente de confirmación por parte de un administrador.',
        'registered_confirmed' => 'Tu inscripción ha sido confirmada automáticamente.',

        // Rejection email
        'rejected_subject' => 'Inscripción rechazada: :event',
        'rejected_body' => 'Lamentamos informarte que tu inscripción al evento ":event" ha sido rechazada.',

        // Admin notification email
        'admin_greeting' => 'Hola,',
        'admin_new_registration_subject' => 'Nueva inscripción: :event',
        'admin_new_registration_body' => ':user (:email) se ha inscrito al evento ":event".',
        'admin_registration_state' => 'Estado de la inscripción: **:state**',
        'admin_waiting_list_position' => 'Posición en lista de espera: **:position**',
        'admin_requires_confirmation' => 'Esta inscripción requiere confirmación manual.',
        'admin_view_registrations' => 'Ver inscripciones',
    ],

    // Settings
    'settings' => [
        'general' => 'General',
        'default_registration_enabled' => 'Inscripciones habilitadas por defecto',
        'default_registration_enabled_help' => 'Si está activado, las inscripciones estarán habilitadas por defecto en los nuevos eventos',
        'default_waiting_list' => 'Lista de espera habilitada por defecto',
        'default_requires_confirmation' => 'Requiere confirmación por defecto',
        'default_max_participants' => 'Máximo de participantes por defecto',
        'default_max_waiting_list' => 'Máximo en lista de espera por defecto',
        'emails' => 'Emails',
        'send_confirmation_email' => 'Enviar email de confirmación',
        'send_waiting_list_email' => 'Enviar email de lista de espera',
        'send_promotion_email' => 'Enviar email de promoción',
        'send_cancellation_email' => 'Enviar email de cancelación',
        'send_registration_email' => 'Enviar email de inscripción',
        'send_rejection_email' => 'Enviar email de rechazo',
    ],
];
