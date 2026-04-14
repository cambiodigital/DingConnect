# AGENTS - Contexto rápido del workspace DingConnect

## Antes de proponer cambios

Lee en este orden:

1. `Documentación/CONTEXTO_IA.md`
2. `Documentación/BACKLOG_FUNCIONAL_TECNICO.md`
3. `Documentación/GUIA_TECNICA_DING_CONNECT.md`

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
