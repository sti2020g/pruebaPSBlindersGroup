# CLAUDE.md — productbadges

## Contexto del proyecto

Módulo PrestaShop 1.7 (`productbadges`) para gestionar badges visuales en productos del catálogo.

## Reglas de código

- PHP 7.4+ compatible (no usar named arguments ni match expressions sin fallback)
- Usar `Db::getInstance()` con `pSQL()` o `DbQuery` — nunca concatenación directa de strings en SQL
- Colores siempre validados con regex `/^#[0-9A-Fa-f]{6}$/` antes de persistir y antes de renderizar
- Posición siempre validada contra whitelist `['top-left', 'top-right']`
- En plantillas Smarty: `|escape:'html':'UTF-8'` en TODOS los valores dinámicos, incluyendo los usados en atributos `style`
- Sin `exit` ni `die` fuera del guard `if (!defined('_PS_VERSION_')) { exit; }`

## Arquitectura

```
productbadges.php          — módulo principal, hooks, config page
classes/ProductBadge.php   — ObjectModel, lógica de negocio, queries
controllers/admin/         — AdminProductBadgesController (CRUD back office)
views/templates/hook/      — plantillas Smarty para front
views/css/                 — CSS front (productbadges.css) y admin (productbadges-admin.css)
views/js/                  — JS admin (preview en vivo)
sql/                       — install.php y uninstall.php
translations/              — en.php, es.php
```

## Patrones a seguir

- Lógica de BO en `AdminProductBadgesController`, NO en `productbadges.php`
- `postProcess()` intercepta submits propios con guard + return antes de `parent::postProcess()`
- Assets CSS/JS solo se cargan donde se necesitan (front en `displayHeader`, admin en `actionAdminControllerSetMedia`)
- Validación server-side en `validateAndSave()` del controller — no fiarse de validación client-side

## Lo que NO hay que hacer

- No usar `$_POST` directamente — usar `Tools::getValue()`
- No concatenar vars en SQL — usar `(int)` cast o `pSQL()`
- No dejar tablas/hooks/tabs si el módulo falla a mitad de instalación (manejar errores en `install()`)
- No subir el archivo `config-PS-Blinders Group.png` al repo (contiene credenciales)
