# Base de conocimiento completa - DingConnect API

## 1. Alcance y fuentes verificadas

Fecha de consolidacion: 2026-04-21

Cobertura interna auditada de `https://www.dingconnect.com/api`:

1. `https://www.dingconnect.com/Api` (Methods, ReDoc)
2. `https://www.dingconnect.com/Api/Index` (Methods alias)
3. `https://www.dingconnect.com/Api/Description`
4. `https://www.dingconnect.com/Api/Faq`
5. Anclas internas de Description:
   - `#responses`
   - `#reference-data`
   - `#localization`
   - `#querying`
   - `#paging`
   - `#batching`

Notas de verificacion tecnica:

- La pagina Methods expone toda la referencia de operaciones V1 (request/response/status por endpoint).
- La documentacion menciona un swagger con sufijo `/swagger/docs/v1`, pero el endpoint publico no quedo accesible por fetch directo en esta sesion (404/extraeccion no concluyente).
- Se valido en browser integrado que el contenido de Methods carga por ReDoc y contiene el inventario completo de endpoints V1.

## 2. Contrato operativo V1 (resumen integral)

### 2.1 Endpoints del dominio V1

1. `GET /api/V1/GetBalance`
2. `GET /api/V1/GetCountries`
3. `GET /api/V1/GetCurrencies`
4. `GET /api/V1/GetRegions`
5. `GET /api/V1/GetProviders`
6. `GET /api/V1/GetProviderStatus`
7. `GET /api/V1/GetProducts`
8. `GET /api/V1/GetProductDescriptions`
9. `GET /api/V1/GetPromotions`
10. `GET /api/V1/GetPromotionDescriptions`
11. `GET /api/V1/GetAccountLookup`
12. `POST /api/V1/EstimatePrices`
13. `POST /api/V1/LookupBills`
14. `POST /api/V1/SendTransfer`
15. `POST /api/V1/ListTransferRecords`
16. `POST /api/V1/CancelTransfers`
17. `GET /api/V1/GetErrorCodeDescriptions`

### 2.2 Capa comun de respuestas

Casi todas las respuestas incluyen:

- `ResultCode`
- `ErrorCodes[]` con `Code` y `Context`
- `Items[]` cuando aplica

Codigos HTTP comunes en Methods:

- 200 `OK`
- 400 `BadRequest`
- 401 `Unauthorized`
- 500 `InternalServerError`
- 503 `ServiceUnavailable`

Header comun:

- `X-Correlation-Id` (trazabilidad de request)

## 3. Reglas funcionales criticas (Description + Methods + FAQ)

1. `SendTransfer` tiene timeout maximo operativo de 90 segundos.
2. Si `SendTransfer` no confirma en ventana esperada, se debe consultar `ListTransferRecords` para estado final.
3. `ValidateOnly=true` valida pero no descuenta saldo ni produce recarga real.
4. En `ValidateOnly=true`, el precio devuelto es estimado y puede no venir `TransferId`.
5. `DistributorRef` debe ser unico por operacion (idempotencia y conciliacion).
6. Si un producto marca `LookupBillsRequired=true`, debe ejecutarse `LookupBills` antes de `SendTransfer` y pasar `BillRef`.
7. Productos de rango requieren `EstimatePrices` para UI y valor final esperado.
8. `GetProducts` puede devolver `SettingDefinitions`; los `Name` deben enviarse verbatim en `Settings`.
9. `GetProductDescriptions` y `GetPromotionDescriptions` son necesarios para experiencia completa con markdown, validez y textos extendidos.
10. `ReceiptText` y `ReceiptParams` son obligatorios para PIN/vouchers (`pin`, `providerRef`, y otros pares variables).

## 4. Semantica importante por endpoint

### 4.1 Catalogo y referencia

- `GetCountries`: ISO 3166-1 alpha-2 (incluye excepcion `XG` para alcance global).
- `GetCurrencies`: ISO 4217.
- `GetRegions`: mapeo region-pais.
- `GetProviders`: regex de validacion de cuenta por proveedor, logo y canales de soporte.
- `GetProviderStatus`: disponibilidad operacional de proveedor.

### 4.2 Descubrimiento de oferta

- `GetAccountLookup`: mejor para inferir proveedor/region por cuenta (si no hay match, retorna vacio).
- `GetProducts`: fuente de SKUs, min/max, taxes, benefits, processing mode, redemption mechanism, `UatNumber`.
- `GetProductDescriptions`: capa de localizacion y markdown de producto.
- `GetPromotions` + `GetPromotionDescriptions`: promociones + bonus tipificados y textos.

### 4.3 Precio y validacion

- `EstimatePrices`: lote con `BatchItemRef`; `SendValue` y `ReceiveValue` no pueden ser ambos no-cero.
- `LookupBills`: obligatorio para productos de factura.

### 4.4 Ejecucion y post-transaccion

- `SendTransfer`: operacion principal (real o validate-only).
- `ListTransferRecords`: estado final, conciliacion, soporte de timeout y trazabilidad (hasta 2 meses).
- `CancelTransfers`: cancelacion condicional por estado.
- `GetErrorCodeDescriptions`: catalogo legible de errores para capa de soporte.

## 5. FAQ consolidada para implementacion

Preguntas de mayor impacto en desarrollo:

1. Diferencia `validateOnly: true` vs `false`.
2. Uso correcto de `Settings`, `DistributorRef` y `BatchItemRef`.
3. Flujo recomendado para UAT y pruebas por casos de error.
4. Campos que deben mapearse en frontend para sign-off.
5. Uso obligatorio de `ListTransferRecords` en timeouts y conciliacion.
6. Politica de guardado de catalogo y actualizacion periodica.
7. Umbrales de uso (method usage limit) en credenciales de prueba.
8. Whitelisting IP/DNS, codigos de error/contexto y logos de operadores.

## 6. Guia de aterrizaje especifica para este plugin WordPress

### 6.1 Lo ya alineado en el plugin

1. Normalizacion backend de variaciones de payload (`Items`/`Result`) en flujo operativo.
2. Endpoints plugin para `products`, `transfer` y balance admin.
3. Manejo de `validate_only` y modo productivo controlado.
4. Diagnostico mejorado de errores de negocio (balance insuficiente, cuenta invalida, etc.).

### 6.2 Brechas que siguen siendo obligatorias

1. Integrar de forma completa `EstimatePrices` en flujo publico y checkout cuando aplique.
2. Integrar `ListTransferRecords` en timeout/reconciliacion automatizada de operaciones.
3. Completar mapeo frontend de campos enriquecidos:
   - `DefaultDisplayText`
   - `ValidityPeriodIso`
   - `DescriptionMarkdown`
   - `ReadMoreMarkdown`
   - `ReceiptText`
   - `ReceiptParams`
4. Consolidar estrategia de cache diario para catalogo local + refresco incremental.

## 7. Contrato canonico sugerido para backend del plugin

Respuesta canonica recomendada hacia frontend propio:

- `ok` (bool)
- `result_code` (int|string)
- `error_codes` (array)
- `message` (string legible)
- `data` (objeto dominio)
- `meta`:
  - `source_endpoint`
  - `correlation_id`
  - `http_status`
  - `raw_shape` (`items`, `result`, `mixed`)

Objetivo: desacoplar frontend del shape real de Ding y reducir regresiones cuando cambien detalles de payload.

## 8. Checklist de completitud tecnica para proximas fases

1. `GetProducts` + `GetProductDescriptions` mapeados al 100%.
2. `EstimatePrices` activo para productos de rango.
3. `SendTransfer` con `DistributorRef` unico, soporte `Settings` y `BillRef`.
4. `ListTransferRecords` integrado para timeout + reconciliacion.
5. `LookupBills` para productos con `LookupBillsRequired=true`.
6. Superficie de errores normalizada y documentada para soporte.
7. Estrategia UAT/prod separada por credenciales y politicas.
8. Registro de correlacion (`X-Correlation-Id`) persistido en logs internos.

## 9. Referencias oficiales

- Methods: https://www.dingconnect.com/Api
- Description: https://www.dingconnect.com/Api/Description
- FAQ: https://www.dingconnect.com/Api/Faq
- Contacto soporte integracion: partnersupport@ding.com
