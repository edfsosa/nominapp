# Horarios de Trabajo

Los horarios definen los turnos laborales de los empleados. Se usan para calcular horas trabajadas, horas extra, tardanzas y ausencias.

## Conceptos clave

- **Horario:** plantilla reutilizable con un tipo de jornada y la configuración de cada día de la semana
- **Día del horario:** define si ese día es laboral, y cuáles son las horas de entrada y salida esperadas
- **Descanso:** pausa dentro del turno (ej: almuerzo 12:00–13:00). Se descuenta de las horas netas del día
- **Asignación:** vínculo entre un empleado y un horario, con fecha de inicio y fin opcional

## Tipos de jornada

| Valor | Descripción |
|-------|-------------|
| **Diurno** | Turno entre 06:00 y 20:00 |
| **Nocturno** | Turno entre 20:00 y 06:00 |
| **Mixto** | Combinación de ambos |

El tipo de jornada determina las horas mensuales de referencia y los multiplicadores de horas extra que se aplican en nómina.

## Crear un horario

1. Ir a **Organización → Horarios**
2. Clic en **Nuevo horario**
3. Ingresar el nombre (ej: "Administrativo 08:00–17:00") y seleccionar el tipo de jornada
4. Guardar

### Configurar los días del horario

Dentro del horario creado, la pestaña **Días** muestra los 7 días de la semana. Para cada día:

- **Activar** el día si es laboral (los días desactivados se tratan como descanso semanal)
- Ingresar la **hora de entrada** y la **hora de salida** esperadas

> Las horas netas del día se calculan automáticamente restando la duración de los descansos configurados.

### Configurar descansos

Desde la pestaña **Descansos** (o dentro de cada día), puede agregar pausas:

1. Clic en **Nuevo descanso**
2. Ingresar nombre (ej: "Almuerzo"), hora de inicio y fin
3. Guardar

El sistema descuenta automáticamente los minutos de descanso al calcular las horas trabajadas del día.

## Asignar un horario a un empleado

1. Abrir el perfil del empleado en **Empleados**
2. Ir a la pestaña **Horarios**
3. Clic en **Asignar horario**
4. Seleccionar el horario y la **fecha de inicio** de la asignación
5. Guardar

> Un empleado puede tener historial de asignaciones. El sistema usa automáticamente la asignación vigente en la fecha de cada marcación para calcular la asistencia.

## Cambiar el horario de un empleado

No elimine la asignación anterior. En cambio, cree una **nueva asignación** con el nuevo horario y su fecha de inicio. El historial anterior queda preservado.

## Notas importantes

- Si un empleado no tiene horario asignado, sus marcaciones se registran pero **no se calculan** horas trabajadas ni ausencias automáticamente.
- Un mismo horario puede asignarse a múltiples empleados.
