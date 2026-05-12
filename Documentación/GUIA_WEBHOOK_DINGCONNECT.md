# Configuración de Webhooks en DingConnect

Para resolver el problema del estado "Procesando" y la demora en la obtención del `TransferRef` en modo de Producción (especialmente para productos que se procesan de forma asíncrona "Batch"), debes configurar los Webhooks (Deferred SendTransfer).

Esto permitirá que DingConnect notifique de forma instantánea a tu tienda en cuanto la recarga se haya completado exitosamente.

## Paso 1: Configuración en el Plugin (WordPress)
1. Ve al panel de administración de **DingConnect CD**.
2. Dirígete a la pestaña **Config** (Credenciales y opciones generales).
3. Busca la sección **Webhook DingConnect**.
4. Marca la casilla: **"Habilitar recepción de webhook (Deferred SendTransfer)"**.
5. Opcional pero recomendado: Deja la "Tolerancia timestamp" en **300** segundos.
6. Opcional pero recomendado en la fase inicial: Marca la casilla **"Aceptar variantes de “signed payload” hasta confirmar el formato real"** (modo compatibilidad de firma).
7. Haz clic en **Guardar cambios**.

## Paso 2: Configuración en el Portal de DingConnect
1. Inicia sesión en tu cuenta en el [Portal para Partners de DingConnect](https://www.dingconnect.com/portal).
2. Ve a la sección de **Configuración de API** o **Webhooks / Callbacks** (según esté nombrado en tu portal).
3. Localiza la opción para configurar la **Callback URL** o **Webhook URL** para `Deferred SendTransfer`.
4. Ingresa la siguiente URL exacta de tu tienda:
   ```text
   https://cubakilos.com/wp-json/dingconnect/v1/webhook
   ```
5. Guarda la configuración en el portal de DingConnect.

## Paso 3: Validación y Pruebas
1. Una vez configurado en ambos lados, realiza una nueva recarga de prueba (en modo Producción, idealmente con un importe bajo o hacia tu propio número).
2. Monitorea el pedido en WooCommerce:
   - Al pagar, el pedido quedará momentáneamente en "Procesando".
   - En unos segundos o minutos (cuando DingConnect complete la recarga), el Webhook se activará.
   - El pedido debería cambiar automáticamente a "Completado".
3. Ve al detalle del pedido en WooCommerce y revisa las **Notas del pedido**. Deberías ver un mensaje similar a:
   `"DingConnect webhook para [NUMERO] (SKU: [SKU]): estado COMPLETE."`
4. Revisa también la pestaña **Registros** en el panel del plugin de DingConnect para asegurarte de que el evento `webhook` se está recibiendo sin errores de firma (código HTTP 401).

## Notas Técnicas Adicionales
- El plugin ya está preparado para procesar el payload del Webhook, verificar la firma criptográfica (usando RS256 y la clave pública JWKS de DingConnect) y actualizar el pedido de WooCommerce de manera segura.
- Una vez que compruebes que los Webhooks están funcionando perfectamente y las firmas coinciden, puedes regresar a la configuración del plugin y desmarcar la casilla de "compatibilidad de firma" si lo deseas, para hacer la validación más estricta.