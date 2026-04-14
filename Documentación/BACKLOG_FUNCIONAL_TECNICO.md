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
2. Validación manual en entorno WordPress.
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
2. Buscador sobre `Products-with-sku.csv` integrado en admin con selector de resultados para autocompletar alta de bundles.
3. Prevención de duplicados al importar presets por clave `country_iso + sku_code`.
4. Creación automática de bundle desde resultado CSV seleccionado (publicación inmediata opcional).
5. Integración opcional con WooCommerce para anadir al carrito, checkout obligatorio y despacho al confirmar el pago.
6. Registro interno de transferencias y normalización backend de respuestas `Items`/`Result`.
7. Rate limiting básico por IP en endpoints operativos del plugin.
8. Reorganización de la interfaz del panel admin en pestañas: sección 1 junto con uso en frontend (6) en pestaña propia, secciones 2-3-4 en pestaña operativa y sección 5 en pestaña especial para bundles guardados.
9. Gestión operativa de bundles guardados en admin con edición, activación/desactivación y eliminación.
8. Mejora UX en buscador CSV del panel admin: carga automática inicial de 10 resultados de países principales (CO, ES, MX, CU) y mensaje explícito en resultados cuando no hay CSV cargado.
9. Mejora UX en buscador CSV del panel admin: selector de país dinámico con todos los países detectados en el CSV cargado y búsqueda automática al escribir o cambiar país, sin depender del botón Buscar.
10. Mejora UX en frontend público de recargas: auto-búsqueda de paquetes al escribir el número móvil o cambiar el país, con debounce y sin necesidad de pulsar el botón Buscar paquetes.
11. Optimización del frontend público: deduplicación de consultas para no repetir llamadas al endpoint cuando país y número no cambiaron o ya hay una consulta idéntica en curso.
12. Actualización de release y branding del plugin: versión 1.2.0, créditos visibles de Cambiodigital.net y personalización para cubakilos.com en cabecera del plugin, panel admin, frontend y manuales del plugin.
13. Modernización visual del panel admin: eliminación de numeración en títulos y pestañas, actualización de estilo con tabs tipo píldora, paneles en tarjeta y jerarquía visual más limpia para reducir la apariencia clásica de WordPress.
14. Mejora UX en bundles guardados: la acción Editar ahora abre un modal inline sobre la tabla, sin salto de pantalla, para actualizar el bundle de forma rápida y contextual.
15. Corrección de sincronización frontend-admin: el caché de búsqueda por país+número en el frontend ahora expira (TTL 10 segundos) para refrescar operadores y bundles nuevos sin recargar toda la página.
16. Corrección de robustez en frontend público: el script del shortcode ahora valida nodos requeridos y maneja markup parcial sin lanzar errores de JavaScript como `Cannot set properties of null (setting 'innerHTML')` durante la búsqueda automática de paquetes.
17. Corrección productiva en recargas: el backend REST ahora normaliza `AccountNumber` a solo dígitos (sin símbolo `+`) para cumplir validación regex de DingConnect en `SendTransfer`, y propaga el código HTTP real del error para diagnóstico operativo.
18. Mejora de diagnóstico productivo: el cliente API del plugin ahora traduce códigos de negocio de DingConnect (incluyendo `InsufficientBalance`) a mensajes claros para operación y soporte, manteniendo el detalle técnico en `error_data`.
19. Corrección de catálogo multioperador: el endpoint `/products` del plugin ahora consulta productos por país cuando existe `country_iso`, normaliza precios y campos clave al contrato del frontend y completa `ProviderName` a partir de `ProviderCode`, evitando que operadores válidos queden ocultos en países como Colombia.
19. Operación administrativa reforzada: se agregó endpoint interno `GET /wp-json/dingconnect/v1/balance` (solo administradores) y botón "Consultar balance ahora" en la pestaña de credenciales para validar saldo del agente desde WordPress.
20. Mejora UX en pestaña Credenciales: el balance se actualiza automáticamente al entrar a la pestaña (con control de frecuencia) y se presenta como tarjeta amigable con monto, moneda y estado en lugar de JSON.
21. Mejora UX en selección de operador del frontend: los operadores se muestran en una grilla uniforme con tarjetas de tamaño consistente para mantener alineación visual y espacio equivalente por operador.
22. Mejora UX en administración de bundles: campos País ISO, Nombre comercial, Moneda y Operador ahora usan combobox consistente (alta y edición) con apertura inmediata al clic, opciones alineadas debajo del input, filtrado al escribir y alta libre de nuevos valores compartidos entre formularios.
23. Respuesta de balance robusta en admin: el endpoint interno `/balance` normaliza variaciones de payload de DingConnect para mostrar siempre saldo, moneda y código de resultado en la tarjeta de credenciales.
24. Diagnóstico extendido de recargas fallidas: errores `ProviderError` agregan contexto (`ding_error_context`) y trazas de operación (`transfer_ref`, `distributor_ref`, `processing_state`) para investigación de soporte.
25. Gestión masiva de bundles en admin: la tabla de bundles guardados ahora permite seleccionar uno o varios registros (incluye "seleccionar todos") y eliminarlos en bloque con confirmación.

## Backlog actualizado por impacto

1. Prioridad P2 - Administración de bundles más completa.
   - Estado: parcialmente completado.
   - Completado: importación desde CSV filtrado, catálogo inicial multi-país, edición de bundles en modal inline, activación/desactivación por registro y eliminación masiva por selección.
   - Pendiente: flujo de actualización masiva de campos (bulk edit).
2. Prioridad P1 - Flujo WooCommerce y post-pago.
   - Estado: parcialmente completado.
   - Completado: add-to-cart, checkout obligatorio, creación de producto base, ejecución de transferencia al confirmar pedido y notas en orden.
   - Pendiente: validación completa con pasarela real, manejo fino de estados de pedido y reconciliación con `ListTransferRecords`.
3. Prioridad P2 - Observabilidad operativa.
   - Estado: parcialmente completado.
   - Completado: logs internos de transferencias.
   - Pendiente: filtros, búsqueda y panel de soporte sobre logs.

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
