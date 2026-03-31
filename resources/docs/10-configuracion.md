# Configuración del Sistema

Esta sección cubre los ajustes globales que afectan el comportamiento de todos los módulos.

---

## Configuración General

Acceder desde **Configuración → Configuración General**.

### Información de la empresa

- **Nombre:** aparece en encabezados de PDFs
- **Logo:** imagen que encabeza los documentos generados
- **RUC, dirección, teléfono, email:** datos de contacto institucional

### Configuración laboral

- **Zona horaria:** por defecto `America/Asuncion`
- **Horas laborales por semana:** referencia para cálculos de horas extra

### Configuración de préstamos

- **Monto máximo de préstamo:** límite por empleado
- **Monto máximo de adelanto:** límite de adelanto de salario

### Configuración de contratos

- **Días de alerta antes de vencimiento:** el sistema notifica cuando un contrato determinado está por expirar

### Registro facial

- **Horas de validez del registro:** tiempo antes de que el registro facial expire y deba renovarse

---

## Configuración de Nómina

Acceder desde **Configuración → Configuración de Nómina**.

### Horas de trabajo — Jornada diurna

- **Horas mensuales:** base para calcular el valor-hora (ej: 200 horas)
- **Horas diarias:** horas de trabajo por día (ej: 8 horas)
- **Días por mes:** días laborales promedio por mes (ej: 25 días)

### Jornada nocturna y mixta

Parámetros equivalentes para turnos nocturnos y mixtos.

### Multiplicadores de horas extra

- **Hora extra diurna:** factor de recargo (ej: 1.5x)
- **Hora extra nocturna:** factor de recargo (ej: 2.0x)
- **Hora extra en feriado:** factor de recargo (ej: 3.0x)

### Liquidación / Finiquito

- **Tasa IPS empleador y empleado:** porcentajes aplicados al calcular aportes
- **Días de indemnización por año:** base legal para el cálculo del finiquito

### Vacaciones

- Parámetros de acumulación y cálculo de días de vacaciones según antigüedad
- Configuración de días hábiles de la semana

---

## Usuarios del Sistema

Acceder desde **Configuración → Usuarios**.

### Crear un usuario

1. Clic en **Nuevo usuario**
2. Completar nombre, email y contraseña
3. Guardar

> Los usuarios pueden gestionar su perfil (nombre, email, contraseña, foto) desde el ícono de perfil en el panel.

---

## Feriados

Acceder desde **Configuración → Feriados**.

Los feriados se usan para:
- Excluir días del cálculo de vacaciones (días hábiles)
- Aplicar el multiplicador de horas extra en feriado
- Evitar contar ausencias en días que no son laborables

### Registrar un feriado

1. Clic en **Nuevo feriado**
2. Ingresar nombre y fecha
3. Indicar si es feriado nacional o solo de la empresa
4. Guardar

> Se recomienda cargar los feriados del año completo al inicio de cada año.
