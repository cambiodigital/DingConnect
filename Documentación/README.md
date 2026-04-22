# Documentación DingConnect

Esta carpeta concentra el contexto técnico y funcional del proyecto para facilitar continuidad entre sesiones y entre agentes IA.

## Punto de entrada recomendado

1. Leer `CONTEXTO_IA.md` para entender estado real, arquitectura y riesgos.
2. Leer `BACKLOG_FUNCIONAL_TECNICO.md` para priorización de próximos pasos.
3. Usar los documentos de referencia según la tarea (API, guía técnica, hallazgos).

## Índice de documentos

### Contexto operativo

- `CONTEXTO_IA.md`: fuente principal de contexto actual del repositorio, con mapa de archivos, flujos, riesgos e inconsistencias.
- `BACKLOG_FUNCIONAL_TECNICO.md`: backlog priorizado por fases (seguridad, integración, producto y operación).
- `ANALISIS_WEBHOOK_LANDINGS_RENE_CUBAKILOS.md`: análisis específico de solicitud de René sobre shortcodes por landing, paquetes propios y estrategia de webhook.
- `../HANDOFF_ESTADO_ACTUAL.md`: handoff histórico de continuidad de trabajo.
- `../REPORTE_INTEGRACION_DING_CONNECT.md`: reporte histórico de validación inicial.

### Referencia técnica de API

- `GUIA_TECNICA_DING_CONNECT.md`: resumen técnico unificado de endpoints, flujos, UAT y checklist de sign-off.
- `API_DING_CONNECT_V1.md`: detalle extendido de endpoints, modelos de datos y reglas operativas.
- `HALLAZGOS_2026-04-14_DING_SKUS.md`: hallazgos de SKUs para Colombia y recomendaciones de catálogo curado.
- `MATRIZ_PRUEBAS_MANUALES_PROVEEDOR_REAL.md`: matriz manual por familia de proveedor real (DTH, electricidad, PIN/voucher y movil rango), con payload esperado y criterio GO/NO-GO operativo.

### Artefactos de implementación

- `../recargas.html`: prototipo frontend legado en un solo archivo (incluye modo demo y pruebas).
- `../dingconnect-wp-plugin/dingconnect-recargas/`: plugin WordPress actual (admin, REST y frontend).
- `../Products-with-sku.csv`: catálogo exportado desde DingConnect con columna `SkuCode`.

## Estado actual del plugin WordPress

En abril de 2026, el plugin ya contempla:

- Formulario público por shortcode.
- Catálogo curado y creación de bundles desde CSV.
- Endpoints REST propios bajo `dingconnect/v1`.
- Modo directo de transferencia.
- Integración opcional con WooCommerce para carrito y checkout.
- Logs internos de transferencias y controles básicos de rate limiting.

## Fuentes oficiales

- API Guide: <https://www.dingconnect.com/Api/Description>
- Methods: <https://www.dingconnect.com/Api>
- FAQ: <https://www.dingconnect.com/Api/Faq>
- Integration sign-off checklist: <https://dingconnect.zendesk.com/hc/en-us/articles/18016429030289-What-are-the-steps-to-get-a-sign-off-from-Ding-Integration-team>
- Flow diagram: <https://dingconnect.zendesk.com/hc/en-us/articles/43096787096209-DingConnect-API-flows>
- UAT Setup: <https://dingconnect.zendesk.com/hc/en-us/articles/43707127986961-UAT-API-credentials-for-DingConnect-API-users>

## Alcance funcional de la API

Con una sola integración, DingConnect permite operar:

- Recargas móviles prepago.
- Recargas de datos y bundles.
- Gift vouchers y productos con `ReceiptText`.
- Servicios no telecom como DTH y electricidad prepago.