# Documentación Oficial — Ding API V1

> **Fuente:** [https://www.dingconnect.com/Api/#tag/V1](https://www.dingconnect.com/Api/#tag/V1)  
> **Documentación adicional:** [https://www.dingconnect.com/Api/Description](https://www.dingconnect.com/Api/Description)  
> **FAQ:** [https://www.dingconnect.com/Api/Faq](https://www.dingconnect.com/Api/Faq)  
> **Base consolidada del repo:** [BASE_CONOCIMIENTO_API_DINGCONNECT_COMPLETA.md](BASE_CONOCIMIENTO_API_DINGCONNECT_COMPLETA.md)  
> **Última actualización de este documento:** 21-04-2026

---

## Tabla de Contenidos

1. [Introducción](#introducción)
2. [Cobertura auditada de páginas internas](#cobertura-auditada-de-páginas-internas)
3. [Autenticación](#autenticación)
4. [Conceptos Generales](#conceptos-generales)
   - [Respuestas y Códigos de Error](#respuestas-y-códigos-de-error)
   - [Datos de Referencia](#datos-de-referencia)
   - [Localización](#localización)
   - [Consultas y Filtrado](#consultas-y-filtrado)
   - [Paginación](#paginación)
   - [Batching (Procesamiento por Lotes)](#batching-procesamiento-por-lotes)
5. [Endpoints](#endpoints)
   - [GetBalance](#1-getbalance)
   - [GetCountries](#2-getcountries)
   - [GetCurrencies](#3-getcurrencies)
   - [GetRegions](#4-getregions)
   - [GetProviders](#5-getproviders)
   - [GetProviderStatus](#6-getproviderstatus)
   - [GetProducts](#7-getproducts)
   - [GetProductDescriptions](#8-getproductdescriptions)
   - [GetPromotions](#9-getpromotions)
   - [GetPromotionDescriptions](#10-getpromotiondescriptions)
   - [GetAccountLookup](#11-getaccountlookup)
   - [EstimatePrices](#12-estimateprices)
   - [LookupBills](#13-lookupbills)
   - [SendTransfer](#14-sendtransfer)
   - [ListTransferRecords](#15-listtransferrecords)
   - [CancelTransfers](#16-canceltransfers)
   - [GetErrorCodeDescriptions](#17-geterrorcodedescriptions)
5. [Modelos de Datos](#modelos-de-datos)
6. [Estados de Procesamiento](#estados-de-procesamiento)
7. [Flujos Recomendados](#flujos-recomendados)

---

## Introducción

La Ding API es un servicio web REST de Nivel 0. Ding utiliza el estándar [Swagger/OpenAPI](http://swagger.io/) para describir el servicio, lo que permite generar SDKs cliente automáticamente en más de 20 lenguajes de programación a través de herramientas como [http://editor.swagger.io](http://editor.swagger.io/).

**URL base:** `https://www.dingconnect.com/api/V1`

La definición Swagger está disponible en la URL que termina con `/swagger/docs/v1`, visible en la página de Methods.

---

## Cobertura auditada de páginas internas

Durante la actualización del 21-04-2026 se verificó navegación y extracción sobre estas rutas internas de `https://www.dingconnect.com/api`:

1. `https://www.dingconnect.com/Api`
2. `https://www.dingconnect.com/Api/Index`
3. `https://www.dingconnect.com/Api/Description`
4. `https://www.dingconnect.com/Api/Faq`
5. Anclas internas de `Description` (`#responses`, `#reference-data`, `#localization`, `#querying`, `#paging`, `#batching`).

Notas técnicas de cobertura:

- `Methods` contiene el inventario completo de operaciones V1 y schemas operativos.
- La referencia pública al swagger con sufijo `/swagger/docs/v1` se menciona en la documentación, pero en esta auditoría no estuvo disponible por URL directa abierta.
- Este archivo se mantiene como referencia de integración diaria; la base extensa y de arquitectura aplicada para el plugin vive en `Documentación/BASE_CONOCIMIENTO_API_DINGCONNECT_COMPLETA.md`.

## Autenticación

Todas las solicitudes deben incluir el `API Key` en el encabezado de autenticación.

Para generar un API Key:
1. Acceder a la [pestaña Developer](https://www.dingconnect.com/en-US/ActMgmt/Developer) en la configuración de la cuenta de DingConnect.
2. Copiar la clave generada.
3. Incluirla en cada request con el header `api_key`.

**Header requerido en todos los endpoints:**

```
api_key: {tu_api_key}
```

**Header opcional (correlación):**

```
X-Correlation-Id: {string_único}
```

> Sirve para correlacionar solicitudes HTTP entre cliente y servidor, útil para trazabilidad y debugging.

---

## Conceptos Generales

### Respuestas y Códigos de Error

Toda respuesta de la API incluye los campos:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `ResultCode` | integer | Código de resultado general de la operación |
| `ErrorCodes` | array | Lista de errores con `Code` y `Context` |
| `Items` | array | Datos devueltos (cuando aplica) |

**Códigos HTTP de respuesta comunes:**

| Código | Significado |
|--------|-------------|
| `200` | OK — operación exitosa |
| `400` | BadRequest — parámetros inválidos |
| `401` | Unauthorized — API Key inválida o ausente |
| `500` | InternalServerError — error en el servidor de Ding |
| `503` | ServiceUnavailable — servicio no disponible temporalmente |

> Los `ErrorCodes` están orientados al agente/integrador y **no son aptos para mostrar al usuario final**. Usar `GetErrorCodeDescriptions` para obtener descripciones legibles.

---

### Datos de Referencia

Los datos de referencia (países, regiones, monedas) cambian con poca frecuencia. Se recomienda:

- **Cachear** estos datos en el sistema propio.
- **Actualizar periódicamente** (no en cada request).

**Estándares utilizados:**
- Códigos de país: [ISO 3166-1 alpha-2](https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2) — excepción: `XG` para productos disponibles en todos los países.
- Códigos de moneda: [ISO 4217](https://en.wikipedia.org/wiki/ISO_4217) (3 letras).

---

### Localización

Los productos y promociones tienen descripciones localizadas. Para obtenerlas:

- Llamar a `GetProductDescriptions` con el código de idioma deseado.
- Llamar a `GetPromotionDescriptions` con el código de idioma deseado.

Los textos devueltos incluyen formato Markdown en algunos campos (`DescriptionMarkdown`, `ReadMoreMarkdown`, `TermsAndConditionsMarkDown`).

---

### Consultas y Filtrado

La mayoría de los endpoints `GET` aceptan **parámetros de filtrado opcionales** en el query string. Si no se pasan filtros, se devuelve la lista completa.

**Patrón general de filtros disponibles:**

- Por país: `countryIsos`
- Por proveedor: `providerCodes`
- Por producto: `skuCodes`
- Por región: `regionCodes`
- Por número de cuenta: `accountNumber`

---

### Paginación

El endpoint `ListTransferRecords` soporta paginación mediante:

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `Skip` | integer | Cantidad de registros a saltar |
| `Take` | integer (**requerido**) | Cantidad de registros a retornar |

La respuesta incluye `ThereAreMoreItems: true` cuando hay más registros disponibles más allá de los retornados.

---

### Batching (Procesamiento por Lotes)

Los endpoints `CancelTransfers` y `EstimatePrices` aceptan arrays de solicitudes en un único request. Cada ítem del array debe incluir un `BatchItemRef` que lo identifique de forma única dentro del lote.

En la respuesta, cada ítem del array de resultados incluirá su `BatchItemRef` correspondiente, lo que permite correlacionar resultados con solicitudes originales.

---

## Endpoints

### 1. GetBalance

**`GET /api/V1/GetBalance`**

Obtiene el saldo actual del agente.

**Notas importantes:**
- Incluye incrementos por comisión sobre ventas.
- **No refleja** transferencias en procesamiento.
- Para transferencias **Instant**: el saldo se descuenta después de interacción exitosa con el proveedor.
- Para transferencias **Batch**: el saldo se descuenta inmediatamente y se reembolsa si falla.

**Headers:**

| Header | Tipo | Descripción |
|--------|------|-------------|
| `X-Correlation-Id` | string | Correlación de request (opcional) |

**Respuesta 200:**

```json
{
  "Balance": 0,
  "CurrencyIso": "string",
  "ResultCode": 0,
  "ErrorCodes": [
    {
      "Code": "string",
      "Context": "string"
    }
  ]
}
```

---

### 2. GetCountries

**`GET /api/V1/GetCountries`**

Lista de países soportados por el sistema.

**Headers:**

| Header | Tipo | Descripción |
|--------|------|-------------|
| `X-Correlation-Id` | string | Correlación de request (opcional) |

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "CountryIso": "string",
      "CountryName": "string",
      "InternationalDialingInformation": [
        {
          "Prefix": "string",
          "MinimumLength": 0,
          "MaximumLength": 0
        }
      ],
      "RegionCodes": ["string"]
    }
  ]
}
```

> Usa [ISO 3166-1 alpha-2](https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2). Excepción: `XG` para productos sin restricción de país.

---

### 3. GetCurrencies

**`GET /api/V1/GetCurrencies`**

Lista de monedas soportadas por el sistema.

**Headers:**

| Header | Tipo | Descripción |
|--------|------|-------------|
| `X-Correlation-Id` | string | Correlación de request (opcional) |

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "CurrencyIso": "string",
      "CurrencyName": "string"
    }
  ]
}
```

> Usa [ISO 4217](https://en.wikipedia.org/wiki/ISO_4217) (códigos de 3 letras).

---

### 4. GetRegions

**`GET /api/V1/GetRegions`**

Lista de regiones disponibles en el sistema.

**Query Parameters:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `countryIsos` | array of strings | Filtra por código(s) de país |

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "RegionCode": "string",
      "RegionName": "string",
      "CountryIso": "string"
    }
  ]
}
```

---

### 5. GetProviders

**`GET /api/V1/GetProviders`**

Lista los proveedores de productos disponibles para el agente.

**Query Parameters:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `providerCodes` | array of strings | Filtra por códigos de proveedor |
| `countryIsos` | array of strings | Filtra por códigos de país |
| `regionCodes` | array of strings | Filtra por códigos de región |
| `accountNumber` | string | Filtra los proveedores válidos para este número (formato internacional) |

**Notas sobre los campos:**

- **`Name`**: puede incluir nombre del país, por ejemplo: `"Airtel India"`, `"Vodafone UK"`.
- **`ValidationRegex`**: expresión regular que valida números de cuenta. Compatible con PCRE / .NET Regex. El sistema de Ding es .NET y usa la [librería de Microsoft](https://msdn.microsoft.com/en-us/library/hs600312).
  > Un número puede pasar la regex pero ser inválido para un producto específico y válido para otro.
- **`CustomerCareNumber`**: número de atención al cliente del proveedor, útil para incluirlo en recibos de productos PIN.
- **`LogoUrl`**: URL del logo del operador. Acepta parámetros `?height=xxx&width=yyy` para dimensiones. Ejemplo: `https://imagerepo.ding.com/logo/AI/IN.png?height=100`.
  > Se recomienda especificar solo la dimensión dominante para evitar espacios en blanco.

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "ProviderCode": "string",
      "CountryIso": "string",
      "Name": "string",
      "ShortName": "string",
      "ValidationRegex": "string",
      "CustomerCareNumber": "string",
      "RegionCodes": ["string"],
      "PaymentTypes": ["string"],
      "LogoUrl": "string"
    }
  ]
}
```

---

### 6. GetProviderStatus

**`GET /api/V1/GetProviderStatus`**

Estado operacional actual de los proveedores.

**Query Parameters:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `providerCodes` | array of strings | Filtra por códigos de proveedor |

**Notas:**
- `Message`: texto sin formato, sin localización, destinado al agente. **No es apto para mostrar al usuario final**.
- Un proveedor puede estar suspendido o en estado de error.

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "ProviderCode": "string",
      "IsProcessingTransfers": true,
      "Message": "string"
    }
  ]
}
```

---

### 7. GetProducts

**`GET /api/V1/GetProducts`**

Lista de productos disponibles para usar en `SendTransfer`.

**Query Parameters:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `countryIsos` | array of strings | Filtra por países |
| `providerCodes` | array of strings | Filtra por proveedores |
| `skuCodes` | array of strings | Filtra por SKUs específicos |
| `benefits` | array of strings | Filtra por tipo de beneficio |
| `regionCodes` | array of strings | Filtra por regiones |
| `accountNumber` | string | Filtra productos válidos para este número (formato internacional) |

#### Campos importantes del producto

**`ProcessingMode`** — modo de procesamiento:

| Valor | Descripción |
|-------|-------------|
| `Instant` | Procesamiento en tiempo real. `SendTransfer` responderá con estado `Completed` o `Failed`. |
| `Batch` | Procesamiento diferido. `SendTransfer` responderá con estado `Submitted`. Verificar el estado con `ListTransferRecords`. |

**`RedemptionMechanism`** — mecanismo de canje:

| Valor | Descripción |
|-------|-------------|
| `Immediate` | No requiere acción adicional del cliente. |
| `ReadReceipt` | Instrucciones en `ReceiptText`. Ej: productos PIN o minutos de larga distancia. |
| `ReadAdditionalInformation` | Instrucciones en `AdditionalInformation` del producto. El agente debe implementar lógica según esas instrucciones. |

**`Benefits`** — beneficios del producto:

| Valor |
|-------|
| `Mobile` |
| `Minutes` |
| `Data` |
| `LongDistance` |
| `Electricity` |
| `TV` |
| `Internet` |
| `Utility` |
| `Balance` |

> La lista de beneficios puede extenderse. Recomendado: implementar el sistema para manejar tipos desconocidos.

**`SettingDefinitions`** — configuraciones adicionales requeridas por el proveedor. Si está presente, los valores en `Name` deben usarse **verbatim** (incluyendo mayúsculas y caracteres especiales) en el request a `SendTransfer`.

**`CommissionRate`** — tasa de comisión aplicable por venta del producto.

**`ValidityPeriodIso`** — período de validez en formato [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601). Ejemplo: `P30D` = 30 días.

**`UatNumber`** — número para pruebas UAT. Transferencias con este número son en modo test: no se descuenta saldo ni se realiza transacción real con el proveedor.

**`AdditionalInformation`** — información no estándar para el integrador. Sin formato, sin localización, no apto para usuarios finales.

**`LookupBillsRequired`** — si es `true`, se debe llamar a `LookupBills` antes de `SendTransfer`.

#### Estructura de precios (Price)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `SendValue` | decimal | Monto a enviar (en moneda del agente) |
| `SendCurrencyIso` | string | Moneda del monto enviado |
| `ReceiveValue` | decimal | Monto que recibirá el destinatario |
| `ReceiveCurrencyIso` | string | Moneda del monto recibido |
| `ReceiveValueExcludingTax` | decimal | Monto recibido sin impuestos |
| `CustomerFee` | decimal | Tarifa cobrada al cliente final (en moneda del agente) |
| `DistributorFee` | decimal | Tarifa que Ding cobra al agente |
| `TaxRate` | decimal | Tasa impositiva aplicada |
| `TaxName` | string | Nombre del impuesto |
| `TaxCalculation` | string | `Inclusive` o `Exclusive` (ver abajo) |

**Lógica de tarifas:**

```
SendValue = 10
CustomerFee = 1
DistributorFee = 2

→ Al proveedor se transfiere: SendValue - CustomerFee = 9
→ Del saldo del agente se descuenta: SendValue + DistributorFee = 12
```

**TaxCalculation:**

| Valor | Significado |
|-------|-------------|
| `Inclusive` | El impuesto está incluido en el precio |
| `Exclusive` | El impuesto se añade sobre el precio |
| *(vacío)* | El impuesto lo maneja el proveedor directamente |

> Ver: [Tax Rates en Wikipedia](https://en.wikipedia.org/wiki/Tax_rate#Inclusive_and_exclusive).

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "ProviderCode": "string",
      "SkuCode": "string",
      "LocalizationKey": "string",
      "SettingDefinitions": [
        {
          "Name": "string",
          "Description": "string",
          "IsMandatory": true
        }
      ],
      "Maximum": {
        "CustomerFee": 0,
        "DistributorFee": 0,
        "ReceiveValue": 0,
        "ReceiveCurrencyIso": "string",
        "ReceiveValueExcludingTax": 0,
        "TaxRate": 0,
        "TaxName": "string",
        "TaxCalculation": "string",
        "SendValue": 0,
        "SendCurrencyIso": "string"
      },
      "Minimum": {
        "CustomerFee": 0,
        "DistributorFee": 0,
        "ReceiveValue": 0,
        "ReceiveCurrencyIso": "string",
        "ReceiveValueExcludingTax": 0,
        "TaxRate": 0,
        "TaxName": "string",
        "TaxCalculation": "string",
        "SendValue": 0,
        "SendCurrencyIso": "string"
      },
      "CommissionRate": 0,
      "ProcessingMode": "string",
      "RedemptionMechanism": "string",
      "Benefits": ["string"],
      "ValidityPeriodIso": "string",
      "UatNumber": "string",
      "AdditionalInformation": "string",
      "DefaultDisplayText": "string",
      "RegionCode": "string",
      "PaymentTypes": ["string"],
      "LookupBillsRequired": true
    }
  ]
}
```

---

### 8. GetProductDescriptions

**`GET /api/V1/GetProductDescriptions`**

Textos localizados de los productos.

**Query Parameters:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `languageCodes` | array of strings | Filtra por código(s) de idioma |
| `skuCodes` | array of strings | Filtra por SKU(s) del producto |

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "DisplayText": "string",
      "DescriptionMarkdown": "string",
      "ReadMoreMarkdown": "string",
      "LocalizationKey": "string",
      "LanguageCode": "string"
    }
  ]
}
```

---

### 9. GetPromotions

**`GET /api/V1/GetPromotions`**

Lista de promociones vigentes.

**Query Parameters:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `countryIsos` | array of strings | Filtra por países |
| `providerCodes` | array of strings | Filtra por proveedores |
| `accountNumber` | string | Filtra por número de cuenta (formato internacional) |

**Notas:**
- `ValidityPeriodIso` en formato ISO 8601. Ejemplo: `P30D` = 30 días.
- Las descripciones localizadas se obtienen con `GetPromotionDescriptions`.

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "ProviderCode": "string",
      "StartUtc": "2019-08-24T14:15:22Z",
      "EndUtc": "2019-08-24T14:15:22Z",
      "CurrencyIso": "string",
      "ValidityPeriodIso": "string",
      "MinimumSendAmount": 0,
      "LocalizationKey": "string"
    }
  ]
}
```

---

### 10. GetPromotionDescriptions

**`GET /api/V1/GetPromotionDescriptions`**

Textos localizados de las promociones.

**Query Parameters:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `languageCodes` | array of strings | Filtra por código(s) de idioma |

#### Campos especiales

**`Dates`** — fechas específicas en que aplica la promoción, separadas por punto y coma. Vacío significa "sin restricción de fecha". Ejemplo: `"07/02/2017;07/03/2017;07/04/2017"`.

**`BonusValidity`** — días de validez del bono otorgado.

**Tipos de bono (`BonusType`):**

| Valor | Descripción |
|-------|-------------|
| `+` | Suma al monto enviado |
| `%` | Porcentaje del monto enviado |
| `x` | Multiplicador del monto enviado (ej: `2x` = doble) |
| `MB` | Megabytes |
| `GB` | Gigabytes |
| `SMS` | Cantidad de SMS |

> Pueden existir nuevos tipos no incluidos en esta lista. El sistema debe estar preparado para manejarlos.

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "Dates": "string",
      "Headline": "string",
      "TermsAndConditionsMarkDown": "string",
      "BonusValidity": "string",
      "PromotionType": "string",
      "LocalizationKey": "string",
      "LanguageCode": "string",
      "SendAmounts": [
        {
          "Minimum": 0,
          "Maximum": 0,
          "Bonuses": [
            {
              "BonusType": "string",
              "Quantity": 0,
              "Validity": 0
            }
          ]
        }
      ]
    }
  ]
}
```

---

### 11. GetAccountLookup

**`GET /api/V1/GetAccountLookup`**

Devuelve información de país, proveedor y región para un número de cuenta específico.

**Query Parameters:**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `accountNumber` | string | Número de cuenta en formato internacional (ej: `+34612345678`) |

**Diferencia clave vs `GetProducts` y `GetProviders`:**

| Comportamiento | GetProducts / GetProviders | GetAccountLookup |
|----------------|---------------------------|-----------------|
| Sin coincidencia | Devuelve lista completa | Devuelve lista vacía |

> Se recomienda usar `GetAccountLookup` primero para determinar proveedor y región, y luego pasar esos datos como filtros a `GetProducts`.

**Respuesta 200:**

```json
{
  "CountryIso": "string",
  "AccountNumberNormalized": "string",
  "Items": [
    {
      "ProviderCode": "string",
      "RegionCode": "string"
    }
  ],
  "ResultCode": 0,
  "ErrorCodes": []
}
```

---

### 12. EstimatePrices

**`POST /api/V1/EstimatePrices`**

Estima el precio para valores de envío o recepción dados, antes de realizar una transferencia.

> Los precios exactos no se pueden garantizar antes de la transferencia real (por tipos de cambio, promociones y comisiones en tiempo real). Este endpoint provee una estimación.

**Request Body:** `application/json` (array)

```json
[
  {
    "SendValue": 0,
    "SendCurrencyIso": "string",
    "ReceiveValue": 0,
    "SkuCode": "string",
    "BatchItemRef": "string"
  }
]
```

**Reglas de los parámetros:**

- `SendValue` y `ReceiveValue` **no pueden ser ambos distintos de cero** al mismo tiempo.
- Si `ReceiveValue > 0`: estima cuánto debe enviar el cliente para que el destinatario reciba ese monto.
- Si `SendValue > 0` y `SendCurrencyIso` está vacío: estima qué recibirá el destinatario por ese monto (en moneda del agente).
- Si `SendValue > 0` y `SendCurrencyIso` está especificado: estima usando esa moneda específica. Contactar a Ding para habilitar envío en monedas distintas a la principal de la cuenta.
- `BatchItemRef`: referencia única por ítem dentro del lote.

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "Price": {
        "CustomerFee": 0,
        "DistributorFee": 0,
        "ReceiveValue": 0,
        "ReceiveCurrencyIso": "string",
        "ReceiveValueExcludingTax": 0,
        "TaxRate": 0,
        "TaxName": "string",
        "TaxCalculation": "string",
        "SendValue": 0,
        "SendCurrencyIso": "string"
      },
      "SkuCode": "string",
      "BatchItemRef": "string",
      "ResultCode": 0,
      "ErrorCodes": []
    }
  ]
}
```

---

### 13. LookupBills

**`POST /api/V1/LookupBills`**

Busca facturas/boletas disponibles para pago. Requerido cuando el producto tiene `LookupBillsRequired: true`.

> El `BillRef` retornado aquí debe incluirse en el campo `BillRef` del request a `SendTransfer`.

**Request Body:** `application/json`

```json
{
  "SkuCode": "string",
  "AccountNumber": "string",
  "Settings": [
    {
      "Name": "string",
      "Value": "string"
    }
  ]
}
```

| Campo | Requerido | Descripción |
|-------|-----------|-------------|
| `SkuCode` | ✅ | Código del producto obtenido de `GetProducts` |
| `AccountNumber` | ✅ | Número de cuenta del destinatario |
| `Settings` | ❌ | Pares nombre/valor específicos del producto |

**Respuesta 200:**

```json
{
  "Items": [
    {
      "Price": {
        "CustomerFee": 0,
        "DistributorFee": 0,
        "ReceiveValue": 0,
        "ReceiveCurrencyIso": "string",
        "ReceiveValueExcludingTax": 0,
        "TaxRate": 0,
        "TaxName": "string",
        "TaxCalculation": "string",
        "SendValue": 0,
        "SendCurrencyIso": "string"
      },
      "BillRef": "string",
      "AdditionalInfo": {
        "property1": "string",
        "property2": "string"
      }
    }
  ],
  "ResultCode": 0,
  "ErrorCodes": []
}
```

---

### 14. SendTransfer

**`POST /api/V1/SendTransfer`**

Envía una transferencia a un número de cuenta.

**Tiempo máximo de procesamiento:** 90 segundos. Si se supera, el sistema retorna `ProviderTimedOut`. La transferencia se trata como fallida y no se descuenta el saldo.

**Request Body:** `application/json`

```json
{
  "SkuCode": "string",
  "SendValue": 0,
  "SendCurrencyIso": "string",
  "AccountNumber": "string",
  "DistributorRef": "string",
  "Settings": [
    {
      "Name": "string",
      "Value": "string"
    }
  ],
  "ValidateOnly": true,
  "BillRef": "string"
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `SkuCode` | string | ✅ | Código del producto (obtenido de `GetProducts`) |
| `SendValue` | decimal | ✅ | Monto a enviar. Debe estar entre Minimum y Maximum del producto. Especificado con 2 decimales (ej: `3.17`) |
| `SendCurrencyIso` | string | ❌ | Moneda del monto. Si es nulo, se asume la moneda del agente |
| `AccountNumber` | string | ✅ | Número de cuenta del destinatario |
| `DistributorRef` | string | ✅ | Identificador único de la transferencia en el sistema del agente |
| `Settings` | array | ❌ | Pares nombre/valor del producto. Los pares propios del agente se almacenan y son consultables con `ListTransferRecords` |
| `ValidateOnly` | boolean | ✅ | Si `true`: valida sin cobrar ni realizar la transferencia real |
| `BillRef` | string | ❌ | Referencia de factura. Requerida cuando `LookupBillsRequired: true` |

#### ValidateOnly

Cuando `ValidateOnly: true`:
- Se valida el request en el sistema de Ding.
- Se intenta validar el `AccountNumber` con el proveedor (no todos los proveedores lo soportan).
- **No se descuenta saldo** (pero sí se verifica que haya saldo suficiente).
- El precio en la respuesta es una **estimación**.
- **No se asigna `TransferId`** en la respuesta.

#### Settings (configuraciones adicionales)

Los pares en `Settings` sirven para dos propósitos:
1. **Requeridos por el proveedor**: definidos en `SettingDefinitions` del producto. Los `Name` deben usarse verbatim.
2. **Propios del agente**: cualquier par que el agente quiera almacenar y luego consultar en `ListTransferRecords`.

#### Deferred SendTransfer (Transferencia Diferida)

Permite recibir notificación por callback HTTP (webhook) cuando la transferencia se completa.

- Respuesta inmediata con `ProcessingState: Submitted`.
- El resultado final llega como webhook.
- En caso de fallos, se reintenta hasta alcanzar el umbral máximo.
- Más detalles en la [documentación de Deferred SendTransfer](https://www.dingconnect.com/Api/Description).

#### Recibos (Receipts)

- **`ReceiptText`**: texto del proveedor con instrucciones para el usuario final.
- **`ReceiptParams`**: pares nombre/valor para usar en plantillas propias (HTML, dispositivos con espacio limitado, etc.).
  - Si hay un PIN: la clave `pin` estará en `ReceiptParams`.
  - Si el proveedor retorna un ID de transacción: la clave `providerRef` estará en `ReceiptParams`.
  
> Los nombres en `ReceiptParams` pueden cambiar según el proveedor. El sistema debe ser robusto a desaparición o aparición de nuevas claves.

**Respuesta 200:**

```json
{
  "TransferRecord": {
    "TransferId": {
      "TransferRef": "string",
      "DistributorRef": "string"
    },
    "SkuCode": "string",
    "Price": {
      "CustomerFee": 0,
      "DistributorFee": 0,
      "ReceiveValue": 0,
      "ReceiveCurrencyIso": "string",
      "ReceiveValueExcludingTax": 0,
      "TaxRate": 0,
      "TaxName": "string",
      "TaxCalculation": "string",
      "SendValue": 0,
      "SendCurrencyIso": "string"
    },
    "CommissionApplied": 0,
    "StartedUtc": "2019-08-24T14:15:22Z",
    "CompletedUtc": "2019-08-24T14:15:22Z",
    "ProcessingState": "string",
    "ReceiptText": "string",
    "ReceiptParams": {
      "pin": "string",
      "providerRef": "string"
    },
    "AccountNumber": "string"
  },
  "ResultCode": 0,
  "ErrorCodes": []
}
```

---

### 15. ListTransferRecords

**`POST /api/V1/ListTransferRecords`**

Consulta el historial de transferencias enviadas al sistema.

> **Límite:** solo transferencias de los últimos **2 meses**.

**Request Body:** `application/json`

```json
{
  "TransferRef": "string",
  "DistributorRef": "string",
  "AccountNumber": "string",
  "Skip": 0,
  "Take": 0
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `TransferRef` | string | ❌ | Referencia de transferencia de Ding |
| `DistributorRef` | string | ❌ | Referencia del agente |
| `AccountNumber` | string | ❌ | Número de cuenta del destinatario |
| `Skip` | integer | ❌ | Registros a saltar (paginación) |
| `Take` | integer | ✅ | Registros a retornar |

**Usos:**
- Verificar el estado final de transferencias `Batch`.
- Consultar el `ProcessingState` actualizado de cualquier transferencia.
- Listar transferencias candidatas para cancelar (`CancelTransfers`).

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "TransferRecord": {
        "TransferId": {
          "TransferRef": "string",
          "DistributorRef": "string"
        },
        "SkuCode": "string",
        "Price": {
          "CustomerFee": 0,
          "DistributorFee": 0,
          "ReceiveValue": 0,
          "ReceiveCurrencyIso": "string",
          "ReceiveValueExcludingTax": 0,
          "TaxRate": 0,
          "TaxName": "string",
          "TaxCalculation": "string",
          "SendValue": 0,
          "SendCurrencyIso": "string"
        },
        "CommissionApplied": 0,
        "StartedUtc": "2019-08-24T14:15:22Z",
        "CompletedUtc": "2019-08-24T14:15:22Z",
        "ProcessingState": "string",
        "ReceiptText": "string",
        "ReceiptParams": {
          "property1": "string",
          "property2": "string"
        },
        "AccountNumber": "string"
      },
      "ResultCode": 0,
      "ErrorCodes": []
    }
  ],
  "ThereAreMoreItems": true
}
```

---

### 16. CancelTransfers

**`POST /api/V1/CancelTransfers`**

Intenta cancelar transferencias por sus `TransferId`.

> No toda transferencia puede ser cancelada. El resultado indica el `ProcessingState` final: si es `Cancelled`, fue exitosa y el saldo fue compensado.

**Flujo recomendado:**
1. Llamar a `ListTransferRecords` para obtener los `TransferId` que se desean cancelar.
2. Llamar a `CancelTransfers` con esos IDs.

**Request Body:** `application/json` (array)

```json
[
  {
    "TransferId": {
      "TransferRef": "string",
      "DistributorRef": "string"
    },
    "BatchItemRef": "string"
  }
]
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `TransferId.TransferRef` | string | ✅ | Referencia de transferencia de Ding |
| `TransferId.DistributorRef` | string | ✅ | Referencia del agente |
| `BatchItemRef` | string | ✅ | Referencia única del ítem en el lote |

**Respuesta 200:**

```json
{
  "Items": [
    {
      "TransferId": {
        "TransferRef": "string",
        "DistributorRef": "string"
      },
      "ProcessingState": "string",
      "BatchItemRef": "string",
      "ResultCode": 0,
      "ErrorCodes": []
    }
  ],
  "ResultCode": 0,
  "ErrorCodes": []
}
```

---

### 17. GetErrorCodeDescriptions

**`GET /api/V1/GetErrorCodeDescriptions`**

Devuelve descripciones legibles para los códigos de error.

> Ding no eliminará códigos de error existentes, pero puede agregar nuevos. Los mensajes están orientados al agente, **no al usuario final**.

**Respuesta 200:**

```json
{
  "ResultCode": 0,
  "ErrorCodes": [],
  "Items": [
    {
      "Message": "string",
      "Code": "string"
    }
  ]
}
```

---

## Modelos de Datos

### Price (Precio)

```json
{
  "CustomerFee": 0,
  "DistributorFee": 0,
  "ReceiveValue": 0,
  "ReceiveCurrencyIso": "string",
  "ReceiveValueExcludingTax": 0,
  "TaxRate": 0,
  "TaxName": "string",
  "TaxCalculation": "string",
  "SendValue": 0,
  "SendCurrencyIso": "string"
}
```

### TransferId

```json
{
  "TransferRef": "string",
  "DistributorRef": "string"
}
```

### ErrorCode

```json
{
  "Code": "string",
  "Context": "string"
}
```

### Setting

```json
{
  "Name": "string",
  "Value": "string"
}
```

### SettingDefinition

```json
{
  "Name": "string",
  "Description": "string",
  "IsMandatory": true
}
```

### TransferRecord

```json
{
  "TransferId": { "TransferRef": "string", "DistributorRef": "string" },
  "SkuCode": "string",
  "Price": { ... },
  "CommissionApplied": 0,
  "StartedUtc": "2019-08-24T14:15:22Z",
  "CompletedUtc": "2019-08-24T14:15:22Z",
  "ProcessingState": "string",
  "ReceiptText": "string",
  "ReceiptParams": { "pin": "string", "providerRef": "string" },
  "AccountNumber": "string"
}
```

---

## Estados de Procesamiento

| Estado | Descripción |
|--------|-------------|
| `Submitted` | Transferencia recibida en el sistema. Para Batch, es el estado inicial normal. |
| `Processing` | El sistema está procesando activamente la transferencia. |
| `Completed` | Transferencia finalizada con éxito. |
| `Failed` | Transferencia finalizada con error. |
| `Cancelled` | Transferencia cancelada exitosamente. El saldo fue compensado. |
| `Cancelling` | Solicitud de cancelación enviada al proveedor, en proceso. |

---

## Flujos Recomendados

### Flujo básico — recarga de saldo móvil

```
1. GetAccountLookup(accountNumber)
   → CountryIso, ProviderCode, RegionCode

2. GetProducts(countryIsos, providerCodes, regionCodes)
   → Lista de SkuCodes disponibles con precios Min/Max

3. EstimatePrices(SkuCode, SendValue o ReceiveValue)
   → Precio estimado a mostrar al usuario

4. SendTransfer(SkuCode, SendValue, AccountNumber, DistributorRef)
   → TransferRef + ProcessingState

5. Si ProcessingMode == "Batch":
   → ListTransferRecords(TransferRef) hasta que ProcessingState != "Submitted"
```

### Flujo con LookupBills (pago de facturas)

```
1. GetProducts → verificar LookupBillsRequired == true

2. LookupBills(SkuCode, AccountNumber)
   → BillRef + Price

3. SendTransfer(SkuCode, SendValue, AccountNumber, DistributorRef, BillRef)
```

### Flujo de validación UAT (sin dinero real)

```
1. GetProducts → obtener UatNumber del producto

2. SendTransfer(SkuCode, SendValue, UatNumber, DistributorRef, ValidateOnly: false)
   → Transacción en modo test, sin descuento de saldo, sin transacción real
```

### Flujo de validación previa

```
1. SendTransfer(SkuCode, SendValue, AccountNumber, DistributorRef, ValidateOnly: true)
   → Valida parámetros y número de cuenta sin cobrar
   → El precio retornado es una estimación

2. Si ResultCode == OK → proceder con ValidateOnly: false
```

---

## FAQ operativa de alto impacto (resumen)

Estas preguntas aparecen de forma recurrente en `https://www.dingconnect.com/Api/Faq` y afectan directamente el diseño del plugin:

1. Diferencia exacta entre `ValidateOnly: true` y `ValidateOnly: false`.
2. Uso de `Settings` y `DistributorRef` para trazabilidad e idempotencia.
3. Uso de `BatchItemRef` en requests por lotes (`EstimatePrices`, `CancelTransfers`).
4. Qué campos deben mapearse en frontend para completar integración y sign-off.
5. Qué hacer ante timeout/no respuesta de `SendTransfer` (usar `ListTransferRecords`).
6. Cómo ejecutar UAT y casos de error controlados.
7. Si se puede guardar catálogo localmente y con qué frecuencia actualizarlo.
8. Dónde consultar códigos de error/contexto y logos de operadores.
9. Cómo aplicar whitelisting de IP/DNS a credenciales de API.

Para respuestas amplias con contexto de implementación WordPress ver:

- `Documentación/BASE_CONOCIMIENTO_API_DINGCONNECT_COMPLETA.md`
- `Documentación/GUIA_TECNICA_DING_CONNECT.md`

---

*Documentación actualizada y validada contra Methods/Description/FAQ de DingConnect (21-04-2026).*
