# Préstamos y Adelantos

El sistema gestiona préstamos otorgados a empleados, con descuento automático de cuotas en cada nómina.

## Diferencia entre préstamo y adelanto

- **Préstamo:** monto mayor pagadero en cuotas mensuales durante varios meses
- **Adelanto de salario:** monto pequeño que se descuenta íntegramente en la próxima nómina

Ambos se gestionan desde el mismo módulo.

## Crear un préstamo

1. Ir a **Nóminas → Préstamos y Adelantos**
2. Clic en **Nuevo préstamo**
3. Completar:
   - **Empleado**
   - **Monto total** del préstamo
   - **Cantidad de cuotas** (1 cuota = adelanto de salario)
   - **Fecha de inicio** del primer descuento
   - **Observación** (opcional)
4. Guardar

> El sistema calcula automáticamente el monto de cada cuota (monto ÷ cuotas).

## Estados de un préstamo

| Estado | Descripción |
|--------|-------------|
| `Pendiente` | Creado, aún sin descontar |
| `Activo` | Con cuotas en curso |
| `Pagado` | Todas las cuotas saldadas |
| `Cancelado` | Cancelado antes de completarse |
| `En mora` | Con cuotas vencidas sin pagar |

## Cuotas y descuento en nómina

Cada mes, al generar la nómina, el sistema incluye automáticamente la cuota del préstamo como deducción para cada empleado con préstamo activo.

Para ver el detalle de cuotas de un préstamo, abrir el registro del préstamo y revisar la sección **Cuotas**.

## Documento del préstamo

Desde la vista del préstamo puede generarse el contrato de préstamo en PDF con el botón **Ver contrato**.

## Límites de préstamos

Los montos máximos permitidos para préstamos y adelantos se configuran en **Configuración General** (sección Configuración de Préstamos).

> Un empleado puede tener más de un préstamo activo siempre que el total no supere el límite configurado.
