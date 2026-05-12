# Hallazgos de Pruebas en Producción (Checkout y Pago)

## 1. Error de Impresión de Voucher (Solucionado)
- **Síntoma:** El botón de "Guardar PDF" no funcionaba. En la consola aparecía el error `Uncaught ReferenceError: dcPrintVoucher is not defined`.
- **Causa:** En el archivo `class-dc-woocommerce.php`, un error de sintaxis en JavaScript (`Uncaught SyntaxError: Unexpected identifier 'Segoe'`) interrumpía la lectura del script. Ocurría por un mal escape de comillas en la fuente `"Segoe UI"` inyectada en el HTML del PDF.
- **Solución Aplicada:** Se corrigieron las comillas dentro del string inyectado (usando comillas simples `'Segoe UI'`), lo que restauró el registro de la función `dcPrintVoucher()`.

## 2. Pedido en "Procesando" y Ausencia de Transfer ID
- **Síntoma:** La recarga fue exitosa en DingConnect, pero WooCommerce mantuvo el pedido en `procesando` y el voucher no reflejó el `Transfer ID`.
- **Diagnóstico Técnico:**
  - **Comportamiento "Deferred" (Batch):** Si el producto se procesa en diferido, la primera respuesta de DingConnect a `SendTransfer` es un estado pendiente (ej. `Submitted`) sin un `TransferRef` definitivo.
  - **Mecanismo de Seguridad del Plugin:** El plugin está diseñado para exigir una confirmación firme. Si el `TransferRef` llega vacío, en `"0"`, o el estado no es éxito rotundo, el pedido permanece en `processing` (con ítem en `pending_confirmation`).
  - **Ventana de Sincronización:** Para resolver estados pendientes, el plugin agenda una consulta a `ListTransferRecords` vía WP-Cron. Por configuración predeterminada, esta tarea espera **10 minutos** para ejecutarse. Hasta que no se ejecuta y confirma el éxito, el pedido no pasa a `Completado`.
- **Pasos Propuestos para Resolver / Mitigar:**
  1. **Acelerar el Cron Local:** En la pestaña *Credenciales* del plugin, ajustar "Política Submitted: backoff (minutos)" de `10,20,40` a tiempos más cortos, como `1,2,5`.
  2. **Activar Webhooks:** Habilitar la recepción de Webhooks en el plugin y configurar la URL en el portal de DingConnect. Esto permitirá que la tienda se entere al instante cuando DingConnect complete la recarga y asigne el `TransferRef`, actualizando el pedido a `Completado`.
  3. **Verificar Logs Nativos:** Inspeccionar la tabla "Registros" (Transfer Logs) en el plugin para ver la respuesta "Raw" (cruda) exacta de ese pedido. Así validaremos si DingConnect envió el `TransferRef` en un campo inesperado que el método `extract_transfer_snapshot()` no esté leyendo adecuadamente.