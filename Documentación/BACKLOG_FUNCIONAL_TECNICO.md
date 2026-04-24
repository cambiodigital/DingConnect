# Backlog funcional y técnico

## Propósito

Priorizar próximos proyectos y funcionalidades sobre la base actual del plugin WordPress de DingConnect.

## Prioridad P0 (crítico)

1. Unificar contrato entre frontend y REST del plugin.
   - Alinear namespace y parámetros (`dingconnect/v1`, `account_number`).
   - Definir shape de respuesta canónico para productos y transferencias.
2. Eliminar dependencia de credencial en frontend legado.
   - Mantener `recargas.html` solo como entorno de prototipo sin uso productivo.
3. Validar mapeo de respuesta real de DingConnect.
   - Confirmar si el payload usa `Items` o `Result` en cada endpoint y normalizar en backend.

## Prioridad P1 (alto)

1. Robustecer flujo de productos de rango.
   - Integrar `EstimatePrices` en plugin y frontend del plugin.
2. Soportar campos enriquecidos de producto.
   - `GetProductDescriptions`, vigencia, markdown y textos de beneficio.
3. Implementar consulta de estado final.
   - Integrar `ListTransferRecords` para timeout y reconciliación.
4. Configurar catálogo curado inicial para Colombia con SKUs verificados.
   - Fuente: `HALLAZGOS_2026-04-14_DING_SKUS.md` y `Products-with-sku.csv`.

## Prioridad P2 (medio)

1. Administración de bundles más completa.
   - Edición de bundles existentes.
   - Importación desde CSV filtrado.
2. Registro operativo.
   - Historial de operaciones en WordPress con búsqueda por referencia.
3. Endurecimiento de API REST.
   - Rate limit básico.
   - Auditoría de requests/responses (sin exponer datos sensibles).

## Prioridad P3 (evolutivo)

1. Integración de pagos del e-commerce.
2. Observabilidad avanzada (dashboard de métricas).
3. Soporte de más países y líneas de negocio (vouchers, DTH, electricidad prepago).

## Definición de listo por iniciativa

Una iniciativa se considera lista cuando cumple:

1. Código implementado en el plugin.
2. Validación manual en entorno WordPress cuando exista entorno disponible; si no existe, se permite validación estática y QA/UAT diferida.
3. Actualización de documentación en `Documentación/`.
4. Registro de riesgos y decisiones relevantes.

## Checklist de validación mínima para nuevas funcionalidades

1. Verificar configuración del plugin en admin (`api_base`, `api_key`, `validate_only`).
2. Probar endpoints REST involucrados.
3. Confirmar comportamiento de fallback.
4. Validar errores esperados y mensajes al usuario.
5. Confirmar compatibilidad con catálogo curado activo.

## Avances implementados (14-04-2026)

1. Panel admin con importación rápida de bundles preconfigurados para Colombia, España, México y Cuba.
2. Búsqueda operativa de catálogo integrada en admin para acelerar el alta de bundles curados.
3. Prevención de duplicados al importar presets por clave `country_iso + sku_code`.
4. Creación automática de bundle desde resultados de catálogo seleccionados (publicación inmediata opcional).
5. Integración opcional con WooCommerce para anadir al carrito, checkout obligatorio y despacho al confirmar el pago.
6. Registro interno de transferencias y normalización backend de respuestas `Items`/`Result`.
7. Rate limiting básico por IP en endpoints operativos del plugin.
8. Reorganización de la interfaz del panel admin en pestañas: pestaña de configuración para credenciales, modo y balance; pestaña operativa para catálogo/alta; y pestaña especial para bundles guardados.
9. Gestión operativa de bundles guardados en admin con edición, activación/desactivación y eliminación.
10. Mejora UX en frontend público de recargas: auto-búsqueda de paquetes al escribir el número móvil o cambiar el país, con debounce y sin necesidad de pulsar el botón Buscar paquetes.
11. Optimización del frontend público: deduplicación de consultas para no repetir llamadas al endpoint cuando país y número no cambiaron o ya hay una consulta idéntica en curso.
12. Actualización de release y branding del plugin: versión 1.2.0, créditos visibles de Cambiodigital.net y personalización para cubakilos.com en cabecera del plugin, panel admin, frontend y manuales del plugin.
13. Modernización visual del panel admin: eliminación de numeración en títulos y pestañas, actualización de estilo con tabs tipo píldora, paneles en tarjeta y jerarquía visual más limpia para reducir la apariencia clásica de WordPress.
14. Mejora UX en bundles guardados: la acción Editar ahora abre un modal inline sobre la tabla, sin salto de pantalla, para actualizar el bundle de forma rápida y contextual.
15. Corrección de sincronización frontend-admin: el caché de búsqueda por país+número en el frontend ahora expira (TTL 10 segundos) para refrescar operadores y bundles nuevos sin recargar toda la página.
16. Corrección estructural en tabs del admin: se cerró correctamente la sección "Wizard y landings" para evitar que "Catálogo y alta", "Bundles guardados" y "Registros" quedaran anidados dentro de un panel oculto (síntoma: pestaña activa sin contenido visible).
16. Corrección de robustez en frontend público: el script del shortcode ahora valida nodos requeridos y maneja markup parcial sin lanzar errores de JavaScript como `Cannot set properties of null (setting 'innerHTML')` durante la búsqueda automática de paquetes.
17. Corrección productiva en recargas: el backend REST ahora normaliza `AccountNumber` a solo dígitos (sin símbolo `+`) para cumplir validación regex de DingConnect en `SendTransfer`, y propaga el código HTTP real del error para diagnóstico operativo.
18. Mejora de diagnóstico productivo: el cliente API del plugin ahora traduce códigos de negocio de DingConnect (incluyendo `InsufficientBalance`) a mensajes claros para operación y soporte, manteniendo el detalle técnico en `error_data`.
19. Corrección de catálogo multioperador: el endpoint `/products` del plugin ahora consulta productos por país cuando existe `country_iso`, normaliza precios y campos clave al contrato del frontend y completa `ProviderName` a partir de `ProviderCode`, evitando que operadores válidos queden ocultos en países como Colombia.
20. Operación administrativa reforzada: se agregó endpoint interno `GET /wp-json/dingconnect/v1/balance` (solo administradores) y botón "Consultar balance ahora" en la pestaña de credenciales para validar saldo del agente desde WordPress.
21. Unificación de control de modo de pruebas: se reemplazó el modelo de dos checkboxes independientes (`ValidateOnly por defecto` + `Permitir recarga real`) por un único selectbox `Modo de recargas` con tres opciones claras: 🔒 Pruebas (Simular siempre), ⚙️ Pruebas (Permitir cambio desde frontend), ⚡ Producción (Reales). Esta UX simplificada elimina estados confusos y acelera la transición entre entornos.
20. Mejora UX en pestaña Credenciales: el balance se actualiza automáticamente al entrar a la pestaña (con control de frecuencia) y se presenta como tarjeta amigable con monto, moneda y estado en lugar de JSON.
21. Mejora UX en selección de operador del frontend: los operadores se muestran en una grilla uniforme con tarjetas de tamaño consistente para mantener alineación visual y espacio equivalente por operador.
22. Mejora UX en administración de bundles: campos País ISO, Nombre comercial, Moneda y Operador ahora usan combobox consistente (alta y edición) con apertura inmediata al clic, opciones alineadas debajo del input, filtrado al escribir y alta libre de nuevos valores compartidos entre formularios.
23. Respuesta de balance robusta en admin: el endpoint interno `/balance` normaliza variaciones de payload de DingConnect para mostrar siempre saldo, moneda y código de resultado en la tarjeta de credenciales.
24. Diagnóstico extendido de recargas fallidas: errores `ProviderError` agregan contexto (`ding_error_context`) y trazas de operación (`transfer_ref`, `distributor_ref`, `processing_state`) para investigación de soporte.
25. Gestión masiva de bundles en admin: la tabla de bundles guardados ahora permite seleccionar uno o varios registros (incluye "seleccionar todos") y eliminarlos en bloque con confirmación.
26. Fundaciones del wizard v2: se creó `includes/class-dc-wizard.php` con pasos base, validación de transición y estructura de estado inicial para recargas + gift cards.
27. Estado de wizard persistente: se agregó tabla `dc_wizard_sessions` con creación automática (activación y upgrade) para guardar/recuperar sesiones.
28. API REST del wizard: nuevos endpoints `wizard/config` y `wizard/session` para configuración pública y recuperación de estado por `session_id`.
29. Panel admin preparado para wizard: nuevo feature flag (`wizard_enabled`), límite de ofertas por categoría y parámetros de mapeo de teléfono en checkout.
30. Frontend habilitado para siguiente fase: registro del shortcode `[dingconnect_wizard]` con atributos `entry_mode` y `country` para landings externas.
31. Motor de ofertas del wizard: clasificación base recargas/gift cards, filtrado por categoría y límite configurable por categoría con orden estable.
32. Contrato base de confirmación unificado: se incorporó payload canónico para voucher/confirmación con estructura común para recargas y gift cards.
33. API del wizard ampliada: endpoint `GET /wp-json/dingconnect/v1/wizard/offers` con soporte de `entry_mode`, `country_iso`, `account_number`, `fixed_prefix` y `category`.
34. Sincronización operativa del wizard: endpoint administrativo `POST /wp-json/dingconnect/v1/wizard/sync-now` que consulta catálogo por país, genera fingerprint y detecta cambios para notificar actualización de landings.
35. Contrato REST v1 para wizard: respuestas homogéneas con `endpoint`, `contract_version`, `result` y metadatos de seguridad (`backend_only`) para integración frontend estable.
36. Bloque frontend wizard v2 implementado: nuevo cliente `assets/js/wizard-core.js`, nuevo estilo `assets/css/wizard.css` y shortcodes predefinidos para recargas, gift cards y Cuba con prefijo fijo.
37. Enforcement payment-first en WooCommerce: transferencias DingConnect bloqueadas hasta estado pagado (`order->is_paid`) y ejecución idempotente por item para evitar duplicados.
38. Política de reintentos en producción: configuración en admin para intentos automáticos y ventana de espera (`wizard_transfer_retry_attempts`, `wizard_transfer_retry_delay_minutes`) con reintentos programados.
39. Reconciliación manual operativa: nueva acción de pedido en WooCommerce para reintentar recargas fallidas y registrar auditoría con notas de orden.
40. Confirmación de voucher unificada en checkout: resumen en pantalla de thank-you y datos de recarga inyectados al correo de WooCommerce.
41. Bloque de verificación v2 (6.1-6.7) ejecutado en modo evidencia técnica: reporte actualizado con trazabilidad de código para payment-first, idempotencia, retries, reconciliación y shortcodes; quedan bloqueadas las evidencias E2E runtime por falta de entorno WordPress/WooCommerce/gateways y CLI PHP en esta estación.
42. Infraestructura de staging local agregada para verificación runtime: `staging/docker-compose.yml` + scripts `scripts/staging-up.ps1` y `scripts/run-matrix-6.ps1` para bootstrap WordPress/WooCommerce, activar gateways de prueba (BACS/Cheque/COD) y repetir matriz 6.1-6.7.
43. Bloqueo operativo identificado para ejecutar staging en esta estación: comando `docker` no disponible en PowerShell, por lo que la ejecución runtime 6.1-6.7 sigue pendiente hasta instalar/activar Docker Desktop.
44. Cierre documental de fase 6.8 del cambio `dingconnect-recargas-v2`: se actualizó el backlog técnico y la guía técnica con resultados actuales, brechas runtime, riesgos y recomendación `NO-GO` condicional hasta completar evidencia de staging con WooCommerce y pasarelas.
45. Endurecimiento del wizard v2 contra bypass de pasos: la persistencia de sesión ahora valida reglas por paso (categoría, número, operador y producto) y bloquea saltos no secuenciales en backend para evitar estados inconsistentes por llamadas REST directas.
46. Detección automática en flujo number-first: en el paso de datos del destinatario el país pasa a ser opcional y el wizard intenta resolverlo automáticamente desde el catálogo obtenido con el número, manteniendo opción de selección manual.
47. Limpieza técnica del controlador REST del wizard: `wizard/session/{session_id}` eliminó un manejo muerto de `WP_Error` en lectura de sesión para reflejar el contrato real de `get_session()` (`array|null`) y evitar falsos positivos de análisis estático.
48. Actualización documental exhaustiva de DingConnect API: se auditó cobertura interna de `Methods`, `Description` y `FAQ`, se reforzó `API_DING_CONNECT_V1.md` con trazabilidad de fuentes y se agregó base de conocimiento ampliada en `Documentación/BASE_CONOCIMIENTO_API_DINGCONNECT_COMPLETA.md` para acelerar futuras fases de integración.
49. Análisis funcional-técnico de solicitud René/Cubakilos para landings: se agregó `Documentación/ANALISIS_WEBHOOK_LANDINGS_RENE_CUBAKILOS.md` con definición de alcance para diseño por landing, paquetes propios por shortcode y ruta recomendada para webhook de `Deferred SendTransfer`.
50. Wizard operativo dentro del panel admin: se habilitó una vista embebida del flujo paso a paso en la pestaña de configuración, reutilizando contrato REST del wizard para pruebas internas sin depender de una landing pública.
51. Gestor de shortcodes dinámicos para landings en admin: nueva sección para crear objetivos de landing, seleccionar bundles concretos y generar shortcodes reutilizables con clave (`landing_key`).
52. Shortcode `dingconnect_recargas` ampliado para objetivos: soporta `landing_key`, `bundles`, `country`, `title` y `subtitle`, permitiendo variantes por campaña con país fijo y catálogo restringido.
53. Edición inline de shortcodes dinámicos en admin: el listado de landings ahora permite abrir modal de edición, actualizar objetivo/clave/país/bundles y guardar cambios sin salir del panel.
54. Duplicado rápido de landings en admin: cada shortcode dinámico ahora incluye acción `Duplicar`, clonando configuración (título, subtítulo, país y bundles) con clave única automática para acelerar nuevas campañas y abriendo automáticamente el modal de edición de la copia.
55. Reorganización funcional del panel admin por secciones: el bloque de wizard y landings se movió a pestaña dedicada `Wizard y landings`, se renombró el título operativo a `Wizard de pruebas internas` y se alineó la navegación (mensajes, edición y redirecciones) para caer en la sección correcta.
56. Ajuste de prioridad visual en subpestañas de catálogo: en `Catálogo y alta` se priorizó el flujo `Buscar en API` como entrada principal para crear bundles.
57. Mejora UX en `Buscar en API`: el listado `Paquetes encontrados` ahora indica el doble click para `Alta manual` y ese gesto carga el producto seleccionado en el formulario manual para edición previa al guardado.
58. Mejora UX en `Buscar en API`: nuevo filtro `Tipo de paquete` y render agrupado del listado usando tres patrones dominantes detectados en `Products-with-sku.csv` (`Saldo / top-up`, `Datos`, `Combo / voz + datos`), conservando `Otros` como fallback para productos no móviles.
59. Consistencia operativa en landings: el campo `País fijo (ISO, opcional)` ahora funciona como buscador con select etiquetado por país real (`Nombre + ISO`), alimentado desde países presentes en bundles, landings y catálogo CSV; además el guardado valida que el país fijo coincida con los bundles seleccionados para evitar configuraciones inconsistentes.
60. Mejora UX en `Buscar en API`: el aviso `Selecciona un país antes de buscar.` ahora se muestra arriba del selector de país con estilo de advertencia en naranja, separado del texto de ayuda de resultados.
61. Simplificación del panel admin de catálogo: se eliminó el subflujo `Buscar en CSV` y se consolidó la operación en dos métodos soportados, `Buscar en API` y `Alta manual`, manteniendo la creación automática de bundles desde resultados live de la API.
62. Persistencia local en `Buscar en API`: el panel admin ahora guarda en el navegador la última consulta exitosa con país, filtro, texto y resultados restaurables para retomar la catalogación sin repetir inmediatamente la llamada a DingConnect.
63. Mejora de trazabilidad en `Alta manual`: al cargar un producto con doble click desde `Buscar en API`, se muestra junto a `Datos del bundle` el nombre limpio del paquete seleccionado (`label` original de la API) para confirmar visualmente qué producto se está editando.
64. Ajuste operativo en `Buscar en API`: se eliminó la opción `Publicar bundle inmediatamente (activo)` y la creación desde catálogo ahora guarda el bundle inactivo por defecto; la activación/desactivación se centraliza en la pestaña `Bundles guardados`.
65. Limpieza de UX en pestaña Credenciales: se retiró del panel admin el bloque estático `Uso en frontend` con el shortcode base `[dingconnect_recargas]`, dejando la publicación centrada en `Wizard y landings`.
66. Corrección de navegación en `Catálogo y alta`: la subpestaña `Alta manual` vuelve a abrir correctamente tanto al hacer click directo como al cargar un producto por doble click desde `Buscar en API`, usando activación explícita del panel en lugar de depender solo de un `click()` simulado.
67. Mejora UX en selección de bundles para landings: tanto en alta como en el modal de edición se reemplazó la selección múltiple con `Ctrl/Cmd` por un checklist con checkboxes, para visualizar claramente qué bundles se incluyen o excluyen.
68. Rediseño UX del shortcode público de recargas: se eliminó el stepper numerado visible (1, 2, 3) y el flujo quedó consolidado en dos pasos. En el primer paso, debajo del número, aparece la selección de paquetes de la propia landing mediante un `select` con ficha resumida (Beneficios recibidos, Operador, Monto y País ISO). En el segundo paso se presenta la confirmación final antes de continuar a WooCommerce o a la recarga directa.
69. Corrección funcional en landings por shortcode: el frontend ahora envía `allowed_bundle_ids` al endpoint `/products` y backend prioriza esos `bundle_ids` configurados en la landing (aunque estén inactivos globalmente), evitando mezclar catálogo activo general del país con el catálogo específico del objetivo publicado.
70. Corrección UX en admin para campos con buscador (datalist-combobox): se reparó la inicialización JavaScript del combobox (mapa de datalists, registro y alta dinámica de opciones) y se ajustó el ancho/estilo del campo `País fijo (ISO, opcional)` para evitar render comprimido y dropdown inconsistente en alta/edición de landings.
71. Simplificación operativa en `Landings`: se eliminó del admin el campo `País fijo (ISO, opcional)` para alta/edición de shortcodes dinámicos; el país de la landing ahora se deriva automáticamente de los bundles seleccionados.
72. Ajuste funcional en frontend de `dingconnect_recargas`: el selector de país siempre queda editable y la lista de países se construye a partir de los `country_iso` presentes en los bundles permitidos de la landing.
73. Control de pasarelas WooCommerce para recargas: en `Credenciales` se añadió selección de `Pasarelas permitidas` y, cuando el carrito contiene recargas DingConnect en modo WooCommerce, checkout filtra métodos de pago a los IDs configurados (si no se selecciona ninguno, se mantienen todas las pasarelas activas).
74. Estabilización de subpestañas en `Catálogo y alta`: la navegación entre `Buscar en API` y `Alta manual` ahora usa un único controlador de estado y binding, evitando bloqueos intermitentes por doble inicialización del cambio de subpestaña.
75. Corrección visual en paso final del frontend (`dingconnect_recargas`): se ajustó la generación de CSS de personalización por landing para limitar su alcance a componentes primarios del wizard y evitar que se sobrescribieran estilos del resultado final; además se corrigió el selector del contenedor por instancia para mantener el layout esperado.
76. Endurecimiento de despliegue del frontend público: los assets `assets/css/frontend.css` y `assets/js/frontend.js` ahora se versionan por `filemtime` desde el plugin para evitar mezclar markup nuevo con CSS/JS cacheados de versiones anteriores tras una actualización.
77. Corrección del modal de país en frontend público: el overlay `.dc-country-overlay` ahora respeta el atributo `hidden` mediante una regla CSS explícita, evitando que el modal inicial quede visible y bloquee toda interacción en la landing.
78. Contrato REST enriquecido para catálogo live: el endpoint `/wp-json/dingconnect/v1/products` ahora expone `ProductType` por ítem (además de `SkuCode`, operador y precios), y el flujo AJAX de `Buscar en API` en admin también devuelve `product_type` para clasificación y reglas futuras basadas en tipo real de DingConnect.
79. Operación avanzada en shortcodes dinámicos de landings: al crear/editar una landing se puede definir orden explícito de bundles y marcar un `bundle` destacado; frontend respeta el orden configurado y resalta visualmente el destacado con fondo amarillo suave en selección y confirmación.
80. Mejora UX en `Landings`: los bundles de alta y edición ahora soportan drag and drop con manija visual para reordenar fácilmente; el campo de orden se sincroniza automáticamente con la posición resultante antes de guardar.
81. Higiene de análisis estático en workspace: se agregó archivo de stubs `wordpress-stubs.php` y configuración de editor para reducir falsos positivos (`Undefined function/type`) de WordPress/WooCommerce en VS Code sin afectar ejecución real del plugin.
82. Replanteamiento del modelo de bundle para catálogo/comercial: se incorporó persistencia de precio dual por bundle (`send_value` como Coste DIN y `public_price` como Precio al Público editable), permitiendo que el mismo SKU tenga distinto margen por bundle.
83. Alta manual y edición de bundles actualizadas al nuevo esquema comercial: formularios y modal ahora muestran campos explícitos de `Coste DIN` y `Precio al Público`, manteniendo `label` siempre editable por operación.
84. Landings escalables con checklist filtrable: la selección de bundles en alta/edición de shortcodes dinámicos ahora soporta filtros por país y tipo de producto (`package_family`), preservando siempre visibles los bundles ya seleccionados para evitar pérdidas de configuración.
85. Enriquecimiento de alta desde catálogo API: al seleccionar producto se propagan metadatos comerciales (`package_family`, `product_type_raw`, `validity_raw`) al alta manual y el backend persiste también `validity_days` derivado cuando el formato de vigencia es parseable.
86. Simplificación de catálogo admin en `Buscar en API`: se retiró la hidratación por CSV y los resultados ahora se construyen únicamente con datos live de DingConnect (`operator`, `receive`, `product_type`, `validity`, `send_value`, moneda).
87. Limpieza operativa en `Credenciales`: se eliminó del panel la carga de CSV de catálogo por SKU al dejar de utilizarse esa fuente para enriquecer productos encontrados por API.
88. Rediseño de `Paquetes encontrados` en `Buscar en API`: el listado dejó de usar un `select` básico y pasó a una tabla operativa de ancho completo, fuera de la `form-table`, con columnas completas (tipo, operador, beneficios, SKU, coste, moneda, vigencia y fuente), encabezado fijo, selección por fila y doble click para cargar directamente en `Alta manual`.
88. Corrección funcional en filtros de bundles para landings: los filtros `País` y `Tipo de producto` del checklist (alta y edición) recuperan su efecto visual al respetar `hidden` por fila, evitando que el estilo base `display:flex` mantenga visibles bundles fuera del filtro activo.
89. Compatibilidad de análisis estático en WooCommerce: la creación del producto base de recarga ahora reutiliza el ID devuelto por `save()` en `WC_Product_Simple`, evitando falsos positivos de `Undefined method get_id` en VS Code y manteniendo el mismo comportamiento del CRUD real de WooCommerce.
90. Inicio de cumplimiento backend con documentación DingConnect ampliada: `class-dc-api.php` incorporó métodos para `GetCountries`, `GetCurrencies`, `GetRegions`, `GetProviderStatus`, `GetAccountLookup`, `EstimatePrices`, `LookupBills`, `ListTransferRecords` y `GetErrorCodeDescriptions`, además de extender `SendTransfer` con soporte de `Settings` y `BillRef`.
91. Contrato REST del plugin ampliado para nuevos flujos: `class-dc-rest.php` ahora expone rutas `provider-status`, `estimate-prices`, `lookup-bills` y `transfer-status`, flexibiliza `GET /products` para consultas por país/proveedor/SKU y normaliza más metadatos de producto (`ValidationRegex`, `SettingDefinitions`, `LookupBillsRequired`, `RegionCode`, `ReceiveValueExcludingTax`, `TaxCalculation`, `DescriptionMarkdown`, `ReadMoreMarkdown`, `CustomerCareNumber`, `LogoUrl`).
92. Alineación dinámica del shortcode público con el contrato ampliado: el frontend ya mantiene selección filtrada estable por landing, valida `ValidationRegex`, consulta `GetProviderStatus` antes de confirmar, renderiza `SettingDefinitions`, soporta productos de rango con `EstimatePrices`, ejecuta `LookupBills` cuando el producto lo exige y transporta `settings` + `bill_ref` tanto a recarga directa como a WooCommerce.
93. Endurecimiento del transporte operativo de datos dinámicos: WooCommerce persiste `dc_settings` y `dc_bill_ref` desde `add-to-cart`, los muestra en carrito/pedido y los reenvía a `SendTransfer`, mientras la conciliación previa por `ListTransferRecords` evita retrabajar items ya resueltos y distingue estados pendientes (`Submitted`) de éxitos terminales.
94. Ajuste UX inicial para payloads reales de `EstimatePrices` y `LookupBills`: el backend REST preserva `ResultCode/ErrorCodes` en `lookup-bills`, normaliza `SettingDefinitions` con metadatos extendidos (`Type`, `ValidationRegex`, límites y `AllowedValues`) y frontend muestra errores accionables por código DingConnect, estados de carga de estimación e invalidación automática de factura/estimación al cambiar importe o settings.
95. Cierre operativo inicial de `Submitted` en WooCommerce: la política de reintentos dejó de ser fija y pasó a configuración en admin (`intentos máximos`, `backoff`, `ventana máxima`, `correo de escalado`, `códigos no reintentables`), con escalado automático a `escalado_soporte`, corte de errores no reintentables y monitor visible en pestaña `Registros` para recargas pendientes/escaladas.
96. Copy final por familia real de producto: el shortcode público ahora adapta el mensaje final para móvil rango, PIN/voucher, electricidad y DTH usando `ProductType`, `RedemptionMechanism`, `LookupBillsRequired`, `ReceiptText`, `ReceiptParams`, `ReceiveValueExcludingTax` y `ProcessingState`, mostrando `PIN`, `providerRef`, `BillRef` y guidance explícito cuando el estado queda pendiente.
97. Copy final alineado en WooCommerce: `add-to-cart` ya preserva metadatos de clasificación (`product_type`, `redemption_mechanism`, `lookup_bills_required`, `is_range`, `customer_care_number`) hasta carrito/pedido, y el thank-you/email generan resumen por familia con mensaje de no repetir compra cuando el estado sigue `Submitted` o equivalente.
98. Matriz manual por proveedor real documentada: se agregó `Documentación/MATRIZ_PRUEBAS_MANUALES_PROVEEDOR_REAL.md` con casos para DTH, electricidad, PIN/voucher y móvil rango, además de criterio operativo `GO/NO-GO` ligado a evidencia real/UAT y a la política `Submitted`.
99. Hardening anti-duplicados en WooCommerce: se restauró la guardia de idempotencia por `transfer_ref` existente y se ajustó la conciliación para que, ante fallo de `ListTransferRecords`, se difiera la operación sin reenviar `SendTransfer` hasta recuperar estado, evitando riesgo de recargas duplicadas.
100. Estabilidad de estimaciones en frontend: el cálculo de `EstimatePrices` ahora invalida respuestas asíncronas tardías al cambiar importe o paquete, evitando que una estimación vieja sobrescriba la selección actual del usuario.
101. Mejora de usabilidad en `Bundles guardados` (admin): la tabla ahora se renderiza dentro de contenedor con scroll horizontal interno para evitar desbordes y la columna de checkboxes (incluyendo `seleccionar todos`) queda centrada y con espaciado consistente respecto al resto de filas.
102. Limpieza final API-only en panel admin: `Catálogo y alta` eliminó referencias visuales y lógicas a CSV (avisos, badge condicional, sufijos de conteo y copy heredado), por lo que la búsqueda live muestra fuente única `API` y la carga de formulario manual se alimenta exclusivamente de respuesta DingConnect.
103. Mejora integral del mini sistema interactivo `mejoras-solicitud-interactiva.html`: se añadieron panel de métricas visuales, búsqueda/filtros por módulo, historial reciente, plantillas rápidas de cambios, formulario guiado de solicitud (objetivo/prioridad/impacto/criterios/notas), deduplicación de campos y acciones de productividad (deshacer historial y reinicio de sesión) para facilitar solicitudes precisas de evolución del plugin.
104. Ampliación del diccionario de contrato API en `mejoras-solicitud-interactiva.html`: el nodo `Producto Live Normalizado` ahora incluye el catálogo completo de campos DingConnect relevantes para operación (`ProviderCode`, `ProductType`, precios/impuestos, descripciones markdown, reglas dinámicas, lookup/status y metadatos UAT), con descripciones funcionales para modelar cambios y generar solicitudes IA sin omitir datos críticos.
105. Persistencia enriquecida de bundles y salida `saved` alineada al contrato API: el admin ahora puede conservar metadatos ricos al cargar desde catálogo API (provider, regiones, pricing extendido, flags/rules, settings dinámicos, medios de pago, UAT), y `GET /products` en `source=saved` prioriza esos campos persistidos con fallback seguro; adicionalmente `ReceiveCurrencyIso` del precio comercial pasa a usar `public_price_currency` para reflejar moneda pública en frontend.
106. Auditoría visual de flujo por campo en asistente interactivo: se agregó nodo `API -> Persistencia -> Landing (Auditoría)` en `mejoras-solicitud-interactiva.html` con estado por campo (`Persistido`, `Derivado`, `Pendiente`) para identificar rápidamente brechas de contrato antes de solicitar cambios a IA.
107. Realineación operativa del asistente interactivo al flujo real del plugin: el modelo visual ahora inicia en `Buscar en API`, continúa con `Hidratación API -> Alta manual`, luego `Formulario Alta Manual`, `Persistencia`, `REST /products`, `Frontend público` y `WooCommerce`, reflejando el pipeline actual de punta a punta para permitir ajustes de campos con contexto real de ejecución.
108. Claridad de `Buscar en API` en asistente interactivo: se separó explícitamente la vista de `8 columnas visibles` de la tabla del plugin y el `payload interno` usado para hidratación/persistencia, evitando confusión entre lo que se muestra en UI y lo que realmente viaja en datos.
109. Sincronización 1:1 final del asistente interactivo con el plugin real: `mejoras-solicitud-interactiva.html` ahora modela explícitamente los campos visibles de alta manual, los hidden fields reales de hidratación, el set completo persistido en `dc_recargas_bundles`, el payload exacto de WooCommerce cart item (`dc_*`) y los metadatos de orden (`_dc_*`) para reflejar el flujo operativo end-to-end sin abstracciones.
110. Modernización de acciones en tablas del admin: en `Shortcodes creados` y `Bundles guardados` se eliminó el botón explícito `Editar`, la edición pasa a abrirse al hacer click (o Enter/Espacio) sobre la fila completa, y las demás acciones se unificaron como botones minimalistas `icon-only` para una UI más limpia y consistente.
110. Corrección UX en `Wizard y landings`: el control `Destacado` en alta y edición de shortcodes dinámicos ahora funciona como toggle (si haces click sobre el mismo destacado activo se desactiva), permitiendo guardar `featured_bundle_id` vacío sin añadir controles extra.
111. Mejora UX en `Productos guardados` (antes `Bundles guardados`): se renombró la sección del admin y se incorporó filtrado en tiempo real sobre la tabla con buscador automático + filtros por tipo de producto (`package_family`), país (`country_iso`) y operador (`provider_name`) para acelerar operación sobre catálogos amplios.
111. Simplificación de `Catálogo y alta`: se eliminaron las subpestañas internas del panel y quedó visible solo el flujo `Buscar en API`; `Alta manual` pasa a abrirse como modal desde `Seleccionar producto`, precargando el bundle elegido para revisión y guardado sin cambiar de vista.
112. Mejora de legibilidad en `Buscar en API`: la columna `Vigencia` ahora presenta el valor del API en lenguaje natural (por ejemplo, `P7D` -> `7 días`, `P2W` -> `2 semanas`, `P1M` -> `1 mes`) manteniendo el valor original para persistencia interna del bundle.
113. Mejora UX en `Shortcodes creados`: al hacer click sobre el texto del shortcode en la tabla de `Wizard y landings`, ahora se copia automáticamente al portapapeles y muestra confirmación visual breve (`Copiado`) sin abrir el modal de edición.

## Backlog actualizado por impacto

1. Prioridad P2 - Administración de bundles más completa.
   - Estado: parcialmente completado.
   - Completado: importación desde CSV filtrado, catálogo inicial multi-país, edición de bundles en modal inline, activación/desactivación por registro y eliminación masiva por selección.
   - Pendiente: flujo de actualización masiva de campos (bulk edit).
2. Prioridad P1 - Flujo WooCommerce y post-pago.
   - Estado: parcialmente completado.
   - Completado: add-to-cart, checkout obligatorio, creación de producto base, ejecución de transferencia al confirmar pedido, notas en orden, reconciliación defensiva por `ListTransferRecords`, política configurable de `Submitted` (backoff/escalado/no-reintentos) y monitor operativo inicial en admin.
   - Pendiente: validación completa con pasarela real y evidencia runtime multi-gateway con casos prolongados reales.
3. Prioridad P2 - Observabilidad operativa.
   - Estado: parcialmente completado.
   - Completado: logs internos de transferencias.
   - Pendiente: filtros, búsqueda y panel de soporte sobre logs.
4. Prioridad P1 - Verificación E2E y validación multi-gateway del wizard v2.
   - Estado: en ejecución (fase de verificación automatizada disponible, pendiente runtime).
   - Alcance validado para ejecutar: E2E recargas number-first, E2E gift cards country-fixed, enforcement payment-first, idempotencia por item, matriz multi-gateway y reconciliación manual.
   - Evidencia requerida: notas de pedido, logs internos de transferencia, comprobación de voucher en thank-you/email y resultado por gateway.
   - Estado actual de entorno: scripts de staging listos; ejecución bloqueada por ausencia de `docker` en la máquina local.
5. Prioridad P1 - Operación multi-landing por shortcode dinámico.
   - Estado: parcialmente completado.
   - Completado: alta, edición inline, duplicado rápido y baja de configuraciones de landing desde admin, generación de shortcode por clave y filtrado de bundles por landing.
   - Pendiente: vista previa de shortcode en frontend por entorno, métricas por objetivo, validación runtime/UAT con productos reales de rango, bill payment y PIN, y cierre del drift `saved vs live` persistiendo metadatos ricos del bundle para que una landing curada no pierda comportamiento dinámico respecto al catálogo DingConnect.
6. Prioridad P1 - Flujo frontend dinámico según tipo de producto DingConnect.
   - Estado: parcialmente completado.
   - Completado: provider status previo a confirmación, campos dinámicos por `SettingDefinitions`, estimación de importes con `EstimatePrices`, selección de facturas mediante `LookupBills`, transporte de `bill_ref/settings` hasta WooCommerce y `SendTransfer`, render de errores reales (`ResultCode/ErrorCodes`), control de estado obsoleto en factura/estimación, copy final por familia real en shortcode/WooCommerce y matriz manual operativa con criterio `GO/NO-GO`.
   - Pendiente: ejecutar en WordPress/UAT la matriz manual con proveedores reales (DTH, electricidad, PIN/voucher y móvil rango), anexar evidencia runtime por familia y corregir el contrato de bundles guardados para reutilizar `ProviderCode`, `LookupBillsRequired`, `SettingDefinitions`, `IsRange`, markdown, branding y moneda pública sin degradar el flujo al pasar por admin.

## Nota operativa de despliegue (14-04-2026)

Para actualización manual del plugin en WordPress, el ZIP debe construirse desde la carpeta padre `dingconnect-wp-plugin` apuntando a la carpeta `dingconnect-recargas` como origen, de forma que el paquete resultante contenga:

- `dingconnect-recargas/dingconnect-recargas.php`

Referencia de salida recomendada:

- `x:\Proyectos\DingConnect\dingconnect-recargas-wp-update.zip`

Alternativa de compatibilidad (si WordPress espera carpeta contenedora previa):

- `x:\Proyectos\DingConnect\dingconnect-recargas-wp-update-wrapper.zip`
- Estructura interna esperada: `dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php`

Variante exacta detectada por URL de activación:

- `x:\Proyectos\DingConnect\dingconnect-recargas-wp-update-exact.zip`
- Estructura interna esperada: `dingconnect-recargas-wp-update/dingconnect-recargas/dingconnect-recargas.php`

Regla de estabilización tras error 500 en activación:

- Limpiar copias anidadas en `wp-content/plugins/` y reinstalar solo paquete canónico.
- Paquete canónico del repositorio: `x:\Proyectos\DingConnect\dingconnect-recargas-clean.zip`.

Regla definitiva de empaquetado para evitar anidación continua:

- Usar `dingconnect-recargas.zip` con archivos del plugin en raíz del ZIP (sin carpeta padre).
- Esperar activación en ruta `dingconnect-recargas/dingconnect-recargas.php`.

Corrección aplicada de resiliencia en activación (14-04-2026):

- Se agregó fallback en cargador principal del plugin para localizar `includes/class-dc-api.php` en estructuras anidadas.
- Si faltan dependencias, el plugin muestra aviso administrativo y evita fatal de PHP durante activación.

Corrección aplicada de salida inesperada en activación (14-04-2026):

- Se identificó como causa raíz la presencia de BOM UTF-8 en `dingconnect-recargas.php`, `includes/class-dc-api.php`, `includes/class-dc-frontend.php` y `includes/class-dc-rest.php`.
- Se regrabaron esos archivos en UTF-8 sin BOM para eliminar los 12 bytes de salida inesperada detectados por WordPress al activar el plugin.
- Regla de mantenimiento: cualquier edición futura de archivos PHP del plugin debe conservar codificación UTF-8 sin BOM.

Corrección aplicada de resiliencia ante copias duplicadas (14-04-2026):

- El bootstrap del plugin ahora detecta si ya existe otra copia cargada desde una ruta distinta y evita continuar con una inicialización ambigua.
- En ese caso se muestra un aviso administrativo para limpiar carpetas duplicadas en `wp-content/plugins` antes de activar la nueva copia.
- Regla de despliegue: usar ZIP canónico `dingconnect-recargas.zip` con archivos del plugin en la raíz del paquete para minimizar anidaciones como `carpeta-extra/dingconnect-recargas/`.
- Se reemplazó la búsqueda limitada con `glob()` y la destructuring array por una búsqueda iterativa simple: primero en carpeta actual, luego un nivel arriba, luego dentro de subcarpetas inmediatas.
- El bootstrap ahora busca `includes/class-dc-api.php` tolerando ambas variantes de separador: forward slash (Unix) y backslash (Windows), usando `DIRECTORY_SEPARATOR` de PHP para máxima compatibilidad.
