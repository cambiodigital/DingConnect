---

## 1. Panorama General

Hay **13 archivos** de log (`debug.log` al `debug (12).log`) con un total de **~45,000+ entradas** desde el **29-Abr-2026** hasta hoy **11-May-2026**.

| Métrica                    | Cantidad                        |
| -------------------------- | ------------------------------- |
| Entradas totales (plugins) | ~45,107                         |
| **PHP Fatal Errors**       | **250** (críticos)              |
| **PHP Warnings**           | **5,406** (importantes)         |
| PHP Notices                | ~39,000+ (ruido - bajo impacto) |
| WordPress DB Errors        | 7 (en el último archivo)        |

---

## 2. Plugins Involucrados

| Plugin                                                       | Problema                                                     | Severidad   |
| ------------------------------------------------------------ | ------------------------------------------------------------ | ----------- |
| **Tropipay** (`tropipay`)                                    | Propiedades dinámicas obsoletas (PHP 8.2+), `session_start()` tras headers enviados | **ALTA**    |
| **WooCommerce Order Export** (`woocommerce-order-export`)    | Traducciones cargadas demasiado temprano                     | **BAJA**    |
| **Health Check** (`health-check`)                            | Traducciones cargadas demasiado temprano                     | **BAJA**    |
| **WPO PDF Invoices** (`woocommerce-pdf-invoices-packing-slips`) | Hooks obsoletos (ej: `wpo_wcpdf_invoice_title` → `wpo_wcpdf_document_title`) | **MEDIA**   |
| **Tasty Recipes Lite** (`tasty-recipes-lite`)                | `reset()` sobre objetos (obsoleto en PHP 8+)                 | **BAJA**    |
| **Dokan Pro** (`dokan-pro`)                                  | **Fatal**: Clase `BlockSupportIntegration` no encontrada     | **CRÍTICA** |
| **JetElements** (`jet-elements`)                             | **Fatal**: Archivo requerido `/inc/controls/query.php` no encontrado | **CRÍTICA** |
| **DingConnect Recargas** (`dingconnect-recargas`)            | **Fatal**: `array_key_exists()` recibe `null` en vez de array | **CRÍTICA** |
| **WooCommerce** (`woocommerce`)                              | **Fatal**: `needs_shipping()` sobre `null` en carrito        | **CRÍTICA** |
| **Facebook for WooCommerce** (`facebook-for-woocommerce`)    | Transient key, errores DB "Commands out of sync"             | **MEDIA**   |

---

## 3. Problemas CRÍTICOS (Fatal Errors) - Requieren Acción Inmediata

### a) **DingConnect Recargas** — `class-dc-admin.php:5018`
**242 ocurrencias** en `debug (10).log`.
```
PHP Fatal error: array_key_exists(): Argument #2 ($array) must be of type array, null given
```
**Causa:** En la línea 5018, la función `array_key_exists()` recibe `null` en vez de un array. O bien la variable nunca se inicializó o la consulta a la API de DingConnect devolvió `null`.
**Solución:** Agregar validación de tipo antes de llamar `array_key_exists()`:
```php
if (is_array($tu_array) && array_key_exists($key, $tu_array)) { ... }
```
O en el plugin, reportar al desarrollador que maneje el caso `null`.

### b) **Dokan Pro** — `Hooks.php:491`
```
PHP Fatal error: Class "WeDevs\DokanPro\VendorDiscount\BlockSupportIntegration" not found
```
**Causa:** Un archivo del módulo VendorDiscount no se está cargando, probablemente por una actualización incompleta o un conflicto de autoloader.
**Solución:** Reinstalar/actualizar Dokan Pro a la última versión compatible.

### c) **JetElements** — `jet-elementor-extension.php:89`
```
PHP Fatal error: Failed opening required '/inc/controls/query.php'
```
**Causa:** Ruta de archivo faltante. Posiblemente el plugin se actualizó y movió archivos, o la instalación está corrupta.
**Solución:** Reinstalar JetElements.

### d) **WooCommerce** — `class-wc-cart.php:1772`
**~200+ ocurrencias** en `debug (10).log`
```
PHP Fatal error: Call to a member function needs_shipping() on null
```
**Causa:** Un producto en el carrito es `null`. Ocurre cuando un producto se elimina del catálogo pero sigue en el carrito de un usuario, o un plugin de terceros corrompe el objeto producto.
**Solución:** 
1. Revisar productos que ya no existen pero tienen stock en carritos abandonados.
2. Identificar qué plugins modifican el objeto carrito (posiblemente DingConnect o Tropipay contribuyendo).

---

## 4. Problemas GRAVES (Warnings)

### a) **Tropipay** — `class-wc-tropipay.php:60`
```
PHP Warning: session_start(): Session cannot be started after headers have already been sent
```
**Causa:** `session_start()` se ejecuta después de que ya se envió output al navegador.
**Solución:** Reemplazar por:
```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```
Y mover la llamada a un hook temprano como `init`.

### b) **Tropipay** — Propiedades dinámicas (16 propiedades)
```
PHP Deprecated: Creation of dynamic property WC_Tropipay::$notify_url is deprecated
```
**Causa:** PHP 8.2+ ya no permite crear propiedades dinámicas sin declararlas.
**Solución:** Declarar todas las propiedades en la clase `WC_Tropipay`:
```php
class WC_Tropipay {
    public $notify_url;
    public $log;
    public $idLog;
    public $tropipayentorno;
    // ... etc
}
```

### c) **WPO PDF Invoices** — Hooks obsoletos
```
Deprecated: Hook wpo_wcpdf_invoice_title obsoleto desde 3.8.7. Usa wpo_wcpdf_document_title
```
**Solución:** Actualizar el plugin a la última versión (debería corregir los hooks internos). Si tienes código propio usando esos hooks, actualiza los nombres.

### d) **Facebook for WooCommerce** — "Commands out of sync" (errores DB)
```
WordPress database error Commands out of sync; you can't run this command now
```
**Causa:** Un query no se consumió completamente antes de ejecutar otro. Indica que hay un plugin con manejo incorrecto de conexiones MySQL (posiblemente Tropipay o DingConnect dejando queries abiertos).
**Solución:** Identificar qué plugin está dejando resultados MySQL sin consumir. El error aparece durante el shutdown de Facebook, pero el responsable es otro plugin.

---

## 5. Problemas de Ruido (Notices - Bajo Impacto)

### `_load_textdomain_just_in_time` — ~410+ ocurrencias
**Plugins:** `health-check` y `woocommerce-order-export`
**Causa:** Carga de traducciones antes del hook `init` (WordPress 6.7+ es más estricto).
**Solución:** En los plugins, reemplazar `load_plugin_textdomain()` directo por:
```php
add_action('init', function() {
    load_plugin_textdomain('health-check', false, ...);
});
```
Como son plugins de terceros, la solución es actualizarlos o reportar a sus autores. No bloquean nada, solo generan ruido en los logs.

---

## 6. Correcciones Prioritarias (Orden de Ejecución)

| Prioridad | Plugin                          | Acción                                                       |
| --------- | ------------------------------- | ------------------------------------------------------------ |
| **1**     | **DingConnect Recargas**        | Parchear línea 5018 con validación `is_array()` — **rompe funcionalidad** |
| **2**     | **WooCommerce**                 | Revisar productos huérfanos en carritos — **rompe checkout** |
| **3**     | **Dokan Pro**                   | Reinstalar/actualizar — **rompe funcionalidad**              |
| **4**     | **JetElements**                 | Reinstalar — **rompe funcionalidad**                         |
| **5**     | **Tropipay**                    | Declarar propiedades y corregir `session_start()` — **afecta pagos** |
| **6**     | **WPO PDF Invoices**            | Actualizar plugin — **deprecaciones**                        |
| **7**     | WPO, Health Check, Order Export | Actualizar plugins a última versión                          |
| **8**     | **Facebook for WooCommerce**    | Revisar driver MySQL tras corregir Tropipay/DingConnect      |

---

## 7. Estado del Log Actual

El archivo `debug (12).log` (hoy, 11-May-2026, ~2MB):
- Ya **no aparecen** los Fatal Errors de DingConnect, Dokan, JetElements ni WooCommerce
- **Principalmente ruido**: `_load_textdomain_just_in_time` (health-check, order-export) y hooks obsoletos de WPO PDF
- **Nuevo**: 7 errores de base de datos "Commands out of sync" relacionados con Facebook for WooCommerce y ActionScheduler

Esto sugiere que **algunos fatales fueron corregidos** o que el sitio dejó de recibir tráfico intenso. Sin embargo, el error de base de datos "Commands out of sync" es una señal de que **algo sigue corrompiendo las conexiones MySQL** (sospecha: Tropipay o DingConnect dejando resultados de query abiertos).

---

## 8. Recomendación Adicional

1. **Desactivar WP_DEBUG en producción** una vez corregidos los fatales, o al menos usar `WP_DEBUG_LOG` apuntando a una ruta fuera del webroot.
2. **Rotar logs automáticamente** — tienes 13 archivos de ~2MB cada uno. Implementa `define('WP_DEBUG_LOG', __DIR__ . '/../debug-' . date('Y-m-d') . '.log');` en `wp-config.php`.
3. **Evaluar Tropipay** — es el plugin con más entradas en el log. Si es un plugin personalizado, considera modernizarlo para PHP 8.2+.