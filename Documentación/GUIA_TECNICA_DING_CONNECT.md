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
- Falta documentar e implementar `EstimatePrices` para operadores de rango.
- Falta una implementación explícita de `ListTransferRecords` para timeout y reconciliación.
- Falta incorporar `GetProductDescriptions` y el mapeo completo de campos de front.
- Falta evidenciar flujo UAT formal y checklist de sign-off.
- Debe revisarse whitelisting, 2FA y separación de credenciales de prueba y producción.

## 14. Recomendación de implementación

Orden recomendado para dejar la integración lista para UAT y sign-off:

1. Mover la integración a backend y retirar credenciales del frontend.
2. Implementar `GetProducts`, `EstimatePrices`, `GetProductDescriptions`, `SendTransfer` y `ListTransferRecords` como flujo estándar.
3. Soportar `ReceiptText`, markdown y `ValidityPeriodISO` en interfaz.
4. Crear credenciales UAT bajo un Test Agent y ejecutar la matriz mínima de pruebas.
5. Documentar capturas, resultados y errores esperados.
6. Cerrar hardening de seguridad: 2FA, whitelisting, allowed countries y rotación de claves.

## 15. Nota sobre los diagramas de flujo

El artículo de flujos publicado por Ding está compuesto principalmente por diagramas e imágenes, además de un PDF descargable (`DingConnect Flows.pdf`). Como fuente funcional es útil para validar recorridos de:

- Top-up estándar.
- Bonus.
- PIN.
- DTH.
- Prepaid electricity.

Cuando se requiera documentación visual para QA o negocio, conviene adjuntar ese PDF oficial junto con esta guía.

## 16. Catálogo operativo en panel admin (plugin WordPress)

Desde abril de 2026, el plugin incorpora capacidades para acelerar operación y soporte:

1. Búsqueda sobre `Products-with-sku.csv` con selector de resultados para autocompletar alta de bundles.
2. Filtros por país y tipo de paquete/producto en métodos CSV y API dentro del panel de catálogo.
3. Panel admin modernizado sin numeración en títulos y pestañas, con estilo visual más actual para navegación y operación diaria.

### Objetivo operativo

- Reducir errores manuales al capturar SKU.
- Agilizar la creación de catálogo curado por país.
- Mantener coherencia entre export de DingConnect y bundles disponibles en frontend.

### Recomendación de uso

1. Cargar o actualizar el CSV oficial de DingConnect desde el Método 1.
2. Usar buscador CSV o API para ampliar catálogo según campañas o necesidades comerciales.
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