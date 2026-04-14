# Manual de usuario (breve) - DingConnect Recargas

## 1. Objetivo
Este plugin permite publicar una sección de recargas en WordPress y operar recargas/bundles con DingConnect desde un panel de administración.

## 2. Requisitos
- WordPress 6.0 o superior.
- PHP 7.4 o superior.
- API Key activa de DingConnect.
- Usuario administrador de WordPress (permiso manage_options).

## 3. Instalación del plugin

### Opción A: instalar por ZIP (recomendada)
1. Comprime la carpeta del plugin en un archivo ZIP.
2. En WordPress, ve a Plugins > Añadir nuevo > Subir plugin.
3. Sube el ZIP, instala y activa.

### Opción B: instalación manual (FTP/hosting)
1. Copia la carpeta dingconnect-recargas dentro de wp-content/plugins.
2. En WordPress, ve a Plugins.
3. Activa DingConnect Recargas.

## 4. Configuración inicial en panel admin
1. En el menú lateral de WordPress, entra en DingConnect.
2. En la sección Credenciales y modo de operación:
   - API Base URL: usa la URL oficial de DingConnect (por defecto ya viene cargada).
   - API Key DingConnect: pega tu clave real.
   - ValidateOnly por defecto: déjalo activado para pruebas seguras.
   - Permitir recarga real: mantenlo desactivado al inicio.
3. Guarda la configuración.

## 5. Publicar o insertar la sección pública
El plugin expone un shortcode:

[dingconnect_recargas]

Puedes insertarlo de estas formas:
- En una página nueva (ejemplo: Recargas), pegando el shortcode en el editor.
- En una entrada, si deseas mostrar el formulario en un post.
- En un bloque/widget de tipo Shortcode (si usas constructor visual o áreas de widgets).

Flujo recomendado:
1. Crea la página Recargas.
2. Inserta el shortcode.
3. Publica.
4. Abre la URL pública y valida que se vea el formulario (país, móvil, búsqueda y botón de recarga).

## 6. Uso del panel admin (día a día)

### 6.1 Añadir bundles curados
En DingConnect > Añadir bundle curado:
- País ISO (obligatorio).
- Nombre comercial (obligatorio).
- SKU Code (obligatorio).
- Monto y moneda.
- Operador y descripción.
- Activo: si está marcado, aparece en el frontend como bundle disponible.

Luego pulsa Añadir bundle.

### 6.2 Gestionar bundles guardados
En DingConnect > Bundles guardados:
- Verás país, nombre, SKU, monto, moneda, operador y estado.
- Puedes eliminar bundles que ya no quieras mostrar.

Nota: actualmente el panel permite añadir y eliminar; no incluye edición directa del bundle.

### 6.3 Probar antes de operar en real
1. Mantén ValidateOnly por defecto activado.
2. Mantén Permitir recarga real desactivado.
3. Haz pruebas de búsqueda y envío para validar integración.
4. Cuando todo esté validado, habilita Permitir recarga real y realiza una recarga controlada.

## 7. Flujo de uso en frontend
1. El cliente selecciona país.
2. Ingresa número móvil.
3. Pulsa Buscar paquetes.
4. Selecciona bundle.
5. Pulsa Procesar recarga.
6. Revisa la respuesta mostrada en pantalla.

Si DingConnect no responde, el sistema puede mostrar bundles curados activos como respaldo.

## 8. Verificación rápida de funcionamiento
- Abre el frontend y valida que cargue sin errores visuales.
- Comprueba que la API Key esté cargada en el panel.
- Verifica que haya bundles activos en el admin.
- Ejecuta una prueba en modo seguro (ValidateOnly).

## 9. Buenas prácticas
- No compartas la API Key con usuarios no administradores.
- Mantén modo seguro activo mientras configuras.
- Activa recarga real solo cuando estés listo para producción.
- Guarda solo bundles vigentes para evitar errores de compra.
