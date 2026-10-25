# Inscripciones a eventos

Inscripciones a los eventos de GuildForge: aforo, lista de espera con promoción automática, confirmación manual, eventos solo para socios, emails al usuario y al organizador, exportación a CSV y gestión desde el panel.

## Características

- **Inscripción desde la ficha del evento**: el botón se inyecta solo en la página del evento.
- **Mis inscripciones en el perfil**: una pestaña del perfil lista las inscripciones del usuario, separadas en próximos eventos, eventos pasados y «Eventos no disponibles» (las de eventos que no están publicados), con la fecha, el lugar, el estado y la posición en la lista de espera.
- **Aforo y lista de espera**: máximo de participantes y de plazas en espera. Cuando se libera una plaza confirmada (el inscrito cancela, o desde el panel se rechaza, se pasa a lista de espera, se cambia de estado o se elimina), las primeras personas de la lista pasan a confirmadas hasta llenar las plazas libres. Quien se pasa a lista de espera desde el panel va al final y no recupera la plaza en ese momento, aunque nadie más espere. Desde el inicio del evento no se promociona a nadie.
- **Confirmación manual**: las inscripciones quedan pendientes hasta que alguien las confirma desde el panel.
- **Fechas**: apertura y cierre de inscripciones y fecha límite para cancelar. El inicio del evento cierra inscripciones, cancelaciones y lista de espera aunque las fechas configuradas sean posteriores, y desde ese momento nadie pasa de la lista de espera a confirmado. Las acciones del panel (confirmar, rechazar, mover a lista de espera, editar, eliminar) no tienen ese límite.
- **Solo socios**: si se activa en el evento, solo pueden inscribirse (o entrar en la lista de espera) los socios de la asociación, es decir, los usuarios con el rol de socio del core. La ficha del evento no ofrece la inscripción a quien no lo es (el botón muestra "Solo para socios") y el servidor la rechaza igualmente con "Solo los socios pueden inscribirse en este evento" (HTTP 403).
- **Verificación de email**: si el core exige verificar el email y la cuenta aún no lo ha hecho, la ficha del evento muestra el botón desactivado con el motivo («Para inscribirte, verifica tu email.» o «Para cancelar tu inscripción, verifica tu email.») y un enlace a la página de verificación, y la pestaña «Mis inscripciones» explica que hay que verificar el email para ver las inscripciones. Ver [Verificación de email](#verificación-de-email).
- **Emails**: inscripción recibida (con la posición si queda en lista de espera), confirmación desde el panel, plaza liberada y cancelación; aviso opcional a un email del organizador. No se envían emails de eventos ya terminados.
- **Exportación**: descarga en CSV de las inscripciones de un evento.
- **Widget del dashboard**: totales de inscripciones confirmadas, en lista de espera y pendientes.
- **Zonas horarias**: las fechas se guardan en la zona de almacenamiento del sitio (UTC cuando el sitio lo usa) y todo lo que ve una persona (emails, CSV, panel) se muestra en la zona de visualización del sitio (por defecto Europe/Madrid).

## Requisitos

- GuildForge core `^3.22` (almacenamiento de fechas en UTC y zona horaria de visualización)
- PHP >= 8.5, Laravel >= 13, Filament 5

El core rechaza el módulo si no se cumplen (`module.json` → `requires`).

## Instalación

1. Descarga `event-registrations-X.Y.Z.zip` de la última release de `DavidMRGaona/guildforge-event-registrations` en GitHub.
2. En el panel, activa el modo mantenimiento (**Configuración del sitio** → pestaña **Mantenimiento**).
3. En **Módulos**, pulsa **Instalar / actualizar** y sube el ZIP.
4. Pulsa **Habilitar** en el módulo: la primera vez ejecuta sus migraciones.
5. Desactiva el modo mantenimiento.

Las versiones nuevas se aplican desde **Actualizaciones de módulos** (también con el modo mantenimiento activo) o con `php artisan module:update event-registrations`.

### En desarrollo

Con el repositorio clonado en `src/modules/event-registrations`:

```bash
php artisan module:discover
php artisan module:enable event-registrations
```

Los componentes Vue se compilan con las herramientas del core, desde `src/`:

```bash
docker exec guildforge_node npm run build:module -- event-registrations
```

En las releases los assets se compilan en el workflow y van dentro del ZIP.

## Uso en el panel

Al editar un evento aparecen dos pestañas:

- **Configuración** (siempre visible): opciones de inscripción del evento.
- **Inscripciones** (visible si las inscripciones están habilitadas o el evento ya tiene inscripciones): listado con filtro por estado y las acciones confirmar, rechazar, mover a lista de espera, editar y eliminar; en bloque, confirmar, rechazar y eliminar.

La acción **Exportar** de la pestaña **Inscripciones** descarga `inscripciones-<slug>.csv` con nombre, email, estado, posición, fecha de confirmación y fecha de inscripción, también de los usuarios desactivados. El archivo va en UTF-8 con BOM para que las hojas de cálculo respeten los acentos y usa `;` como separador (el que espera Excel con la configuración regional española). Los valores que una hoja de cálculo ejecutaría como fórmula (los que empiezan por `=`, `+`, `-`, `@`, tabulador o retorno de carro) se exportan precedidos de un apóstrofo. Solo la ven quienes tienen el permiso `registrations.export`.

### Configuración por evento

| Campo | Descripción |
|-------|-------------|
| Inscripciones habilitadas | Activa o desactiva las inscripciones del evento |
| Máximo de participantes | Límite de plazas (vacío = sin límite) |
| Lista de espera habilitada | Admite lista de espera cuando el evento está completo |
| Máximo en lista de espera | Límite de la lista de espera (vacío o 0 = sin límite) |
| Apertura de inscripciones | Desde cuándo se aceptan inscripciones |
| Cierre de inscripciones | Hasta cuándo se aceptan inscripciones; no puede ser posterior al inicio del evento, que las cierra siempre |
| Fecha límite de cancelación | Hasta cuándo puede cancelar el usuario; no puede ser posterior al inicio del evento, y sin fecha se puede cancelar hasta el inicio |
| Requiere confirmación manual | Las inscripciones quedan pendientes hasta que se confirman |
| Solo socios | Solo pueden inscribirse los socios (rol de socio del core) |
| Email de notificación | Recibe un aviso con cada inscripción nueva |

### Quién es socio

Un socio es un usuario con el rol de socio del core, el que se elige en **Configuración del sitio** → **Autenticación** (`socio` por defecto). El rol `member`, que tiene cualquier usuario registrado, no basta. El módulo se lo pregunta al core en cada comprobación, así que un cambio en ese ajuste se aplica sin tocar el módulo. Los administradores no tienen excepción: sin el rol de socio tampoco pueden inscribirse en un evento solo para socios.

La comprobación se hace en dos sitios con la misma regla: al inscribirse (el servidor responde 403) y en el estado del evento (`meets_members_only`), que la ficha usa para desactivar el botón. Los visitantes sin sesión siguen viendo el botón, que los lleva a iniciar sesión. Quien ya tenía una inscripción activa la conserva y puede cancelarla aunque haya perdido el rol de socio.

### Ajustes del módulo

Se editan en el panel: **Módulos** → Inscripciones a eventos → **Configurar**. Los valores por defecto se aplican a los eventos que todavía no tienen configuración propia.

| Ajuste | Descripción |
|--------|-------------|
| Inscripciones habilitadas por defecto | Estado inicial de los eventos sin configuración |
| Lista de espera habilitada por defecto | Ídem para la lista de espera |
| Requiere confirmación por defecto | Ídem para la confirmación manual |
| Máximo de participantes por defecto | Aforo inicial (vacío = sin límite) |
| Máximo en lista de espera por defecto | Límite inicial de la lista de espera (vacío o 0 = sin límite) |
| Enviar email de inscripción | Email al inscribirse (confirmada, pendiente o en lista de espera) |
| Enviar email de confirmación | Email de inscripción confirmada (ver [Emails](#emails)) |
| Enviar email de promoción | Email cuando se libera una plaza y alguien pasa de la lista de espera a confirmado |
| Enviar email de cancelación | Email cuando el usuario cancela su inscripción o sale de la lista de espera |
| Enviar recordatorio antes del evento | Recordatorio a las inscripciones confirmadas (desactivado por defecto) |
| Antelación del recordatorio (horas) | Horas antes del inicio en que sale el recordatorio (24 por defecto) |

Los valores guardados desde el panel se conservan en la base de datos del core, así que sobreviven a las actualizaciones. `config/settings.php` solo trae los valores por defecto de cada versión.

### Emails

| Email | Destinatario | Cuándo |
|-------|--------------|--------|
| Inscripción recibida | Usuario | Al inscribirse; el asunto y el texto dicen si queda confirmada, pendiente o en lista de espera (con su posición) |
| Inscripción confirmada | Usuario | Cuando se confirma desde el panel; también en las confirmaciones automáticas (al inscribirse o por promoción) si el email de inscripción o el de promoción está desactivado |
| Plaza disponible | Usuario | Cuando pasa de la lista de espera a confirmado |
| Inscripción cancelada | Usuario | Cuando cancela su inscripción o sale de la lista de espera |
| Recordatorio | Usuario | Una vez, las horas configuradas antes del inicio, si está confirmada y el recordatorio está activado (`event-registrations:send-reminders`, cada hora) |
| Nueva inscripción | Email de notificación del evento | Con cada inscripción, si el evento tiene ese email configurado |

No se envían emails de eventos ya terminados.

### Widget del dashboard

`RegistrationStatsWidget` muestra los totales de inscripciones confirmadas, en lista de espera y pendientes de todos los eventos. Lo ven los usuarios con el permiso `registrations.view_any`, y se puede ocultar u ordenar desde la configuración del dashboard (**Panel de control**).

## Estados de inscripción

| Estado | Etiqueta | Descripción |
|--------|----------|-------------|
| `pending` | Pendiente | Pendiente de confirmación |
| `confirmed` | Confirmado | Inscripción confirmada |
| `waiting_list` | Lista de espera | En lista de espera, con posición |
| `cancelled` | Cancelado | Cancelada por el usuario |
| `rejected` | Rechazado | Rechazada desde el panel |

## Frontend

`RegistrationButton` se inyecta en la ficha del evento (slot `event-detail-actions`) e incluye `RegistrationStatus` y `RegistrationForm`. Si la configuración del evento tiene campos personalizados (`custom_fields`), el botón pide esos datos antes de inscribir y los envía como `form_data`; el panel todavía no permite definirlos.

Con una inscripción activa (pendiente, confirmada o en lista de espera), el botón ofrece cancelarla mientras `can_cancel` es `true`, aunque las inscripciones ya estén cerradas; cuando ya no se puede cancelar, el botón no se muestra y `RegistrationStatus` sigue indicando el estado de la inscripción.

`UserRegistrations` es la pestaña «Mis inscripciones» del perfil (slot `profile-sections`). Pide `GET /mis-inscripciones` al abrirse; si la sesión ha caducado, ofrece recargar la página para volver a iniciar sesión.

### Verificación de email

Con *Requerir verificación de email* activo en el core, el servidor rechaza las rutas que requieren sesión a las cuentas que deben verificar su email (en una petición JSON, un 403 con `error: "email_verification_required"` y la cabecera `X-Email-Verification-Required: 1`; en una visita normal, una redirección a la página de verificación). Las cuentas que no deben verificar (verificadas, creadas antes de que se empezara a exigir o con el ajuste apagado) y los visitantes sin sesión no notan ningún cambio.

Los componentes leen ese estado con `useVerificationGate()` del core (`auth.user.mustVerifyEmail`) para no ofrecer lo que el servidor va a rechazar:

- `RegistrationButton` no pide la inscripción del usuario y muestra el botón desactivado con el motivo visible, asociado al botón por `aria-describedby`, y el enlace «Ver cómo →» a la página de verificación. El motivo depende de lo que haría el botón: inscribirse (o entrar en la lista de espera) o cancelar la inscripción. Si el botón ya estaba desactivado por otro motivo (inscripciones cerradas, evento completo, solo socios), no añade el de la verificación. El estado público del evento (`/estado`) se sigue pidiendo, porque no requiere sesión.
- Si el servidor rechaza una inscripción o una cancelación por la verificación aunque la página no lo sabía (por ejemplo, porque el estado cambió en otra pestaña), el botón lleva a la página de verificación en vez de mostrar un error. Si es la lectura de la inscripción la que se rechaza, el botón queda desactivado con el motivo.
- `UserRegistrations` no pide `/mis-inscripciones` y muestra «Para ver tus inscripciones, verifica tu email.» con el enlace a la página de verificación; lo mismo si el servidor responde con el 403 de verificación.

### Rutas

Son rutas web (sesión y CSRF) que responden JSON; el módulo no registra rutas `/api`. Sin sesión, las que la requieren responden 401 a las peticiones JSON; una visita normal del navegador va a la página de inicio de sesión. A una cuenta que debe verificar su email le responden como se explica en [Verificación de email](#verificación-de-email).

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/eventos/{eventId}/inscripcion/estado` | Estado público de las inscripciones del evento; `is_open` indica si se admiten inscripciones ahora y `can_cancel` si se admiten cancelaciones ahora (las dos pasan a `false` al empezar el evento); `meets_members_only` indica si el visitante actual cumple el requisito de solo socios (siempre `true` si el evento está abierto a todos, `false` sin sesión en un evento solo para socios) |
| `GET` | `/eventos/{eventId}/inscripcion` | Inscripción del usuario (requiere sesión) |
| `POST` | `/eventos/{eventId}/inscripcion` | Inscribirse (requiere sesión; `notes`, `form_data`) |
| `DELETE` | `/eventos/{eventId}/inscripcion` | Cancelar la inscripción (requiere sesión) |
| `GET` | `/mis-inscripciones` | Inscripciones del usuario, de la más reciente a la más antigua (requiere sesión) |

Las cuatro rutas `/eventos/{eventId}/inscripcion…` responden 404 con «Evento no encontrado» si el evento no existe o no está publicado, también a quien ya tenía una inscripción en él (las tres que exigen sesión responden antes 401 a un visitante sin sesión). Las acciones del panel no tienen esa restricción.

Las inscripciones que recibe el usuario nunca incluyen las notas internas del panel (`admin_notes`). Cada elemento de `/mis-inscripciones` lleva además `event` con `id`, `title`, `slug`, `start_date`, `end_date`, `location` y `url` (la ficha del evento), o `null` si el evento no está publicado.

`POST` responde 201 si crea la inscripción, 403 si el evento es solo para socios y el usuario no lo es, y 422 si las inscripciones están cerradas (con «El evento ya ha comenzado» cuando la causa es el inicio del evento), el evento está completo o el usuario ya está inscrito.

`DELETE` responde 200 si cancela la inscripción (también la plaza en lista de espera), 404 si el usuario no está inscrito y 422 si ha pasado la fecha límite de cancelación o el evento ya ha comenzado.

```javascript
const response = await fetch(`/eventos/${eventId}/inscripcion`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify({ notes: 'Comentarios opcionales' }),
});
```

## Eventos de dominio

| Evento | Cuándo se dispara |
|--------|-------------------|
| `UserRegisteredToEvent` | Un usuario se inscribe (también al entrar en la lista de espera) |
| `UserUnregisteredFromEvent` | Un usuario cancela su inscripción |
| `RegistrationConfirmed` | Una inscripción queda confirmada (al inscribirse, desde el panel o por promoción) |
| `WaitingListPromoted` | Alguien pasa de la lista de espera a confirmado |
| `RegistrationRejected` | Declarado, pero no se dispara todavía |

## Permisos

Los administradores tienen todos los permisos.

| Permiso | Descripción | Rol por defecto |
|---------|-------------|-----------------|
| `registrations.view_any` | Ver el listado de inscripciones y el widget | editor |
| `registrations.view` | Ver el detalle de una inscripción | editor |
| `registrations.create` | Crear inscripciones | editor |
| `registrations.update` | Editar inscripciones | editor |
| `registrations.delete` | Eliminar inscripciones | ninguno |
| `registrations.manage_config` | Gestionar la configuración de inscripciones | editor |
| `registrations.export` | Exportar las inscripciones de un evento a CSV | editor |

## Arquitectura

```
src/modules/event-registrations/
├── config/
│   ├── module.php          # Datos del módulo
│   └── settings.php        # Valores por defecto de los ajustes del panel
├── database/migrations/    # Inscripciones y configuración por evento
├── lang/                   # Traducciones (es, en)
├── resources/
│   ├── css/                # Estilos del módulo
│   ├── js/components/      # Componentes Vue
│   ├── js/locales/         # Textos de la ficha del evento y del perfil (es, en)
│   ├── js/types/           # Tipos TypeScript
│   └── views/filament/     # Vistas Blade del panel
├── routes/web.php          # Rutas JSON
├── src/
│   ├── Application/        # DTOs e interfaces de servicios
│   ├── Domain/             # Entidades, enums, eventos, excepciones, repositorios, reloj (Clock)
│   ├── Filament/           # Relation managers, páginas y widget del dashboard
│   ├── Http/               # Controlador y form requests
│   ├── Infrastructure/     # Persistencia Eloquent y servicios
│   ├── Listeners/          # Emails y promoción de la lista de espera
│   ├── Notifications/      # Emails
│   └── Policies/           # Autorización
├── tests/
│   ├── Feature/
│   ├── Integration/
│   ├── Support/            # Utilidades compartidas (FixedClock)
│   ├── Unit/
│   └── bootstrap.php       # Carga el namespace de los tests
├── module.json             # Manifiesto del módulo
└── phpunit.xml             # Configuración de tests
```

## Tests

Desde `src/` (o dentro del contenedor `guildforge_app`):

```bash
vendor/bin/phpunit -c modules/event-registrations/phpunit.xml
vendor/bin/phpunit -c modules/event-registrations/phpunit.xml --testsuite=Unit
```

`php artisan test` no ejecuta los tests de los módulos.

Los servicios leen la hora de `Domain\Services\Clock` (`SystemClock` en producción, que sigue a `travelTo()`), nunca de `new DateTimeImmutable`. Los tests que sitúan el inicio o los plazos a minutos de ahora fijan el reloj en una hora neutra y crean sus eventos con fechas relativas a esa hora: la fecha por defecto de la factoría sigue al reloj real, que `travelTo()` no mueve. Las dos noches de cambio de hora se prueban en `RegistrationAcrossDaylightSavingTest` con `Tests\Support\FixedClock`, porque el reloj de pruebas de Carbon no puede representar la primera pasada de la hora que se repite en octubre.

Las fechas se guardan como hora peninsular sin desplazamiento, así que una fecha guardada dentro de la hora repetida de octubre (de 02:00 a 02:59) se lee como su segunda pasada (CET). Desaparecerá cuando el core guarde los instantes en UTC (DavidMRGaona/GuildForge#107).

## Publicar una versión

1. Sube `version` en `module.json`.
2. Crea y empuja la etiqueta `vX.Y.Z` (debe coincidir con `version`).
3. El workflow `release.yml` ejecuta los tests contra el core (`main`, o la variable `GUILDFORGE_HOST_REF`), compila los assets y publica `event-registrations-X.Y.Z.zip` con su `.sha256` en la release.

## Licencia

Este módulo es parte de GuildForge y está bajo la misma licencia del proyecto principal.
