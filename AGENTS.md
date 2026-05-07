# AGENTS - Contexto rápido del workspace DingConnect

## Antes de proponer cambios

Lee en este orden:

1. `Documentación/CONTEXTO_IA.md`
2. `Documentación/BACKLOG_FUNCIONAL_TECNICO.md`
3. `Documentación/GUIA_TECNICA_DING_CONNECT.md`

## Índice rápido (para IA)

- Índice por intención + fuentes oficiales verificadas: `Documentación/README.md`.
- Contrato REST real del plugin (fuente de verdad): `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-rest.php`.
- Cliente DingConnect (HTTP / normalización / errores): `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-api.php`.
- WooCommerce (carrito/checkout/dispatch post-pago): `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php`.
- Frontend shortcode público: `dingconnect-wp-plugin/dingconnect-recargas/assets/js/frontend.js`.
- Wizard v2 (sesiones/estado): `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-wizard.php`.

## Enfoque de implementación

- Base principal: plugin WordPress en `dingconnect-wp-plugin/dingconnect-recargas/`.
- Referencia histórica: `recargas.html` (prototipo legado).
- Catálogo de SKUs: `Products-with-sku.csv`.

## Decisión de arquitectura vigente

- No usar credenciales DingConnect en frontend público.
- Todas las llamadas productivas deben salir desde backend (plugin WordPress).
- Mantener `validate_only` activo por defecto hasta completar pruebas reales controladas.

## Prioridades técnicas actuales

1. Unificar contrato REST entre frontend y backend del plugin.
2. Normalizar respuesta de DingConnect (`Items`/`Result`) en backend.
3. Soportar `EstimatePrices` y `ListTransferRecords` en flujo operativo.

## Regla de documentación

Cada cambio funcional o técnico debe actualizar, como mínimo:

1. `Documentación/BACKLOG_FUNCIONAL_TECNICO.md`
2. El archivo técnico directamente afectado en `Documentación/`
3. Después de algún cambio se debe cambiar la versión del plugin en `dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php:6`.
