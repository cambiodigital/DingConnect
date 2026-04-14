# Backlog funcional y técnico

## Propósito

Priorizar próximos proyectos y funcionalidades sobre la base actual del plugin WordPress de DingConnect.

## Prioridad P0 (crítico)

1. Unificar contrato entre frontend y REST del plugin.
   - Alinear namespace y parámetros (`dingconnect/v1`, `account_number`).
   - Definir shape de respuesta canónico para productos y transferencias.
2. Eliminar dependencia de credencial en frontend legado.
   - Mantener `recargas.html` solo como entorno de prototipo sin uso productivo.
3. Validar mapeo de respuesta real de DingConnect.
   - Confirmar si el payload usa `Items` o `Result` en cada endpoint y normalizar en backend.

## Prioridad P1 (alto)

1. Robustecer flujo de productos de rango.
   - Integrar `EstimatePrices` en plugin y frontend del plugin.
2. Soportar campos enriquecidos de producto.
   - `GetProductDescriptions`, vigencia, markdown y textos de beneficio.
3. Implementar consulta de estado final.
   - Integrar `ListTransferRecords` para timeout y reconciliación.
4. Configurar catálogo curado inicial para Colombia con SKUs verificados.
   - Fuente: `HALLAZGOS_2026-04-14_DING_SKUS.md` y `Products-with-sku.csv`.

## Prioridad P2 (medio)

1. Administración de bundles más completa.
   - Edición de bundles existentes.
   - Importación desde CSV filtrado.
2. Registro operativo.
   - Historial de operaciones en WordPress con búsqueda por referencia.
3. Endurecimiento de API REST.
   - Rate limit básico.
   - Auditoría de requests/responses (sin exponer datos sensibles).

## Prioridad P3 (evolutivo)

1. Integración de pagos del e-commerce.
2. Observabilidad avanzada (dashboard de métricas).
3. Soporte de más países y líneas de negocio (vouchers, DTH, electricidad prepago).

## Definición de listo por iniciativa

Una iniciativa se considera lista cuando cumple:

1. Código implementado en el plugin.
2. Validación manual en entorno WordPress.
3. Actualización de documentación en `Documentación/`.
4. Registro de riesgos y decisiones relevantes.

## Checklist de validación mínima para nuevas funcionalidades

1. Verificar configuración del plugin en admin (`api_base`, `api_key`, `validate_only`).
2. Probar endpoints REST involucrados.
3. Confirmar comportamiento de fallback.
4. Validar errores esperados y mensajes al usuario.
5. Confirmar compatibilidad con catálogo curado activo.

## Avances implementados (14-04-2026)

1. Panel admin con importación rápida de bundles preconfigurados para Colombia, España, México y Cuba.
2. Buscador sobre `Products-with-sku.csv` integrado en admin con selector de resultados para autocompletar alta de bundles.
3. Prevención de duplicados al importar presets por clave `country_iso + sku_code`.
4. Creación automática de bundle desde resultado CSV seleccionado (publicación inmediata opcional).

## Backlog actualizado por impacto

1. Prioridad P2 - Administración de bundles más completa.
   - Estado: parcialmente completado.
   - Completado: importación desde CSV filtrado y catálogo inicial multi-país.
   - Pendiente: edición de bundles existentes y flujo de actualización masiva.
