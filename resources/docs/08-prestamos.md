# Préstamos y Adelantos

El sistema gestiona préstamos y adelantos de salario otorgados a empleados, con descuento automático de cuotas en cada nómina.

## Diferencia entre préstamo y adelanto

| | Préstamo | Adelanto de Salario |
|-|----------|---------------------|
| **Uso** | Montos mayores | Montos pequeños |
| **Cuotas** | Varias cuotas mensuales | 1 sola cuota (descuento íntegro en la próxima nómina) |
| **Límite** | Cuota máxima 25% del salario (Art. 245 CLT) | Hasta el 25% del salario de referencia |
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
| **Pendiente** | Creado, aún sin descontar cuotas. Puede editarse o cancelarse. |
| **Activo** | Aprobado y con cuotas en curso de descuento |
| **En Mora** | Tiene cuotas vencidas hace más de 30 días sin pagar |
| **Pagado** | Todas las cuotas fueron saldadas |
| **Cancelado** | Cancelado antes de completarse |

## Ciclo de vida y acciones

Un préstamo recién creado queda en estado **Pendiente**. Desde ahí:

- **Activar** — aprueba el préstamo y habilita el descuento automático de cuotas en nómina. El sistema valida que la cuota no supere el 25% del salario del empleado (Art. 245 CLT).
- **Cancelar** — cancela el préstamo. Las cuotas pendientes se anulan; las ya pagadas se conservan.

Una vez **Activo**:

- **Marcar en mora** — si el empleado no está pagando, se puede registrar el préstamo como moroso. Requiere ingresar un motivo.
- **Cancelar** — también disponible desde activo.

Desde **En Mora**:

- **Reactivar** — vuelve el préstamo a estado activo para retomar los descuentos.
- **Cancelar** — cancela definitivamente.

> El préstamo pasa a **Pagado** automáticamente cuando se confirma el pago de la última cuota al procesar la nómina.

## Cuotas y descuento automático en nómina

Al generar la nómina de un período, el sistema incluye automáticamente la cuota del mes como deducción para cada empleado con préstamo activo. No es necesario agregar la deducción manualmente.

### Ver el detalle de cuotas

Abrir el préstamo y revisar la sección **Cuotas**. Cada cuota muestra:
- Número de cuota (ej: "Cuota 3/12")
- Monto
- Fecha de vencimiento
- Estado: Pendiente, Pagada o Cancelada

## Adelanto automático mensual

Es posible configurar que el sistema genere el adelanto de cada empleado automáticamente el 1° de cada mes.

### Configurar el porcentaje de adelanto

1. Ir al perfil del empleado → pestaña **Contratos**
2. Editar el contrato activo
3. En la sección **Remuneración**, completar el campo **% de adelanto** (1–25%)
4. Guardar

El 1° de cada mes a las 07:00 el sistema genera y activa el adelanto por ese porcentaje del salario mensual.

> Solo aplica a empleados con contrato de tipo **mensual**. Si el empleado ya tiene un adelanto pendiente o activo, se omite ese mes.

### Configurar masivamente

Para definir el porcentaje a varios empleados a la vez:

1. Ir a **Empleados**
2. Seleccionar los empleados deseados con el checkbox
3. En **Acciones masivas**, seleccionar **Definir % de adelanto automático**
4. Ingresar el porcentaje y confirmar

## Contrato de préstamo en PDF

Desde la vista del préstamo, el botón **Ver contrato** genera el documento del préstamo en PDF, listo para imprimir o firmar.

## Límites y restricciones

- **Cuota máxima:** el monto de cada cuota no puede superar el **25% del salario mensual** del empleado (Art. 245 CLT). El sistema lo valida al activar el préstamo.
- **Adelantos:** el monto máximo es el **25% del salario de referencia mensual** del contrato activo.
- Un empleado **no puede tener más de un adelanto** en estado pendiente o activo al mismo tiempo. Debe completarse o cancelarse el existente antes de solicitar uno nuevo.

> El salario de referencia para un empleado mensual es el salario base mensual. Para un jornalero, es la tarifa diaria multiplicada por 30.
