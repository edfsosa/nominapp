# Vacaciones

El módulo de Vacaciones gestiona el saldo anual de días de descanso remunerado de cada empleado. El saldo se calcula automáticamente según la antigüedad.

---

## Días a los que tiene derecho (según antigüedad)

| Antigüedad | Días hábiles |
|------------|--------------|
| Menos de 1 año | Sin derecho |
| 1 a 5 años | 12 días |
| 6 a 10 años | 18 días |
| Más de 10 años | 30 días |

---

## Saldo de vacaciones

El saldo se actualiza automáticamente al aprobar o rechazar solicitudes. Para consultarlo:

1. Abrir el perfil del empleado en **Empleados**
2. Pestaña **Vacaciones** — muestra días disponibles, usados y pendientes de aprobación

---

## Registrar una solicitud de vacaciones

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

---

## Estados de una solicitud

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Creada, esperando aprobación |
| **Aprobado** | Autorizada — los días se descuentan del saldo disponible |
| **Rechazado** | No autorizada — los días vuelven al saldo disponible |
