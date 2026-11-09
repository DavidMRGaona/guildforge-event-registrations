---
id: event-registrations.panel.settings
title: 'Ajustes de inscripciones: valores por defecto y correos'
summary: Qué decide cada campo de la configuración del módulo de inscripciones, los valores con que empieza cada evento y los correos que se envían.
category: activities
audience: panel
order: 180
keywords: [ajustes, configuración, configurar inscripciones, valores por defecto, por defecto, plazas por defecto, lista de espera, confirmación, correos, emails, avisos, notificaciones, recordatorio, recordar evento, horas antes, desactivar correos]
related: [event-registrations.panel.overview, event-registrations.panel.configure, core.panel.modules, core.panel.mail]
conditions:
  permissions:
    all: ['modules.configure']
---
La configuración del módulo decide con qué valores empieza cada evento y qué correos reciben quienes se inscriben.

## Abrir la configuración

1. En el menú lateral, abre **Administración** y elige **Módulos**.
2. En la tarjeta **Inscripciones a eventos**, pulsa **Configuración**.
3. Cambia lo que necesites y pulsa **Guardar configuración**.

## General: los valores por defecto

Sirven para los eventos cuyas inscripciones nunca se han guardado en su pestaña **Configuración**. La pestaña empieza con estos valores y, mientras nadie la guarde, la web también los usa. Al guardarla, el evento se queda con los suyos y ya no le afecta cambiar estos.

- **Inscripciones habilitadas por defecto**: los eventos empiezan con **Inscripciones habilitadas** activado.
- **Lista de espera habilitada por defecto**.
- **Requiere confirmación por defecto**: cada inscripción queda **Pendiente** hasta que alguien la confirma.
- **Máximo de participantes por defecto** y **Máximo en lista de espera por defecto**. Vacíos, sin límite.

> [!WARNING]
> Con **Inscripciones habilitadas por defecto** activado, todos los eventos publicados que no tienen su configuración guardada muestran el botón **Inscribirse** en la web, también los que ya existían.

## Emails

Cada interruptor activa o apaga un correo a la persona inscrita. Nunca se envían correos de eventos que ya han terminado.

Estos interruptores valen para todo el sitio. Además, cada persona elige en su perfil, pestaña **Correos**, qué correos opcionales recibe:

- **Mis inscripciones** (inscripción, confirmación, rechazo, cancelación y plaza libre) es obligatoria: le llega siempre, porque confirma algo que ha hecho o le cambia los planes.
- **Recordatorios de eventos** es opcional: cada persona puede desactivarlo, o dejar de recibirlo mientras tenga sus correos en pausa.

> [!IMPORTANT]
> Un interruptor apagado aquí manda: ese correo no sale aunque la persona lo tenga activado. La elección de cada persona solo sirve para recibir menos, nunca más.

| Interruptor | Cuándo sale el correo |
|---|---|
| **Enviar email de inscripción** | Al inscribirse: «Inscripción confirmada», «Inscripción recibida» si queda pendiente o «Lista de espera» con su posición. |
| **Enviar email de confirmación** | Cuando confirmas una inscripción en el panel. Si el de inscripción está apagado, también al inscribirse con plaza. |
| **Enviar email de promoción** | Cuando sube de la lista de espera a una plaza: «¡Plaza disponible en (evento)!». Si está apagado, recibe el de confirmación. |
| **Enviar email de cancelación** | Cuando cancela su inscripción desde la web. |
| **Enviar recordatorio antes del evento** | Antes del inicio, a cada inscripción **Confirmado**. Viene desactivado. |

**Antelación del recordatorio (horas)**, de 1 a 168, dice cuántas horas antes del inicio sale el recordatorio. Se revisa cada hora, y cada inscripción recibe un solo recordatorio.

Para que salga cualquier correo, el correo de la web tiene que estar configurado.

[[action:filament.admin.pages.modules-page|Ver los módulos]]
