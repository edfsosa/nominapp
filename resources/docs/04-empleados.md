# Empleados y Contratos

El módulo de Empleados gestiona toda la información del personal: datos personales, documentos, contratos laborales y estado.

## Crear un empleado

1. Ir a **Empleados → Empleados**
2. Clic en **Nuevo empleado**
3. Completar los datos personales:
   - Nombre y apellido
   - Cédula de identidad (CI) — solo dígitos, sin puntos
   - Fecha de nacimiento, sexo, estado civil
   - Teléfono (formato paraguayo, ej: `0981123456`)
   - Email
   - Sucursal a la que pertenece
4. Opcionalmente, en la sección **Contrato inicial**, complete el primer contrato para no tener que crearlo por separado:
   - Tipo de contrato, fechas, salario, cargo
5. Guardar

> El nombre se capitaliza automáticamente al guardar.

## Contratos

El contrato activo define el **salario, cargo y fecha de ingreso** del empleado. Un empleado puede tener historial de contratos.

### Crear un contrato

1. Abrir el empleado y ir a la pestaña **Contratos**
2. Clic en **Nuevo contrato**
3. Completar:
   - **Tipo:** determinado o indeterminado
   - **Modalidad:** presencial, remoto o híbrido
   - **Fechas:** inicio y fin (la fecha fin solo aplica a contratos determinados)
   - **Tipo de salario:** mensual o jornalero
   - **Salario:** monto en Guaraníes
   - **Cargo:** seleccionar departamento y luego cargo
4. Guardar

### Estados del contrato

| Estado | Descripción |
|--------|-------------|
| `Borrador` | Creado pero no vigente |
| `Activo` | Contrato en curso |
| `Suspendido` | Suspensión temporal |
| `Inactivo` | Contrato finalizado |

Solo puede haber **un contrato activo** a la vez por empleado.

### Documentos del contrato

Desde la fila del contrato puede:
- **Generar PDF:** descarga el contrato firmable en PDF
- **Subir PDF firmado:** adjunta el documento firmado al registro
- **Descargar firmado:** descarga el PDF que fue subido

## Legajo del empleado

El legajo es un resumen completo del empleado en PDF. Se genera desde la vista del empleado con el botón **Descargar legajo**.

Incluye: datos personales, contrato activo, historial laboral y documentos asociados.

## Percepciones y Deducciones del empleado

Desde las pestañas **Percepciones** y **Deducciones** del empleado puede asignar conceptos adicionales que se incluirán automáticamente en cada nómina:

- **Percepciones:** bonificaciones fijas (ej: bono de transporte, antigüedad)
- **Deducciones:** descuentos fijos (ej: IPS, seguro médico)

> Las percepciones y deducciones globales se definen en **Nóminas → Percepciones** y **Nóminas → Deducciones**.
