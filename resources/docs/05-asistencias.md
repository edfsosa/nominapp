# Asistencias

El módulo de Asistencias registra las entradas y salidas de los empleados y calcula automáticamente horas trabajadas, horas extra, tardanzas y ausencias.

## Modos de marcación

Ir a **Asistencias → Modos de Marcación** para ver los enlaces y códigos QR de cada modo.

### Terminal compartida (kiosco)
Un dispositivo fijo en la sucursal (tablet o PC). Todos los empleados marcan desde ese mismo equipo. No requiere que el empleado tenga cuenta en el sistema. El reconocimiento facial identifica automáticamente quién está marcando.

### Modo móvil
El empleado usa su propio dispositivo (celular o tablet). Puede marcar desde cualquier lugar. Útil para empleados remotos o en campo.

---

## Reconocimiento facial

El sistema usa reconocimiento facial para identificar al empleado sin usuario ni contraseña. El proceso tiene dos etapas: **registro** y **aprobación**.

### Proceso completo de registro facial

**Paso 1 — Generar el enlace de captura:**

1. Ir al perfil del empleado en **Empleados**
2. Ir a la pestaña **Registro Facial**
3. Clic en **Nuevo registro** (o **Generar enlace**)
4. El sistema genera un enlace con token temporal

**Paso 2 — El empleado captura su rostro:**

1. El empleado abre el enlace generado en su dispositivo (celular o PC con cámara)
2. Sigue el proceso de captura facial (el sistema toma varias muestras)
3. Al finalizar, el registro queda en estado **Pendiente de aprobación**

**Paso 3 — El administrador aprueba:**

1. Ir a **Asistencias → Registro Facial**
2. Buscar el registro en estado **Pendiente de aprobación**
3. Revisar la imagen capturada y el score de calidad
4. Clic en **Aprobar** (o **Rechazar** si la calidad es insuficiente)
5. Al aprobar, el descriptor facial queda activo y el empleado puede marcar

### Estados del registro facial

| Estado | Descripción |
|--------|-------------|
| **Pendiente de Captura** | Enlace generado, el empleado aún no capturó |
| **Pendiente de Aprobación** | Rostro capturado, esperando revisión del admin |
| **Aprobado** | Activo, el empleado puede marcar |
| **Rechazado** | Captura rechazada, debe generarse un nuevo enlace |
| **Expirado** | El token venció antes de completar la captura |

### Calidad del registro

| Nivel | Score | Color |
|-------|-------|-------|
| Alta | ≥ 0.85 | Verde |
| Media | ≥ 0.70 | Amarillo |
| Baja | < 0.70 | Rojo |

Se recomienda aprobar solo registros con calidad media o alta.

### Expiración

Los registros faciales tienen una vigencia configurable (en **Configuración General**). Al expirar, el empleado debe renovar su registro repitiendo el proceso desde el Paso 1.

---

## Tipos de eventos de marcación

Cada vez que un empleado interactúa con el sistema de asistencia, se registra un evento:

| Tipo | Descripción |
|------|-------------|
| **Entrada jornada** | Inicio del turno |
| **Inicio descanso** | Pausa (ej: almuerzo) |
| **Fin descanso** | Regreso de pausa |
| **Salida jornada** | Fin del turno |

### Orígenes

| Origen | Descripción |
|--------|-------------|
| **Terminal (kiosco)** | Desde dispositivo compartido de la sucursal |
| **Móvil** | Desde el celular del empleado |
| **Manual (admin)** | Ingresado manualmente por el administrador |

---

## Marcaciones manuales

Si un empleado no puede marcar (problema técnico, visitante, etc.):

1. Ir a **Asistencias → Marcaciones**
2. Clic en **Nueva marcación**
3. Seleccionar empleado, tipo de evento, fecha y hora
4. Guardar

---

## Resumen diario (AttendanceDay)

Por cada empleado y día, el sistema calcula automáticamente (durante la noche o de forma manual):

- **Horas totales** marcadas y **horas netas** (sin descansos)
- **Horas esperadas** según el horario asignado
- **Horas extra diurnas y nocturnas**
- **Minutos de tardanza** y **minutos de salida anticipada**
- **Anomalías** detectadas

### Estados del día

| Estado | Descripción |
|--------|-------------|
| **Presente** | El empleado asistió |
| **Ausente** | No hubo marcaciones |
| **De permiso** | Tiene permiso/licencia registrada |
| **Feriado** | El día es feriado |
| **Fin de semana** | Día no laboral |

Para ver el detalle de un día: **Asistencias → Asistencias**, clic sobre el registro.

---

## Reporte de asistencias

Ir a **Asistencias → Reportes de Asistencia** para ver resúmenes por período.

**Filtros disponibles:** período (desde/hasta), empresa, sucursal, departamento.

**Pestaña Asistencias:** días presentes, ausentes, horas trabajadas, horas extra y tardanzas por empleado.

**Pestaña Ausencias:** total de ausencias, justificadas, injustificadas y descuentos por empleado.

Ambas pestañas permiten exportar a Excel (resumen y detalle).
