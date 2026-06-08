# Percepciones y Deducciones

El catálogo de Percepciones y Deducciones define los conceptos que se pueden asignar a empleados y que el sistema incluye automáticamente en cada nómina.

- **Percepciones:** ingresos adicionales al salario base (bonos, comisiones, viáticos, etc.)
- **Deducciones:** descuentos sobre el salario (IPS, préstamos, embargos, descuentos voluntarios, etc.)

---

## Percepciones

### Tipos de percepción

| Tipo | Afecta IPS | Descripción |
|------|-----------|-------------|
| **Salarial** | Sí (automático) | Forma parte del salario cotizable para IPS |
| **No Salarial** | No | No entra en la base de cálculo del IPS |
| **Otro** | Configurable | El usuario decide si afecta o no al IPS |

### Crear una percepción

1. Ir a **Nóminas → Percepciones**
2. Clic en **Nueva percepción**
3. Completar:
   - **Nombre** y **Código** (único, ej: `BON-TRANS`)
   - **Tipo:** Salarial, No Salarial u Otro
   - **Tipo de cálculo:** Monto Fijo (Gs.) o Porcentaje del salario
   - **Monto** o **porcentaje** según el tipo de cálculo elegido
   - **Afecta IPS** (solo editable si el tipo es "Otro")
4. Guardar

### Activar / desactivar una percepción

Desde el listado o el detalle, usar el campo **Activo**. Una percepción inactiva no aparece en los formularios de asignación, pero sus registros históricos se conservan.

---

## Deducciones

### Tipos de deducción

| Tipo | Descripción |
|------|-------------|
| **Legal** | Obligaciones legales (IPS, IRP) |
| **Deuda** | Cuotas de préstamos, adelantos y retiros de mercadería |
| **Judicial** | Embargos dispuestos por orden judicial |
| **Voluntaria** | Descuentos optativos acordados con el empleado (seguros, sindicato, etc.) |

### Crear una deducción

1. Ir a **Nóminas → Deducciones**
2. Clic en **Nueva deducción**
3. Completar:
   - **Nombre** y **Código** (único, ej: `IPS001`)
   - **Tipo** de deducción
   - **Tipo de cálculo:** Monto Fijo (Gs.) o Porcentaje del salario
   - **Monto** o **porcentaje**
   - **Deducción Obligatoria:** si está activo, se asigna automáticamente a todos los empleados
   - **Aplicar tope legal Art. 245 CLT** (solo visible en tipo Judicial): limita el embargo al 25% del salario sobre el mínimo legal; desactivar para prestaciones alimentarias
4. Guardar

---

## Asignar a un empleado

### Asignación individual

Desde el perfil del empleado:

1. Ir a **Empleados** y abrir el empleado
2. Ir a la pestaña **Percepciones** o **Deducciones**
3. Clic en **Asignar percepción / Asignar deducción**
4. Seleccionar el concepto
5. Definir:
   - **Fecha de inicio** (obligatoria)
   - **Fecha de fin** (opcional — dejar en blanco si es indefinida)
   - **Monto personalizado** (opcional — sobreescribe el monto global del concepto)
6. Guardar

### Asignación masiva

Desde el detalle de la percepción o deducción:

1. Clic en **Asignar a Todos**
2. Filtrar opcionalmente por empresa y/o sucursal
3. Confirmar

El sistema asigna el concepto a todos los empleados activos que aún no lo tengan. Si ya existe una asignación activa para ese empleado, se omite sin modificarla. La notificación final indica cuántos empleados fueron asignados y cuántos ya lo tenían.

### Remover masivamente

Desde el detalle de la percepción o deducción:

1. Clic en **Remover de Todos**
2. Filtrar opcionalmente por empresa y/o sucursal
3. Confirmar

El sistema marca la fecha de fin de todas las asignaciones activas con la fecha de hoy. Los registros históricos se conservan.

---

## Deducciones del sistema (no editar)

El sistema usa códigos reservados para descuentos generados automáticamente. No deben eliminarse ni modificarse:

| Código | Uso |
|--------|-----|
| `IPS001` | Aporte IPS (9% del salario) |
| `PRE001` | Cuotas de préstamos |
| `ADE001` | Adelantos de salario |
| `MER001` | Cuotas de retiros de mercadería |
| `LIC001` | Descuento por permiso parcial |

---

## Filtros disponibles

**Percepciones:**

| Filtro | Opciones |
|--------|----------|
| Tipo de Percepción | Salarial / No Salarial / Otro |
| Tipo de Cálculo | Monto Fijo / Porcentaje |
| Afecta IPS | Todos / Sí / No |
| Estado | Todos / Activos / Inactivos |

**Deducciones:**

| Filtro | Opciones |
|--------|----------|
| Tipo de Deducción | Legal / Deuda / Judicial / Voluntaria |
| Tipo de Cálculo | Monto Fijo / Porcentaje |
| Obligatorio | Todos / Obligatorios / No Obligatorios |
| Estado | Todos / Activos / Inactivos |
