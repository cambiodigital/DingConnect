# Contexto IA Unificado - DingConnect

## Objetivo del repositorio

Implementar y operar recargas internacionales con DingConnect para Cubakilos, con una transición desde un prototipo web hacia un plugin WordPress seguro y mantenible.

## Estado real (abril 2026)

- Existe un frontend legado en un solo archivo (`recargas.html`) con lógica de catálogo, estimación y envío.
- Existe un plugin WordPress funcional en `dingconnect-wp-plugin/dingconnect-recargas/` con panel admin, endpoints REST y shortcode.
- Hay documentación técnica amplia, pero estaba dispersa y parcialmente redundante.
- Hay un catálogo exportado de SKUs (`Products-with-sku.csv`) útil para preconfigurar bundles.

## Mapa del sistema actual

### 1) Frontend legado (prototipo)

- Archivo: `recargas.html`.
- Características:
	- Intenta llamadas directas a DingConnect.
	- Incluye modo demo/fallback local.
	- Tiene soporte opcional para proxy WordPress.
	- Contiene `api_key` en cliente (riesgo crítico, solo aceptable en pruebas locales).

### 2) Plugin WordPress (implementación objetivo)

- Carpeta: `dingconnect-wp-plugin/dingconnect-recargas/`.
- Entrypoint: `dingconnect-recargas.php`.
- Componentes:
	- `includes/class-dc-api.php`: cliente HTTP hacia DingConnect.
	- `includes/class-dc-rest.php`: API REST pública del plugin.
	- `includes/class-dc-admin.php`: panel de configuración y bundles curados.
	- `includes/class-dc-frontend.php`: shortcode y carga de assets.
	- `assets/js/frontend.js`: UI pública para buscar paquetes y enviar recarga.
	- `assets/css/frontend.css`: estilos del frontend del plugin.

### 3) Datos de negocio

- Archivo: `Products-with-sku.csv`.
- Uso principal:
	- Construcción de catálogo curado.
	- Trazabilidad de `SkuCode` por operador, país y tipo de producto.

## Endpoints REST del plugin

Namespace actual: `dingconnect/v1`

- `GET /wp-json/dingconnect/v1/status`
- `GET /wp-json/dingconnect/v1/bundles`
- `GET /wp-json/dingconnect/v1/products`
	- Query esperada: `account_number`, opcional `country_iso`
- `POST /wp-json/dingconnect/v1/transfer`

## Flujos implementados

### Flujo de consulta de productos

1. Frontend captura país y número.
2. Llama a `/products`.
3. Si DingConnect responde, muestra catálogo live.
4. Si falla, usa bundles curados activos como fallback.

### Flujo de transferencia

1. Usuario selecciona bundle.
2. Frontend envía `account_number`, `sku_code`, `send_value`, `send_currency_iso`.
3. Backend aplica política de `validate_only` y `allow_real_recharge`.
4. Se ejecuta `SendTransfer` en DingConnect.

## Hallazgos clave para futuras IA

- El repositorio tiene dos líneas de integración coexistiendo: prototipo legado y plugin WordPress.
- El plugin es la base recomendada para evolución futura.
- El prototipo legado sigue siendo útil para pruebas UX y experimentación rápida.
- Se dispone de SKUs reales para Colombia (Claro y top-up) documentados en `HALLAZGOS_2026-04-14_DING_SKUS.md`.

## Riesgos y brechas actuales

1. Exposición de credencial en frontend legado (`recargas.html`).
2. Desalineaciones entre prototipo y plugin en nombres de parámetros REST:
	 - Prototipo usa `accountNumber` y `CONFIG.wpProxyBase = /wp-json/cubakilos/v1`.
	 - Plugin usa `account_number` y namespace `dingconnect/v1`.
3. Posible diferencia de shape de respuesta (`Result` vs `Items`) entre llamadas y mapeos.
4. Falta de documentación de contrato canónico interno para normalizar respuestas antes del frontend.

## Fuente de verdad recomendada

Para nuevas funcionalidades, tomar como referencia en este orden:

1. Código del plugin WordPress (`dingconnect-wp-plugin/dingconnect-recargas/`).
2. `GUIA_TECNICA_DING_CONNECT.md`.
3. `API_DING_CONNECT_V1.md`.
4. `HALLAZGOS_2026-04-14_DING_SKUS.md`.
5. `recargas.html` solo como referencia histórica o de UX.

## Reglas operativas para próximos cambios

- Evitar cambios de negocio en `recargas.html` salvo pruebas o prototipos rápidos.
- Implementar nuevas capacidades en el plugin.
- Mantener `ValidateOnly` activo por defecto hasta cerrar ciclo de pruebas reales controladas.
- Versionar y fechar cualquier actualización del catálogo de SKUs.
- Documentar cada cambio funcional en `BACKLOG_FUNCIONAL_TECNICO.md` y en el archivo que lo implemente.

## Lista de lectura rápida para cualquier IA antes de trabajar

1. `Documentación/CONTEXTO_IA.md`
2. `Documentación/BACKLOG_FUNCIONAL_TECNICO.md`
3. `Documentación/GUIA_TECNICA_DING_CONNECT.md`
4. `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-rest.php`
5. `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-api.php`
