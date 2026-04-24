# Guía técnica de integración con DingConnect

## 1. Objetivo

Esta guía resume la documentación oficial de DingConnect necesaria para implementar, probar y poner en producción una integración basada en API. El objetivo es dejar en un único documento los endpoints principales, los flujos funcionales, los requisitos de UAT y el checklist de sign-off.

La API permite operar con una sola integración sobre varias líneas de negocio:

- Recargas móviles prepago.
- Datos y bundles.
- Gift vouchers y productos PIN.
- Servicios DTH.
- Electricidad prepago.

## 2. Referencias oficiales

- API Guide: <https://www.dingconnect.com/Api/Description>
- Methods: <https://www.dingconnect.com/Api>
- FAQ: <https://www.dingconnect.com/Api/Faq>
- Integration sign-off checklist: <https://dingconnect.zendesk.com/hc/en-us/articles/18016429030289-What-are-the-steps-to-get-a-sign-off-from-Ding-Integration-team>
- Flow diagram: <https://dingconnect.zendesk.com/hc/en-us/articles/43096787096209-DingConnect-API-flows>
- UAT Setup: <https://dingconnect.zendesk.com/hc/en-us/articles/43707127986961-UAT-API-credentials-for-DingConnect-API-users>

## 3. Base técnica de la API

- Ding define su API como un servicio REST nivel 0 documentado con Swagger.
- La documentación interactiva de métodos está en `https://www.dingconnect.com/Api`.
- La autenticación puede hacerse con API Key u OAuth2, generadas desde `Account Settings > Developer` en el portal de DingConnect.
- La propia guía recomienda usar la página de métodos para inspeccionar headers, cuerpos y respuestas, o bien reutilizar la definición Swagger para generar SDKs.

### Secciones principales del API Guide

La guía oficial está estructurada en estas áreas:

- Introduction.
- Authentication.
- Test with Postman.
- Deferred SendTransfer.
- Responses.
- Reference Data.
- Localization.
- Querying.
- Paging.
- Batching.
- Method Usage Limit.
- Terminology.
- Error Codes.
- Optional Features.

## 4. Inventario de endpoints relevantes

### Catálogo y referencia

- `GET /api/V1/GetCountries`: catálogo de países.
- `GET /api/V1/GetRegions`: regiones.
- `GET /api/V1/GetCurrencies`: monedas soportadas.
- `GET /api/V1/GetProviders`: proveedores por filtros como país o cuenta.
- `GET /api/V1/GetProviderStatus`: estado de un proveedor.
- `GET /api/V1/GetProducts`: productos disponibles para una cuenta, país o proveedor.
- `GET /api/V1/GetProductDescriptions`: textos enriquecidos del producto, incluidos markdown y descripciones extendidas.
- `GET /api/V1/GetPromotions`: promociones aplicables.
- `GET /api/V1/GetPromotionDescriptions`: detalle textual de promociones.
- `GET /api/V1/GetErrorCodeDescriptions`: descripción de errores y contextos.

### Lookup y validación previa

- `GET /api/V1/GetAccountLookup`: lookup de la cuenta o número de destino.
- `POST /api/V1/EstimatePrices`: cálculo de `ReceiveValue` para operadores de rango.
- `POST /api/V1/LookupBills`: consulta de facturas o importes a pagar cuando el producto lo requiera.

### Operación transaccional

- `POST /api/V1/SendTransfer`: ejecución o validación de la transferencia.
- `POST /api/V1/ListTransferRecords`: consulta de estado final, reconciliación y soporte a timeout.
- `POST /api/V1/CancelTransfers`: intento de cancelación de transferencias cancelables.
- `GET /api/V1/GetBalance`: consulta de balance del agente.

## 5. Endpoints críticos en una integración estándar

No todos los endpoints son obligatorios para un primer release. Para la mayoría de integraciones comerciales, Ding deja claro que hay un subconjunto crítico.

### Flujo mínimo operativo

1. `GetProducts` para identificar SKUs válidos.
2. `EstimatePrices` cuando el operador permite un rango de importes y `SendValue` o `ReceiveValue` no es fijo.
3. `GetProductDescriptions` para mostrar textos, beneficios, instrucciones y markdown.
4. `SendTransfer` para validar o ejecutar.
5. `ListTransferRecords` para timeout, consulta final de estado y reconciliación.

### Endpoints adicionales según producto

- `GetAccountLookup` si necesitas descubrir proveedor o validar una cuenta antes de listar productos.
- `LookupBills` para casos de bill payment o servicios que requieren consulta previa.
- `GetPromotions` y `GetPromotionDescriptions` si el front muestra promociones.
- `CancelTransfers` solo si el caso de negocio necesita cancelaciones y el estado lo permite.

## 6. Reglas funcionales y campos importantes

### `SendTransfer` y `ValidateOnly`

Según la FAQ:

- `ValidateOnly: true` valida sintaxis y balance, pero no descuenta saldo ni envía la recarga real.
- `ValidateOnly: false` ejecuta la operación real y descuenta balance si la respuesta es exitosa.

Esto hace que `ValidateOnly` sea útil para pruebas técnicas, pero no sustituye UAT real ni el flujo productivo completo.

### `DistributorRef` y `Settings`

La FAQ aclara que:

- `DistributorRef` debe identificar de forma única la operación dentro de tu sistema.
- `Settings` permite enviar pares nombre-valor requeridos por ciertos productos o proveedores.
- Los pares enviados por el distribuidor quedan almacenados y pueden recuperarse con `ListTransferRecords`.

### `BatchItemRef`

Se usa en escenarios batched y también aparece en el ejemplo de `EstimatePrices` como identificador único del ítem dentro de una petición por lotes.

## 7. Flujos recomendados por tipo de producto

## 7.1 Recarga móvil estándar

Flujo recomendado:

1. El usuario introduce `AccountNumber`.
2. La integración llama a `GetProducts` usando la cuenta o el país.
3. Si el producto es de rango, se llama a `EstimatePrices` con el importe real deseado.
4. Se complementa la visualización con `GetProductDescriptions`.
5. Se envía `SendTransfer` con `DistributorRef` único.
6. Si hay demora o corte de red, se espera hasta 90 segundos y luego se consulta `ListTransferRecords`.

Nota operativa WooCommerce (abril 2026):

- Para shortcodes en modo `payment_mode=woocommerce`, la recarga se agrega al carrito y el envío a DingConnect se ejecuta solo cuando la orden ya está pagada (`is_paid`).
- El plugin permite definir en admin (`Credenciales > Pasarelas permitidas`) qué métodos de pago WooCommerce quedan habilitados para carritos con recargas DingConnect.
- Si no se selecciona ninguna pasarela en esa lista, checkout conserva todas las pasarelas activas de WooCommerce.

### Nota operativa Wizard v2 (abril 2026)

- En `entry_mode=number_first`, el paso de país puede quedar opcional en frontend: el wizard intenta inferir país automáticamente a partir del catálogo devuelto para el número consultado.
- El backend del wizard ahora aplica validación de estado por paso y bloqueo de saltos no secuenciales al guardar sesión (`category -> country -> operator -> product -> review`, permitiendo retroceso de un paso), para evitar bypass vía llamadas REST directas.
- Además del shortcode público, el wizard está disponible en el panel admin del plugin para operación interna y validación rápida de flujo sin publicar una landing.

### Nota operativa Landings dinámicas (abril 2026)

- El panel admin incluye gestión de shortcodes dinámicos por objetivo de campaña/landing.
- Cada landing selecciona bundles específicos y opcionalmente define título y subtítulo del formulario.
- La configuración de cada landing se administra inline en el panel (alta, edición y baja) sin salir de la pantalla de configuración del plugin.
- El país operativo de la landing se deriva automáticamente de los `country_iso` presentes en los bundles seleccionados.
- El shortcode base soporta `landing_key` para resolver configuración guardada en admin:
  - Ejemplo: `[dingconnect_recargas landing_key="cuba-mayo-2026"]`
- También soporta filtros directos por atributos cuando se requiera configuración manual:
  - `bundles="bundle_id_1,bundle_id_2"`
  - `country="CU"`
  - `title="Recargas Cuba"`
  - `subtitle="Selecciona tu paquete y confirma"`
- Contrato de catálogo para landing: el frontend del shortcode envía `allowed_bundle_ids` al endpoint `/products` y backend prioriza esos bundles explícitos de la landing. Esto evita que se mezclen bundles activos globales del país cuando el objetivo tiene su propio catálogo curado.
- Limitación real actual: cuando `/products` resuelve bundles guardados (`source = saved`) en lugar de catálogo live DingConnect, la landing conserva orden, destacado y restricción de catálogo, pero NO conserva por defecto metadatos ricos del producto (`ProviderCode`, `IsRange`, `LookupBillsRequired`, `SettingDefinitions`, `Benefits`, markdown, branding y validaciones del proveedor).
- Orden y destacado por landing: la configuración del shortcode dinámico permite ordenar bundles de forma explícita y marcar uno como destacado; el endpoint `/products` respeta ese orden cuando aplica filtro por `allowed_bundle_ids` y frontend resalta el paquete destacado con una variación visual amarilla suave.
- Desactivación de destacado en landings: el control `Destacado` del checklist en admin se puede desactivar con click sobre el mismo item ya marcado (toggle), permitiendo persistir `featured_bundle_id` vacío cuando se requiere que ningún bundle aparezca resaltado.
- UX de orden en admin: en alta y edición de landings el checklist de bundles incorpora drag and drop (con manija), y al mover filas se recalcula automáticamente `bundle_order` para persistir el orden visual sin edición manual de números.
- Selector de país en frontend: ya no depende de una configuración fija; se muestra con los países detectados en los bundles permitidos de esa landing.
- Flujo público de la landing: el shortcode `dingconnect_recargas` ya no expone un índice numerado visible; resuelve el recorrido en dos pasos operativos. Paso 1: número + selector de paquetes disponibles para la landing, con ficha visual de Beneficios recibidos, Operador, Monto y País ISO. Paso 2: confirmación antes de derivar a WooCommerce o a la recarga directa.
- Soporte multi-instancia en una misma página: el contenedor del shortcode usa ID único por render y el script inicializa cada bloque por clase (`.dc-recargas-app`), evitando conflictos cuando una landing publica dos o más formularios de recarga.
- Endurecimiento de personalización visual por landing: el CSS dinámico generado desde admin debe mantenerse acotado a controles primarios del wizard (sin reglas globales de `button`) para no degradar estilos del paso final de resultado ni de navegación secundaria.
- Regla de despliegue para assets del shortcode: `assets/css/frontend.css` y `assets/js/frontend.js` deben invalidar caché automáticamente por fecha de modificación (`filemtime`) para impedir que un HTML/JS nuevo conviva con una hoja CSS antigua servida por caché de plugin, CDN o LiteSpeed.
- Regla de visibilidad del selector de país: el overlay `.dc-country-overlay` debe incluir una regla específica para `[hidden]` con `display: none !important;`, porque depende de ese atributo para no bloquear la interacción inicial del shortcode.
- Regla de desarrollo en VS Code (análisis estático): para evitar falsos positivos de funciones globales de WordPress/WooCommerce en edición local, el repositorio mantiene `wordpress-stubs.php` como soporte de análisis; no forma parte del runtime de WordPress.
- Regla de helpers CRUD WooCommerce: cuando se crea un `WC_Product_Simple` nuevo y el ID se necesita inmediatamente, se puede reutilizar el valor retornado por `save()` en lugar de llamar de nuevo a `get_id()`. En este workspace esa forma reduce falsos positivos del analizador sin cambiar el comportamiento real de WooCommerce.

### Nota operativa Shortcode público alineado con DingConnect (abril 2026)

- El shortcode `dingconnect_recargas` ya no se limita a SKUs fijos simples: cuando el producto es de rango, renderiza un input de importe y llama a `EstimatePrices` para recalcular lo que recibirá el destinatario antes de confirmar.
- Cuando DingConnect publica `SettingDefinitions`, el frontend genera inputs dinámicos, valida obligatorios y reenvía esos pares `Name/Value` hasta `SendTransfer` o `add-to-cart`.
- Cuando el producto requiere `LookupBills`, el frontend ejecuta la consulta previa, deja seleccionar la factura o importe devuelto por Ding y reenvía `BillRef` en la operación final.
- Antes de dejar avanzar a confirmación o envío, el shortcode consulta `GetProviderStatus` por `ProviderCode` y bloquea el flujo si el proveedor informa indisponibilidad transaccional.
- En modo WooCommerce, `settings` y `bill_ref` ya no se pierden: se guardan en carrito/pedido y el despachador backend los incorpora a `SendTransfer` junto con la reconciliación previa por `ListTransferRecords`.
- UX dinámica endurecida para payload real: el frontend procesa `ResultCode` y `ErrorCodes` de `EstimatePrices/LookupBills`, muestra mensajes accionables por código DingConnect y refleja estado de carga durante la estimación.
- Coherencia de datos en productos con factura: al cambiar importe o `SettingDefinitions`, el frontend invalida `BillRef` y obliga a reconsulta para evitar enviar una factura obsoleta.
- Alcance exacto del flujo dinámico: estas capacidades avanzadas quedan plenamente activas cuando `/products` entrega catálogo live normalizado desde DingConnect. Si el shortcode trabaja sobre bundles guardados del admin, hoy el backend expone un contrato degradado (`ProviderCode` normalmente vacío, `IsRange = false`, `LookupBillsRequired = false`, `SettingDefinitions = []`), por lo que `provider-status`, `EstimatePrices`, `LookupBills` y campos dinámicos suelen no activarse.
- Cambio mínimo recomendado para alinear runtime y documentación: al guardar un bundle desde catálogo live, persistir también `ProviderCode`, `Benefits`, `DescriptionMarkdown`, `ReadMoreMarkdown`, `LookupBillsRequired`, `SettingDefinitions`, `ValidationRegex`, `CustomerCareNumber`, `LogoUrl`, `PaymentTypes`, `RegionCodes`, `RedemptionMechanism`, `ProcessingMode` e indicador real de rango; después, hacer que `/products` reutilice esos campos cuando `source = saved`.

### Nota operativa Catálogo y alta (abril 2026)

- En la subpestaña `Buscar en API` del admin, los bundles creados desde resultados live se guardan como inactivos por defecto.
- La activación/desactivación de bundles se realiza exclusivamente desde `Bundles guardados`, para mantener un único punto de control operativo.
- La búsqueda live del admin en `Buscar en API` usa únicamente datos de DingConnect para mostrar `operator`, `receive`, `product_type`, `validity`, `send_value` y moneda.
- La pestaña `Credenciales` ya no expone carga de CSV para enriquecimiento del catálogo, dejando un único contrato operativo basado en API live.
- La tabla `Paquetes encontrados` fija la columna `Fuente` como `API` y eliminó avisos/contadores asociados a match contra CSV, para evitar ambigüedad operativa.
- La sección `Catálogo y alta` ya no usa subpestañas internas: la pantalla permanece enfocada en `Buscar en API` y el formulario de `Alta manual` se abre como modal al pulsar `Seleccionar producto`, con precarga del paquete seleccionado.
- El modelo de bundle soporta precio dual por registro: `send_value` (Coste DIN) y `public_price` (Precio al Público editable), con moneda pública por defecto en EUR.
- Brecha contractual actual: aunque `public_price_currency` se persiste en admin, `/products` para bundles guardados sigue publicando `ReceiveCurrencyIso` con `send_currency_iso`; para que el contrato sea exacto con el precio comercial guardado, ese mapeo debe separarse o corregirse explícitamente.
- La clasificación comercial por bundle usa `package_family` (`topup | data | combo | voucher | dth | other`) y conserva `product_type_raw` para trazabilidad contra API.
- La vigencia del producto se guarda en texto (`validity_raw`) y, cuando es interpretable (por ejemplo `P30D` o `30 days`), se deriva `validity_days` para filtros o reglas futuras.
- En landings dinámicas, el checklist de bundles incorpora filtros de País y Tipo de producto; los bundles ya seleccionados permanecen visibles aunque no coincidan con el filtro activo.
- La sección `Paquetes encontrados` de `Buscar en API` ahora se renderiza como tabla de ancho completo fuera de la `form-table`, con columnas de operación (tipo, operador, beneficio, SKU, coste, moneda, vigencia y fuente API), selección por fila y doble click para cargar en `Alta manual`.
- Regla de legibilidad para `Vigencia` en `Buscar en API`: cuando DingConnect entrega formatos técnicos (como `P7D`, `P2W`, `P1M` o equivalentes con texto), la UI muestra una versión natural en español (`7 días`, `2 semanas`, `1 mes`) sin alterar el valor crudo usado para guardado (`validity_raw`).
- Regla de implementación de filtros en checklist: si cada fila usa `display:flex` por estilo, debe existir una regla explícita para `label[hidden]` (`display:none !important`) para que los filtros de País/Tipo oculten realmente los bundles no coincidentes.
- Regla de tabla en `Bundles guardados`: la tabla debe ir dentro de un contenedor con `overflow-x: auto` para evitar desbordes en pantallas estrechas; además, la columna `check-column` debe mantener alineación centrada y espaciado homogéneo entre `Seleccionar todos` y checkboxes por fila.
- Operación sobre catálogos guardados: la pestaña `Productos guardados` (renombrada desde `Bundles guardados`) incorpora filtro en tiempo real con buscador + selectores por tipo de producto, país y operador; el filtrado aplica sobre filas ya renderizadas sin recargar la página.
- Regla UX para tablas con acciones en admin: cuando una tabla permita edición de registros, la edición primaria debe abrir por click en la fila completa (con soporte de teclado Enter/Espacio), y los controles secundarios de la columna `Acciones` deben renderizarse como botones minimalistas `icon-only` con `aria-label`/`title` para mantener accesibilidad y consistencia visual.
- Regla UX para `Shortcodes creados`: el valor del shortcode en tabla debe comportarse como control de copia rápida (click para copiar al portapapeles con feedback visual), sin disparar apertura del modal de edición de la fila.
- El asistente visual `mejoras-solicitud-interactiva.html` mantiene un diccionario ampliado de campos de producto API (live contract) con descripciones operativas para diseñar cambios de arquitectura end-to-end (API -> persistencia -> landing -> WooCommerce) antes de pedir implementación a la IA.
- `Catálogo y alta` conserva metadatos ricos cuando un paquete se carga desde resultados API hacia alta manual (provider/region/pricing extendido/impuestos/rangos/flags/settings/payment types/UAT), de forma que el bundle guardado pueda reutilizar ese contrato en `source=saved`.
- Contrato de moneda comercial en `source=saved`: `ReceiveCurrencyIso` debe priorizar `public_price_currency` (con fallback a `send_currency_iso`) para reflejar correctamente la moneda pública definida por negocio en frontend.
- El asistente visual incluye nodo de auditoría `API -> Persistencia -> Landing` con estado por campo (`Persistido`, `Derivado`, `Pendiente`) para control rápido de cobertura contractual.
- El asistente `mejoras-solicitud-interactiva.html` refleja explícitamente el orden operativo actual del plugin: `Buscar en API -> Hidratación en Alta manual -> Guardado en bundles -> REST /products -> Frontend shortcode -> WooCommerce`, para que los cambios de campos se diseñen sobre el flujo real y no sobre un esquema abstracto.
- En `Buscar en API`, diferenciar `columnas visibles` vs `payload interno`: la tabla operativa muestra 8 columnas (Tipo, Operador, Beneficios, SKU, Coste, Moneda, Vigencia, Fuente), mientras el payload interno puede incluir más campos para hidratación/persistencia (por ejemplo settings/rangos/impuestos/provider metadata).
- Fidelidad operacional 1:1 del asistente: además del contrato API y REST, el modelo visual expone el inventario exacto de campos de formulario visible, hidden fields de hidratación, persistencia completa en `dc_recargas_bundles`, payload de carrito Woo (`dc_*`) y metadatos de pedido (`_dc_*`) para que cualquier solicitud a IA parta del estado real del plugin y no de un subconjunto simplificado.

## 7.2 PIN, vouchers y productos de lectura de recibo

La FAQ indica:

- Cada producto define un mecanismo de redención.
- Los productos PIN son de tipo `ReadReceipt`.
- El dato que debe ver el cliente final viene en `ReceiptText` dentro de la respuesta de `SendTransfer`.
- Para productos PIN, el `AccountNumber` por defecto es `0000000000`.

Implicación práctica: en estos casos no basta con confirmar éxito, también hay que mostrar el recibo, instrucciones de redención y descripciones extendidas.

## 7.3 DTH y electricidad prepago

El diagrama oficial incluye flujos específicos para DTH y electricidad prepago. Además muestra ejemplos concretos de:

- `SendTransfer` request/response para Ikeja Nigeria Electricity.
- `GetProductDescriptions` para ese mismo caso.
- Receipt final.

Esto confirma que para utilities y servicios no telecom la integración debe contemplar campos adicionales, descripciones extendidas y presentación correcta del receipt.

## 7.4 Bonus, bundles y datos

El flow diagram incluye un `Bonus Example` y la FAQ exige mapear `DefaultDisplayText`, `ValidityPeriodISO` y descripciones markdown cuando existan. Eso es especialmente importante en bundles, data packs, vouchers y beneficios no equivalentes a saldo puro.

## 8. Qué debe mostrar el frontend

La FAQ y el checklist de sign-off son muy concretos sobre los campos que deben mapearse en pantalla:

- `SendValue` desde `GetProducts` o `SendTransfer`.
- `ReceiveValue`.
- `ReceiveValueExcludingTax`, cuando aplique.
- `ReceiveCurrencyIso`.
- `DefaultDisplayText` para bundles, datos, vouchers y PIN.
- `ValidityPeriodISO` si no es nulo.
- `Description Mark Down` y `Read More mark down` desde `GetProductDescriptions`.
- `ReceiptText` desde `SendTransfer` para PIN y vouchers.
- `TransferRef` como identificador Ding.
- `DistributorRef` como identificador interno.
- `AccountNumber` como cuenta o MSISDN destino.

Estado actual del plugin WordPress (abril 2026):

- El frontend público ya cubre `ValidationRegex`, `ReceiptText`, `ReceiptParams`, `DescriptionMarkdown` y `ReadMoreMarkdown` cuando llegan normalizados por backend.
- Para productos de rango, la confirmación usa el importe realmente seleccionado por el usuario y la estimación devuelta por `EstimatePrices`, no solo el `SendValue` fijo del catálogo.
- Para bill payment y productos con parámetros obligatorios, la confirmación muestra también `BillRef` y los datos dinámicos capturados antes de enviar o derivar al checkout.

## 9. Timeout, consulta y reconciliación

La FAQ indica que el cliente debe soportar transacciones que demoren más de lo normal:

- Ding procesa la mayoría de operaciones en segundos.
- El cliente debe esperar hasta 90 segundos antes de tratar un `SendTransfer` como no resuelto.
- Si se corta la conexión o no llega respuesta, hay que consultar `ListTransferRecords`.
- `ListTransferRecords` no es opcional: Ding lo recomienda para consulta final de estado y reconciliación.

Parámetros mínimos citados en la FAQ para revisar una transacción:

```json
{
  "DistributorRef": "string",
  "AccountNumber": "string",
  "Take": 1
}
```

### Política operativa actual para `Submitted` prolongado (WooCommerce, abril 2026)

- Reintentos y reconciliación ya no son hardcoded: se configuran desde `Credenciales` con cinco parámetros operativos:
  - `submitted_retry_max_attempts`
  - `submitted_retry_backoff_minutes`
  - `submitted_max_window_hours`
  - `submitted_escalation_email`
  - `submitted_non_retryable_codes`
- Estrategia recomendada y aplicada por defecto: backoff agresivo `10,20,40,80` minutos con escalado a soporte a las `12h` si el estado sigue pendiente.
- Cuando un error DingConnect está en la lista de no reintentables (por ejemplo `InsufficientBalance` o `AccountNumberInvalid`), el ítem se marca como `failed_permanent` y no agenda nuevos reintentos.
- Si la recarga supera ventana/intentos máximos en estado pendiente, el sistema marca `escalado_soporte`, elimina próximo reintento y deja trazabilidad en notas del pedido.
- El panel `Registros` incluye monitor operativo de recargas pendientes/escaladas para seguimiento rápido por orden, estado, intentos y próximo reintento.
- Hardening 22-04-2026: la idempotencia WooCommerce vuelve a considerar `transfer_ref` existente como señal de operación ya procesada para evitar redespacho accidental.
- Hardening 22-04-2026: cuando `ListTransferRecords` falla o no devuelve estado concluyente para un item con referencia previa, el flujo difiere el reintento de conciliación y evita reenviar `SendTransfer` en ese ciclo.
- Hardening 22-04-2026: en frontend, `EstimatePrices` descarta respuestas tardías de solicitudes previas al cambiar importe/producto, evitando confirmaciones con estimación obsoleta.

## 10. UAT y credenciales de prueba

## 10.1 Cómo se crean

La configuración UAT actual indica:

- Primero hay que crear un `Test Agent`.
- Cualquier API Key u OAuth2 generado bajo ese agente será credencial de prueba.
- Se requiere perfil administrador para crear el agente.
- Los distribuidores nuevos suelen tener un agente de prueba por defecto llamado `UAT Api Agent`.

### Límites de credenciales

- 1 test agent por distribuidor.
- Bajo ese agente: máximo 2 API keys y máximo 2 credenciales OAuth2.

## 10.2 Comportamiento UAT

### Métodos basados en `AccountNumber`

Para `GetProviders`, `GetProducts` y `GetAccountLookup`:

- Si el número es válido, la respuesta es coherente con ese número real.
- Si no es válido, Ding intenta detectar el país y devuelve el primer proveedor de ese país.

### `SendTransfer` en UAT

- Todo número válido devuelve éxito en UAT.
- Para simular errores, hay que usar el `UatNumber` devuelto por `GetProducts` y modificar los últimos dígitos siguiendo la tabla publicada por Ding.

Ejemplos documentados en la guía UAT:

- `...001` → `RateLimited`.
- `...002` → `TransientProviderError / ProviderTimedOut`.
- `...003` → `TransientProviderError / ProviderRefusedRequest`.
- `...004` → `ProviderError / ProductUnavailable`.
- `...005` → `AccountNumberInvalid / ProviderRefusedRequest`.
- `...007` → `InsufficientBalance`.
- `...008` → `AccountNumberInvalid / AccountNumberFailedRegex`.
- `...009` → `ProviderError / RechargeNotAllowed`.

## 10.3 Throttling en UAT

Con credenciales de prueba existe throttling. Ding especifica un límite de `100 requests por día` para tráfico UAT sobre estos métodos:

- `GetProviders`.
- `GetAccountLookup`.
- `GetProducts`.
- `SendTransfer`.
- `GetPromotions`.

El límite es por distribuidor, no por API key.

## 11. Preguntas frecuentes que afectan el diseño

### Pruebas

- No existe un servidor o cuenta de prueba separado; las pruebas se hacen con números UAT.
- Incluso en modo live, los números `UatNumber` de éxito pueden utilizarse para probar sin descontar balance.
- Para habilitar un modo de test más detallado, la FAQ indica contactar a `partnersupport@ding.com`.

### Catálogo de productos

La FAQ admite dos enfoques:

- Enfoque estático: guardar productos y resolver proveedor con `GetAccountLookup`.
- Enfoque dinámico: pedir `GetProducts?accountNumber=` en tiempo real.

Si decides guardar catálogo localmente, Ding recomienda:

- Actualizarlo cada día.
- Guardar tanto la respuesta de `GetProducts` como la de `GetProductDescriptions`.

### IP whitelisting

La FAQ confirma que puedes fijar IP o DNS desde `Account Settings > Developer`, en la sección de IP whitelisting. Las entradas se cargan separadas por comas.

## 12. Checklist de sign-off antes de go-live

El artículo de sign-off deja estos requisitos como base para que el equipo de integración dé el visto bueno.

### Seguridad

1. Activar 2FA en todas las cuentas.
2. Regenerar credenciales si fueron compartidas con terceros o personal no autorizado.
3. Hacer whitelisting de IP sobre API Key u OAuth2.
4. Restringir acceso por países permitidos cuando aplique.

### Integración funcional

1. Crear `UAT Agent` y `UAT Key`.
2. Ejecutar transacciones UAT y compartir capturas.
3. Realizar al menos una prueba de cada tipo:
   - Operador de rango.
   - Operador denominado.
   - PIN o voucher.
   - Data recharge.
4. Implementar `EstimatePrices` para operadores de rango.
5. Implementar `ListTransferRecords` para timeout.
6. Mostrar todos los campos de `GetProductDescriptions`.
7. Mapear en la pantalla final todos los campos listados por Ding.

## 13. Observaciones para este workspace

Tomando como referencia el reporte técnico actual del proyecto, el prototipo ya demuestra llamadas a `GetProducts` y `SendTransfer` con `ValidateOnly: true`, pero todavía quedan brechas para cumplir con la guía oficial completa:

- La API Key sigue expuesta en cliente y debe moverse a un backend propio.
- Avance 22-04-2026: el plugin WordPress ya inició esa capa backend con cliente API ampliado y nuevas rutas REST para `GetProviderStatus`, `EstimatePrices`, `LookupBills` y `ListTransferRecords`.
- Avance 22-04-2026: `GET /wp-json/dingconnect/v1/products` ahora expone metadatos operativos adicionales (`ValidationRegex`, `SettingDefinitions`, `LookupBillsRequired`, región, pricing ampliado y textos localizados) y el frontend público ya consume parte de ese contrato para validación básica y visualización de `ReceiptText`/`ReceiptParams`.
- Avance 22-04-2026: WooCommerce ahora reconcilia por `ListTransferRecords` antes de reintentar, evitando tratar `Submitted` como éxito terminal.
- Avance 22-04-2026: el copy final del shortcode público y del cierre WooCommerce ahora se adapta por familia real de producto usando `ProductType`, `RedemptionMechanism`, `LookupBillsRequired`, `ReceiptText`, `ReceiptParams` y señales fiscales (`ReceiveValueExcludingTax`, `TaxName`, `TaxCalculation`).
- Avance 22-04-2026: el soporte de planificación interactiva (`mejoras-solicitud-interactiva.html`) se reforzó como herramienta de diseño/solicitud, incorporando métricas de arquitectura, filtros, historial, plantillas rápidas y formulario guiado para generar prompts técnicos más precisos antes de pedir cambios al código del plugin.
- Sigue pendiente ejecutar validación manual/UAT con proveedores reales y cerrar la evidencia runtime de promociones/markdown enriquecido donde aplique.
- Falta evidenciar flujo UAT formal y checklist de sign-off.
- Debe revisarse whitelisting, 2FA y separación de credenciales de prueba y producción.

## 14. Recomendación de implementación

Orden recomendado para dejar la integración lista para UAT y sign-off:

1. Mover la integración a backend y retirar credenciales del frontend.
2. Consolidar el contrato backend/REST ya iniciado en el plugin y cerrar los pasos pendientes del flujo estándar: `EstimatePrices` interactivo, `LookupBills`, formularios dinámicos por `SettingDefinitions` y reconciliación completa.
3. Completar en interfaz el soporte de `ReceiptText`, markdown, `ValidityPeriodISO`, promociones y estados no terminales.
4. Crear credenciales UAT bajo un Test Agent y ejecutar la matriz mínima de pruebas.
5. Documentar capturas, resultados y errores esperados.
6. Cerrar hardening de seguridad: 2FA, whitelisting, allowed countries y rotación de claves.

## 15. Estado técnico actual: DingConnect Recargas v2 (17-04-2026)

Resumen de verificación consolidada sobre el cambio `dingconnect-recargas-v2`:

- Verificado a nivel de código: flujo wizard para recargas y gift cards, enforcement payment-first, idempotencia por item, política de reintentos y reconciliación manual en WooCommerce.
- Bloqueado a nivel runtime: no hay evidencia E2E ejecutable en esta estación por falta de entorno activo WordPress/WooCommerce con pasarelas y ausencia de `docker`/CLI PHP.
- Contrato de confirmación: unificado para ambos tipos de producto (`transaction_id`, `status`, `operator`, `amount_sent`, `amount_received`, `beneficiary_phone`, `timestamp`, `promotion`, `voucher_lines`).

Brechas pendientes para go-live:

1. Ejecutar matriz runtime 6.1-6.7 en staging con pedido real de WooCommerce.
2. Capturar evidencia por gateway (al menos Stripe, PayPal y una alternativa equivalente disponible).
3. Validar visual y fallback progresivo en al menos un tema externo de landing.
4. Confirmar trazabilidad completa en notas de pedido, logs internos y contenido de voucher/email.

Recomendación operativa:

- Estado actual: `NO-GO` condicional desde este entorno local.
- Condición de habilitación: completar evidencia runtime de la matriz fase 6 y mantener `validate_only=true` hasta cierre de validación controlada.

## 16. Matriz manual por proveedor real y criterio operativo (22-04-2026)

Para cubrir las nuevas rutas UX y la política `Submitted`, la fuente operativa vigente es `MATRIZ_PRUEBAS_MANUALES_PROVEEDOR_REAL.md`.

Resumen ejecutivo de esa matriz:

1. Familias obligatorias: DTH, electricidad prepago, PIN/voucher y móvil rango.
2. Superficies obligatorias: shortcode público, checkout WooCommerce, thank-you, email, notas de pedido y monitor de pendientes.
3. Señales mínimas a validar en payload:
  - móvil rango: `ReceiveValue`, `ReceiveValueExcludingTax`, `TaxName`, `TaxCalculation`;
  - voucher: `ReceiptText`, `ReceiptParams.pin`, `ReceiptParams.providerRef`;
  - electricidad: `LookupBillsRequired`, `BillRef`, `SettingDefinitions`;
  - DTH: `ValidationRegex`, `ProcessingState`, `AdditionalInformation` cuando exista.
4. Regla operativa: `Submitted`, `Pending`, `Processing`, `pending_retry` y `escalado_soporte` nunca deben comunicarse como éxito terminal ni invitar a repetir la compra.

Estado de cierre:

- Estado de código: listo para validar en manual/UAT.
- Estado operativo: `NO-GO` hasta ejecutar las 4 filas de la matriz con evidencia real o UAT equivalente.
- Paso para pasar a GO controlado: completar las 4 familias con capturas/payloads y verificar que thank-you/email preservan el mismo copy por familia que el shortcode.

## 16. Nota sobre los diagramas de flujo

El artículo de flujos publicado por Ding está compuesto principalmente por diagramas e imágenes, además de un PDF descargable (`DingConnect Flows.pdf`). Como fuente funcional es útil para validar recorridos de:

- Top-up estándar.
- Bonus.
- PIN.
- DTH.
- Prepaid electricity.

Cuando se requiera documentación visual para QA o negocio, conviene adjuntar ese PDF oficial junto con esta guía.

## 16. Catálogo operativo en panel admin (plugin WordPress)

Desde abril de 2026, el plugin incorpora capacidades para acelerar operación y soporte:

1. Búsqueda en API con selector de resultados para crear bundles curados, precargar el formulario de alta manual y mostrar en el encabezado de `Datos del bundle` el nombre limpio del paquete API seleccionado.
2. Filtros por país y tipo de paquete/producto dentro del método `Buscar en API` del panel de catálogo.
3. Panel admin modernizado sin numeración en títulos y pestañas, con estilo visual más actual para navegación y operación diaria.

### Objetivo operativo

- Reducir errores manuales al capturar SKU.
- Agilizar la creación de catálogo curado por país.
- Mantener coherencia entre export de DingConnect y bundles disponibles en frontend.

## 17. Incidencia técnica resuelta (21-04-2026)

Se corrigió un problema de marcado HTML en el render del admin del plugin: faltaba el cierre de la sección `tab_wizard` antes de abrir `tab_catalog`.

Impacto observado:

- Las pestañas "Catálogo y alta", "Bundles guardados" y "Registros" podían marcarse como activas, pero su contenido no se mostraba.
- En el DOM, esos paneles quedaban anidados dentro de `tab_wizard`, que normalmente está oculto (`display: none`).

Corrección aplicada:

- Se añadió el cierre de sección faltante en `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-admin.php` para restaurar la jerarquía correcta de paneles hermanos bajo `dc-admin-tabs`.

### Recomendación de uso

1. Usar `Buscar en API` para ampliar catálogo según campañas o necesidades comerciales.
2. Completar o ajustar bundles desde `Alta manual` cuando se necesite curaduría adicional.
3. Verificar en frontend y pruebas controladas antes de habilitar recarga real.

### Buenas prácticas

- Evitar duplicados de SKU por país.
- Priorizar productos con descripción y vigencia claras.
- Reimportar catálogo cuando DingConnect publique nuevos SKUs o retire productos.

## 17. Empaquetado ZIP para actualizar plugin en WordPress

Para actualizar el plugin por carga manual en WordPress, el ZIP debe contener una carpeta raíz llamada `dingconnect-recargas` y, dentro de ella, el archivo principal `dingconnect-recargas.php`.

### Comando recomendado (PowerShell)

```powershell
Set-Location 'x:\Proyectos\DingConnect'
$source = 'x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas'
$dest = 'x:\Proyectos\DingConnect\dingconnect-recargas-wp-update.zip'
Compress-Archive -Path $source -DestinationPath $dest -Force
```

### Validación rápida

Antes de subir el ZIP, validar que contiene esta ruta:

- `dingconnect-recargas/dingconnect-recargas.php`

Si no está esa ruta, WordPress puede mostrar errores como "El archivo del plugin no existe" o no reconocer correctamente el paquete de actualización.

### Variante con carpeta contenedora (compatibilidad)

Si la instalación de WordPress fue creada originalmente con una carpeta contenedora adicional, usar este formato alternativo:

- `dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php`

Comando de referencia:

```powershell
Set-Location 'x:\Proyectos\DingConnect'
$buildRoot = 'x:\Proyectos\DingConnect\_zip_build'
$src = 'x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas'
New-Item -ItemType Directory -Path "$buildRoot\dingconnect-wp-plugin" -Force | Out-Null
Copy-Item -Path $src -Destination "$buildRoot\dingconnect-wp-plugin\dingconnect-recargas" -Recurse -Force
Compress-Archive -Path "$buildRoot\dingconnect-wp-plugin" -DestinationPath 'x:\Proyectos\DingConnect\dingconnect-recargas-wp-update-wrapper.zip' -Force
```

Usar este paquete solo si el plugin activo en WordPress depende de esa estructura de carpetas.

### Variante exacta por URL de activación

Si la URL de activación contiene un path como:

- `plugin=dingconnect-recargas-wp-update/dingconnect-recargas/dingconnect-recargas.php`

entonces el ZIP debe respetar exactamente esa estructura interna:

- `dingconnect-recargas-wp-update/dingconnect-recargas/dingconnect-recargas.php`

Archivo de salida recomendado para este caso:

- `x:\Proyectos\DingConnect\dingconnect-recargas-wp-update-exact.zip`

## 18. Troubleshooting WordPress: "El archivo del plugin no existe" y error 500 al activar

Si en cada intento la URL de activación agrega un nivel más de carpetas (por ejemplo `.../dingconnect-recargas-wp-update-exact2/...`), se está subiendo el plugin con raíces anidadas y WordPress termina activando rutas incorrectas.

Además, un error `500` al activar suele indicar conflicto por copias duplicadas del mismo plugin (mismas clases PHP cargadas desde carpetas distintas).

### Procedimiento recomendado

1. Eliminar del servidor todas las carpetas antiguas del plugin bajo `wp-content/plugins/` (por ejemplo `dingconnect-recargas*`).
2. Subir solo un paquete limpio con estructura canónica:
  - `dingconnect-recargas/dingconnect-recargas.php`
3. Activar únicamente esa instalación.

### Paquete canónico del workspace

- `x:\Proyectos\DingConnect\dingconnect-recargas-clean.zip`

### Solución definitiva recomendada (anti-anidación)

Cuando WordPress empieza a concatenar carpetas en `plugin=...`, empaquetar así:

1. Nombre del ZIP: `dingconnect-recargas.zip`.
2. Contenido del ZIP: archivos del plugin en la raíz (sin carpeta contenedora).
3. Resultado esperado en activación: `plugin=dingconnect-recargas/dingconnect-recargas.php`.

Ruta de salida recomendada:

- `x:\Proyectos\DingConnect\dingconnect-recargas.zip`

### Hallazgo técnico (14-04-2026)

Se detectó fatal de activación por `require_once` de `includes/class-dc-api.php` cuando la estructura instalada en el servidor no coincide con la esperada.

Mitigación aplicada en el plugin:

1. Fallback de carga para detectar subcarpeta anidada con `glob("*/includes/class-dc-api.php")`.
2. Si no se encuentran archivos requeridos, mostrar aviso en admin y evitar fatal en activación.
