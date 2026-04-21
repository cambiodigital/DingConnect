# Contexto IA Unificado - DingConnect

## Objetivo del repositorio

Implementar y operar recargas internacionales con DingConnect para Cubakilos, con una transición desde un prototipo web hacia un plugin WordPress seguro y mantenible.

## Estado real (abril 2026)

- Existe un frontend legado en un solo archivo (`recargas.html`) con lógica de catálogo, estimación y envío.
- Existe un plugin WordPress funcional en `dingconnect-wp-plugin/dingconnect-recargas/` con panel admin, endpoints REST y shortcode.
- Hay documentación técnica amplia, pero estaba dispersa y parcialmente redundante.
- Hay un catálogo exportado de SKUs (`Products-with-sku.csv`) útil para preconfigurar bundles.

## Mapa del sistema actual

### 1) Frontend legado (prototipo)

- Archivo: `recargas.html`.
- Características:
	- Intenta llamadas directas a DingConnect.
	- Incluye modo demo/fallback local.
	- Tiene soporte opcional para proxy WordPress.
	- Contiene `api_key` en cliente (riesgo crítico, solo aceptable en pruebas locales).

### 2) Plugin WordPress (implementación objetivo)

- Carpeta: `dingconnect-wp-plugin/dingconnect-recargas/`.
- Entrypoint: `dingconnect-recargas.php`.
- Componentes:
	- `includes/class-dc-api.php`: cliente HTTP hacia DingConnect.
	- `includes/class-dc-rest.php`: API REST pública del plugin.
	- `includes/class-dc-admin.php`: panel de configuración y bundles curados.
	- `includes/class-dc-frontend.php`: shortcode y carga de assets.
	- `includes/class-dc-woocommerce.php`: integración opcional con WooCommerce para carrito, checkout y despacho post-pago.
	- `assets/js/frontend.js`: UI pública para buscar paquetes y enviar recarga.
	- `assets/css/frontend.css`: estilos del frontend del plugin.

### 3) Datos de negocio

- Archivo: `Products-with-sku.csv`.
- Uso principal:
	- Construcción de catálogo curado.
	- Trazabilidad de `SkuCode` por operador, país y tipo de producto.

## Endpoints REST del plugin

Namespace actual: `dingconnect/v1`

- `GET /wp-json/dingconnect/v1/status`
- `GET /wp-json/dingconnect/v1/bundles`
- `GET /wp-json/dingconnect/v1/products`
	- Query esperada: `account_number`, opcional `country_iso`
- `POST /wp-json/dingconnect/v1/transfer`
- `POST /wp-json/dingconnect/v1/add-to-cart`
- `GET /wp-json/dingconnect/v1/wizard/config`
- `POST /wp-json/dingconnect/v1/wizard/session`
- `GET /wp-json/dingconnect/v1/wizard/session/{session_id}`
- `GET /wp-json/dingconnect/v1/wizard/offers`
- `POST /wp-json/dingconnect/v1/wizard/sync-now`

## Flujos implementados

### Flujo de consulta de productos

1. Frontend captura país y número.
2. Llama a `/products`.
3. Si DingConnect responde, muestra catálogo live.
4. Si falla, usa bundles curados activos como fallback.

### Flujo de transferencia

1. Usuario selecciona bundle.
2. El frontend muestra una confirmación previa con país, número, operador y precio.
3. Si WooCommerce no está activo, el frontend envía `account_number`, `sku_code`, `send_value`, `send_currency_iso` al endpoint `/transfer`.
4. Si WooCommerce está activo, el frontend llama a `/add-to-cart`, redirige al checkout y la recarga real se ejecuta cuando el pedido pasa a `processing` o `completed`.
5. Backend aplica política de `validate_only` y `allow_real_recharge`.
6. Toda operación queda registrada en un log interno.

## Capacidades nuevas ya implementadas

1. Integración opcional con WooCommerce sin romper el modo directo existente.
2. Endpoint REST `add-to-cart` para iniciar flujo de compra.
3. Rate limiting básico por IP en `products`, `transfer` y `add-to-cart`.
4. Caché temporal de productos por número durante 10 minutos.
5. Normalización de respuestas DingConnect de `Items` a `Result` en backend.
6. Registro de intentos de transferencia en `Transfer Logs`.
7. Resultado visual amigable en frontend en lugar de JSON crudo.
8. Catálogo admin simplificado: `Catálogo y alta` opera con búsqueda en API y alta manual, eliminando la dependencia del subflujo `Buscar en CSV` dentro del plugin.
9. Formulario público del shortcode con auto-búsqueda al editar número móvil o cambiar país, evitando dependencia del botón Buscar paquetes.
10. Release de branding aplicado en plugin WordPress: versión 1.2.0 con créditos visibles "Hecho por Cambiodigital.net" y "personalizado para cubakilos.com" en componentes clave de administración y frontend.
8. Gestión de bundles guardados en panel admin con edición, activación/desactivación y eliminación por fila.
9. Panel administrativo reorganizado en pestañas para operación más rápida: pestaña de configuración centrada en credenciales/modo/balance, pestaña operativa de catálogo y alta (2-3-4) y pestaña especial de bundles guardados (5).
10. Edición de bundles guardados optimizada: el botón Editar abre un modal inline en la pestaña de bundles guardados, evitando navegación o recarga visual entre pantallas para cambios rápidos.
11. Sincronización frontend-admin mejorada: la deduplicación de búsquedas por país+número en el frontend ahora usa expiración (TTL de 10 segundos), permitiendo que bundles y operadores añadidos en admin aparezcan sin recargar la página completa.
12. Frontend público más resiliente: el script del shortcode ahora resuelve elementos dentro del contenedor del formulario y valida nodos críticos para evitar errores por `innerHTML` en elementos nulos cuando hay markup incompleto o plantillas desactualizadas.
13. Recargas directas más compatibles en producción: el backend REST del plugin normaliza `AccountNumber` en formato numérico puro (sin `+`) para cumplir validaciones regex de DingConnect y retorna el status HTTP real de errores de API al cliente.
14. Diagnóstico operativo más claro: el backend API del plugin interpreta códigos de negocio de DingConnect (`InsufficientBalance`, `AccountNumberInvalid`, `RateLimited`, `RechargeNotAllowed`) y devuelve mensajes orientados a soporte sin perder el detalle técnico original.
15. Catálogo público más robusto por país: el endpoint `/products` prioriza la consulta por `country_iso`, normaliza productos DingConnect al contrato del frontend y resuelve nombres de operador desde `ProviderCode`, evitando que operadores válidos queden ocultos cuando la API no envía `ProviderName`.
15. Verificación interna de saldo disponible: el plugin expone `GET /wp-json/dingconnect/v1/balance` con permisos de administrador y lo integra en el panel de credenciales mediante un botón de consulta directa.
16. Experiencia de balance mejorada en admin: al activar la pestaña Credenciales se consulta el balance automáticamente y se muestra en tarjeta legible (monto, moneda y estado), evitando salida JSON cruda para operación diaria.
17. Mejora visual en frontend público: la selección de operador ahora usa una grilla uniforme con tarjetas del mismo espacio y altura, para una lectura más organizada y consistente entre operadores.
18. Mejora UX en panel admin de bundles: los campos País ISO, Nombre comercial, Moneda y Operador quedaron unificados con combobox de apertura inmediata al clic, lista posicionada bajo el input, filtrado por texto y sincronización de nuevas opciones entre formulario de alta y modal de edición.
19. Resiliencia del balance administrativo: el endpoint `/balance` normaliza múltiples formatos de respuesta de DingConnect (top-level, `Result` o `Items`) para exponer siempre `Balance`, `CurrencyIso` y `ResultCode` al panel.
20. Diagnóstico operativo ampliado en transferencias: los errores `ProviderError` ahora incluyen contexto (`ding_error_context`) y referencias de la operación (`transfer_ref`, `distributor_ref`, `processing_state`) para soporte y trazabilidad.
21. Operación de bundles más ágil en admin: los bundles guardados ya pueden eliminarse de forma masiva mediante checkboxes por fila y selección global en la tabla.
22. Base del wizard v2 implementada en backend: nueva clase `DC_Recargas_Wizard` con máquina de estados inicial y transición de pasos.
23. Persistencia de sesiones de wizard en base de datos: tabla dedicada para recuperar estado entre requests con expiración.
24. Configuración de wizard en admin: flag `wizard_enabled`, máximo de ofertas por categoría y mapeo de teléfono para checkout WooCommerce.
25. Shortcode base de wizard disponible (`[dingconnect_wizard]`) para iniciar landings externas con modo de entrada configurable.
26. Endpoint `wizard/offers` operativo con filtros de categoría, modo de entrada (number-first/country-fixed) y prefijo fijo opcional.
27. Taxonomía inicial del wizard activa en backend: clasificación base recargas vs gift cards, orden determinístico y límite por categoría.
28. Sincronización manual de catálogo wizard: endpoint `wizard/sync-now` para administradores, con detección de cambios y payload de notificaciones por país.
29. Contrato REST normalizado para endpoints wizard: respuestas incluyen `endpoint`, `contract_version` y metadatos `backend_only`.
30. Frontend wizard v2 activo: assets dedicados (`assets/js/wizard-core.js`, `assets/css/wizard.css`) y variantes de shortcode para landings (`dingconnect_wizard_recargas`, `dingconnect_wizard_giftcards`, `dingconnect_wizard_cuba`).
31. Payment-first reforzado en WooCommerce: `process_recarga_on_payment` ahora exige `order->is_paid()` y aplica idempotencia por item para evitar doble despacho.
32. Reintentos configurables para fallos transitorios: nuevas opciones `wizard_transfer_retry_attempts` y `wizard_transfer_retry_delay_minutes` con programación de retries vía `wp_schedule_single_event`.
33. Reconciliación manual disponible en pedido WooCommerce: acción `dc_recargas_manual_reconcile` para reintentar items fallidos y dejar auditoría en notas del pedido.
34. Voucher de confirmación integrado en WooCommerce: resumen visible en pantalla de thank-you y enriquecimiento de metadatos en email con referencias de transferencia.
35. Entorno de staging reproducible agregado en repositorio para verificación runtime: `staging/docker-compose.yml`, `staging/scripts/bootstrap-staging.sh`, `scripts/staging-up.ps1` y `scripts/run-matrix-6.ps1`.
36. Matriz 6.1-6.7 preparada para ejecución automática tipo smoke sobre WordPress/WooCommerce local con gateways de prueba internos (BACS/Cheque/COD).
37. Bloqueo actual de ejecución en esta estación: binario `docker` no disponible en PowerShell, impidiendo levantar contenedores y recolectar evidencia E2E runtime.
38. Refresh visual del panel admin del plugin: nueva cabecera operativa con chips de estado y KPIs rápidos (bundles/landings), mejoras de jerarquía visual en tarjetas y tablas, tabs con comportamiento sticky y foco accesible en controles para una experiencia de operación más clara y moderna.
39. Re-categorización del panel admin por responsabilidad: `Wizard y landings` ahora vive en pestaña propia, con nombre operativo corregido (`Wizard de pruebas internas`) y flujo de navegación ajustado para que edición/acciones de landings abran siempre en su sección.
40. Catálogo admin enfocado en operación live: en `Catálogo y alta` se mantiene `Buscar en API` como método principal y `Alta manual` como respaldo operativo.
41. Mejora UX en catálogo por API del admin: `Paquetes encontrados` ahora informa el uso de doble click para `Alta manual` y ese gesto precarga el bundle seleccionado en el formulario manual para revisión antes del alta.
42. Mejora UX en `Buscar en API`: se añadió un filtro secundario `Tipo de paquete` y agrupación visual de resultados usando tres patrones operativos derivados del catálogo CSV (`Saldo / top-up`, `Datos`, `Combo / voz + datos`), con fallback `Otros` para productos fuera de esos patrones.
43. Mejora UX en `Buscar en API`: el warning `Selecciona un país antes de buscar.` dejó de reutilizar el bloque de ayuda de resultados y ahora aparece sobre el selector de país con estilo visual naranja para señalar mejor la acción requerida.
44. Persistencia local en `Buscar en API`: la última consulta exitosa del admin queda guardada por navegador (`localStorage`) con país, filtro, texto y resultados restaurables para continuar catalogación sin reconsultar de inmediato a DingConnect.
45. Mejora UX en `Alta manual`: cuando un paquete se carga con doble click desde `Buscar en API`, ahora se muestra junto a `Datos del bundle` una etiqueta con el nombre limpio del paquete seleccionado (`label` API) para dar trazabilidad visual durante la edición manual.
46. Corrección de navegación en `Catálogo y alta`: la subpestaña `Alta manual` ahora se activa de forma explícita y consistente, tanto al hacer click directo como al mover un paquete desde `Buscar en API` por doble click, evitando depender solo de eventos `click()` simulados.
47. Mejora UX en `Wizard y landings`: la selección de `Bundles de la landing` ahora usa checklist con checkboxes en alta y en el modal de edición, reemplazando el selector múltiple con `Ctrl/Cmd` para hacer explícito qué bundles se agregan o quitan.
48. Mejora UX en frontend público (`dingconnect_recargas`): el formulario ahora incorpora bloque `Paquete activo` bajo el número con selector y detalle del bundle (Beneficios recibidos, Operador, Monto y moneda); al seleccionar paquete, la confirmación pasa al bloque superior y se ocultan los pasos inferiores hasta solicitar `Cambiar paquete`.
49. Corrección UX en panel admin para inputs con buscador (combobox sobre datalist): se completó la inicialización JavaScript compartida (`datalistMap`, `comboboxRegistry`, `addToDatalist`) y se ajustó el tamaño visual del campo `País fijo (ISO, opcional)` en alta/edición de landings, eliminando el render estrecho y comportamiento inconsistente del dropdown.
50. Simplificación operativa de landings: el panel admin dejó de pedir `País fijo (ISO, opcional)` en alta/edición de shortcodes dinámicos; el país se infiere desde los bundles seleccionados para evitar configuración duplicada.
51. Regla de país en frontend de landings: el selector de país ya no se bloquea por configuración fija y se alimenta con los `country_iso` de los bundles permitidos en esa landing.
52. Estabilidad reforzada en `Catálogo y alta`: el cambio entre `Buscar en API` y `Alta manual` quedó centralizado en un único controlador JS con estado explícito, para evitar bloqueos o navegación inconsistente al alternar subpestañas.

## Hallazgos clave para futuras IA

- El repositorio tiene dos líneas de integración coexistiendo: prototipo legado y plugin WordPress.
- El plugin es la base recomendada para evolución futura.
- El prototipo legado sigue siendo útil para pruebas UX y experimentación rápida.
- Se dispone de SKUs reales para Colombia (Claro y top-up) documentados en `HALLAZGOS_2026-04-14_DING_SKUS.md`.
- Cuando WooCommerce está activo, el flujo objetivo ya no es "buscar y disparar recarga" sino "buscar, anadir al carrito y despachar al confirmar el pago".
- Si WordPress reporta "12 caracteres de salida inesperados durante la activación", revisar primero la codificación UTF-8 BOM en los archivos PHP del plugin; cuatro archivos con BOM generan exactamente esos 12 bytes de salida antes de enviar cabeceras.
- Si la URL de activación muestra una ruta anidada como `carpeta-extra/dingconnect-recargas/dingconnect-recargas.php`, tratarlo como síntoma de empaquetado no canónico o de copias duplicadas en `wp-content/plugins`.
- Si el plugin se activa pero muestra el aviso de archivos requeridos faltantes, puede deberse a que el servidor Unix interpreta literalmente los `\` del ZIP de Windows. El bootstrap ahora tolera ambas variantes: `includes/class-dc-api.php` y `includes\class-dc-api.php`, usando `DIRECTORY_SEPARATOR` para ser agnóstico.
- Para repetir verificación de fase 6 en local, el flujo operativo recomendado ahora es `./scripts/staging-up.ps1` seguido de `./scripts/run-matrix-6.ps1`; si falla, validar primero disponibilidad de `docker compose` en la terminal activa.

## Riesgos y brechas actuales

1. Exposición de credencial en frontend legado (`recargas.html`).
2. Desalineaciones entre prototipo y plugin en nombres de parámetros REST:
	 - Prototipo usa `accountNumber` y `CONFIG.wpProxyBase = /wp-json/cubakilos/v1`.
	 - Plugin usa `account_number` y namespace `dingconnect/v1`.
3. Posible diferencia de shape de respuesta (`Result` vs `Items`) entre llamadas y mapeos.
4. El flujo WooCommerce depende de que el pedido alcance `processing` o `completed`; pasarelas con estados intermedios deben validarse manualmente.
5. El plugin ya normaliza `Items -> Result`, pero todavía falta documentar un contrato canónico más amplio para promociones, rangos y receipts.
6. DingConnect no garantiza `ProviderName` dentro de `GetProducts`; para renderizar operadores de forma consistente hay que enriquecer el catálogo con `GetProviders` usando `ProviderCode`.

## Fuente de verdad recomendada

Para nuevas funcionalidades, tomar como referencia en este orden:

1. Código del plugin WordPress (`dingconnect-wp-plugin/dingconnect-recargas/`).
2. `GUIA_TECNICA_DING_CONNECT.md`.
3. `API_DING_CONNECT_V1.md`.
4. `HALLAZGOS_2026-04-14_DING_SKUS.md`.
5. `recargas.html` solo como referencia histórica o de UX.

## Reglas operativas para próximos cambios

- Evitar cambios de negocio en `recargas.html` salvo pruebas o prototipos rápidos.
- Implementar nuevas capacidades en el plugin.
- Mantener `ValidateOnly` activo por defecto hasta cerrar ciclo de pruebas reales controladas.
- Versionar y fechar cualquier actualización del catálogo de SKUs.
- Documentar cada cambio funcional en `BACKLOG_FUNCIONAL_TECNICO.md` y en el archivo que lo implemente.
- Si WooCommerce está habilitado, validar también carrito, checkout, notas del pedido y logs internos antes de activar recarga real.
- Guardar los archivos PHP del plugin en UTF-8 sin BOM para evitar salida invisible durante activación, actualización y carga temprana del plugin.
- Empaquetar preferentemente el plugin con sus archivos en la raíz del ZIP canónico `dingconnect-recargas.zip` para evitar carpetas contenedoras adicionales en la instalación.

## Lista de lectura rápida para cualquier IA antes de trabajar

1. `Documentación/CONTEXTO_IA.md`
2. `Documentación/BACKLOG_FUNCIONAL_TECNICO.md`
3. `Documentación/GUIA_TECNICA_DING_CONNECT.md`
4. `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-rest.php`
5. `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-api.php`
