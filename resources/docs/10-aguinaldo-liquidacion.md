# Aguinaldo y Liquidaciones

## Aguinaldo (13.° salario)

El aguinaldo es el salario adicional obligatorio que corresponde a cada empleado al cierre del año, proporcional al tiempo trabajado y a los ingresos del período.

### Fórmula de cálculo

```
Aguinaldo = (Total ingresos del período ÷ 12) × Meses trabajados
```

El "total de ingresos" incluye el salario base, percepciones y horas extra de cada mes trabajado. Los "meses trabajados" pueden ser decimales si el empleado ingresó o egresó durante el año.

### Flujo del aguinaldo

```
Borrador → En Proceso → Cerrado
```

### Paso 1 — Crear el período de aguinaldo

1. Ir a **Nóminas → Períodos de Aguinaldo**
2. Clic en **Nuevo período**
3. Seleccionar la **empresa** y el **año**
4. Guardar

### Paso 2 — Generar los aguinaldos individuales

1. Ir a **Nóminas → Recibos Aguinaldo**
2. Clic en **Nuevo aguinaldo**
3. Seleccionar el período de aguinaldo
4. El sistema calcula un registro por cada empleado activo con contrato vigente durante el año
5. Revisar los montos calculados

### Paso 3 — Emitir recibos y registrar el pago

- Desde la lista de aguinaldos individuales, clic en **Ver recibo** para abrir el recibo en PDF
- Una vez pagado, marcar el aguinaldo como **Pagado**

### Estados del aguinaldo individual

| Estado | Descripción |
|--------|-------------|
| **Pendiente** | Calculado, aún sin pagar |
| **Pagado** | Pago registrado |

### Desglose mensual

Cada aguinaldo individual contiene un desglose mes a mes con:
- Salario base del mes
- Percepciones del mes
- Horas extra del mes
- Total del mes

---

## Liquidaciones (Finiquito)

La liquidación es el pago final al empleado cuando termina la relación laboral. El monto y los conceptos incluidos dependen del **tipo de terminación**.

### Tipos de terminación y conceptos incluidos

| Tipo | Preaviso | Indemnización |
|------|----------|---------------|
| **Despido Injustificado** | Sí | Sí |
| **Despido Justificado** | No | No |
| **Renuncia Voluntaria** | No | No |
| **Mutuo Acuerdo** | No | No |
| **Fin de Contrato** | No | No |

Todos los tipos incluyen: salario pendiente proporcional, vacaciones no gozadas y aguinaldo proporcional al año.

### Crear una liquidación

1. Ir a **Asistencias → Liquidaciones**
2. Clic en **Nueva liquidación**
3. Seleccionar el empleado y completar:
   - **Fecha de terminación**
   - **Tipo de terminación**
   - **Motivo** (opcional)
4. Guardar — el sistema calcula automáticamente todos los conceptos

### Conceptos calculados automáticamente

**Haberes (ingresos):**
- Salario pendiente (días trabajados en el último mes)
- Vacaciones no gozadas
- Preaviso (solo en despido injustificado)
- Indemnización (solo en despido injustificado)
- Aguinaldo proporcional al año

**Deducciones:**
- IPS del período
- Cuotas de préstamos pendientes
- Otras deducciones activas

### Estados de la liquidación

| Estado | Descripción |
|--------|-------------|
| **Borrador** | Creada, puede editarse |
| **Calculada** | Montos confirmados |
| **Cerrada** | Procesada y definitiva |

### Documentos de la liquidación

Desde la vista de la liquidación:
- **Ver en PDF:** abre el recibo de finiquito en el navegador
- **Descargar PDF:** descarga el documento para firmar

### Después de crear la liquidación

Recuerde **cambiar el estado del contrato** del empleado a "Terminado" desde su perfil, y actualizar el **estado del empleado** a "Inactivo".

### Parámetros de cálculo

Los parámetros de indemnización (días por año de servicio) y las tasas de IPS se configuran en **Configuración → Configuración de Nómina**, sección **Liquidación / Finiquito**.
