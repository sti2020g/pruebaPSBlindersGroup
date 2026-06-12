# productbadges — PrestaShop 1.7 Module

Módulo que soporta MultiStore para gestionar etiquetas visuales (badges) sobre las imágenes de productos en el catálogo: listados de categoría, resultados de búsqueda y ficha de producto. Configuración independiente por tienda.

## Instalación

1. Importa el zip del módulo desde el Module Manager de PS.
2. Ve a **Back office → Módulos → Gestor de módulos**.
3. Busca "Etiquetador MultiStore" y haz clic en **Configure** para parametrizar en qué store se verán las etiquetas (si solo hay una, todo se aplica a la misma), el número máximo de etiquetas y si quieres ocultar las que vienen por defecto en el tema actual.
4. Para añadir etiquetas de NUEVO, PROMOCIÓN, etc., ve a **Catálogo → Product Badges**.

## Desinstalación

Desinstala desde el gestor de módulos. El módulo elimina sus tablas (`product_badge`, `product_badge_lang`, `product_badge_product`), sus hooks y su pestaña de admin. No deja rastro.

## Versión probada

- PrestaShop **1.7.8.11**
- PHP **8.1**

## Uso

### Crear una badge

1. Back office → Catálogo → **Product Badges** → Añadir nuevo.
2. Rellena: texto (por idioma), color de fondo, color de texto, posición (esquina superior izquierda o derecha), orden numérico y estado activo.
3. Si hay multitienda activa, selecciona la tienda en el desplegable **Select Store** — la lista de productos se filtra por esa tienda.
4. Selecciona los productos en el multiselect (Ctrl/Cmd para selección múltiple). Filtra por nombre con el campo de texto encima de la lista.
5. Guarda. Las asignaciones se guardan por badge + tienda.

### Configuración por tienda

Back office → Módulos → configurar **Etiquetador MultiStore**:

- Si hay multitienda, el panel azul **Select Store** muestra un desplegable para elegir la tienda. Cada tienda tiene su configuración independiente.
- Campos configurables por tienda:
  - Activar/desactivar el módulo para esa tienda.
  - Mostrar en listados (categoría, búsqueda).
  - Mostrar en ficha de producto.
  - Número máximo de badges visibles por producto (0 = sin límite).
  - Ocultar badges del tema por defecto (`.product-flags`).
- Al pie del panel azul, la tabla **Saved settings per store** muestra el estado actual de todas las tiendas.
- El panel verde **Manage Badges** da acceso directo al gestor de badges.

## Decisiones técnicas

### ObjectModel + tablas propias

Se usan tres tablas:
- `product_badge` — datos principales (colores, posición, activo).
- `product_badge_lang` — texto de la badge por idioma (multilenguaje nativo de PS).
- `product_badge_product` — relación N:M badge ↔ producto, con columna `id_shop` para aislar asignaciones por tienda.

### Asignación de productos con multiselect filtrable

La asignación de productos se hace con un `<select multiple>` que lista los productos activos de la tienda seleccionada. Incluye un campo de texto para filtrar por nombre en tiempo real. Los productos mostrados se obtienen filtrando `product_lang` y `product_shop` por `id_shop`, evitando que aparezcan productos de otras tiendas.

### Configuración per-shop en `ps_configuration`

La configuración se escribe directamente en `ps_configuration` con `id_shop` explícito por tienda. Se usa `Db::executeS()` en lugar de `Db::getValue()` para todas las lecturas — `getValue()` devuelve datos obsoletos en esta versión de PS por un problema de caché interna de la clase `Db`. Las escrituras usan `Db::execute()` con SQL directo para evitar el doble-escape de `Db::insert()/update()`.

### Hooks utilizados

- `displayProductListItem` — badges en listados de categoría/búsqueda.
- `displayProductAdditionalInfo` — badges en ficha de producto (hook estándar PS 1.7).
- `displayHeader` — carga CSS/JS en frontend solo cuando el módulo está activo para la tienda actual.
- `actionAdminControllerSetMedia` — carga CSS/JS de admin.

**Nota sobre temas:** `displayProductListItem` y `displayProductAdditionalInfo` son los hooks estándar de PrestaShop 1.7. Si el tema activo no llama a estos hooks (temas muy personalizados o legacy), las badges no se mostrarán sin modificar el tema. Esta es la limitación esperada del sistema de hooks de PS.

### Sanitización y escapado

- Colores validados con regex `/^#[0-9A-Fa-f]{6}$/` antes de guardar y antes de renderizar.
- Posición validada contra whitelist `['top-left', 'top-right']`.
- Etiquetas validadas con `Validate::isGenericName()` de PrestaShop.
- IDs de producto casteados a `int` y verificados contra la BD antes de insertar.
- En plantillas Smarty: `|escape:'html':'UTF-8'` en todos los valores dinámicos.
- En JS admin: regex de validación hex antes de aplicar colores a CSS (evita inyección CSS).

### Multitienda

Cada tienda tiene su propio conjunto de filas en `ps_configuration` (`id_shop_group=0, id_shop=N`). La lectura sigue una cascada: primero busca fila específica de tienda, luego fila global, luego cualquier valor existente. Los hooks se registran para todas las tiendas activas en la instalación. Las asignaciones badge→producto son independientes por tienda gracias al campo `id_shop` en `product_badge_product`.

## Testing

Probado funcionalmente sobre una instalación real de **PrestaShop 1.7.8.11 en modo multitienda** (2 tiendas activas). Se verificaron:

- Guardado y lectura de configuración independiente por tienda.
- Asignación de productos filtrada por tienda — cada tienda ve solo sus productos.
- Visualización de badges en front por tienda según config activa.
- Comportamiento correcto al activar/desactivar por tienda.

## Qué dejé fuera y por qué

| Feature | Motivo |
|---|---|
| Soporte de imágenes en badges (no solo texto) | No requerido en el enunciado |

## Asunciones

- El tema activo incluye los hooks `displayProductListItem` y `displayProductAdditionalInfo`. Los temas por defecto de PS 1.7 (Classic) los soportan.
- El número máximo de badges (0-20) en configuración es suficiente para cualquier caso de uso real.
- En instalaciones sin multitienda activa, la configuración se guarda como global y no aparece el selector de tienda.
