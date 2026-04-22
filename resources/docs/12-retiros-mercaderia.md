# Retiros de Mercadería

Un retiro de mercadería es una compra a crédito de productos del catálogo del empleador. El monto total se descuenta en cuotas mensuales iguales directamente en la nómina, sin interés.

A diferencia de los préstamos, un empleado puede tener varios retiros activos al mismo tiempo.

## Estados de un retiro

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Creado, con productos cargados. Puede editarse, completarse o cancelarse. |
| **Aprobado** | Aprobado, con cuotas generadas. Los descuentos se aplican automáticamente en nómina. |
| **Pagado** | Todas las cuotas fueron descontadas. Estado final. |
| **Cancelado** | Cancelado antes de completarse. Estado final. |

## Crear un retiro

1. Ir a **Nóminas → Retiros de Mercadería**
2. Clic en **Nuevo Retiro**
3. Completar:
   - **Empleado**
   - **Cantidad de cuotas**
   - **Notas** (opcional)
4. Guardar

## Agregar productos

Después de crear el retiro, ir a la pestaña **Productos** para cargar los ítems:

1. Clic en **Agregar producto**
2. Ingresar:
   - **Código** — código interno libre (puede ser el código del catálogo)
   - **Nombre del producto**
   - **Descripción** (opcional)
   - **Precio unitario**
   - **Cantidad**
3. El **subtotal** se calcula automáticamente (precio × cantidad)
4. El **total del retiro** se actualiza automáticamente al guardar cada producto

> Los productos solo pueden editarse mientras el retiro esté en estado **Pendiente**.

## Ciclo de vida y acciones

**Desde Pendiente:**
- **Aprobar** — genera las cuotas y habilita el descuento en nómina. El monto de cada cuota es `total ÷ cantidad de cuotas`.
- **Cancelar** — cancela el retiro.

**Desde Aprobado:**
- **Cancelar** — cancela el retiro; las cuotas pendientes se anulan.
- **Descargar PDF** — genera el documento del retiro con los productos y el plan de cuotas.

> El retiro pasa a **Pagado** automáticamente al procesar en nómina la última cuota pendiente.

## Cuotas y descuento en nómina

Al generar la nómina de un período, el sistema incluye automáticamente las cuotas vencidas del período como deducción. No es necesario agregarlas manualmente.

Para ver el detalle de cuotas, abrir el retiro y revisar la sección **Cuotas**. Cada cuota muestra:
- Número de cuota (ej: "Cuota 3/12")
- Monto
- Fecha de vencimiento
- Estado: Pendiente, Pagada o Cancelada

La sección de cuotas también se puede exportar a Excel desde el botón **Exportar Excel**.

## Documento PDF

Desde la vista del retiro aprobado, el botón **Descargar PDF** genera el documento con:
- Datos del empleado y la empresa
- Listado detallado de productos (código, nombre, precio, cantidad, subtotal)
- Resumen del pago (total, cuotas, monto por cuota, saldo pendiente)
- Plan de cuotas completo con fechas de vencimiento
- Sección de firmas

## Límites y configuración

Los límites se configuran en **Configuración → Configuración de Nómina**, sección **Retiros de Mercadería**:

| Parámetro | Descripción |
|-----------|-------------|
| **Monto máximo por retiro** | Límite en Guaraníes por cada retiro individual |
| **Máximo de cuotas** | Cantidad máxima de cuotas permitidas por retiro |
