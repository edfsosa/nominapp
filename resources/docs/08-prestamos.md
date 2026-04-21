# Préstamos

El módulo de préstamos permite otorgar créditos a empleados con descuento automático en cuotas mensuales a través de la nómina.

## Estados de un préstamo

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Creado, aún no activado. Puede editarse o cancelarse. |
| **Activo** | Activado, con cuotas descontándose en nómina. |
| **En Mora** | Tiene cuotas vencidas sin pagar. |
| **Pagado** | Todas las cuotas fueron saldadas. Estado final. |
| **Cancelado** | Cancelado antes de completarse. Estado final. |

## Crear un préstamo

1. Ir a **Nóminas → Préstamos**
2. Clic en **Nuevo Préstamo**
3. Completar:
   - **Empleado**
   - **Monto total**
   - **Cantidad de cuotas**
   - **Fecha de otorgamiento**
   - **Motivo** y **notas** (opcional)
4. Guardar

> El sistema calcula automáticamente el monto de cada cuota (monto ÷ cuotas).

## Ciclo de vida y acciones

**Desde Pendiente:**
- **Activar** — aprueba el préstamo y habilita el descuento de cuotas en nómina. Valida que la cuota no supere el 25% del salario del empleado (Art. 245 CLT).
- **Cancelar** — cancela el préstamo.

**Desde Activo:**
- **Marcar en Mora** — registra el préstamo como moroso. Requiere ingresar un motivo.
- **Cancelar** — cancela el préstamo; las cuotas pendientes se anulan.

**Desde En Mora:**
- **Reactivar** — vuelve a estado Activo para retomar los descuentos.
- **Cancelar** — cancela definitivamente.

> El préstamo pasa a **Pagado** automáticamente al procesar en nómina la última cuota pendiente.

## Cuotas y descuento en nómina

Al generar la nómina de un período, el sistema incluye automáticamente las cuotas activas de cada empleado como deducción. No es necesario agregarlas manualmente.

Para ver el detalle de cuotas, abrir el préstamo y revisar la sección **Cuotas**. Cada cuota muestra:
- Número de cuota (ej: "Cuota 3/12")
- Monto
- Fecha de vencimiento
- Estado: Pendiente, Pagada o Cancelada

## Contrato de préstamo en PDF

Desde la vista del préstamo, el botón **Descargar PDF** genera el contrato del préstamo listo para imprimir o firmar digitalmente.

## Límites

- **Cuota máxima:** el monto de cada cuota no puede superar el **25% del salario mensual** del empleado (Art. 245 CLT). El sistema lo valida al activar el préstamo.
- El límite de cuota se configura en **Configuración → Configuración de Nómina**, sección **Préstamos**.
