# Revisión Exhaustiva del Flujo Actual: catálogo, bundles guardados y landings

## Objetivo

Este documento describe el flujo real implementado hoy en el plugin WordPress `dingconnect-recargas`, desde la búsqueda en API y alta manual, hasta la persistencia de bundles, creación de landings, resolución del shortcode público y salida operativa hacia DingConnect o WooCommerce.

La lectura correcta es esta:

1. El camino `live API` es mucho más rico que el camino `bundle guardado`.
2. Las landings hoy filtran y ordenan bundles correctamente, pero heredan la pobreza de datos del bundle guardado si ese bundle no conserva metadatos del producto original.
3. Si quieres “sacarle provecho” a la información del API por producto, el punto crítico no es el shortcode: es la capa de persistencia del bundle.

## Diagrama Mermaid

El diagrama importable en Excalidraw está en [Documentación/DIAGRAMA_FLUJO_ACTUAL_CATALOGO_BUNDLES_LANDINGS.mmd](/x:/Proyectos/DingConnect/Documentación/DIAGRAMA_FLUJO_ACTUAL_CATALOGO_BUNDLES_LANDINGS.mmd).

## Resumen ejecutivo

- `Catálogo y alta` trabaja con un item AJAX normalizado desde `GetProducts`, pero al guardarlo como bundle solo conserva un subconjunto del producto live.
- `dc_recargas_bundles` es la base del catálogo curado y del fallback, pero hoy no preserva varios campos ricos de DingConnect.
- `dc_recargas_landing_shortcodes` no guarda un `bundle_order` separado; el orden real se persiste implícitamente en el orden final de `bundle_ids`.
- La personalización visual de landing sí queda embebida dentro del mismo registro de landing, en `customization`, y se guarda por REST administrativo `dc-recargas/v1`.
- `/products` tiene dos contratos prácticos: `source=saved` y `source=dingconnect`. El segundo es claramente más completo.
- El frontend solo activa flujos avanzados cuando el producto trae `ProviderCode`, `IsRange`, `LookupBillsRequired` o `SettingDefinitions`. En bundles guardados esos flags llegan apagados o vacíos.

## Flujo real por etapas

### 1. Catálogo live en admin

La subpestaña `Buscar en API` llama a `dc_fetch_api_products`, que a su vez consulta `GetProducts` por país. Cada producto se reduce a este shape operativo para admin:

| Campo admin AJAX | Origen DingConnect | Notas |
| --- | --- | --- |
| `country_iso` | selector admin | No viene del producto; lo fija la búsqueda. |
| `sku_code` | `SkuCode` | Se conserva. |
| `operator` | `ProviderName` o fallback `ProviderCode` | Si no viene nombre, usa código. |
| `product_type` | `ProductType` | Se guarda luego en `product_type_raw`. |
| `label` | `DefaultDisplayText` o `SkuCode` | Se usa sobre todo para UI. |
| `send_value` | `Minimum.SendValue` | En admin se usa como coste DIN base. |
| `send_currency_iso` | `Minimum.SendCurrencyIso` | Default visual posterior: `USD` o `EUR` según el flujo. |
| `receive` | `Benefits[]` o `DefaultDisplayText` | Se convierte en copy humano del beneficio. |
| `validity` | `ValidityPeriodIso` | Luego pasa a `validity_raw`. |
| `package_group` | heurística local | No viene de DingConnect; se clasifica en admin. |
| `package_group_label` | mapa local | Solo UI. |
| `catalog_source` | fijo `api` | Solo UI/trazabilidad. |

### 2. Paso de “producto live” a “alta manual”

Cuando haces doble click sobre un producto live, `fillManualForm(item)` rellena el formulario manual así:

| Campo formulario manual | Valor cargado |
| --- | --- |
| `country_iso` | `item.country_iso` |
| `label` | `item.operator + ' - ' + (item.receive \/ item.label \/ item.sku_code)` |
| `sku_code` | `item.sku_code` |
| `send_value` | `item.send_value` |
| `send_currency_iso` | `item.send_currency_iso` |
| `public_price` | vacío |
| `public_price_currency` | `EUR` |
| `provider_name` | `item.operator` |
| `description` | `item.receive` o `item.label` |
| hidden `package_family` | `item.package_group` |
| hidden `product_type_raw` | `item.product_type` |
| hidden `validity_raw` | `item.validity` |

Aquí aparece el primer recorte fuerte: el formulario manual NO recibe ni persiste automáticamente `ProviderCode`, `Benefits[]`, `DescriptionMarkdown`, `ReadMoreMarkdown`, `LookupBillsRequired`, `SettingDefinitions`, `ValidationRegex`, `LogoUrl`, `CustomerCareNumber`, `PaymentTypes` ni `RegionCodes`.

### 3. Persistencia del bundle guardado

`handle_add_bundle()` y `handle_update_bundle()` terminan guardando en `dc_recargas_bundles` este shape real:

| Campo bundle | Cómo se obtiene |
| --- | --- |
| `id` | `uniqid('bundle_', true)` |
| `country_iso` | input admin uppercased |
| `label` | input admin |
| `sku_code` | input admin |
| `send_value` | float |
| `send_currency_iso` | input admin, default `USD` |
| `public_price` | float |
| `public_price_currency` | input admin, default `EUR` |
| `package_family` | `normalize_package_family(package_family, product_type_raw)` |
| `product_type_raw` | hidden precargado o input indirecto |
| `validity_raw` | hidden precargado |
| `validity_days` | derivado por `parse_validity_days()` |
| `provider_name` | input admin |
| `description` | input admin |
| `is_active` | checkbox |

Observaciones críticas:

- `provider_code` no se guarda desde el alta manual normal.
- `public_price_currency` sí se guarda, pero después no sale correctamente reflejada en el contrato `saved` de `/products`.
- Se permiten bundles duplicados por `country_iso + sku_code`; la validación de duplicados está desactivada.

### 4. Creación y persistencia de landing

La landing se construye desde bundles guardados, no desde productos live.

`handle_add_landing_shortcode()` y `handle_update_landing_shortcode()` hacen esto:

1. Recogen `landing_name`, `landing_key`, `landing_title`, `landing_subtitle`, `bundle_ids[]`, `featured_bundle_id` y `bundle_order` del DOM.
2. Validan que los `bundle_ids` existan en `dc_recargas_bundles`.
3. Reordenan SOLO los bundles seleccionados con `order_selected_bundles()`.
4. Derivan `country_iso` automáticamente:
   - si todos los bundles elegidos comparten país, guardan ese ISO.
   - si hay más de un país, guardan `''`.
5. Validan que `featured_bundle_id` esté dentro de `bundle_ids`.
6. Generan `key` única si no existe o si colisiona.

Shape real persistido en `dc_recargas_landing_shortcodes`:

| Campo landing | Origen |
| --- | --- |
| `id` | `uniqid('landing_', true)` |
| `name` | input admin |
| `key` | input admin o `sanitize_title(name)` + unicidad |
| `title` | input admin |
| `subtitle` | input admin |
| `country_iso` | derivado desde bundles |
| `bundle_ids` | array ordenado final |
| `featured_bundle_id` | radio válido dentro de `bundle_ids` |
| `created_at` | `current_time('mysql')` |
| `updated_at` | solo en edición |
| `cloned_from` | solo en duplicado |
| `customization` | se añade luego por REST administrativo |

Punto importante: el orden NO queda como `bundle_order` guardado aparte. El orden efectivo queda codificado en el array final `bundle_ids`.

### 5. Personalización de landing

La personalización visual no va por formulario clásico; se guarda por `wp.apiFetch()` hacia:

- `POST /wp-json/dc-recargas/v1/save-shortcode-customization`
- `GET /wp-json/dc-recargas/v1/get-shortcode-customization?key=...`

El payload guardado dentro del mismo registro de landing es:

| Campo customization | Uso posterior |
| --- | --- |
| `max_width` | ancho máximo del contenedor |
| `bg_color` | fondo de la card |
| `primary_color` | botones / foco / selección |
| `text_color` | títulos / textos clave |
| `border_radius` | radios |
| `padding` | espaciado interior |
| `shadow_intensity` | sombra predefinida |

### 6. Resolución del shortcode público

`render_shortcode()` toma `landing_key`, carga la landing y fusiona:

| Dato de entrada | Resolución final |
| --- | --- |
| `atts.bundles` | se suma a `config.bundle_ids` |
| `atts.country` | tiene prioridad sobre `config.country_iso` |
| `atts.title/subtitle` | si usan el texto por defecto, se sustituyen por los de la landing |
| `featured_bundle_id` | se conserva solo si sigue estando en `bundle_ids` |
| `default_country_iso` | `atts.country` o `config.country_iso` o país único disponible |
| `available_countries` | se calcula desde `dc_recargas_bundles` filtrados por `bundle_ids` o activos globales |

Lo que el frontend recibe en HTML/data attributes:

| `data-*` | Contenido |
| --- | --- |
| `data-allowed-bundle-ids` | lista CSV de bundles permitidos |
| `data-featured-bundle-id` | bundle destacado |
| `data-default-country-iso` | país inicial |
| `data-available-countries` | países disponibles serializados |

Además, `DC_RECARGAS_DATA` recibe `restBase`, `nonce`, catálogo de países y estado WooCommerce.

### 7. `/products`: dos contratos distintos

#### Camino A: `source = saved`

Si hay bundles guardados que aplican, `filter_bundles_by_country()` devuelve este shape:

| Campo REST saved | Fuente real |
| --- | --- |
| `BundleId` | `bundle.id` |
| `SkuCode` | `bundle.sku_code` |
| `ProviderCode` | `bundle.provider_code` si existiera, normalmente vacío |
| `ProviderName` | `bundle.provider_name` |
| `ProductType` | `bundle.product_type_raw` |
| `SendValue` | `bundle.send_value` |
| `SendCurrencyIso` | `bundle.send_currency_iso` |
| `ReceiveValue` | `bundle.public_price` o fallback `send_value` |
| `ReceiveCurrencyIso` | `bundle.send_currency_iso` |
| `ReceiveValueExcludingTax` | `bundle.public_price` o fallback `send_value` |
| `DefaultDisplayText` / `DisplayText` | `bundle.label` |
| `Description` / `AdditionalInformation` | `bundle.description` |
| `CountryIso` | `bundle.country_iso` |
| `ValidityPeriodIso` | `bundle.validity_raw` |
| `IsRange` | siempre `false` |
| `LookupBillsRequired` | siempre `false` |
| `SettingDefinitions` | siempre `[]` |

Este contrato sirve para catálogo curado, pero aplana el producto.

#### Camino B: `source = dingconnect`

Si no hay bundles saved aplicables, `normalize_products_for_frontend()` devuelve un contrato mucho más rico:

| Campo live | Fuente / enriquecimiento |
| --- | --- |
| `ProviderCode`, `ProviderName` | producto + `GetProviders` |
| `CountryIso`, `RegionCode`, `RegionCodes` | producto + proveedor |
| `SendValue`, `ReceiveValue`, `ReceiveValueExcludingTax`, min/max, fees, taxes | `extract_product_price()` |
| `DefaultDisplayText`, `DisplayText` | producto + `GetProductDescriptions` |
| `Description` | `Description` o `AdditionalInformation` o `DescriptionMarkdown` o beneficios |
| `DescriptionMarkdown`, `ReadMoreMarkdown` | `GetProductDescriptions` |
| `Benefits`, `ValidityPeriodIso` | producto |
| `RedemptionMechanism`, `ProcessingMode` | producto |
| `LookupBillsRequired` | producto |
| `SettingDefinitions[]` | producto normalizado |
| `ValidationRegex`, `CustomerCareNumber`, `LogoUrl`, `PaymentTypes` | `GetProviders` |
| `UatNumber` | producto |
| `IsRange` | calculado por min/max |

Aquí está el verdadero valor del API por producto.

### 8. Frontend: qué usa realmente

El frontend aprovecha estas propiedades cuando vienen en el contrato del producto:

| Propiedad | Uso real |
| --- | --- |
| `ProviderCode` | consulta `/provider-status` antes de confirmar |
| `ValidationRegex` | valida el número contra el proveedor |
| `IsRange` | muestra input de importe y llama a `/estimate-prices` |
| `LookupBillsRequired` | obliga a consultar `/lookup-bills` |
| `SettingDefinitions[]` | genera inputs dinámicos requeridos |
| `CustomerCareNumber` | lo muestra en confirmación |
| `RedemptionMechanism`, `ProductType` | cambian el copy final y el tipo de flujo |
| `ReceiveValue`, `ReceiveValueExcludingTax`, `Tax*`, `CustomerFee` | enriquecen confirmación y estimación |
| `featured_bundle_id` | resalta visualmente el paquete |

Cuando el producto viene de bundle guardado, casi todo esto desaparece o llega neutro.

### 9. Salida a `/transfer` o `/add-to-cart`

#### Directo

Payload que sale al backend REST:

| Campo | Fuente frontend |
| --- | --- |
| `account_number` | número normalizado |
| `sku_code` | bundle seleccionado |
| `send_value` | importe actual o estimado |
| `send_currency_iso` | producto seleccionado |
| `settings[]` | inputs dinámicos capturados |
| `bill_ref` | selección de factura |

Luego `transfer()` genera `DistributorRef` si hace falta, normaliza teléfono sin `+`, aplica política `ValidateOnly` desde options y llama a `SendTransfer`.

#### WooCommerce

Además del payload anterior, el frontend manda:

- `country_iso`
- `provider_name`
- `bundle_label`
- `product_type`
- `redemption_mechanism`
- `lookup_bills_required`
- `customer_care_number`
- `is_range`

Todo eso se persiste en cart item data y luego en order item meta `_dc_*`, para que el flujo post-pago conserve contexto operativo.

## Derivaciones y conversiones clave

### Conversiones importantes

| De | A | Comentario |
| --- | --- | --- |
| `ProviderName/ProviderCode` live | `operator` admin | para tabla de catálogo |
| `ProductType` live | `product_type_raw` bundle | persistencia operativa mínima |
| `package_group` admin | `package_family` bundle | clasificación comercial local |
| `ValidityPeriodIso` | `validity_raw` + `validity_days` | parte texto, parte derivado |
| `bundle_ids` DOM ordenado | `landing.bundle_ids` | este array ES el orden persistido |
| `public_price` | `ReceiveValue` en `source=saved` | uso comercial/display |
| `public_price_currency` | sin exposición real en `source=saved` | gap actual |
| `account_number` con `+` | `AccountNumber` numérico | sanitización backend obligatoria |
| `Settings[{Name,Value}]` frontend | `Settings` DingConnect | pasa casi directo |
| `bill_ref` frontend/WC | `BillRef` DingConnect | pasa casi directo |

## Gaps actuales que importan si quieres explotar mejor el API

### Gap 1: bundles curados demasiado pobres

Hoy, guardar un bundle implica perder o no fijar automáticamente:

- `ProviderCode`
- `LookupBillsRequired`
- `SettingDefinitions[]`
- `Benefits[]`
- `DescriptionMarkdown`
- `ReadMoreMarkdown`
- `ValidationRegex`
- `CustomerCareNumber`
- `LogoUrl`
- `PaymentTypes`
- `RegionCodes`
- `RedemptionMechanism`
- `ProcessingMode`
- `UatNumber`

Consecuencia: una landing basada en bundles curados no puede comportarse igual de bien que el catálogo live si el producto requiere reglas dinámicas reales.

### Gap 2: moneda pública guardada pero no servida

`public_price_currency` se guarda en admin, pero el contrato `saved` de `/products` responde `ReceiveCurrencyIso` con `send_currency_iso`. Eso mezcla “coste interno” con “precio público mostrado”.

### Gap 3: Provider status se puede saltar en bundles saved

`ensureProviderStatus()` solo corre si hay `ProviderCode`. Como el bundle guardado normalmente no lo persiste, el chequeo de estado del proveedor no se ejecuta en la mayoría de landings curadas.

### Gap 4: productos complejos quedan aplanados

Si mañana quieres sacarle partido a electricidad, bill payment, PIN/voucher, DTH o range products desde landings curadas, hoy te faltan metadatos persistidos para que ese comportamiento sobreviva al paso “live -> bundle”.

## Qué conservaría yo del API al guardar bundle

Si el objetivo es que el bundle guardado siga siendo “rico” y no solo “un SKU decorado”, el mínimo recomendable a persistir sería:

| Campo recomendado | Motivo |
| --- | --- |
| `provider_code` | provider status y trazabilidad real |
| `product_type_raw` | ya existe, mantenerlo |
| `redemption_mechanism` | copy y flujo por familia real |
| `processing_mode` | UX y soporte |
| `benefits[]` | beneficio real del producto |
| `lookup_bills_required` | activar flujo de facturas |
| `setting_definitions[]` | inputs dinámicos por producto |
| `validation_regex` | validación real de MSISDN/cuenta |
| `customer_care_number` | soporte visible |
| `logo_url` | branding del operador |
| `description_markdown` y `read_more_markdown` | copy enriquecido |
| `payment_types` | futuras reglas o UX |
| `region_codes` | filtros futuros |
| `price_shape` (`minimum`, `maximum`, tax, fees) | soporte real para rango y pricing avanzado |
| `public_price_currency` bien expuesta | consistencia comercial |

## Conclusión práctica para la siguiente iteración

Si tu intención es aprovechar el API producto a producto, el camino correcto no es rehacer el shortcode desde cero. El verdadero salto está en introducir una **capa de persistencia enriquecida del producto live** para que el bundle guardado conserve:

1. identidad técnica del producto,
2. reglas de validación,
3. capacidades dinámicas,
4. copy enriquecido,
5. y estructura de precio más fiel.

Con eso, las landings pasarían de ser un simple filtro visual de bundles a ser una proyección curada de productos DingConnect con casi toda su inteligencia intacta.