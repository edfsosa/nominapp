# Empleados y Contratos

## Empleados

El módulo de Empleados gestiona todos los datos personales y laborales del personal.

### Campos del empleado

- **Nombre y apellido**, foto
- **Cédula de identidad (CI):** solo dígitos, sin puntos ni guiones (ej: `4567890`)
- **Fecha de nacimiento**, sexo (`Masculino` / `Femenino`)
- **Teléfono:** con 0 inicial, sin espacios (ej: `0981123456`)
- **Email**
- **Sucursal** a la que pertenece
- **Estado:** Activo, Inactivo o Suspendido

### Crear un empleado

1. Ir a **Empleados → Empleados**
2. Clic en **Nuevo empleado**
3. Completar los datos personales
4. Opcionalmente, expandir la sección **Contrato inicial** para crear el primer contrato en el mismo paso
5. Guardar

> Los nombres se capitalizan automáticamente al guardar. La CI debe ser única en el sistema.

### Estados del empleado

| Estado | Descripción |
|--------|-------------|
| **Activo** | Empleado vigente en nómina |
| **Inactivo** | Relación laboral terminada |
| **Suspendido** | Suspensión temporal |

---

## Contratos

El contrato activo define el **salario, cargo y fecha de ingreso** del empleado. Un empleado puede tener historial de contratos.

### Campos del contrato

- **Tipo de contrato** (ver tabla abajo)
- **Modalidad de trabajo:** presencial, remoto o híbrido
- **Fecha de inicio** (obligatoria) y **fecha de fin** (solo para contratos a plazo o por obra)
- **Días de prueba** (opcional)
- **Tipo de remuneración:** Mensual o Por Jornal (diario)
- **Salario:** monto en Guaraníes
- **Frecuencia de nómina:** Mensual, Quincenal o Semanal
- **Departamento y Cargo**
- **Método de pago:** débito, efectivo o cheque
- **Notas**

### Tipos de contrato

| Tipo | Descripción |
|------|-------------|
| **Por Tiempo Indefinido** | Sin fecha de fin |
| **Por Plazo Determinado** | Con fecha de fin definida |
| **Por Obra Determinada** | Hasta completar una tarea específica |
| **De Aprendizaje** | Contrato formativo |
| **Pasantía** | Período de práctica |

> Para contratos **Por Plazo Determinado** y **Por Obra Determinada** debe ingresarse la fecha de fin. Para los demás tipos, la fecha de fin no aplica.

### Estados del contrato

| Estado | Descripción |
|--------|-------------|
| **Vigente** | Contrato en curso |
| **Vencido** | Fecha de fin superada sin renovar |
| **Terminado** | Finalizado anticipadamente |
| **Renovado** | Reemplazado por un nuevo contrato |

Solo puede haber **un contrato vigente** a la vez por empleado.

### Crear un contrato

1. Abrir el empleado y ir a la pestaña **Contratos**
2. Clic en **Nuevo contrato**
3. Completar los campos (tipo, fechas, salario, cargo)
4. Guardar

> Departamentos y cargos pueden crearse directamente desde el selector, sin salir del formulario.

### Documentos del contrato

Desde la fila del contrato en la tabla:
- **Generar PDF:** abre el contrato en PDF listo para imprimir o firmar
- **Subir PDF firmado:** adjunta el documento firmado escaneado
- **Descargar firmado:** descarga el PDF que fue subido anteriormente

---

## Percepciones y Deducciones del empleado

Desde las pestañas **Percepciones** y **Deducciones** del perfil del empleado puede asignar conceptos que se incluirán automáticamente en cada nómina:

- **Percepciones:** ingresos adicionales (ej: bono de transporte, antigüedad)
- **Deducciones:** descuentos recurrentes (ej: IPS, seguro médico)

Cada asignación tiene fecha de inicio, fecha de fin opcional y monto personalizado (si difiere del monto global del concepto).

---

## Amonestaciones del empleado

Desde la pestaña **Amonestaciones** en el perfil del empleado se pueden ver y registrar todas las amonestaciones emitidas a ese empleado. Ver el capítulo **Amonestaciones** para el detalle completo del módulo.

---

## Legajo del empleado

El legajo es un resumen completo del empleado en PDF. Para generarlo:

1. Abrir el perfil del empleado
2. Clic en el botón **Legajo** en el encabezado de la página

El documento incluye datos personales, contrato activo e historial relevante.
