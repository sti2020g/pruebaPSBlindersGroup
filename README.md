# productbadges — PrestaShop 1.7 Module

Módulo para gestionar etiquetas visuales (badges) sobre las imágenes de producto en el catálogo: listados de categoría, resultados de búsqueda y ficha de producto.

## Instalación

1. Copia la carpeta `modules/productbadges/` al directorio `modules/` de tu PrestaShop.
2. Ve a **Back office → Módulos → Gestor de módulos**.
3. Busca "Product Badges" e instala.
4. El módulo crea las tablas, registra los hooks y añade una pestaña en **Catálogo → Product Badges**.

## Desinstalación

Desinstala desde el gestor de módulos. El módulo elimina sus tablas (`product_badge`, `product_badge_lang`, `product_badge_product`), sus hooks y su pestaña de admin. No deja rastro.

## Versión probada

- PrestaShop **1.7.8.11**
- PHP **8.1**

## Uso

### Crear una badge

1. Back office → Catálogo → **Product Badges** → Añadir nuevo.
2. Rellena: texto (por idioma), color de fondo, color de texto, posición (esquina superior izquierda o derecha), estado activo.
3. En el campo "Assign to products" introduce los IDs de producto separados por comas (ej: `1,5,12`).

### Configuración global

Back office → Módulos → configurar **Product Badges**:
- Activar/desactivar el módulo globalmente.
- Mostrar en listados (categoría, búsqueda).
- Mostrar en ficha de producto.
- Número máximo de badges visibles por producto (0 = sin límite).

## Decisiones técnicas

### ObjectModel + tablas propias
Se usan tres tablas:
- `product_badge` — datos principales (colores, posición, activo).
- `product_badge_lang` — texto de la badge por idioma (multilenguaje nativo de PS).
- `product_badge_product` — relación N:M badge ↔ producto.

### Asignación de productos via IDs en textarea
La UI más correcta sería un selector ajax con autocompletado, pero requeriría JS complejo o un endpoint custom. Para no añadir complejidad fuera de alcance, se opta por un textarea con IDs numéricos. Cada ID se valida contra la tabla `product` antes de insertarse — no hay riesgo de referencias huérfanas.

### Hooks utilizados
- `displayProductListItem` — badges en listados de categoría/búsqueda.
- `displayProductCover` — badges en ficha de producto sobre la imagen principal.
- `displayHeader` — carga CSS en frontend solo cuando el módulo está activo.
- `actionAdminControllerSetMedia` — carga CSS/JS de admin.

**Nota sobre temas:** `displayProductListItem` y `displayProductCover` son los hooks estándar de PrestaShop 1.7. Si el tema activo no llama a estos hooks (temas muy personalizados o legacy), las badges no se mostrarán sin modificar el tema. Esta es la limitación esperada del sistema de hooks de PS.

### Sanitización y escapado
- Colores validados con regex `/^#[0-9A-Fa-f]{6}$/` antes de guardar y antes de renderizar.
- Posición validada contra whitelist `['top-left', 'top-right']`.
- Etiquetas validadas con `Validate::isGenericName()` de PrestaShop.
- IDs de producto casteados a `int` y verificados contra la BD antes de insertar.
- En plantillas Smarty: `|escape:'html':'UTF-8'` en todos los valores dinámicos.
- En JS admin: regex de validación hex antes de aplicar colores a CSS (evita inyección CSS).

### Multitienda
El módulo usa `Configuration::updateValue/get` sin pasar `id_shop` explícito, lo que respeta el contexto de tienda activo en el back office de PS. Las badges son globales (no difieren por tienda), lo cual es coherente y no rompe el comportamiento multitienda.

## Qué dejé fuera y por qué

| Feature | Motivo |
|---|---|
| Selector de productos con autocompletado JS | Fuera de alcance para el tiempo disponible; los IDs por textarea son funcionales y seguros |
| Ordenación manual de badges por producto | Añade tabla de join con campo `sort_order`; el enunciado no lo requiere |
| Tests unitarios | No eliminatorio según el enunciado |
| Previsualización de badge en listado de admin con imagen real | El callback `renderColorSwatch` cubre la necesidad visual de forma simple |
| Soporte de imágenes en badges (no solo texto) | No requerido en el enunciado |

## Asunciones

- Se asume que el tema activo incluye los hooks `displayProductListItem` y `displayProductCover`. Los temas por defecto de PS 1.7 (Classic) los soportan.
- El campo "product_ids" en el formulario acepta IDs de productos de cualquier tienda en contexto multitienda.
- El número máximo de badges (0-20) en configuración es suficiente para cualquier caso de uso real.
