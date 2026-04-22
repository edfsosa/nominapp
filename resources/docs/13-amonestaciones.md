# Amonestaciones

El módulo de Amonestaciones permite registrar formalmente las sanciones disciplinarias emitidas a los empleados. Cada amonestación queda documentada en el sistema y puede generarse en PDF para firmar.

## Tipos de amonestación

| Tipo | Descripción |
|------|-------------|
| **Verbal** | Llamado de atención oral, registrado de forma preventiva |
| **Escrita** | Sanción formal con documentación firmada |
| **Grave** | Falta grave con posibles consecuencias disciplinarias mayores |

## Motivos predefinidos

| Motivo | |
|--------|-|
| Tardanza reiterada | Uso indebido de recursos |
| Ausencia injustificada | Desobediencia a superiores |
| Incumplimiento de normas | Conflicto con compañeros |
| Conducta inapropiada | Bajo rendimiento |
| Negligencia en el trabajo | Otro |

## Registrar una amonestación

### Desde el listado general

1. Ir a **Empleados → Amonestaciones**
2. Clic en **Nueva Amonestación**
3. Completar:
   - **Empleado** (solo se muestran empleados activos)
   - **Tipo** (Verbal, Escrita o Grave)
   - **Motivo** (categoría predefinida)
   - **Descripción del hecho** (detalle libre)
   - **Observaciones** (opcional)
4. Guardar

> La fecha de emisión y el emisor se registran automáticamente con la fecha actual y el usuario logueado.

### Desde el perfil del empleado

1. Abrir el perfil del empleado
2. Ir a la pestaña **Amonestaciones**
3. Clic en **Nueva Amonestación**
4. Completar el formulario y guardar

## Editar una amonestación

Desde la vista de detalle, clic en **Editar**. En el formulario de edición están disponibles adicionalmente:

- **Fecha de emisión** — si se necesita corregir la fecha
- **Emitida por** — si se necesita cambiar el emisor
- **Documento Firmado** — para adjuntar el PDF escaneado con la firma del empleado

## Documento PDF

Cada amonestación puede generarse como documento formal en PDF:

- **Desde la tabla:** botón **PDF** en la fila del registro
- **Desde la vista de detalle:** botón **Descargar PDF** en el encabezado

El documento incluye los datos del empleado (nombre, CI, cargo, departamento), el tipo y motivo de la amonestación, la descripción del hecho, y espacio para firmas del representante de la empresa y el empleado.

## Subir el PDF firmado

Una vez que el empleado firme el documento impreso, se puede adjuntar el escaneado:

1. Abrir la amonestación y clic en **Editar**
2. Expandir la sección **Documento Firmado**
3. Subir el archivo PDF (máximo 5 MB)
4. Guardar

> El botón **Firmado** aparece en la tabla solo cuando hay un PDF adjunto, y permite descargarlo directamente.

## Filtros y búsqueda

El listado permite filtrar por:

- **Tipo** (Verbal, Escrita, Grave) — también mediante las pestañas superiores
- **Motivo**
- **Empleado**
- **Rango de fechas** de emisión

## Exportar a Excel

Desde el encabezado del listado, el botón **Exportar Excel** descarga un archivo con todas las amonestaciones registradas, incluyendo: Empleado, CI, Tipo, Motivo, Descripción, Fecha de emisión, Emitida por, Observaciones, Creado y Editado.
