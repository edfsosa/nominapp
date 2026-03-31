# Nóminas

El módulo de nóminas gestiona la liquidación salarial de los empleados por período. Soporta empleados mensuales y jornaleros.

## Conceptos clave

- **Período de nómina:** rango de fechas al que corresponde la liquidación (ej: marzo 2025)
- **Nómina:** liquidación generada para un período y un grupo de empleados
- **Ítem de nómina:** línea de detalle por empleado con salario, percepciones, deducciones y neto
- **Percepción:** ingreso adicional (bono, horas extra, comisión, etc.)
- **Deducción:** descuento (IPS, préstamo, ausencia, etc.)

## Flujo de una nómina

```
Borrador → En Proceso → Aprobada → Pagada
```

1. Se crea el período y se genera la nómina en estado **Borrador**
2. El sistema calcula los montos automáticamente → pasa a **En Proceso**
3. Se revisan los ítems y se ajusta si es necesario
4. Se **Aprueba** la nómina — ya no puede editarse
5. Se registra el **pago** y pasa a **Pagada**

---

## Períodos de nómina

Los períodos definen el rango de fechas de cada liquidación.

### Crear un período

1. Ir a **Nóminas → Períodos**
2. Clic en **Nuevo período**
3. Ingresar nombre (ej: "Marzo 2025"), fecha inicio y fin, empresa
4. Guardar

---

## Generar una nómina

1. Ir a **Nóminas → Recibos**
2. Clic en **Nueva nómina**
3. Seleccionar el período y la sucursal (o procesar toda la empresa)
4. El sistema genera un ítem por cada empleado activo con contrato vigente
5. Revisar los ítems: salario base, percepciones, deducciones, neto a pagar
6. Si todo es correcto, cambiar estado a **Aprobada**

### Ver el recibo de un empleado

Desde la lista de ítems, clic en **Ver recibo** para abrir el PDF del recibo de salario individual.

---

## Percepciones

Las percepciones son conceptos de ingreso adicional al salario base.

### Crear una percepción global

1. Ir a **Nóminas → Percepciones**
2. Clic en **Nueva percepción**
3. Ingresar nombre, tipo de cálculo (fijo, porcentaje o por horas) y monto/tasa
4. Guardar

### Asignar a un empleado

Desde el perfil del empleado, pestaña **Percepciones**, asignar la percepción con el monto específico para ese empleado.

---

## Deducciones

Las deducciones son descuentos aplicados al salario.

### Crear una deducción global

1. Ir a **Nóminas → Deducciones**
2. Clic en **Nueva deducción**
3. Ingresar nombre, tipo (fijo, porcentaje) y monto/tasa
4. Guardar

### Asignar a un empleado

Desde el perfil del empleado, pestaña **Deducciones**, asignar la deducción. Puede especificarse un monto distinto al global.

---

## Horas extra

Las horas extra calculadas desde las asistencias se incluyen automáticamente como percepciones en la nómina. Los multiplicadores se configuran en **Configuración de Nómina**.

---

## Exportar nómina

Desde la lista de nóminas puede exportar un resumen en Excel con el botón **Exportar Excel** que aparece antes de crear una nueva.
