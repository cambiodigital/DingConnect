# DingConnect Recargas v1.2.0 - Instalación, configuración y salida a producción

Hecho por Cambiodigital.net, personalizado para cubakilos.com.

## 1) Generar el ZIP para WordPress

> **IMPORTANTE**: No usar `Compress-Archive` de PowerShell directamente.
> PowerShell genera rutas internas con backslash (`\`) dentro del ZIP.
> Los servidores Linux no reconocen `\` como separador de directorios,
> lo que provoca que los archivos se extraigan como nombres planos en la raíz
> sin crear las subcarpetas `includes/` ni `assets/`. El plugin no podrá
> cargar sus dependencias y mostrará "archivos no encontrados".

Desde PowerShell, ejecutar este comando en la raíz del workspace:

```powershell
$dest   = 'dingconnect-recargas.zip'
$source = 'dingconnect-wp-plugin\dingconnect-recargas'
if (Test-Path $dest) { Remove-Item $dest -Force }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($dest, 'Create')
Get-ChildItem -Path $source -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring((Resolve-Path $source).Path.Length + 1).Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
        $zip, $_.FullName, $relativePath
    ) | Out-Null
}
$zip.Dispose()
```

Esto garantiza que las rutas internas usen `/` (forward slash), compatible con Linux.

**Verificar** que la estructura del ZIP sea plana (SIN carpeta contenedora):

```
dingconnect-recargas.php
includes/class-dc-api.php
includes/class-dc-admin.php
...
assets/css/frontend.css
assets/js/frontend.js
```

WordPress crea automáticamente la carpeta `dingconnect-recargas/` a partir del nombre del ZIP.

## 2) Instalar en WordPress

1. Si existe una versión anterior, eliminarla desde Plugins (o borrar la carpeta desde el file manager del hosting).
2. En WordPress ve a `Plugins > Añadir nuevo > Subir plugin`.
3. Sube `dingconnect-recargas.zip` e instala.
4. Activa el plugin.

## 2) Configurar credenciales y modo operativo

1. En WordPress ve a `DingConnect`.
2. Configura:
   - `API Base URL`: `https://www.dingconnect.com/api/V1`
   - `API Key DingConnect`: tu clave real.
3. Mantén `ValidateOnly por defecto` activado durante pruebas.
4. Mantén `Permitir recarga real` desactivado hasta la validación final.

## 3) Preparar el catálogo

Puedes combinar tres mecanismos:

1. `Importar bundles sugeridos (CO, ES, MX, CU)` para cargar una base inicial.
2. Buscar productos en `Products-with-sku.csv` y crear bundles desde selección.
3. Añadir bundles manualmente si necesitas un catálogo curado propio.

## 4) Publicar el formulario

1. Crea una página, por ejemplo `Recargas`.
2. Inserta este shortcode:

   `[dingconnect_recargas]`

3. Publica la página.

## 5) Elegir modo de operación

### Modo A: sin WooCommerce
Usa el plugin como formulario directo:

- El cliente busca productos.
- Selecciona bundle.
- Confirma la operación.
- El plugin llama a `POST /transfer`.

Este modo sirve para validación técnica o para integraciones que todavía no pasan por carrito y checkout.

### Modo B: con WooCommerce
Si WooCommerce está activo, el plugin habilita automáticamente el flujo comercial:

- El botón final pasa a `Anadir al carrito`.
- El plugin crea un producto base oculto si aún no existe.
- La recarga se agrega al carrito con precio dinámico.
- El checkout exige registro del cliente.
- El login por teléfono queda habilitado usando `billing_phone`.
- La transferencia DingConnect se ejecuta cuando el pedido pasa a `processing` o `completed`.

## 6) Endpoints REST actuales del plugin

- `GET /wp-json/dingconnect/v1/status`
- `GET /wp-json/dingconnect/v1/bundles`
- `GET /wp-json/dingconnect/v1/products?account_number=+5355512345&country_iso=CU`
- `POST /wp-json/dingconnect/v1/transfer`
- `POST /wp-json/dingconnect/v1/add-to-cart`

Ejemplo JSON para transferencia directa:

```json
{
  "account_number": "+5355512345",
  "sku_code": "TU_SKU_REAL",
  "send_value": 10,
  "send_currency_iso": "USD"
}
```

Ejemplo JSON para anadir al carrito:

```json
{
  "account_number": "+573001234567",
  "country_iso": "CO",
  "sku_code": "TU_SKU_REAL",
  "send_value": 7.9,
  "send_currency_iso": "EUR",
  "provider_name": "Wom Colombia",
  "bundle_label": "Paquete WOM datos + minutos"
}
```

## 7) Comportamientos técnicos nuevos a considerar

- La consulta de productos usa caché temporal de 10 minutos.
- El backend normaliza respuestas DingConnect `Items -> Result` para simplificar el frontend.
- Toda transferencia queda registrada en `Transfer Logs`.
- Hay rate limiting por IP en `products`, `transfer` y `add-to-cart`.
- El frontend usa nonce REST para endpoints que modifican estado.

## 8) Prueba recomendada antes de sincronizar a producción

1. Validación técnica:
   - Buscar paquetes para un número válido.
   - Elegir bundle.
   - Confirmar que el frontend muestre la tarjeta de resultado o redirección al checkout, según el modo.

2. Validación operativa segura:
   - Mantener `ValidateOnly` activo.
   - Mantener `Permitir recarga real` desactivado.
   - Confirmar creación de log en `Transfer Logs`.

3. Validación real controlada:
   - Habilitar `Permitir recarga real`.
   - Ejecutar una recarga de bajo monto en un número controlado.
   - Si usas WooCommerce, confirmar que el pedido llegue a `processing` o `completed`.
   - Verificar `TransferRef`, estado y nota del pedido.

## 9) Recomendaciones de producción

- Usa HTTPS en todo el sitio.
- Excluye `/wp-json/dingconnect/v1/*` de caché agresiva y CDN si aplica.
- Limita el acceso administrativo a la configuración del plugin.
- Revisa periódicamente `Transfer Logs` y notas de pedidos WooCommerce.
- No habilites recarga real hasta cerrar un ciclo de pruebas end-to-end.
