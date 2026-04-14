# Manual de usuario - DingConnect Recargas v1.2.0

Hecho por Cambiodigital.net, personalizado para cubakilos.com.

## 1. Objetivo

Este plugin permite publicar un formulario de recargas en WordPress, consultar productos de DingConnect y operar dos modos de salida:

- Modo directo: el frontend llama al endpoint interno de transferencia del plugin.
- Modo WooCommerce: el cliente añade la recarga al carrito, pasa por checkout y la recarga real se ejecuta cuando el pedido entra en procesamiento o completado.

## 2. Requisitos

- WordPress 6.0 o superior.
- PHP 7.4 o superior.
- API Key activa de DingConnect.
- Usuario administrador de WordPress con permiso `manage_options`.
- WooCommerce opcional, solo si se quiere cobrar y despachar la recarga desde carrito y checkout.

## 3. Instalación del plugin

### Opción A: instalar por ZIP

1. 
2. En WordPress, ve a `Plugins > Anadir nuevo > Subir plugin`.
3. Sube el ZIP, instala y activa.

### Opción B: instalación manual

1. Copia la carpeta `dingconnect-recargas` dentro de `wp-content/plugins`.
2. En WordPress, ve a `Plugins`.
3. Activa `DingConnect Recargas`.

## 4. Configuración inicial

1. En el menú lateral de WordPress, entra en `DingConnect`.
2. En `Credenciales y modo de operación`, configura:
   - `API Base URL`: usa `https://www.dingconnect.com/api/V1` salvo que Ding indique otra base.
   - `API Key DingConnect`: pega tu clave real.
   - `ValidateOnly por defecto`: mantenlo activado durante pruebas.
   - `Permitir recarga real`: mantenlo desactivado hasta validar el flujo completo.
3. Guarda la configuración.

## 5. Gestión del catálogo curado

### 5.1 Importar bundles sugeridos

En `DingConnect`, usa `Importar bundles sugeridos (CO, ES, MX, CU)` para cargar un catálogo inicial sin duplicar combinaciones `pais + SKU`.

### 5.2 Buscar productos en el CSV

Si el archivo `Products-with-sku.csv` está disponible, el panel permite:

- Buscar por SKU, operador, país o descripción.
- Filtrar por país.
- Seleccionar un resultado del CSV.
- Crear un bundle automáticamente desde la selección.
- Publicarlo de inmediato como activo, si la casilla correspondiente está marcada.

### 5.3 Añadir bundles manualmente

En `Anadir bundle curado`, completa:

- País ISO.
- Nombre comercial.
- SKU Code.
- Monto y moneda.
- Operador.
- Descripción.
- Estado activo.

Después pulsa `Anadir bundle`.

### 5.4 Gestionar bundles guardados

En `Bundles guardados` puedes revisar el catálogo activo y eliminar bundles que ya no deban mostrarse. No existe edición directa desde la tabla; si necesitas ajustar un bundle, debes eliminarlo y volver a crearlo.

## 6. Publicación del frontend

El plugin expone el shortcode:

`[dingconnect_recargas]`

Uso recomendado:

1. Crea una página, por ejemplo `Recargas`.
2. Inserta el shortcode en el editor.
3. Publica la página.
4. Abre la URL pública y valida que carguen país, teléfono, búsqueda y selector de bundle.

## 7. Cómo funciona el frontend hoy

### 7.1 Consulta de productos

1. El cliente selecciona el país.
2. Escribe el número móvil.
3. Pulsa `Buscar paquetes`.
4. El plugin consulta `GetProducts` por REST.
5. Si DingConnect no devuelve productos válidos, el frontend usa los bundles curados activos como fallback.

### 7.2 Confirmación previa

Antes de continuar, el frontend muestra una confirmación con:

- País.
- Número.
- Nombre del paquete.
- Operador.
- Precio.

### 7.3 Si WooCommerce no está activo

El botón final ejecuta `Procesar recarga` y llama al endpoint interno de transferencia. La respuesta se muestra en una tarjeta amigable con referencia, estado, número y valor recibido cuando aplique.

### 7.4 Si WooCommerce está activo

El botón cambia a `Anadir al carrito` y el flujo pasa a ser:

1. Añadir la recarga al carrito.
2. Redirigir al checkout.
3. Obligar registro del cliente durante la compra.
4. Ejecutar la transferencia real cuando el pedido entra en `processing` o `completed`.

## 8. Logs y trazabilidad

El plugin registra cada intento de transferencia en un tipo de contenido interno llamado `Transfer Logs`.

En esos registros quedan guardados:

- Número enmascarado.
- SKU.
- Monto y moneda.
- `DistributorRef`.
- `TransferRef` cuando exista.
- Estado final o error.
- Respuesta cruda serializada.

Esto sirve para soporte operativo, seguimiento y validaciones posteriores.

## 9. Reglas operativas importantes

### 9.1 Seguridad de envío real

- Aunque WooCommerce esté activo, la recarga real solo debe ejecutarse cuando `Permitir recarga real` esté habilitado.
- Mantén `ValidateOnly` activo mientras haces pruebas técnicas.

### 9.2 Límite básico de solicitudes

El plugin incorpora rate limiting por IP para reducir abuso:

- `products`: hasta 20 solicitudes por minuto.
- `add-to-cart`: hasta 10 solicitudes por minuto.
- `transfer`: hasta 5 solicitudes por minuto.

Si se excede el límite, el usuario recibe un mensaje para reintentar al minuto siguiente.

### 9.3 Caché de productos

La búsqueda de productos por número usa caché temporal de 10 minutos para reducir llamadas repetidas a DingConnect.

## 10. Verificación rápida

1. Confirma que la API Key esté cargada en el panel.
2. Verifica que existan bundles activos para al menos un país.
3. Abre la página pública y prueba una búsqueda válida.
4. Confirma que aparezca el selector de bundles.
5. Ejecuta una prueba en modo seguro.
6. Si WooCommerce está activo, valida también el paso por carrito y checkout.

## 11. Buenas prácticas

- No compartas la API Key con usuarios no administradores.
- Mantén modo seguro activo durante configuración y QA.
- Activa recarga real solo cuando el ciclo completo haya sido probado.
- Revisa periódicamente `Transfer Logs` para detectar errores repetidos.
- Mantén actualizado el catálogo curado para evitar SKUs obsoletos.
