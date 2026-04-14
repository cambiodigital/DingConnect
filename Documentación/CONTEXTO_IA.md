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
	- `includes/class-dc-woocommerce.php`: integración opcional con WooCommerce para carrito, checkout y despacho post-pago.
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
- `POST /wp-json/dingconnect/v1/add-to-cart`

## Flujos implementados

### Flujo de consulta de productos

1. Frontend captura país y número.
2. Llama a `/products`.
3. Si DingConnect responde, muestra catálogo live.
4. Si falla, usa bundles curados activos como fallback.

### Flujo de transferencia

1. Usuario selecciona bundle.
2. El frontend muestra una confirmación previa con país, número, operador y precio.
3. Si WooCommerce no está activo, el frontend envía `account_number`, `sku_code`, `send_value`, `send_currency_iso` al endpoint `/transfer`.
4. Si WooCommerce está activo, el frontend llama a `/add-to-cart`, redirige al checkout y la recarga real se ejecuta cuando el pedido pasa a `processing` o `completed`.
5. Backend aplica política de `validate_only` y `allow_real_recharge`.
6. Toda operación queda registrada en un log interno.

## Capacidades nuevas ya implementadas

1. Integración opcional con WooCommerce sin romper el modo directo existente.
2. Endpoint REST `add-to-cart` para iniciar flujo de compra.
3. Rate limiting básico por IP en `products`, `transfer` y `add-to-cart`.
4. Caché temporal de productos por número durante 10 minutos.
5. Normalización de respuestas DingConnect de `Items` a `Result` en backend.
6. Registro de intentos de transferencia en `Transfer Logs`.
7. Resultado visual amigable en frontend en lugar de JSON crudo.
8. Buscador CSV del panel admin con países cargados dinámicamente desde el archivo `Products-with-sku.csv` y auto-búsqueda por texto y país sin clic explícito en Buscar.
9. Formulario público del shortcode con auto-búsqueda al editar número móvil o cambiar país, evitando dependencia del botón Buscar paquetes.
10. Release de branding aplicado en plugin WordPress: versión 1.2.0 con créditos visibles "Hecho por Cambiodigital.net" y "personalizado para cubakilos.com" en componentes clave de administración y frontend.
8. Gestión de bundles guardados en panel admin con edición, activación/desactivación y eliminación por fila.
9. Panel administrativo reorganizado en pestañas para operación más rápida: pestaña de configuración que agrupa credenciales y uso en frontend (1 y 6), pestaña operativa de catálogo y alta (2-3-4) y pestaña especial de bundles guardados (5).
10. Edición de bundles guardados optimizada: el botón Editar abre un modal inline en la pestaña de bundles guardados, evitando navegación o recarga visual entre pantallas para cambios rápidos.
11. Sincronización frontend-admin mejorada: la deduplicación de búsquedas por país+número en el frontend ahora usa expiración (TTL de 10 segundos), permitiendo que bundles y operadores añadidos en admin aparezcan sin recargar la página completa.
12. Frontend público más resiliente: el script del shortcode ahora resuelve elementos dentro del contenedor del formulario y valida nodos críticos para evitar errores por `innerHTML` en elementos nulos cuando hay markup incompleto o plantillas desactualizadas.
13. Recargas directas más compatibles en producción: el backend REST del plugin normaliza `AccountNumber` en formato numérico puro (sin `+`) para cumplir validaciones regex de DingConnect y retorna el status HTTP real de errores de API al cliente.
14. Diagnóstico operativo más claro: el backend API del plugin interpreta códigos de negocio de DingConnect (`InsufficientBalance`, `AccountNumberInvalid`, `RateLimited`, `RechargeNotAllowed`) y devuelve mensajes orientados a soporte sin perder el detalle técnico original.
15. Verificación interna de saldo disponible: el plugin expone `GET /wp-json/dingconnect/v1/balance` con permisos de administrador y lo integra en el panel de credenciales mediante un botón de consulta directa.

## Hallazgos clave para futuras IA

- El repositorio tiene dos líneas de integración coexistiendo: prototipo legado y plugin WordPress.
- El plugin es la base recomendada para evolución futura.
- El prototipo legado sigue siendo útil para pruebas UX y experimentación rápida.
- Se dispone de SKUs reales para Colombia (Claro y top-up) documentados en `HALLAZGOS_2026-04-14_DING_SKUS.md`.
- Cuando WooCommerce está activo, el flujo objetivo ya no es "buscar y disparar recarga" sino "buscar, anadir al carrito y despachar al confirmar el pago".
- Si WordPress reporta "12 caracteres de salida inesperados durante la activación", revisar primero la codificación UTF-8 BOM en los archivos PHP del plugin; cuatro archivos con BOM generan exactamente esos 12 bytes de salida antes de enviar cabeceras.
- Si la URL de activación muestra una ruta anidada como `carpeta-extra/dingconnect-recargas/dingconnect-recargas.php`, tratarlo como síntoma de empaquetado no canónico o de copias duplicadas en `wp-content/plugins`.
- Si el plugin se activa pero muestra el aviso de archivos requeridos faltantes, puede deberse a que el servidor Unix interpreta literalmente los `\` del ZIP de Windows. El bootstrap ahora tolera ambas variantes: `includes/class-dc-api.php` y `includes\class-dc-api.php`, usando `DIRECTORY_SEPARATOR` para ser agnóstico.

## Riesgos y brechas actuales

1. Exposición de credencial en frontend legado (`recargas.html`).
2. Desalineaciones entre prototipo y plugin en nombres de parámetros REST:
	 - Prototipo usa `accountNumber` y `CONFIG.wpProxyBase = /wp-json/cubakilos/v1`.
	 - Plugin usa `account_number` y namespace `dingconnect/v1`.
3. Posible diferencia de shape de respuesta (`Result` vs `Items`) entre llamadas y mapeos.
4. El flujo WooCommerce depende de que el pedido alcance `processing` o `completed`; pasarelas con estados intermedios deben validarse manualmente.
5. El plugin ya normaliza `Items -> Result`, pero todavía falta documentar un contrato canónico más amplio para promociones, rangos y receipts.

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
- Si WooCommerce está habilitado, validar también carrito, checkout, notas del pedido y logs internos antes de activar recarga real.
- Guardar los archivos PHP del plugin en UTF-8 sin BOM para evitar salida invisible durante activación, actualización y carga temprana del plugin.
- Empaquetar preferentemente el plugin con sus archivos en la raíz del ZIP canónico `dingconnect-recargas.zip` para evitar carpetas contenedoras adicionales en la instalación.

## Lista de lectura rápida para cualquier IA antes de trabajar

1. `Documentación/CONTEXTO_IA.md`
2. `Documentación/BACKLOG_FUNCIONAL_TECNICO.md`
3. `Documentación/GUIA_TECNICA_DING_CONNECT.md`
4. `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-rest.php`
5. `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-api.php`
