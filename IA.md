# Uso de IA en este proyecto

## 1. Herramientas utilizadas

| Herramienta | Versión / Modelo | Modo de uso | Aprox. % del trabajo |
|---|---|---|---|
| Claude Code CLI | claude-sonnet-4-6 (Sonnet 4.6) | Terminal integrado en VS Code | 85% |
| Ninguna | — | Revisión manual, correcciones | 15% |

## 2. Configuración del proyecto

### CLAUDE.md / AGENTS.md
No se creó CLAUDE.md específico para este proyecto. El contexto del proyecto se proporcionó directamente en el prompt inicial de la sesión con Claude Code.

### settings.json u otra configuración equivalente
Se usa la configuración por defecto de Claude Code. No se modificaron permisos ni modelo activo para este proyecto específico. El archivo de configuración de la sesión se encuentra en `.claude/`.

## 3. Skills personalizadas

Ninguna skill personalizada utilizada en este proyecto.

## 4. Slash commands personalizados

Ninguno. Se trabajó con el flujo estándar de Claude Code sin comandos custom.

## 5. Sub-agentes invocados

No se invocaron sub-agentes (Task tool o Plan Mode) para este proyecto. El módulo se construyó de forma lineal en una única sesión de Claude Code.

## 6. MCPs (Model Context Protocol)

| MCP | Para qué lo usaste | ¿Qué te aportó? |
|---|---|---|
| filesystem | Lectura y escritura de archivos del módulo | Navegación y escritura directa sin salir del contexto |
| Ningún MCP de documentación | — | Con más tiempo habría conectado un MCP de docs de PrestaShop para verificar las firmas exactas de hooks y métodos de ObjectModel en 1.7.8.x, reduciendo el riesgo de alucinaciones en APIs específicas |

## 7. Prompts importantes

### Prompt 1
- **Herramienta:** Claude Code CLI
- **Prompt:** El enunciado completo del ejercicio (estructura de módulo, requisitos funcionales, técnicos, estructura de repo)
- **Qué generó (resumen):** Plan de implementación y estructura de archivos completa
- **Qué hice con el output:** Acepté la estructura propuesta y pedí implementación incremental por archivos

### Prompt 2
- **Herramienta:** Claude Code CLI
- **Prompt:** Implementar `ProductBadge.php` como ObjectModel con multilang y relación N:M con productos
- **Qué generó (resumen):** Clase con `$definition`, métodos `getByProduct`, `getAssignedProducts`, `saveProductAssignments`
- **Qué hice con el output:** Revisé la validación de colores y añadí los métodos `validateColor` y `validatePosition` como estáticos explícitos tras detectar que la IA usaba `isColor` de PS que acepta formatos no hex estrictos

### Prompt 3
- **Herramienta:** Claude Code CLI
- **Prompt:** Implementar `productbadges.php` principal con hooks, install/uninstall y página de configuración con HelperForm
- **Qué generó (resumen):** Archivo completo con todos los métodos requeridos
- **Qué hice con el output:** Corregí el hook `displayProductCover` — la IA generó acceso a `$params['product']['id_product']` cuando la firma real en PS 1.7 usa `$params['product']['id']` en algunos contextos; añadí fallback con `Tools::getValue`

### Prompt 4
- **Herramienta:** Claude Code CLI
- **Prompt:** Implementar `AdminProductBadgesController` con HelperList, HelperForm, validación server-side y guardado de relación N:M
- **Qué generó (resumen):** Controlador completo con `renderForm`, `postProcess`, `validateAndSave`, `parseProductIds`
- **Qué hice con el output:** Refactoricé `postProcess` para separar la validación propia del `parent::postProcess()` — la IA los mezclaba de forma que el flujo de edición podía saltarse validaciones

### Prompt 5
- **Herramienta:** Claude Code CLI
- **Prompt:** Implementar plantilla Smarty `badges.tpl` y CSS para posicionamiento absoluto sobre imagen de producto
- **Qué generó (resumen):** Plantilla con `{foreach}` y CSS con `position: absolute`
- **Qué hice con el output:** Añadí `|escape:'html':'UTF-8'` explícito en todos los campos de salida — la IA solo lo puso en `label`, dejando `bg_color` y `text_color` sin escapar en el atributo `style`

### Prompt 6
- **Herramienta:** Claude Code CLI
- **Prompt:** JS de admin para preview en vivo del badge al cambiar colores
- **Qué generó (resumen):** Script jQuery con listener en inputs de color
- **Qué hice con el output:** Añadí validación regex hex antes de aplicar los valores como CSS — sin eso un input manipulado podía inyectar CSS arbitrario vía `background-color`

### Prompt 7
- **Herramienta:** Claude Code CLI
- **Prompt:** SQL de install/uninstall
- **Qué generó (resumen):** Tres tablas con `CREATE TABLE IF NOT EXISTS` y DROP correspondientes
- **Qué hice con el output:** Verifiqué que usaba `_DB_PREFIX_` y `_MYSQL_ENGINE_` correctamente. Cambié `utf8` por `utf8mb4` para soporte completo de caracteres

## 8. Errores de la IA que detecté

### Error 1 — Escapado incompleto en plantilla Smarty
- **Qué generó la IA (mal):** `style="background-color:{$badge.bg_color};color:{$badge.text_color};"` sin escape en los valores de color dentro del atributo `style`
- **Por qué estaba mal:** Aunque los colores están validados antes de renderizar, sin `|escape:'html':'UTF-8'` cualquier carácter especial (comillas, `<`, `>`) podría romper el HTML o permitir XSS si la validación fallara por algún motivo
- **Cómo lo corregiste:** Añadí `|escape:'html':'UTF-8'` a todos los valores dinámicos en la plantilla, incluidos `bg_color`, `text_color` y `position`

### Error 2 — CSS injection en JS de admin
- **Qué generó la IA (mal):** `$('#preview').css({'background-color': $('#bg_color').val()})` sin validar el valor del input
- **Por qué estaba mal:** Un usuario malintencionado con acceso al back office podría manipular el DOM e inyectar CSS arbitrario. Aunque el riesgo es bajo (requiere acceso admin), es una mala práctica
- **Cómo lo corregiste:** Añadí `var hexRe = /^#[0-9A-Fa-f]{6}$/; if (!hexRe.test(bg) || !hexRe.test(text)) return;` antes de aplicar los valores

### Error 3 — Firma de parámetro de hook incorrecta
- **Qué generó la IA (mal):** En `hookDisplayProductCover`, acceso a `$params['product']['id_product']`
- **Por qué estaba mal:** En PrestaShop 1.7, el hook `displayProductCover` pasa el objeto producto con clave `id`, no `id_product`. Hubiera generado badges nunca cargadas (id_product = null → 0 → sin resultados)
- **Cómo lo corregiste:** `$idProduct = isset($params['product']) ? (int) $params['product']['id'] : (int) Tools::getValue('id_product');` con fallback a la querystring

### Error 4 — `isColor` de PrestaShop acepta formatos no estrictos
- **Qué generó la IA (mal):** Usó `'validate' => 'isColor'` en `$definition` para los campos de color y lo consideró suficiente
- **Por qué estaba mal:** `Validate::isColor()` de PS acepta nombres CSS (`red`, `blue`) y formatos cortos (`#fff`). La plantilla Smarty aplica los colores directamente en `style=""`, por lo que necesitamos estrictamente `#RRGGBB`
- **Cómo lo corregiste:** Mantuve `isColor` en `$definition` para la validación de ObjectModel, pero añadí `ProductBadge::validateColor()` con regex estricta en `validateAndSave()` del controller y en `renderBadgesForProduct()` del módulo principal

### Error 5 — `postProcess` mezclaba flujo propio con `parent::postProcess()`
- **Qué generó la IA (mal):** Llamaba a `parent::postProcess()` y luego ejecutaba la lógica propia de guardado, sin control del flujo
- **Por qué estaba mal:** `parent::postProcess()` de `ModuleAdminController` ya ejecuta save/delete según los botones de submit. Mezclarlo con lógica propia duplica operaciones y puede saltar validaciones
- **Cómo lo corregiste:** `postProcess()` intercepta los submits propios con guard clauses y hace `return` antes de llegar al `parent::postProcess()`. Solo delega al padre para acciones estándar (toggle status, delete)

### Error 6 — Charset `utf8` en lugar de `utf8mb4`
- **Qué generó la IA (mal):** `DEFAULT CHARSET=utf8` en las tablas SQL
- **Por qué estaba mal:** MySQL `utf8` es en realidad `utf8mb3` y no soporta emojis ni algunos caracteres especiales. Para textos de badge en múltiples idiomas lo correcto es `utf8mb4`
- **Cómo lo corregiste:** Cambié a `DEFAULT CHARSET=utf8mb4` en los tres `CREATE TABLE`

## 9. Partes que NO usé IA

- **Revisión de todos los valores de escape en Smarty**: Revisé línea a línea la plantilla manualmente porque la IA sistemáticamente olvida escapar valores en atributos HTML que no sean el contenido principal.
- **Verificación del flujo de install/uninstall**: Tracé mentalmente el orden de ejecución (SQL → hooks → tab) para asegurar que una instalación fallida a mitad no deja estado inconsistente.
- **Decisión de usar textarea para IDs de producto**: La IA propuso un selector con autocomplete AJAX. Decidí simplificarlo a textarea por criterio propio — cumple los requisitos con menos complejidad y menos superficie de ataque.

## 10. Reflexión final

**¿Qué te ahorró la IA?**
Tiempo en scaffolding y boilerplate: estructura de directorios, SQL, HelperForm/HelperList, la plantilla base de ObjectModel. Sin IA esto habría costado 3-4 horas adicionales de consulta de docs y escritura mecánica.

**¿En qué te entorpeció o llevó por mal camino?**
La IA tiene una tendencia a generar código que parece correcto pero tiene bugs sutiles en las APIs específicas de PrestaShop (firmas de hooks, comportamiento de `isColor`, flujo de `postProcess` en ModuleAdminController). Si lo hubiera aceptado sin revisión, el módulo habría tenido bugs en producción que solo aparecen al probar con datos reales.

**¿Qué cambiarías si lo repitieras?**
Conectaría un MCP con la documentación de PrestaShop 1.7 para que la IA verificara las firmas exactas de hooks y métodos antes de generarlos. Reduciría significativamente los errores de tipo 3 y 4 (APIs incorrectas de PS).
