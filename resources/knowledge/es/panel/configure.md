---
id: event-registrations.panel.configure
title: Abrir las inscripciones de un evento
summary: Habilita las inscripciones de un evento y decide sus plazas, su lista de espera, sus fechas, si se revisan a mano, si son solo para socios y el mensaje del correo de confirmación.
category: activities
audience: panel
order: 160
keywords: [habilitar inscripciones, abrir inscripciones, activar inscripciones, configurar inscripciones, plazas, aforo, máximo de participantes, lista de espera, fecha de apertura, fecha de cierre, fecha límite de cancelación, confirmación manual, revisar inscripciones, solo socios, cerrar inscripciones, mensaje de confirmación, correo de confirmación, mensaje propio]
related: [event-registrations.panel.overview, event-registrations.panel.manage-registrations, event-registrations.panel.settings, core.panel.manage-events, core.panel.association-role]
conditions:
  permissions:
    all: ['events.update', 'event-registrations:registrations.manage_config']
---
Las inscripciones de cada evento se configuran en su ficha, en la pestaña **Configuración**. El evento tiene que existir: si lo estás creando, guárdalo primero.

## Abrir la configuración

1. En el menú lateral, abre **Contenido** y elige **Eventos**.
2. Entra en el evento.
3. Abre la pestaña **Configuración**.
4. Activa **Inscripciones habilitadas**. Al activarlo aparecen las demás secciones.
5. Rellena lo que necesites y pulsa **Guardar configuración**. Sale el aviso «Configuración guardada».

> [!IMPORTANT]
> Esta pestaña se guarda con su propio botón, **Guardar configuración**. **Guardar cambios**, el botón de la pestaña **Detalles**, no guarda las inscripciones.

En cuanto lo guardas, la página del evento en la web muestra el botón **Inscribirse**, siempre que el evento esté publicado. También aparece la pestaña **Inscripciones** en la ficha. Si no la ves aún, recarga la página.

Un evento que nunca se ha guardado aquí usa los valores por defecto de la configuración del módulo, que explica la guía de ajustes.

## Capacidad

- **Máximo de participantes**: cuántas personas pueden tener plaza. Déjalo vacío para no poner límite.
- **Lista de espera habilitada**: con las plazas llenas, la gente puede ponerse en la cola. Sin ella, el botón dice **Evento completo**.
- **Máximo en lista de espera**: cuántas personas caben en la cola. Déjalo vacío para no poner límite.

## Fechas

- **Apertura de inscripciones**: desde cuándo se puede uno inscribir. Vacío, desde ya.
- **Cierre de inscripciones**: hasta cuándo. Tiene que ser posterior a la apertura.
- **Fecha límite de cancelación**: hasta cuándo puede cancelar quien está inscrito. Vacío, hasta el inicio del evento.

Ni el cierre ni la fecha límite pueden ser posteriores al inicio del evento: el inicio cierra las inscripciones y las cancelaciones pase lo que pase. Desde ese momento la lista de espera tampoco corre.

## Opciones

- **Requiere confirmación manual**: cada inscripción queda **Pendiente** hasta que la confirmas en la pestaña **Inscripciones**. Las pendientes no ocupan plaza.
- **Solo socios**: solo se pueden inscribir quienes tienen el rol de {{association_role}}, el que se elige en **Configuración del sitio** → **Autenticación**. Los demás ven el botón **Solo para socios**, sin poder pulsarlo.

## Mensaje de confirmación

En **Notificaciones** puedes escribir un **Mensaje de confirmación**, por ejemplo «Trae tu ejército pintado» o «Te esperamos en la sala 2». Se añade, con el título «Mensaje de quien organiza», al correo que recibe quien consigue plaza: cuando confirmas una inscripción pendiente, cuando alguien se inscribe y queda confirmado de inmediato, y cuando sube desde la lista de espera. Quien queda en lista de espera o pendiente no lo recibe todavía.

- Es solo texto, con un máximo de 500 caracteres. Los enlaces, los asteriscos o el HTML salen tal cual, sin funcionar, y los saltos de línea se convierten en espacios.
- Vacío, el correo sale como siempre.
- Los correos que ya se han enviado no cambian. Si editas el mensaje, el nuevo se usa desde la siguiente plaza que se confirme.
- Los correos de confirmación se pueden desactivar en los ajustes del módulo; en ese caso el mensaje no se envía.

## Cerrar o quitar las inscripciones

Para cerrar las inscripciones antes de tiempo, pon un **Cierre de inscripciones** o desactiva **Inscripciones habilitadas** y guarda. Desactivarlas quita el botón de la web, pero no borra a nadie: los inscritos siguen en la pestaña **Inscripciones**, y las plazas, las fechas y las opciones se conservan para cuando vuelvas a activarlas.

## Si algo no sale bien

- **«No puede ser posterior al inicio del evento (…)»**: el cierre o la fecha límite de cancelación caen después del inicio. Adelántalos o déjalos vacíos.
- **La web no muestra el botón**: comprueba que **Inscripciones habilitadas** está activado y guardado, y que el evento está publicado.
- **Nadie puede inscribirse aunque el botón sale**: mira la **Apertura de inscripciones** y el **Cierre de inscripciones**, y si el evento ya ha empezado.

[[action:filament.admin.resources.events.index|Ver los eventos]]
