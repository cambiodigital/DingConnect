# 📝 Las notas

abr 15, 2026

## Revisión CRM / Ding Connect

Invitado [Jhony Alexander Alvarez Vasquez](mailto:jhony@cambiodigital.net) [Cubakilos](mailto:operaciones@cubakilos.com) [Jimmy Ospina](mailto:jimmy@cambiodigital.net) [Juan Sebastián Galeano](mailto:juan@cambiodigital.net)

Archivos adjuntos [Revisión CRM / Ding Connect](https://calendar.google.com/calendar/event?eid=M24yNzBsZmxhZGkycnVjMWVmdTB0cjMxN2ogamhvbnlAY2FtYmlvZGlnaXRhbC5uZXQ)

Registros de la reunión [Transcripción](https://docs.google.com/document/d/1ise0IqDTBygBj_jhSKEHklyBr0QaDEB2def7LzQ3u74/edit?usp=drive_web&tab=t.z1qbmq256nta) [Grabación](https://drive.google.com/file/d/19PFd8FkfgGbfxMfgyAkHaMlGDIhjyhZd/view?usp=drive_web) 

### Resumen

Revisión técnica del plugin de recargas DIN Connect y estrategias para optimizar la automatización y migración telefónica.

**Optimización del plugin DIN**  
El plugin permite personalizar paquetes y recibos mediante marca blanca facilitando la integración en WordPress y WooCommerce. Se priorizará la mejora en la interfaz del selector para categorizar productos.

**Automatización del CRM logístico**  
La inteligencia artificial gestionará el flujo de cotización y recogida recopilando datos esenciales del cliente. La migración técnica a GLS está en progreso para centralizar las operaciones logísticas.

**Transición de número telefónico**  
Se decidió implementar una migración gradual de WhatsApp Business notificando a los clientes existentes sobre el nuevo número. Se utilizará la herramienta de fusión de duplicados en CONMO.

### Próximos pasos

- [ ] \[Jhony Alexander Alvarez Vasquez\] Compartir Plugin: Enviar archivo del plugin para que Cubakilos pueda instalarlo y activarlo. Después de cambios solicitados

- [ ] \[Jhony Alexander Alvarez Vasquez\] Adaptar Recibo: Editar voucher de transacción basado en datos JSON para personalizar la marca.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Sincronización Paquetes: Arreglar el fallo de sincronización en tiempo real de la información de los paquetes dentro de WordPress.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Prellenar Teléfono: Asegurar que el número telefónico se arrastre y se complete automáticamente en el formulario de pago de WCommerce.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Enviar Invitación: Mandar invitación de reunión para revisar los avances de DIN Connect mañana.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Transcribir Flujo CRM: Transcribir la discusión del flujo CRM, confirmar detalles. Pasar requisitos documentados a Juan Sebastián Galeano.

- [ ] \[Cubakilos\] Preparar Plantillas: Elaborar un Google Doc con todas las plantillas actuales de FAQs y respuestas frecuentes.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Activar Asesoría IA: Activar etapa base de asesoría de inteligencia artificial para Cubakilos pueda comenzar a probarla.

- [ ] \[Juan Sebastián Galeano\] Ajustar GLS Producción: Ajustar flujo de trabajo de GLS a la nueva ID de producción. Finalizar el sistema para solicitar recogidas automáticas.

- [ ] \[Juan Sebastián Galeano\] Ajustar Panel CTT/GLS: Realizar ajustes solicitados al panel de administración de CTT y GLS.

- [ ] \[Cubakilos\] Cambiar Número: Poner el nuevo teléfono en publicidad, redes sociales y web.

- [ ] \[Jimmy Ospina\] Configurar Bot: Configurar el bot con el disparador y agregar el enlace directo al nuevo número. Dejar el bot en pausa para la activación de Cubakilos.

### Detalles

* **Revisión de avances del plugin de recargas DIN Connect**: Cubakilos revisó los videos y audios enviados sobre el avance del plugin y se confirma que el desarrollo se basa en un plugin de WordPress que gestiona las recargas, aprovechando el servidor y la interfaz de WordPress ([00:02:27](#00:02:27)). Se explica que el plugin incluye un panel de administración con funciones de prueba, consulta de saldo, y la opción de conectarse a WooCommerce para que las recargas se procesen después de finalizar la compra ([00:03:33](#00:03:33)).

* **Detalles del panel de administración y diseño**: Se menciona que el panel de administración permite la gestión de las recargas, aunque todavía no existe un panel para editar los estilos de los formularios, lo cual sería importante si se planean varias \*landings\* ([00:04:54](#00:04:54)). También se explicó la sección del catálogo que carga los productos o \*bundles\* de DIN Connect, con la opción de descargar un archivo CSV para cargar los paquetes inmediatamente ([00:06:19](#00:06:19)).

* **Funcionalidad de configuración de paquetes y marca blanca**: El plugin permite crear y configurar paquetes de recarga eligiéndolos directamente desde la lista de DIN Connect, donde se pueden modificar valores y descripciones para el cliente final ([00:06:19](#00:06:19)). Se discutió que el diseño de los recibos o \*vouchers\* que se envían al cliente es personalizable y totalmente marca blanca, lo que facilita su edición ([00:13:02](#00:13:02)).

* **Filtros y categorización de productos de DIN Connect**: Cubakilos notó que, aunque se filtra por país, no hay una opción nativa para filtrar por categoría de producto, ya que DIN Connect maneja recargas, \*gift cards\* y otros servicios. Jhony Alexander Alvarez Vasquez explicó que los filtros, como el de país, son segmentados manualmente a partir del archivo que proporciona DIN Connect ([00:07:35](#00:07:35)).

* **Adaptación de la respuesta al cliente según el tipo de producto**: Se señaló la importancia de adaptar la respuesta enviada al cliente, ya que esta varía si es una recarga (donde basta con un mensaje de éxito) o una \*gift card\* (que podría requerir un código o un correo electrónico) ([00:08:22](#00:08:22)). La confirmación al cliente debe incluir el ID de operación que DIN devuelve después de la recarga, que es un dato que debe programarse para ser enviado al cliente que pagó ([00:09:34](#00:09:34)).

* **Requisito de información para el recibo de compra**: Cubakilos compartió ejemplos de recibos de DIN Connect que contienen información clave como ID de transacción, estado y operador ([00:11:36](#00:11:36)). Se enfatizó la necesidad de enviar esta información como confirmación al cliente que paga, potencialmente a través de un correo electrónico plano o un \*voucher\* editable ([00:13:02](#00:13:02)).

* **Optimización de la interfaz del selector de paquetes**: Se revisaron ejemplos de competidores que dividen los productos por categorías como "Recargas" y "Tarjetas de regalo" para mejorar la experiencia del usuario ([00:14:06](#00:14:06)). Cubakilos sugirió que, para simplificar la vista y evitar marear al cliente, se deben mostrar solamente entre tres y cuatro ofertas principales, categorizadas por destino y tipo de producto ([00:17:21](#00:17:21)) ([00:29:10](#00:29:10)).

* **Definición de paquetes iniciales y capacidad de edición**: Jhony Alexander Alvarez Vasquez solicitó a Cubakilos una definición clara de los paquetes a categorizar y los diseños deseados para tener un avance puntual al día siguiente ([00:18:24](#00:18:24)). La capacidad de editar el título y la descripción de los paquetes es crucial, ya que las ofertas de Cubacel pueden cambiar, lo que requiere actualizar el texto que ve el cliente final ([00:20:26](#00:20:26)).

* **Proceso para seleccionar y personalizar paquetes de recarga**: Se confirmó el proceso mediante el cual Cubakilos puede seleccionar varios productos de un país, como Cuba, y crear un \*bundle\* o grupo de ofertas que luego se mostrará en el \*shortcode\* de la página ([00:21:29](#00:21:29)) ([00:33:44](#00:33:44)). Cada paquete o \*bundle\* creado puede ser editado para tener un texto personalizado y llamativo, mientras que el SKU permanece visible solo para uso interno ([00:34:50](#00:34:50)).

* **Organización del proceso de compra mediante WooCommerce**: Se demostró que al añadir un paquete al carrito, la información del \*bundle\* se transfiere a WooCommerce, permitiendo el pago a través de la pasarela configurada. En el formulario de pago, se requiere la información del cliente y solamente el número de teléfono del familiar en Cuba, el cual debe ser automáticamente arrastrado desde el dato introducido al inicio ([00:37:39](#00:37:39)).

* **Compromiso para la próxima revisión y temas pendientes**: Se acordó una revisión prioritaria de los avances del plugin DIN Connect para el día siguiente ([00:38:49](#00:38:49)). Jhony Alexander Alvarez Vasquez indicó que la próxima reunión se enfocará en el CRM, la migración de CTT y GLS, y que tomarían notas sobre las tareas pendientes ([00:39:53](#00:39:53)).

* **Asesoría del CRM y flujo de conversación con el cliente**: Se discutió la estructura del flujo del CRM, que comienza con la IA guiando la conversación para definir campos esenciales, resolver dudas y, finalmente, dirigir al cliente al formulario de envío ([00:41:20](#00:41:20)). La IA debe siempre insistir en obtener los datos clave, como la ciudad de origen y destino, y los detalles del paquete para poder cotizar ([00:42:47](#00:42:47)).

* **Reglas para cotización y tipos de envío**: Cubakilos especificó que, para cotizar, el cliente necesita saber el origen, destino (La Habana o provincia), peso, dimensiones y tipo de artículo (si es una batería o perfume, por ejemplo, esto limita el tipo de envío) ([00:42:47](#00:42:47)). La IA debe ofrecer los precios y tiempos de demora para los tres tipos de envío disponibles: marítimo, aéreo estándar y aéreo \*express\* ([00:45:08](#00:45:08)).

* **Soporte de la IA para finalizar el proceso de recogida**: El objetivo es que la IA asesore al cliente hasta que defina los detalles del paquete y el tipo de envío, y luego recabe el día y el rango horario para la recogida antes de enviarle el formulario final ([00:46:19](#00:46:19)). Una vez que el cliente rellena el formulario, la automatización posterior se encargará de lanzar las recogidas a través de la API de CTT ([00:48:30](#00:48:30)).

* **Estrategia para cargar la base de conocimiento de la IA**: Cubakilos propuso compilar todas las plantillas de comunicación existentes, que contienen información estructurada de preguntas y respuestas frecuentes, en un Google Doc ([00:50:58](#00:50:58)). El equipo de desarrollo utilizará esta información para que la IA la resuma y la adapte a las reglas predefinidas del flujo de trabajo ([00:52:16](#00:52:16)).

* **Avances y problemas pendientes en la automatización de recogidas**: Cubakilos expresó su frustración por no poder aprovechar las recogidas automatizadas debido a problemas técnicos y a la falta de integración de GLS, lo que les obliga a trabajar manualmente en dos sistemas ([00:54:27](#00:54:27)). Juan Sebastián Galeano indicó que la migración de GLS a un código de producción ya se terminó y que están en proceso de organizar las peticiones para solicitar recogidas con el nuevo ID ([00:57:48](#00:57:48)).

* **Ajustes y expansión de las operaciones de recogida**: La necesidad de optimizar el flujo de trabajo es más urgente debido a un nuevo contrato con FedEx, que implica la recogida de paquetes en toda Europa, lo que aumentará la carga de trabajo ([00:56:50](#00:56:50)). Se confirmó que el problema del formulario de CTT que no se activaba ya está resuelto, lo cual fue causado por los movimientos recientes de Juan Sebastián Galeano ([00:57:48](#00:57:48)).

* **Revisión del Bot para la Migración de Teléfono**: Se revisó el estado del bot necesario para la migración del número de teléfono, lo cual implica que la empresa debe comenzar a usar un nuevo número de teléfono en su publicidad y redes sociales. El propósito de esto es que el nuevo número comience a generar historial ([00:58:45](#00:58:45)).

* **Estrategia para la Transición de Números de Teléfono**: Se discutieron las implicaciones de cambiar la publicidad para el nuevo número, lo que resultará en que los clientes nuevos envíen mensajes al número nuevo, mientras que los clientes antiguos seguirán escribiendo al número antiguo (644). Jhony Alexander Alvarez Vasquez explicó que hay dos maneras de manejar la transición: dirigiendo solo a los clientes nuevos que escriban al número viejo a través del bot, o enviando el mensaje de cambio de número a todos los clientes activos, siendo esta última opción una decisión que debe tomar Cubakilos ([00:59:58](#00:59:58)).

* **Consideraciones sobre WhatsApp Business y el Cambio de Número**: Se confirmó que al usar la opción "cambiar de número" en WhatsApp Business, la aplicación notificará a los usuarios sobre el cambio, pero se perderán todas las conversaciones en la aplicación. Sin embargo, Cubakilos aclaró que las conversaciones están guardadas en el CRM y no se borrarán de allí. La estrategia preferida por Cubakilos es notificar a los clientes existentes a través de estados y el bot sobre el nuevo número antes de realizar el cambio de teléfono en el WhatsApp Business antiguo ([01:01:03](#01:01:03)).

* **Gestión de Leads Duplicados en CONMO**: Se señaló que un cliente antiguo que escriba al nuevo número de API generará otro lead duplicado en CONMO, lo cual es un problema conocido del sistema ([01:02:16](#01:02:16)). Se recomendó a Cubakilos utilizar la función de "buscar duplicados" en CONMO (accesible a través de los tres puntos en la sección de leads) para fusionar los leads y evitar la duplicación excesiva de contactos ([01:03:10](#01:03:10)).

* **Recomendación Final para la Migración del Número**: Se concluyó que la mejor práctica por el momento es que las conversaciones nuevas reciban un aviso en el WhatsApp anterior para que escriban al nuevo número, y que los nuevos clientes que lleguen por la publicidad utilicen el número actualizado ([01:05:32](#01:05:32)). Se sugirió hacer el cambio total del número en el WhatsApp antiguo después de uno o dos meses, cuando el nuevo número ya esté completamente establecido ([01:06:39](#01:06:39)).

* **Configuración y Uso del Bot de Notificación**: Jimmy Ospina confirmó que ya configuró el mensaje genérico del bot con el nuevo número y solo necesita que Cubakilos lo revise y se le configure el disparador. Se confirmó que el mensaje del bot puede incluir un enlace directo de WhatsApp para que los clientes puedan pasar fácilmente a la nueva conversación ([01:07:50](#01:07:50)).

* **Activación del Bot y Próxima Reunión**: Se acordó que Jimmy Ospina dejará el bot listo y en pausa, con el disparador configurado, incluyendo el enlace al nuevo número. Cubakilos activará el bot (dará 'play') cuando esté listo, y se programó otra reunión para el día siguiente a las 4:00 p. m. para revisar el progreso ([01:08:47](#01:08:47)).

00:14:28.685,00:14:31.685

Cubakilos: https://www.doctorsim.com/es-es/

00:18:52.841,00:18:55.841

Cubakilos: dimecuba.com/recargas-a-cuba

00:31:14.326,00:31:17.326

Cubakilos: https://acuba.com/

*Revisa las notas de Gemini para asegurarte de que sean precisas. [Obtén sugerencias y descubre cómo Gemini toma notas](https://support.google.com/meet/answer/14754931)*

*Cómo es la calidad de **estas notas específicas?** [Responde una breve encuesta](https://google.qualtrics.com/jfe/form/SV_9vK3UZEaIQKKE7A?confid=tG3klLyLFO-El8PgSvx5DxIVOAIIigIgABgDCA&detailid=standard&screenshot=false) para darnos tu opinión; por ejemplo, cuán útiles te resultaron las notas.*

# 📖 Transcripción

15 abr 2026

## Revisión CRM / Ding Connect \- Transcripción

### 00:00:00

   
**Cubakilos:** Hola. Ya  
**Jhony Alexander Alvarez Vasquez:** Ahora sí sería lo que pasó con esa reunión, pero bueno,  
**Cubakilos:** listo.  
**Jhony Alexander Alvarez Vasquez:** ya lo solucionamos. Listo. Bueno, hecho una pequeña agendita. Eh, René, ¿qué tal? ¿Cómo te ido?  
**Cubakilos:** ¿Cómo estamos?  
**Jhony Alexander Alvarez Vasquez:** Bien, bien. Está. Entonces,  
**Cubakilos:** Quedal  
**Jhony Alexander Alvarez Vasquez:** eh quería revisar primero eh muy breve, bueno, cada tema, pero especialmente lo de lo que tenemos reciente para poder orientarlo mejor. Entonces vente comparto comparto pantalla rápido. Ya está grabando también. Listo. Esto por acá está. Bueno, y me imagino que ya viste un poco de lo que lo que te mostré esta semana. Ej, yo abro aquí los el navegador,  
**Cubakilos:** Sí, los videos los vi,  
**Jhony Alexander Alvarez Vasquez:** voy a compartir.  
**Cubakilos:** los vi, los vi, los vi todos.  
**Jhony Alexander Alvarez Vasquez:** Ah, bueno, excelente. Aquí yo te voy a eh  
**Cubakilos:** Pudiste, pudiste escuchar mi audio donde te decía lo único que así había notado que podía  
   
 

### 00:02:27 {#00:02:27}

   
**Jhony Alexander Alvarez Vasquez:** Bueno, sí, claro, lo resumí inclusive.  
**Cubakilos:** mejorarse.  
**Jhony Alexander Alvarez Vasquez:** Ah, llegóas también. Hola, Sebas.  
**Juan Sebastián Galeano:** Hola, René,  
**Cubakilos:** Hola,  
**Juan Sebastián Galeano:** ¿qué tal?  
**Cubakilos:** Juan, ¿qué tal? Buen  
**Juan Sebastián Galeano:** Se estaba terminando de escuchar tu audio.  
**Cubakilos:** día.  
**Jhony Alexander Alvarez Vasquez:** Mira,  
**Juan Sebastián Galeano:** Ya, perdón.  
**Jhony Alexander Alvarez Vasquez:** dale. Y bueno, sí, con ese audio inclusive ya yo tuve otro punto importante y otro escenario eh para también incluirlo. De todas maneras no está fuera de lo de lo que ya estaba yo como pensando también. Eh, entonces, ¿qué hicimos? El desarrollo se basó en un plugin en donde dentro de él está todo el código y aprovecha pues la  
**Cubakilos:** Mhm.  
**Jhony Alexander Alvarez Vasquez:** interfaz, el servidor y todo de WordPress para gestionar pues todo el tema de de las recargas. Eh, de momento pues le ponemos información muy eh sobre la marca, bueno, y cualquier cosa para que tú lo puedas instalar. Igualmente, igualmente yo te pasaría como un archivo donde tú simplemente le das añadir plugin y ya te lo se te activa.  
   
 

### 00:03:33 {#00:03:33}

   
**Jhony Alexander Alvarez Vasquez:** Eh, se creó un panel izquierdo como el en el panel izquierdo, un panel admin,  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** bueno, ya viste un poco de esto. Este pago directo, pues más que todo es para las pruebas, como te decía, eh lo que hace el usuario, bueno, tú como pruebas pones el número y él te recarga de inmediato, ¿listo? Pero la gracia es conectarlo bajo CMO, sí, perdón, bajo WCommerce. Entonces, Wcommerce lo que va a hacer es que hasta que no termine el carrito, ya ahí eh puede enviar la solicitud de recarga. Es lo que haría de inmediato si activamos esta esta opción. Estas funciones pues es para activar el modo de pruebas para que no te descuente nada. Y bueno, agregué esto eh para mantener el saldo, consultar el saldo aquí desde este punto e lo que te sale Connect. Él tiene él tiene ciertas cositas adicionales como notificaciones,  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** como bueno, hay muchas cosas en la documentación que se pueden ir agregando a medida que los vayas necesitando.  
**Cubakilos:** Claro.  
**Jhony Alexander Alvarez Vasquez:** Entonces, si tú ves, no sé, me gustaría tener los números de de los clientes que se han registrado y llenarlos acá como este esto de registros, se puede también ir guardando todo eso.  
   
 

### 00:04:54 {#00:04:54}

   
**Jhony Alexander Alvarez Vasquez:** Luego aquí en ah, bueno, aquí al final pues esto ya lo vas conociendo todavía porque ya es algo pues que se podría ir haciendo. Eh, todavía no hay un panel como para editar los estilos, pero se puede definir uno o se puede como crear un panel donde tú vas organizando, porque si me hablas de que vas a tener varios landing, entonces ahí eh pues me imagino que una landing va a estar con una salida para cierto tipo de clientes, con un diseño. Entonces, bueno, lo ideal podría ser que se pudiera modificar ese diseño según donde vayas a poner el formulario. Eh, bueno, en catálogo eh tenemos ciertas funciones. Esto ya lo he modificado, pero no lo he subido eh para agregarlo más fácil así como con pestañas. Pero esto en resumida es el esta parte donde carga como los bondols o productos que vienen de DIN Connect es esta parte no más.  
**Cubakilos:** Mhm.  
**Jhony Alexander Alvarez Vasquez:** Y de aquí se divide en dos en el catálogo de productos que tú puedes descargar directamente DIN Connect o eh traer los paquetes directamente desde Incnect.  
**Cubakilos:** Claro.  
**Jhony Alexander Alvarez Vasquez:** Por ahí vi una regla que no permitía como 100 traer 100 productos como por cada tanto tiempo. Eso es lo hacen ellos por seguridad y demás.  
   
 

### 00:06:19 {#00:06:19}

   
**Jhony Alexander Alvarez Vasquez:** Es por eso fue que yo dije, "Listo, voy a tener un un voy a mantener el CSV para que porque uno descargándolo desde ahí puede cargar inmediatamente todos los paquetes." ¿Listo? Y hay un botón, eh, no sé si esta parte me entendiste cuando te mandé el video, que si tú le das crear aquí, él inmediatamente te crea el paquete. O si tú le das clic y eliges el paquete, él te lo pone acá abajo, que este sería como la tercera parte aquí o sección donde tú vas a a configurar tu, tu paquete. Estos son los valores al cliente final. Entonces, ya tú puedes aquí elegir otro valor o poner otra descripción, no sé como se ven en Cubayama llama Cubayama.  
**Cubakilos:** Sí. Guama.  
**Jhony Alexander Alvarez Vasquez:** Eso.  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** Entonces aquí tú puedes agregar como información así directa $100 para gastar en lo que quieras.  
**Cubakilos:** Mhm.  
**Jhony Alexander Alvarez Vasquez:** Entonces,  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** ya le das añadir bundle. Bueno, también noté, bueno, él se va directamente a bandados. También noté que eh no es solamente recargas, entonces hay muchísimos servicios que maneja que maneja eh DIN Connect, pero bueno, tu producto principal sería recargas,  
   
 

### 00:07:35 {#00:07:35}

   
**Cubakilos:** No tienen la categoría.  
**Jhony Alexander Alvarez Vasquez:** ¿verdad?  
**Cubakilos:** Ahí veo que filtras por país. Está perfecto. No tienes para filtrar por categoría de producto. No lo tienen ellos.  
**Jhony Alexander Alvarez Vasquez:** Eh, yo lo que he hecho esto, por ejemplo, este filtro no es que lo traigan ellos, yo lo que hago es como segmentar el el archivo que viene.  
**Cubakilos:** por el  
**Jhony Alexander Alvarez Vasquez:** Entonces,  
**Cubakilos:** código.  
**Jhony Alexander Alvarez Vasquez:** yo cogí, yo digo, en esta en esta posición está el país, entonces lo los ubiqué acá. ¿Sí me hago entender,  
**Cubakilos:** Bueno,  
**Jhony Alexander Alvarez Vasquez:** entonces?  
**Cubakilos:** lo puedes ahí lo puedes filtrar por el por el código inicial, por el  
**Jhony Alexander Alvarez Vasquez:** Correcto. Entonces,  
**Cubakilos:** cuo.  
**Jhony Alexander Alvarez Vasquez:** si tú me vas diciendo, sí, eh, me gustaría encontrar esto y y categorizar o clasificar de una, no hay problema.  
**Cubakilos:** Sí,  
**Jhony Alexander Alvarez Vasquez:** Ah,  
**Cubakilos:** yo lo yo por el tema de las gift cards y ve eso son gift cards.  
**Jhony Alexander Alvarez Vasquez:** que todo lo  
**Cubakilos:** Por ejemplo, App Store iTunes,  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** eso es son una gift card de Apple.  
   
 

### 00:08:22 {#00:08:22}

   
**Jhony Alexander Alvarez Vasquez:** Mm.  
**Cubakilos:** Razer, no sé qué será, pero es otra gift card. Eh, que bueno, esas son las de Cuba, pero están las de los demás países. Entonces,  
**Jhony Alexander Alvarez Vasquez:** Vale.  
**Cubakilos:** ellos tienen como tres productos principales, si no recuerdo, esto que son las recargas, eh las gift cards y no sé si algo más eh e pero bueno, como que, o sea,  
**Jhony Alexander Alvarez Vasquez:** Yo yo encontré yo encontré hasta juegos.  
**Cubakilos:** como que hay alguna categoría diferente a a Topop.  
**Jhony Alexander Alvarez Vasquez:** Eso tienen de todo. Pero eh a qué voy con esta cuando te estoy diciendo que encontré de todo. Es que cada vez que elegimos un paquete, se lo promocionamos al cliente, el cliente lo acepta y hace su recarga, hay una salida como eh una información que le llega al cliente y eso es lo que hay que saber con qué jugar. O sea, si dice recarga exitosa, pues basta con un mensaje. Pero si dice, no sé, cuando yo recargo el de App Store algún tema así de de que me llegue, no sé, un código o me llegue o se tenga que enviar un correo, bueno, algo que podamos ir descubriendo según lo que tú necesites, eso se tiene que adaptar también.  
   
 

### 00:09:34 {#00:09:34}

   
**Jhony Alexander Alvarez Vasquez:** O sea, ¿qué qué respuesta le le damos? Dependiendo del paquete. A eso voy, porque de momento si yo le doy si yo lleno el formulario y elijo un paquete de recarga, al final él va a decir recarga exitoso y ya. ¿Cuál es la confirmación ahí? que en tu teléfono va te va a llegar el mensaje diciendo, "Te recargamos este  
**Cubakilos:** Sí, no, pero tiene que llegarle al al al el teléfono en mensaje le llega automáticamente por detrás sin que tú hagas  
**Jhony Alexander Alvarez Vasquez:** paquete.  
**Cubakilos:** nada, sin que tengas que programar. nada tú, porque por ejemplo cuando yo hago las recargas hoy en día manualmente en la plataforma de DIN al familiar, o sea,  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** la persona en Cuba o de Colombia, ellos le mandan un mensaje automático.  
**Jhony Alexander Alvarez Vasquez:** Ah, sí, sí,  
**Cubakilos:** Pero pero lo el que nosotros tenemos que programar,  
**Jhony Alexander Alvarez Vasquez:** correcto.  
**Cubakilos:** que sí tienes que programar es tú, que cuando haces la recarga eh DIN te devuelve un ID de operación.  
**Jhony Alexander Alvarez Vasquez:** Mm.  
**Cubakilos:** Ese ID es el que tenemos que mandarle como confirmación al cliente que pagó.  
**Jhony Alexander Alvarez Vasquez:** Sí, correcto.  
   
 

### 00:10:31

   
**Jhony Alexander Alvarez Vasquez:** Bueno, eso eso lo podemos ir viendo. Aquí sale referencia distribuidor, eh, sale, bueno, sale ya una respuesta que probablemente es ID esté acá y tú me dices, esto hay que convertirlo y enviárselo al cliente o o eso ya se encarga de Inconnect. Bueno, eso ya lo tenemos que ir viendo también.  
**Cubakilos:** Sí, no, o sea, te digo, Dincon una cosa es el cliente que paga fuera de Cuba y otro es el que recibe dentro de Cuba. El que recibe dentro de Cuba recibe un mensaje automatizado que de hecho no lo hace ni DIN,  
**Jhony Alexander Alvarez Vasquez:** Mhm.  
**Cubakilos:** lo hace Cubacel. Parece que DIN le manda una información obviamente a Cubacel, al operador en Cuba, y el operador en Cuba le manda el SMS al al teléfono en Cuba. O sea, eso es hasta por Cuba ser. Yo yo lo que me refiero al que nosotros tenemos que tener es al que paga y dice, "Hola, una vez que paga y se hace la recarga que consultarle a DIN el código de ID de operación que te da." Por ejemplo, mira, eh, si abres DIN, bueno, tú has hecho, la abro yo para enseñarte lo que te estoy diciendo,  
**Jhony Alexander Alvarez Vasquez:** Sí, sí, dale.  
   
 

### 00:11:36 {#00:11:36}

   
**Cubakilos:** que es, o sea, es sencillo el tema.  
**Jhony Alexander Alvarez Vasquez:** Por acá tengo el mío, pero bueno, no he hecho todavía una recarga real, solo han sido las pruebas.  
**Cubakilos:** Bueno, vengo acá, eh, no sé, desde abril de marzo. Sí, vale. Mira, recarga, mira, esto es una recarga, ¿vale? Mira, v detalle, mira, esto es lo que es, ¿ve? É te devuelve un ID de transacción, estado exitoso, ahí operador, el número de destino y de transacción de operador, ¿ves? E esto más o menos.  
**Jhony Alexander Alvarez Vasquez:** Okay.  
**Cubakilos:** Uy, no, que de hecho se puede hacer así fácilmente, obviamente se le quita se le quita el monto o el monto enviado, porque ahí el monto enviado varía con respecto a lo que yo le cobro. O sea, lo importante es hasta aquí. O sea, has pagado tanto o se le manda el monto, o sea, pagaste 26 € y esto es lo que estos son los datos.  
**Jhony Alexander Alvarez Vasquez:** Vale,  
**Cubakilos:** Pero fíjate,  
**Jhony Alexander Alvarez Vasquez:** déjame un momento.  
**Cubakilos:** fíjate aquí, ellos incluso tienen imprimir un recibo,  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** míralo aquí que no sé si ve que ve d connet de transacción lo mismo.  
   
 

### 00:13:02 {#00:13:02}

   
**Cubakilos:** Vamos a ver para acá. Mírale ahí. 19031 de transacción en tal día.  
**Jhony Alexander Alvarez Vasquez:** Okay.  
**Cubakilos:** El agente somos nosotros ofrecido por DIN y tal, tal, tal, tal, tal. Aquí esto si se puede editar con facilidad, yo le enviaría poniéndome a mí aquí como o aquí o lo que sea, o sea, no tanto din eh correcto,  
**Jhony Alexander Alvarez Vasquez:** Correcto. Eso es totalmente marca blanca y muy fácil de editar. Sí.  
**Cubakilos:** ¿entiendes? Si esto se complica o se puede hacer más adel o se haría más adelante, porque a mí lo que me interesa es empezar a hacerlo automatizado ya. Si eh podemos ir enviando un mensaje plano, prácticamente texto plano por mail que diga, "Hola, información, has realizado tu recarga. Este es el ID, este es el estado, esta es la fecha, este es el operador, este es el número que pusiste de destino, el ID y bueno, pagaste 26 € no sé si  
**Jhony Alexander Alvarez Vasquez:** Okay. Mira,  
**Cubakilos:** nacional.  
**Jhony Alexander Alvarez Vasquez:** entonces ahí, ¿qué es lo que se puede hacer? Totalmente ese este voucher que se ve ahí se ve porque yo ya tengo los datos en ese Jason, ya aparecen inclusive solo es adaptarlos.  
   
 

### 00:14:06 {#00:14:06}

   
**Jhony Alexander Alvarez Vasquez:** Aquí lo que hay que revisar es el tema de eh enviar residuo por SMS desde la página directamente que estoy estoy seguro que ya nos tocaría a nosotros enviarlo. Se puede enviar es correo si le preguntamos al cliente el correo,  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** pero mi duda es si a ver aquí hay otro. Este también maneja conect o  
**Cubakilos:** Sí, esta esta gente manejan DIN Conet, creo, o sea, manejan Discon y otro más, pero por ejemplo ver lo que te comentaba,  
**Jhony Alexander Alvarez Vasquez:** eso.  
**Cubakilos:** ¿ve? O sea, enviar recargas y compras de tarjetas reales, porque ellos tienen también las tarjetas realos, ¿ves? país que aquí ellos lo hacen genérico, pero yo lo que quiero yo lo que voy a hacer es, por ejemplo, que tú entres a este este es otro competidor promoción activa, la recarga, los planetes móvil,  
**Jhony Alexander Alvarez Vasquez:** Hm.  
**Cubakilos:** dime en móvil y ve esto es lo que yo necesito, un desplegable con los paquetes de Cuba.  
**Jhony Alexander Alvarez Vasquez:** Ok.  
**Cubakilos:** Aquí yo voy a poner con el shortcut solo Cuba. idea. Móvil y dame el plan de recarga optimizado para recargas cubacel, no tarjeta de regalo, sino recarga, ¿ves?  
   
 

### 00:15:23

   
**Cubakilos:** Y aquí tiene que esa es la pregunta que te voy a hacer ahora eh, alto seguido, eh, por ejemplo, espa, dame un momento. Entonces, v estos chicos, estas personas tienen docin, esto es recarga o tarjeta de regalo. Por ejemplo, Colombia, tarjeta de regalo y ya te lleva gift cards,  
**Jhony Alexander Alvarez Vasquez:** Okay.  
**Cubakilos:** ya te marcó de nuevo, veo tien lo tiene dividido por Colombia aquí, gift card, tarjeta de regalo en Colombia y ya te sale el listado de tarjeta regalo, ¿eh?  
**Jhony Alexander Alvarez Vasquez:** Sí. Y lo único que está haciendo ahí es categorizarlos por todos y en vez del  
**Cubakilos:** Exactamente, eso es a lo que me  
**Jhony Alexander Alvarez Vasquez:** botoncito que está dentro que yo ya te tengo,  
**Cubakilos:** refería.  
**Jhony Alexander Alvarez Vasquez:** pues se puede repartir los botones en una en una interfaz más amigable,  
**Cubakilos:** Claro, mira, pero por ejemplo, Cuba, tarjeta de regalo, pero es optimizado porque yo esto hay que tenerlo presente que yo tengo, esto es muy fuerte, hay que trabajar en SEO como ganar de adquisición es muy importante para para este rubro. Eh, XB tarjeta de regalo Cuba y tienes las tarjetas de regalo. Entonces, si yo le digo a el recarga móvil, envío la recarga y le voy a decir,  
**Jhony Alexander Alvarez Vasquez:** No.  
   
 

### 00:16:28

   
**Cubakilos:** dame Cuba. Ven, fíjate que es lo mismo para ahí. En este caso, yo lo haría para Cuba. Aquí por qué lo tiene, porque le está poniendo recarga. Bueno, ya lo tiene. Bueno, ya lo tiene que ir inventado con V.  
**Jhony Alexander Alvarez Vasquez:** estáentado por el para que le le posicione más el CEO. Sí.  
**Cubakilos:** y ya te sale la promoción activa que es  
**Jhony Alexander Alvarez Vasquez:** Y dale click a uno a uno de los paquetes que está suelto ahí abajo.  
**Cubakilos:** la  
**Jhony Alexander Alvarez Vasquez:** Ese por ejemplo que dice recarga Qacel abajo más abajo.  
**Cubakilos:** Sí, sí, claro,  
**Jhony Alexander Alvarez Vasquez:** Eso ahí.  
**Cubakilos:** porque esto es cubac que es los móviles y el nauta es como la fibra en la casa.  
**Jhony Alexander Alvarez Vasquez:** Y si tú le das clic ahí a uno. A ver. Ah. como que es que eso lo tienes lleno de lo tienes lleno de  
**Cubakilos:** Vedel.  
**Jhony Alexander Alvarez Vasquez:** páginas con todas las  
**Cubakilos:** Sí, lo tiene un poco más.  
**Jhony Alexander Alvarez Vasquez:** categorías.  
**Cubakilos:** Pero, por ejemplo, mira, dime Cuba, ¿qué es lo que hizo, ves?  
   
 

### 00:17:21 {#00:17:21}

   
**Cubakilos:** Tiene como un switch aquí arriba, ¿ves? V recarga Cubacel. Cuba sereles móviles es otra cosa, o sea, que son todos dentro de los por eso te decía la categorización de los productos, porque por ejemplo para Cuba tiene el arrecado que va a hacer que es en móvil y en nauta que es la fibra en la casa. V.  
**Jhony Alexander Alvarez Vasquez:** Sí,  
**Cubakilos:** Y lo que hicieron ellos fue,  
**Jhony Alexander Alvarez Vasquez:** tiene completo.  
**Cubakilos:** ¿por qué te lo hicieron ellos? Porque esto se lo hice yo a ellos, porque des el punto de vista de SEO no hace no hay no hay no hay interés de posicionamiento por nauta. Por ejemplo, a mí no me interesa. Bueno, aquí esto vamos a hacer otro un segundo. Aquí Nauta V aquí Na Plus. Hasta cierto punto no había interés de eh posicionar na Blue porque no tiene búsqueda, no tiene búsqueda SEO. Entonces, para simplificarlo,  
**Jhony Alexander Alvarez Vasquez:** Mhm.  
**Cubakilos:** hicimos un switch porque que da tiene el tráfico es la recarga Cuba. Entonces, pero bueno, si v un switch,  
**Jhony Alexander Alvarez Vasquez:** Vale.  
**Cubakilos:** esta gente hicieron, pero si es importante categorizar para poder usar por destino y por y por tipo de producto.  
   
 

### 00:18:24 {#00:18:24}

   
**Jhony Alexander Alvarez Vasquez:** Vale. Eh, ¿cuál es sería la idea, eh, René, para que para mañana te tengamos o algo bien puntual? Dime como porque bueno, esto para arrancar deberíamos tener unos paquetes. Yo me he creado unos paquetes pues básicos eh de acá del sistema, pero bueno, otra cosa es lo que tú lo que tú vayas a necesitar para iniciar. Entonces,  
**Cubakilos:** Vale.  
**Jhony Alexander Alvarez Vasquez:** tenemos estos paquetes.  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** Ah, perdón. Eh, ¿dónde es que están? Ah, okay. Aquí que me me he creado así muy rápido, pero bueno, esto lo podemos editar a tu gusto. Mira que aquí también te mostrado que se puede editar y demás. Eh, tener esos paquetes,  
**Cubakilos:** Bueno,  
**Jhony Alexander Alvarez Vasquez:** o sea, ¿qué paquetes quieres categorizar y y qué, ¿cómo se le dice eso?  
**Cubakilos:** vale.  
**Jhony Alexander Alvarez Vasquez:** ¿Qué diseños quisieras sacar también? Porque vi ahí el selección,  
**Cubakilos:** Bueno,  
**Jhony Alexander Alvarez Vasquez:** vi el botón. Entonces,  
**Cubakilos:** sí.  
**Jhony Alexander Alvarez Vasquez:** esto es un poco flexible.  
   
 

### 00:19:22

   
**Jhony Alexander Alvarez Vasquez:** Si tú me lo dejas claro, mañana podemos hacer un buen  
**Cubakilos:** Mira, ahí te pasé ya tú tienes Kubayama,  
**Jhony Alexander Alvarez Vasquez:** avance.  
**Cubakilos:** ahí te pasé por el chat el mensaje, te pasé, bueno, el drctor Sim para que lo tengas como idea y dime Cuba, recargas a Cuba, eh, y el otro que es Dime Cuba, eh, bueno, que ese sí me faltó el HTTP, pero lo tienes por ahí. Vale, entonces, ¿qué es lo que yo necesito? Vamos al escritorio tuyo, al al WordPress ahí un momento y te comento. Como tú ves en el selector de Dime Cuba, ellos pusieron tres paquetes nada más.  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** Perdón que te vuelva para si quieres asegurarte para que lo mires un segundo. Ahora cambio la pestaña en No, en recarga. Ajá. Ve abajo a los al plan ahí en Vale.  
**Jhony Alexander Alvarez Vasquez:** Es  
**Cubakilos:** V. Tienen 1 2 3 cu.  
**Jhony Alexander Alvarez Vasquez:** aquí.  
**Cubakilos:** La última es la básica, la que mandan esta gente por que ¿Qué es lo que pasa ahí? El el plan básico de de de DIN es 500 CUP y en dependencia de si hay alguna oferta activa o no, ese plan se eh Cubacel le agrega cosas.  
   
 

### 00:20:26 {#00:20:26}

   
**Cubakilos:** Por eso es que yo te decía que necesitaba editar el texto, ¿ves? Porque si le das clic abajo, ve ahora hoy en este mes de abril por 500 pesos recibes 500 pesos más internet limitado 24 horas durante 10 días. Pero, por ejemplo, dentro de 2 semanas ese mismo plan de 500 pesos, el texto puede ser reciben 500 CUP más eh 30 minutos gratis más 5 eh horas de internet. Por eso es que yo te decía que necesitaba editar título y descripción.  
**Jhony Alexander Alvarez Vasquez:** Es  
**Cubakilos:** Ahora, como viste, ellos seleccionaron cuatro productos que son los que le interesa vender de todos los que le da DIN.  
**Jhony Alexander Alvarez Vasquez:** vale.  
**Cubakilos:** No, yo no estoy seguro si ellos están con DIN o no, pero todos los proveedores el mismo porque al final eh son cuatro proveedores los que están conectados con Cuba. Tú tienes que estar son solo cuatro los que tienen el monopolio con con Cuba. Entonces, ve, por ejemplo, DIN te de Wuelve 10 paquetes,  
**Jhony Alexander Alvarez Vasquez:** Ok.  
**Cubakilos:** pero ellos cogieron cuatro. Entonces, yo lo que necesito es en el tu en el WordPress, donde tú estás ahora en el WordPress, yo crear y yo decir, entra a la parte de catálogo, creo que es, ¿no?  
   
 

### 00:21:29 {#00:21:29}

   
**Cubakilos:** Ajá. Y yo decir, "Dame Cuba, ¿vale?" Y de Cuba, yo nada más voy a seleccionar, estaría bien otro filtro que me diga, por ejemplo, los topop limpie el desplegable el listado y no ver, por ejemplo, Riot Ansens ni ver, por ejemplo, Apec Gift Card la idea,  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** pero bueno, ahí yo iría a los top, a los planes de top up de recarga,  
**Jhony Alexander Alvarez Vasquez:** ¿Cuáles  
**Cubakilos:** por ejemplo, voy a seleccionar el Cuba Wunder,  
**Jhony Alexander Alvarez Vasquez:** son?  
**Cubakilos:** el un, el dos y el 3, digamos. Un,  
**Jhony Alexander Alvarez Vasquez:** Eh,  
**Cubakilos:** dos,  
**Jhony Alexander Alvarez Vasquez:** ¿cuál es?  
**Cubakilos:** ve.  
**Jhony Alexander Alvarez Vasquez:** Es este de  
**Cubakilos:** Yo puedo seleccionarlo, seleccionar tres,  
**Jhony Alexander Alvarez Vasquez:** acá,  
**Cubakilos:** 1, dos y tres y crear y y ese sea lo que después se muestra en el desplegable como  
**Jhony Alexander Alvarez Vasquez:** ¿correcto? Entonces,  
**Cubakilos:** oferta.  
**Jhony Alexander Alvarez Vasquez:** mira, yo tú puedes hacer aquí darle clic a uno, le das crear bandle. Y luego te vas a bandados y este te va a salir acá. Él te trae el texto completo hasta en inglés,  
   
 

### 00:22:30

   
**Cubakilos:** Vale,  
**Jhony Alexander Alvarez Vasquez:** ¿cierto? Porque así viene conect.  
**Cubakilos:** sí,  
**Jhony Alexander Alvarez Vasquez:** Tú le das acá editar y ya aquí le puedes modificar a tu gusto.  
**Cubakilos:** genial.  
**Jhony Alexander Alvarez Vasquez:** Si esto está activo se va a mostrar allá.  
**Cubakilos:** Vale,  
**Jhony Alexander Alvarez Vasquez:** Vamos a poner aquí prueba.  
**Cubakilos:** vale,  
**Jhony Alexander Alvarez Vasquez:** Prueba promo. Damos guardar.  
**Cubakilos:** vale,  
**Jhony Alexander Alvarez Vasquez:** Aquí nos eh,  
**Cubakilos:** okay.  
**Jhony Alexander Alvarez Vasquez:** ¿dónde está? A ver, prueba promo, eh, pero no se me actualizó acá. Bueno,  
**Cubakilos:** No,  
**Jhony Alexander Alvarez Vasquez:** ya lo ya lo veo.  
**Cubakilos:** no seó.  
**Jhony Alexander Alvarez Vasquez:** Y acá buscamos número. Bueno, pongamos cualquier número mientras tanto. Y por acá, a ver, a ver si coincide. Ver, este debería salir al menos 20 con9. También tengo Ah, es que está aquí está segmentado. A ver, Cubacel. Yo le puse va ser el  
**Cubakilos:** Sí, porque una cosa son los bond y otra cosa son los normales.  
   
 

### 00:23:42

   
**Jhony Alexander Alvarez Vasquez:** bandols.  
**Cubakilos:** Sí, voy a abrirlo y te lo muestro en cuanto quieras ahí para que para que lo entiendas un punto de vista de cómo lo ven ellos. A ver, esto sería Sí. Vamos.  
**Jhony Alexander Alvarez Vasquez:** A ver si de pronto escaché que no me está cargando.  
**Cubakilos:** Envía recarga. Envíen  
**Jhony Alexander Alvarez Vasquez:** 2090\. Este es recarga 500\.  
**Cubakilos:** ponlo más lento, ponlo más cortico y ya. Ponle test y ya. No sé, algo así. Y así lo es de uno.  
**Jhony Alexander Alvarez Vasquez:** A ver, este lunes estuve haciéndole bastante. Listo, ahí está. Todas maneras, la idea está apuntando. A ver, prueba, prueba. Ah, no, aquí está los paquetes. me está trayendo lo nuevo.  
**Cubakilos:** Ve, mira, SIM car. Esa es otra cosa. Esas son las SIM de turismo. Bueno, esa esa no es la de turismo. La de turismo está abajo ahí. Mírala ahí. Eso es algo que me interesa. Pero claro, eso lo puedo poner en otra landing que que es la que vamos a ver más adelante, que necesito que me hagas otra integración, pero bueno, lo vemos después.  
   
 

### 00:25:28

   
**Jhony Alexander Alvarez Vasquez:** Bueno, de momento estoy viendo que no me está sincronizando en tiempo real. Yo te lo arreglo, pero eh bueno,  
**Cubakilos:** Te paso la idea.  
**Jhony Alexander Alvarez Vasquez:** pero la idea la idea está muy clara con lo que nos has dicho.  
**Cubakilos:** Mira, te paso de nuevo aquí para que te hagas la idea de lo que yo quiero que cómo debe mostrarse también desde el punto de vista de experiencia de usuario  
**Jhony Alexander Alvarez Vasquez:** Ah.  
**Cubakilos:** del cuando tú haces la recarga aquí digamos Cuba, o sea, ¿cómo lo tiene DIN? Él tiene dos cosas y lo puedes verificar con esta gente. A ver, eh, dame un segundo. Ellos tienen. Vale, esto es, fíjate, intendo, 30 días. 30 días 3600 está. Vale, recomendado. Esto es por seis. Claro, porque ese es el otro la otra oferta. Mira,  
**Jhony Alexander Alvarez Vasquez:** Bueno,  
**Cubakilos:** cuando tú entras aquí, ve. Tú dices, él tiene los bondos. y tiene la recarga normal,  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** que es esta recarga con un monto. Vamos, mira, ve los Cuba tiene los bondos que ya vienen todos estos paquetes que ve que tienen una descripción, un combo, etcétera.  
   
 

### 00:26:41

   
**Cubakilos:** Por otro lado, tienes el moden data, que esto sería para la fibra en la casa. A ver, el moden data. Bueno, creo que sí, ese no sé cuál. Este casi no se oferta. Esto no. Este es la SIM de turista. Esto es para cuando vayas de turista a Cuba, tal. Nauta y lo otro que este no.  
**Jhony Alexander Alvarez Vasquez:** No.  
**Cubakilos:** Entonces, ¿qué es lo que pasa aquí en recarga? Si yo le digo, o sea, esto ya yo lo hago prácticamente como que lo configuro. Este es el básico que él te devuelve. Básico por fuera de los bundles. Por fuera de los bondes, él tiene este básico que es la recarga. purura normal que por ejemplo 22 € equivale a los  
**Jhony Alexander Alvarez Vasquez:** Todo ingresas en saldo. Sí.  
**Cubakilos:** 500 CUP. ¿Y qué sucede? Que este mes ellos Cubacel tiene todo el mes, eso nos los informa constantemente de DIN a nosotros dice, "Mira, el básico de 500 pesos de 22 € te va le va a enviar al cubano al familiar 500 CUP más internet limitado por 10 días." Ye.  
   
 

### 00:27:45

   
**Cubakilos:** Ah, vale. Y si pones 26 40 digo ahora. Vale, el tienen otra oferta que es 600 CUP por 2640 que es que van a recibir lo que dice acá. Si se es duplicado el internet, el saldo por 600 por 6 3600 CP saldo más  
**Jhony Alexander Alvarez Vasquez:** No.  
**Cubakilos:** internet nocturno ilimitado por 30 días. Entonces, fíjate que aquellos aquí no muestran ni siquiera y en Cubayama igual eh los bundles, ¿entiendes?  
**Jhony Alexander Alvarez Vasquez:** Okay.  
**Cubakilos:** Fíjate,  
**Jhony Alexander Alvarez Vasquez:** Sí,  
**Cubakilos:** no lo muestran.  
**Jhony Alexander Alvarez Vasquez:** sí.  
**Cubakilos:** eh limitado porque esto son 14 GB, 15 minutos, 120 SMS ya prácticamente, o sea, prácticamente lo que se, o sea, lo que quiero decir es que yo sea quiero ser que yo sea capaz de poder decir en la selección eh crear los productos que quiero mostrar en el selector si tú vas ahora, o sea, que yo pueda con en el WordPress que tú me que que tú crees que tú me das, yo pueda ser capaz de seleccionar en los productos que quiero que luego me me aparezcan en este  
**Jhony Alexander Alvarez Vasquez:** Sí, sí, así va a ser.  
   
 

### 00:29:10 {#00:29:10}

   
**Cubakilos:** selector,  
**Jhony Alexander Alvarez Vasquez:** Así es, así es. Inclusive en este momento, solo que se va a bueno, organizar un poco un poco  
**Cubakilos:** por ejemplo, si entras a la a la a la página, la recarga ahí,  
**Jhony Alexander Alvarez Vasquez:** mejor.  
**Cubakilos:** por ejemplo, v, mira, ese diseño estaría primero que sea el forma de listado y eh obviamente se muestra los que yo seleccione para así limpiar la vista.  
**Jhony Alexander Alvarez Vasquez:** Mhm.  
**Cubakilos:** Entonces, después yo te puedo crear abajo otro otra landing o otro show code donde yo en la página de recarga te diga otro producto similar, darte la idea y te diga, "Mira, aquí tienes la recarga nauta y él puede hacerle la compra de la recarga nauta ante el IDE." Pero sí,  
**Jhony Alexander Alvarez Vasquez:** Vale, perfecto.  
**Cubakilos:** el el principal y lo que está como orientado mucho, al menos en Cuba, eh,  
**Jhony Alexander Alvarez Vasquez:** Eh,  
**Cubakilos:** es eh los tres paqueticos. Eso son cuatro o tres oferticas que que va directo a recargar eso. De hecho, mucha gente lo que recarga es la básica, la Pero no podemos no puedo marearlo con 50 paquetes para seleccionar porque si no se me  
**Jhony Alexander Alvarez Vasquez:** sí, sí. No, ahí la ahí la clave es clasificarlo bien,  
   
 

### 00:30:17

   
**Cubakilos:** pierde.  
**Jhony Alexander Alvarez Vasquez:** así como tú ya tienes la idea. Entonces, bueno,  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** el objetivo inclusive de para la parte de editable es que tú elijas el producto. La base principal va a ser ese SQ, pero eso va a estar para nosotros, para el cliente va a estar el texto que tú quieras.  
**Cubakilos:** Claro.  
**Jhony Alexander Alvarez Vasquez:** Esa esa va a ser la parte importante. Tú vas a venir aquí, vas a poder editar.  
**Cubakilos:** Genial.  
**Jhony Alexander Alvarez Vasquez:** Yo vuelvo y cambio algo acá. A ver, ayer me funcionó, pero de pronto estamos haciendo algo mal. Y lo que recibe el usuario.  
**Cubakilos:** Te paso otra para que loacom creo que  
**Jhony Alexander Alvarez Vasquez:** Prueba,  
**Cubakilos:** es.  
**Jhony Alexander Alvarez Vasquez:** prueba.  
**Cubakilos:** Míralo aquí. Mira, te paso otra que tiene más paquetes incluso,  
**Jhony Alexander Alvarez Vasquez:** Ah.  
**Cubakilos:** pero para que tengas la idea y con esto lo vas a hacer, eh, lo tienes perfecto. Mira, Acuba, Acuba.com. Esa es otra que también lo hace. Eso me gusta. Mira, ese ese eso que hiciste me gusta en el sentido de que primero seleccionas, o sea, obviamente en Cuba te va a salir solo el código de Cuba y nada más es poner el teléfono de Cuba,  
   
 

### 00:31:25

   
**Jhony Alexander Alvarez Vasquez:** Mhm.  
**Cubakilos:** pero por ejemplo que primero pongas el teléfono y después te aparezca el listado de oferta para seleccionar. Eso me esa ese efecto me gusta porque, o sea, como que enfoca primero la atención por paso, ¿no? Crea tu teléfono, pon tu teléfono y después mira lo que te aparece.  
**Jhony Alexander Alvarez Vasquez:** Okay, pero mira que sí como que se me desconectó en el último cambio el editable de acá con lo que se ve el cliente, pero bueno, eso no tarda mucho en arreglarlo. Eh, muy bien. Ah, mira, ya actualicé porque anoche había dejado algunos cambios que es aquí para para elegir de dónde queremos el bundle, los productos y bueno, y ya aquí se va eligiendo  
**Cubakilos:** Hay una pregunta que me queda duda. ¿Para qué sería, por ejemplo,  
**Jhony Alexander Alvarez Vasquez:** el  
**Cubakilos:** me dices ahí la diferencia? Eh, no estaría bien solo, por ejemplo, que que yo obtenga o le do un botón refrescar y obtenga desde la API directo todos los productos de de Edin.  
**Jhony Alexander Alvarez Vasquez:** estoy con un tema de precaución nada más por lo que te dije al principio que a el en la documentación decía que no podemos tener cierta cantidad de solicitudes a al por minuto o por cada tanto.  
**Cubakilos:** M.  
   
 

### 00:32:42

   
**Jhony Alexander Alvarez Vasquez:** Entonces, eh se puede recargar todo, pero bueno, lo mejor es elegir el país para que no venga todo de inmediato.  
**Cubakilos:** Ah, vale,  
**Jhony Alexander Alvarez Vasquez:** Elige el país y ya puedes elegir acá crear bundle o guardarlo,  
**Cubakilos:** vale.  
**Jhony Alexander Alvarez Vasquez:** guardarlo según como se quiera. Vamos a probar esto. que ayer no funcionaba  
**Cubakilos:** Vale, ahí me ahí me ahí me queda duda.  
**Jhony Alexander Alvarez Vasquez:** bien.  
**Cubakilos:** A ver, yo cojo ahí pedí Cuba. Está genial. Vale,  
**Jhony Alexander Alvarez Vasquez:** Sí,  
**Cubakilos:** me salen todos los productos.  
**Jhony Alexander Alvarez Vasquez:** todos los paquetes que hay en Cuba y este sí está sincronizado en tiempo real.  
**Cubakilos:** Vale. Y ahí yo no. ¿Qué quiere decir crear bundle?  
**Jhony Alexander Alvarez Vasquez:** El bondel es lo que vas a ver acá en estas partes, eh, primero el operador y luego estos estos paquetes que son los bonds o los productos o los paquetes.  
**Cubakilos:** Vale, ahí entonces tendría sentido que el Bunder sea que yo pueda como que seleccionar varios productos y unirlos en un eh, o sea, hm, ¿cómo decir?  
**Jhony Alexander Alvarez Vasquez:** como elegir qué este con este y venderlo a  
**Cubakilos:** Sí, eliges tres y como Exacto.  
   
 

### 00:33:44 {#00:33:44}

   
**Jhony Alexander Alvarez Vasquez:** 49\.  
**Cubakilos:** Eliges tres y eso te crea un grupo que sea el que después muestres en el show code. No sería así mejor.  
**Jhony Alexander Alvarez Vasquez:** Pero te refieres a aquí a esta lista de  
**Cubakilos:** Y por ejemplo, mira, abre la otra que te mandé eh,  
**Jhony Alexander Alvarez Vasquez:** acá.  
**Cubakilos:** a Cuba que tiene más, ¿ve? Por ejemplo, v ellos pusieron más.  
**Jhony Alexander Alvarez Vasquez:** Okay.  
**Cubakilos:** Entonces yo lo que me yo lo que me refiero es que yo te diga,  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** eh, vale, voy a coger los productos, voy a crear un desplegable de Cuba de recarga, no de gift cards. Y entonces voy a mi WordPress y digo,  
**Jhony Alexander Alvarez Vasquez:** Ah.  
**Cubakilos:** "Dame todos los productos de Cuba actualizados por la API." Entonces voy a seleccionar este primero, el segundo, el tercero y el cuarto tal tal, que son de topop de recarga, que son los que quiero mostrar en mi desplegable para Cuba.  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** 1 2 3 cu le doy crear, vamos decir bundle, por decir. Ya tengo esos cuatro paqueticos, cuatro productos y entonces son los que salen aquí en el en el desplegarlo.  
   
 

### 00:34:50 {#00:34:50}

   
**Jhony Alexander Alvarez Vasquez:** Okay, entonces así está, así está.  
**Cubakilos:** Ah,  
**Jhony Alexander Alvarez Vasquez:** Lo que falta es categorizarlo un poco mejor.  
**Cubakilos:** vale,  
**Jhony Alexander Alvarez Vasquez:** ¿A qué a qué me refiero?  
**Cubakilos:** vale,  
**Jhony Alexander Alvarez Vasquez:** Tú no puedes elegir aquí todos de una porque cada uno de los paquetes, que es cada uno de esto que hay acá,  
**Cubakilos:** sí.  
**Jhony Alexander Alvarez Vasquez:** tú le vas a poner un nombre y un texto llamativo.  
**Cubakilos:** Exact.  
**Jhony Alexander Alvarez Vasquez:** Entonces tú eliges eh este de 10 GB inmediatamente,  
**Cubakilos:** Sí,  
**Jhony Alexander Alvarez Vasquez:** mira que se puso aquí de inmediato con el código,  
**Cubakilos:** sí,  
**Jhony Alexander Alvarez Vasquez:** el valor y demás. Aquí recibe 10 GB.  
**Cubakilos:** sí. Genial.  
**Jhony Alexander Alvarez Vasquez:** Aquí paquete paquete este tú le das guardar y no sé si notaste que aquí arriba decía decía Cuba y en bondes guardados ya va a aparecer acá el  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** paquete con con el texto eh personalizado. Bueno, este este texto también se puede personalizar todo todo se puede personalizar.  
**Cubakilos:** Sí. Bueno, el SK1 se debería,  
**Jhony Alexander Alvarez Vasquez:** Entonces el ah el SK  
**Cubakilos:** ¿no? No debería.  
   
 

### 00:35:49

   
**Jhony Alexander Alvarez Vasquez:** no no no este este lo tengo lo tengo ahí es para yo  
**Cubakilos:** De hecho, si si quieres Ah,  
**Jhony Alexander Alvarez Vasquez:** en pruebas confirmarte que sí se me envíe lo correcto porque este es el que toma con y dice okay me están pidiendo esta  
**Cubakilos:** vale, vale, vale. Claro, claro, claro, claro.  
**Jhony Alexander Alvarez Vasquez:** recarga bueno y en ese momento en que actualizamos que  
**Cubakilos:** Sí, total.  
**Jhony Alexander Alvarez Vasquez:** guardamos ya acá deberíamos elegir Cuba, pero bueno, ya esto va a estar segmentado por landing, por shortcode diferente y aquí vamos a encontrar ese paquete. Entonces, sino que ese es lo que me está fallando ahorita, pero pero bueno, o creo que es este. A ver, ¿no? Y ese es el que va a aparecer acá con el texto y todo lo que tú has puesto. A ver, es este. Cuando tengamos uno no más o dos y vamos agregando y vamos  
**Cubakilos:** Sí. De hecho,  
**Jhony Alexander Alvarez Vasquez:** viendo.  
**Cubakilos:** ahí lo que tú puedes hacer es decir, seleccionar un operador donde pusiste ahí. Por ejemplo, digamos que yo voy a poner, yo voy a mostrar, por ejemplo, en Cuba, yo voy a querer mostrar, okay, Cubacel y voy a querer mostrar en Nauta.  
   
 

### 00:36:51

   
**Cubakilos:** Y por ejemplo, ahí, por ejemplo, tú tienes que verificar y decir, "Ah, mira, si seleccionó más de un operador, o sea, le muestro el operador, por ejemplo, BQCEL y abajo te salen los paquetes disponibles que haya yo seleccionado." y  
**Jhony Alexander Alvarez Vasquez:** Mhm.  
**Cubakilos:** digamos que seleccionés un paquete de nauta, entonces se te va a te va a aparecer arriba, solo te va a aparecer, por ejemplo, dos tarjeticas primero, una tarjetica que diga Cubacel y una tarjetica que diga Nauta. Y cuando haga click en Nauta sale solo el paquete disponible. Es es la otra forma de categorizarlo así ahí,  
**Jhony Alexander Alvarez Vasquez:** Exacto. Que en vez de estos botones se un selección,  
**Cubakilos:** ¿no?  
**Jhony Alexander Alvarez Vasquez:** una con opciones.  
**Cubakilos:** Sí,  
**Jhony Alexander Alvarez Vasquez:** Sí, sí,  
**Cubakilos:** exacto.  
**Jhony Alexander Alvarez Vasquez:** creo que creo que ya uo muy muy clara y y ahora mismo. Ah, creo que es este.  
**Cubakilos:** Ese sí,  
**Jhony Alexander Alvarez Vasquez:** Creo que este el de 22 que creamos hace poco. 22 10 k.  
**Cubakilos:** pero ese es turismo.  
**Jhony Alexander Alvarez Vasquez:** Vale.  
**Cubakilos:** Eso es una sin de  
   
 

### 00:37:39 {#00:37:39}

   
**Jhony Alexander Alvarez Vasquez:** Y y aquí le doy confirmar y bueno,  
**Cubakilos:** turisto.  
**Jhony Alexander Alvarez Vasquez:** ya se iría directamente a tu cuenta. Entonces, muy bien.  
**Cubakilos:** Sí, pero bueno,  
**Jhony Alexander Alvarez Vasquez:** Creo,  
**Cubakilos:** pasaría a un proceso de compra,  
**Jhony Alexander Alvarez Vasquez:** claro, que es el que tengo aquí también que tenemos que definir porque yo le habilito WCommerce  
**Cubakilos:** ¿no?  
**Jhony Alexander Alvarez Vasquez:** y lo que va a hacer es que antes de irse a ese botón último de pago, él me va a decir, "No, un momento, tienes que registrarte o ingresar tu número y luego ahí va a estar el pago a tu cuenta. Bueno, el la pasarela que tú tengas de WCommerce.  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** A ver, añadir al carrito. Mira, si es yo le doy añadir al carrito y ya se me va con el misma información.  
**Cubakilos:** Claro.  
**Jhony Alexander Alvarez Vasquez:** Este, estos paquetes se me va con la misma información y yo ya le digo realizar pedido y cuando yo haga el pago según el método que tú tengas ahí ya solicita inconnect el pago y bueno y aquí ya es lo que tú quieras pedirle al cliente el  
**Cubakilos:** Claro.  
**Jhony Alexander Alvarez Vasquez:** teléfono no más o bueno ya  
**Cubakilos:** Sí. Ahí hay que pedirle los datos de él, ¿eh?  
   
 

### 00:38:49 {#00:38:49}

   
**Cubakilos:** Y del del familiar en Cuba es el teléfono nada más. O sea,  
**Jhony Alexander Alvarez Vasquez:** M.  
**Cubakilos:** nada más el teléfono que estaría genial que lo coja, lo arrastre desde el que puso al  
**Jhony Alexander Alvarez Vasquez:** Ah, sí, sí, sí. Ve, no lo traje. Sí, no lo traje acá,  
**Cubakilos:** inicio.  
**Jhony Alexander Alvarez Vasquez:** pero sí, sí lo lo tengo que traer. Eso sí lo tenía yo en cuenta también. Okay, creo que creo que esto ya se me quedó mucho más claro. Voy a sentar bien las ideas, menos mal grabe todo también para que la me saque las tareas y bueno,  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** ya avanzamos con con los demás temas. Eh, con respecto, para no salirnos del tema, con respecto a Inconnect, eh, mañana podrías eh a eso de las 3, espérame, yo miro cómo estoy. Echale otro ojo a eso para ver lo que hablamos hoy.  
**Cubakilos:** Sí, sí, sí. Eh,  
**Jhony Alexander Alvarez Vasquez:** Si esto,  
**Cubakilos:** sí, sí, sí, si para mí esto de DIN es prioridad.  
**Jhony Alexander Alvarez Vasquez:** ¿vale? Ah, bueno, a las 3 ya hay, pero es que a las 3 otra vez.  
   
 

### 00:39:53 {#00:39:53}

   
**Jhony Alexander Alvarez Vasquez:** H, bueno, yo te mando eh la invitación. Perfecto. Eh, queríamos entonces revisar el tema del CRM en estos minuticos que faltan y darte avances con lo de CTT y GLS, con la migración que que Sebas está haciendo. Entonces, bueno, vámonos a directamente a por eso los llamé a todos para que también Jimmy que está muy al tanto del CRM va a tomar nota de cualquier cosa.  
**Cubakilos:** Sí, eso sí yo lo revisé completico.  
**Jhony Alexander Alvarez Vasquez:** Ah, sí, nosotros volvimos a revisarlo. Eh, creamos como unas serie de etapas para confirmar contigo también. Eh, nos pusiste por aquí la documentación de ah los artículos restringidos. Ya saqué también documentación de eso, lo agregué ahí al archivo hace poco. Y bueno, en resumidas para nosotros ya poder hacer las pruebas, ya que Sebas pues estuvo diseñando el flujo, pero me preguntó varias varios temas que no están muy bien eh eh aclarados, es en cuanto a las etapas con que con que vamos a trabajar.  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** Mira, eh me gustaría tener claro ahorita es como ese foco, ese ese proceso eh estructural, no tanto irnos al detalle que sí, qué responde y qué no responde, sino en la estructura como tal para saber cuándo disparar una cosa o la otra,  
   
 

### 00:41:20 {#00:41:20}

   
**Cubakilos:** Sí,  
**Jhony Alexander Alvarez Vasquez:** porque esto tiene que estar muy en sintonía con lo que tiene Sebastián.  
**Cubakilos:** sí.  
**Jhony Alexander Alvarez Vasquez:** Entonces, eh se inicia, bueno, se inicia una conversación. Esa conversación va a estar para definir los campos que van a ir en el lead, que ya sabemos que hay unos campos que necesitamos rellenar para que pueda que pueda funcionar el el CT y GLS y demás. Y con esos campos eh al mismo tiempo, en paralelo, estamos resolviendo preguntas de de los clientes,  
**Cubakilos:** Mhm.  
**Jhony Alexander Alvarez Vasquez:** porque no solo van a preguntar eh o van a decir directamente el paquete, sino que también van a preguntar, mira, y y ¿qué pasa con esto? ¿Cuánto se demora? Y todo esto, ese tipo de de consultas. Entonces, el proceso está así. Eh, se inicia la conversación con el cliente, eh conversación natural y luego se inician ciertas eh a resolver ciertas preguntas. Ese proceso lo que va a esperar es que todo llegue al formulario.  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** Vamos a intentar hacer que la guía tenga una intención, o sea, que detecte esa intención y cuando el cliente ya diga que, bueno, me parece bien, vamos a cono lleno el formulario o lo que necesito para enviar el paquete, entonces ahí es cuando la IA va a disparar otro bot donde se va a enviar ese formulario y sus seguimientos respectivos.  
   
 

### 00:42:47 {#00:42:47}

   
**Jhony Alexander Alvarez Vasquez:** esas dos etapas, ¿eh? ¿Tienes alguna observación, algo eh puntual que quisieras enfocarnos bien o quisiera reordenar eso? Porque eh la va a estar siempre insistiendo de como ven, pero a qué ciudad va a ir si él no responde nada o si o si desvió el tema. Eh, la idea siempre va a estar como encaminando al cliente para que que llene el bueno,  
**Cubakilos:** Sí,  
**Jhony Alexander Alvarez Vasquez:** llene los datos bases para poder proseguir con el formulario y demás. Entonces,  
**Cubakilos:** sí,  
**Jhony Alexander Alvarez Vasquez:** esa esa es mi  
**Cubakilos:** vale. ¿Cómo yo te lo resumiría?  
**Jhony Alexander Alvarez Vasquez:** duda.  
**Cubakilos:** Eh, como siempre, claro, el el cliente que está perfecto, llega sin saber nada, mi objetivo es siempre para atenderlo lo más rápido posible y y agilizar  
**Jhony Alexander Alvarez Vasquez:** Mhm.  
**Cubakilos:** el tiempo y el proceso es estructurarle la conversación y guiarlo en el flujo, que lo más primer paso, yo necesito saber desde dónde tú vas a enviar el paquete. No es el mismo precio de Península, ni de Canarias, ni ni de Baliares, ni de Portugal. Eh, por ejemplo, a esos yo lo detecto por el número de teléfono porque normalmente el número de teléfono es 351, creo, ¿no?  
   
 

### 00:44:01

   
**Cubakilos:** Pero bueno,  
**Jhony Alexander Alvarez Vasquez:** Vale.  
**Cubakilos:** vale. Pero bueno, el origen, si ya él me dijo en la, "Oh, la quiero enviar desde Madrid a Cuba, típico, ya lo que me falta es el destino, porque eh ya me dijo Madrid, ya yo sé que eso es península, ahora me falta el destino que a qué ciudad de Cuba, que lo lo principal es lo primero es definir La Habana o el resto de provincia." Y bueno, ¿a dónde? En Cuba. Después yo tengo eh él me dice, "Sí, pero ¿cuánto cuesta el kilo? Es a a Santiago Cuba. ¿Cuánto me cuesta el kilo?", "Bueno, mira, yo tengo tres tipos de envío. Tengo marítimo, aéreo estándar y aéreo pr." Entonces, eh, dime más menos qué tienes pensado enviar, eh, peso y peso, dimensiones o si es una batería o si es ropa y comida para yo guiarte en el mejor tipo de envío, eh, el que te más te te, o sea, se aplique, porque si es una batería,  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** eh, tiene que ir por marítimo y entonces Exacto. Si lleva spray perfume, no puede ir por estándar, tiene que ir por aéreo spray, por ejemplo.  
**Jhony Alexander Alvarez Vasquez:** Ahí paremos un segundito.  
   
 

### 00:45:08 {#00:45:08}

   
**Jhony Alexander Alvarez Vasquez:** Esas reglas,  
**Cubakilos:** Entonces,  
**Jhony Alexander Alvarez Vasquez:** esas reglas las tenemos definidas, ¿eh? A ver.  
**Juan Sebastián Galeano:** Sí, sí, los tenemos definido.  
**Jhony Alexander Alvarez Vasquez:** Sí, perfecto, perfecto.  
**Cubakilos:** ¿vale? Eh, entonces eh que por ejemplo es también puede suceder el caso que em diga, "Mira, yo quiero enviar eh eh o sea 10 kg y en ambos casos él puede enviarlo por los tres vías, tanto por aéreo, por ESPRO como marítimo." Y él me dice, "Dame el precio de los tres para ver cuál me conviene." Pero bueno, también yo le digo, y me dice, "Dame también y y el tiempo que demora es precio y y tiempo de demora, lo que le interesa al cliente." Y yo le digo, "Mira, mira, con marítimo te sale 55 € 10 kg, por aéreo estándar te sale en tanto y por spray en tanto. El marítimo dura 2 meses, el spray te llega en 10 días y el estándar te llega en 15, no sé. Pero bueno, la idea inici la idea es siempre eh como que simplificar la información y lo más ajustada posible a lo que quiere el cliente. De hecho, lo que nosotros estamos haciendo mucho es yo le mando, te digo, le mando los tres tipos de envío donde yo hice varios resúmenes a cada uno.  
   
 

### 00:46:19 {#00:46:19}

   
**Cubakilos:** Por ejemplo, mira, este este tarda tanto y hay gente que dice, "No, no, yo no puedo esperar por un marítimo dos meses. A ver, dime el aéreo estándar o el spre." Y mucha gente me dice, "Mira, cuando tenga definido el paquete que quiero enviarte, contacto de nuevo." Entonces, porque yo no yo trato no enviarle la plantilla de de precio porque completa por ir para por express porque es mucha información por gusto. Entonces, esa es la idea,  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** ¿no? Ya luego que el cliente eh te dice, "Mira, sí, yo quiero enviar un patinete eléctrico." Eso sería una sería sería superfácil, ¿no? Ahí lo que tiene que hacer la guía es decirle, "Vale, perfecto. Para yo cotizarte el patinete eléctrico o la batería o la lavadora, necesito peso y dimensiones eh del producto, ¿no? O de la caja que lo contiene. Si no lo has comprado, si aún no lo has comprado, eh lo puedes mirar en la descripción del producto en la página web. O si lo compraste, pues mídelo y o mira la caja por detrás que que debe tener esos datos. Eso es, por ejemplo, una batería. Mira, yo quiero enviar 10 kg por estándar.  
   
 

### 00:47:15

   
**Jhony Alexander Alvarez Vasquez:** Ok.  
**Cubakilos:** Eh, vale, mira, este es el precio por estándar, 10 kg, entonces, eh, vale, perfecto. Mira, ya. Bueno, ¿cuándo cuándo me pueden recoger el paquete? Típico, bueno, ¿cuándo me lo recogen? O ¿qué día sale? Mira, en el aéreo estándar, eh, si te recojo esta semana, sale el domingo que viene, porque tengo salida todos los domingos. Ah, vale, vale. Bueno, ¿cuándo me lo recoges? Dígame, ¿qué día quieres que te te ponga la recogida? Eh, el viernes está bien, ¿no? El sábado no. Sábado, sábado y domingo no se puede, debe ser el viernes. Vale, el viernes. Eh, bueno, te mando el formulario para que para que pongas los datos de recogida y de entrega. Ah, listo. Mira, vale. Se le manda el formulario y ahí viene la otra parte y él dice, "No, quiero recoger el viernes." Viernes. Ah, vale. El viernes. Ya. Entonces, rellen el formulario y ahí ya yo paso a CTT, lo que hemos hablado, donde cuando lo rellena se tienen los datos de recogida y entrega, eh, sobre todo la recogida y eh se tiene el día de la recogida con el rango horario de bueno, ¿en qué horario?  
   
 

### 00:48:30 {#00:48:30}

   
**Cubakilos:** No, mira, yo puedo todo el día, ¿no? Yo puedo. Tienes estos tres rangos horarios, 9, 15 o todo el día. No, yo puedo solo en la mañana, en la tarde trabajo. No, yo puedo en la tarde porque en la mañana trabajo, ¿vale? Eh, básicamente y entonces ya ahí se tienen todos los parámetros para lanzar el formulario. Y una vez que lo rellena, pues se tiene la fecha, se tiene que tener la fecha, el día recogida, el rango horario y y bueno, los datos que se rellenan en el formulario.  
**Jhony Alexander Alvarez Vasquez:** Perfecto. Bien. Eh,  
**Cubakilos:** Yo de momento te digo con que la IA me apoye hasta que le lance el formulario y tenga teniendo todo eso esa información y ya le lancea  
**Jhony Alexander Alvarez Vasquez:** Bueno,  
**Cubakilos:** capaz de lanzarle el formulario. Efectivamente, con los datos que son, eso sería un gran paso porque ya luego después yo nada más tengo que entrar a los paneles de administración y voy viendo las los pendientes de recogida que se me van poniendo con esta ahí y ahí yo mismo decido, vale, este, ¿qué día quería, vale? Ejecutar recogida, ejecutar recogida y lo hace la API.  
   
 

### 00:49:46

   
**Jhony Alexander Alvarez Vasquez:** Correcto.  
**Cubakilos:** Ya después la automatización con oye, ya hicimos tu recogida y después está conectada en tiempo real con CTT y tal.  
**Jhony Alexander Alvarez Vasquez:** Eso.  
**Cubakilos:** Esa parte hasta ahí a mí es perfecta porque ya después lo otro es que cuando se le recoge, que se verifica los pesos y tal, que eso ya es más manual, es otra automatización que no debe no es con que es que le mande la factura, ¿no? O sea,  
**Jhony Alexander Alvarez Vasquez:** Sí,  
**Cubakilos:** solicit pago y  
**Jhony Alexander Alvarez Vasquez:** correcto. Listo. Entonces, mira,  
**Cubakilos:** tal.  
**Jhony Alexander Alvarez Vasquez:** eh justo algo así te iba a mencionar. Vamos a empezar para que mañana podamos ya activarlo. es que iniciemos con esta primera etapa de asesoría en donde se pueda desmenuzar todo  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** lo que el cliente quiere, cómo lo quiere y todas las dudas se pueda resolver el cliente y ahí es a partir de ese punto tú atendiendo todos los clientes de manera automatizada puedes ya eh eh atenderlos y y ahí en ese punto también tú nos vas a decir, "Okay, la ya a partir de este momento eh eh con lo que veo que está asesorando nos puede ya eh, por ejemplo, ingresar los datos que está recogiendo, ponerlos en en el lead o cambiarlo de etapa para que se envíe el formulario. Listo.  
   
 

### 00:50:58 {#00:50:58}

   
**Jhony Alexander Alvarez Vasquez:** El cambio de etapa se basa de que él lo puede hacer,  
**Cubakilos:** Hm.  
**Jhony Alexander Alvarez Vasquez:** es muy sencillo y con un punto clave que diga el cliente, se cambia de etapa y el formulario se dispara solito porque eso ya está automatizado. Entonces, pero vamos a irnos un paso atrás antes de ese es de que la asesoría se esté dando como es.  
**Cubakilos:** Claro.  
**Jhony Alexander Alvarez Vasquez:** Entonces, con esto que este ejemplo que nos acabas de decir, yo lo voy a transcribir bien, voy a confirmar de que todo esté así y le paso todas edas para que lo añada y el día de mañana podríamos o tú pasarte un poco como cliente y ya y ya empezar a interactuar con él y nos dices cuándo podemos activarse a los clientes finales.  
**Cubakilos:** Sí, ahí eh yo sé que todavía, por ejemplo, yo revisé esto eh miré creo que no sé si llegué a terminar el bron maestro Eh, pero bueno, ya los otros los detalles otros eh sería y hoy, o sea, ir a agregando la información en los Excel esos que me pusiste,  
**Jhony Alexander Alvarez Vasquez:** Sí, sí.  
**Cubakilos:** porque a mí lo que se me estaba ocurriendo era eh yo dije era preparar un doc, un doble doc donde yo pusiera todas las plantillas que actualmente nosotros usamos, que tienen toda esa información bastante eh estructurada para pregunta y respuesta frecuente.  
   
 

### 00:52:16 {#00:52:16}

   
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** Y entonces como que ustedes le dijeran,  
**Jhony Alexander Alvarez Vasquez:** Mm.  
**Cubakilos:** tú le dijeras a la le dices a la eh, "Mira, hazme un resumen de la manera que a ti te interese de esta información para que se adapte a lo a las reglas, ¿no?  
**Jhony Alexander Alvarez Vasquez:** Correcto. Sí,  
**Cubakilos:** Yo estaba pensando eso, eh, si te digo,  
**Jhony Alexander Alvarez Vasquez:** sí.  
**Cubakilos:** dame un segundo que era para que tienda la idea. Yo voy a venir aquí a a cómo yo sé que hay varias plantillas que no están bien y tal, entonces no eso no lo pueden ponerse a buscarlo ustedes. Eh, eh, o sea, yo coger de acá, eh, qué lento está esto, ¿no? De acá yo voy a copiar, por ejemplo,  
**Jhony Alexander Alvarez Vasquez:** Ahí  
**Cubakilos:** las que son, por ejemplo, origen, eh,  
**Jhony Alexander Alvarez Vasquez:** cargó.  
**Cubakilos:** o sea, de todas estas voy a ir copiando el texto de cada una y la voy a poner todas una debajo de la otra en un Google Doc y de ahí tú puedas decir con tu con la herramienta tuya decirle, "Mira, resúmame esto en que me sirva para que tú puedas predefinir las reglas, ¿no?  
   
 

### 00:53:36

   
**Jhony Alexander Alvarez Vasquez:** Correcto. Sí,  
**Cubakilos:** Eso, eso,  
**Jhony Alexander Alvarez Vasquez:** eso lo hacemos.  
**Cubakilos:** eso se me ocurrió y dije, "Déjame hacerlo así a ver si eh es  
**Jhony Alexander Alvarez Vasquez:** Si esas plantillas son la base con la que tú sueles trabajar bastante,  
**Cubakilos:** más  
**Jhony Alexander Alvarez Vasquez:** te van a te van a ayudar a ayudar muchísimo.  
**Cubakilos:** Sí, sí." No te digo eh eh o sea,  
**Jhony Alexander Alvarez Vasquez:** Eso está actualizado y lo está  
**Cubakilos:** por o sea, eh eh Sí, sí, sí, total eh e o sea,  
**Jhony Alexander Alvarez Vasquez:** pasando.  
**Cubakilos:** ¿cómo decirte? comentar, cómo enviarlo, por ejemplo, cómo lo envuelvo si lo estás viendo, te digo, aéreo estándar, si es por aéreo, prepara, yo tengo preparación del envío aéreo, embalaje, sellarlo, la información que debe ir y entiendes y enviar, eh, sí,  
**Jhony Alexander Alvarez Vasquez:** Perfecto.  
**Cubakilos:** sí.  
**Jhony Alexander Alvarez Vasquez:** Bien, entonces empecemos con esta asesoría base. Tú nos vas pasando la información que quieras ir agregando.  
**Cubakilos:** de  
**Jhony Alexander Alvarez Vasquez:** Vas a ir notando inclusive de a poco que le hables a la día, vas a ir notando que é ya va teniendo más más información y así es como se va a comportar con los clientes.  
   
 

### 00:54:27 {#00:54:27}

   
**Cubakilos:** la  
**Jhony Alexander Alvarez Vasquez:** Entonces, y creo que est esta idea le suena muy bien a CBA, le he estado hablando sobre eso esta semana, de que iniciemos con la asesoría porque estamos ya pensando en los temas de de cuándo se activa el formulario, cuándo no y demás, sin arrancarlo. a a conversar.  
**Cubakilos:** Claro. Sí, sí,  
**Jhony Alexander Alvarez Vasquez:** Entonces,  
**Cubakilos:** sí.  
**Jhony Alexander Alvarez Vasquez:** entonces eh hoy te activamos eso, bueno, tardecito por la hora en Colombia, pero para que mañana ya puedas comprobarlo y y bueno, a esta hora un poco más temprano lo vemos entonces.  
**Cubakilos:** Eh, sí, yo lo que sí te digo de nuevo, yo eh eh, o sea, necesito también, como te digo, eh optimiz eh tener alguna yo no he podido, o sea, aprovechar como tal la, o sea, el tema de las recogidas a mí me sigue tomando porque yo no te digo, no le he podido aprovechar hacer ninguna recogida automatizada porque  
**Jhony Alexander Alvarez Vasquez:** Vale, terminemos con ese con ese tema. Sebas,  
**Cubakilos:** siempre  
**Jhony Alexander Alvarez Vasquez:** eh, tenía algo ya hoy casi por terminar inclusive, eh, porque yo también se lo he hecho ver y bueno,  
**Cubakilos:** sí porque bueno,  
**Jhony Alexander Alvarez Vasquez:** entre los dos hemos  
   
 

### 00:55:40

   
**Cubakilos:** sabes, o sea, sabes que por ejemplo hace como ya no sé si ya fueron casi tres semanas que fue que iba a estar por ejemplo el ID lo de GLS porque como yo le decía a a Juan, él decía, yo para mí no tiene mucho sentido ponerme a trabajar por el panel. el eh con tres clientes de CT para que, por ejemplo, los que son de GLS no los pueda, o sea, no los puedo todavía automatizar. Entonces, porque si no voy a estar trabajando entre dos herramientas que al final no me es eh eh no me da el provecho, ¿entiendes? Y además siempre casi siempre ha pasado cada vez que he querido como decir, "Bueno, voy a hacerlo, pero hoy mismo fui a enviar el formulario y no se enviaba el el formulario eh de nuevo que que está generado, no salía el link, entonces ya mandé el viejo." Entonces lo que trato de decir es que yo a estas alturas todavía yo no he podido beneficiarme de de optimizaciones para agilizar el trabajo. Entonces,  
**Jhony Alexander Alvarez Vasquez:** Sí.  
**Cubakilos:** ya, okay. Yo me nos sentaremos ahí para ir probando la eh, obviamente pues en esa etapa, pero si si yo no vivo todavía descomprimir el trabajo de un lado, además me me va me va a pasar una cosa ahora.  
   
 

### 00:56:50 {#00:56:50}

   
**Jhony Alexander Alvarez Vasquez:** No, no te va a dar tiempo.  
**Cubakilos:** Acabamos de acabo de cerrar un contrato con FedEx y ya voy a empezar a recoger en toda Europa.  
**Jhony Alexander Alvarez Vasquez:** No hay  
**Cubakilos:** Entonces, imagínate tú, ya tengo gente de Austria, de Alemania, de Francia,  
**Jhony Alexander Alvarez Vasquez:** gente.  
**Cubakilos:** porque además me dieron unos precios super extremadamente competitivos y y pues nada, entonces te digo, la carga de trabajo siendo recogida y tal, pero bueno, la atención está bien, pero te digo, necesito ir beneficiándome de de optimización del flujo de trabajo, ¿entiendes?  
**Jhony Alexander Alvarez Vasquez:** Vale. E Sebas como lo tenemos para hoy. Bueno, yo a él le he dado todo el enfoque, he intentado que que no tuviera ninguna tarea adicional y bueno, también me ha explicado varios temas que se ha encontrado. Yo le digo, "Bien, pero ¿por qué no lo activamos ya?" Y entonces, claro, revisamos el código, revisamos pendientes, entonces, pero bueno, sabemos la carga que tienes. Eh, en cuanto a la pues solo es quiero que le hables un momento,  
**Cubakilos:** Sí,  
**Jhony Alexander Alvarez Vasquez:** no intentaré no tomarte mucho tiempo.  
**Cubakilos:** sí, sí.  
**Jhony Alexander Alvarez Vasquez:** Y bueno, hoy me estaba diciendo Sebas que ya estaba eh por fin terminando con algo y una migración que  
   
 

### 00:57:48 {#00:57:48}

   
**Cubakilos:** Vale.  
**Jhony Alexander Alvarez Vasquez:** también te tocó hacer en la última semana. Entonces, a ver, Sebas, ya nos comentas ahí para que hoy nos enfoquemos  
**Juan Sebastián Galeano:** La migración ya se terminó en días anteriores.  
**Jhony Alexander Alvarez Vasquez:** bien.  
**Juan Sebastián Galeano:** Lo que lo yo le comenté a René ahora es que ya estoy eh ajustando el flujo al a la producción porque hace varios días ya nos dieron el código de de GLS para producción porque antes manejaba uno  
**Cubakilos:** Sí.  
**Juan Sebastián Galeano:** exclusivo para pruebas.  
**Jhony Alexander Alvarez Vasquez:** Ok.  
**Juan Sebastián Galeano:** Entonces estoy organizando ahí un par de peticiones para que ya me las acepte con base en este nuevo ID de producción para empezar a a solicitar recogida. Yo le dije a René que eso iba a quedar listo para ahorita y después de eso hacer los ajustes que pues él también pidió ahí para el panel.  
**Jhony Alexander Alvarez Vasquez:** Eh, el formulario que no se le pudo activar a CTT ahora fue por lo que estás  
**Juan Sebastián Galeano:** Ah, sí, perdón, eso ya está resuelto.  
**Jhony Alexander Alvarez Vasquez:** moviéndolo.  
**Juan Sebastián Galeano:** Eso ya también se lo había informado ahorita.  
**Jhony Alexander Alvarez Vasquez:** Era lo que estaba moviendo.  
**Juan Sebastián Galeano:** M.  
**Jhony Alexander Alvarez Vasquez:** Vale, vale, perfecto.  
**Cubakilos:** Eh, Jimmy, una cosa.  
   
 

### 00:58:45 {#00:58:45}

   
**Jhony Alexander Alvarez Vasquez:** Bien.  
**Cubakilos:** Jimmy, pudiste verlo de porque ahí lo que tenemos que ir haciendo poco a poco es lo de la migración de un teléfono a otro. No sé si tenemos.  
**Jhony Alexander Alvarez Vasquez:** Eh, sí, también justo le iba a dar la palabra a Jimmy también porque él me reportó que ya hizo el  
**Cubakilos:** Ah,  
**Jhony Alexander Alvarez Vasquez:** bot o o que ya lo tenía para probar Jimmy,  
**Cubakilos:** vale.  
**Jhony Alexander Alvarez Vasquez:** ¿verdad? Jim, ¿estás?  
**Cubakilos:** está  
**Jhony Alexander Alvarez Vasquez:** Sí, creo que está hablando con el mute.  
**Cubakilos:** muteado.  
**Jhony Alexander Alvarez Vasquez:** A ver, a ver, aquí si necesito que se lo active, aunque yo lo veo, yo veo ahí el bot, pero quiero que que lo te lo mencione.  
**Cubakilos:** Johnny, que ahí Johnny, que ahí la pregunta es, yo lo que tengo que hacer ahora es cambiar, por ejemplo, en mi web, en mis redes sociales, en la publicidad, poner el teléfono nuevo,  
**Jhony Alexander Alvarez Vasquez:** es lo más conveniente para que él empiece a a tener historial.  
**Cubakilos:** ¿vale? Va llegar mensaje, ¿vale?  
**Jhony Alexander Alvarez Vasquez:** Eh,  
**Cubakilos:** Entonces, ¿qué me va a pasar?  
**Jhony Alexander Alvarez Vasquez:** más que todo ser para los masivos o para la publicidad que hagas a futuro.  
   
 

### 00:59:58 {#00:59:58}

   
**Cubakilos:** Claro. Entonces, ¿qué me va a pasar ahí?  
**Jhony Alexander Alvarez Vasquez:** Dime.  
**Cubakilos:** Yo voy poniendo el número nuevo en todos los lugares y me van a empezar a llegar ya el mensaje de nuevas personas porque los viejos me van a estar seguir escribiendo al 644\. Entonces esto es para aclararme la idea, por eso para que te la comento y tú me dices,  
**Jhony Alexander Alvarez Vasquez:** Correcto.  
**Cubakilos:** tú y Jimmy me dicen si es por ahí. Entonces, primera voy a cambiar el teléfono a todos los lugares. Segundo. Entonces, con el bot, los que me vayan escribiendo el número viejo le va a empezar a llegar, mira, vamos a pasar la conversación a este teléfono.  
**Jhony Alexander Alvarez Vasquez:** Tienes dos maneras de trabajar eso, ya sea que sea solamente los nuevos que lleguen al viejo teléfono porque aunque quites publicidad por ahí te va a saltar alguno nuevo o que todos los los clientes que estén activos en este momento, desde mañana, desde cierta hora, desde lo que tú nos digas, le empiecen a llegar también. Es como tú quieras manejarlo. Eh, hemos tenido estas experiencias con otras empresas y ellos suelen dejarle los a los clientes viejos ahí. Otros nos dicen, "No, Johnny, ya el que escriba, que le llegue el mensaje así sea un cliente viejo." Pero bueno,  
   
 

### 01:01:03 {#01:01:03}

   
**Jhony Alexander Alvarez Vasquez:** eso ya tú nos dirás,  
**Cubakilos:** Sí, pero o sea, pero pero una cosa,  
**Jhony Alexander Alvarez Vasquez:** ¿eh?  
**Cubakilos:** eh, porque creo entiendo que, por ejemplo, si yo saco del el WhatsApp Business que yo tengo actualmente con el número viejo, si yo le pongo el número nuevo, eh, cambio de número, va a pasar que en la aplicación le va a decir a los a los usuarios, mira, este Esta persona cambió el teléfono, ¿cierto?  
**Jhony Alexander Alvarez Vasquez:** Correcto. Eso  
**Cubakilos:** Entonces, ¿qué me va a pasar?  
**Jhony Alexander Alvarez Vasquez:** sí,  
**Cubakilos:** Que en el business y ahí lo que me sucede es que en el WhatsApp Business se me borran todas las conversaciones,  
**Jhony Alexander Alvarez Vasquez:** correcto.  
**Cubakilos:** pero las tengo guardadas en el CRM.  
**Jhony Alexander Alvarez Vasquez:** Eso sí nunca se va a borrar.  
**Cubakilos:** ¿Vale?  
**Jhony Alexander Alvarez Vasquez:** ten presente algo que  
**Cubakilos:** Entonces, a mí lo que se me estaba ocurriendo es,  
**Jhony Alexander Alvarez Vasquez:** Eu  
**Cubakilos:** y ahora esta sería la última, es que de momento, como todavía tengo control de ese número del se del viejo, yo lo que sea es que le vaya diciendo a los clientes con estados y con un botle, mira, vamos a ir pasando las conversaciones al número este nuevo y entonces dentro de un tiempo hacer el cambio de teléfono en el WhatsApp Business Viejo, o sea, ponerle el cambio de teléfono.  
   
 

### 01:02:16 {#01:02:16}

   
**Jhony Alexander Alvarez Vasquez:** Ah, okay. Ya,  
**Cubakilos:** entiendo.  
**Jhony Alexander Alvarez Vasquez:** ya me quitaste de la boca algo que te iba a mencionar, pero sí es mejor hacerlo así para para que tú mantengas en control de  
**Cubakilos:** Sí,  
**Jhony Alexander Alvarez Vasquez:** de lo que de los clientes  
**Cubakilos:** sí. O sea, porque tengo el registro, pero va a llegar un momento donde ya esas conversaciones ya no me da me da igual no tenerlas,  
**Jhony Alexander Alvarez Vasquez:** anteriores.  
**Cubakilos:** perderlas en el WhatsApp Business, ¿no? Igual las puedo consultar en en CRM, pero bueno, ya no son tan frescas, ¿no? Es lo que  
**Jhony Alexander Alvarez Vasquez:** Sí, correcto. Y algo para tener en cuenta es que si un cliente viejo te escribe desde el nuevo  
**Cubakilos:** Vale.  
**Jhony Alexander Alvarez Vasquez:** API, se te va a crear otro lead en  
**Cubakilos:** Claro,  
**Jhony Alexander Alvarez Vasquez:** CONMO.  
**Cubakilos:** eso es en cómo Claro,  
**Jhony Alexander Alvarez Vasquez:** Eso sería en  
**Cubakilos:** claro, claro.  
**Jhony Alexander Alvarez Vasquez:** conmo.  
**Cubakilos:** Entonces yo tendría que para que no se me dupliquen los leads, ¿qué tendría que hacer? desconectar el WhatsApp, viejo. De  
**Jhony Alexander Alvarez Vasquez:** No, no se te va a duplicar de todas maneras.  
   
 

### 01:03:10 {#01:03:10}

   
**Cubakilos:** cómo  
**Jhony Alexander Alvarez Vasquez:** un cliente viejo te escribe al nuevo. Sí, porque nos ha pasado, nos ha pasado con y con otros  
**Cubakilos:** c\*\*\*. Entonces,  
**Jhony Alexander Alvarez Vasquez:** clientes.  
**Cubakilos:** entonces yo no puedo unificar las conversaciones después.  
**Jhony Alexander Alvarez Vasquez:** te va a saltar un aviso que te va a decir si esta conversación es duplicado en CONMO, sino que el problema de CONMO es que él tiene de uno, pero no sé si te ha llegado a pasar, de pronto sí que en leads suele decir eh consultar duplicados y ahí vas a poder ver si algún cliente se ha hablado desde otro y le das fusionar y ya se te queda un ladit no más.  
**Cubakilos:** Vale, vale, vale. Bueno, listo.  
**Jhony Alexander Alvarez Vasquez:** Ya, ya llegó Jimmy. Entonces, y pero si has visto esa esa opción, esa sería la única. Dice buscar duplicados en leads, en los tres puntos dice buscar duplicados y ahí te muestra que clientes de pronto con el mismo número se han comunicado desde desde otra fuente. En este caso sería lo que pasaría. ¿Qué es esto? Es esto acá esta opción.  
**Cubakilos:** B  
**Jhony Alexander Alvarez Vasquez:** Le das fusionar de ahí, mira si que que dejar en común y ya le das fusionar.  
   
 

### 01:04:28

   
**Jhony Alexander Alvarez Vasquez:** Es acá tres puntos. Buscar duplicados.  
**Cubakilos:** Vale, por lo que por lo que entiendo lo más eh por cortar por lo sano, lo más rápido es, por ejemplo, que haga el cambio de teléfono en el WhatsApp, viejo. Pongo el número nuevo, cambiar el teléfono, pongo el número nuevo y ya todo el mundo le va a decir, "Mira, esta persona, este persona cambió de teléfono y ya se sigue por ese mismo WhatsApp.  
**Jhony Alexander Alvarez Vasquez:** Sí. Y aquí te va a llegar los nuevos lits, pero bueno, teniendo en cuenta que te van a se van a  
**Cubakilos:** Ah,  
**Jhony Alexander Alvarez Vasquez:** duplicar,  
**Cubakilos:** sí o sí. Aún cuando cambie el teléfono voy a tener nuevos leads.  
**Jhony Alexander Alvarez Vasquez:** ¿correcto? Sí, porque aquí este es un sistema como en conexión pero totalmente aislado. Inclusive tú puedes borrar todo el WhatsApp, absolutamente todo y cono a seguir quieto. Igual si alguien te escribe independiente de lo que hayas hecho, ah, que cambié el número, que le di la opción de cambiar número y demás, aquí se te va a crear otro lead apenas esa persona te escriba. Luego aquí se te va a saltar un aviso y va a decir, "Este el está duplicado. Hay soluciones.  
   
 

### 01:05:32 {#01:05:32}

   
**Cubakilos:** se me van a dupo.  
**Jhony Alexander Alvarez Vasquez:** Eh,  
**Cubakilos:** Me preocupo porque se me haga duplicar miles de lit.  
**Jhony Alexander Alvarez Vasquez:** si hay muchísimos que están todavía.  
**Cubakilos:** Date cuenta, date cuenta que yo respondo 200 mensajes por día o  
**Jhony Alexander Alvarez Vasquez:** Bueno, sí,  
**Cubakilos:** más.  
**Jhony Alexander Alvarez Vasquez:** tenerlo tenerlo presente de momento. Entonces, sería bueno es con las conversaciones nuevas que que les llegue el aviso al WhatsApp anterior, que escriban mejor al otro y a los clientes que vayan llegando nuevos por la publicidad que cambies el número que cambies. Me refiero al bot de este mensaje número nuevo. Jimmy, ya está, ¿cierto?  
**Cubakilos:** Mhm.  
**Jhony Alexander Alvarez Vasquez:** Me me refiero eh eh René que que si un cliente nuevo del  
**Jimmy Ospina:** en jefe de  
**Jhony Alexander Alvarez Vasquez:** número viejo te escribe,  
**Jimmy Ospina:** Orel.  
**Jhony Alexander Alvarez Vasquez:** o sea, por ejemplo, este, se salta un mensaje diciendo, "Hola, este no es nuestro nuevo número." Aunque bueno,  
**Cubakilos:** Mhm.  
**Jhony Alexander Alvarez Vasquez:** esto va a ir pasando menos porque tú ya ves vas a cambiar la publicidad en en las páginas y demás.  
**Cubakilos:** Sí.  
**Jhony Alexander Alvarez Vasquez:** Y los clientes anteriores,  
**Jimmy Ospina:** H  
   
 

### 01:06:39 {#01:06:39}

   
**Jhony Alexander Alvarez Vasquez:** mientras van saliendo, mientras van ya se van despachando y como dijiste tú y en después de un tiempo, un mes, dos meses que ya estés full con el otro número, ahí sí hace ese cambio para que los clientes ya empiecen a hablar al otro lado y probablemente van a hacer consultas nuevas porque ya va a pasar unos días, no vas a tener que ir a buscar el número nuevo, al nuevo al anterior, digo, pero sí es un defecto de Cosmo. ese tema del duplicado y hay algunas personas en empresas de desarrollo con como que manejan ese tema de los duplicados que lo puede se puede automatizar inclusive, solo que normalmente son eh automático como tantos ladits y luego empieza a cobrar una tarifa pequeña, pero bueno, es un fremium.  
**Cubakilos:** Vale.  
**Jhony Alexander Alvarez Vasquez:** Encuentra encuentra y uno automáticamente duplicados de contactos. Entonces,  
**Cubakilos:** He.  
**Jhony Alexander Alvarez Vasquez:** puedes probarlo. ¿Qué tal te va? si está funcionando bien. Y bueno, eh avisarte que normalmente estas herramientas, bueno, casi yo creo que son todas, ya he probado algunas, eh, después de 10, 20 más o menos que se han funcionado te dicen, "Okay, te gustó, paga tanto." Vale.  
   
 

### 01:07:50 {#01:07:50}

   
**Cubakilos:** Sí, claro. Vale,  
**Jhony Alexander Alvarez Vasquez:** Jimmy, entonces el bot es solamente es que René nos diga cuándo y ya le llegaría una plantilla al cliente que  
**Cubakilos:** acuerdo.  
**Jhony Alexander Alvarez Vasquez:** escriba  
**Jimmy Ospina:** Eh, sí, pero yo configuré el mensaje,  
**Jhony Alexander Alvarez Vasquez:** o  
**Jimmy Ospina:** mensaje así genérico indicando, obviamente pues cuál era el número nuevo y ya es que nos diga si está bien el mensaje y ya ponerle el disparador.  
**Cubakilos:** Y una una pregunta,  
**Jhony Alexander Alvarez Vasquez:** un enlace.  
**Cubakilos:** Yi, ¿es posible, o sea, creo que me comentaste, pero es posible que tenga como un link donde el cliente haga click y pase para el número nuevo  
**Jimmy Ospina:** Eh, sí, sí,  
**Jhony Alexander Alvarez Vasquez:** tendríamos puede poner un link ahí directo en el mensaje,  
**Jimmy Ospina:** cierto.  
**Cubakilos:** o  
**Jimmy Ospina:** Se puede sacar el link de WhatsApp  
**Jhony Alexander Alvarez Vasquez:** sino que como no está con el API, entonces no va a funcionar un botón, pero sí se puede poner un enlace y el cliente toca y de una se le abre se le abre la otra  
**Jimmy Ospina:** el enlace.  
**Jhony Alexander Alvarez Vasquez:** conversación.  
**Cubakilos:** sí, sí, sí, sí. para que sea más fácil para él.  
**Jhony Alexander Alvarez Vasquez:** Okay, eso que está listo. Entonces, solo es que nos digas eh cuándo  
   
 

### 01:08:47 {#01:08:47}

   
**Cubakilos:** No, déjamelo ahí, déjamelo listo y yo le doy activar ya cuando lo lo único que Vale,  
**Jhony Alexander Alvarez Vasquez:** eso.  
**Cubakilos:** entiendo que es el SBS.  
**Jimmy Ospina:** Co?  
**Jhony Alexander Alvarez Vasquez:** No  
**Cubakilos:** Ya yo edito la plantilla y listo.  
**Jhony Alexander Alvarez Vasquez:** sé.  
**Cubakilos:** Déjamelo con el activador y todo y yo lo que le doy es activar. O sea, por ejemplo, lo dejas,  
**Jimmy Ospina:** Vale.  
**Cubakilos:** lo pones listo y le das pausa y yo le doy play cuando ya estemos.  
**Jimmy Ospina:** Ah, vale, perfecto. Le pongo el disparador y lo lo Vale,  
**Cubakilos:** y lo pauso.  
**Jhony Alexander Alvarez Vasquez:** y lo apagas y agregas el link que se vaya ese número  
**Jimmy Ospina:** perfecto. Ah,  
**Cubakilos:** Sí,  
**Jimmy Ospina:** sí,  
**Jhony Alexander Alvarez Vasquez:** directo.  
**Jimmy Ospina:** sí.  
**Cubakilos:** sí,  
**Jimmy Ospina:** Lo lo agrego.  
**Cubakilos:** sí.  
**Jhony Alexander Alvarez Vasquez:** Bueno, René, mañana mañana podrías otra vez a las 4\.  
**Cubakilos:** Sí,  
**Jhony Alexander Alvarez Vasquez:** Quizás nos demoremos menos.  
**Cubakilos:** a las 4\. Vale, quedamos a las 4\.  
**Jhony Alexander Alvarez Vasquez:** Perfecto.  
**Cubakilos:** Entonces,  
**Jhony Alexander Alvarez Vasquez:** Así tenemos avances cada día. intentamos no hacerlo un poco más breve que hoy.  
**Cubakilos:** venga, un saludo.  
**Jhony Alexander Alvarez Vasquez:** Bueno, igualmente.  
**Jimmy Ospina:** Vale,  
**Jhony Alexander Alvarez Vasquez:** Hasta luego.  
**Cubakilos:** Chao,  
**Jimmy Ospina:** Saludos.  
**Cubakilos:** chicos.  
**Jimmy Ospina:** Felizard,  
   
 

### La transcripción finalizó después de 01:09:50

*Esta transcripción editable se ha generado por ordenador y puede contener errores. Los usuarios también pueden cambiar el texto después de que se haya generado.*