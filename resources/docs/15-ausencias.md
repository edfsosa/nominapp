# Ausencias

Las ausencias registran los días en que un empleado no asistió sin justificación previa. El sistema las detecta automáticamente al calcular la asistencia diaria.

---

## Estados de una ausencia

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Sin revisión todavía |
| **Justificada** | El empleado presentó justificación válida — sin descuento |
| **Injustificada** | Sin justificación válida — genera descuento automático en nómina |

---

## Revisar una ausencia

1. Ir a **Empleados → Ausencias**
2. Clic sobre la ausencia a revisar
3. Desde el detalle, opciones disponibles según estado:

| Acción | Cuándo | Qué hace |
|--------|--------|----------|
| **Registrar asistencia** | Estado Pendiente | El empleado sí estuvo presente pero no marcó — crea las marcaciones y justifica la ausencia automáticamente |
| **Justificar** | Estado Pendiente o Injustificada | El empleado no estuvo pero tiene razón válida — vincula un permiso aprobado que cubra la fecha |
| **Marcar como injustificada** | Estado Pendiente | Confirma la ausencia sin justificación — genera deducción salarial automática |

---

## Justificar una ausencia manualmente

Al justificar una ausencia es obligatorio vincularla a un **permiso aprobado** (Licencia) que cubra esa fecha. Si no existe ninguno, el sistema lo indica y deshabilita el campo.

1. Abrir la ausencia y clic en **Justificar**
2. En el modal, seleccionar el permiso aprobado correspondiente
3. Agregar notas si aplica
4. Confirmar

> Para más información sobre permisos y licencias ver el capítulo **Permisos y Licencias**.

---

## Descuento por ausencia injustificada

El monto descontado se calcula según el tipo de contrato del empleado:

| Tipo de contrato | Fórmula |
|-----------------|---------|
| **Mensual** | Salario base ÷ 30 |
| **Jornalero** | Tarifa diaria pactada |

El descuento se aplica automáticamente en la siguiente nómina del empleado.

---

## Reporte de ausencias

Ir a **Asistencias → Reportes de Asistencia**, pestaña **Ausencias**, para ver un resumen por período con:
- Total de ausencias por empleado
- Ausencias justificadas vs. injustificadas
- Monto total de descuentos generados
