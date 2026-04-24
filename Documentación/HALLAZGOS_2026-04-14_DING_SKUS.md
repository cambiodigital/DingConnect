# Hallazgos del 14 de abril de 2026 - DingConnect SKUs para plugin

## Objetivo de la sesión
Dejar identificado el SKU exacto que pide el plugin de WordPress para una prueba en Colombia y preparar base para crear paquetes preconfigurados.

## Lo confirmado hoy

1. Se accedió al panel de DingConnect en la sección Products.
2. Se verificó que el producto solicitado existe:
   - Operador: Claro Colombia Bundles
   - País: Colombia
   - Precio: EUR 0.78
   - Descripción: 300 MIN for 1 day
   - Tipo: Bundle
3. Se obtuvo el SKU exacto del producto:
   - SKU: F4CO50927

## Archivo fuente agregado al proyecto

Se agregó el archivo:
- Products-with-sku.csv

Este archivo contiene el catálogo exportado con columna SkuCode y permite construir configuraciones del plugin sin depender de búsqueda manual en el panel.

## SKUs de referencia para Colombia (Claro)

- Top-up rango general: CO_CO_TopUp
- Bundle 300 MIN 1 day (EUR 0.78): F4CO50927
- Bundle 400 MB + apps (EUR 0.94): F4CO61827
- Bundle 800 MB 3 days (EUR 1.56): F4CO81944
- Bundle 2 GB 7 days (EUR 2.50): F4CO40857
- Bundle 3.5 GB 10 days (EUR 4.05): F4COCO48589
- Bundle 1000 minutes 20 days (EUR 5.46): F4CO52495
- Bundle 12 GB 30 days (EUR 10.28): F4COCO76788
- Bundle 18 GB 30 days (EUR 13.09): F4COCO66236

## Propuesta para siguiente paso: paquetes preconfigurados en el plugin

Definir un catalogo inicial en el plugin con claves estables para no pedir SKU manual cada vez.

Propuesta minima (v1):

- co_claro_minutes_300_1d
  - sku: F4CO50927
  - nombre: Claro CO 300 MIN 1 dia
  - tipo: bundle
  - moneda_envio: EUR
  - monto_envio: 0.78

- co_claro_data_400mb_7d
  - sku: F4CO61827
  - nombre: Claro CO 400 MB + apps 7 dias
  - tipo: bundle
  - moneda_envio: EUR
  - monto_envio: 0.94

- co_claro_data_2gb_7d
  - sku: F4CO40857
  - nombre: Claro CO 2 GB + apps 7 dias
  - tipo: bundle
  - moneda_envio: EUR
  - monto_envio: 2.50

- co_claro_topup_range
  - sku: CO_CO_TopUp
  - nombre: Claro CO Recarga libre
  - tipo: topup_range
  - moneda_envio: EUR

## Criterios recomendados para construir el catalogo preconfigurado

1. Incluir solo SKUs visibles y vigentes en DingConnect.
2. Priorizar bundles de bajo monto para pruebas (0.78, 0.94, 1.56).
3. Guardar metadatos de negocio junto al SKU (pais, operador, validez, descripcion corta).
4. Versionar el catalogo por fecha de exportacion para poder auditar cambios.

## Nota operativa

Si un SKU deja de funcionar en el tiempo, se debe volver a exportar Products with SKU codes desde DingConnect y actualizar el catalogo interno del plugin.
