# Decisiones pendientes — Solicitudes de René (reunión 15-abr-2026)

> **Instrucciones**: Para cada punto, marcá con `[x]` la opción que coincida con lo que habló René.
> Si ninguna opción aplica, escribí la correcta en "Otra".
> Cuando esté completo, lo usamos como input para `/sdd-init` y el plan de fases.

---

## 1. Categorización y filtrado de productos

### 1.1 ¿Qué tipos de producto va a vender CubaKilos?

René mencionó que DingConnect maneja muchos servicios. Marcá todos los que apliquen:

- [ ] **Solo recargas móviles** (Cubacel, Nauta)
- [X] **Recargas + Gift Cards**
- [ ] **Recargas + Gift Cards + otros** (DTH, electricidad, etc.)

> Referencia reunión: *"no es solamente recargas, entonces hay muchísimos servicios que maneja DingConnect, pero bueno, tu producto principal sería recargas"* (00:06:19)

### 1.2 ¿Cómo se categorizan los productos en el frontend?

René mostró doctorsim.com que divide en "Recargar Móvil" y "Digital Gift Cards" como tabs separados. DimeCuba usa tabs "RECARGA CUBACEL / PLANES MOVILES / NAUTA". aCuba es más directo con un solo formulario.

- [ ] **Opción A — Tabs tipo doctorsim**: Separar en categorías amplias (Recargas | Tarjetas Regalo | Otros)
- [ ] **Opción B — Tabs tipo dimecuba**: Separar por sub-tipo específico (Recarga Cubacel | Planes Móviles | Nauta)
- [X] **Opción C — Híbrido**: Primer nivel por categoría (Recargas | Gift Cards), segundo nivel por operador/sub-tipo
- [ ] **Opción D — Sin tabs**: Todo junto pero con etiquetas visuales que distingan el tipo

> Referencia reunión: *"se revisaron ejemplos de competidores que dividen los productos por categorías como Recargas y Tarjetas de regalo para mejorar la experiencia del usuario"* (00:14:06)

### 1.3 ¿Cuántos productos/ofertas mostrar al cliente?

René dijo que no quiere marear al cliente:

- [ ] **3-4 ofertas** por categoría (lo que René pidió explícitamente)
- [X] **5-6 ofertas** por categoría (un poco más de variedad)
- [ ] **Todas las disponibles** con scroll
- [X] **Configurable desde admin** — René elige cuántas mostrar por landing

> Referencia reunión: *"para simplificar la vista y evitar marear al cliente, se deben mostrar solamente entre tres y cuatro ofertas principales"* (00:17:21, 00:29:10)

### 1.4 ¿Cómo se organiza la vista por país?

El plugin ya filtra por país. La pregunta es el flujo:

- [ ] **Opción A — País primero**: El cliente elige país → ve categorías → ve ofertas (flujo actual mejorado)
- [ ] **Opción B — Categoría primero**: El cliente ve tabs de categoría → filtra por país dentro
- [X] **Opción C — Número primero (tipo dimecuba/acuba)**: El cliente escribe el número → se detecta país y operador → se muestran ofertas relevantes
- [X] **Opción D — Landing por país**: Cada landing ya tiene país fijo, solo se muestran las ofertas de ese país

> Referencia reunión: *"categorizadas por destino y tipo de producto"* (00:29:10)

---

## 2. Voucher / Recibo de transacción

### 2.1 ¿Qué información debe incluir el voucher?

René mostró ejemplos de recibos de DingConnect. Marcá todo lo que debe ir:

- [X] ID de transacción (DistributorRef de DingConnect)
- [X] Estado de la operación (Completada/Pendiente/Error)
- [X] Operador (ej: Cubacel, Nauta)
- [X] Monto enviado
- [X] Monto recibido en destino (en moneda local)
- [X] Número recargado
- [X] Fecha y hora
- [X] Logo/marca de CubaKilos (marca blanca)
- [X] Promoción aplicada (si aplica, ej: "Reciben 3600 CUP")

> Referencia reunión: *"recibos de DingConnect que contienen información clave como ID de transacción, estado y operador"* (00:11:36)

### 2.2 ¿Cómo se envía la confirmación al cliente?

- [ ] **Opción A — Email plano**: Texto con los datos de la transacción
- [ ] **Opción B — Email HTML con diseño**: Template con logo, colores de marca, formato tipo factura
- [X] **Opción C — Pantalla de confirmación + Email**: Mostrar voucher en pantalla después del pago Y enviar por email
- [X] **Opción D — WooCommerce nativo**: Usar el email de "pedido completado" de WooCommerce con los datos inyectados

> Referencia reunión: *"a través de un correo electrónico plano o un voucher editable"* (00:13:02)

### 2.3 ¿La respuesta varía según el tipo de producto?

René mencionó que recargas y gift cards tienen respuestas diferentes:

- [ ] **Sí, diferenciada**:
  - Recarga: mensaje de éxito + ID operación
  - Gift card: código/PIN + instrucciones de canje + posible envío por email separado
- [X] **No, misma respuesta para todo**: Siempre mostrar el mismo template con los datos que apliquen
- [ ] **De momento solo recargas** — se resuelve gift cards después

> Referencia reunión: *"varía si es una recarga (donde basta con un mensaje de éxito) o una gift card (que podría requerir un código o un correo electrónico)"* (00:08:22)

### 2.4 ¿Marca blanca total?

- [X] **Sí, 100% marca blanca**: El cliente NUNCA ve "DingConnect" en ningún lado
- [ ] **Parcial**: Se puede mencionar "Powered by DingConnect" en letra pequeña
- [ ] **No importa por ahora**

> Referencia reunión: *"personalizable y totalmente marca blanca"* (00:13:02)

---

## 3. Pre-llenado de teléfono en WooCommerce

### 3.1 ¿Qué número se pre-llena?

El plugin pide un número al inicio para detectar país/operador. Luego WooCommerce pide datos del checkout.

- [ ] **El número del beneficiario** (el familiar en Cuba que recibe la recarga) → se arrastra al campo custom del checkout
- [ ] **El número del comprador** (quien paga) → se arrastra al campo de teléfono de WooCommerce billing
- [X] **Ambos**: El del beneficiario al custom field, el del comprador se lo pide WooCommerce normalmente

> Referencia reunión: *"solamente el número de teléfono del familiar en Cuba, el cual debe ser automáticamente arrastrado desde el dato introducido al inicio"* (00:37:39)

### 3.2 ¿Formato del campo de teléfono?

- [ ] **Con selector de país (+53)**: Como dimecuba/acuba, selector de prefijo + número
- [X] **Campo libre**: El cliente escribe el número completo
- [X] **Prefijo fijo por landing**: Si la landing es "Recargas a Cuba", el +53 ya está puesto

> Referencia: DimeCuba y aCuba ambos usan `+53` fijo con input para el número local.

---

## 4. Sincronización de paquetes

### 4.1 ¿Con qué frecuencia deben actualizarse los productos?

- [ ] **Manual**: René descarga el CSV y lo sube cuando quiera
- [X] **Semi-automática**: Botón "Sincronizar ahora" en admin que trae los datos frescos de DingConnect
- [ ] **Automática programada**: Cron de WordPress que sincroniza cada X horas
- [ ] **Combinación**: CSV como respaldo + sync automático como principal

> Referencia reunión: *"Arreglar el fallo de sincronización en tiempo real de la información de los paquetes dentro de WordPress"* (próximos pasos)

### 4.2 ¿Qué pasa cuando Cubacel cambia una promoción?

René mencionó que las ofertas cambian frecuentemente:

- [ ] **Opción A — Edición manual**: René edita el título/descripción del bundle desde admin cuando cambia la promo
- [ ] **Opción B — Sync automático + override**: El sync trae datos nuevos pero NO pisa los textos personalizados de René
- [X] **Opción C — Notificación**: El plugin detecta cambios en DingConnect y notifica a René para que actualice

> Referencia reunión: *"las ofertas de Cubacel pueden cambiar, lo que requiere actualizar el texto que ve el cliente final"* (00:20:26)

---

## 5. Bundles y Shortcodes por landing

### 5.1 ¿Cuántas landings va a tener CubaKilos inicialmente?

- [ ] **Una sola** (la principal de recargas a Cuba)
- [ ] **2-3** (por país o por tipo de producto)
- [ ] **Muchas** (una por cada país/producto/campaña)
- [X] **No definido aún** — arrancar con una y luego escalar (deben haber diferentes shortcodes que se pueden editar según la landing que se desee poner)

### 5.2 ¿Cada landing tiene su propio diseño o comparten el mismo?

- [ ] **Mismo diseño, diferentes productos**: Solo cambia qué bundles se muestran
- [X] **Diseño personalizable por landing**: Colores, layout, textos diferentes (deben haber diferentes shortcodes que se pueden editar según la landing que se desee poner, el diseño se hace aparte en wordpress, el shortcode debe poder crearse e indicar que paquetes, productos tendría, y país u operador o paquete/producto por defecto)
- [ ] **De momento un solo diseño** — personalización visual después

> Referencia reunión: *"si me hablas de que vas a tener varios landing, entonces cada una va a estar con una salida para cierto tipo de clientes, con un diseño"* (00:04:54)

### 5.3 ¿Cómo se arma un bundle?

Actualmente René puede seleccionar productos del CSV y crear bundles. La pregunta es el flujo ideal:

- [ ] **Opción A — Selección individual**: René elige producto por producto del catálogo y los agrupa
- [ ] **Opción B — Importación por filtro**: René filtra por país+categoría y crea el bundle con todos los que apliquen
- [ ] **Opción C — Presets sugeridos**: El plugin sugiere bundles populares y René los ajusta
- [X] **El flujo actual está bien** — solo necesita las mejoras de categorización

> Referencia reunión: *"seleccionar varios productos de un país, como Cuba, y crear un bundle o grupo de ofertas que luego se mostrará en el shortcode de la página"* (00:21:29, 00:33:44)

---

## 6. Prioridades y orden de entrega

### 6.1 ¿Qué necesita René PRIMERO para poder empezar a operar?

Ordená del 1 al 6 (1 = más urgente):

- [ ] `_1__` Categorización de productos (tabs, filtros)
- [ ] `_1__` Voucher/recibo de confirmación
- [ ] `_1__` Pre-llenado de teléfono en checkout
- [ ] `_1__` Sincronización de paquetes
- [ ] `_1__` Sistema de bundles por landing
- [ ] `_1__` Entrega del plugin para instalación

### 6.2 ¿Hay algo que René mencionó que NO está en esta lista?

> Escribí acá si falta algo:
>
> - Es importante definir el tema de las landings, no se van a crear landings se van a crear shortcodes predefinidos para poner en cada landing hecha externamente.
> - También se necesita que el front tenga diseño por paso, no que vaya bajando los pasos del cliente.
> - También se necesita modificar el checkout de woocommerce para este plugin, permitiendo elegir que campos se van a necesitar para el usuario a la hora de pagar.
> - Es importante definir el proceso de pago, nunca enviar la solicitud de recarga a ding-connect api sin que el pago se hubiera hecho en woocommerce exitosamente, pero que también la interfaz se mantenga consistente dentro del mismo woocommerce (preferiblemente si se pudiera mantener en la interfaz del plugin sería mejor)

---

## 7. Contexto operativo

### 7.1 ¿Qué pasarela de pago usa CubaKilos en WooCommerce?

- [X] Stripe
- [X] PayPal
- [X] Redsys
- [X] Bizum
- [ ] Otra: ________________

Usando woocommerce realmente debería permitir usar cualquier pasarela.

### 7.2 ¿CubaKilos ya tiene WooCommerce instalado y funcionando?

- [X] Sí, con productos y ventas activas
- [ ] Sí, instalado pero sin uso real
- [ ] No, hay que instalarlo como parte de este proyecto

### 7.3 ¿El WordPress de CubaKilos es gestionado por ellos o por Cambiodigital?

- [X] CubaKilos lo gestiona (nosotros solo entregamos el plugin)
- [ ] Cambiodigital lo gestiona (nosotros instalamos y configuramos)
- [ ] Compartido (nosotros configuramos, ellos operan día a día)

---

> **Cuando completes este documento**, lo usamos como base para iniciar SDD y crear las specs de cada fase con los detalles correctos.
