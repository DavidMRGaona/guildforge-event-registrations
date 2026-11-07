---
id: event-registrations.panel.manage-registrations
title: Gestionar los inscritos de un evento
summary: Consulta quién se ha inscrito en un evento, confirma o rechaza inscripciones, mueve gente a la lista de espera y exporta la lista.
category: activities
audience: panel
order: 170
keywords: [inscritos, inscripciones, asistentes, participantes, lista de inscritos, confirmar, aprobar, rechazar, pendiente, lista de espera, mover a lista de espera, promover, dar plaza, borrar inscripción, notas del administrador, exportar, csv, excel, hoja de cálculo, descargar lista]
related: [event-registrations.panel.overview, event-registrations.panel.configure, event-registrations.panel.settings]
conditions:
  permissions:
    all: ['events.update', 'event-registrations:registrations.view_any']
---
Los inscritos de un evento están en la pestaña **Inscripciones** de su ficha. La pestaña sale cuando el evento tiene las inscripciones habilitadas o ya tiene alguna inscripción.

## Ver los inscritos

1. En el menú lateral, abre **Contenido** y elige **Eventos**.
2. Entra en el evento.
3. Abre la pestaña **Inscripciones**.

Arriba, un resumen cuenta las inscripciones de cada estado, por ejemplo «12 Confirmados · 3 Lista de espera · 2 Pendientes». Cada fila muestra el **Usuario**, el **Email**, el **Estado**, la **Posición** en la lista de espera, la fecha en que se confirmó (**Confirmado el**) y la **Fecha de inscripción**. Puedes buscar por nombre o email y filtrar por **Estado**.

## Las acciones de cada fila

| Acción | Cuándo sale | Qué hace | Correo |
|---|---|---|---|
| **Confirmar** | Inscripción **Pendiente** o en **Lista de espera** | Le da plaza si queda alguna libre; con el evento lleno se niega: «No quedan plazas libres: el evento está completo.». Si venía de la lista de espera, quienes iban detrás suben un puesto. | «Inscripción confirmada: (evento)» |
| **Rechazar** | Inscripción **Pendiente**, **Confirmado** o en **Lista de espera** | La rechaza. Si tenía plaza, pasa al primero de la lista de espera. | «Inscripción rechazada: (evento)» |
| **Mover a lista de espera** | Inscripción **Confirmado** | La pone al final de la cola. Su plaza pasa a quien ya esperaba. | Ninguno |
| **Editar** | Siempre, con el permiso **Editar inscripciones** | Cambia el **Estado** y las **Notas del administrador**, que solo se ven en el panel. | Ninguno |
| **Borrar** | Siempre, con el permiso **Eliminar inscripciones** | Quita la inscripción. Si tenía plaza, pasa al primero de la lista de espera. | Ninguno |

**Confirmar**, **Rechazar**, **Mover a lista de espera** y **Borrar** piden confirmación antes de hacer nada. Quien sube de la lista de espera a una plaza recibe «¡Plaza disponible en (evento)!». Los correos salen si están activados en la configuración del módulo; el de rechazo no tiene ajuste y sale siempre, también con **Rechazar seleccionados**.

> [!TIP]
> Para confirmar a alguien con su correo, usa **Confirmar**. Cambiar el estado con **Editar** no envía ningún correo ni mira las plazas libres.

## Varias a la vez

Marca las casillas de las filas, abre el menú de acciones en bloque y elige:

- **Confirmar seleccionados**: confirma las seleccionadas que están pendientes o en la lista de espera, hasta llenar las plazas libres: primero la lista de espera, por su puesto, y después las pendientes, de la más antigua a la más reciente. Se salta las canceladas, las rechazadas, las ya confirmadas y las que no caben, que se quedan como estaban.
- **Rechazar seleccionados**: rechaza las seleccionadas que están pendientes, confirmadas o en la lista de espera. Se salta las canceladas y las ya rechazadas.
- **Eliminar**: borra las seleccionadas.

El aviso final dice cuántas ha cambiado y cuántas se ha saltado, y por qué. Al terminar, las plazas que hayan quedado libres pasan a quien espera en la lista.

## Exportar la lista

Pulsa **Exportar**, arriba de la tabla, para descargar `inscripciones-(evento).csv`. Trae todas las inscripciones del evento, también las de cuentas desactivadas, con las columnas **Usuario**, **Email**, **Estado**, **Posición**, **Confirmado el** y **Fecha de inscripción**. Se abre con cualquier hoja de cálculo. El botón solo sale con el permiso **Exportar inscripciones**.

## La lista de espera

Mientras el evento no ha empezado, cada plaza que se libera pasa sola al primero de la lista, que queda **Confirmado** aunque el evento requiera confirmación manual. Desde el inicio del evento, nadie sube solo: usa **Confirmar** si quieres dar una plaza.

[[action:filament.admin.resources.events.index|Ver los eventos]]
