---
id: event-registrations.panel.overview
title: 'Inscripciones a eventos: cómo funcionan'
summary: Qué añade el módulo de inscripciones a los eventos, por qué estados pasa una inscripción, dónde está cada cosa en el panel y qué permisos hay.
category: activities
audience: panel
order: 150
featured: true
keywords: [inscripciones, inscripción, inscritos, asistentes, evento, eventos, plazas, aforo, lista de espera, confirmación manual, solo socios, estados, pendiente, confirmado, rechazado, cancelado, permisos, cómo funciona, introducción, empezar]
related: [event-registrations.panel.configure, event-registrations.panel.manage-registrations, event-registrations.panel.settings, core.panel.manage-events, core.panel.roles-and-permissions]
conditions:
  permissions:
    any: ['event-registrations:registrations.view_any', 'event-registrations:registrations.manage_config']
---
El módulo de inscripciones deja que la gente de {{site_name}} se apunte a los eventos desde la web, con un número de plazas, lista de espera y, si quieres, revisión de cada inscripción. Esta guía explica las piezas. Las demás guías de inscripciones entran en el detalle de cada tarea.

## Cómo se ve en la web

Un evento con las inscripciones habilitadas muestra en su página un botón para inscribirse, encima de la descripción, con el número de confirmados. Para inscribirse hace falta una cuenta. Cada persona ve sus inscripciones en su perfil, en la pestaña **Mis inscripciones**.

## Estados de una inscripción

| Estado | Qué significa | Cómo llega ahí |
|---|---|---|
| **Pendiente** | Espera a que alguien la revise. No ocupa plaza. | Al inscribirse, si el evento **Requiere confirmación manual**. |
| **Confirmado** | Tiene plaza. | Al inscribirse, si hay sitio y el evento confirma solo; con **Confirmar** en el panel; o al subir de la lista de espera. |
| **Lista de espera** | Está en la cola, con su **Posición**. | Al inscribirse con las plazas llenas, o con **Mover a lista de espera**. |
| **Cancelado** | La persona se dio de baja. | Desde la web, hasta la fecha límite de cancelación o el inicio del evento. |
| **Rechazado** | La organización no la acepta. | Con **Rechazar** en el panel. |

Solo las inscripciones **Confirmado** cuentan para el **Máximo de participantes**.

## La lista de espera

Cuando queda una plaza libre, porque alguien cancela, se rechaza, se borra o se mueve a la lista de espera, el primero de la lista pasa solo a **Confirmado**, aunque el evento requiera confirmación manual. Esto deja de pasar al empezar el evento.

## Dónde está cada cosa

- En la ficha de cada evento (**Contenido** → **Eventos**), la pestaña **Configuración** abre y ajusta sus inscripciones, y la pestaña **Inscripciones** lista a los inscritos.
- En el escritorio, un widget suma las inscripciones de todos los eventos: **Confirmados**, **Lista de espera** y **Pendientes**.
- En **Administración** → **Módulos**, la tarjeta del módulo tiene **Configuración**: los valores por defecto y los correos.

Si tu web tiene encuestas, el formulario del evento trae además el campo **Encuesta al terminar**, con su propia ayuda.

## Permisos

Las pestañas **Configuración** e **Inscripciones** están en la ficha del evento: las ve quien puede editar eventos. Dentro, algunos botones piden un permiso del módulo:

| Permiso | Qué deja hacer |
|---|---|
| **Ver listado de inscripciones** | Ver el widget del escritorio. |
| **Editar inscripciones** | Usar **Editar** en una inscripción. |
| **Eliminar inscripciones** | Usar **Borrar** en una inscripción. |
| **Exportar inscripciones** | Usar **Exportar** en la pestaña **Inscripciones**. |

**Ver listado de inscripciones** y **Gestionar configuración de inscripciones** deciden además qué guías de inscripciones ves en esta ayuda. Los permisos se dan a cada rol en **Roles**, en la pestaña del módulo, y ninguno se concede solo.

[[action:filament.admin.resources.events.index|Ver los eventos]]
