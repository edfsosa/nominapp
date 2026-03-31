# Introducción al Sistema

Bienvenido al sistema de Gestión de Recursos Humanos. Esta guía explica cómo usar cada módulo del panel administrativo.

## ¿Qué puede hacer el sistema?

El sistema cubre el ciclo completo de gestión de empleados:

- **Organización:** estructura jerárquica de empresa, sucursales, departamentos y cargos
- **Empleados:** registro completo con contratos, documentos y legajo
- **Asistencias:** marcaciones por reconocimiento facial o terminal compartida
- **Nóminas:** liquidación mensual, percepciones, deducciones y recibos en PDF
- **Vacaciones y ausencias:** solicitudes, aprobaciones y saldo de días
- **Préstamos y adelantos:** gestión de cuotas y descuentos automáticos en nómina
- **Aguinaldo:** cálculo y emisión del 13.° salario
- **Liquidaciones:** finiquito al término de contratos
- **Configuración:** usuarios, feriados y parámetros del sistema

## Acceso al panel

El panel de administración está disponible en la URL principal del sistema. Ingrese con su usuario y contraseña. Según su rol, verá solo los módulos que tiene habilitados.

## Jerarquía de datos

Comprender la jerarquía facilita el uso del sistema:

```
Empresa
 └── Sucursal
      └── Empleado
           └── Contrato (salario, cargo, fecha inicio)

Empresa
 └── Departamento
      └── Cargo
```

> **Importante:** El salario activo, el cargo y la fecha de ingreso del empleado siempre se leen desde el **contrato activo**, no desde el perfil del empleado directamente.

## Flujo de trabajo típico

1. Crear la **empresa** y sus **sucursales**
2. Definir **departamentos** y **cargos**
3. Configurar **horarios** de trabajo
4. Registrar **empleados** y crear sus **contratos**
5. Asignar un **horario** a cada empleado
6. Registrar **feriados** del año
7. Habilitar **marcaciones** (terminal o facial)
8. Cada período: generar **nómina**, revisar y aprobar
9. Gestionar **vacaciones**, **ausencias** y **préstamos** según necesidad
10. Al cierre de año: emitir **aguinaldo**
