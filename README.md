# Sistema RRHH - Macro Alliance SRL

Sistema de gestión de recursos humanos para Macro Alliance SRL, desarrollado con Laravel y Filament.

## Características

- Gestión de empleados y departamentos
- Control de asistencia con reconocimiento facial
- Gestión de nóminas y períodos de pago
- Administración de vacaciones y ausencias
- Sistema de préstamos con cuotas
- Percepciones y deducciones personalizadas
- Generación de reportes en PDF y Excel
- Calendario de días feriados
- Gestión de horarios y turnos

## Requisitos

- PHP 8.2 o superior
- Composer
- Node.js y npm
- MySQL/PostgreSQL/SQLite
- Extensiones PHP: PDO, mbstring, OpenSSL, JSON, BCMath, Ctype, Fileinfo, Tokenizer

## Instalación

1. Clonar el repositorio:
```bash
git clone <url-del-repositorio>
cd rrhh-macro
```

2. Instalar dependencias de PHP:
```bash
composer install
```

3. Instalar dependencias de Node.js:
```bash
npm install
```

4. Configurar el archivo de entorno:
```bash
cp .env.example .env
```

5. Generar la clave de aplicación:
```bash
php artisan key:generate
```

6. Configurar la base de datos en el archivo `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario
DB_PASSWORD=contraseña
```

7. Ejecutar las migraciones:
```bash
php artisan migrate
```

8. Ejecutar los seeders (opcional, para datos de prueba):
```bash
php artisan db:seed
```

9. Compilar los assets:
```bash
npm run build
```

## Uso

Iniciar el servidor de desarrollo:
```bash
php artisan serve
```

La aplicación estará disponible en `http://localhost:8000`

## Desarrollo

Para desarrollo con recarga automática:
```bash
composer run dev
```

Este comando inicia simultáneamente el servidor, cola de trabajos y compilador de assets.

## Licencia

MIT
