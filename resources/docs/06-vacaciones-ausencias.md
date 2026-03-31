# Vacaciones, Permisos y Ausencias

## Vacaciones

El sistema gestiona el saldo anual de días de vacaciones de cada empleado. El saldo se calcula según la antigüedad del empleado.

### Días a los que tiene derecho (según antigüedad)

| Antigüedad | Días hábiles |
|------------|--------------|
| Menos de 1 año | Sin derecho |
| 1 a 5 años | 12 días |
| 6 a 10 años | 18 días |
| Más de 10 años | 30 días |

### Saldo de vacaciones

El saldo se actualiza automáticamente al aprobar o rechazar solicitudes. Para consultarlo:

1. Abrir el perfil del empleado en **Empleados**
2. Pestaña **Vacaciones** — muestra días disponibles, usados y pendientes de aprobación

### Registrar una solicitud de vacaciones

1. Ir a **Empleados → Vacaciones**
2. Clic en **Nueva solicitud**
3. Seleccionar:
   - **Empleado**
   - **Tipo:** Remunerada o No Remunerada
   - **Fecha de inicio** y **fecha de fin**
   - **Fecha de reintegro** (opcional)
   - **Motivo** (opcional)
4. El sistema calcula automáticamente los días hábiles del período
5. Guardar

### Estados de una solicitud

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Creada, esperando aprobación |
| **Aprobado** | Autorizada |
| **Rechazado** | No autorizada — los días vuelven al saldo disponible |

---

## Permisos y Licencias

Los permisos registran ausencias planificadas y autorizadas (reposo médico, maternidad, etc.).

### Tipos de permiso disponibles

| Tipo | Descripción |
|------|-------------|
| **Reposo Médico** | Licencia por enfermedad o accidente |
| **Vacaciones** | Días de descanso remunerado |
| **Día Libre** | Día de descanso eventual |
| **Permiso por Maternidad** | Ley 5508/15 |
| **Permiso por Paternidad** | Por nacimiento de hijo |
| **Sin Goce de Sueldo** | Permiso sin retribución |
| **Otro** | Cualquier otro tipo de permiso |

### Registrar un permiso

1. Ir a **Empleados → Permisos**
2. Clic en **Nuevo permiso**
3. Seleccionar empleado, tipo, fechas y subir documento de respaldo si aplica
4. Guardar

### Estados del permiso

Pending (Pendiente) → Approved (Aprobado) / Rejected (Rechazado).

---

## Ausencias

Las ausencias registran los días en que un empleado no asistió sin justificación previa. El sistema las detecta automáticamente al calcular la asistencia diaria.

### Estados de una ausencia

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Sin revisión todavía |
| **Justificada** | El empleado presentó justificación válida — sin descuento |
| **Injustificada** | Sin justificación válida — genera descuento automático |

### Revisar una ausencia

1. Ir a **Empleados → Ausencias**
2. Clic sobre la ausencia a revisar
3. Opciones:
   - **Justificar:** registra la justificación y elimina el descuento si ya existía
   - **Marcar como injustificada:** genera automáticamente una deducción en la nómina del empleado por el valor del día

### Descuento por ausencia injustificada

El monto descontado se calcula según el tipo de contrato:
- **Mensual:** salario base ÷ 30
- **Jornalero:** tarifa diaria pactada

El descuento se aplica automáticamente en la siguiente nómina del empleado.
