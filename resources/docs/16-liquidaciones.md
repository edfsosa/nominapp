# Liquidaciones (Finiquito)

La liquidación es el pago final al empleado cuando termina la relación laboral. El monto y los conceptos incluidos dependen del **tipo de terminación**.

---

## Tipos de terminación y conceptos incluidos

| Tipo | Preaviso | Indemnización |
|------|----------|---------------|
| **Despido Injustificado** | Sí | Sí |
| **Despido Justificado** | No | No |
| **Renuncia Voluntaria** | No | No |
| **Mutuo Acuerdo** | No | No |
| **Fin de Contrato** | No | No |

Todos los tipos incluyen: salario pendiente proporcional, vacaciones no gozadas y aguinaldo proporcional al año.

---

## Estados de la liquidación

| Estado | Descripción |
|--------|-------------|
| **Borrador** | Creada, puede editarse y recalcularse |
| **Calculada** | Montos confirmados, pendiente de cierre |
| **Cerrada** | Procesada y definitiva — el empleado queda inactivo |

---

## Crear una liquidación

1. Ir a **Nóminas → Liquidaciones**
2. Clic en **Nueva liquidación**
3. Completar:
   - **Empleado**
   - **Fecha de terminación**
   - **Tipo de terminación**
   - **Motivo** (opcional)
4. Guardar — el sistema calcula automáticamente todos los conceptos

---

## Conceptos calculados automáticamente

**Haberes (ingresos):**
- Salario pendiente (días trabajados en el último mes sin nómina generada)
- Vacaciones no gozadas
- Preaviso (solo en Despido Injustificado)
- Indemnización (solo en Despido Injustificado)
- Aguinaldo proporcional al año en curso

**Deducciones:**
- IPS del período
- Saldo de préstamos activos pendientes
- Otras deducciones activas del empleado

---

## Cerrar la liquidación

Al ejecutar la acción **Cerrar Liquidación**, el sistema automáticamente:
- Marca el contrato activo del empleado como **Terminado**
- Actualiza el estado del empleado a **Inactivo**
- Cancela todos los préstamos activos pendientes

> No es necesario hacer estos cambios manualmente. El cierre es **irreversible**.

---

## Documentos de la liquidación

Desde la vista de la liquidación:

| Acción | Descripción |
|--------|-------------|
| **Ver en PDF** | Abre el recibo de finiquito en el navegador |
| **Descargar PDF** | Descarga el documento para imprimir y firmar |

---

## Parámetros de cálculo

Los parámetros de indemnización (días por año de servicio) y las tasas de IPS se configuran en **Configuración → Configuración de Nómina**, sección **Liquidación / Finiquito**.
