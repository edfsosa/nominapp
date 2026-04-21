# Adelantos de Salario

Un adelanto es un anticipo parcial del salario del mes en curso. A diferencia de los préstamos, no tiene cuotas: el monto se descuenta íntegro en la próxima liquidación de nómina.

## Estados de un adelanto

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Creado, esperando aprobación. Puede editarse, rechazarse o cancelarse. |
| **Aprobado** | Aprobado, será descontado en la próxima nómina. Puede cancelarse mientras no haya sido procesado. |
| **Pagado** | Descontado en nómina. Estado final. |
| **Rechazado** | Rechazado por el administrador. Estado final. |
| **Cancelado** | Cancelado antes de ser procesado. Estado final. |

## Crear un adelanto individual

1. Ir a **Nóminas → Adelantos**
2. Clic en **Nuevo Adelanto**
3. Seleccionar el empleado (solo se muestran empleados activos con salario definido)
4. Ingresar el monto — el sistema muestra el monto máximo permitido según el salario del empleado
5. Agregar notas u observaciones (opcional)
6. Guardar

## Generar adelantos masivamente

Para crear adelantos para varios empleados a la vez:

1. Ir a **Nóminas → Adelantos**
2. Clic en **Generar Adelantos** en el encabezado de la página
3. Completar el formulario:
   - **Empresa / Sucursal** (opcional) — filtra la lista de empleados disponibles
   - **Monto** — se aplicará a todos los empleados seleccionados
   - **Empleados** — seleccionar de la lista o dejar vacío para aplicar a todos los activos del filtro
   - **Notas** (opcional)
4. Confirmar con **Generar**

> El sistema omite automáticamente los empleados que ya alcanzaron el límite de adelantos activos por período o cuyo monto supere su tope máximo. La notificación de resultado indica cuántos se crearon y cuántos se omitieron con el motivo.

## Ciclo de vida y acciones

**Desde Pendiente:**
- **Aprobar** — habilita el descuento en la próxima nómina.
- **Rechazar** — rechaza el adelanto; se puede registrar un motivo.
- **Cancelar** — cancela el adelanto; se puede registrar un motivo.

**Desde Aprobado:**
- **Cancelar** — solo disponible si la nómina del período aún no fue generada.

**Desde Aprobado o Pagado:**
- **Descargar PDF** — genera el comprobante del adelanto.

## Aprobación y rechazo masivo

Desde el listado se pueden seleccionar varios adelantos y ejecutar acciones en bloque:

- **Aprobar seleccionados** — aprueba todos los que estén en estado Pendiente.
- **Rechazar seleccionados** — rechaza todos los que estén en estado Pendiente; permite ingresar un motivo común.

La notificación de resultado indica cuántos fueron procesados y cuántos fueron ignorados por no estar en el estado esperado.

## Descuento automático en nómina

Al generar la nómina, el sistema descuenta automáticamente todos los adelantos **Aprobados** del empleado que aún no hayan sido procesados. No es necesario agregar la deducción manualmente.

## Exportar a Excel

Desde el encabezado del listado, el botón **Exportar Excel** descarga un archivo con todos los adelantos registrados, incluyendo: Empleado, CI, Monto, Estado, Notas, Fecha de aprobación, Aprobado por, Fecha de creación y última edición.

## Límites y configuración

Los límites se configuran en **Configuración → Configuración de Nómina**, sección **Adelantos de Salario**:

| Parámetro | Descripción |
|-----------|-------------|
| **Porcentaje máximo por adelanto** | % del salario que puede adelantarse por solicitud individual |
| **Máximo de adelantos por período** | Cantidad máxima de adelantos activos simultáneos (0 = sin límite) |

> El sistema valida que `cantidad máxima × porcentaje` no supere el 100% del salario al guardar la configuración.

**Validaciones al aprobar un adelanto:**
- La cantidad de adelantos activos del empleado (Pendientes + Aprobados) no puede igualar o superar el límite configurado.
- Para empleados mensuales: la suma de todos los adelantos activos más el monto del nuevo adelanto no puede superar el salario mensual bruto.
- No se puede aprobar si la nómina del período actual ya fue generada para ese empleado.

> El salario de referencia para empleados mensuales es el salario base del contrato activo. Para jornaleros, es la tarifa diaria × 30.
