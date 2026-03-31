# Vacaciones, Permisos y Ausencias

## Vacaciones

El sistema gestiona el saldo de días de vacaciones de cada empleado según la antigüedad y lo establecido en el contrato.

### Saldo de vacaciones

El saldo se acumula automáticamente a partir de la fecha de ingreso del empleado (registrada en el contrato activo).

Para consultar el saldo:
1. Ir al perfil del empleado en **Empleados**
2. Pestaña **Vacaciones**

### Registrar una solicitud de vacaciones

1. Ir a **Empleados → Vacaciones**
2. Clic en **Nueva solicitud**
3. Seleccionar empleado, fechas de inicio y fin
4. El sistema calcula automáticamente los días hábiles
5. Guardar

### Estados de una solicitud

| Estado | Descripción |
|--------|-------------|
| `Pendiente` | Solicitud creada, en espera de aprobación |
| `Aprobada` | Vacaciones autorizadas |
| `Rechazada` | Solicitud no aprobada |
| `Tomada` | Período ya disfrutado |

### Documento de vacaciones

Desde la fila de la solicitud puede generarse el documento oficial de vacaciones en PDF.

---

## Permisos

Los permisos son tipos de ausencias justificadas (ej: permiso médico, permiso personal, duelo, etc.).

### Crear un tipo de permiso

1. Ir a **Empleados → Permisos**
2. Clic en **Nuevo permiso**
3. Ingresar nombre y si descuenta días del saldo de vacaciones
4. Guardar

---

## Ausencias

Las ausencias registran los días en que un empleado no asistió al trabajo.

### Registrar una ausencia

1. Ir a **Empleados → Ausencias**
2. Clic en **Nueva ausencia**
3. Seleccionar:
   - Empleado
   - Fecha(s) de la ausencia
   - Tipo de permiso (justificada o no)
   - Observación (opcional)
4. Guardar

### Impacto en nómina

Las ausencias injustificadas generan un descuento proporcional al salario diario del empleado, que se refleja automáticamente en la nómina del período correspondiente.

Las ausencias justificadas con permiso médico u otro tipo configurado como "sin descuento" no afectan el salario.

> El cálculo del descuento por ausencia se basa en la configuración de días y horas mensuales definida en **Configuración de Nómina**.
