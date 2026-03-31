# Asistencias

El módulo de asistencias registra las entradas y salidas de los empleados y calcula automáticamente horas trabajadas, horas extra y ausencias.

## Modos de marcación

El sistema ofrece dos modalidades:

### Terminal compartida (kiosk)
Un dispositivo fijo en la sucursal (tablet o PC). Todos los empleados marcan desde ese mismo dispositivo. No requiere que el empleado tenga cuenta en el sistema.

### Modo mobile
El empleado usa su propio dispositivo (celular o tablet). Puede marcar desde cualquier lugar con conexión a internet. Útil para empleados remotos o en campo.

Para obtener los enlaces y códigos QR de cada modo, ir a **Asistencias → Modos de Marcación**.

## Reconocimiento facial

El sistema usa reconocimiento facial para identificar al empleado en la marcación, sin necesidad de ingresar usuario ni contraseña.

### Registrar el rostro de un empleado

1. Ir al perfil del empleado en **Empleados**
2. Pestaña **Registro Facial**
3. Clic en **Registrar rostro** (genera un enlace de auto-registro)
4. El empleado abre el enlace en su dispositivo y sigue el proceso de captura
5. Una vez completado, el registro queda activo

> Los registros faciales expiran después de un período configurable en **Configuración General**. Al expirar, el empleado debe renovar su registro.

## Marcaciones manuales

Si un empleado no puede marcar por facial (problema técnico, visitante, etc.), se puede registrar manualmente:

1. Ir a **Asistencias → Marcaciones**
2. Clic en **Nueva marcación**
3. Seleccionar empleado, tipo (entrada/salida) y fecha/hora
4. Guardar

## Resumen diario de asistencia

Cada día el sistema calcula automáticamente (a las 23:00 o manualmente):

- Total de horas trabajadas
- Horas extra diurnas y nocturnas
- Minutos de tardanza
- Ausencias y anomalías

Para ver el resumen: **Asistencias → Asistencias**, filtrar por empleado y fecha.

## Reporte de asistencias

Ir a **Asistencias → Reportes de Asistencia** para ver resúmenes por período y exportar a Excel.

Filtros disponibles: período, empresa, sucursal, departamento.

Dos vistas disponibles:
- **Asistencias:** días presentes, ausentes, horas trabajadas y extras por empleado
- **Ausencias:** resumen de inasistencias justificadas, injustificadas y descuentos

## Consultar asistencia de un día

Desde **Asistencias → Asistencias**, clic sobre un registro para ver el detalle del día: todas las marcaciones (entrada/salida) con horarios exactos.
