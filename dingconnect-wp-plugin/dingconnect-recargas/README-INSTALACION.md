# DingConnect Recargas - Instalación y prueba real

## 1) Instalar plugin

1. Comprime la carpeta `dingconnect-recargas` en un archivo ZIP.
2. En WordPress ve a `Plugins > Añadir nuevo > Subir plugin`.
3. Sube el ZIP y activa el plugin.

## 2) Configurar credenciales

1. En WordPress ve a `DingConnect`.
2. Configura:
   - `API Base URL`: `https://www.dingconnect.com/api/V1`
   - `API Key DingConnect`: tu clave real.
3. Deja `ValidateOnly por defecto` activado para pruebas seguras.
4. Mantén `Permitir recarga real` desactivado durante pruebas iniciales.

## 3) Crear catálogo de bundles curados

1. En la misma pantalla, sección `Añadir bundle curado`.
2. Agrega bundles con SKU reales de DingConnect (por país).
3. Guarda y verifica tabla de bundles activos.

## 4) Publicar frontend

1. Crea una página (ejemplo: `Recargas`).
2. Inserta este shortcode:

   `[dingconnect_recargas]`

3. Publica la página.

## 5) Prueba real recomendada (paso a paso)

1. Prueba técnica segura:
   - Buscar paquetes para un número válido.
   - Elegir bundle.
   - Ejecutar operación con `ValidateOnly` activo.
   - Confirmar respuesta JSON sin error.

2. Prueba de recarga real:
   - Desactivar `ValidateOnly por defecto`.
   - Activar `Permitir recarga real`.
   - Ejecutar una recarga de bajo monto en un número controlado.
   - Verificar estado final en respuesta y en panel/proveedor.

## 6) Endpoints REST del plugin

- `GET /wp-json/dingconnect/v1/status`
- `GET /wp-json/dingconnect/v1/bundles`
- `GET /wp-json/dingconnect/v1/products?account_number=+5355512345&country_iso=CU`
- `POST /wp-json/dingconnect/v1/transfer`

Ejemplo JSON para transferencia:

```json
{
  "account_number": "+5355512345",
  "sku_code": "TU_SKU_REAL",
  "send_value": 10,
  "send_currency_iso": "USD"
}
```

## 7) Recomendaciones de producción

- Usa HTTPS en todo el sitio.
- Limita quién puede cambiar la configuración del plugin.
- Activa monitoreo/log para errores de DingConnect.
- Si usas caché/CDN, excluye rutas `/wp-json/dingconnect/v1/*`.
