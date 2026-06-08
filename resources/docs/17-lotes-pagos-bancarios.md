# Lotes de Pagos Bancarios

El módulo de Lotes agrupa adelantos de salario en un único archivo TXT en formato Itaú para enviar al banco y registrar el resultado de la acreditación. Evita procesar cada adelanto por separado y mantiene trazabilidad completa del ciclo bancario.

---

## Requisitos previos

Para poder generar y descargar el archivo TXT, deben estar configurados:

- La **empresa** con una cuenta bancaria principal activa
- La cuenta bancaria con el **ID de Empresa** configurado
- Los empleados incluidos con **cuentas bancarias primarias activas**

---

## Estados de un lote

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Creado, aún no confirmado con el banco |
| **Confirmado** | Todos los adelantos fueron aceptados por el banco |
| **Parcialmente Confirmado** | El banco aceptó algunos y rechazó otros |
| **Cancelado** | Cancelado manualmente o porque todos los ítems fueron rechazados |

---

## Crear un lote

1. Ir a **Nóminas → Lotes Bancarios**
2. Clic en **Nuevo Lote**
3. Seleccionar la **empresa** — el sistema carga automáticamente los adelantos disponibles (estado Aprobado, método de pago Transferencia, sin lote asignado)
4. Seleccionar los adelantos a incluir en este lote
5. Definir la **fecha de acreditación** (por defecto: hoy)
6. Agregar **notas** si aplica
7. Guardar

---

## Descargar el archivo TXT para el banco

1. Abrir el lote desde **Nóminas → Lotes Bancarios**
2. Clic en **Descargar TXT**
3. El archivo generado está en formato Itaú — enviarlo al banco por el canal habitual

> Si algún empleado no tiene cuenta bancaria configurada, sus adelantos no se incluyen en el TXT y el sistema lo notifica.

---

## Confirmar el resultado del banco

Una vez que el banco procesa el archivo y devuelve el resultado:

1. Abrir el lote (debe estar en estado **Pendiente**)
2. Clic en **Confirmar Lote**
3. En el modal:
   - Adjuntar el **comprobante bancario** (PDF, JPG, PNG o WEBP, máx. 10 MB)
   - Si el banco rechazó algún adelanto, marcarlo como rechazado en la lista
   - Ingresar el motivo de rechazo si aplica
4. Confirmar

El sistema actualiza automáticamente:
- Adelantos aceptados → estado **Entregado (Acreditado)**
- Adelantos rechazados → vuelven a estado **Aprobado** con el motivo de rechazo registrado
- El lote pasa a **Confirmado**, **Parcialmente Confirmado** o **Cancelado** según el resultado

---

## Cancelar un lote

Solo disponible desde estado **Pendiente**:

1. Abrir el lote
2. Clic en **Cancelar Lote**
3. Confirmar

Al cancelar, todos los adelantos del lote vuelven a estado **Aprobado** y quedan disponibles para incluirse en un nuevo lote.

---

## Acciones disponibles según estado

| Estado | Acciones disponibles |
|--------|---------------------|
| **Pendiente** | Editar, Descargar TXT, Confirmar Lote, Cancelar |
| **Confirmado** | Ver comprobante bancario (solo lectura) |
| **Parcialmente Confirmado** | Ver comprobante bancario (solo lectura) |
| **Cancelado** | Solo lectura |

---

## Tabs del listado

| Tab | Descripción |
|-----|-------------|
| **Todos** | Todos los lotes |
| **Pendientes** | Esperando confirmación bancaria |
| **Confirmados** | Acreditación exitosa |
| **Parcialmente Confirmados** | Con algunos ítems rechazados |
| **Cancelados** | Cancelados o rechazados totalmente |

---

## Relación con los adelantos

Los adelantos incluidos en un lote muestran el lote al que pertenecen en su vista de detalle. Al cancelar o rechazar un lote, el adelanto queda libre para asignarse a un nuevo lote.

Para ver y gestionar los adelantos individualmente ir a **Nóminas → Adelantos** (ver capítulo **Adelantos**).
