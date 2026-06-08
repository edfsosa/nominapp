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

Los permisos registran ausencias planificadas y autorizadas (reposo médico, maternidad, permisos parciales por horas, etc.). El sistema distingue dos modalidades: **permisos por días completos** y **permisos parciales por horas**.

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

---

### Registrar un permiso por días completos

1. Ir a **Empleados → Licencias**
2. Clic en **Nuevo permiso**
3. Completar:
   - **Empleado** y **Tipo de licencia**
   - **Fecha de inicio** y **Fecha de fin**
   - **Motivo** (opcional) y **Documento de soporte** (obligatorio para Reposo Médico)
4. Guardar — el permiso queda en estado **Pendiente**

---

### Registrar un permiso parcial por horas

Un permiso parcial es cuando el empleado se ausenta solo una parte del día (ej: 2 horas para una consulta médica). No abarca el día completo.

1. Ir a **Empleados → Licencias**
2. Clic en **Nuevo permiso**
3. Completar los datos generales (empleado, tipo, fecha de inicio y fin)
4. En la sección **Permiso por horas (opcional)**:
   - Ingresar la **Hora de inicio** y la **Hora de fin** del permiso
   - El sistema calcula automáticamente la duración y la muestra en tiempo real
5. Si el permiso debe descontar del salario, activar el toggle **Genera descuento en la próxima nómina**
6. Guardar

> 💡 La sección de horas está colapsada por defecto. Expandirla solo si el permiso es parcial (no el día completo).

#### Cálculo del descuento

Si se activa la opción de deducción, el monto se calcula automáticamente al aprobar según el tipo de contrato del empleado:

| Tipo de contrato | Fórmula |
|------------------|---------|
| **Mensual** | Salario ÷ 240 horas (30 días × 8 hs) × horas del permiso |
| **Jornalero** | Tarifa diaria ÷ 8 × horas del permiso |

El descuento se aplica en la **siguiente nómina** del empleado, igual que un adelanto de salario.

---

### Estados del permiso

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Creado, esperando aprobación |
| **Aprobado** | Autorizado |
| **Rechazado** | No autorizado |

---

### Aprobar o rechazar un permiso

Los permisos se pueden gestionar desde dos lugares:

**Desde el listado global (Empleados → Licencias):**
1. Buscar el permiso en la tabla
2. En la fila, usar el menú de acciones → **Aprobar** o **Rechazar**

**Desde el detalle del permiso:**
1. Clic sobre el permiso en la tabla para abrir el detalle
2. En el encabezado, usar los botones **Aprobar** o **Rechazar**

**Desde el perfil del empleado:**
1. Abrir el empleado en **Empleados**
2. Pestaña **Licencias**
3. En la fila del permiso → **Aprobar** o **Rechazar**

El modal de confirmación de aprobación muestra información contextual según el tipo de permiso:
- **Permiso parcial:** rango horario y monto del descuento a generar (si aplica)
- **Permiso por días completos:** cantidad de ausencias del período que serán justificadas automáticamente

---

### Justificación automática de ausencias

Al aprobar un permiso **por días completos**, el sistema busca automáticamente todas las ausencias del empleado registradas en ese período con estado **Pendiente** o **Injustificada**, y las justifica sin intervención adicional.

> ⚠️ Los permisos **parciales por horas** **no justifican ausencias** — el empleado estuvo presente ese día aunque se ausentó unas horas. La justificación automática aplica solo a permisos de día completo.

---

### Documento de soporte

Al registrar el permiso se puede adjuntar un documento (certificado médico, solicitud escrita, etc.). Formatos aceptados: PDF, JPG, PNG, GIF. Tamaño máximo: 10 MB.

El documento es **obligatorio** para permisos de tipo **Reposo Médico**.

Desde el detalle del permiso o la pestaña Licencias del empleado, se puede descargar el documento adjunto con el botón **Descargar**.

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
