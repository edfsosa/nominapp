# Nóminas

El módulo de Nóminas gestiona la liquidación salarial de los empleados por período. Soporta frecuencias mensual, quincenal y semanal, y tanto empleados mensuales como jornaleros.

## Conceptos clave

- **Período de nómina:** rango de fechas al que corresponde la liquidación
- **Recibo de salario:** liquidación generada para un empleado dentro de un período
- **Ítem de nómina:** cada línea de percepción o deducción dentro de un recibo
- **Percepción:** ingreso adicional al salario base (bono, horas extra, viáticos, etc.)
- **Deducción:** descuento aplicado al salario (IPS, préstamo, ausencia, etc.)

---

## Períodos de Nómina

Los períodos definen el rango de fechas de cada liquidación. Todo el proceso de nómina se gestiona desde **Nóminas → Períodos**.

### Crear un período

1. Ir a **Nóminas → Períodos**
2. Clic en **Nuevo período**
3. Seleccionar la **frecuencia:** Mensual, Quincenal o Semanal
4. Ingresar las fechas de inicio y fin
5. El **nombre** se genera automáticamente:
   - Mensual: "Enero 2026"
   - Quincenal: "Quincena 01/01/2026 - 15/01/2026"
   - Semanal: "Semana del 05/01/2026 al 11/01/2026"
6. Guardar

### Estados del período

| Estado | Descripción |
|--------|-------------|
| **Borrador** | Período creado, sin recibos generados |
| **En Proceso** | Con recibos en curso |
| **Cerrado** | Período finalizado — los recibos no pueden modificarse |

### Generar recibos del período

Desde la vista del período (clic en el período para abrirlo):

1. Clic en **Generar Recibos** para procesar todos los empleados activos que corresponden a la frecuencia del período
2. El sistema calcula automáticamente para cada empleado:
   - Salario base (proporcional si el período es parcial)
   - Percepciones activas en el período
   - Horas extra desde las asistencias registradas
   - Día de descanso semanal remunerado (para jornaleros)
   - Deducciones activas (IPS, préstamos, adelantos, retiros de mercadería, ausencias injustificadas)
   - Bonificación familiar IPS

Para agregar un empleado que no estaba incluido: clic en **Agregar Recibo** y seleccionar el empleado.

### Cerrar un período

Al cerrar el período los recibos quedan bloqueados. El botón **Cerrar Período** solo está disponible cuando no hay recibos en estado Borrador o Aprobado pendientes.

> Si hay empleados activos sin recibo al momento de cerrar, el sistema lo informa como advertencia en el modal de confirmación, pero permite continuar.

---

## Recibos de Salario

Cada recibo corresponde a la liquidación de un empleado en un período.

### Flujo de estados

El flujo varía según el método de pago del recibo:

**Acreditación bancaria (transferencia):**
```
Borrador → Aprobado → Acreditado → Pagado
```

**Efectivo:**
```
Borrador → Aprobado → Pagado
```

| Estado | Descripción |
|--------|-------------|
| **Borrador** | Generado, pendiente de revisión. Se puede regenerar o editar. |
| **Aprobado** | Revisado y aprobado. Para transferencias, pasa a Acreditado al confirmarse el depósito. Para efectivo, pasa directo a Pagado. |
| **Acreditado** | El monto fue depositado en la cuenta bancaria del empleado (solo transferencias). |
| **Pagado** | Pago registrado y completado. |

### Acciones disponibles por estado

Desde la vista del recibo o desde la tabla de recibos del período:

| Estado | Acciones disponibles |
|--------|---------------------|
| Borrador | Aprobar, Regenerar, Editar (método de pago y notas), Eliminar |
| Aprobado | Marcar Acreditado (transferencia), Marcar Pagado (efectivo), Desaprobar, Descargar PDF |
| Acreditado | Marcar Pagado, Revertir a Aprobado (si no está en un lote bancario), Descargar PDF |
| Pagado | Revertir Pago, Descargar PDF |

### Aprobar todos los recibos del período

Desde la vista del período, el botón **Aprobar Todos** aprueba en un solo paso todos los recibos en estado Borrador.

### Marcar como pagados

El botón **Marcar Todos Pagados** (en la vista del período) marca como pagados:
- Recibos de **efectivo** en estado Aprobado
- Recibos de **transferencia** en estado Acreditado

---

## Descarga del recibo en PDF

Desde la vista de un recibo o desde la tabla del período (botón **PDF** en la fila), se abre un modal para elegir el formato:

| Formato | Descripción |
|---------|-------------|
| **Para imprimir** | Hoja A4 horizontal con dos copias: *COPIA EMPLEADO* y *COPIA EMPRESA*, separadas por una línea punteada de corte |
| **Para empleado** | Hoja A4 vertical con una sola copia, ideal para enviar por correo electrónico |

También es posible descargar los PDFs de varios recibos a la vez seleccionándolos en la tabla y usando la acción **Descargar PDFs** (bulk action). Si se selecciona un solo recibo se descarga el PDF directamente; si son varios, se genera un archivo ZIP.

---

## Percepciones

Las percepciones son conceptos de ingreso adicional al salario base.

### Crear una percepción

1. Ir a **Nóminas → Percepciones**
2. Clic en **Nueva percepción**
3. Completar:
   - **Nombre** y **código** (único, ej: `BON-TRANS`)
   - **Tipo de cálculo:** Fijo (monto en Gs.) o Porcentaje del salario
   - **Monto** o **porcentaje**
   - Si afecta IPS o IRP
4. Guardar

### Asignar una percepción a un empleado

Desde el perfil del empleado, pestaña **Percepciones**:

1. Clic en **Asignar percepción**
2. Seleccionar la percepción global
3. Ingresar la fecha de inicio (y fin si aplica)
4. Opcionalmente, definir un **monto personalizado** que reemplaza al monto global
5. Guardar

---

## Deducciones

Las deducciones son descuentos aplicados al salario.

### Crear una deducción

1. Ir a **Nóminas → Deducciones**
2. Clic en **Nueva deducción**
3. Completar nombre, código, tipo de cálculo (fijo o porcentaje), y si es **obligatoria**
4. Guardar

> Las deducciones marcadas como **obligatorias** se asignan automáticamente a todos los empleados.

### Asignar una deducción a un empleado

Misma lógica que las percepciones: desde el perfil del empleado, pestaña **Deducciones**.

---

## Horas extra en nómina

Las horas extra calculadas automáticamente desde las asistencias se incluyen como percepciones en la nómina. Los multiplicadores de recargo se configuran en **Configuración → Configuración de Nómina**:

- Hora extra diurna: 1.5×
- Hora extra nocturna: 1.67×
- Hora extra en feriado: 2.0×

---

## Exportar nómina

Desde la tabla de recibos de un período, el botón **Exportar Excel** genera un archivo con el resumen de todos los recibos del período. También es posible exportar solo los recibos seleccionados usando la acción bulk **Exportar Seleccionados**.
