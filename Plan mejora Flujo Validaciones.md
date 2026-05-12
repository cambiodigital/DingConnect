He revisado el flujo actual del plugin y la documentación local basada en DingConnect. La brecha importante es esta: el plugin ya tiene `GetAccountLookup` en `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-api.php`, pero no lo usa al pulsar **Continuar**. El botón actualmente valida proveedor, regex, monto, settings y bill ref en `dingconnect-wp-plugin/dingconnect-recargas/assets/js/frontend.js`, pero no hace lookup remoto ni `SendTransfer ValidateOnly=true` antes de pasar a “Confirma tu recarga”.

La solución correcta es añadir un endpoint backend dedicado, por ejemplo:

`POST /wp-json/dingconnect/v1/precheck`

Ese endpoint debe ejecutarse al pulsar **Continuar**, antes de `buildConfirmStep()`, y repetir o verificar el precheck en `add-to-cart` para evitar bypass del frontend. NO debe reutilizar `/transfer`, porque en modo WooCommerce está bloqueado con razón: protege el flujo payment-first.

## Flujo Propuesto

1. Usuario introduce país, número y selecciona paquete.
2. Frontend valida formato básico y campos dinámicos.
3. Al pulsar **Continuar**, frontend llama a `/precheck`.
4. Backend ejecuta, en este orden:
   - `GetAccountLookup`
   - `GetProducts`
   - validación de producto/proveedor/monto
   - saldo/límites del vendedor
   - `SendTransfer` con `ValidateOnly=true`
5. Si todo pasa, backend devuelve `ok: true` y un `precheck_token` temporal.
6. Frontend muestra “Confirma tu recarga”.
7. Al pulsar “Proceder al pago”, `add-to-cart` exige `precheck_token` válido o repite la validación.
8. Solo entonces se redirige a WooCommerce.
9. Tras pago confirmado, WooCommerce ejecuta `SendTransfer` real con `ValidateOnly=false` si el modo productivo lo permite.

Regla central: si falla cualquier validación, NO se muestra confirmación, NO se añade al carrito y NO se cobra.

---

## 1) Validaciones Frontend y Backend

### Frontend

| Validación | Momento | Acción si falla |
|---|---|---|
| País seleccionado | Antes de buscar/continuar | Mostrar “Selecciona el país del destinatario.” |
| Número no vacío | Antes de buscar/continuar | Mostrar error inline |
| Formato básico del número | Al escribir y al continuar | Bloquear Continue |
| Longitud mínima/máxima por país | Al escribir | Sugerir formato correcto |
| Regex del producto (`ValidationRegex`) | Al seleccionar paquete y continuar | Bloquear paquete |
| Producto seleccionado | Antes de continuar | Mostrar “Selecciona un paquete.” |
| Monto positivo | Al editar importe | Bloquear Continue |
| Monto dentro de rango visible | Al editar importe | Mostrar mínimo/máximo |
| `SettingDefinitions` obligatorios | Antes de continuar | Marcar campos faltantes |
| `LookupBillsRequired` con `BillRef` | Antes de continuar | Obligar consulta/selección |
| `EstimatePrices` vigente | En productos de rango | Invalidar si cambia monto/paquete |
| Provider status | Antes de continuar | Bloquear si proveedor no procesa |
| Precheck remoto `/precheck` | Al pulsar Continue | Bloquear confirmación si falla |
| Doble clic / concurrencia | Durante validación | Deshabilitar botón y usar spinner |
| Timeout visual | > 3-5 segundos | Mostrar “Seguimos validando con el operador...” |

El frontend sirve para UX y ahorro de llamadas, pero NO es seguridad. Todo lo crítico debe repetirse en backend.

### Backend

| Validación | Endpoint/Fuente | Acción si falla |
|---|---|---|
| Nonce / permisos REST | WordPress REST | 401/403 |
| Rate limit por IP/sesión | Plugin | 429 |
| Sanitización de número | Backend | 400 |
| Normalización `AccountNumber` | Backend | Convertir a formato Ding esperado |
| `GetAccountLookup` | DingConnect | Bloquear si vacío/error |
| ProviderCode esperado | Lookup vs producto | Bloquear mismatch |
| País/región compatible | Lookup vs producto | Bloquear mismatch |
| `GetProducts` por número/proveedor/país | DingConnect | Bloquear si no hay productos |
| SKU existe en productos válidos | DingConnect | Bloquear si no aparece |
| Producto activo en landing/bundle | Plugin | Bloquear si no pertenece |
| Monto permitido | Producto/bundle | Bloquear fuera de rango |
| `SettingDefinitions` | Producto Ding | Bloquear si falta valor |
| `LookupBillsRequired` | Ding + `BillRef` | Bloquear si falta factura |
| Saldo vendedor | `GetBalance` / cache corto | Bloquear si insuficiente |
| Límite operativo del vendedor | Config/plugin | Bloquear si excede |
| `SendTransfer ValidateOnly=true` | DingConnect | Bloquear si falla |
| Timeout/retry transitorio | Backend | Reintentar con backoff |
| Respuesta parcial | Backend | Tratar como no concluyente y bloquear |
| Token de precheck | Backend/session/transient | Exigir en `add-to-cart` |

---

## 2) Pseudocódigo y Ejemplos

### Secuencia Backend Recomendada

```text
POST /precheck
  1. Validar payload local
  2. Normalizar account_number
  3. GetAccountLookup(account_number)
     - si vacío: NUMBER_INVALID
     - extraer CountryIso, ProviderCode, RegionCode
  4. GetProducts(account_number o filtros provider/country)
     - si vacío: NO_PRODUCTS
     - buscar SkuCode seleccionado
  5. Validar compatibilidad:
     - ProviderCode coincide
     - CountryIso coincide
     - RegionCode compatible si aplica
     - producto pertenece a landing/bundle permitido
  6. Validar monto:
     - fijo: monto == SendValue esperado
     - rango: MinimumSendValue <= monto <= MaximumSendValue
  7. Validar settings/bill_ref
  8. Validar saldo/límites
  9. SendTransfer ValidateOnly=true
     - si ResultCode OK: emitir precheck_token
     - si error: mapear y bloquear
  10. Responder ok:true
```

### Request Frontend al Plugin

```http
POST /wp-json/dingconnect/v1/precheck
Content-Type: application/json
X-WP-Nonce: <nonce>
```

```json
{
  "account_number": "+5355555555",
  "country_iso": "CU",
  "sku_code": "CU_CUBACEL_10",
  "bundle_id": "bundle_123",
  "send_value": 10,
  "send_currency_iso": "USD",
  "provider_code": "CUBACEL_CU",
  "settings": [],
  "bill_ref": "",
  "landing_key": "cuba-mayo-2026"
}
```

### Response OK del Plugin

```json
{
  "ok": true,
  "code": "PRECHECK_OK",
  "message": "La recarga fue validada correctamente.",
  "precheck_token": "dc_precheck_9fd2e1...",
  "expires_in": 300,
  "normalized": {
    "account_number": "5355555555",
    "country_iso": "CU",
    "provider_code": "CUBACEL_CU",
    "sku_code": "CU_CUBACEL_10",
    "send_value": 10,
    "send_currency_iso": "USD"
  }
}
```

### Response Bloqueante

```json
{
  "ok": false,
  "code": "PRODUCT_NOT_AVAILABLE",
  "message": "No hay paquetes disponibles para este número. Selecciona otro paquete o número.",
  "retryable": false,
  "details": {
    "provider_code": "OTHER_PROVIDER",
    "requested_sku": "CU_CUBACEL_10"
  }
}
```

### GetAccountLookup hacia DingConnect

```http
GET /api/V1/GetAccountLookup?AccountNumber=5355555555
Authorization: <api-key-o-oauth>
```

Respuesta esperada:

```json
{
  "CountryIso": "CU",
  "AccountNumberNormalized": "5355555555",
  "Items": [
    {
      "ProviderCode": "CUBACEL_CU",
      "RegionCode": "CU"
    }
  ],
  "ResultCode": 0,
  "ErrorCodes": []
}
```

Si no hay coincidencia real:

```json
{
  "CountryIso": "",
  "AccountNumberNormalized": "",
  "Items": [],
  "ResultCode": 0,
  "ErrorCodes": []
}
```

### GetProducts hacia DingConnect

```http
GET /api/V1/GetProducts?accountNumber=5355555555
Authorization: <api-key-o-oauth>
```

Respuesta simplificada:

```json
{
  "Items": [
    {
      "SkuCode": "CU_CUBACEL_10",
      "ProviderCode": "CUBACEL_CU",
      "ProductType": "Mobile",
      "DefaultDisplayText": "Cubacel 10 USD",
      "MinimumSendValue": 10,
      "MaximumSendValue": 10,
      "Price": {
        "SendValue": 10,
        "SendCurrencyIso": "USD",
        "ReceiveValue": 10,
        "ReceiveCurrencyIso": "CUC"
      },
      "ValidationRegex": "^[0-9]{8,12}$",
      "LookupBillsRequired": false,
      "SettingDefinitions": []
    }
  ],
  "ResultCode": 0,
  "ErrorCodes": []
}
```

### SendTransfer ValidateOnly

```http
POST /api/V1/SendTransfer
Content-Type: application/json
Authorization: <api-key-o-oauth>
```

```json
{
  "SkuCode": "CU_CUBACEL_10",
  "SendValue": 10,
  "SendCurrencyIso": "USD",
  "AccountNumber": "5355555555",
  "DistributorRef": "PRECHECK-20260512-abc123",
  "Settings": [],
  "ValidateOnly": true,
  "BillRef": ""
}
```

Respuesta OK:

```json
{
  "TransferRecord": {
    "SkuCode": "CU_CUBACEL_10",
    "Price": {
      "SendValue": 10,
      "SendCurrencyIso": "USD",
      "ReceiveValue": 10,
      "ReceiveCurrencyIso": "CUC"
    },
    "ProcessingState": "Validated",
    "AccountNumber": "5355555555"
  },
  "ResultCode": 0,
  "ErrorCodes": []
}
```

Nota importante: con `ValidateOnly=true` no debe asumirse `TransferRef`; DingConnect indica que no ejecuta la recarga real ni descuenta saldo.

---

## PHP para Plugin WooCommerce

### Endpoint REST `precheck`

```php
register_rest_route('dingconnect/v1', '/precheck', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => [$this, 'precheck_recharge'],
    'permission_callback' => '__return_true',
]);
```

```php
public function precheck_recharge(WP_REST_Request $request) {
    if (!$this->check_rate_limit('precheck', 10)) {
        return new WP_REST_Response([
            'ok' => false,
            'code' => 'RATE_LIMIT',
            'message' => 'Hemos alcanzado el límite de solicitudes. Intenta en unos segundos.',
            'retryable' => true,
        ], 429);
    }

    $params = $request->get_json_params();

    $account = $this->sanitize_phone($params['account_number'] ?? '');
    $country = strtoupper(sanitize_text_field($params['country_iso'] ?? ''));
    $sku = sanitize_text_field($params['sku_code'] ?? '');
    $bundle_id = sanitize_text_field($params['bundle_id'] ?? '');
    $send_value = (float) ($params['send_value'] ?? 0);
    $currency = strtoupper(sanitize_text_field($params['send_currency_iso'] ?? ''));
    $settings = $this->sanitize_settings($params['settings'] ?? []);
    $bill_ref = sanitize_text_field($params['bill_ref'] ?? '');

    if (!$account || !$sku || $send_value <= 0) {
        return $this->precheck_error('MISSING_FIELDS', 'Datos incompletos para validar la recarga.', 400, false);
    }

    $lookup = $this->with_retries(function () use ($account) {
        return $this->api->get_account_lookup($account);
    });

    if (is_wp_error($lookup)) {
        return $this->map_ding_error_to_response($lookup, 'LOOKUP_FAILED');
    }

    $lookup_items = $this->extract_items($lookup);
    if (empty($lookup_items)) {
        return $this->precheck_error(
            'NUMBER_INVALID',
            'El número introducido no es válido o no está registrado para recargas. Verifica el número e inténtalo de nuevo.',
            400,
            false
        );
    }

    $provider_code = sanitize_text_field((string) ($lookup_items[0]['ProviderCode'] ?? ''));
    $lookup_country = strtoupper(sanitize_text_field((string) ($lookup['CountryIso'] ?? $country)));

    $products = $this->with_retries(function () use ($account, $country, $provider_code) {
        return $this->api->get_products_catalog([
            'accountNumber' => $account,
            'countryIsos' => [$country],
            'providerCodes' => [$provider_code],
        ]);
    });

    if (is_wp_error($products)) {
        return $this->map_ding_error_to_response($products, 'PRODUCTS_FAILED');
    }

    $items = $this->extract_items($products);
    if (empty($items)) {
        return $this->precheck_error(
            'NO_PRODUCTS',
            'No hay paquetes disponibles para este número. Selecciona otro paquete o número.',
            400,
            false
        );
    }

    $product = $this->find_product_by_sku($items, $sku);
    if (!$product) {
        return $this->precheck_error(
            'PRODUCT_NOT_AVAILABLE',
            'No hay paquetes disponibles para este número. Selecciona otro paquete o número.',
            400,
            false
        );
    }

    if (!$this->product_matches_lookup($product, $provider_code, $lookup_country)) {
        return $this->precheck_error(
            'OPERATOR_NOT_SUPPORTED',
            'El operador del número no admite este tipo de recarga desde nuestra plataforma.',
            400,
            false
        );
    }

    $amount_validation = $this->validate_send_value_against_bundle($sku, $country, $send_value, $bundle_id);
    if (is_wp_error($amount_validation)) {
        return $this->precheck_error(
            'AMOUNT_NOT_ALLOWED',
            $amount_validation->get_error_message(),
            400,
            false
        );
    }

    if (!$this->is_amount_allowed_for_product($product, $send_value)) {
        return $this->precheck_error(
            'AMOUNT_NOT_ALLOWED',
            $this->build_amount_range_message($product),
            400,
            false
        );
    }

    $balance_ok = $this->seller_has_available_balance($send_value, $currency);
    if (!$balance_ok) {
        $this->api->log_operational_event('precheck_insufficient_seller_balance', [
            'status' => 'error',
            'account_number' => $account,
            'sku_code' => $sku,
            'send_value' => $send_value,
            'currency' => $currency,
        ]);

        return $this->precheck_error(
            'SELLER_BALANCE_INSUFFICIENT',
            'No podemos procesar esta recarga en este momento. Intenta más tarde.',
            503,
            false
        );
    }

    $distributor_ref = 'PRECHECK-' . gmdate('YmdHis') . '-' . wp_generate_password(8, false, false);

    $validate = $this->with_retries(function () use ($account, $sku, $send_value, $currency, $settings, $bill_ref, $distributor_ref) {
        return $this->api->send_transfer([
            'DistributorRef' => $distributor_ref,
            'AccountNumber' => $account,
            'SkuCode' => $sku,
            'SendValue' => $send_value,
            'SendCurrencyIso' => $currency,
            'Settings' => $settings,
            'BillRef' => $bill_ref,
            'ValidateOnly' => true,
        ]);
    });

    if (is_wp_error($validate)) {
        return $this->map_ding_error_to_response($validate, 'VALIDATE_ONLY_FAILED');
    }

    if (!$this->is_ding_success($validate)) {
        $code = $this->extract_first_error_code($validate) ?: 'VALIDATE_ONLY_FAILED';
        return $this->precheck_error($code, $this->user_message_for_code($code), 400, $this->is_retryable_code($code));
    }

    $token = 'dc_precheck_' . wp_generate_password(32, false, false);
    set_transient($token, [
        'account_number' => $account,
        'country_iso' => $country,
        'sku_code' => $sku,
        'bundle_id' => $bundle_id,
        'send_value' => $send_value,
        'send_currency_iso' => $currency,
        'provider_code' => $provider_code,
        'settings_hash' => md5(wp_json_encode($settings)),
        'bill_ref' => $bill_ref,
        'created_at' => time(),
    ], 5 * MINUTE_IN_SECONDS);

    return rest_ensure_response([
        'ok' => true,
        'code' => 'PRECHECK_OK',
        'message' => 'La recarga fue validada correctamente.',
        'precheck_token' => $token,
        'expires_in' => 300,
        'normalized' => [
            'account_number' => $account,
            'country_iso' => $country,
            'provider_code' => $provider_code,
            'sku_code' => $sku,
            'send_value' => $send_value,
            'send_currency_iso' => $currency,
        ],
    ]);
}
```

### Retry con Backoff

```php
private function with_retries(callable $operation, int $max_attempts = 3) {
    $delays_ms = [250, 750, 1500];
    $last_error = null;

    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $result = $operation();

        if (!is_wp_error($result)) {
            return $result;
        }

        $last_error = $result;

        if (!$this->is_transient_wp_error($result)) {
            return $result;
        }

        if ($attempt < $max_attempts - 1) {
            usleep($delays_ms[$attempt] * 1000);
        }
    }

    return $last_error ?: new WP_Error('dc_timeout', 'Error temporal en el servicio de recargas.', ['status' => 504]);
}
```

### Validar `precheck_token` en `add-to-cart`

```php
private function validate_precheck_token(array $params) {
    $token = sanitize_text_field((string) ($params['precheck_token'] ?? ''));
    if (!$token) {
        return new WP_Error('dc_precheck_required', 'Primero debemos validar la recarga antes de pasar al pago.', ['status' => 400]);
    }

    $snapshot = get_transient($token);
    if (!is_array($snapshot)) {
        return new WP_Error('dc_precheck_expired', 'La validación expiró. Vuelve a pulsar Continuar.', ['status' => 400]);
    }

    $same = $snapshot['account_number'] === $this->sanitize_phone($params['account_number'] ?? '')
        && $snapshot['sku_code'] === sanitize_text_field($params['sku_code'] ?? '')
        && (float) $snapshot['send_value'] === (float) ($params['send_value'] ?? 0)
        && $snapshot['settings_hash'] === md5(wp_json_encode($this->sanitize_settings($params['settings'] ?? [])))
        && $snapshot['bill_ref'] === sanitize_text_field((string) ($params['bill_ref'] ?? ''));

    if (!$same) {
        return new WP_Error('dc_precheck_mismatch', 'Los datos cambiaron después de la validación. Valida nuevamente.', ['status' => 400]);
    }

    return true;
}
```

---

## Node.js Express

```js
app.post('/validate-recharge', async (req, res) => {
  const input = normalizeInput(req.body);

  if (!input.accountNumber || !input.skuCode || input.sendValue <= 0) {
    return res.status(400).json({
      ok: false,
      code: 'MISSING_FIELDS',
      message: 'Datos incompletos para validar la recarga.',
      retryable: false
    });
  }

  try {
    const lookup = await retryTransient(() =>
      ding.getAccountLookup(input.accountNumber)
    );

    const lookupItems = extractItems(lookup);
    if (!lookupItems.length) {
      return res.status(400).json({
        ok: false,
        code: 'NUMBER_INVALID',
        message: 'El número introducido no es válido o no está registrado para recargas. Verifica el número e inténtalo de nuevo.',
        retryable: false
      });
    }

    const providerCode = lookupItems[0].ProviderCode;
    const countryIso = lookup.CountryIso || input.countryIso;

    const products = await retryTransient(() =>
      ding.getProducts({
        accountNumber: input.accountNumber,
        countryIsos: [countryIso],
        providerCodes: [providerCode]
      })
    );

    const items = extractItems(products);
    if (!items.length) {
      return res.status(400).json({
        ok: false,
        code: 'NO_PRODUCTS',
        message: 'No hay paquetes disponibles para este número. Selecciona otro paquete o número.',
        retryable: false
      });
    }

    const product = items.find(p => p.SkuCode === input.skuCode);
    if (!product) {
      return res.status(400).json({
        ok: false,
        code: 'PRODUCT_NOT_AVAILABLE',
        message: 'No hay paquetes disponibles para este número. Selecciona otro paquete o número.',
        retryable: false
      });
    }

    if (!productMatchesLookup(product, providerCode, countryIso)) {
      return res.status(400).json({
        ok: false,
        code: 'OPERATOR_NOT_SUPPORTED',
        message: 'El operador del número no admite este tipo de recarga desde nuestra plataforma.',
        retryable: false
      });
    }

    const amountCheck = isAmountAllowed(product, input.sendValue);
    if (!amountCheck.ok) {
      return res.status(400).json({
        ok: false,
        code: 'AMOUNT_NOT_ALLOWED',
        message: `El importe seleccionado no está permitido para este paquete. Elige un importe entre ${amountCheck.min} y ${amountCheck.max}.`,
        retryable: false
      });
    }

    const balance = await ding.getBalanceCached();
    if (!hasEnoughBalance(balance, input.sendValue, input.sendCurrencyIso)) {
      notifyAdmin('Saldo insuficiente para precheck', input);
      return res.status(503).json({
        ok: false,
        code: 'SELLER_BALANCE_INSUFFICIENT',
        message: 'No podemos procesar esta recarga en este momento. Intenta más tarde.',
        retryable: false
      });
    }

    const distributorRef = `PRECHECK-${Date.now()}-${crypto.randomUUID()}`;

    const validate = await retryTransient(() =>
      ding.sendTransfer({
        SkuCode: input.skuCode,
        SendValue: input.sendValue,
        SendCurrencyIso: input.sendCurrencyIso,
        AccountNumber: input.accountNumber,
        DistributorRef: distributorRef,
        Settings: input.settings || [],
        BillRef: input.billRef || undefined,
        ValidateOnly: true
      })
    );

    if (!isDingSuccess(validate)) {
      const code = firstDingErrorCode(validate) || 'VALIDATE_ONLY_FAILED';
      return res.status(isRetryable(code) ? 503 : 400).json({
        ok: false,
        code,
        message: userMessageForCode(code, product),
        retryable: isRetryable(code)
      });
    }

    const token = await precheckStore.save({
      accountNumber: input.accountNumber,
      skuCode: input.skuCode,
      sendValue: input.sendValue,
      sendCurrencyIso: input.sendCurrencyIso,
      providerCode,
      countryIso,
      settingsHash: hash(input.settings || []),
      billRef: input.billRef || '',
      expiresAt: Date.now() + 5 * 60 * 1000
    });

    return res.json({
      ok: true,
      code: 'PRECHECK_OK',
      message: 'La recarga fue validada correctamente.',
      precheck_token: token,
      expires_in: 300
    });
  } catch (err) {
    const mapped = mapTransportError(err);
    return res.status(mapped.status).json(mapped.body);
  }
});
```

### Helpers Node.js

```js
function isAmountAllowed(product, amount) {
  const fixed = Number(product?.Price?.SendValue ?? product.SendValue ?? 0);
  const min = Number(product.MinimumSendValue ?? fixed);
  const max = Number(product.MaximumSendValue ?? fixed);

  if (min > 0 && amount < min) return { ok: false, min, max };
  if (max > 0 && amount > max) return { ok: false, min, max };

  if (min === max && fixed > 0 && amount !== fixed) {
    return { ok: false, min: fixed, max: fixed };
  }

  return { ok: true, min, max };
}

async function retryTransient(fn, maxAttempts = 3) {
  const delays = [250, 750, 1500];
  let lastErr;

  for (let i = 0; i < maxAttempts; i++) {
    try {
      return await fn();
    } catch (err) {
      lastErr = err;
      if (!isTransientError(err)) throw err;
      if (i < maxAttempts - 1) {
        await new Promise(resolve => setTimeout(resolve, delays[i]));
      }
    }
  }

  throw lastErr;
}

function isTransientError(err) {
  return err.code === 'ETIMEDOUT'
    || err.code === 'ECONNRESET'
    || err.status === 408
    || err.status === 429
    || err.status >= 500;
}
```

---

## 3) Mapeo de Errores a Mensajes

| Código interno / Ding | Tipo | Reintentar | Mensaje usuario |
|---|---:|---:|---|
| `NUMBER_INVALID` | Validación | No | “El número introducido no es válido o no está registrado para recargas. Verifica el número e inténtalo de nuevo.” |
| `LOOKUP_EMPTY` | Validación | No | “No encontramos este número para recargas. Revisa el país y el número.” |
| `AccountNumberInvalid` | Ding | No | “El número introducido no es válido o no admite recargas.” |
| `AccountNumberFailedRegex` | Ding | No | “El formato del número no es válido para este operador.” |
| `InvalidRecipient` | Ding | No | “No es posible recargar este destinatario. Contacta soporte.” |
| `OPERATOR_NOT_SUPPORTED` | Validación | No | “El operador del número no admite este tipo de recarga desde nuestra plataforma.” |
| `PROVIDER_MISMATCH` | Validación | No | “El paquete seleccionado no corresponde al operador detectado para este número.” |
| `ProviderRefusedRequest` | Ding | Depende | “El operador rechazó la validación de este número. Verifica los datos.” |
| `NO_PRODUCTS` | Catálogo | No | “No hay paquetes disponibles para este número. Selecciona otro paquete o número.” |
| `PRODUCT_NOT_AVAILABLE` | Catálogo | No | “Este paquete ya no está disponible para el número indicado.” |
| `ProductUnavailable` | Ding | No | “El paquete seleccionado no está disponible en este momento.” |
| `AMOUNT_NOT_ALLOWED` | Monto | No | “El importe seleccionado no está permitido para este paquete. Elige un importe entre X y Y.” |
| `ParameterOutOfRange` | Ding | No | “El importe seleccionado está fuera del rango permitido.” |
| `LookupBillsRequired` | Flujo | No | “Debes consultar y seleccionar la factura antes de continuar.” |
| `BillRefInvalid` | Ding | No | “La referencia de factura ya no es válida. Vuelve a consultar la factura.” |
| `SettingRequired` | Flujo | No | “Completa los datos requeridos por el operador antes de continuar.” |
| `InsufficientBalance` | Operativo | No | “No podemos procesar esta recarga en este momento. Intenta más tarde.” |
| `SELLER_BALANCE_INSUFFICIENT` | Operativo | No | “No podemos procesar esta recarga en este momento. Intenta más tarde.” |
| `RateLimited` | Transitorio | Sí | “Hemos alcanzado el límite de solicitudes. Intenta en unos segundos.” |
| `ProviderTimedOut` | Transitorio | Sí | “Error temporal en el servicio de recargas. Intenta de nuevo en unos minutos.” |
| `TransientProviderError` | Transitorio | Sí | “El operador no respondió correctamente. Intenta nuevamente en unos minutos.” |
| `VALIDATE_ONLY_FAILED` | Validación remota | Depende | “No podemos confirmar la recarga con el operador en este momento. Intenta más tarde.” |
| `DING_5XX` | Transitorio | Sí | “Error temporal en el servicio de recargas. Intenta de nuevo en unos minutos.” |
| `DING_TIMEOUT` | Transitorio | Sí | “La validación tardó más de lo esperado. Intenta de nuevo en unos minutos.” |
| `PARTIAL_RESPONSE` | Indeterminado | Sí | “No pudimos confirmar la disponibilidad de la recarga. Intenta nuevamente.” |
| `PRECHECK_EXPIRED` | Sesión | Sí | “La validación expiró. Pulsa Continuar para validar nuevamente.” |
| `PRECHECK_MISMATCH` | Seguridad | No | “Los datos cambiaron después de la validación. Valida nuevamente antes de pagar.” |

Regla importante: los códigos técnicos de DingConnect no deben mostrarse crudos al cliente. Deben guardarse en logs para soporte y traducirse a mensajes accionables.

---

## 4) Recomendaciones UX para Errores

- Mostrar errores inline en la misma pantalla de selección, no en modal genérico.
- Mantener el usuario en el paso actual si falla `/precheck`; no avanzar a confirmación.
- Deshabilitar **Continuar** mientras valida para evitar doble solicitud.
- Cambiar texto del botón a “Validando con el operador...”.
- Si la validación tarda más de 3 segundos, mostrar: “Seguimos validando con el operador. No cierres esta pantalla.”
- Si hay timeout, permitir botón “Intentar de nuevo”.
- Si `GetAccountLookup` detecta operador distinto, sugerir: “Selecciona otro paquete compatible con este número.”
- Si no hay productos, ofrecer:
  - cambiar número
  - cambiar país
  - contactar soporte
- Si el monto está fuera de rango, mostrar rango exacto: “Mínimo X, máximo Y”.
- Si la sesión/precheck expira, no mandar al checkout; volver a validar.
- Si hay error operativo interno como saldo insuficiente, no decir “saldo insuficiente” al cliente; mostrar mensaje neutro y alertar admin.
- Si `ValidateOnly` falla por razón no recuperable, bloquear el pago. Esto es CLAVE: cobrar primero y descubrir después que Ding lo rechaza es mala arquitectura.

---

## 5) Pruebas Automáticas y Manuales

### Pruebas Automáticas Backend

1. `precheck` con payload incompleto → `400 MISSING_FIELDS`.
2. Número con formato inválido → `400 NUMBER_INVALID`.
3. `GetAccountLookup` vacío → bloquea.
4. `GetAccountLookup` con proveedor distinto al paquete → `OPERATOR_NOT_SUPPORTED`.
5. `GetProducts` vacío → `NO_PRODUCTS`.
6. SKU seleccionado no aparece en `GetProducts` → `PRODUCT_NOT_AVAILABLE`.
7. Producto fijo con monto distinto → `AMOUNT_NOT_ALLOWED`.
8. Producto de rango debajo del mínimo → `AMOUNT_NOT_ALLOWED`.
9. Producto de rango sobre máximo → `AMOUNT_NOT_ALLOWED`.
10. `SettingDefinitions` obligatorio sin valor → error de settings.
11. `LookupBillsRequired=true` sin `BillRef` → bloquea.
12. `GetBalance` insuficiente → bloquea y loguea evento admin.
13. `SendTransfer ValidateOnly=true` OK → devuelve `precheck_token`.
14. `ValidateOnly` con `AccountNumberInvalid` → bloquea.
15. `ValidateOnly` con `RateLimited` → `429/503 retryable:true`.
16. Timeout en `ValidateOnly` → retry con backoff y luego error claro.
17. Respuesta parcial sin `ResultCode` concluyente → bloquea.
18. `add-to-cart` sin `precheck_token` → bloquea.
19. `add-to-cart` con token expirado → bloquea.
20. `add-to-cart` con datos distintos al precheck → bloquea.
21. Doble validación concurrente misma sesión → no duplica cobro.
22. `/transfer` sigue bloqueado en `payment_mode=woocommerce`.

### Pruebas Automáticas Frontend

1. Continue deshabilitado sin número.
2. Continue deshabilitado sin paquete.
3. Número inválido muestra error inmediato.
4. Producto de rango muestra mínimo/máximo.
5. Cambio de monto invalida estimación anterior.
6. Cambio de paquete borra `precheck_token`.
7. Click en Continue llama `/precheck`.
8. Error `/precheck` mantiene usuario en selección.
9. OK `/precheck` avanza a confirmación.
10. Doble click en Continue genera una sola llamada activa.
11. Timeout visual muestra mensaje de espera.
12. Botón se reactiva tras error.
13. `rest_cookie_invalid_nonce` recarga sesión como ya hace el frontend actual.

### Pruebas Manuales UAT

Usando números UAT documentados por DingConnect:

| Caso | Entrada | Resultado esperado |
|---|---|---|
| Éxito | Número válido + SKU válido | Avanza a confirmación |
| Rate limit | UAT terminado en `001` | Bloquea con mensaje de límite |
| Timeout proveedor | UAT terminado en `002` | Retry y mensaje temporal |
| Producto no disponible | UAT terminado en `004` | Bloquea paquete |
| Número inválido | UAT terminado en `005` | Bloquea número |
| Saldo insuficiente | UAT terminado en `007` | Bloquea pago y alerta admin |
| Regex inválido | UAT terminado en `008` | Bloquea formato |
| Recarga no permitida | UAT terminado en `009` | Bloquea con mensaje operativo |
| Lookup vacío | Número inventado | Bloquea sin checkout |
| SKU removido | Producto desactivado en backend | Bloquea selección |
| Monto fuera de rango | Importe bajo/alto | Muestra rango |
| Latencia alta | Simular 8-10 s | Spinner + no doble submit |
| Ding 5xx | Mock de 500 | Retry, luego error temporal |
| Respuesta parcial | Sin `Items`/`ResultCode` | Bloquea como indeterminado |
| Token expirado | Esperar > 5 min | Revalidar antes de pago |
| Cambio post-precheck | Cambiar monto o número | Invalida token |

---

## Punto Exacto de Integración en el Plugin

En `dingconnect-wp-plugin/dingconnect-recargas/assets/js/frontend.js`, el handler actual está alrededor de `btnContinueConfirm.addEventListener('click', ...)`.

Debe quedar conceptualmente así:

```js
btnContinueConfirm.addEventListener('click', async function () {
  if (!state.selected) return;

  btnContinueConfirm.disabled = true;
  setFeedback('Validando tu recarga con el operador...', 'info');

  var providerAvailable = await ensureProviderStatus(state.selected, 'package');
  if (!providerAvailable) {
    btnContinueConfirm.disabled = false;
    return;
  }

  var validationError = validateSelectedBundle(state.selected);
  if (validationError) {
    setFeedback(validationError, 'warning');
    btnContinueConfirm.disabled = false;
    return;
  }

  var precheck = await validateRechargePrecheck(state.selected);
  if (!precheck.ok) {
    setFeedback(precheck.message || 'No pudimos validar la recarga.', 'error');
    btnContinueConfirm.disabled = false;
    return;
  }

  state.precheckToken = precheck.precheck_token;

  buildConfirmStep(state.selected);
  goToStep('confirm', 'forward');
});
```

Y `addToCart()` debe enviar:

```js
precheck_token: state.precheckToken || ''
```

Backend debe rechazar `add-to-cart` si el token no existe, expiró o no coincide con número/SKU/monto/settings/bill_ref.

---

## Reglas de Seguridad Operativa

- Nunca llamar DingConnect desde frontend público con credenciales.
- Nunca confiar solo en validaciones frontend.
- Nunca cobrar si `/precheck` falla.
- Nunca reutilizar `/transfer` para precheck en WooCommerce.
- Siempre forzar `ValidateOnly=true` dentro del endpoint `/precheck`.
- Siempre repetir validación o exigir token en `add-to-cart`.
- Siempre invalidar token si cambian número, país, SKU, monto, settings o bill ref.
- Siempre registrar logs sin exponer datos sensibles completos.
- Siempre tratar respuestas parciales como bloqueo, no como éxito.
- Siempre usar `ListTransferRecords` después del pago para reconciliar timeouts o estados pendientes.

La arquitectura correcta es: validar fuerte antes de confirmar, cobrar solo si el precheck fue concluyente, y ejecutar la recarga real únicamente cuando WooCommerce confirme pago.