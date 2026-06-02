# Guía de Instalación — Nominapp

Guía para instalar Nominapp en un servidor con cPanel (PHP 8.2).

---

## Requisitos del servidor

| Requisito | Versión mínima |
|-----------|---------------|
| PHP | 8.2+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Composer | 2.x |
| Git | cualquier versión |

**Extensiones PHP requeridas:** BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, PDO_MySQL, Tokenizer, XML, GD, Zip

---

## 1. Clonar el repositorio

Desde el Terminal de cPanel, en la carpeta de tu dominio:

```bash
cd /home/tu_usuario
git clone https://github.com/edfsosa/nominapp.git nombre_carpeta
```

> En cPanel, la carpeta del proyecto **no debe estar dentro de `public_html`** — debe estar un nivel arriba.

---

## 2. Apuntar el dominio a `public/`

En cPanel → **Domains** (o **Subdomains**) → configurar el **Document Root** del dominio apuntando a:

```
/home/tu_usuario/nombre_carpeta/public
```

---

## 3. Instalar dependencias

```bash
cd /home/tu_usuario/nombre_carpeta
/opt/cpanel/ea-php82/root/usr/bin/php /opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader
```

---

## 4. Configurar el entorno

```bash
cp .env.example .env
```

Editá `.env` con los datos de tu servidor. Los campos obligatorios:

```env
APP_NAME="Nominapp"
APP_URL=https://tu-dominio.com

DB_HOST=localhost
DB_DATABASE=nombre_base_de_datos
DB_USERNAME=usuario_db
DB_PASSWORD=contraseña_db

GOOGLE_MAPS_API_KEY=tu_api_key   # Requerida — ver sección Google Maps API Key
```

---

## 5. Generar la clave de la aplicación

```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan key:generate
```

---

## 6. Ejecutar migraciones y datos iniciales

Podés pre-configurar las credenciales del administrador en `.env` antes de correr el seeder (opcional):

```env
ADMIN_NAME="Nombre Apellido"
ADMIN_EMAIL=admin@tu-empresa.com
ADMIN_PASSWORD=una_contraseña_segura
```

Si no las configurás, el seeder genera una contraseña aleatoria y la muestra en pantalla.

```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php82/root/usr/bin/php artisan db:seed --class=ProductionSeeder
```

**Guardá las credenciales que aparecen en pantalla** — las necesitás para el primer acceso. El seeder también muestra una lista de pasos de configuración pendientes (sucursales, horarios, feriados, etc.).

---

## 7. Configurar permisos de carpetas

```bash
chmod -R 775 storage bootstrap/cache
```

---

## 8. Configurar el cron job (scheduler)

En cPanel → **Cron Jobs** → agregar esta tarea con frecuencia **"Every Minute"**:

```
/opt/cpanel/ea-php82/root/usr/bin/php /home/tu_usuario/nombre_carpeta/artisan schedule:run >> /dev/null 2>&1
```

---

## 9. Optimizar para producción

```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize
/opt/cpanel/ea-php82/root/usr/bin/php artisan filament:optimize
```

---

## 10. (Opcional) Configurar deploy automático

Para que cada actualización de Nominapp se aplique automáticamente en tu servidor:

**En tu servidor**, agregá al `.env`:

```env
DEPLOY_TOKEN=un_token_secreto_largo_y_aleatorio
```

Podés generar el token con:

```bash
openssl rand -hex 32
```

**Comunicale a tu proveedor de Nominapp:**
- La URL de tu aplicación (ej. `https://tu-dominio.com`)
- El `DEPLOY_TOKEN` generado

El proveedor configurará el webhook para que las actualizaciones lleguen automáticamente.

---

## Acceso inicial

Una vez completados los pasos, accedé a:

```
https://tu-dominio.com/admin
```

Con las credenciales generadas por el `ProductionSeeder`.

---

## Google Maps API Key

El mapa de sucursales requiere una API Key de Google Maps.

1. Creá un proyecto en [Google Cloud Console](https://console.cloud.google.com/)
2. Habilitá la API **Maps JavaScript API**
3. Creá una clave y restringila al dominio de tu aplicación
4. Pegá la clave en `.env`:
   ```env
   GOOGLE_MAPS_API_KEY=tu_clave_aqui
   ```

> Sin esta clave el mapa de sucursales no carga, pero el resto del sistema funciona con normalidad.

---

## Solución de problemas frecuentes

**Página en blanco o error 500**
```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize:clear
cat storage/logs/laravel.log | tail -50
```

**Error de permisos**
```bash
chmod -R 775 storage bootstrap/cache
```

**Migraciones pendientes**
```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan migrate --force
```
