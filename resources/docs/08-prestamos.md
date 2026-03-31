# Préstamos y Adelantos

El sistema gestiona préstamos y adelantos de salario otorgados a empleados, con descuento automático de cuotas en cada nómina.

## Diferencia entre préstamo y adelanto

| | Préstamo | Adelanto de Salario |
|-|----------|---------------------|
| **Uso** | Montos mayores | Montos pequeños |
| **Cuotas** | Varias cuotas mensuales | 1 sola cuota (descuento íntegro en la próxima nómina) |
| **Límite** | Configurable en el sistema | Hasta el 50% del salario de referencia |
| **Restricción** | Sin límite de cantidad activos | Solo 1 adelanto activo por empleado a la vez |

## Crear un préstamo o adelanto

1. Ir a **Nóminas → Préstamos y Adelantos**
2. Clic en **Nuevo préstamo**
3. Completar:
   - **Empleado**
   - **Tipo:** Préstamo o Adelanto de Salario
   - **Monto total**
   - **Cantidad de cuotas** (1 cuota = adelanto)
   - **Fecha de otorgamiento**
   - **Motivo** y **notas** (opcional)
4. Guardar

> El sistema calcula automáticamente el monto de cada cuota (monto ÷ cantidad de cuotas).

## Estados del préstamo

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Creado, aún sin descontar cuotas |
| **Activo** | Con cuotas en curso de descuento |
| **Pagado** | Todas las cuotas fueron saldadas |
| **Cancelado** | Cancelado antes de completarse |

## Cuotas y descuento automático en nómina

Al generar la nómina de un período, el sistema incluye automáticamente la cuota del mes como deducción para cada empleado con préstamo activo. No es necesario agregar la deducción manualmente.

### Ver el detalle de cuotas

Abrir el préstamo y revisar la sección **Cuotas**. Cada cuota muestra:
- Número de cuota (ej: "Cuota 3/12")
- Monto
- Fecha de vencimiento
- Estado: Pendiente, Pagada o Cancelada

## Contrato de préstamo en PDF

Desde la vista del préstamo, el botón **Ver contrato** genera el documento del préstamo en PDF, listo para imprimir o firmar.

## Límites y restricciones

- **Préstamos:** el monto máximo por préstamo se configura en **Configuración General** (sección Préstamos).
- **Adelantos:** el monto máximo se calcula por empleado: **50% del salario de referencia mensual** del contrato activo.
- Un empleado **no puede tener más de un adelanto** en estado pendiente o activo al mismo tiempo. Debe completarse o cancelarse el existente antes de solicitar uno nuevo.

> El salario de referencia para un empleado mensual es el salario base mensual. Para un jornalero, es la tarifa diaria multiplicada por los días trabajados en el período.
