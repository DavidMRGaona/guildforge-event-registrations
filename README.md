# Event registrations

Sistema de inscripciones para eventos con lista de espera, confirmación manual, notificaciones por email y gestión desde el panel de administración.

## Características

- **Inscripciones a eventos**: Los usuarios pueden inscribirse a eventos desde el frontend
- **Lista de espera**: Gestión automática con promoción cuando se liberan plazas
- **Confirmación manual**: Opción para que los administradores aprueben inscripciones
- **Límites configurables**: Máximo de participantes y plazas en lista de espera
- **Fechas de apertura/cierre**: Control de cuándo se pueden realizar inscripciones
- **Solo socios**: Restringir inscripciones a usuarios con membresía activa
- **Notificaciones por email**: Confirmación, lista de espera, promoción, cancelación y rechazo
- **Notificación al administrador**: Email cuando alguien se inscribe a un evento
- **Panel de administración**: Gestión completa desde Filament

## Requisitos

- PHP >= 8.2
- GuildForge core

## Instalación

1. Copiar el módulo a `src/modules/event-registrations/`

2. Descubrir y habilitar el módulo:

```bash
php artisan module:discover
php artisan module:enable event-registrations
```

3. Ejecutar las migraciones:

```bash
php artisan migrate
```

## Configuración

### Configuración por evento

Cada evento tiene su propia configuración de inscripciones accesible desde el panel de administración:

| Campo | Descripción |
|-------|-------------|
| Inscripciones habilitadas | Activa/desactiva las inscripciones |
| Máximo de participantes | Límite de plazas (vacío = sin límite) |
| Lista de espera habilitada | Permite lista de espera cuando el evento está lleno |
| Máximo en lista de espera | Límite de plazas en lista de espera |
| Apertura de inscripciones | Fecha desde la que se aceptan inscripciones |
| Cierre de inscripciones | Fecha hasta la que se aceptan inscripciones |
| Fecha límite de cancelación | Fecha hasta la que se puede cancelar |
| Requiere confirmación manual | Las inscripciones quedan pendientes hasta aprobación |
| Solo socios | Restringe inscripciones a usuarios con membresía |
| Email de notificación | Recibe aviso cuando alguien se inscribe |

### Configuración global

Valores por defecto para nuevos eventos en `config/settings.php`:

```php
return [
    'default_registration_enabled' => false,
    'default_waiting_list_enabled' => true,
    'default_requires_confirmation' => false,
    'default_max_participants' => null,
    'default_max_waiting_list' => null,
    'send_registration_email' => true,
    'send_confirmation_email' => true,
    'send_waiting_list_email' => true,
    'send_promotion_email' => true,
    'send_cancellation_email' => true,
    'send_rejection_email' => true,
];
```

## Estados de inscripción

| Estado | Descripción |
|--------|-------------|
| `pending` | Pendiente de confirmación por administrador |
| `confirmed` | Inscripción confirmada |
| `waiting_list` | En lista de espera |
| `cancelled` | Cancelada por el usuario |
| `rejected` | Rechazada por administrador |

## Arquitectura

```
src/modules/event-registrations/
├── config/
│   ├── module.php          # Configuración del módulo
│   └── settings.php        # Valores por defecto
├── database/
│   └── migrations/         # Migraciones de base de datos
├── lang/
│   ├── en/messages.php     # Traducciones en inglés
│   └── es/messages.php     # Traducciones en español
├── resources/
│   ├── js/
│   │   ├── components/     # Componentes Vue
│   │   └── types/          # Tipos TypeScript
│   └── views/
│       └── filament/       # Vistas Blade para Filament
├── routes/
│   ├── api.php             # Rutas API
│   └── web.php             # Rutas web
├── src/
│   ├── Application/
│   │   ├── DTOs/           # Data Transfer Objects
│   │   └── Services/       # Interfaces de servicios
│   ├── Domain/
│   │   ├── Entities/       # Entidades de dominio
│   │   ├── Enums/          # Enumeraciones
│   │   ├── Events/         # Eventos de dominio
│   │   ├── Exceptions/     # Excepciones de dominio
│   │   ├── Repositories/   # Interfaces de repositorios
│   │   └── ValueObjects/   # Objetos de valor
│   ├── Filament/
│   │   ├── Pages/          # Páginas de Filament
│   │   ├── RelationManagers/  # Gestores de relaciones
│   │   └── Widgets/        # Widgets del dashboard
│   ├── Http/
│   │   ├── Controllers/    # Controladores
│   │   └── Requests/       # Form Requests
│   ├── Infrastructure/
│   │   ├── Persistence/    # Repositorios Eloquent
│   │   └── Services/       # Implementaciones de servicios
│   ├── Listeners/          # Listeners de eventos
│   ├── Notifications/      # Notificaciones por email
│   └── Policies/           # Políticas de autorización
├── tests/
│   ├── Integration/        # Tests de integración
│   └── Unit/               # Tests unitarios
├── module.json             # Manifiesto del módulo
└── phpunit.xml             # Configuración de tests
```

## API

### Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/events/{event}/register` | Inscribirse a un evento |
| `DELETE` | `/api/events/{event}/register` | Cancelar inscripción |
| `GET` | `/api/events/{event}/registration` | Obtener estado de inscripción |
| `GET` | `/api/user/registrations` | Listar inscripciones del usuario |

### Ejemplo de inscripción

```javascript
const response = await fetch(`/api/events/${eventId}/register`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({
        notes: 'Comentarios opcionales',
    }),
});
```

## Componentes Vue

### RegistrationButton

Botón de inscripción que se inyecta en la página de detalle del evento.

```vue
<RegistrationButton :event="event" />
```

### RegistrationStatus

Muestra el estado actual de la inscripción del usuario.

```vue
<RegistrationStatus :registration="registration" />
```

### UserRegistrations

Lista las inscripciones del usuario actual.

```vue
<UserRegistrations />
```

## Eventos de dominio

| Evento | Descripción |
|--------|-------------|
| `UserRegisteredToEvent` | Usuario se inscribió a un evento |
| `UserUnregisteredFromEvent` | Usuario canceló su inscripción |
| `RegistrationConfirmed` | Inscripción confirmada por administrador |
| `RegistrationRejected` | Inscripción rechazada por administrador |
| `WaitingListPromoted` | Usuario promocionado de lista de espera |

## Permisos

| Permiso | Descripción |
|---------|-------------|
| `registrations.view_any` | Ver listado de inscripciones |
| `registrations.view` | Ver detalle de inscripción |
| `registrations.create` | Crear inscripciones |
| `registrations.update` | Editar inscripciones |
| `registrations.delete` | Eliminar inscripciones |
| `registrations.manage_config` | Gestionar configuración |
| `registrations.export` | Exportar inscripciones |

## Tests

Ejecutar los tests del módulo:

```bash
# Desde el directorio del módulo
cd src/modules/event-registrations
../../../vendor/bin/phpunit

# O desde el directorio raíz
php artisan test --filter=EventRegistration
```

## Licencia

Este módulo es parte de GuildForge y está bajo la misma licencia del proyecto principal.
