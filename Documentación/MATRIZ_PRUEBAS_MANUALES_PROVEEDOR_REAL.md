# Matriz de Pruebas Manuales por Proveedor Real

## Objetivo

Definir una matriz manual ejecutable para validar las nuevas rutas de UX del plugin DingConnect Recargas y la politica operativa de `Submitted` sobre cuatro familias reales de producto:

1. DTH.
2. Electricidad prepago.
3. PIN / Voucher.
4. Movil rango.

La matriz cubre tanto el shortcode publico (`dingconnect_recargas`) como el cierre en WooCommerce (checkout, thank-you, email y seguimiento de `Submitted`).

## Precondiciones obligatorias

1. Plugin activo en WordPress con WooCommerce habilitado.
2. `payment_mode=woocommerce` para validar la ruta payment-first.
3. Politica `Submitted` configurada y visible en `Credenciales`:
   - `submitted_retry_max_attempts`
   - `submitted_retry_backoff_minutes`
   - `submitted_max_window_hours`
   - `submitted_escalation_email`
   - `submitted_non_retryable_codes`
4. Acceso a:
   - notas de pedido WooCommerce,
   - monitor de pendientes en `Registros`,
   - email de confirmacion del pedido,
   - logs internos del plugin,
   - capturas del shortcode publico.
5. Proveedor real disponible o UAT equivalente por familia:
   - DTH: ejemplo documentado `Airtel DTH` o `Sun Direct DTH`.
   - Electricidad: ejemplo documentado `Ikeja Nigeria Electricity`.
   - PIN / Voucher: cualquier producto `ReadReceipt` con `ReceiptText` y, si existe, `ReceiptParams.pin`.
   - Movil rango: cualquier producto con `IsRange=true` y `EstimatePrices` activo; referencia conocida en el workspace: `Wom Colombia` / `CO_CO_TopUp`.
6. Mantener `validate_only=true` salvo ventana controlada de UAT o proveedor real acordada con negocio.

## Señales de payload que deben quedar visibles

La prueba NO se considera cerrada solo por exito tecnico. Deben verificarse los campos reales que definen el copy final y el soporte operativo:

| Familia | Payload minimo que debe capturarse | Campo visible esperado |
|---|---|---|
| Movil rango | `IsRange`, `ReceiveValue`, `ReceiveValueExcludingTax`, `TaxName`, `TaxCalculation`, `ProcessingState` | Importe solicitado, recibe estimado, recibe sin impuestos cuando aplique, estado final/pendiente |
| PIN / Voucher | `RedemptionMechanism=ReadReceipt`, `ReceiptText`, `ReceiptParams`, `ProcessingState` | Titulo voucher, bloque de instrucciones, `PIN` si llega, `providerRef` si llega |
| Electricidad | `LookupBillsRequired`, `BillRef`, `SettingDefinitions`, `ReceiveValue`, `ReceiveValueExcludingTax`, `ProcessingState` | Factura elegida, copy de servicio, comprobante o instrucciones, guidance contra duplicado |
| DTH | `ProductType` tipo DTH/TV, `ValidationRegex`, `ProcessingState`, `AdditionalInformation` si existe | Copy DTH, validacion de cuenta/abonado, referencia de soporte, guidance de pendiente |

## Matriz manual ejecutable

| ID | Familia / proveedor sugerido | Ruta UX obligatoria | Pasos minimos a ejecutar | Resultado esperado en copy final | Validaciones `Submitted` | Evidencia requerida | Bloquea GO |
|---|---|---|---|---|---|---|---|
| MR-01 | Movil rango / `Wom Colombia` o equivalente `IsRange=true` | `country -> number -> provider status -> amount -> EstimatePrices -> confirm -> checkout -> paid order -> thank-you/email` | 1. Buscar numero real/UAT.<br>2. Elegir producto de rango.<br>3. Cambiar importe al menos 2 veces.<br>4. Confirmar que la estimacion se invalida y recalcula.<br>5. Pagar pedido y revisar cierre. | Shortcode: `Recarga movil confirmada` o `Recarga movil en validacion`.<br>Debe mostrar `Recibe` y `Recibe sin impuestos` si el payload lo trae.<br>Thank-you/email deben mantener resumen de recarga movil, no copy generico. | Si el estado queda `Submitted`/`Pending`, debe aparecer guidance de no repetir compra, nota de pedido y proximo reintento o monitor de pendientes. | Captura de estimacion, payload REST, thank-you, email, nota de pedido, fila en `Registros`. | Si falta recalculo, si el copy sigue generico o si `Submitted` se trata como exito terminal. |
| MR-02 | PIN / Voucher / cualquier proveedor `ReadReceipt` real | `country -> provider -> denom -> confirm -> checkout/direct -> result -> thank-you/email` | 1. Filtrar producto `ReadReceipt`.<br>2. Confirmar provider status.<br>3. Completar compra.<br>4. Revisar `ReceiptText` y `ReceiptParams`. | Shortcode: `Voucher listo para usar`, `Voucher en validacion` o `Voucher no confirmado`.<br>Debe aparecer bloque de canje, `PIN` como campo propio si llega y `Ref. proveedor` si llega.<br>Thank-you/email deben resumir voucher y PIN sin perder referencia. | Si el proveedor devuelve pendiente, debe verse guidance de espera y no repeticion. WooCommerce no debe dar mensaje de exito terminal mientras siga pendiente. | Captura de resultado final, payload con `ReceiptText/ReceiptParams`, thank-you, email, nota de pedido. | Si `ReceiptText` o `PIN` se pierden, si thank-you/email no distinguen voucher, o si `Submitted` no deja guidance. |
| MR-03 | Electricidad / `Ikeja Nigeria Electricity` o equivalente `LookupBillsRequired=true` | `country -> provider -> phone/account -> settings -> LookupBills -> bill select -> confirm -> checkout -> paid order -> result` | 1. Completar `SettingDefinitions`.<br>2. Ejecutar `LookupBills`.<br>3. Seleccionar una factura.<br>4. Verificar que un cambio en settings invalida la factura.<br>5. Completar pago y revisar resultado. | Shortcode: `Pago del servicio registrado` o `Pago del servicio en validacion`.<br>Debe mostrar `Factura`, `Recibe`, `Recibe sin impuestos` si aplica y texto de servicio/comprobante.<br>Thank-you/email deben conservar `BillRef` y no hablar de voucher o recarga movil. | Si queda `Submitted`, deben verse monitor, reintento programado o escalado; no debe invitar a pagar otra vez la misma factura. | Captura de formulario dinamico, `LookupBills`, payload final con `BillRef`, thank-you, email, monitor `Submitted`. | Si se pierde `BillRef`, si cambia settings y la factura vieja sigue valida, o si `Submitted` permite duplicado operativo. |
| MR-04 | DTH / `Airtel DTH`, `Sun Direct DTH` o equivalente | `country -> provider -> provider status -> denom o free range -> account validation -> confirm -> checkout -> result` | 1. Validar regex de cuenta/abonado.<br>2. Ejecutar flujo de denominacion o rango segun proveedor.<br>3. Completar pedido.<br>4. Revisar resultado y referencias. | Shortcode: `Recarga DTH registrada`, `Recarga DTH en validacion` o `Recarga DTH no confirmada`.<br>Debe verse copy de servicio DTH, no de voucher ni electricidad.<br>Thank-you/email deben conservar resumen DTH y referencia. | Si proveedor esta caido, el frontend debe bloquear con mensaje de disponibilidad.<br>Si queda `Submitted`, debe caer en la misma politica de reintento/escalado sin mensaje engañoso. | Captura de validacion regex o bloqueo, payload final, thank-you, email, nota de pedido. | Si el flujo DTH muestra copy generico, si no respeta `ValidationRegex` o si `Submitted` no queda trazado. |

## Checklist transversal por caso

Cada fila de la matriz debe pasar este checklist completo:

1. El titulo final del shortcode coincide con la familia del producto, no con un copy generico.
2. El thank-you de WooCommerce conserva la misma familia semantica del shortcode.
3. El email del pedido mantiene el mismo resumen y referencia.
4. Los campos reales del payload se ven o se omiten con criterio correcto.
5. Si el proveedor devuelve `Submitted`, `Pending`, `Processing`, `pending_retry` o `escalado_soporte`, el copy NO habla de exito terminal.
6. Si hay `ReceiptText`, se muestra como bloque principal de instrucciones.
7. Si hay `ReceiptParams.pin`, se muestra como campo principal y no enterrado en datos tecnicos.
8. Si hay `ReceiptParams.providerRef`, se expone como referencia del proveedor.
9. Si hay `BillRef`, se mantiene desde el shortcode hasta thank-you y email.
10. La nota de pedido refleja el estado operativo real y el monitor `Submitted` muestra el item cuando corresponda.

## Copy final esperado por familia

| Familia | Titulos esperados | Campos obligatorios en pantalla final |
|---|---|---|
| Movil rango | `Recarga movil confirmada` / `Recarga movil en validacion` / `Recarga movil no confirmada` | Referencia, estado, numero, proveedor, `Recibe`, `Recibe sin impuestos` si aplica |
| PIN / Voucher | `Voucher listo para usar` / `Voucher en validacion` / `Voucher no confirmado` | Referencia, estado, instrucciones de canje, `PIN` si existe, `providerRef` si existe |
| Electricidad | `Pago del servicio registrado` / `Pago del servicio en validacion` / `Pago del servicio no confirmado` | Referencia, estado, `BillRef`, datos del servicio, guidance contra pago duplicado |
| DTH | `Recarga DTH registrada` / `Recarga DTH en validacion` / `Recarga DTH no confirmada` | Referencia, estado, operador, cuenta/abonado, guidance de servicio |

## Criterio GO / NO-GO operativo

### GO controlado

Solo se permite pasar a GO controlado cuando se cumpla TODO lo siguiente:

1. Las 4 familias de esta matriz tienen al menos 1 ejecucion documentada con proveedor real o UAT equivalente y evidencia adjunta.
2. El shortcode publico muestra copy final correcto por familia en estado exitoso y pendiente.
3. Thank-you y email de WooCommerce preservan la misma familia semantica y la referencia DingConnect.
4. La politica `Submitted` deja evidencia operativa real:
   - nota de pedido,
   - monitor de pendientes,
   - proximo reintento o escalado,
   - guidance explicito de no repetir compra.
5. No hay perdida de `PIN`, `providerRef`, `BillRef` ni `ReceiveValueExcludingTax` cuando vienen en el payload.

### NO-GO inmediato

Se mantiene NO-GO si ocurre cualquiera de estos bloqueos:

1. El copy final sigue siendo generico para alguna familia.
2. `Submitted` se comunica como exito terminal o invita implicitamente a reintentar la compra.
3. `PIN`, `ReceiptText`, `providerRef` o `BillRef` se pierden entre shortcode, thank-you o email.
4. El item pendiente no aparece en monitor, notas de pedido o politica de reintento.
5. La evidencia manual existe solo para movil y no para DTH, electricidad o voucher.

## Estado operativo despues de este cambio

- Estado de codigo: listo para ejecutar la matriz manual.
- Estado de release: `NO-GO` hasta capturar evidencia real de las 4 familias y confirmar la politica `Submitted` en entorno WordPress/WooCommerce operativo.
- Siguiente paso recomendado: ejecutar MR-01 a MR-04 en UAT o ventana controlada y anexar capturas/payloads en el mismo orden.