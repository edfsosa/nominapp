# Flujo de trabajo con Claude Code y GitHub

Guía para el equipo de desarrollo de **Nominapp** sobre cómo trabajar con Claude Code como asistente de desarrollo, integrando el proceso con GitHub para mantener un historial limpio y revisable.

---

## Índice

1. [¿Qué es Claude Code?](#qué-es-claude-code)
2. [Requisitos previos](#requisitos-previos)
3. [Iniciar una sesión](#iniciar-una-sesión)
4. [Flujo de trabajo estándar](#flujo-de-trabajo-estándar)
5. [Estrategia de ramas](#estrategia-de-ramas)
6. [Cómo formular pedidos efectivos](#cómo-formular-pedidos-efectivos)
7. [Revisión de cambios antes del merge](#revisión-de-cambios-antes-del-merge)
8. [Manejo de sesiones largas](#manejo-de-sesiones-largas)
9. [Situaciones comunes y cómo manejarlas](#situaciones-comunes-y-cómo-manejarlas)
10. [Buenas prácticas del equipo](#buenas-prácticas-del-equipo)
11. [Preguntas frecuentes](#preguntas-frecuentes)

---

## ¿Qué es Claude Code?

Claude Code es un asistente de desarrollo de IA de Anthropic que opera directamente sobre el repositorio. A diferencia de un chatbot genérico, Claude Code:

- **Lee y edita archivos reales** del proyecto
- **Ejecuta comandos** (tests, linters, git)
- **Crea commits y pull requests** en GitHub
- **Entiende el contexto del proyecto** leyendo `CLAUDE.md`, la estructura de archivos, y las convenciones existentes

En este proyecto, Claude Code se usa como **co-desarrollador**: el equipo describe qué quiere construir o corregir, y Claude lo implementa siguiendo las convenciones de `CLAUDE.md`. El equipo revisa y aprueba antes de mergear.

---

## Requisitos previos

- Acceso al repositorio `edfsosa/nominapp` en GitHub
- Acceso a [claude.ai/code](https://claude.ai/code) o la sesión web de Claude Code configurada por el admin
- Familiaridad básica con pull requests en GitHub (para la etapa de revisión)

No es necesario tener el proyecto corriendo localmente para colaborar vía Claude Code.

---

## Iniciar una sesión

### Desde la web (claude.ai/code)

1. Ir a [claude.ai/code](https://claude.ai/code)
2. Seleccionar el repositorio `edfsosa/nominapp`
3. Claude clona el repo fresco en un entorno remoto aislado
4. Ya se puede empezar a describir tareas

### Cuándo empezar sesión nueva vs. continuar una existente

| Situación | Recomendación |
|-----------|---------------|
| La sesión actual tiene muchos mensajes (más de ~30–40 intercambios) | Nueva sesión |
| El contexto fue resumido automáticamente varias veces | Nueva sesión |
| Se está trabajando en una tarea completamente nueva y diferente | Nueva sesión |
| La tarea actual está en medio y tiene contexto importante | Continuar |
| La sesión acabó de empezar | Continuar |

> **Por qué importa:** Claude Code tiene una ventana de contexto. A medida que la sesión crece, los mensajes más antiguos se comprimen automáticamente. Una sesión nueva parte "fresco" y lee todo el estado actual del código desde `main`, lo que suele dar mejores resultados.

### Primera acción en cada sesión

Siempre verificar en qué rama y estado está el repo antes de pedir cambios:

```
¿En qué rama estamos y está todo al día con main?
```

Claude responderá con el estado actual. Si hay cambios sin commitear de sesiones anteriores, resolverlos antes de continuar.

---

## Flujo de trabajo estándar

El ciclo completo de una tarea sigue estos pasos:

```
Describir tarea → Claude implementa en rama feature → PR en GitHub → Revisar → Merge → Eliminar rama
```

### Paso a paso

**1. Describir la tarea**

Escribir en el chat qué se necesita. Ver la sección [Cómo formular pedidos efectivos](#cómo-formular-pedidos-efectivos) para ejemplos.

**2. Claude crea una rama y trabaja**

Claude automáticamente:
- Crea una rama con nombre `claude/descripcion-timestamp` (ej: `claude/schedule-template-vars-1781536146`)
- Lee los archivos relevantes
- Implementa los cambios siguiendo las convenciones de `CLAUDE.md`
- Corre el linter (`./vendor/bin/pint --dirty`)
- Hace commit con mensaje descriptivo
- Pushea la rama

**3. Claude crea el Pull Request**

Una vez terminada la implementación, Claude crea el PR en GitHub con:
- Título descriptivo
- Resumen de los cambios
- Lista de puntos a testear manualmente

**4. Revisar el PR**

El equipo revisa el PR en GitHub antes de mergear. Ver [Revisión de cambios antes del merge](#revisión-de-cambios-antes-del-merge).

**5. Merge y limpieza**

Claude puede hacer el merge directamente si el equipo lo aprueba, o el equipo lo hace desde GitHub. Siempre se usa **squash merge** para mantener el historial de `main` limpio (un commit por feature).

**6. Eliminar la rama**

Después del merge, eliminar la rama desde GitHub:
- `github.com/edfsosa/nominapp/branches` → ícono de papelera en la rama mergeada

> **Nota:** Claude Code no tiene permisos para eliminar ramas remotas desde el entorno remoto. Siempre hacerlo manualmente desde GitHub.

---

## Estrategia de ramas

### Convención de nombres

Todas las ramas creadas por Claude siguen el patrón:

```
claude/<descripcion-corta>-<timestamp>
```

Ejemplos reales:
- `claude/absence-attendance-flows-oh3axu`
- `claude/schedule-template-vars-1781536146`
- `claude/horario-semanal-var-1781538077`

El timestamp garantiza unicidad incluso si se trabaja en tareas similares en sesiones distintas.

### Reglas

- **Nunca trabajar directo en `main`** — siempre en una rama `claude/...`
- **Una rama por tarea o grupo de tareas relacionadas** — si una tarea es pequeña y relacionada con la anterior, se puede incluir en la misma rama
- **Mergear y eliminar antes de empezar la siguiente tarea grande** — evita que se acumulen ramas abiertas

---

## Cómo formular pedidos efectivos

La calidad del resultado depende en gran parte de cómo se describe la tarea. Estas son las técnicas que funcionan mejor en este proyecto.

### Ser específico sobre QUÉ, no sobre CÓMO

Claude conoce la arquitectura del proyecto. No hace falta explicar cómo implementarlo, solo qué se quiere lograr.

```
# ❌ Demasiado técnico / prescriptivo
Agrega un campo 'phone' al modelo Employee y créa una migración ALTER TABLE
employees ADD COLUMN phone VARCHAR(10), y actualiza el formulario de creación.

# ✅ Descriptivo del objetivo
Necesito poder registrar el teléfono del empleado. Es un campo opcional.
```

### Pedir preguntas antes de implementar

Para tareas con ambigüedad, agregar "hazme preguntas si tienes dudas" al final del pedido. Esto evita que Claude asuma algo incorrecto y haya que revertir.

```
Quiero agregar variables de horario del empleado a las plantillas de contrato.
Hazme preguntas si tienes dudas.
```

Claude preguntará lo que necesita saber antes de tocar código.

### Dar contexto de negocio

Explicar el "por qué" ayuda a Claude a tomar mejores decisiones de diseño.

```
# Sin contexto
Agrega el campo 'tipo' al select de deducciones.

# Con contexto
En el módulo de deducciones, el campo Tipo solo tiene opciones para
deducciones legales y judiciales. Necesitamos agregar "Otros" para que
el usuario pueda ingresar manualmente el nombre del concepto cuando no
encaja en las categorías existentes.
```

### Aprobar recomendaciones

Cuando Claude hace una pregunta con opciones y hay una recomendada, simplemente responder "ok" o el número/nombre de la opción. No hace falta repetir el contexto.

```
Claude: "¿Querés mostrar INDEFINIDO o dejar el campo vacío cuando el contrato
        no tiene fecha de fin?"

Equipo: "ok" (acepta la recomendación de Claude)
```

### Ejemplos de pedidos bien formulados

| Pedido | Por qué funciona |
|--------|-----------------|
| "En el apartado de crear deducciones, agregar una opción 'Otros' en el select de Tipo. Hazme preguntas si tienes dudas." | Específico, con contexto de dónde, y pide clarificación proactiva |
| "La clave `{domicilio_empleado}` en las plantillas de contrato no está funcionando, ¿podés revisarlo?" | Describe el síntoma, no la causa — Claude investiga |
| "Necesito variables de horario del empleado en los contratos: hora entrada, hora salida, días laborales, tiempo de descanso, horario de descanso. Las 5." | Lista clara y concisa de lo que se necesita |
| "¿Podés hacer el PR y merge?" | Delegar una acción concreta post-implementación |

---

## Revisión de cambios antes del merge

**Nunca mergear sin revisar el diff.** Claude es muy bueno pero puede cometer errores, especialmente en casos de borde o cuando hay ambigüedad en el pedido.

### Qué revisar en el PR

1. **El diff en GitHub** — leer qué archivos cambió y qué cambió en cada uno
2. **Los archivos de migración** — verificar que las columnas, tipos y constraints sean correctos
3. **El test plan del PR** — Claude siempre incluye una checklist de puntos a verificar manualmente
4. **Los casos de borde** — ej: ¿qué pasa si el campo es null? ¿qué pasa con registros existentes?

### Señales de alerta en un PR

- Cambia muchos más archivos de los esperados
- Modifica `CLAUDE.md`, `.env`, o archivos de configuración críticos sin haberlo pedido
- Tiene migraciones que eliminan o renombran columnas existentes
- El mensaje de commit es muy genérico ("Update files" en lugar de algo descriptivo)

### Si algo está mal

Simplemente describir el problema en el chat:

```
En el PR, el campo 'tipo' del formulario debería ser obligatorio pero lo
dejaste como opcional. ¿Podés corregirlo?
```

Claude hará un nuevo commit a la misma rama. No hace falta cerrar el PR ni crear uno nuevo.

---

## Manejo de sesiones largas

### Compresión automática de contexto

Cuando una sesión crece mucho, Claude comprime automáticamente los mensajes más viejos en un resumen. Esto es normal y no implica pérdida de trabajo — el código ya está commiteado en la rama. Sin embargo, puede afectar la coherencia si Claude "olvida" detalles del principio de la sesión.

### Señales de que conviene una sesión nueva

- Las respuestas de Claude empiezan a ser menos precisas o repite preguntas ya contestadas
- La sesión tiene más de 40–50 intercambios
- Se terminó una tarea grande y se va a empezar algo completamente distinto
- Claude menciona que el contexto fue resumido varias veces

### Cómo terminar una sesión limpiamente

Antes de cerrar:

1. Verificar que todos los cambios están commiteados y pusheados
2. Verificar que el PR fue creado (si aplica)
3. Opcionalmente, pedir un resumen de lo hecho:

```
¿Podés hacer un resumen de todo lo que trabajamos hoy para que lo tenga
como referencia?
```

### Al iniciar una sesión nueva después de una anterior

La sesión nueva no tiene memoria de la sesión anterior. El código en `main` es la fuente de verdad. Si hay contexto importante que Claude necesita saber (decisiones de diseño tomadas, restricciones específicas), mencionarlas al inicio:

```
Continuamos trabajando en el módulo de contratos. Ya implementamos las
variables de horario la sesión pasada. Ahora necesitamos [nueva tarea].
```

---

## Situaciones comunes y cómo manejarlas

### Claude implementó algo diferente a lo pedido

No revertir manualmente. Describir exactamente qué está mal:

```
El comportamiento que implementaste no es lo que necesito. Cuando el tipo
es "Otros", el campo Nombre debería seguir siendo obligatorio. Actualmente
lo dejaste como opcional. ¿Podés corregirlo?
```

### Hay un error en producción y hay que arreglarlo rápido

Si se puede trabajar con Claude: describir el error exacto (incluyendo el stack trace si hay uno) y pedir el fix. Claude leerá los archivos relevantes e investigará la causa.

Si el fix es urgente y no hay tiempo para el flujo normal: hacer el fix directo en `main` sin Claude, luego en la próxima sesión pedirle a Claude que revise el cambio y lo documente si hace falta.

### Claude dice que no puede hacer algo

Claude a veces declina tareas que percibe como riesgosas (ej: `git reset --hard`, eliminar ramas remotas, modificar CI/CD). En esos casos:
- Si es legítimo: hacerlo manualmente
- Si es un malentendido: aclarar el contexto ("esto es un entorno de desarrollo, no producción")

### El PR tiene conflictos con main

Si `main` recibió cambios mientras la rama de Claude estaba abierta, puede haber conflictos. Pedirle a Claude que los resuelva:

```
El PR tiene conflictos con main. ¿Podés resolverlos?
```

Claude hará `git pull --rebase origin main` y resolverá los conflictos.

### Se quiere cambiar algo en una tarea que ya fue mergeada

Crear una nueva tarea en una nueva sesión (o la sesión actual si tiene espacio). No intentar reabrir el PR mergeado. El historial de `main` ya tiene el commit anterior y el nuevo commit simplemente lo corrige.

---

## Buenas prácticas del equipo

### Commits atómicos

Cada PR debe resolver **una sola cosa** o un grupo de cosas claramente relacionadas. Si durante el trabajo aparece algo secundario que también habría que cambiar, anotarlo para una sesión futura en lugar de mezclarlo en el mismo PR.

### Documentar decisiones de diseño en el pedido

Si hay una restricción de negocio o técnica que no es obvia, mencionarla en el pedido. Eso permite a Claude tomar decisiones correctas y también queda como contexto en el historial del chat.

### No modificar manualmente archivos que Claude está editando

Si Claude está trabajando en una sesión activa, no editar los mismos archivos desde otro editor. Los cambios pueden colisionar. Esperar a que Claude haga el commit antes de tocar esos archivos.

### Revisar `CLAUDE.md` periódicamente

`CLAUDE.md` es el archivo de instrucciones que Claude lee al inicio de cada sesión. Si el equipo descubre una convención nueva o una regla que Claude debería seguir siempre, agregarla ahí. Pedirle a Claude que lo actualice es la forma más fácil:

```
Podés agregar a CLAUDE.md la convención de que los PDFs siempre deben
abrirse en nueva pestaña con Content-Disposition: inline?
```

### Mantener `main` siempre deployable

Todos los merges van a `main` y `main` se deploya automáticamente. No mergear un PR si:
- Tiene migraciones que podrían romper datos existentes sin haberlas verificado
- Cambia configuración de producción sin haberla coordinado con el admin
- No pasó al menos una revisión visual del diff

---

## Preguntas frecuentes

**¿Claude puede acceder a datos reales de producción?**
No. Claude trabaja sobre el código del repositorio en un entorno aislado. No tiene acceso a la base de datos de producción ni a variables de entorno del servidor.

**¿Qué pasa si Claude crea un PR con un bug y ya fue mergeado?**
No hay forma de "deshacerlo" desde Claude Code. El flujo correcto es crear un nuevo PR con el fix. Por eso es importante revisar el diff antes de mergear.

**¿Se puede usar Claude Code para consultar cómo funciona algo del código?**
Sí. Claude puede leer el código y explicar cómo funciona cualquier módulo. No hace falta pedir siempre cambios — preguntas de exploración también son válidas.

**¿Cuánto del código de Nominapp fue escrito por Claude?**
La gran mayoría. Claude se usa como desarrollador principal bajo supervisión del equipo. Las decisiones de producto y arquitectura las toma el equipo; Claude las implementa.

**¿Claude recuerda lo de sesiones anteriores?**
No directamente. Cada sesión parte de cero. Lo que "recuerda" está en el código (la fuente de verdad) y en `CLAUDE.md`. Las decisiones importantes deben quedar en el código o en `CLAUDE.md`, no solo en el chat.

**¿Qué pasa si dos personas abren sesiones de Claude al mismo tiempo?**
Puede generar conflictos en el repositorio si ambas trabajan en los mismos archivos. Coordinar para no tener dos sesiones activas en paralelo modificando los mismos módulos.
