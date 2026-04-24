# Análisis — Solicitud de René (Cubakilos): webhook, diseño y paquetes por landing

Fecha: 2026-04-21

## 1) Objetivo del documento

Consolidar lo que René sí pidió en reunión sobre:

1. diseño diferenciado por landing,
2. paquetes propios por landing,
3. posible uso de webhook para confirmación final,

y traducirlo a una decisión técnica clara para el plugin WordPress de DingConnect.

## 2) Evidencia de lo solicitado por René

## 2.1 Diseño por landing

En la reunión se dejó explícito que no se crearán landings dentro del plugin, sino shortcodes predefinidos para insertar en landings externas con diseño propio por campaña.

Esto está reflejado en decisiones ya marcadas:

- "deben haber diferentes shortcodes que se pueden editar según la landing que se desee poner".
- "el diseño se hace aparte en wordpress, el shortcode debe poder crearse e indicar qué paquetes/productos tendría".

Conclusión: el plugin debe exponer variantes de shortcode y presets, no imponer una sola plantilla visual para todos los casos.

## 2.2 Paquetes propios por landing

También se confirmó que René quiere controlar qué ofertas aparecen por contexto (país, categoría, operador, promo), con capacidad de escalar a más landings.

Puntos confirmados:

- Landing por país (country fixed) como patrón válido.
- Categorización recargas + gift cards.
- Cantidad de ofertas configurable por landing desde admin.
- Flujo actual de armado de bundles se mantiene, con mejoras de categorización.

Conclusión: la personalización por landing es de negocio (catálogo y orden), no solo visual.

## 2.3 Webhook

Aquí hay una distinción importante:

1. En las notas/decisiones de René no aparece un requerimiento explícito cerrado de "implementar webhook ya".
2. En la documentación de DingConnect sí existe capacidad técnica para flujo diferido con callback HTTP (webhook), asociado a `Deferred SendTransfer`.

Conclusión: webhook es una capacidad disponible y recomendable para robustecer confirmación/reconciliación, pero debe tratarse como siguiente decisión de implementación (no como funcionalidad ya aceptada y cerrada por René en el acta).

## 3) Estado actual frente a la solicitud

El proyecto ya cubre una parte alta de lo pedido:

1. Shortcodes predefinidos para landings externas.
2. Modos `number_first` y `country_fixed`.
3. Presets por categoría/país.
4. Salida white-label en wizard (sin branding DingConnect en frontend cliente).
5. Flujo payment-first en WooCommerce (no enviar recarga sin pago exitoso).

Brecha principal pendiente para este tema:

- No hay, en la documentación operativa actual del plugin, un contrato detallado de recepción y procesamiento de webhook de DingConnect para cierre asíncrono de transferencias diferidas.

## 4) Propuesta técnica concreta (alineada con lo pedido)

## 4.1 Contrato funcional por landing

Cada landing debe configurarse por shortcode + parámetros:

- `entry_mode`: `number_first` o `country_fixed`.
- `country`: opcional/fijo según landing.
- `category`: `topup` o `gift_card`.
- `fixed_prefix`: prefijo telefónico fijo cuando aplique.
- `max_offers`: límite visual por landing (desde admin).

Regla: una landing no define credenciales ni lógica transaccional; solo preset de experiencia y selección de oferta.

## 4.2 Modelo webhook recomendado

Para operaciones con respuesta diferida:

1. Registrar endpoint backend-only para callback.
2. Validar autenticidad (firma/token/IP permitida según contrato DingConnect disponible).
3. Correlacionar por `DistributorRef` y/o `TransferRef`.
4. Actualizar estado interno idempotente en logs/pedidos.
5. Disparar notificación final al comprador (voucher/email) según tipo de producto.

Regla: webhook no reemplaza `ListTransferRecords`; se usa junto con reconciliación para resiliencia operativa.

## 4.3 Respuesta al cliente por tipo de producto

Para mantener coherencia con lo hablado por René:

- Recarga: confirmación de éxito + referencias.
- Gift card/PIN: confirmación + datos de canje (`ReceiptText` / `ReceiptParams`) y formato de voucher adaptado.

## 5) Riesgos y decisiones pendientes

## Riesgos

1. Si se opera sin webhook en escenarios diferidos, aumenta el riesgo de estados "pendiente" largos y soporte manual.
2. Si se mezcla lógica de diseño dentro del plugin en vez de shortcodes por landing, se complica la operación comercial por campaña.
3. Si se reutiliza un solo catálogo global sin presets por landing, se degrada conversión por exceso de opciones.

## Decisiones que faltan cerrar con René

1. Si webhook entra en Fase inmediata o en Fase siguiente.
2. Si se exige webhook para todos los productos o solo para casos diferidos/timeout.
3. SLA de confirmación al cliente para cada tipo de producto.

## 6) Recomendación ejecutiva

Avanzar en dos tracks:

1. **Track A (inmediato):** consolidar catálogo/presets por landing (diseño externo + shortcode interno), con operación payment-first y vouchers por tipo de producto.
2. **Track B (siguiente):** especificar e implementar webhook de estado diferido con idempotencia y reconciliación (`ListTransferRecords`) para cerrar robustez transaccional.

Esto respeta exactamente la intención de René sobre landings y paquetes propios, sin asumir decisiones no confirmadas sobre webhook, pero dejando el camino técnico listo.
