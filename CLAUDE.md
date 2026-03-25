# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Development (server + queue + vite)
composer run dev

# Run all tests
composer run test

# Run a single test
php artisan test --filter=TestName
php artisan pest tests/Feature/SomeTest.php

# Build frontend
npm run build

# Database
php artisan migrate
php artisan db:seed --class=ProductionSeeder   # new client setup (sets admin user)
```

## Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Admin panel:** Filament 3.3 (all CRUD at `/admin`)
- **Frontend:** Blade + Vite + Tailwind CSS v4 + Vue (only for attendance/facial pages)
- **Testing:** Pest
- **Queue/Cache/Session:** database driver
- **Locale:** Spanish (`es`), timezone `America/Asuncion`, currency Guaraní (Gs.)

## Architecture

### Hierarchy
Two separate axes that converge in `Contract`:
- **Physical/location:** `Company → Branch → Employee`
- **Organizational:** `Company → Department → Position` (`departments.company_id` FK — each company defines its own departments)

`Employee` has `branch_id` (company is derived via `employee→branch→company`). Active salary, position, and start date live in the active `Contract` — not on `Employee` directly. `employee.position_id` is a legacy field; always use `employee.activeContract.position`. Employee status flows through the contract lifecycle.

### Modules

| Module | Key classes |
|--------|------------|
| Payroll | `PayrollService`, `PayrollPeriod`, `Payroll`, `PayrollItem` |
| Perceptions | `PerceptionCalculator`, `EmployeePerception` |
| Deductions | `DeductionCalculator`, `EmployeeDeduction` |
| Loans & Advances | `LoanService`, `LoanInstallmentCalculator` |
| Vacations | `VacationService`, `VacationBalance` |
| Aguinaldo (13th) | `AguinaldoService`, `AguinaldoPeriod`, `AguinaldoItem` |
| Liquidación | `LiquidacionService`, `LiquidacionItem` |
| Attendance | `AttendanceDay`, `AttendanceEvent`, observers auto-calculate daily totals |
| Face Recognition | TensorFlow.js (128-element descriptors), `FaceEnrollment`, `FaceCaptureApp.js` |

### Service Layer
Business logic lives in `app/Services/`. Each domain has a `*Service` for orchestration and a `*Calculator` for isolated math. PDF generation is handled by dedicated generator classes in the same directory.

### Observers
`app/Observers/` — `AttendanceDayObserver` recalculates daily totals on event changes; `AttendanceEventObserver` validates timestamps; `EmployeeObserver` handles lifecycle hooks.

### Admin Panel (Filament)
- Resources: `app/Filament/Resources/` — one Resource per Model
- Pages: `app/Filament/Pages/` — `ManageGeneralSettings`, `ManagePayrollSettings`
- Widgets: `app/Filament/Widgets/` — dashboard stats, attendance today, expiring contracts

#### Convenciones de RelationManagers en EmployeeResource

`app/Filament/Resources/EmployeeResource/RelationManagers/` — cada RM sigue esta estructura:

**`form()`**
- Layout con `->columns(1)` en el root; cada grupo lógico es un `Section::make(...)->compact()->icon(...)->columns(N)`
- Secciones estándar en `ContractsRelationManager`:
  - *Contrato* (3 cols): `[type ── span 2 ──] [work_modality]` / `[start_date] [end_date*] [trial_days]`
  - *Remuneración* (2 cols): `[salary_type] [salary]`
  - *Cargo* (2 cols): `[department_id] [position_id]`
  - `Textarea notes` al root sin section, `->columnSpanFull()`
- Select encadenado `department_id → position_id`: department con `->live()->afterStateUpdated(fn(Set $set) => $set('position_id', null))`; position filtra opciones por `$get('department_id')`
- Ambos selects tienen `->createOptionForm()` + `->createOptionUsing()` para crear inline; el select de departamento en el form de posición va `->disabled()->dehydrated()`

**`table()`**
- Columnas: `type` (badge), `start_date` / `end_date` (toggleable hidden), `position.name`, `status` (badge)
- Acciones de fila:
  - `generate_pdf` → `->url(route(...))->openUrlInNewTab()` (nunca `->action()` para binarios)
  - `upload_signed` → acción con `->form([FileUpload...])` para subir PDF firmado
  - `download_signed` → `->action(fn() => response()->download(...))` — válido acá porque es un archivo ya en disco (no generado en tiempo real)
  - `ActionGroup` con `EditAction` (solo `status === active`) + `DeleteAction` (limpia `document_path` del disco antes)

**`headerActions()`**
- `CreateAction` con `->before()` para validar reglas de negocio (ej: no crear si ya hay contrato activo) — usar `$action->halt()` para cancelar con notificación de error
- `->mutateFormDataUsing()` para inyectar campos del sistema (`created_by_id`, limpiar `end_date` si indefinido)

#### Convenciones de Pages en Resources Filament

Cada Resource tiene sus Pages en `app/Filament/Resources/{Resource}Resource/Pages/`. Seguir estas convenciones:

**`ListRecords` (index)**
- `getHeaderActions()`: acción export Excel (con confirmación modal) **antes** de `CreateAction`
- El export usa `Action::make('export_excel')` con `->requiresConfirmation()`, dispara `Notification` de éxito y retorna `Excel::download(new FooExport(), 'foo_' . now()->format('Y_m_d_H_i_s') . '.xlsx')`
- Tabs opcionales (`getTabs()`) para filtrar por estado; usar caché en propiedad (`?array $fooCounts`) para evitar N+1

**`CreateRecord`**
- `mutateFormDataBeforeCreate()`: capitalizar `name` con `preg_replace_callback('/(?:^|\s)\S/u', fn($m) => mb_strtoupper($m[0], 'UTF-8'), $data['name'])`
- `getCreatedNotification()`: retornar `Notification::make()->success()->title('...')->body('...')` — **NO llamar `->send()`**, Filament lo llama automáticamente

**`EditRecord`**
- `getHeaderActions()`: `[ViewAction::make()->icon('heroicon-o-eye')->color('primary'), DeleteAction::make()->label('Eliminar')->icon('heroicon-o-trash')->color('danger')->modalHeading(...)->modalDescription(...)->modalSubmitActionLabel('Sí, eliminar')->successNotificationTitle(...)->successRedirectUrl(index)]`
- `mutateFormDataBeforeSave()`: misma capitalización que en Create
- `getRedirectUrl()`: redirigir a `view` del record
- `getSavedNotification()`: retornar `Notification::make()->success()->title('...')->body('...')` — **NO llamar `->send()`**, Filament lo llama automáticamente

**`ViewRecord`**
- `getHeaderActions()`: solo `EditAction::make()->icon('heroicon-o-pencil-square')`

**Acciones en tabla (`->actions([])`)**
- No agregar `ViewAction` ni `EditAction` en las filas de la tabla: el clic sobre el registro ya navega a `ViewRecord` por defecto, y el usuario accede a edición desde el `EditAction` en el encabezado de `ViewRecord`
- Solo agregar acciones de fila para operaciones específicas del dominio (ej: `view_map`, `download`, `approve`)

### Public Routes (no auth)
Kiosk/terminal marking and face enrollment run on public routes, served by their own JS entry points:
- `resources/js/attendances/mark.js` — facial clock-in
- `resources/js/attendances/terminal.js` — shared kiosk terminal
- `resources/js/enrollments/capture-face.js` — employee self-enrollment

### Status Enums
- Employee/Contract: `active`, `inactive`, `draft`, `suspended`
- Payroll: `draft` → `processing` → `approved` → `paid`
- Loans: `pending` → `active` → `paid` / `defaulted` / `cancelled`

### Key Config Files
- `config/payroll.php` — vacation tiers, payroll rules
- `config/attendance.php` — `ABSENCE_THRESHOLD_MINUTES`, face recognition settings
- `app/Settings/` — runtime settings via Filament Settings plugin (`GeneralSettings`, `PayrollSettings`)

### Convenciones de Validación de Campos

**Teléfonos Paraguay**
- Guardar **con `0` inicial**, sin prefijo `+595`, sin espacios ni guiones
- `->maxLength(10)->regex('/^0\d{8,9}$/')` — cubre móviles (`09XXXXXXXX`, 10 dígitos) y fijos (`021XXXXXX` / `0XXXXXXXX`, 9 dígitos)
- `->validationMessages(['regex' => 'Ingrese un número válido de Paraguay: móvil (09XXXXXXXX) o fijo (021XXXXXX / 0XXXXXXXX).'])`
- `->helperText('Número sin espacios ni guiones. Ej: 0981123456')`
- No usar `->prefix('+595')` ni `->minLength()`

**RUC**
- Formato: número base + guion + dígito verificador. Ej: `80012345-6` o `1234567-1`
- `->maxLength(20)->regex('/^\d{1,8}-\d$/')`

**CI (Cédula de Identidad)**
- Solo dígitos, sin puntos ni guiones, 1–8 dígitos
- `->integer()->minValue(1)->maxValue(99999999)`

**Número Patronal IPS**
- Solo dígitos, hasta 8 dígitos
- `->integer()->minValue(1)->maxValue(99999999)`

### Convenciones de Documentación

Todos los archivos del proyecto deben tener sus clases, métodos y propiedades documentados con **PHPDoc** (PHP) o **JSDoc** (JS). Esto aplica a:

- **PHP:** Resources, Pages, RelationManagers, Models, Services, Observers, Exports, Controllers, Rules, Settings, Providers, Commands
- **JS/Vue:** archivos en `resources/js/` (funciones, componentes, props, eventos)
- **Blade:** comentarios `{{-- Descripción de la sección --}}` en bloques relevantes
- **CSS:** comentarios de sección en archivos de estilos

**PHP — PHPDoc mínimo por tipo:**
```php
// Clase
/** Gestiona la exportación de sucursales a Excel. */
class BranchesExport { ... }

// Método (siempre con @param y @return)
/**
 * Retorna los encabezados de columna para el archivo Excel.
 *
 * @return array<int, string>
 */
public function headings(): array { ... }

/**
 * Capitaliza el nombre y limpia el teléfono antes de crear el registro.
 *
 * @param  array<string, mixed> $data
 * @return array<string, mixed>
 */
protected function mutateFormDataBeforeCreate(array $data): array { ... }

// Propiedad
/** @var int|null ID de la empresa para filtrar sucursales. */
protected ?int $companyId;
```

**JS — JSDoc mínimo:**
```js
/**
 * Inicializa el componente de captura facial.
 * @param {HTMLElement} container - Contenedor del video
 */
function initCapture(container) { ... }
```

No agregar comentarios redundantes que repitan lo que el nombre ya dice. El objetivo es explicar el **propósito** o comportamiento no obvio.

### Coordenadas GPS (sucursales)

- Almacenar como JSON `{lat, lng}` en columna `coordinates` con cast `'coordinates' => 'array'` en el modelo
- Usar el campo `Map` de `cheesegrits/filament-google-maps` (v4.0.2, compatible PHP 8.2)
- Configuración estándar del campo:
  ```php
  Map::make('coordinates')
      ->defaultLocation([-25.2867, -57.6478]) // Asunción, Paraguay
      ->draggable()->clickable()
      ->autocomplete('address')->autocompleteReverse(true)
      ->reverseGeocode(['address' => '%n %S', 'city' => '%L'])
      ->geolocate()->height('400px')
  ```
- El campo `city` debe ser `->readOnly()` — se completa automáticamente via `reverseGeocode`
- Para mostrar en Google Maps desde una action: `sprintf('https://www.google.com/maps?q=%s,%s', $record->coordinates['lat'], $record->coordinates['lng'])` con `->visible(fn($r) => isset($r->coordinates['lat'], $r->coordinates['lng']))`

### Colores semánticos en Filament

- Usar `'gray'` en lugar de `'secondary'` — `'secondary'` no es un color semántico válido en Filament 3
- Colores válidos: `'primary'`, `'success'`, `'warning'`, `'danger'`, `'info'`, `'gray'`
- También inválidos: `'pink'`, `'blue'`, `'red'`, `'green'` — solo los 6 de arriba

### Evitar hardcoding de labels, colores y opciones

Los labels, colores y opciones de campos enum/select deben centralizarse en el modelo correspondiente, nunca hardcodearse en Resources, Pages o RelationManagers. Esto facilita el mantenimiento: si se agrega un nuevo valor, se toca un solo lugar.

**Patrón en el modelo:**
```php
public static function getShiftTypeOptions(): array  // para Select en formularios (puede incluir descripción)
{
    return ['diurno' => 'Diurno (06:00 - 20:00)', ...];
}

public static function getShiftTypeLabels(): array   // para badges/columnas (label corto)
{
    return ['diurno' => 'Diurno', 'nocturno' => 'Nocturno', 'mixto' => 'Mixto'];
}

public static function getShiftTypeColors(): array   // para colores de badges
{
    return ['diurno' => 'success', 'nocturno' => 'info', 'mixto' => 'warning'];
}
```

**Uso en Resource/Page:**
```php
->formatStateUsing(fn($state) => Schedule::getShiftTypeLabels()[$state] ?? $state)
->color(fn($state) => Schedule::getShiftTypeColors()[$state] ?? 'gray')
```

### Descargas de archivos desde acciones Filament

Las acciones Filament corren via Livewire (AJAX) y **no pueden retornar respuestas binarias** desde `->action()` — causa `Malformed UTF-8` al serializar a JSON.

**Patrón correcto para PDFs y descargas:**
1. Crear una ruta autenticada en `routes/web.php` dentro del grupo `auth`
2. Crear (o agregar a) un controller en `app/Http/Controllers/`
3. El generator devuelve `response($pdf->output(), 200, ['Content-Type' => 'application/pdf', ...])`
4. La action usa `->url(fn() => route('foo.bar', $record))->openUrlInNewTab()`

**PDFs on-demand vs almacenados:**
- On-demand (ej. legajo): `response()` directo, sin `Storage::put()` — no hay necesidad de persistirlo
- Almacenados (ej. recibo de nómina): `Storage::disk('public')->put($fileName, $pdf->output())` y se guarda la ruta en BD para re-descargar después

**`inline` vs `attachment` en Content-Disposition:**
- Usar `inline` cuando la action tiene `->openUrlInNewTab()` — el navegador renderiza el PDF en la pestaña
- Usar `attachment` solo cuando se quiere forzar la descarga al disco (sin abrir en el navegador)
- Regla general del proyecto: **los PDFs se abren en nueva pestaña** (`inline`)

### Select dependiente (parent → child)

Patrón estándar para campos encadenados (ej. Departamento → Cargo):

```php
// 1. Parent primero, con ->live() y limpiar el hijo al cambiar
Select::make('department_id')
    ->live()
    ->afterStateUpdated(fn(Set $set) => $set('position_id', null))
    ...

// 2. Hijo filtra por el parent; si no hay parent seleccionado, muestra todos
Select::make('position_id')
    ->options(function (Get $get) {
        $deptId = $get('department_id');
        return $deptId
            ? Position::where('department_id', $deptId)->orderBy('name')->pluck('name', 'id')->toArray()
            : Position::getOptionsWithDepartment();
    })
    ...
```

### `createOptionForm` en Select

Permite crear registros inline desde un campo Select usando `->createOptionForm()` + `->createOptionUsing()`.

**Acceso al record padre en RelationManagers:** usar `$this->getOwnerRecord()` directamente en los closures (PHP enlaza `$this` automáticamente en closures de métodos de clase).

```php
->createOptionUsing(function (array $data) {
    return Department::create([
        'name'       => $data['name'],
        'company_id' => $this->getOwnerRecord()->branch?->company_id, // inyectado, no expuesto en el form
    ])->id;
})
```

**Regla `unique` cuando el campo de scope no está en el form** (ej. `company_id` no es un input visible):

```php
->unique(
    table: Department::class,
    column: 'name',
    modifyRuleUsing: fn($rule) => $rule->where('company_id', $this->getOwnerRecord()->branch?->company_id)
)
->validationMessages(['unique' => 'Ya existe un departamento con ese nombre en esta empresa.'])
```

**Mensajes de validación personalizados:** `->validationMessages(['rule' => 'mensaje'])` — la clave es el nombre de la regla Laravel (`unique`, `required`, `max`, `regex`, etc.).

### Campos virtuales en formularios de creación (`->dehydrated(false)`)

Para campos que no mapean a columnas del modelo pero disparan lógica post-creación (ej. contrato inicial, horario inicial), usar `->dehydrated(false)` para que Filament no intente guardarlos en la BD.

**Gotcha crítico:** `$this->form->getState()` **excluye** los campos con `->dehydrated(false)`. Para leerlos en `afterCreate()` o `afterSave()`, usar `$this->data` (propiedad Livewire con el estado raw completo del form):

```php
// ❌ No funciona para campos ->dehydrated(false)
$state = $this->form->getState();

// ✅ Correcto
$state = $this->data;
```

**Patrón completo para lógica post-creación con campos virtuales:**

```php
// En el form: prefijo para evitar colisiones con columnas reales
Select::make('initial_schedule_id')
    ->dehydrated(false)
    ->visibleOn('create')
    ...

// En afterCreate(): usar $this->data
protected function afterCreate(): void
{
    $state = $this->data;

    if (filled($state['initial_schedule_id'] ?? null)) {
        ScheduleAssignmentService::assign($this->record, Schedule::find($state['initial_schedule_id']), Carbon::today());
    }
}
```

**Convención de prefijos para campos virtuales:** usar `ic_` para "initial contract", `initial_` para otros — así es obvio que no son columnas reales y evitan colisiones con campos del modelo.

### Secciones opcionales en `CreateRecord`

Para secciones que solo tienen sentido en creación (ej. contrato inicial, horario inicial):
- `->visibleOn('create')` en la Section — desaparece en edit sin lógica extra
- `->collapsible()->collapsed()` — no abruma al usuario; solo la expande quien quiere usarla
- La lógica de creación va en `afterCreate()` con una condición mínima: si los campos clave están vacíos, no hacer nada (el usuario no expandió la sección)

```php
// Condición mínima — no crear si campos esenciales están vacíos
if (filled($state['ic_salary'] ?? null) && filled($state['ic_position_id'] ?? null)) {
    Contract::create([...]);
}
```

### Filament Forms — convenciones adicionales

**`->live()` en lugar de `->reactive()`**
`->reactive()` está deprecado en Filament 3. Siempre usar `->live()`.

**`Section::columns()` no acepta Closure**
El método `->columns()` en `Section` (form e infolist) solo acepta `int|array|null`. Para layouts condicionales por operación (create vs edit), la única alternativa sin duplicar campos es aceptar un layout fijo o usar `Grid` interno.

**Grid en Infolists: namespace diferente al de Forms**
En un `infolist()`, usar `Filament\Infolists\Components\Grid` — no `Filament\Forms\Components\Grid`. Convención: importar como alias para evitar conflictos:
```php
use Filament\Infolists\Components\Grid as InfoGrid;
```

**`modalSubmitActionLabel` obligatorio en acciones con confirmación**
Toda acción con `->requiresConfirmation()` — tanto en filas como en `BulkAction` — debe tener `->modalSubmitActionLabel('Sí, [verbo]')`. El botón genérico "OK" de Filament no es suficiente.

**BulkActions de cambio de estado deben filtrar antes de actualizar**
Nunca hacer `$records->each->update([...])` sin verificar el estado esperado. Siempre filtrar:
```php
->action(fn($records) => $records->each(
    fn($record) => $record->status === 'pending' && $record->update(['status' => 'approved'])
))
```
Para lógica más compleja, iterar con `foreach` y contar procesados/omitidos para reportar en la notificación.

### Iconos semánticos por entidad

Usar siempre estos iconos para representar cada entidad en columnas, badges, infolists y acciones:

| Entidad | Icono |
|---------|-------|
| Empresa | `heroicon-o-building-office-2` |
| Sucursal | `heroicon-o-building-storefront` |
| Departamento | `heroicon-o-building-library` |
| Cargo | `heroicon-o-briefcase` |

### Convenciones de PDFs (DomPDF / Blade)

Todos los PDFs en `resources/views/pdf/` siguen estas reglas de estilo base:

```css
@page {
    size: A4;
    margin: 0;          /* SIEMPRE margin: 0 en @page */
}

body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    line-height: 1.5;
    padding: 15mm 20mm; /* El margen real va en body padding */
}
```

**Regla crítica:** `@page { margin: 0 }` + `body { padding: 15mm 20mm }`. Nunca poner margen en `@page` y padding en `body` a la vez — DomPDF los suma y duplica el margen efectivo.

**Encabezado de empresa estándar** (centrado, mismo en todos los PDFs):
```css
.company-header { text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #000; }
.company-logo   { max-height: 40px; max-width: 120px; margin-bottom: 8px; }
.company-name   { font-size: 14px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
.company-info   { font-size: 9px; }
```

**Título del documento:**
```css
.title    { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; margin: 20px 0 5px 0; }
.subtitle { text-align: center; font-size: 10px; margin-bottom: 20px; }
```

**Sección:**
```css
.section-title { font-weight: bold; font-size: 10px; text-transform: uppercase; padding: 5px 0; margin-bottom: 8px; border-bottom: 1px solid #000; }
```

**Firmas:**
```css
.signature-section { margin-top: 50px; display: table; width: 100%; }
.signature-item    { display: table-cell; width: 50%; text-align: center; padding: 0 25px; }
.signature-line    { border-top: 1px solid #000; margin-bottom: 5px; padding-top: 5px; }
.signature-label   { font-size: 10px; font-weight: bold; }
.signature-sublabel{ font-size: 9px; }
```

**Footer:**
```css
.footer { margin-top: 40px; text-align: center; font-size: 8px; border-top: 1px solid #ccc; padding-top: 10px; }
```

**Otras reglas:**
- Sin colores de acento — documentos en blanco/negro/gris (`#000`, `#ccc`, `#f5f5f5`)
- Labels/opciones de enums (estado, tipo) siempre desde métodos del modelo (`Model::getStatusLabel()`) — nunca arrays hardcodeados en el blade
- Campos casteados como `datetime` ya son instancias Carbon — no usar `Carbon::parse()` sobre ellos
- Relaciones via `activeContract.position` — nunca `employee->position` (campo legacy)
- PDFs compactos (ej. asistencia diaria): pueden usar `padding: 12mm 15mm` y `font-size: 10px` como excepción justificada
- Pseudo-selector `:last-child` no funciona en DomPDF — usar clase explícita (`.metric-last`, etc.)

### Páginas de reporte con tabla agregada (custom Page + InteractsWithTable)

Para reportes que necesitan filtros prominentes + tabla con datos agregados (ej. `AttendanceReport`):

**Estructura:**
```php
class AttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.attendance-report';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->columns([...])
            ->filters([...], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->persistFiltersInSession();
    }

    private function buildQuery(): Builder { ... }
}
```

Vista mínima (`resources/views/filament/pages/attendance-report.blade.php`):
```blade
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
```

**`FiltersLayout::AboveContent`** — renderiza los filtros visibles sobre la tabla (sin drawer). Ideal para reportes donde el período/agrupación es el foco principal.

**Acceder a filtros activos desde header actions** (ej. para pasarlos a un export):
```php
private function resolveActiveFilters(): array
{
    $f = $this->tableFilters ?? [];
    return [
        $f['period']['from_date'] ?? null,           // Filter con form
        $f['company_id']['value'] ?? null,            // SelectFilter
    ];
}
```

### GROUP BY con MySQL ONLY_FULL_GROUP_BY

MySQL en modo estricto exige que **todas las columnas no agregadas del SELECT estén en el GROUP BY**, incluso si son funcionalmente dependientes de la PK.

```php
// ❌ Falla con: 'employees.first_name' isn't in GROUP BY
->groupBy('employees.id');

// ✅ Correcto — incluir todas las columnas no agregadas del SELECT
->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'employees.ci', 'employees.branch_id');
```

Las columnas calculadas por subquery en el SELECT (`DB::raw('(SELECT ...) AS alias')`) **no** necesitan ir en GROUP BY.

### Deployment a producción

**Servidor cliente:** `sedvouco@bh7104` — CentOS, cPanel, PHP 8.2 via `/opt/cpanel/ea-php82/root/usr/bin/php`, Node 16 (sin RAM suficiente para Vite build).

**Limitación Node 16:** Vite 6 + Rollup requieren Node ≥18 y ~512MB RAM para compilar. El servidor falla con `RangeError: WebAssembly.instantiate(): Out of memory`. **Solución permanente: buildear en local y subir assets via rsync.**

**Checklist de deployment:**
```bash
# En el servidor
php artisan down
git pull origin main
composer install --no-dev --optimize-autoloader

# En local (dev)
npm run build
rsync -avz --delete public/build/ sedvouco@bh7104:/ruta/nominapp/public/build/

# De vuelta en el servidor
# Si hay nuevas variables en .env, agregarlas antes de migrate
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan filament:optimize
php artisan queue:restart
php artisan up
```

**Variables de entorno requeridas en producción:**
- `GOOGLE_MAPS_API_KEY` — requerida por `cheesegrits/filament-google-maps` para el mapa de sucursales

**Scheduler configurado** (`crontab -l`):
```
* * * * * cd /ruta/nominapp && /opt/cpanel/ea-php82/root/usr/bin/php artisan schedule:run >> storage/logs/cron.log 2>&1
```
Tareas activas: `app:calculate-attendance` (23:00 diario), `attendance:check-missing` (cada 15min, 6am-8pm, lun-sáb), `face:expire-enrollments` (cada hora).

### Important Notes
- Monetary values use `decimal:2` cast
- `Employee::getAdvanceReferenceSalary()` does **not** yet include the weekly paid rest day for jornaleros — pending automatic calculation
- Mobile mode is for remote employees using their own device, **not** a shared kiosk
- Terminal/kiosk mode is a shared device per branch
