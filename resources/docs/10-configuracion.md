# Configuración del Sistema

Esta sección cubre los ajustes que afectan el comportamiento global del sistema.

---

## Configuración General

Acceder desde **Configuración → Configuración General**.

### Información de la empresa

Estos datos aparecen en los encabezados de todos los PDFs generados:

- **Nombre de la empresa**
- **Logo** (se sube desde aquí; formatos JPG, PNG, WEBP, SVG)
- **RUC, número patronal IPS**
- **Dirección, teléfono, email, ciudad**

> Esta configuración es independiente de los datos cargados en **Organización → Empresas**. Es la información que aparece en los documentos del sistema.

### Configuración laboral

- **Zona horaria:** por defecto `America/Asuncion`
- **Horas laborales por semana:** referencia para cálculos internos (default: 40)

### Configuración de préstamos

- **Monto máximo de préstamo:** límite por operación de préstamo

### Configuración de contratos

- **Días de alerta antes del vencimiento:** cantidad de días de anticipación con que el sistema notifica que un contrato a plazo está por expirar

### Registro facial

- **Horas de validez del token:** tiempo que tiene el empleado para completar la captura facial desde que se genera el enlace. Al vencer el token, debe generarse uno nuevo.

---

## Configuración de Nómina

Acceder desde **Configuración → Configuración de Nómina**.

### Horas de trabajo — Jornada Diurna

| Parámetro | Valor por defecto |
|-----------|-------------------|
| Horas mensuales | 240 |
| Horas diarias | 8 |
| Días por mes | 30 |

### Horas de trabajo — Jornada Nocturna

| Parámetro | Valor por defecto |
|-----------|-------------------|
| Horas mensuales | 210 |
| Horas diarias | 7 |

### Horas de trabajo — Jornada Mixta

Configurable según las necesidades de la empresa.

### Multiplicadores de horas extra

| Tipo | Multiplicador por defecto |
|------|--------------------------|
| Hora extra diurna | 1.5× |
| Hora extra nocturna | 1.67× |
| Hora extra en feriado | 2.0× |

Estos valores determinan el recargo pagado sobre el valor de la hora normal.

### Límites de horas extra

- **Máximo de horas extra diarias:** para detectar anomalías en el cálculo

### Liquidación / Finiquito

- **Tasa IPS empleado (%):** porcentaje de descuento del empleado
- **Código IPS:** código de la deducción IPS en el sistema
- **Días de indemnización por año de servicio:** base para el cálculo de indemnización en despido injustificado

### Vacaciones

- **Días mínimos consecutivos:** mínimo de días que debe tener una solicitud de vacaciones
- **Antigüedad mínima:** años de servicio requeridos para tener derecho a vacaciones
- **Días hábiles:** días de la semana que se cuentan como hábiles (típicamente lunes a viernes)

---

## Usuarios del Sistema

Acceder desde **Configuración → Usuarios**.

### Crear un usuario

1. Clic en **Nuevo usuario**
2. Ingresar nombre, email y contraseña
3. Guardar

Cada usuario puede gestionar su propio perfil (nombre, email, contraseña, foto) desde el menú de perfil en la esquina del panel.

---

## Feriados

Acceder desde **Configuración → Feriados**.

Los feriados se usan para:
- Excluir días no laborales del cálculo de días hábiles en vacaciones
- Aplicar el multiplicador de horas extra en feriado
- Evitar generar ausencias en días que corresponden a feriado

### Feriados nacionales de Paraguay (referencia)

| Fecha | Nombre |
|-------|--------|
| 01/01 | Año Nuevo |
| 03/01 | Día de San Blas |
| 02/25 | Día de la Identidad Paraguaya |
| 05/01 | Día del Trabajador |
| 06/12 | Día de la Paz del Chaco |
| 08/15 | Fundación de Asunción |
| 09/29 | Batalla de Boquerón |
| 10/12 | Día de la Raza |
| 11/01 | Día de Todos los Santos |
| 12/08 | Virgen de la Inmaculada Concepción |
| 12/25 | Navidad |
| Semana Santa | Jueves y Viernes Santos (fecha variable) |

### Cargar feriados

1. Clic en **Nuevo feriado**
2. Ingresar nombre y fecha
3. Guardar

> Se recomienda cargar los feriados del año completo al inicio de cada año.
