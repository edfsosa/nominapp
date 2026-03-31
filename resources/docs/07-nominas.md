# Nóminas

El módulo de Nóminas gestiona la liquidación salarial de los empleados por período. Soporta frecuencias mensual, quincenal y semanal, y tanto empleados mensuales como jornaleros.

## Conceptos clave

- **Período de nómina:** rango de fechas al que corresponde la liquidación
- **Nómina individual:** liquidación generada para un empleado en un período
- **Ítem de nómina:** cada línea de percepción o deducción dentro de una nómina
- **Percepción:** ingreso adicional al salario base (bono, horas extra, comisión, etc.)
- **Deducción:** descuento aplicado al salario (IPS, préstamo, ausencia, etc.)

---

## Períodos de Nómina

Los períodos definen el rango de fechas de cada liquidación.

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
| **Borrador** | Período creado, sin nóminas procesadas |
| **En Proceso** | Con nóminas en curso |
| **Cerrado** | Período finalizado |

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

> Las deducciones marcadas como **obligatorias** se asignan automáticamente a nuevos empleados.

### Asignar una deducción a un empleado

Misma lógica que las percepciones: desde el perfil del empleado, pestaña **Deducciones**.

---

## Generar una nómina

1. Ir a **Nóminas → Recibos**
2. Clic en **Nueva nómina**
3. Seleccionar el **período** y el **empleado** (o procesar por sucursal)
4. El sistema calcula automáticamente:
   - Salario base (proporcional si el empleado no trabajó el período completo)
   - Percepciones activas en el período
   - Horas extra (diurnas, nocturnas, feriados) desde las asistencias
   - Deducciones activas (incluyendo cuotas de préstamos y descuentos por ausencia)
5. Revisar los ítems de la nómina
6. Si todo es correcto, aprobar

### Flujo de estados de la nómina

```
Borrador → En Proceso → Aprobada → Pagada
```

| Estado | Descripción |
|--------|-------------|
| **Borrador** | Generada, pendiente de revisión |
| **En Proceso** | En revisión o ajuste |
| **Aprobada** | Confirmada — ya no puede modificarse |
| **Pagada** | Pago registrado |

> Una nómina en estado **Aprobada** o **Pagada** no puede eliminarse.

---

## Recibo de salario

Desde la lista de nóminas, clic en **Ver recibo** para abrir el recibo de salario individual en PDF. El documento incluye:

- Datos del empleado y período
- Salario base
- Detalle de percepciones y deducciones
- Salario bruto, total de deducciones y **salario neto a pagar**

---

## Exportar nómina

Desde **Nóminas → Recibos**, el botón **Exportar Excel** genera un archivo con el resumen de todas las nóminas del período seleccionado.

---

## Horas extra en nómina

Las horas extra calculadas automáticamente desde las asistencias se incluyen como percepciones en la nómina. Los multiplicadores de recargo se configuran en **Configuración → Configuración de Nómina**:

- Hora extra diurna: 1.5×
- Hora extra nocturna: 1.67×
- Hora extra en feriado: 2.0×
