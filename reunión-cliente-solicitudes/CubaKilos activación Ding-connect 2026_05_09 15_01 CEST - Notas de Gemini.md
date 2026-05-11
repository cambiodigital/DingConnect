# 📝 Las notas

may 9, 2026

## Reunión del 9 may 2026 a las 15:01 CEST

Registros de la reunión [Transcripción](https://docs.google.com/document/d/155zez9SAMrbCQNBVZsJN1nVPw0D7fkpPyEmC5-uJFdQ/edit?usp=drive_web&tab=t.m5860yh4d7o0) [Grabación](https://drive.google.com/file/d/1C7fksvesuZco2mtL8GzWZJYq6bz1J6no/view?usp=drive_web) 

### Resumen

La reunión abordó ajustes técnicos en el sistema, la optimización de pagos y la planificación del lanzamiento.

**Ajustes técnicos y pagos**  
Se decidió usar el identificador de referencia de transferencia como ID único provisional para confirmar exitosamente los pedidos.

**Optimización de interfaz**  
Se ajustaron los productos promocionales y la estructura del catálogo para mejorar la claridad de la experiencia usuario.

**Planificación y despliegue**  
Se definió el lanzamiento de la actualización para el día siguiente tras completar las pruebas de funcionamiento finales.

*Califica este resumen:* [Útil](https://google.qualtrics.com/jfe/form/SV_4YkxrBAaiTVqYCi?isGoogler=false&isHelpful=true) o [Poco útil](https://google.qualtrics.com/jfe/form/SV_4YkxrBAaiTVqYCi?isGoogler=false&isHelpful=false)

### Próximos pasos

- [ ] \[Jhony Alexander Alvarez Vasquez\] Consultar ID: Consultar a DIN si es posible obtener el ID de transacción real, no solo el ID de Transfer API, añadiendo la pregunta al correo.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Actualizar Estado: Implementar la lógica para cambiar automáticamente el estado del pedido a Completado una vez que DIN Connect responda con una transacción exitosa.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Filtrar Tarjetas: Aplicar la regla de categorización de Gift Cards en Cuba: si el operador no contiene la palabra Cubacel, clasificar como producto de Gift Card.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Desmarcar Opción: Ajustar el selector de rango de monto para que permanezca desmarcado por defecto, a pesar de tener la opción de rango activa.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Reparar Icono: Solucionar el problema de renderizado que impide mostrar el icono de la bandera en la página de recarga.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Modificar Etiqueta: Cambiar la etiqueta frontal precio al público a solo precio en la selección de paquetes.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Ajustar Ancho: Modificar el contenedor de la descripción del producto para que aproveche el ancho completo, sacándolo del contenedor de 2 columnas.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Limpiar Títulos: Eliminar la palabra Cuba de los títulos de los productos mostrados en la lista de selección de paquetes.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Eliminar Redundancia: Quitar las líneas Operador Cuba y País Cuba del resumen de confirmación del checkout.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Etiqueta Teléfono: Cambiar la etiqueta del campo de teléfono en el checkout a Tu teléfono para evitar confusiones con el número del beneficiario.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Diseño Botones: Aplicar cambios de diseño a los botones de pago: color gris para Cambiar paquete y resaltar el botón Proceder al pago.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Investigar Registros: Investigar y posiblemente eliminar o desactivar el plugin que está generando archivos de registro excesivamente grandes.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Escanear Registros: Escanear datos copiados enviados por Cubakilos. Usar guía para revisar información.

- [ ] \[Cubakilos\] Enviar Página Web: Guardar página web completa como archivo único. Enviar archivo a Jhony a través de Google Chat.

- [ ] \[Cubakilos\] Confirmar Recarga: Escribir a su madre por WhatsApp. Confirmar si la recarga se hizo efectiva.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Escalar Recibos: Consultar con Dincon Connect el problema. Determinar por qué no se generan los recibos de transacción.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Corregir Estado: Debuggear el algoritmo de estado de la transacción. Utilizar el ID de referencia API transfer recibido para configurar el estado a exitoso o completado.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Pulir Diseño: Aplicar correcciones de diseño y texto solicitadas. Arreglar el mensaje final de confirmación de pedido.

- [ ] \[Cubakilos\] Crear Landing: Diseñar la página de destino definitiva usando Elementor. Integrar el short code de recarga.

- [ ] \[Jhony Alexander Alvarez Vasquez\] Realizar Pruebas: Ejecutar pruebas de recarga con Colombia. Confirmar que el funcionamiento es correcto.

### Detalles

* **Actualización del sistema y pruebas iniciales**: Se confirmó la subida de una nueva versión del sistema, y se acordó realizar pruebas de funcionamiento con una transacción real de 1 € mediante Bison para asegurar que el sistema se comporte correctamente en el entorno de producción, dado que las pruebas en modo "test" no siempre replican los escenarios reales ([00:01:12](#00:01:12)).

* **Resumen de cambios recientes**: Se repasaron las mejoras implementadas recientemente, que incluyen la incorporación de un buscador global que permite filtrar por palabras clave sin restringir los resultados, la visualización del porcentaje de utilidad, la capacidad de ordenar columnas y la posibilidad de editar el nombre comercial y el beneficio directamente desde la vista del cliente ([00:03:25](#00:03:25)).

* **Consulta sobre el ID de transacción**: Se discutió la dificultad para obtener el ID de transacción real desde la API de DIN, ya que actualmente solo se recibe un ID de referencia de transferencia. Se acordó realizar una consulta formal para verificar si es posible obtener el identificador real, el cual es necesario para ofrecer un mejor soporte al cliente ante posibles incidencias ([00:04:41](#00:04:41)).

* **Configuración de los estados de pedido en WooCommerce**: Se analizó el flujo de los estados de pedido (de "procesando" a "completado"). Se aclaró que, mientras que la tienda de alimentos requiere una entrega física antes de marcar un pedido como completado, las recargas son procesos inmediatos, por lo que es preferible ajustar el estado a "completado" una vez que la transacción sea exitosa ([00:07:10](#00:07:10)).

* **Automatización del cambio de estado a completado**: Se estableció que, al recibir la confirmación de la API de DIN indicando que la recarga fue exitosa, el sistema debe cambiar automáticamente el pedido a estado "completado". Esto permitirá gestionar disparadores adicionales en WooCommerce, como el envío de correos electrónicos automáticos informando al cliente sobre la finalización de la recarga ([00:10:32](#00:10:32)).

* **Categorización de tarjetas de regalo (Gift Cards)**: Ante la falta de imágenes en algunas tarjetas de regalo proporcionadas por la API, se definió una regla de filtrado para agrupar estos productos en una categoría separada: si el operador no contiene la palabra "Cubacel", el producto será clasificado automáticamente como una tarjeta de regalo ([00:15:10](#00:15:10)).

* **Ajustes en el selector de montos**: Se solicitó configurar el campo de selección de monto para que aparezca desmarcado por defecto, evitando así confusiones para el cliente al momento de realizar la compra ([00:19:28](#00:19:28)).

* **Gestión de promociones y tipos de producto**: Se debatió la complejidad de las ofertas (como la promoción de "saldo por 10" e internet ilimitado). Debido a que los clientes no deberían calcular los montos manualmente en rangos variables, se decidió crear productos específicos duplicados para cada nivel de oferta (ej. 600 CUP y 750 CUP), permitiendo configurar el nombre, beneficio y precio de forma fija para cada uno ([00:21:35](#00:21:35)).

* **Configuración de productos en el catálogo**: Se procedió a duplicar y configurar los productos promocionales, ajustando los precios al público (ej. 35 €) y los beneficios descritos (ej. 6000 CUP o 7500 CUP), asegurando que el SKU y los detalles sean coherentes con las promociones vigentes por el Día de las Madres ([00:27:37](#00:27:37)).

* **Estructura de la página de destino (landing page)**: Se definió la estructura para la página de "Recarga Cubacel", acordando usar el número de teléfono como primer paso en la experiencia de usuario, seguido por la selección del plan. Se inició la creación de los "shortcodes" para integrar estos productos en la página de manera limpia, eliminando los productos de prueba obsoletos ([00:30:42](#00:30:42)).

* **Problemas visuales y diseño de interfaz**: Se detectó un problema técnico donde el icono de la bandera no se renderizaba correctamente en la interfaz, por lo que se planeó revisar los estilos CSS y considerar la opción de mostrar la información sin el icono si el problema persiste en diferentes navegadores ([00:35:01](#00:35:01)).

* **Diseño y experiencia de usuario**: Se revisó el diseño de la interfaz, sugiriendo cambios para mejorar la claridad: ajustar las etiquetas de los botones, optimizar el uso del ancho del contenedor para las descripciones de los beneficios y retirar etiquetas redundantes como "País: Cuba" cuando la información ya está implícita por el contexto de la recarga ([00:37:37](#00:37:37)).

* **Verificaciones técnicas y analíticas**: Se verificó la configuración de herramientas externas, como Google Analytics y el píxel de Facebook, confirmando que, aunque se observan advertencias en la consola del navegador durante el desarrollo, estos sistemas están activos y funcionan según lo esperado ([00:42:44](#00:42:44)).

* **Proceso de prueba de recarga**: Se realizó una simulación de compra completa introduciendo datos de prueba. Se observó que el sistema mostraba los paquetes disponibles correctamente, pero se identificó la necesidad de ocultar elementos innecesarios como el selector de país si todos los productos pertenecen a un mismo origen ([00:45:31](#00:45:31)).

* **Optimización de la interfaz de usuario**: Se continuó afinando la interfaz del formulario de pago, decidiendo cambiar el texto del campo de número de teléfono a "Número a recargar" para mayor claridad, y se solicitó realizar ajustes de diseño para jerarquizar el botón "Proceder al pago" frente a otras opciones ([00:49:10](#00:49:10)).

* **Mantenimiento del servidor y registros**: Se identificó un exceso de archivos de registro (logs) que ocupaban gran parte del espacio del servidor, alcanzando hasta 45 megas o más. Se procedió a limpiar estos registros para asegurar el rendimiento del sistema ([00:54:21](#00:54:21)).

* **Depuración de errores en el proceso de pago**: Durante la prueba final de pago, la consola mostró errores relacionados con políticas de prevención de seguimiento ("tracking prevention") y una aparente falta de activación del protocolo 3D Secure. Se copió el registro de la consola para investigar estos errores y asegurar que la pasarela de pago procese las transacciones de manera segura ([00:55:55](#00:55:55)).

* **Depuración técnica del plugin Din Connect**: Jhony Alexander Alvarez Vasquez y Cubakilos analizan los registros del sistema para identificar la causa de errores en el acceso y funcionamiento del plugin. Determinan que algunos errores de "sin autorización" provienen de la tienda y no directamente del plugin ([00:59:11](#00:59:11)). Deciden extraer información detallada de la página web para realizar un análisis que permita optimizar el rendimiento del sistema ([01:00:24](#01:00:24)).

* **Ajustes de visualización y facturación**: Los participantes revisan la estructura del recibo y la página de confirmación. Se confirma que se han realizado los cambios solicitados respecto a la visibilidad de las acciones y la organización de los datos del pedido en el formato de archivo único para exportar ([01:01:28](#01:01:28)).

* **Verificación de estado de recargas**: Jhony Alexander Alvarez Vasquez y Cubakilos verifican los registros en la plataforma "Connect" para comprobar si las recargas se procesaron correctamente. Confirman que el sistema notificó la recepción del pedido y que, según los registros, la transacción fue aceptada ([01:02:25](#01:02:25)).

* **Análisis de discrepancias en montos**: Identifican un error en el monto recibido de 560 pesos, cuando el requisito mínimo para aplicar los bonos es de 600 pesos. Cubakilos señala que existe una inconsistencia entre los montos esperados y los procesados efectivamente ([01:05:41](#01:05:41)).

* **Gestión de recibos de transacción**: Cubakilos reporta que el sistema impide generar e imprimir recibos para las nuevas transacciones, a diferencia de las antiguas. Ante esta situación, deciden que el ID de transacción será el identificador principal para que el cliente pueda realizar consultas, dado que el sistema actualmente no genera el comprobante correctamente ([01:08:29](#01:08:29)).

* **Estado de procesamiento de transacciones**: Jhony Alexander Alvarez Vasquez explica que el estado "procesando" ocurre porque el algoritmo no recibe una señal clara de confirmación desde Din Connect. Discuten la necesidad de recibir una respuesta definitiva de los proveedores para evitar ambigüedades en el estado del pedido ([01:11:17](#01:11:17)).

* **Implementación de ID de referencia temporal**: Ante la falta de respuesta sobre el ID de transacción, acuerdan utilizar el "API transfer ref" como identificador único provisional. Este número de nueve dígitos servirá para marcar los pedidos como exitosos temporalmente mientras se obtiene una solución permanente de los proveedores ([01:13:17](#01:13:17)).

* **Definición de mensajes de estado al cliente**: Jhony Alexander Alvarez Vasquez se compromete a ajustar los avisos para el cliente dentro del sistema, utilizando el ID de referencia mencionado para confirmar el éxito de la operación. El objetivo es que el usuario final reciba una confirmación clara y evitar confusiones en los pedidos pendientes ([01:15:53](#01:15:53)).

* **Planificación del lanzamiento**: Cubakilos establece el objetivo de lanzar la actualización hoy mismo para que esté operativa mañana. Jhony Alexander Alvarez Vasquez trabajará en la corrección de los estilos y textos, mientras Cubakilos se enfocará en diseñar la página de destino final mediante Elementor ([01:18:47](#01:18:47)).

* **Pruebas de escenarios de mercado**: Los participantes acuerdan realizar pruebas esta tarde con transacciones de Colombia, aprovechando la evidencia de que el sistema funciona con recargas de Cuba. Esto permitirá confirmar el correcto funcionamiento del plugin en diversos escenarios de paquetes y montos ([01:19:52](#01:19:52)).

* **Integración de servicios de viajes**: Cubakilos informa que ya tiene acceso a la intranet de un consolidador para su agencia de viajes. Discuten la viabilidad de extraer los productos a su propia web en lugar de utilizar un iFrame, lo cual requiere consultar con el personal informático de la entidad externa ([01:20:43](#01:20:43)).

* **Estado del proyecto GLS y supervisión técnica**: Discuten los retrasos en el proyecto GLS, atribuidos a la falta de permisos y a la realización de pruebas fuera de tiempo por parte de Sebas. Jhony Alexander Alvarez Vasquez enfatiza la necesidad de supervisar más estrechamente las tareas técnicas para evitar que los proyectos se alarguen innecesariamente hasta el final de la semana ([01:21:53](#01:21:53)).

* **Estrategia de desarrollo y documentación**: Jhony Alexander Alvarez Vasquez destaca que el avance en futuros proyectos será más rápido gracias a la documentación en repositorios y al entendimiento profundo que tienen del modelo de negocio de Cubakilos. Se reitera el compromiso de continuar con el trabajo pendiente el lunes para mantener la eficiencia en el desarrollo ([01:24:06](#01:24:06)).

*Revisa las notas de Gemini para asegurarte de que sean precisas. [Obtén sugerencias y descubre cómo Gemini toma notas](https://support.google.com/meet/answer/14754931)*

*Cómo es la calidad de **estas notas específicas?** [Responde una breve encuesta](https://google.qualtrics.com/jfe/form/SV_9vK3UZEaIQKKE7A?confid=I-TH3_sT2_RXPvrzscPBDxIYOAIIigIgABgDCA&detailid=standard&screenshot=false) para darnos tu opinión; por ejemplo, cuán útiles te resultaron las notas.*

# 📖 Transcripción

may 9, 2026

## Reunión del 9 may 2026 a las 15:01 CEST \- Transcripción

### 00:01:12 {#00:01:12}

**Cubakilos:** Hola, hola, hola. Joni,

**Jhony Alexander Alvarez Vasquez:** Hola, René, ¿qué tal? Buenos días, buenas tardes. Ya.

**Cubakilos:** buenos días.

**Jhony Alexander Alvarez Vasquez:** ¿Qué tal el fin de semana?

**Cubakilos:** Igual no se para demasiado.

**Jhony Alexander Alvarez Vasquez:** Sí, sí. Em, ¿qué te?

**Cubakilos:** Pudiste subir la actualización y tal.

**Jhony Alexander Alvarez Vasquez:** Sí, sí, ya la he subido. Eh, vi un detallito ahí y te subí otra versión hace un minuto. Eh, bueno, con estas todos estos cambios que hice ayer, eh, lo que me falta es probarlo. Entonces, hagámoslo hagámoslo contigo. Eh,

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** no sé si esta vez me puedes ayudar. Y tú haces el bison de 1 € igual,

**Cubakilos:** vale, vale, seguro, sin problema.

**Jhony Alexander Alvarez Vasquez:** igual igual. igual te llega a ti.

**Cubakilos:** ¿Dónde

**Jhony Alexander Alvarez Vasquez:** Está estaría bien. Bueno,

**Cubakilos:** está?

**Jhony Alexander Alvarez Vasquez:** yo creo que de eso de esas pruebas es que yo, o sea, yo tengo una opción de de cambiar eso a modo test, pero es que nos pasa mucho de que todo queda bien en en modo test, pero cuando lo pasamos a production se arman muchos muchas eh otros escenarios y bueno, y hay otro rollo.

### 00:02:30

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** Entonces, no sé, yo estaba teniendo la idea es que si el lunes que empezáramos de pronto con algunas mejoras, algo, algunas pruebas adicionales, yo te pueda decir como que enviarte el Bisum y de 1 € y cuando queramos hacer algún alguna prueba o algo te escribo como, mira, te mandé Bisun.

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** Pues yo me he gastado solo como 5 € en eso.

**Cubakilos:** vamos allá. No te preocupes.

**Jhony Alexander Alvarez Vasquez:** Entonces es te llega a ti mismo el

**Cubakilos:** Vamos. Yo yo lo hago por aquí. Déjame.

**Jhony Alexander Alvarez Vasquez:** euro y lo único que perdería serían como, no sé, la comisión creo que es cierto,

**Cubakilos:** Eh, no sé,

**Jhony Alexander Alvarez Vasquez:** la comisión de la pasarela.

**Cubakilos:** da igual, da igual. El lo es probar y que funcione,

**Jhony Alexander Alvarez Vasquez:** Ah, sí,

**Cubakilos:** ¿eh? Vale, entonces no sé,

**Jhony Alexander Alvarez Vasquez:** sí,

**Cubakilos:** tienes que poner la página, ¿no? O cómo?

**Jhony Alexander Alvarez Vasquez:** sí, sí. Ya voy a voy a

**Cubakilos:** Vale, vamos para Vale,

**Jhony Alexander Alvarez Vasquez:** compartir.

### 00:03:25 {#00:03:25}

**Cubakilos:** vi que me pusiste varios como un resumen con varios cambios. No sé si me los puedes mostrar

**Jhony Alexander Alvarez Vasquez:** Ah, sí, sí. Eso fue resumen como de lo que estuvimos hablando.

**Cubakilos:** rápidamente.

**Jhony Alexander Alvarez Vasquez:** Entonces, yo iba tomando nota y todo lo las notas, pues hasta medianoche pues me puse a hacerlo. Entonces, buscador, el buscador global, o sea, que aparte de los filtros que salen en las tablas, tú también al escribir algo te sirva también de filtro. Pones Argentina, te vas Venezuela, Cuba, también te va a salir todo lo de Cuba, o sea, no no se va a restringir la la búsqueda. Porcentaje de utilidad,

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** eso es como un informativo para cada producto que tú puedas tener ahí como qué porcentaje tenías en el momento en que lo creaste. el orden de columnas, que también es muy útil, tú le tocas y orden ordena de menor a mayor según la columna que toques.

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** Eh, este fue los temas que estuvimos eh revisando. Eh, uno de ellos fue este, que era de que el beneficio se pudiera cambiar desde el producto. El nombre también comercial también es está dentro del propio del propio vista del cliente.

### 00:04:41 {#00:04:41}

**Jhony Alexander Alvarez Vasquez:** ID de la transacción también eh transfer ref, ¿te acuerdas que vimos este este campo?

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** Ese ya queda como transacción ID y ese lo mostramos en el PDF y en el correo y bueno, es que detrás de todo eso

**Cubakilos:** ahí un punto, pero bueno, para verlo más adelante. se le puede consultar y bueno, esperemos que algún momento eh desde DIN te contesten el mail ese que estuvo que extenso que les enviaste con varias preguntas. Eh,

**Jhony Alexander Alvarez Vasquez:** Mhm.

**Cubakilos:** más adelante preguntarle cómo obtener si es posible obtener el ID de la transacción real, porque lo que nos está devolviendo es el ID de transfer API, algo así.

**Jhony Alexander Alvarez Vasquez:** Ah, vale. Voy a consultarlo. Sin embargo, ese yo creo que lo veo que es el que ellos pusieron para eso, pero a ti te interesaría hacerlo otro donde puedas como ver más información o o

**Cubakilos:** Ya, bueno, porque al final, o sea,

**Jhony Alexander Alvarez Vasquez:** porque

**Cubakilos:** eh, ellos mismos le hacen un campo que se llama ID de transacción y no me no me dejas obtenerlo para ayudárselo al cliente, porque bueno, cuando el cliente me escriba,

**Jhony Alexander Alvarez Vasquez:** vale,

**Cubakilos:** okay, yo puedo decir, "Vale, ahora hay que buscar por transfer,

### 00:05:52

**Jhony Alexander Alvarez Vasquez:** vale.

**Cubakilos:** él me da el número, yo pongo el número y listo." Pero bueno, no sé, lo veo un poco eh contradictorio por gusto. Si tienes un campo que dice ID de transacción, que realmente es el ID de la transacción,

**Jhony Alexander Alvarez Vasquez:** Claro,

**Cubakilos:** ¿por qué no me lo das?

**Jhony Alexander Alvarez Vasquez:** podemos obtener. Yo añado eso al correo que ellos posiblemente tienen una demorita,

**Cubakilos:** ¿Entiendes?

**Jhony Alexander Alvarez Vasquez:** pero yo creo que el lunes, martes ya se ponen al día.

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** Verificar si podemos tener el ID de transacción.

**Cubakilos:** Y por ejemplo, no sé si eso lo lograste ya, una vez que el que que Din, o sea, tú le mandas la solicitud a DIN y luego DIN, o sea, hace el el la recarga y genera el ID de transfer o de transacción, es que tú pasas, por ejemplo, a completado el producto o que bueno, aunque realmente tú no estás haciendo un producto de Wcommerce o sí.

**Jhony Alexander Alvarez Vasquez:** Eh, cuando eso esa parte depende me dice si es si es lo que a lo que te refieres. Esa parte la maneja es más que todo la pasarela y aprovecho mostrarte que te vas a encontrar con estas configuraciones, eh, donde cómo se llama, money.

### 00:07:10 {#00:07:10}

**Jhony Alexander Alvarez Vasquez:** Money,

**Cubakilos:** Ah,

**Jhony Alexander Alvarez Vasquez:** creo que es.

**Cubakilos:** eso tienes que entrar en WCommerce. Párate en WCommerce. No es en WCommerce.

**Jhony Alexander Alvarez Vasquez:** Bueno, yo siempre lo veo por Blue Jeans, sino que bueno, en WCommerce aparece sí que está en la

**Cubakilos:** No, eso están los ajustes de en WCommerce es donde se se guardan todas las

**Jhony Alexander Alvarez Vasquez:** pasarela. Sí, ahí está la lista. Pero si ingresamos a la configuración de la pasarela,

**Cubakilos:** pasarelas.

**Jhony Alexander Alvarez Vasquez:** espérame que quedo cargando, eh, ahí sí muestra qué ocurre cuando el pedido es exitoso. Algunos lo tienen, otros no,

**Cubakilos:** Ah, sí,

**Jhony Alexander Alvarez Vasquez:** otros.

**Cubakilos:** sí, sé lo que te refieres. Sí.

**Jhony Alexander Alvarez Vasquez:** No sé si eso es lo que me estás diciendo.

**Cubakilos:** Bueno,

**Jhony Alexander Alvarez Vasquez:** A ver si Claro,

**Cubakilos:** ¿y cómo ella sabe si el pedido es exitoso o no?

**Jhony Alexander Alvarez Vasquez:** una cosa es lo que ordena money.

**Cubakilos:** Eh, está Mone y está Molly. También hay dos ajustes.

**Jhony Alexander Alvarez Vasquez:** Entonces, ajustes.

### 00:08:02

**Cubakilos:** Eso te va a llevar a la parte Wcommerce.

**Jhony Alexander Alvarez Vasquez:** Eso. Entonces,

**Cubakilos:** Ahí.

**Jhony Alexander Alvarez Vasquez:** mira el algo que vi es raro, no sé por qué la pina se me bloquea siempre. Ahí ya lo pasé la otra vez que cuando está recargando y se desapareció el el la pasarela, no no funciona así en test. Y aquí mira cómo hacer después del de pago. De todas maneras dentro de de WoCommerce hay también un estado del pedido que es

**Cubakilos:** H

**Jhony Alexander Alvarez Vasquez:** completado, pero la transacción y hay otro estado de pedido que es ya el la el propio pedido es el de él está por defecto estar acá lo pasa a procesando. Yo lo puse en completado como para hacer el mejor seguimiento,

**Cubakilos:** Claro.

**Jhony Alexander Alvarez Vasquez:** pero pero yo lo que hice fue que en DIN Connect tú a esa pasarela que elijas le dices cuando ejecutas el A

**Cubakilos:** Sí, porque ahí te voy a explicar por qué la diferencia y por qué estaba antes en processing,

**Jhony Alexander Alvarez Vasquez:** ver,

**Cubakilos:** porque como tú puedes ver la tienda mía eh realmente es de productos de alimentos, ¿no?

**Jhony Alexander Alvarez Vasquez:** sí.

**Cubakilos:** Y entonces cuando un cliente compra, yo le paso esa ese le mando una petición, una notificación al proveedor en Cuba.

### 00:09:21

**Cubakilos:** Ese proveedor en Cuba recibe, "Ah, mira, tengo que entregar en tal dirección eh eh cuatro piernas de cerdo,

**Jhony Alexander Alvarez Vasquez:** Hm.

**Cubakilos:** eh dos champú, etcétera." Y obviamente hasta que el proveedor no finalmente entregue el producto en casa del familiar del destinatario en Cuba, no pasaría realmente a completado, ¿no?

**Jhony Alexander Alvarez Vasquez:** Sí, yo creo que ese es lo ideal.

**Cubakilos:** Pero acá es diferente porque una recarga sí es

**Jhony Alexander Alvarez Vasquez:** Claro,

**Cubakilos:** inmediata.

**Jhony Alexander Alvarez Vasquez:** yo creo que está bien que dejemos entonces en procesando para que tú tengas control ahí y completados cuando ya se, o sea, ya terminó todo el pedido.

**Cubakilos:** Ahora, en el cas era lo que yo te comentaba y es lo que estábamos ahora diciendo, es aquí lo ideal sería es que internamente, no sé si lo puedes lograr, una vez que en el caso de la recarga dinte de webba un ID de transacción o un ID que sea válido de que ya hizo la transacción, que sería la idea de transacción, por ejemplo, él solo pase a completado.

**Jhony Alexander Alvarez Vasquez:** Ah, sí, sí, sí, sí, sí. Eso lo puedo hacer. Ese ese lo puede hacer,

**Cubakilos:** No.

**Jhony Alexander Alvarez Vasquez:** eh, y creo que lo tengo como pero no lo tengo para se configure.

### 00:10:32 {#00:10:32}

**Jhony Alexander Alvarez Vasquez:** Pero si adentro en el código si lo tengo, no lo tengo suelto, ya me acordé. lo tengo suelto. Bueno, tengo en la otra pantalla el código. Eh, lo tengo suelto. O sea, puedo puedo hacer que cuando realmente Dinconnect no nos responda de que se completó la eh el el pago, bueno, la transacción, no ni el pago, es que se haya enviado el la recarga que te iba a mostrar aquí, eh. Sí, que se mueva completado. Si está muy bien. Eso yo ya lo había visto. Él devuelves un estado en ese Jason lo muestra. Bueno, yo ya lo retomo. Din connectosa exitosa. Mueva el pedido a completado. Listo. Sol. Lo agrego también. Vale. Eh, entonces eh por el otro lado que es que es cuando que es cuando decimos que DINC Connect puede hacer la recarga,

**Cubakilos:** No.

**Jhony Alexander Alvarez Vasquez:** que es sería antes de eso que me acabas de pedir, que diga que diga, "Okay, ya puedo hacer la recarga o no." eh es cuando el pago se haya

### 00:11:58

**Jhony Alexander Alvarez Vasquez:** efectuado.

**Cubakilos:** Claro. Exacto. Eso sí, eso

**Jhony Alexander Alvarez Vasquez:** Y aquí y aquí yo le digo a él,

**Cubakilos:** sí.

**Jhony Alexander Alvarez Vasquez:** a Dinconnect, cuándo podemos decirle que se completó la el pago. ¿A qué a qué me refiero? Que podemos decirle, eh, sí, DIN Connect, haz la recarga cuando el el payment esté completado. Listo. Eso ya depende de la pasarela. O también puedo decirlo, ¿no? Como este esta pasarela no permite o no manda una señal, simplemente cuando el pedido pase a procesando o pase a completado, que también son estados viables. De momento este me lo estudié y sirve cuando pase completado, pero aquí tú puedes manejar eso.

**Cubakilos:** Pero no,

**Jhony Alexander Alvarez Vasquez:** Y entonces ahora lo que lo voy a

**Cubakilos:** esa parte esa parte no me queda clara porque ahí es simplemente que eh DIN

**Jhony Alexander Alvarez Vasquez:** poner

**Cubakilos:** ejecute el la recarga del producto o el voucher o lo que sea una vez que la pasarela eh verifica que el pago fue realizado con éxito. No hay mucho

**Jhony Alexander Alvarez Vasquez:** está así está así,

### 00:12:57

**Cubakilos:** más.

**Jhony Alexander Alvarez Vasquez:** sí. Sino que esto me tocó agregarlo porque estoy estudiando un poco a fondo las pasarelas y yo necesito que tú puedas instalar esta pasarela o otra o que esto lo puedas usar en otra página y demás. Y esto solamente es como para que lo tengas en cuenta.

**Cubakilos:** Sí, vale.

**Jhony Alexander Alvarez Vasquez:** A mí me ayuda también a controlar eso. Entonces el de momento está un poco no no nos afecta de momento

**Cubakilos:** Eh,

**Jhony Alexander Alvarez Vasquez:** esta parte mucho, pero bueno, la tienes clara en caso de que lo requieras. Y te lo estoy mencionando porque ahora me estás hablando de que ya cuando Dincon Connect complete, o sea, después de este paso complete la recarga, él ya es el que va nos va a responder y nos va a decir eh pago completado. Okay, mueva el pedido a completado también que ya me lo acaba de anotar.

**Cubakilos:** No,

**Jhony Alexander Alvarez Vasquez:** Entonces, para que el

**Cubakilos:** y cuando DIN te devuelva el ID de transacción,

**Jhony Alexander Alvarez Vasquez:** UNES

**Cubakilos:** que eso quiere decir que ya hizo la transacción, tú vas aquí a producto, me imagino que es en producto, en Wcom el producto, eh, perdón, pedidos en pedidos ahí,

### 00:13:59

**Jhony Alexander Alvarez Vasquez:** y él se pasa completado.

**Cubakilos:** ahí ese pedido debe pasar a completado.

**Jhony Alexander Alvarez Vasquez:** Es inclusive aquí a pasar a completado. Tú con Wcomer puedes manejar otros otros disparadores. Eso es un disparador. Puedes manejar otras respuestas que ya puede ser enviado en un correo adicional. Gracias por haber realizado la recarga.

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** Y el pedido eh tiene en las plantillas de Wocommerce que uno puede como manejar esa parte de de estos campos, puedes agarrar de acá inclusive, pero bueno, eso lo luego lo podemos ir ir adaptando. Que puedas agarrar en la plantilla de Wocommerce del envío, cuando se pase completado, puedas agarrar esto. Ah, tu pedido ha sido completado,

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** este es tu monto y demás. Bueno, eh, esto es okay. Eh, a ver qué cosita si movimos por acá. Ah, bueno, el orden, entonces se ordena por por coste. Ah, bueno,

**Cubakilos:** Una pregunta,

**Jhony Alexander Alvarez Vasquez:** están todas las

**Cubakilos:** ¿las las las giftcard de Cuba no tienen imagen?

### 00:15:10 {#00:15:10}

**Jhony Alexander Alvarez Vasquez:** eh todo le puse imagen, pero sí hay algunas que no están viniendo con imagen.

**Cubakilos:** es que no las manda la API

**Jhony Alexander Alvarez Vasquez:** Correcto. Es que sí, yo inclusive yo vi eso sin imagen. Yo,

**Cubakilos:** de

**Jhony Alexander Alvarez Vasquez:** ¿será que la imagen es muy grande o qué? Pero me metí al API y ahí estaba vacío y yo, "Ah, bueno, por eso ves, por eso es que no tiene no le llegaba

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** imagen.

**Cubakilos:** no habría y no hay forma de de hacerlas en una categoría aparte arriba í en el en el filtro intermedio.

**Jhony Alexander Alvarez Vasquez:** ¿Cómo agregarle la imagen

**Cubakilos:** en no hay en tipo de paquete que es en tipo de producto,

**Jhony Alexander Alvarez Vasquez:** manualmente?

**Cubakilos:** eh, o sea, como que haya uno porque ve combo más boss, pero no habría uno habría forma de decir lo que por ejemplo la ESport FCF25 Cuba, eh, esos son gift cards, ¿entiendes? está en saldo de pop fijo,

**Jhony Alexander Alvarez Vasquez:** Sí.

**Cubakilos:** pero bueno, me gustaría como que estaría más cómodo que que se queden que sea que estén en otro tipo de producto, ¿entiendes?

**Jhony Alexander Alvarez Vasquez:** Ah, okay.

### 00:16:13

**Jhony Alexander Alvarez Vasquez:** Habría que buscar un patrón.

**Cubakilos:** En otra categoría.

**Jhony Alexander Alvarez Vasquez:** Había que buscar un patrón de de cómo reconocerlo porque de momento yo en la en esta

**Cubakilos:** Claro, claro,

**Jhony Alexander Alvarez Vasquez:** columna, en esta tabla, puse todo la información que me trajera que me trajera.

**Cubakilos:** claro. Sí.

**Jhony Alexander Alvarez Vasquez:** Entonces, si no lo vemos acá podemos buscar estas

**Cubakilos:** Una cosa, poncel a ver una cosa en el buscador ahí.

**Jhony Alexander Alvarez Vasquez:** palabras.

**Cubakilos:** Vale, mira todo lo que sale. A ver, ya mira, eso está perfecto. Eso sería una categoría. Si te fijas,

**Jhony Alexander Alvarez Vasquez:** Vale.

**Cubakilos:** todo lo que dian el operador Cubacel, ¿no?

**Jhony Alexander Alvarez Vasquez:** Sí.

**Cubakilos:** Ya serían todos los productos de Cubacel y el resto ahí se pudiera meter un poco lo de e lo de las gift cards,

**Jhony Alexander Alvarez Vasquez:** Vale.

**Cubakilos:** porque por ejemplo si quitas cuacel y ahora ve a los tipos de paquete, ¿vale? Ve combo más voz más fijo, es también eso datos. Sería los combos. Ajá. Ahora ve a datos. Ve los combos. Ajá.

### 00:17:21

**Cubakilos:** Ve a saldo topop. Ahí está. Ya está. Yo

**Jhony Alexander Alvarez Vasquez:** tú me puedes decir el patrón,

**Cubakilos:** creo.

**Jhony Alexander Alvarez Vasquez:** o sea, que todo lo que diga band agrega otro otro otro tipo de paquete o todo lo que diga

**Cubakilos:** Mira,

**Jhony Alexander Alvarez Vasquez:** esto,

**Cubakilos:** ya te voy a decir cuál cuál puede ser la regla.

**Jhony Alexander Alvarez Vasquez:** ¿vale?

**Cubakilos:** en los eh pon un tipo que sea eh ver para ti,

**Jhony Alexander Alvarez Vasquez:** Tipo tipo

**Cubakilos:** pon un pon un tipo de de de producto que sea eh eh

**Jhony Alexander Alvarez Vasquez:** de

**Cubakilos:** tarjetas o gift cards.

**Jhony Alexander Alvarez Vasquez:** tarjetas

**Cubakilos:** Gift cards. Vale. Entonces, ¿cuál es el filtro para encontrarla mientras en el operador no diga cuando no diga Cubacel? Es una gift card. Fíjate, míralos ahí todos.

**Jhony Alexander Alvarez Vasquez:** Oh,

**Cubakilos:** Todos dicen el operador tiene la palabra, o sea, si contiene Cubacel, ya no es un gift card.

**Jhony Alexander Alvarez Vasquez:** si en operador.

**Cubakilos:** Ajá.

**Jhony Alexander Alvarez Vasquez:** Si en operador dice,

**Cubakilos:** La palabra no está la palabra cubacel es una

**Jhony Alexander Alvarez Vasquez:** ah, no dice Qacel

### 00:18:30

**Cubakilos:** giftcard.

**Jhony Alexander Alvarez Vasquez:** una

**Cubakilos:** Fíjate ahí para que tú veas. Ponlos todos. Ajá. Baja todo,

**Jhony Alexander Alvarez Vasquez:** en Ah, bueno, todos.

**Cubakilos:** baja. V App Legends,

**Jhony Alexander Alvarez Vasquez:** Ah, sí,

**Cubakilos:** ¿ves?

**Jhony Alexander Alvarez Vasquez:** razón.

**Cubakilos:** Ya. Por ejemplo, pon ahora pon ahí en paquetes encontrados, poncel. Ajá. Pon Cubacel, ¿ves? Ya te las quitó. Todo lo que queda son paquetes. Ya. O sea,

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** el filtro es mientras cuando no contengael en el operador, ya es un gift card.

**Jhony Alexander Alvarez Vasquez:** vale, pero eso sería para Cuba.

**Cubakilos:** Sí, para para Cuba en todos los tipos de paquete ahí.

**Jhony Alexander Alvarez Vasquez:** para

**Cubakilos:** Ya yo después más adelante cuando tenga,

**Jhony Alexander Alvarez Vasquez:** Cuba.

**Cubakilos:** o sea, países de Venezuela eso si detecto algo te lo voy mencionando, pero la urgencia ahora es

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** Cuba.

**Jhony Alexander Alvarez Vasquez:** esto mejor estos cambios es lo que yo te mencionaba esta mañana. Ya es que tú vayas viendo y me lo mandas,

### 00:19:28 {#00:19:28}

**Cubakilos:** Claro.

**Jhony Alexander Alvarez Vasquez:** me mandas un audio y yo pongo en estos casos ya pongo la en cosas muy puntuales, pongo la idea a correr y eso queda en una hora o menos.

**Cubakilos:** Por ejemplo, aplicar ese cambio. Ahora eso toma tiempo, ¿no?

**Jhony Alexander Alvarez Vasquez:** Este no,

**Cubakilos:** Igual

**Jhony Alexander Alvarez Vasquez:** este es muy esto como en media hora lo queda

**Cubakilos:** ya vale.

**Jhony Alexander Alvarez Vasquez:** bien.

**Cubakilos:** Bueno, seguimos

**Jhony Alexander Alvarez Vasquez:** Sí, sí, listo.

**Cubakilos:** entonces.

**Jhony Alexander Alvarez Vasquez:** Eh, estamos en catálogos. Buscar API. Uno cambió. Fue la orden de acá. Eh, si le doy doble clic, ¿qué cambié por aquí? Ah, bueno, aquí puse ah, el campo de utilidad para que lo guardes en tu producto. El nombre comercial también. Este ya lo tenemos que probar para que se envíe al front y a ver qué otra cosa aquí podríamos hacer. Bueno, bueno, también estado ordenando cositas de informativas y

**Cubakilos:** Sí,

**Jhony Alexander Alvarez Vasquez:** demás.

**Cubakilos:** pon defecto que no esté marcado el check de que que el cliente elija el monto.

### 00:20:30

**Cubakilos:** Exacto. Así que esté desmarcado por defecto. V. Tiene que estar desmarcada por

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** defecto.

**Jhony Alexander Alvarez Vasquez:** es que se marca por defecto a los que tienen la opción de

**Cubakilos:** Sí,

**Jhony Alexander Alvarez Vasquez:** rango.

**Cubakilos:** pero ahí lo que me preocupa ahí que voy a tener que yo sé que lo voy a tener que desmarcar porque eh, ¿cómo decir? Mira, te muestro por qué. Eh, entra Din, perdón, entra din con este porque para que tenga sentido tendrías que hacer

**Jhony Alexander Alvarez Vasquez:** Sí. Eh,

**Cubakilos:** esto. Voy a enviar recarga, ¿vale? En recarga ahora mismo.

**Jhony Alexander Alvarez Vasquez:** no estás escuchando mucho ruido de pronto. Ah, bueno, vale.

**Cubakilos:** No,

**Jhony Alexander Alvarez Vasquez:** Dijo, está les llegó un amiguito y están como en un

**Cubakilos:** no, no, no,

**Jhony Alexander Alvarez Vasquez:** garden. Listo.

**Cubakilos:** mira, ponlo No, ponga. Ajá. Pon PayP.

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** Ajá. Cuatro eh cco 5 402\.

**Jhony Alexander Alvarez Vasquez:** 402 A5

### 00:21:35 {#00:21:35}

**Cubakilos:** No, falta un cinco. 402 4709\. Vale, darle enter. Vale, darle a Vale, ves, mira, esto es lo que tú dices. Tienes el rango desde 5 hasta 51\. Pero, ¿qué es lo que va a pasar? Tú puedes ingresar en monto, pero el cliente va a tener que saber, por ejemplo, pon 22 €

**Jhony Alexander Alvarez Vasquez:** Este, este.

**Cubakilos:** 22, ¿ve? Eso te va a dar un un 486\. Entonces tendrías tú que estar devolviéndole al cliente ese parámetro que te muestra a mi porque, por ejemplo, si yo hago eso en lo que le voy a mandar al cliente está mal y es un y no le va a llegar lo promo que que tiene que llegarle. Por ejemplo, pon 22, ¿vale? 22 535\. Ahora te voy a decir algo. Dame un segundo para leerte el mail de la oferta. Eh, ti Jordi, vale, mira, si yo ahora mismo a un cliente le mando 22 € que son 500 CUP, que es lo que te aparece ahí, ¿verdad? con con 500 CUP la oferta que le llega son

**Jhony Alexander Alvarez Vasquez:** 53

### 00:22:49

**Cubakilos:** eh bueno, de hecho no tiene ninguna activa ahora mismo con 500 CP. No puede ser si normalmente siempre ahí. Bueno, hasta los otros días era que le llegaba eh internet ilimitado durante 10 días, pero por ejemplo si le pones 600 CUP, que es la promo ejemplo tienes que ponerle ahí 2640\. De hecho, está más barato. 600 CP han bajado el precio ahí porque es 600 es menos.

**Jhony Alexander Alvarez Vasquez:** M.

**Cubakilos:** 25\. V. Por eso te digo que yo tengo que por eso la edición porque yo ahora lo que tengo que hacer ahí es poner el número equivalente. Pon 25, por ejemplo. A ver, para que sea ve un poquito menos. 24\.

**Jhony Alexander Alvarez Vasquez:** Hm.

**Cubakilos:** Ahí ve. 2460\. No sé cuánto es. Déjame ver. Es que ellos no me lo ponen aquí. Esta este este cabrón no lo pone. Sí. 2640 2680 2480 que diga 2480\. Madre mía.

**Jhony Alexander Alvarez Vasquez:** Es

**Cubakilos:** Ven 65\. Ahí está. Entonces, para que yo le yo tengo que ponerle entonces para que el cliente reciba 600 CUP, le tengo que poner 2465 que le va a llegar.

### 00:24:19

**Cubakilos:** Entonces, el cliente, ¿cuál es beneficio que recibe? Va a recibir eh 600 CUP multiplicado el saldo por 10, o sea, que le van a llegar 6000 Cup de saldo en el teléfono más internet nocturno ilimitado. Entonces,

**Jhony Alexander Alvarez Vasquez:** Vale.

**Cubakilos:** pero el cliente no se puede poner a jugar con esos rangos, ¿entiendes?

**Jhony Alexander Alvarez Vasquez:** Sí, sí, sí.

**Cubakilos:** porque se va a pender. Entonces, por eso es que yo te decía que yo necesito que que este producto cuando vamos aquí al al producto

**Jhony Alexander Alvarez Vasquez:** lo dejas listo. Sí,

**Cubakilos:** en el en el pluin, yo puedo ejemplo lo puedo poner dos veces.

**Jhony Alexander Alvarez Vasquez:** mira.

**Cubakilos:** Yo lo puedo le tengo que editar el nombre comercial y les beneficio y entonces lo pongo por defecto que tiene que ser si quieres lo hacemos ahora mismo. Por ejemplo, vamos a editar uno que has hecho ese. No sé si ya tienes uno hecho para dejarlo listo. por ejemplo, de esa oferta ahora mismo que está

**Jhony Alexander Alvarez Vasquez:** Yo yo te soy sincero,

**Cubakilos:** activa.

**Jhony Alexander Alvarez Vasquez:** yo creo que sí estoy entendiendo eh todo este tema de las recarsas y demás, pero me siento un poco inseguro.

### 00:25:26

**Jhony Alexander Alvarez Vasquez:** Pero bueno, de la mano contigo. Ahí

**Cubakilos:** Vale, pon el saldo pope o haces hacer dato.

**Jhony Alexander Alvarez Vasquez:** vamos.

**Cubakilos:** Lo tienes ahí mismo. Vale. Ahí yo tengo que decirle, mira, ahora mismo hay una promo de que tengo que pagarle a DIN 26, no sé cuánto fue que dijimos que era 2465, no sé cuánto fue que pusimos ahí en

**Jhony Alexander Alvarez Vasquez:** 24 65\.

**Cubakilos:** 2465,

**Jhony Alexander Alvarez Vasquez:** Sí.

**Cubakilos:** ¿vale? Yo tengo que darle 2465 para que entonces la promo se va a llamar eh Multiplica tu saldo por 10 más internet nocturno,

**Jhony Alexander Alvarez Vasquez:** serían los beneficios que tú

**Cubakilos:** ¿vale? Pero igual le puedo poner un título de gancho inicial que sea cortigo que diga multiplica saldo por 10\.

**Jhony Alexander Alvarez Vasquez:** pondrías.

**Cubakilos:** Tu saldo por 10 o saldo por 10\. Multiplica saldo. Saldo. Ajá. Saldo X 10 más internet ilimitado. Internet nocturno, perdón. Ya,

**Jhony Alexander Alvarez Vasquez:** O sea,

**Cubakilos:** ahora,

**Jhony Alexander Alvarez Vasquez:** que en la noche pueden usarlo.

### 00:26:29

**Cubakilos:** ahora de 12 de la noche.

**Jhony Alexander Alvarez Vasquez:** Quiere decir que eso que en la noche lo pueden usar.

**Cubakilos:** Ahora lo ahora ve ahí viene el beneficio que lo que la descripción que tú la amplias un poco. Eh, reciben 6000 CP de saldo. 6000 CP de saldo más internet limitado de 12 a 7 am. A7 AM. Esa es la promo. De hecho, esa es la promo 26 2465, pero ellos tienen otra oferta que es un poco más cara, que es 750 CP. O sea, hay que poner en el mismo rango ese, subir más el precio para que llegue hasta los 750 CP y ahí recibirían, por ejemplo, 7500 pesos de saldo, o sea, multiplicas el saldo por 10 más lo mismo, más internet limitado, no sé qué, no sé qué cosa. Entonces, por eso ahí lo que yo hago es que este producto yo lo creo dos veces y lo

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** modifico dos veces para que uno sea de 600 y otro de 750,

**Jhony Alexander Alvarez Vasquez:** ven.

**Cubakilos:** ¿entiendes?

**Jhony Alexander Alvarez Vasquez:** He probado la opción de duplicar.

### 00:27:37 {#00:27:37}

**Jhony Alexander Alvarez Vasquez:** Creo que ya te debe servir. Ah, no, lo puse donde no era. Sol te lo espera. Ah, o espera, no mentiras que sí.

**Cubakilos:** No, en producto está bien,

**Jhony Alexander Alvarez Vasquez:** Ah, sí,

**Cubakilos:** ¿no?

**Jhony Alexander Alvarez Vasquez:** productos. Sí, sí, es

**Cubakilos:** Vale, ahí lo duplicas.

**Jhony Alexander Alvarez Vasquez:** productos.

**Cubakilos:** Por ejemplo, creo que hay uno abajo que tiene cero precio público, no sé.

**Jhony Alexander Alvarez Vasquez:** Este, multiplica tu saldo por 10\.

**Cubakilos:** ¿Ves? Por ejemplo, el Vale,

**Jhony Alexander Alvarez Vasquez:** Ah, cuando es cero precio al público,

**Cubakilos:** de hecho,

**Jhony Alexander Alvarez Vasquez:** él va a tomar el coste de Ding. No te preocupes. A ver,

**Cubakilos:** vale,

**Jhony Alexander Alvarez Vasquez:** está pensando. Sí, sí, está

**Cubakilos:** perfecto. Ahora, por ejemplo, ponle que el precio sea, no sé, ve allí a Adí.

**Jhony Alexander Alvarez Vasquez:** bien.

**Cubakilos:** Ajá. No sé, ahora tiene que ser como 26 € mira. ¡Buf\!

### 00:28:25

**Cubakilos:** Mucho más. 28\. Ah, no, eso va a ser como 30 € mira.

**Jhony Alexander Alvarez Vasquez:** ¿A dónde quieres llegar?

**Cubakilos:** a

**Jhony Alexander Alvarez Vasquez:** Ah,

**Cubakilos:** 750\.

**Jhony Alexander Alvarez Vasquez:** entonces vamos bien lejos tampoco.

**Cubakilos:** V. Míralo ahí. 31, 32, 31, ahí ve 30\. Ahora es 3080 la idea. Exacto.

**Jhony Alexander Alvarez Vasquez:** Sí.

**Cubakilos:** Eh, eh, 85, no sé. 8 Ahí está. Entonces, con ese precio, yo creo otro producto igual de con el mismo SKV. Tiene el mismo SKU, pero varía. Entonces, este producto va a ser el 3080, que el precio del cliente, no sé, va a ser, por ejemplo, 35 € digamos. el precio de venta del público,

**Jhony Alexander Alvarez Vasquez:** el el precio de Ah,

**Cubakilos:** por ejemplo, 35 ponle 35 decir ahora multiplica tu saldo más internet nocturno,

**Jhony Alexander Alvarez Vasquez:** ya.

**Cubakilos:** copia, quítale copia arriba. Ahora vamos a ver si funciona. Y abajo pones en vez de 6000 CP, pon 7500 CP.

### 00:29:34

**Cubakilos:** Siete. Pong un siete delante. Quítale el seis.

**Jhony Alexander Alvarez Vasquez:** Ah,

**Cubakilos:** Es 75,500.

**Jhony Alexander Alvarez Vasquez:** ya.

**Cubakilos:** Ya. Guardar product. Ahora elimina los otros productos para que quede limpio.

**Jhony Alexander Alvarez Vasquez:** Estos son los la lista de como que tenemos

**Cubakilos:** Los otros. Vale, borra los dos primeros.

**Jhony Alexander Alvarez Vasquez:** disponible.

**Cubakilos:** Creo que son los dos primeros los que nos sirven. Ya. Los últimos son los nuevos que creamos. Míralo ahí. Listo. De hecho,

**Jhony Alexander Alvarez Vasquez:** Ok.

**Cubakilos:** déjame verificar rápidamente en Cuba, en dime Cuba, cuáles son los productos que tienen activos. Y ya, mira, aquí 6000 reciben el 10 de mayo, 6000 30 días 7500 tienen el de 6000, el de 7500 que acabamos de hacer tienen uno de 10,000 pesos y uno de 12,500 pesos. Ellos crean los cuatro productos de la de la promo. Si vas a a esta landing, te digo, para que lo o sea, para que lo vayas, lo entiendas, ¿no?

### 00:30:42 {#00:30:42}

**Cubakilos:** En tiempo real. Mira, ve a la landing esta que te pasé por el mensaje. Ves, hay una promo nueva. Recarga por 10\. Desde solo el 10 de mayo van 6000 en adelante. O es por el día de las madres. Bueno, el día de las madres, sí. Sí, ahora es el día de las madres.

**Jhony Alexander Alvarez Vasquez:** ías este aviso y ya pondrías el short.

**Cubakilos:** Entonces, vienes aquí, ve el número y mira los paquetes. V, nosotros creamos los dos primeros, ¿ves? Pero ellos crearon dos más caros,

**Jhony Alexander Alvarez Vasquez:** Mm.

**Cubakilos:** ¿ves? Te ponen el recomendado, el primero que nosotros se lo podemos poner. Yo se lo puedo poner también.

**Jhony Alexander Alvarez Vasquez:** ¿Te gustaría que este paquete esté visible? ¿Ya? ¿Cierto?

**Cubakilos:** Bueno, vamos a hacerlo ahora y ver cómo queda ya el diseño, pero bueno, estamos haciendo el proceso

**Jhony Alexander Alvarez Vasquez:** Sí. sino que por defecto está móvil que vas a recargar o bueno,

**Cubakilos:** completo.

**Jhony Alexander Alvarez Vasquez:** la información que pongas acá y acá ingresa el número y después ya aparecen los lo que tiene

### 00:31:34

**Cubakilos:** Plan.

**Jhony Alexander Alvarez Vasquez:** disponible.

**Cubakilos:** Sí, a mí me gusta, a mí me gusta, creo que como lo hiciste tú, que es que primero pongas en móvil para que porque yo desde el punto de experiencia de usuario digo,

**Jhony Alexander Alvarez Vasquez:** Ah, bueno.

**Cubakilos:** enfócate en poner el número de teléfono y ya después, ah, mira, selecciona tu plan.

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, está bien, está bien.

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** Eso,

**Cubakilos:** entonces ya ya vale, ya tenemos los dos productos.

**Jhony Alexander Alvarez Vasquez:** eso lo vas pidiendo.

**Cubakilos:** Vale, vamos ahora a la landing, ¿no? Vale, la landing se va a llamar eh recarga recarga Cuba. Recarga de Cubacel Cuba. Perfecto.

**Jhony Alexander Alvarez Vasquez:** Correcto.

**Cubakilos:** Cuba Cuba, porque yo puedo crear una de Nauta, etcétera. Vale, el chcó es el mismo. Cuba ser Cuba. Vale, ahora el título del formulario. cuando lo vea, me imaginaré bien cómo decir el título de formulario, porque aquí yo tengo que jugar con con mi H1 y toda esa historia para el tema del SEO.

**Jhony Alexander Alvarez Vasquez:** Exacto.

**Cubakilos:** Ya elige, no sé, elige el paquete ahora lo después eso vería eso después se edita fácilmente, ¿verdad?

### 00:32:33

**Jhony Alexander Alvarez Vasquez:** Sí, por

**Cubakilos:** Vale, ya crear show code a seleccionar los dos productos.

**Jhony Alexander Alvarez Vasquez:** supuesto.

**Cubakilos:** No sé si se pueden poner en orden. Eso lo vemos después, ¿no? Creo que es el próximo paso.

**Jhony Alexander Alvarez Vasquez:** Ah, aquí también lo puedes acomodar con

**Cubakilos:** Vale, primero el de 24 y después de listo.

**Jhony Alexander Alvarez Vasquez:** esto.

**Cubakilos:** Crear show code de la landing. Perfecto. Ahora entiendo que hay que ir a Yorco Dinámico. Si puedes eliminar todos los demás para que ya me quede me quede limpio ese.

**Jhony Alexander Alvarez Vasquez:** Ah, sí, este ya es el tuyo.

**Cubakilos:** Ajá.

**Jhony Alexander Alvarez Vasquez:** Sí. Voy a poner aquí como

**Cubakilos:** Sí, para seleccionar varios.

**Jhony Alexander Alvarez Vasquez:** que

**Cubakilos:** Y está perfecto. Genial.

**Jhony Alexander Alvarez Vasquez:** seleccionar varios shortcuts para eliminar Vale.

**Cubakilos:** Perfecto. Porque si esto me sirve, hoy mismo lanzo la publicidad y lo empiezo a vender.

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** Eh, listo. Coger, copiar el chco y meterlo en una página.

**Jhony Alexander Alvarez Vasquez:** miremos si está activo estos dos. Esto te lo he intentado organizar.

### 00:33:57

**Jhony Alexander Alvarez Vasquez:** Olvidé este checkpo pasarlo para acá, pero bueno,

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** te lo paso. Eh,

**Cubakilos:** vale.

**Jhony Alexander Alvarez Vasquez:** aquí puedes filtrar ta ta ta.

**Cubakilos:** Está genial,

**Jhony Alexander Alvarez Vasquez:** Aquí puedes seguir cambiando esto.

**Cubakilos:** está

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** genial.

**Jhony Alexander Alvarez Vasquez:** dentro de la web puedes inspeccionar un poco y vas puedes modificar el CCS. Pues lo puedo agregar luego.

**Cubakilos:** Sí, eso después como meter el diseño ahí.

**Jhony Alexander Alvarez Vasquez:** Aá, eh,

**Cubakilos:** Ajá.

**Jhony Alexander Alvarez Vasquez:** páginas.

**Cubakilos:** Claro, porque al final esto no se crea como un producto de Wcommerce. No se maneja como un producto de verdad.

**Jhony Alexander Alvarez Vasquez:** No, no. Se maneja después que el cliente ya

**Cubakilos:** Claro, esa era mi duda al principio. Me acuerdo que Dame un segundo para cerrar bien la

**Jhony Alexander Alvarez Vasquez:** pague.

**Cubakilos:** puerta. Aspiradoras. Esa es una de mis dudas de cómo se manejaba, ¿eh?

**Jhony Alexander Alvarez Vasquez:** él lo que hace es crear un un producto eh como decir temporal y pero interno y lo agrega y lo agrega al carrito de

### 00:35:01 {#00:35:01}

**Cubakilos:** Sí, sí, porque está está el tema de Wcomermer con los productos y entonces está esto que también es un producto lo que

**Jhony Alexander Alvarez Vasquez:** cliente.

**Cubakilos:** intangible, eh, o sea, no físico, que ese yo también dije, bueno, creo un producto que no sea físico, pero bueno, no sé, es raro. Vale, metemos, publicamos.

**Jhony Alexander Alvarez Vasquez:** Estoy actualizando.

**Cubakilos:** Claro que ahí tendrías que cambiarle la visibilidad para yo poder verla.

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, que la contraseña es el mismo 23\.

**Cubakilos:** Ah, bueno, ya listo, ya.

**Jhony Alexander Alvarez Vasquez:** Que se me se me va se me indexa Google.

**Cubakilos:** Perfecto.

**Jhony Alexander Alvarez Vasquez:** Si.

**Cubakilos:** Sí, sí, sí.

**Jhony Alexander Alvarez Vasquez:** Entonces,

**Cubakilos:** No te

**Jhony Alexander Alvarez Vasquez:** ay, espera que no.

**Cubakilos:** preocupes.

**Jhony Alexander Alvarez Vasquez:** Mejor es abrirlo con incógnito. Te lo comparto también.

**Cubakilos:** Vale, para allá. Entonces, si todo eso yo entraría y comprar,

**Jhony Alexander Alvarez Vasquez:** No, no me está renderizando el la banderita ahí.

**Cubakilos:** ¿no? Eso te salía perfecto ayer.

**Jhony Alexander Alvarez Vasquez:** Sí,

### 00:36:24

**Cubakilos:** Eso que puede ser esa bobería.

**Jhony Alexander Alvarez Vasquez:** es

**Cubakilos:** Esa bobería que puede ser.

**Jhony Alexander Alvarez Vasquez:** que

**Cubakilos:** O sea, salía de lo más

**Jhony Alexander Alvarez Vasquez:** espérame que los min están haciendo mucho

**Cubakilos:** chulo.

**Jhony Alexander Alvarez Vasquez:** ruido. Le le bajo al bajo. Creo que ahora te escucho. Perfecto. que la urele puede ser la que esté teniendo el problema o qué me decías.

**Cubakilos:** Sí, la banderita que ayer te salía. ¿Qué podía ser lo que que ahora no salga?

**Jhony Alexander Alvarez Vasquez:** Ah, sí, no me está saliendo. Es algo de los estilos. Tengo que ir un poco más a fondo a ver que el CU se renderice bien y me lo noté también que el icono del Vale. Eh, pongo un número tuyo de prueba o

**Cubakilos:** Sí. Eh, yo tengo ahora, pero lo vas a hacer tú o lo probamos la compra

**Jhony Alexander Alvarez Vasquez:** e es pero

**Cubakilos:** completa.

**Jhony Alexander Alvarez Vasquez:** son veintitantos. ¿Cuánto era?

**Cubakilos:** Ah, verdad que sí, claro. Sí. No es lo mismo que que el tema este de Claro, porque ahí yo lo haría.

### 00:37:37 {#00:37:37}

**Jhony Alexander Alvarez Vasquez:** serían 20 algo

**Cubakilos:** Claro, porque yo lo hago, yo lo hago. Claro,

**Jhony Alexander Alvarez Vasquez:** de

**Cubakilos:** lo otro es hacer lo hago con la cuenta empresa, ¿eh? Vale, bueno, lo puedo hacer con la cuenta

**Jhony Alexander Alvarez Vasquez:** Bueno, yo puedo hacerlo aquí para que quede también en la llamada por si algo,

**Cubakilos:** empresa.

**Jhony Alexander Alvarez Vasquez:** pero eh pondría tu número si puedes hacer esa recarga.

**Cubakilos:** Sí, yo puedo poner el número de, por ejemplo, de mi madre que está en Cuba. Eh,

**Jhony Alexander Alvarez Vasquez:** Dale. ¿Qué número

**Cubakilos:** 5 2 3 7 12

**Jhony Alexander Alvarez Vasquez:** es?

**Cubakilos:** 66\. Vale, paquetes disponible. Entonces, elige los paquetes disponibles. Ahí tienes los dos. 24\. Fíjense que genial. Ahí te dice beneficio recibido, precio al público. ¡Uf\! Eso ahí al precio al público.

**Jhony Alexander Alvarez Vasquez:** Ay,

**Cubakilos:** Ahí sería ahí sería poner,

**Jhony Alexander Alvarez Vasquez:** sí, es verdad.

**Cubakilos:** por ejemplo, coste, no sé, o precio. Ajá.

**Jhony Alexander Alvarez Vasquez:** Déjame, yo abro otra otra acción aquí.

### 00:38:34

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** Front

**Cubakilos:** Ah, pero me gusta el diseño, me gusta cantidad. Lo veo super lo veo muy

**Jhony Alexander Alvarez Vasquez:** front y estoy haciendo otra otro grupito de notas.

**Cubakilos:** bien.

**Jhony Alexander Alvarez Vasquez:** Y ahora el front. Listo. En el front precio al público cambiamos por eh por precio, ¿cierto? Precio. ¿O qué me dijiste?

**Cubakilos:** Sí, sí.

**Jhony Alexander Alvarez Vasquez:** ¿Cuál es?

**Cubakilos:** Precio,

**Jhony Alexander Alvarez Vasquez:** Sí.

**Cubakilos:** precio.

**Jhony Alexander Alvarez Vasquez:** Eh, y notaste aquí que ya te trae la información de del producto

**Cubakilos:** E chulo ahí en negrito, le te pongo un gancho de venta, multiplica tu saldo por 10 más internet nocturno y después,

**Jhony Alexander Alvarez Vasquez:** y

**Cubakilos:** bueno, recibe 6,000 CP de saldo más internet limitado de las 12 a las 7 a. Listo, está perfecto. El operador va a ser Cuba.

**Jhony Alexander Alvarez Vasquez:** luego también te puedo crear esto con etiqueta de HTML si quieres ponerle

**Cubakilos:** Okay.

**Jhony Alexander Alvarez Vasquez:** negrita esto o alguna cosa así.

**Cubakilos:** Vale. Ahí lo que veo que pudiera hacer es que como que se estire y aproveche el ancho,

### 00:39:27

**Jhony Alexander Alvarez Vasquez:** Ah,

**Cubakilos:** ¿no?

**Jhony Alexander Alvarez Vasquez:** eh.

**Cubakilos:** Para que no digo digo el texto de abajo.

**Jhony Alexander Alvarez Vasquez:** Ah, okay.

**Cubakilos:** Es sí.

**Jhony Alexander Alvarez Vasquez:** Ese texto,

**Cubakilos:** No sé si es posible.

**Jhony Alexander Alvarez Vasquez:** sí, sí, sí. Esa es puro chatm. Ya. Entonces, descripción del producto.

**Cubakilos:** que aproveche el ancho para que sea más más chiquita la caja, ¿no?

**Jhony Alexander Alvarez Vasquez:** Me me toca me toca sacarlo del contenedor porque mire,

**Cubakilos:** Claro,

**Jhony Alexander Alvarez Vasquez:** dice que dos columnas,

**Cubakilos:** claro. Sí,

**Jhony Alexander Alvarez Vasquez:** pues yo voy a sacarlo del contenedor y abajo lo pongo ahí.

**Cubakilos:** contenedor. Claro.

**Jhony Alexander Alvarez Vasquez:** beneficios del producto. Eh,

**Cubakilos:** Ho.

**Jhony Alexander Alvarez Vasquez:** otro sacarlo de de la columna para aprovechar el ancho. Aprovechar el ancho. Listo. Y aquí también me salía la banderita, pero bueno,

**Cubakilos:** Sí, es ideal que salga también.

**Jhony Alexander Alvarez Vasquez:** ya verificar

**Cubakilos:** Está chulo así.

**Jhony Alexander Alvarez Vasquez:** también la bandera icono.

### 00:40:28

**Jhony Alexander Alvarez Vasquez:** Es que son iconos. Yo creo que voy a voy a poner mejor, voy a crearlo sin icono. Algunos navegadores no no muestran y otros

**Cubakilos:** Continuar.

**Jhony Alexander Alvarez Vasquez:** sí.

**Cubakilos:** Ajá. Listo. A ver, y ahora está de nuevo. Te hac como un resumen de nuevo, ¿no? Vale. Multiplica tu saldo,

**Jhony Alexander Alvarez Vasquez:** Mm.

**Cubakilos:** recibe tu 24 € el precio va ser Cuba, el número de de teléfono, ¿no? País Cuba, ahí ya no sé. Para tras confirmar pasarás al pago para completar tu solicitud de recarga. Ahí no sé si estaría bien ya quitar y solo dejar el número al que, o sea, no hace falta el país Cuba quizás, no sé. Yo por reducirle la información al cliente, o sea, operador que va a ser Cuba,

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** operador Cuba va ser Cuba. Sí, me sobra esa línea, me sobra ahí.

**Jhony Alexander Alvarez Vasquez:** eh, espérame que está creando la nota.

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** ¿Cuál te sobra?

**Cubakilos:** ahí me sobra operador en esa parte. Ya va directo al número a recargar.

### 00:41:28

**Cubakilos:** De hecho,

**Jhony Alexander Alvarez Vasquez:** Este

**Cubakilos:** yo pondría número y le pondría el número a recargar porque créeme que los clientes, o sea, el público objetivo este es no es informático, no me recarga él.

**Jhony Alexander Alvarez Vasquez:** entonces quito este.

**Cubakilos:** Sí,

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** yo creo que quitaría hasta el país también.

**Jhony Alexander Alvarez Vasquez:** ¿cuál otro?

**Cubakilos:** Me sobre el país, país, esa línea de país Cuba. Ya con la banderita arriba, ya se sabe, ya se ha dicho una pil.

**Jhony Alexander Alvarez Vasquez:** Ahí

**Cubakilos:** Okay, entré a tras confirmar. Pasarás a vas a preguntar. Okay. Proceder.

**Jhony Alexander Alvarez Vasquez:** y espérame que este estos botones

**Cubakilos:** Sí, están

**Jhony Alexander Alvarez Vasquez:** botones cambiar paquete y proceder al pago.

**Cubakilos:** ido.

**Jhony Alexander Alvarez Vasquez:** dentro del contenedoritar

**Cubakilos:** Bueno, ya esto ya esto es diseño de de CRO y cosas que por ejemplo cambiar el paquete,

**Jhony Alexander Alvarez Vasquez:** desb.

**Cubakilos:** el color yo lo pondría como un gris y el verde y el verde lo dejaría en el proceder al pago para que el cliente se fije en proceder al

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, sí.

**Cubakilos:** pago.

### 00:42:44 {#00:42:44}

**Cubakilos:** Listo. Preparando tu recarga.

**Jhony Alexander Alvarez Vasquez:** Ahí estoy. Bueno, es que eso fue de ayer. Todavía tengo que verificar este paso porque ahí me confirma.

**Cubakilos:** Vale, vamos a

**Jhony Alexander Alvarez Vasquez:** A ver, Google Analytics sigue.

**Cubakilos:** ver.

**Jhony Alexander Alvarez Vasquez:** Eso es eso es lo de siempre que salen muchas veces.

**Cubakilos:** Si tengo que actualizar todo eso también.

**Jhony Alexander Alvarez Vasquez:** Esto es del Facebook. ¿Tú tienes un píxel de pronto? ¿Habías intentado poner pixel de Facebook?

**Cubakilos:** Sí, lo tengo, lo tengo activo.

**Jhony Alexander Alvarez Vasquez:** Aquí, aquí sale error que no, pero bueno,

**Cubakilos:** A ver.

**Jhony Alexander Alvarez Vasquez:** a veces sale error, pero está dentro de lo normal porque él está como que diciendo, "No, aquí no tengo ninguna referencia de publicidad o algo así porque está la parte donde tú haces publicidad y Facebook dice, "Ah, si compraron o no compraron y también para Google,

**Cubakilos:** Claro. Sí.

**Jhony Alexander Alvarez Vasquez:** pero no veo nada mío." Ah, bueno, de pronto este. Ah, que no está logueado. Está bien. Y esto es más Google Analytics.

### 00:43:55

**Jhony Alexander Alvarez Vasquez:** Y esto si es Wcommerce. Ah, creo que ya lo había visto y no era ningún error. Vale, acá vamos a hacer prueba. Bueno, ahora el tuyo.

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** Y este sería tu número. ¿Cómo es tu número?

**Cubakilos:** Eh, vale. Se uf 622 53, o sea, 36\. Ajá.

**Jhony Alexander Alvarez Vasquez:** a 36 y pongamos el correo

**Cubakilos:** 03\.

**Jhony Alexander Alvarez Vasquez:** de una

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** vez de operaciones. Listo. Podemos hacer esta recarga

**Cubakilos:** Eh, sí. Eh, vale. Eh, podemos hacerlo con la tarjeta de Claro. No, sí.

**Jhony Alexander Alvarez Vasquez:** con tarjeta

**Cubakilos:** No, no,

**Jhony Alexander Alvarez Vasquez:** mejor o

**Cubakilos:** porque o sea, yo pondría tarjeta mía de empresa, pero bueno, soy

**Jhony Alexander Alvarez Vasquez:** con viso.

**Cubakilos:** vale

**Jhony Alexander Alvarez Vasquez:** Tú me dices cuál de de las dos. Sí, sí, pues si si no eh eh

**Cubakilos:** un segundo. Sí, sí.

**Jhony Alexander Alvarez Vasquez:** podemos podemos que hacer un buscar un paquete

### 00:45:31 {#00:45:31}

**Cubakilos:** Dame un segundo.

**Jhony Alexander Alvarez Vasquez:** menor

**Cubakilos:** Vale, no, lo que me pasa es el link para hacerla yo del lado acá y ya que ya me lo pasaste, ¿no?

**Jhony Alexander Alvarez Vasquez:** ah para hacerlo

**Cubakilos:** Claro.

**Jhony Alexander Alvarez Vasquez:** con

**Cubakilos:** Sí, con la tarjet con la tarjeta de empresa también para Bueno, ahí sí tengo que poner los datos y eso. Vale. Me dijiste que es contraseña 1 2 3\. No.

**Jhony Alexander Alvarez Vasquez:** admin 1 2

**Cubakilos:** Ah, admin 1 2 3\.

**Jhony Alexander Alvarez Vasquez:** 3

**Cubakilos:** Dame un segundo, déjame eh dame un segundo, déjame, o sea, déjame desloguearme.

**Jhony Alexander Alvarez Vasquez:** es mejor o como incógnito.

**Cubakilos:** No sé dónde estás. salir conectar.

**Jhony Alexander Alvarez Vasquez:** antes de darle eh realizar pedido o pagar, eh me dices para abrir los logs y todo

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** eso.

**Cubakilos:** un segundo, eh, comparto pantalla un momento,

**Jhony Alexander Alvarez Vasquez:** también eso que que he grabado

**Cubakilos:** ¿eh?

**Jhony Alexander Alvarez Vasquez:** no me está cargando verificar.

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** Es que cambi entre tarjeta y Bison y a veces se queda cargando más de la cuenta.

### 00:47:01

**Cubakilos:** Listo. Admin. 1 2 3\. Ahí vamos. Vale, aquí lo tengo. V sale igual sin el país.

**Jhony Alexander Alvarez Vasquez:** La banderita.

**Cubakilos:** Eh, que aquí te diría estaría bien que mientras, por ejemplo, si en los paquetes seleccionados, o sea, sería la regla, hay solo un todo corresponde a un solo país,

**Jhony Alexander Alvarez Vasquez:** No es necesario

**Cubakilos:** no es necesario que esto sea clicable y que tengas el pop.

**Jhony Alexander Alvarez Vasquez:** eso.

**Cubakilos:** Esto es necesario cuando hay más de un país dentro de los productos elegidos.

**Jhony Alexander Alvarez Vasquez:** Sí, creo que eso también lo había anotado.

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** Ya lo voy a a poner de tarea.

**Cubakilos:** 2 3 7 12

**Jhony Alexander Alvarez Vasquez:** No, no,

**Cubakilos:** 66\.

**Jhony Alexander Alvarez Vasquez:** no. Habilitar modal. Habilitar modal elegir país es fácil con una función de

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** HTML.

**Cubakilos:** aquí le pondría, como ya esto tiene la banderita de cubo aquí adelante, aquí le pondría número a recargar este texto.

**Jhony Alexander Alvarez Vasquez:** Listo.

**Cubakilos:** Número a recargar para que diga, "Ah, mira, este es el número que voy a recargar." Ya vale.

### 00:48:09

**Jhony Alexander Alvarez Vasquez:** Ah,

**Cubakilos:** Ahora selecciona los paquetes disponibles para este para este número. Bueno, aquí no sería para este número. Paquetes disponibles.

**Jhony Alexander Alvarez Vasquez:** un segundo.

**Cubakilos:** Eh,

**Jhony Alexander Alvarez Vasquez:** Ahí encima de elige país. Elige un paquete. Elige un paquete.

**Cubakilos:** selecciona,

**Jhony Alexander Alvarez Vasquez:** Eh, ¿qué pongo?

**Cubakilos:** selecciona un eh el número,

**Jhony Alexander Alvarez Vasquez:** ¿Qué pongo en vez de Cuba?

**Cubakilos:** o sea, este número a recargar. Número a recargar.

**Jhony Alexander Alvarez Vasquez:** Pero es el mismo. Ah,

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** ya.

**Cubakilos:** O sea,

**Jhony Alexander Alvarez Vasquez:** que diga eso. Que diga

**Cubakilos:** y exacto. Él va a ver a 5 3\.

**Jhony Alexander Alvarez Vasquez:** eso.

**Cubakilos:** Este es el número a recargar.

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** Vale, selecciono el paquete.

**Jhony Alexander Alvarez Vasquez:** vamos a

**Cubakilos:** Mira, aquí para hacerlo más corto quitarle cuba.

**Jhony Alexander Alvarez Vasquez:** E,

**Cubakilos:** Cer

**Jhony Alexander Alvarez Vasquez:** espérame.

**Cubakilos:** cuba.

**Jhony Alexander Alvarez Vasquez:** Puedes compartir toda la pantalla que esa parte sí no la veo porque se salió como que aún modal

### 00:49:10 {#00:49:10}

**Cubakilos:** Dame un segundo.

**Jhony Alexander Alvarez Vasquez:** o

**Cubakilos:** completa. Eh, ¿esto

**Jhony Alexander Alvarez Vasquez:** uno termina cansadísimo ya el sábado domingo,

**Cubakilos:** qué?

**Jhony Alexander Alvarez Vasquez:** pero es que se trabaja también sin tanto mensaje encima de los clientes. Tantos clientes. Ahora los de Colombia también nos escriben mucho entre semanas.

**Cubakilos:** Entonces, esto ya lo ves,

**Jhony Alexander Alvarez Vasquez:** Ahora sí.

**Cubakilos:** ¿vale?

**Jhony Alexander Alvarez Vasquez:** Mm.

**Cubakilos:** Aquí quitar Cuba, hacer Cuba este campo, quitarlo,

**Jhony Alexander Alvarez Vasquez:** Eh, selecciona un

**Cubakilos:** ¿eh? O sea, te queda multiplica tu saldo más internet,

**Jhony Alexander Alvarez Vasquez:** paquete.

**Cubakilos:** o sea, el título con el precio, pero esto de Cuba Cuba a sobra y así lo hacemos más cortico.

**Jhony Alexander Alvarez Vasquez:** Listo,

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** anotado.

**Cubakilos:** coge 2465\. Ya esto aquí lo vimos. Esto es cambiar aquí, ¿vale? La banderita chula. Operador Cuba cuba. Está bien. Vale, seguimos dando disponibilidad.

**Jhony Alexander Alvarez Vasquez:** tenemos

**Cubakilos:** Vale, listo. Edificio aquí.

### 00:50:27

**Cubakilos:** Quitar operador Cuba cerca. Quitar el país. Ya está bien. Vale. Entré a proceder al pago.

**Jhony Alexander Alvarez Vasquez:** organizar

**Cubakilos:** Preparando recarga.

**Jhony Alexander Alvarez Vasquez:** todo.

**Cubakilos:** Listo. Vale. Número beneficiario. Este número. Perfecto. Ahí metió un cambio de 29 para 24\.

**Jhony Alexander Alvarez Vasquez:** Es que hay un tema que tienes en tu página de creo que es algo de los impuestos o que yo ahí lo tengo sanitizando, pues que que sea el valor exacto. Eh, creo que si estuviera en un producto normal quedaría en 29\.

**Cubakilos:** Claro,

**Jhony Alexander Alvarez Vasquez:** Eso que me

**Cubakilos:** vale.

**Jhony Alexander Alvarez Vasquez:** lo dejaba adicional y luego yo agregué ahí para que me deje solo el valor y por eso carga un

**Cubakilos:** Eh,

**Jhony Alexander Alvarez Vasquez:** poquito porque eso lo trae de la de la pasarela, ¿no? me me vi una opción más más rápida de que cargue no cargue tan lento, sino que ahora me pasó, bueno, ya lo puse ahí para verificar. pasarelas, cambio. Se queda cargando a veces.

### 00:51:52

**Jhony Alexander Alvarez Vasquez:** ¿Qué pasé a tarjeta y me cargó y se quedó cargando en un momento, sino que como ya depende mucho del servidor de WordPress?

**Cubakilos:** Vale, aquí por qué sería por

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, eh,

**Cubakilos:** uno

**Jhony Alexander Alvarez Vasquez:** al principio estaba yo eh manejándolo en un de una manera de que pudiéramos tener varios paquetes, pero luego no lo vi viable. Eso,

**Cubakilos:** claro.

**Jhony Alexander Alvarez Vasquez:** eso lo quito.

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** Eso también es front, pero de la

**Cubakilos:** aquí no sé,

**Jhony Alexander Alvarez Vasquez:** pasarela

**Cubakilos:** aquí estaría bien. No sé si se sobreentiende. Bueno, sí, como que tu teléfono. Listo.

**Jhony Alexander Alvarez Vasquez:** será un paquete que en la izquierda.

**Cubakilos:** Entonces, ya sería marcar aquí,

**Jhony Alexander Alvarez Vasquez:** ¿Qué? Perdón.

**Cubakilos:** ¿eh? No, no, ya después lo veo porque aquí sería como que tu teléfono, o sea, no el teléfono mismo de Cuba, sino el tuyo.

**Jhony Alexander Alvarez Vasquez:** Ah, que sea más que indique más fácil eso en

**Cubakilos:** Quizá quizá.

**Jhony Alexander Alvarez Vasquez:** campo teléfono del checkout.

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** Poner tu basta así con tu teléfono.

### 00:53:06

**Jhony Alexander Alvarez Vasquez:** Tu teléfono y número de beneficios. Sí, yo creo que con eso tu teléfono va a ser más descriptivo.

**Cubakilos:** Sí, tu tu sí tu nombre, tu apellido, o sea, como tus datos, no los del beneficiario. Eh, vale. Entonces acá ya voy a poner la tarjeta.

**Jhony Alexander Alvarez Vasquez:** Okay,

**Cubakilos:** Voy a parar la el compartir de pantalla y voy al

**Jhony Alexander Alvarez Vasquez:** dale.

**Cubakilos:** pago.

**Jhony Alexander Alvarez Vasquez:** Para que no quede grabado. Y para quitarla justo el botón

**Cubakilos:** Ajá. ¿Qué tengo que hacer?

**Jhony Alexander Alvarez Vasquez:** que estaba como azul.

**Cubakilos:** Ah, sí,

**Jhony Alexander Alvarez Vasquez:** ahí al lado del rojo es y

**Cubakilos:** sí, sí. ¿Y cómo cómo tú ves los?

**Jhony Alexander Alvarez Vasquez:** no cuando pongas los datos e no debería salirte o te queda la información o espérame, yo yo abro aquí rápido registros.

**Cubakilos:** Estoy poniendo los datos.

**Jhony Alexander Alvarez Vasquez:** registros. El último registro es del esta

**Cubakilos:** me avisa. va a realizar

**Jhony Alexander Alvarez Vasquez:** recarga es después del

**Cubakilos:** pedido.

**Jhony Alexander Alvarez Vasquez:** Ya. Registros.

**Cubakilos:** me dices y le

### 00:54:21 {#00:54:21}

**Jhony Alexander Alvarez Vasquez:** Esta recarga es demasiado importante. Me das un poco más.

**Cubakilos:** doy.

**Jhony Alexander Alvarez Vasquez:** Yo me meto a al a tu hosting y también veo los registros de log de C. Aquí los Eso está tú lo tienes con Lucu host. Aquí está

**Cubakilos:** Ok.

**Jhony Alexander Alvarez Vasquez:** eh servidores administrar. Bueno, que quede guardado de todas maneras y nos vamos a archivos. Hay un plugin. Eso también te voy a preguntar que está dejando demasiados registros. No vi cuál era exactamente, pero no sé si tú estás debugando alguna cosa o Ah,

**Cubakilos:** Yo no, así que dime cuas para si eso

**Jhony Alexander Alvarez Vasquez:** vale.

**Cubakilos:** quitarlo.

**Jhony Alexander Alvarez Vasquez:** Eh, mira, 45 megas y esto suele tener bytes.

**Cubakilos:** Claro, un

**Jhony Alexander Alvarez Vasquez:** Entonces, no la eh,

**Cubakilos:** montón.

**Jhony Alexander Alvarez Vasquez:** cuando entré la primera vez tenía giga y media y yo, ¿qué qué estaba haciendo aquí? Ah, no, ya no me deja. Yo voy a voy a borrar esta. Vémoslo en la papelera por si algo y lo creo de nuevo y ahí ya me guarda lo de

### 00:55:55 {#00:55:55}

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** ahora.

**Cubakilos:** le doy entonces a realizar pedido.

**Jhony Alexander Alvarez Vasquez:** Eh, a ver si no me falta más. Estoy aquí en los registros. Ya limpiamos el registro del servidor y sí, eh, abre tu aquí en inspeccionar como lo estoy haciendo.

**Cubakilos:** Vale, clic derecho. Invencional.

**Jhony Alexander Alvarez Vasquez:** Vas a consola, no sé si te sale

**Cubakilos:** Un segundo. A ver,

**Jhony Alexander Alvarez Vasquez:** como

**Cubakilos:** estoy en style layout. Uy,

**Jhony Alexander Alvarez Vasquez:** que usabas tú Google Chrome o Ed.

**Cubakilos:** console. Míralo aquí.

**Jhony Alexander Alvarez Vasquez:** Ah, sale, perfecto.

**Cubakilos:** Y ya dime dónde.

**Jhony Alexander Alvarez Vasquez:** Dale aquí a este botoncito que es de clear, perdón. limpiar

**Cubakilos:** Ajá.

**Jhony Alexander Alvarez Vasquez:** consola

**Cubakilos:** Eh, uy, espérate que yo estoy en yo estoy en en Microsoft. Eh, no,

**Jhony Alexander Alvarez Vasquez:** y este también es o clic derecho

**Cubakilos:** no me aparece ese botón. Espérate, es que estoy como style computer lado y

**Jhony Alexander Alvarez Vasquez:** y o clic derecho y clear consola también te sale.

**Cubakilos:** listening

### 00:56:54

**Jhony Alexander Alvarez Vasquez:** Le das clic derecho y da clic consola porque como te car te cargan otras

**Cubakilos:** ahora.

**Jhony Alexander Alvarez Vasquez:** cosas para que no no cargue algo

**Cubakilos:** Ahora sí. Míralo aquí.

**Jhony Alexander Alvarez Vasquez:** diferente.

**Cubakilos:** Click consola. Ya lo tengo. Ya está vacío.

**Jhony Alexander Alvarez Vasquez:** Perfecto. Ahora sí haz la recarga.

**Cubakilos:** Vale, está vacía la consola. Ya puse los datos. Realizar todo el número. Tracking prevention bloque access to storage for URL. Ese es el primer de content. Un C type error. client contest Fecutation parámetro

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** 1 prevention for checkout session.

**Jhony Alexander Alvarez Vasquez:** Ah.

**Cubakilos:** Deja ver porque está P molly. Hm. Vale, pero aquí mm aquí hay aquí hay un pequeño, o sea, ya se hizo la compra, te comparto pantalla, ¿no? Pero ahí hay un pequeño error o creo que no sé si es un error,

**Jhony Alexander Alvarez Vasquez:** Vale.

**Cubakilos:** pero no se activa una cosa que normalmente en España se activa y es obligatorio, que es el 3D Secure,

**Jhony Alexander Alvarez Vasquez:** Okay.

**Cubakilos:** ¿sabes?

### 00:58:11

**Cubakilos:** No, a mí me debería haber permitido validado en mi aplicación de de Kaicha validar la compra,

**Jhony Alexander Alvarez Vasquez:** Vendemos

**Cubakilos:** ¿sabes? Dame un segundo. Vale, lo ves todo

**Jhony Alexander Alvarez Vasquez:** eh clic derecho en en inspeccionar y en cualquier parte de ahí donde salen todo y le das copiar. Es copy console.

**Cubakilos:** vale de la llamada.

**Jhony Alexander Alvarez Vasquez:** Me lo puedes mandar aquí al al chat.

**Cubakilos:** O ahí lo copias,

**Jhony Alexander Alvarez Vasquez:** Sí, sí,

**Cubakilos:** ¿no?

**Jhony Alexander Alvarez Vasquez:** yo lo copié de la llamada. Perfecto. Vale.

**Cubakilos:** Todo vale, está todo ahí.

**Jhony Alexander Alvarez Vasquez:** Y al parecer no llegó confirmación

**Cubakilos:** pedido contiene operaciones pendientes. No repitas la compra mientras el estado siga submite o pending,

**Jhony Alexander Alvarez Vasquez:** del

**Cubakilos:** el sistema seguirá conciliando según la política configurada. Esto cierra la consola.

**Jhony Alexander Alvarez Vasquez:** Sí, ahora ya la puedesar.

**Cubakilos:** Ven acá.

**Jhony Alexander Alvarez Vasquez:** Abre,

**Cubakilos:** Y esto no te hace falta,

**Jhony Alexander Alvarez Vasquez:** dale, guardar.

**Cubakilos:** no te hace falta lo que dice aquí en tracking prevention.

**Jhony Alexander Alvarez Vasquez:** Eh, también me muestra lo que está ahí adentro,

### 00:59:11 {#00:59:11}

**Cubakilos:** abrirlo.

**Jhony Alexander Alvarez Vasquez:** aunque ese prevention access es lo que el navegador hace para que otras otra gente no no te como encriptar la operación más o

**Cubakilos:** Ya vale.

**Jhony Alexander Alvarez Vasquez:** menos. Eh, lo de ese get me puede servir,

**Cubakilos:** Ok.

**Jhony Alexander Alvarez Vasquez:** aunque ahí ya lo estoy viendo que es algo de es algo de la tienda y no del plugin. Es como está validando como que el usuario que esté registrado y no está registrado, entonces sale ese sin autorización. Y este no sé qué es, pero tampoco me hace referencia. Ah, sí, es lo mismo. Es lo mismo de de que no está conectado. Sí, sí, sí. Lo mismo de arriba y los primeros te salieron

**Cubakilos:** Vale, seguimos.

**Jhony Alexander Alvarez Vasquez:** este este sí este sí puede que sea del plugin.

**Cubakilos:** Order receive 1 2 3 order.

**Jhony Alexander Alvarez Vasquez:** Este sí puede que sea el plugin.

**Cubakilos:** Esto es con la

**Jhony Alexander Alvarez Vasquez:** Ah, no, no, no, no. Es es de la speed.

**Cubakilos:** IP.

**Jhony Alexander Alvarez Vasquez:** Entonces ahí que intentó limpiar el caché y lo bloqueó. Ah, bueno, no hay ningún error del plugin, aparentemente.

### 01:00:24 {#01:00:24}

**Jhony Alexander Alvarez Vasquez:** De todas maneras, lo que me mandaste eso todo sale ahí copiado y yo lo escaneo ahí con la guía por ciertas cosas.

**Cubakilos:** Vale, entonces cierro.

**Jhony Alexander Alvarez Vasquez:** Dale,

**Cubakilos:** Aquí se ve opin carga paquete sal por 10 importe

**Jhony Alexander Alvarez Vasquez:** guardar. Mm, ¿sabes que me puedes ayudar con esto?

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** Darle clic derecho y guardar página en cualquier lado y guardar. ¿Cómo? Eso arriba. Esa página completa, web completa. Eh, mentiras, dale solo un archivo ahí en tipo página web, archivo único. Eso, eso. Si ese me lo envías también me va me va a dar bastantes

**Cubakilos:** Esto lo copio,

**Jhony Alexander Alvarez Vasquez:** datos.

**Cubakilos:** lo mando por la por aquí mensaje. Se podrá hacer.

**Jhony Alexander Alvarez Vasquez:** Creo que ya ahí toca en Google Chat. Sí, toca en Google Chat.

**Cubakilos:** para allá un

**Jhony Alexander Alvarez Vasquez:** A veces, a veces puede que todo haya salido bien,

**Cubakilos:** segundo.

**Jhony Alexander Alvarez Vasquez:** pero toda esta información nos ayuda a optimizar cositas. Eso.

### 01:01:28 {#01:01:28}

**Jhony Alexander Alvarez Vasquez:** Muy bien.

**Cubakilos:** Listo. Entonces

**Jhony Alexander Alvarez Vasquez:** Y guardar PDF por a ver si me sale igual.

**Cubakilos:** acá

**Jhony Alexander Alvarez Vasquez:** Ah, baja, baja un poco a ver si de pronto ah, se me fue para la segunda imagen.

**Cubakilos:** si te creo tres

**Jhony Alexander Alvarez Vasquez:** Sí, sí,

**Cubakilos:** páginas

**Jhony Alexander Alvarez Vasquez:** tengo que dejarle solo eso en la primera. Probablemente se me fue ahí el Correcto.

**Cubakilos:** aquí están procesando. Vale,

**Jhony Alexander Alvarez Vasquez:** Ahí toca.

**Cubakilos:** esto esto lo cierro o vale cerrar.

**Jhony Alexander Alvarez Vasquez:** Sí, ya, ya cierra es

**Cubakilos:** No,

**Jhony Alexander Alvarez Vasquez:** cerrar.

**Cubakilos:** vale. Número de pedido. Tarjeta total. Ajá. Producto. Ta ta ta ta ta. Recarga internacional. Guaquilo. Vendedor.

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, eso también era un cambio que me pediste ayer.

**Cubakilos:** Y

**Jhony Alexander Alvarez Vasquez:** Sí,

**Cubakilos:** factura.

**Jhony Alexander Alvarez Vasquez:** las acciones también las quité.

**Cubakilos:** Ver. Resumen de compra. Aquí está. Vale,

**Jhony Alexander Alvarez Vasquez:** es lo mismo.

### 01:02:25 {#01:02:25}

**Cubakilos:** aquí. Entonces, ahora no sé si se envió la recarga o no.

**Jhony Alexander Alvarez Vasquez:** Vamos a tu connect y yo también voy a mirar los

**Cubakilos:** Vale, dame un segundo.

**Jhony Alexander Alvarez Vasquez:** registros.

**Cubakilos:** El lo Sí.

**Jhony Alexander Alvarez Vasquez:** Esto del debug es un caso completo. Connect. Y vamos a actualizar. La última fue 1352\. Registros. Según esto me dice que sí. Pero me faltó algo solo en

**Cubakilos:** Un segundo, porfa.

**Jhony Alexander Alvarez Vasquez:** el

**Cubakilos:** Repíteme que había había salido un segundo.

**Jhony Alexander Alvarez Vasquez:** perdón.

**Cubakilos:** Había salido un segundo.

**Jhony Alexander Alvarez Vasquez:** Ah, no,

**Cubakilos:** ¿Qué me estabas diciendo?

**Jhony Alexander Alvarez Vasquez:** esto aquí ya hablando en voz pensando en voz alta, pero eh sí te estaba diciendo también que aparentemente sí se hizo la recarga. Eh eh correo, correo enviado también, pero no estoy seguro que si te haya llegado porque aquí estoy utilizando el el de WordPress.

**Cubakilos:** Vale, me dio que tengo un nuevo pedido.

**Jhony Alexander Alvarez Vasquez:** Aquí dice que sí fue aceptada. Ay, me pica la espalda.

**Cubakilos:** Vale, a mí me cuak hemos recibido tu pedido

### 01:04:18

**Jhony Alexander Alvarez Vasquez:** Uy, espera, ya me tu correo.

**Cubakilos:** producto.

**Jhony Alexander Alvarez Vasquez:** A ver, gracias. Ah,

**Cubakilos:** Claro,

**Jhony Alexander Alvarez Vasquez:** bueno,

**Cubakilos:** que editar.

**Jhony Alexander Alvarez Vasquez:** sí.

**Cubakilos:** Claro, habría que evitar eso de la factura esa que yo hago con un plugin, pero bueno, esto

**Jhony Alexander Alvarez Vasquez:** Ah, eso también se me pasó preguntarte ayer, eh,

**Cubakilos:** es

**Jhony Alexander Alvarez Vasquez:** hay un tema ahí de de ese plugin, sino que es ese juego de que yo te bloqueo un plugin que de pronto no sea necesario, pero más fácil es configurarlo, sino que el plugin ya que pusiste no conozco tanto.

**Cubakilos:** vale, aquí recibimos la

**Jhony Alexander Alvarez Vasquez:** Entonces,

**Cubakilos:** Eh, crédito claro.

**Jhony Alexander Alvarez Vasquez:** y el la transacción,

**Cubakilos:** ¿Qué pasó?

**Jhony Alexander Alvarez Vasquez:** el ID para el seguimiento.

**Cubakilos:** Eh, para el cliente,

**Jhony Alexander Alvarez Vasquez:** Sí.

**Cubakilos:** ¿no?

**Jhony Alexander Alvarez Vasquez:** Eh, empecemos desde la Bueno,

**Cubakilos:** Sí. Mira, aquí entro. Vamos al historial.

**Jhony Alexander Alvarez Vasquez:** desde

**Cubakilos:** Mirale. Aquí hecha.

**Jhony Alexander Alvarez Vasquez:** ahí se vale. Y te iba a preguntar, eh, ah, tienes todavía la página abierta del checkout de de la finalizada de compra aquí.

### 01:05:41 {#01:05:41}

**Jhony Alexander Alvarez Vasquez:** Eh, ábreme otra vez el PDF o abajo la resumen. Qué raro, no me salió.

**Cubakilos:** Y no está el ID. Sí.

**Jhony Alexander Alvarez Vasquez:** No está en sí.

**Cubakilos:** Y aquí el ID es, ¿ves? Este está el ID transacción y a la vez está el API transfer ref, que es lo que aquí está chulo es esto. Mira, si veo aquí a detalle y D.

**Jhony Alexander Alvarez Vasquez:** Algo que te diga que eso fue a través de la Pierre,

**Cubakilos:** Uy, uy, uy. Bate no lo hice. Monto recibido 560\. Candela tiene que ser 600\. No puede ser.

**Jhony Alexander Alvarez Vasquez:** que mirar ahí a ver qué promoción está.

**Cubakilos:** c\*\*\*, la jodí.

**Jhony Alexander Alvarez Vasquez:** Tú tenías en otro

**Cubakilos:** O sea, a ti te sale otro número diferente a mí, ¿no?

**Jhony Alexander Alvarez Vasquez:** número de de ¿Qué? De transacción.

**Cubakilos:** De coste. Ver.

**Jhony Alexander Alvarez Vasquez:** Ah, ya ya. Esp.

**Cubakilos:** Uh, 26\.

**Jhony Alexander Alvarez Vasquez:** Tú pusiste pagar a Adin

**Cubakilos:** Es que es 2640\.

### 01:07:18

**Jhony Alexander Alvarez Vasquez:** 245\.

**Cubakilos:** Madre mía.

**Jhony Alexander Alvarez Vasquez:** ¿Y cuánto fue que se hizo efectivo el real?

**Cubakilos:** 2465 y pone 500 y pico, pero si no llegas a los 600 pesos no le aplica el bono. O sea, que lo que le mandé a mi mamá fue 500 pesos de salde, ¿no?

**Jhony Alexander Alvarez Vasquez:** Pero el error,

**Cubakilos:** A ver,

**Jhony Alexander Alvarez Vasquez:** o sea, estoy intentando entender ese fue por

**Cubakilos:** parece cuando tú viste que estuvimos probando en Tudin cuando poníamos este

**Jhony Alexander Alvarez Vasquez:** Sí.

**Cubakilos:** monto los 600 pesos te aparecía más barato, te aparecía en 24 o 60 creo que fue, ¿ves? Y a mí no, a mí me sale efectivo en los 2640 como el resto.

**Jhony Alexander Alvarez Vasquez:** Tenemos que verificarlo

**Cubakilos:** Entonces,

**Jhony Alexander Alvarez Vasquez:** ahí.

**Cubakilos:** claro que entonces por me doy cuenta, pero cuando vengo aquí veo que dice, "Ve, mira, dice tipo de producto, monto enviado 24 € 560 pes y tiene que llegar a 600 para que ponga para que pueda poner los bonos que apliquen."

**Jhony Alexander Alvarez Vasquez:** Vale, un segundito,

### 01:08:29 {#01:08:29}

**Cubakilos:** Entonces, y esto lo toca es imprimir el recibo. No puedo encontrar la transacción. Oye, está pasando algo raro ahí. Cuando las transacciones las las hemos las dos que hemos hecho nunca me deja dice que no encuentra recibo y eso es falso.

**Jhony Alexander Alvarez Vasquez:** la tirada

**Cubakilos:** Vamos a ver. Ven, esto no lo hace. Ahora si voy a una vieja que haya hecho yo por la

**Jhony Alexander Alvarez Vasquez:** Y el ID si lo tenemos,

**Cubakilos:** plataforma,

**Jhony Alexander Alvarez Vasquez:** pero está en Wcommerce.

**Cubakilos:** pero si voy a esta recarga, el recibo sí me aparece y en las que estamos haciendo a través y de transacción y este es recib

**Jhony Alexander Alvarez Vasquez:** Ahí sería consultarle, tendríamos que consultar eso con con Dincon Connect.

**Cubakilos:** Ven, imprimir recibo haría chulo enviar por SMS, pero si voy a las anteriores, por ejemplo, si pongo las que hemos hecho ahora, esta misma o las que hiciste tú, que son a través de la web,

**Jhony Alexander Alvarez Vasquez:** M. Okay.

**Cubakilos:** Y si están llegando porque tú me enseñaste la de IMI, ¿no?

**Jhony Alexander Alvarez Vasquez:** Sí.

**Cubakilos:** Que le llegan.

**Jhony Alexander Alvarez Vasquez:** Y ahora es que tu madre te confirme, ¿cierto?

**Cubakilos:** Sí, tengo que escribirle escribirle por

### 01:10:15

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** WhatsApp.

**Jhony Alexander Alvarez Vasquez:** pero entonces te escalo ese caso para ver qué pasa con los recibos en con ellos. consultar

**Cubakilos:** Bueno,

**Jhony Alexander Alvarez Vasquez:** contigo.

**Cubakilos:** eh yo pienso puedes hacerlo, pero bueno, no es la prioridad, o sea,

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** lo puedes dejar en un mail para luego,

**Jhony Alexander Alvarez Vasquez:** dame un segundo. Tomo

**Cubakilos:** porque yo pienso que con que tú pongas acá el ID de la transacción,

**Jhony Alexander Alvarez Vasquez:** captura.

**Cubakilos:** ¿entiendes? Que se que tú puedas verificarlo porque con ese ID es un identificador único que el cliente viene para mí y me dice, "Mira, esta es mi transacción, ¿entiendes? Porque bueno, igual yo puedo venir aquí y al final el número lo puso él, pero el ID sí es una idea que tú creas. Entonces, porque con el número yo puedo venir aquí buscarlo igual, ¿no? Porque yo vengo aquí transacción B por número de destino y míralo aquí. Miral aquí, ¿ves?

**Jhony Alexander Alvarez Vasquez:** Mm.

**Cubakilos:** Pero bueno, lo

**Jhony Alexander Alvarez Vasquez:** aquí, o sea,

**Cubakilos:** lo

**Jhony Alexander Alvarez Vasquez:** en este momento podemos hacer seguimiento, sino que ese ID sería bueno que el cliente te lo mandara, o sea,

### 01:11:17 {#01:11:17}

**Cubakilos:** claro que lo tuviera que como en todo tú dices,

**Jhony Alexander Alvarez Vasquez:** que mira,

**Cubakilos:** "Mira,

**Jhony Alexander Alvarez Vasquez:** pero que salga acá.

**Cubakilos:** aquí hay una idea de transacción,

**Jhony Alexander Alvarez Vasquez:** Ajá.

**Cubakilos:** ¿entiendes?" Y bueno,

**Jhony Alexander Alvarez Vasquez:** Vale.

**Cubakilos:** acá veo que el estado no puede ser

**Jhony Alexander Alvarez Vasquez:** Sí, es que ahí te salió procesando,

**Cubakilos:** procesando.

**Jhony Alexander Alvarez Vasquez:** pero es porque no lo el algoritmo que dejé ayer y desde antier estaba haciendo eh no encontró,

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** o sea, si te salió procesando es porque no no le llegó como la señal de Din Connect.

**Cubakilos:** Claro.

**Jhony Alexander Alvarez Vasquez:** Entonces eso tengo que eso tengo que bugarlo ahorita.

**Cubakilos:** Exacto.

**Jhony Alexander Alvarez Vasquez:** A ver, a ver qué cuál es el como el inclusive ese correo que mandé como el miércoles

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** jueves, ese correo está pidiendo esa información que me digan exactamente como el punto que Lincon dice transacción exitosa, porque aquí yo estoy mirando cuál es el que informa.

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** Ayer cambié más y y le metí más estados de DCN o no, ¿cómo se llama? Eso es el bueno la señal que nos manda Innect y bueno, de momento sigue sigue

### 01:12:22

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** saliendo.

**Cubakilos:** ¿Y por qué sale? ¿Por qué sale este entonces ahora

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** también?

**Jhony Alexander Alvarez Vasquez:** yo ayer yo ayer acomodé eso eh antes salía no procesado porque no no le llegó nada.

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** Yo lo dejé para que dijera eh algo así como está pendiente si no encuentra todavía eso y que no diga que no se hizo, sino que diga está pendiente de de confirmación y demás. Es que, o sea,

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** yo puedo poner ahí que fue exitoso, pero no estoy 100% seguro porque la información no viene de inconnect,

**Cubakilos:** claro. Exacto. Sí,

**Jhony Alexander Alvarez Vasquez:** vi viene Exacto.

**Cubakilos:** tú necesitas tú necesitas el rebote de Disney. Es lo que habláamos. Tú le mandas la solicitud que está funcionando. O sea, aquí lo que se puede confirmar es una dos cosas. una cosa, bueno, la principal, efectivamente se manda la compra y Din con la procesa y la hace. Ahora faltaría que cuando él la haga, él genera un número de ID que es el que tú tienes que pedirle.

### 01:13:17 {#01:13:17}

**Jhony Alexander Alvarez Vasquez:** Aquí se me está viniendo. Sí, ese ese es el que estoy buscando. Eso es lo que les pregunté ayer, esta semana a ellos. A ver, que me digan algo

**Cubakilos:** que de nuevo la solución. Si el que tú estás recibiendo es el ID de API transfer,

**Jhony Alexander Alvarez Vasquez:** muyal.

**Cubakilos:** podemos ir tirando con ese con este V.

**Jhony Alexander Alvarez Vasquez:** A ver. ah, bueno, si recibo ese

**Cubakilos:** Ese tú lo estás recibiendo porque si abre tu abre ve a WordPressamos.

**Jhony Alexander Alvarez Vasquez:** yo voy a abrir en este momento el pedido,

**Cubakilos:** Ajá.

**Jhony Alexander Alvarez Vasquez:** que me tiene que salir. Míralo acá.

**Cubakilos:** Exacto. ¿Qué número te da?

**Jhony Alexander Alvarez Vasquez:** Espérame que no me deja copiar. 844 290 148\.

**Cubakilos:** Dame un segundo. Dame un segundo. Dame un segundo. Voy. Eh,

**Jhony Alexander Alvarez Vasquez:** Igual te iba a copiarlo.

**Cubakilos:** hecho. Vale, ocho.

**Jhony Alexander Alvarez Vasquez:** 84 290 148

### 01:14:17

**Cubakilos:** Míralo ahí. V. Ya cuando él te devuelve ese número, ya puedes poner aquí exitoso y podemos ir tirando con ese número hasta que ellos te respondan lo de la lo del ID de transacción, pero igual al final es un código que está bien, ¿entiendes? Porque de hecho esto tiene 2 4 6 8 nu números.

**Jhony Alexander Alvarez Vasquez:** H

**Cubakilos:** la misma cantidad de números 2 4 6 y tres nueve números.

**Jhony Alexander Alvarez Vasquez:** Mira, puedo escanear también

**Cubakilos:** Amb ambos tienen nueve nu nueve números pero ya

**Jhony Alexander Alvarez Vasquez:** estes

**Cubakilos:** con ese cuando él te devuelve eso, ya él te generó algo, ¿entiendes? generó eso puede ser aquí,

**Jhony Alexander Alvarez Vasquez:** razón.

**Cubakilos:** o sea, podemos ir poniendo ese número como idea de transacción momentáneamente y eh y el Oh, bueno, ya cambiaría lo tomas como el cambio para el estado exitoso o completada

**Jhony Alexander Alvarez Vasquez:** Vale, déjame que estoy es que encontré este sí estoy tomando

**Cubakilos:** aquí. Aquí este PDF, este PDF estaría bien, pero bueno, igual más siguiente paso, eh, hacerlo similar a esto, eh, o sea, o al menos ponerlo algunos campos, aunque aunque igual te digo, eh, los de Fonoma no envían ni c\*\*\*\*\*, lo único que envían es el ID de transacción.

### 01:15:53 {#01:15:53}

**Cubakilos:** Dice,

**Jhony Alexander Alvarez Vasquez:** enviar.

**Cubakilos:** "Has recargado,

**Jhony Alexander Alvarez Vasquez:** ¿Qué?

**Cubakilos:** lo que te hacen es esto mismo." Te dice, "Recargaste a este número con este monto, o sea, con el paquete que mandaste.

**Jhony Alexander Alvarez Vasquez:** Y

**Cubakilos:** y listo. Y el ID de transacción,

**Jhony Alexander Alvarez Vasquez:** ya

**Cubakilos:** ¿eh? Vale, entonces bueno, pero de que ya funciona, funciona.

**Jhony Alexander Alvarez Vasquez:** sí, sí, solo es como ya cuadrar los avisos para el cliente y demás. Eh, veo que para que puedas probar a eh o dejarlo activo. Eh, a ver, voy a ahorita que terminó la llamada, bueno, voy a atender otro cliente que me está pidiendo algo de una página, voy a hacer esa parte, esa esto de de tu pedido contiene operaciones pendientes y voy a seguir tu sugerencia porque yo en el pedido si estoy recibiendo en con éxito esa referencia, ese referencia ID puedo agarrarme,

**Cubakilos:** Claro, ese se llama se llama.

**Jhony Alexander Alvarez Vasquez:** puedo agarrarme de ese, sino que ese es un registro, no es como el La señal que dice em como dinnect el app que diga pedido completado con éxito. manda como el registro de operación que bueno también se puede, no me no me puedo fiar, pero sí se puede utilizar ahora mismo está procesando y se y se recibe en notas de pedido esto, lo cual podemos en el checkout en No, en pedido confirmado.

### 01:17:31

**Jhony Alexander Alvarez Vasquez:** Revisar el estado correcto de exitoso. Listo. De todas maneras, René, te animo mucho, mucho a que lo puedas lanzar y y bueno, te soy sincero, nos está yendo bien ahora mismo tú haciendo pruebas y ves que recarga y demás, eh, pero van a haber otros escenarios, otros métodos de pago, otros cosas que vamos a descubrir con los clientes inclusive. Eh,

**Cubakilos:** Claro,

**Jhony Alexander Alvarez Vasquez:** lo importante es

**Cubakilos:** pero bueno, sí me gustaría sí me gustaría eh al menos que terminemos si puedes terminarlo hoy,

**Jhony Alexander Alvarez Vasquez:** actuar.

**Cubakilos:** al final la recarga se activa a partir de mañana, pero sí me gustaría,

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** por ejemplo, pulir las tres las tres cositas que fuimos viendo en cuanto a incluso a texto,

**Jhony Alexander Alvarez Vasquez:** sí, sí.

**Cubakilos:** a diseño y esta parte de, por ejemplo, o sea, este mensaje final aquí eh arreglarlo a que sea exitoso para que no cree ningún porque lo que me va a pasar es que de hecho, por eso no lo he hecho, me va a empezar a escribir la gente el domingo y y y entiendes y se van a

**Jhony Alexander Alvarez Vasquez:** Sí, sí,

**Cubakilos:** quedar eh ah no sé qué, no sé qué Entonces,

### 01:18:47 {#01:18:47}

**Jhony Alexander Alvarez Vasquez:** claro. Entonces, mira, hagamos algo.

**Cubakilos:** si tú si tú me lo si tú lo si tú lo puedes terminar esas correcciones de de diseño, de testicos viendo, eh, y tal, yo yo lo puedo lanzar hoy en la noche para mañana. Y de este texto que de nuevo te digo, me parece que utilizando el mismo código que te están devolviendo ya, que es este el API transfer,

**Jhony Alexander Alvarez Vasquez:** Sí, yo eso yo creo que está bien.

**Cubakilos:** con que tú ya lo usas, o sea, momentáneamente lo pones aquí y ya.

**Jhony Alexander Alvarez Vasquez:** Sí, sí, me estoy me estoy poniendo muy muy estricto con esa parte, pero creo que usando ese ese registro podemos avanzar.

**Cubakilos:** avanzar hasta que te respondan esta semana los de

**Jhony Alexander Alvarez Vasquez:** Entonces,

**Cubakilos:** DIN.

**Jhony Alexander Alvarez Vasquez:** vale, voy a corregir primero esa parte y empiezo a hacerlo la otra parte que ya es de de estilos y organizar eh los demás, eh, para que no me llegue muy noche sin hacer esto

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** último.

**Cubakilos:** yo voy a yo me voy a poner entonces a hacer en elemento la la landing definitiva donde se pondrá el short code.

**Jhony Alexander Alvarez Vasquez:** Eso es otro también para que me avises cómo te queda ahí por si necesitas algún cambio de estilo para que lo

### 01:19:52 {#01:19:52}

**Cubakilos:** Claro.

**Jhony Alexander Alvarez Vasquez:** puedas mejorar.

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** que que se se incrustre bien y entonces con esta

**Cubakilos:** vale. Listo. Déjame ponerme

**Jhony Alexander Alvarez Vasquez:** prueba que tuvimos que es a Cubacel y bueno,

**Cubakilos:** hacerla.

**Jhony Alexander Alvarez Vasquez:** me imagino que Cuba va a estar muy influido en que puede ser uno de los que más recarguen, eh, vamos a probar, o sea, me refiero a que no tengamos que necesitar probar Cuba porque veo que todas las recargas de Cuba son como más costosas. Podemos probar, podemos ya probar. Estamos evidenciando que con Cuba funciona. A eso voy, que con Cuba funciona, pero hagamos pruebas con Colombia, con España. Ah, no, España tampoco tenía paquetes pequeños, pero con Colombia solo es de 1 € o menos.

**Cubakilos:** Vale, de

**Jhony Alexander Alvarez Vasquez:** Entonces hagamos esta tarde,

**Cubakilos:** acuerdo,

**Jhony Alexander Alvarez Vasquez:** esta noche tarde con con Colombia y confirmamos estas cositas que queden ya

**Cubakilos:** perfecto.

**Jhony Alexander Alvarez Vasquez:** bien.

**Cubakilos:** Estupendo, estupendo. Vale, que me me comentas cualquier cosa, voy a estar

### 01:20:43 {#01:20:43}

**Jhony Alexander Alvarez Vasquez:** Vale, voy a organizarle el carrito de es una página de Shopify,

**Cubakilos:** pendiente.

**Jhony Alexander Alvarez Vasquez:** a veces se complican tanto con Shopify, un cliente que tiene que hacer eso para mañana que es el día de madres en en Latinoamérica.

**Cubakilos:** Andera, mira, aquí te comparto una cosa rápido antes de colgar. Ya, ya tengo ya me dieron acceso al intranet de del consolidador para el tema de la de la agencia de viaje.

**Jhony Alexander Alvarez Vasquez:** Uy, qué bien, qué

**Cubakilos:** Entonces, pues nada, vendría ahora a empezar a ver cómo montamos porque ellos te dan una we de palo ahí, pero bueno, esa a mí no me interesa.

**Jhony Alexander Alvarez Vasquez:** Mm.

**Cubakilos:** Yo mi idea sería, ve aquí este me en una we, pero mi idea es eh jalarlo,

**Jhony Alexander Alvarez Vasquez:** Está cerrado a lo que como ellos lo tienen diseñado y demás.

**Cubakilos:** ¿entiendes? Eh, jalar los productos, actividad, alquilar coche a una we que

**Jhony Alexander Alvarez Vasquez:** a tu web, a tu web una.

**Cubakilos:** cree,

**Jhony Alexander Alvarez Vasquez:** Ah, okay,

**Cubakilos:** sí, o sea, no sé cómo sacarlo.

**Jhony Alexander Alvarez Vasquez:** okay.

**Cubakilos:** Me dijeron que por iframe, pero yo creo que frame no es lo mejor,

### 01:21:53 {#01:21:53}

**Jhony Alexander Alvarez Vasquez:** Por eh Ah, bueno,

**Cubakilos:** ¿no?

**Jhony Alexander Alvarez Vasquez:** es que ahí juegan muchas cosas. No sé si ellos tienen más nivel de de integración, eh, pero bueno, si con el frame uno se la saca rápido.

**Cubakilos:** Porque ve sería

**Jhony Alexander Alvarez Vasquez:** Uy, sí.

**Cubakilos:** para bueno, para ver el motor de ellos. Tengo que escribirle el informático de ello, pero bueno,

**Jhony Alexander Alvarez Vasquez:** Eso.

**Cubakilos:** vale. Pero ahí está

**Jhony Alexander Alvarez Vasquez:** Y también con este trabajo, yo creo que la mejor manera es que Sebas y yo le hagamos juntos a cosas de este no

**Cubakilos:** avanzando.

**Jhony Alexander Alvarez Vasquez:** nos alarguen tanto. ¿Cómo vas con eso de GLS? Ayer vi un reporte de me mandó reporte Sebas, me dijo que no podía avanzar por por el tema de de qué

**Cubakilos:** Sí, el eh decía que era que no teníamos permiso eh por por un o

**Jhony Alexander Alvarez Vasquez:** deh

**Cubakilos:** sea como que el código que le dio GS ya como que lo haya revocado o haya vencido. Por ejemplo, él cuando volvió a revisar vio que él tenía una petición que también estaba haciendo mal, la corrigió y en teoría ya nos respondieron a él de GLS diciendo que se lo pasaban a los compañeros de

### 01:23:05

**Jhony Alexander Alvarez Vasquez:** ¡Uf\!

**Cubakilos:** informática, pero bueno, eso fue a las 6 de la tarde, o sea, que eso será para el lunes.

**Jhony Alexander Alvarez Vasquez:** Bueno,

**Cubakilos:** Sí, o sea, pero todavía él él nunca hizo nunca llegó a hacer una prueba eh en producción y

**Jhony Alexander Alvarez Vasquez:** listo.

**Cubakilos:** si la hubiese hecho durante la semana en en un tiempo hubiésemos tenido respuesta esta semana, pero ahora es hasta la semana que viene.

**Jhony Alexander Alvarez Vasquez:** Me me leíste las palabras que le dije ayer. Sí,

**Cubakilos:** Claro.

**Jhony Alexander Alvarez Vasquez:** sí,

**Cubakilos:** Pero si esperaste el viernes a las 6 de la tarde para hacer la prueba y entonces perdimos tiempo.

**Jhony Alexander Alvarez Vasquez:** sí, sí. Tal tal cual, tal cual. Yo me di cuenta también de eso y bueno, por eso mismo te estoy diciendo que los proyectos yo creo que los voy a agarrar más de más de raíz, como lo que estoy haciendo así con el plugin, con ese plugin de connect porque Sebas también lo voy a lo voy a poner a que esté al tanto de este este plugin también. Yo he documentado todo y tenemos un repositorio también. Eso todo es tuyo. Pues es es ese código es tuyo.

### 01:24:06 {#01:24:06}

**Cubakilos:** Johnny. Entonces, me imagino que ahora eh eh no sé cuántos cuántos pluin tú has hecho para

**Jhony Alexander Alvarez Vasquez:** Entonces,

**Cubakilos:** WordPress a este a este nivel, eh, pero imagino que ahora sería mucho más fácil para ti crearnos nuevos pluin,

**Jhony Alexander Alvarez Vasquez:** claro. Eh,

**Cubakilos:** ¿no?

**Jhony Alexander Alvarez Vasquez:** cada cada plugin es que es un trabajo muy diferente, ¿eh?

**Cubakilos:** Te digo por el tema de que me haría falta un plugin para el tema ahora de la isasin.

**Jhony Alexander Alvarez Vasquez:** Okay.

**Cubakilos:** que te mostré,

**Jhony Alexander Alvarez Vasquez:** Hay dos,

**Cubakilos:** eh, y todo eso.

**Jhony Alexander Alvarez Vasquez:** hay dos cosas. Mira, no es que ya llevamos todos estos son los repositorios. Este, el tuyo está en este, mira. Eh, hay dos, ¿cómo se le dice a eso? dos dos bases de conocimiento, pero para para la empresa, nosotros como desarrolladores y tú que son que son conocer el el mercado o la manera de de trabajo en cuanto a cómo vendes y demás y conocer el eh tus herramientas. ¿A qué voy? O sea, que cuando sabemos cómo cómo vendes, cómo cómo distribuyes, cómo ofreces el producto, es un avance para que las próximas trabajos y demás se puedan entender mucho mejor.

### 01:25:29

**Jhony Alexander Alvarez Vasquez:** Y otro es la manera de trabajar y comunicarnos. Entonces, eh porque te digo, cada cosa es diferente, o sea, cada si tú me dices, "Johnny, ahora vamos a irnos a este otro proyecto." Tenemos avances en cómo nos hablamos, cómo nos comunicamos, cómo avanzamos con los proyectos y lo otro es que tenemos que ver eh ese ese código, esa esa empresa, cómo maneja su app y demás. Entonces ahí es otro trabajo, pero sí se avanza con la parte de entendernos,

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** que ya sabemos cuaquilos cómo vende y ya sabemos Renneté cómo trabaja y demás, que esa es la parte que sí se acelera mucho. Esa es la lo que te quería comentar en cuanto a qué avances podríamos tener para los próximos proyectos o y demás, porque yo no pienso que ni pienso tener otro otro proyecto que vaya a tardar otra vez otros tr cu meses. Entonces, esa es la parte que si podemos garantizarte, que ya entendernos y saber a dónde vas y cómo vendes va a ser un avance rápido, porque eso nos pasó con CTT GLS, parecía que tenía mejor documentación inclusive me Sebas me animó mucho un día que hicimos un estudio antes de empezar GLS y eh ayer que le le metí el regaño porque no hizo las pruebas antes eh de esperar el último día, me hablamos también de lo demás y GLS lo vio inclusive un poco más complejo que CT.

### 01:26:58

**Jhony Alexander Alvarez Vasquez:** Entonces, eh bueno, por ese lado eh te quería dar como ese escenario de que sí, claro, podemos avanzar mucho más rápido porque, claro, nos entendemos muy bien, ya sabemos a qué quieres hacer y qué quieres lograr. Y bueno, y aquí tu documentación es privada, pero tú puedes acceder a este a este repositorio sin problema.

**Cubakilos:** Vale. Eh, sí. Eh, la idea es terminar ya con GLS para entonces después pasar a la parte de facturación,

**Jhony Alexander Alvarez Vasquez:** Okay,

**Cubakilos:** eh, que es lo que Bueno, está la IA,

**Jhony Alexander Alvarez Vasquez:** perfecto.

**Cubakilos:** que más o menos está en más también. que lo que hay es que darle los textos, entiendo un poco más y eh pero bueno,

**Jhony Alexander Alvarez Vasquez:** Mm.

**Cubakilos:** ya lo otro viene ser la parte de facturación. Eh, listo. Bueno, nada, te dejo entonces para que puedas avanzar y el plugin y

**Jhony Alexander Alvarez Vasquez:** Gracias,

**Cubakilos:** eh

**Jhony Alexander Alvarez Vasquez:** René. Sí. Eh,

**Cubakilos:** venga,

**Jhony Alexander Alvarez Vasquez:** igual un feliz eh feliz fin de semana y estamos hablando igual esta tarde y y mañana a ver cómo te va con las pruebas.

**Cubakilos:** venga, genial.

**Jhony Alexander Alvarez Vasquez:** Solo va a estar un momento ahorita,

**Cubakilos:** Salud.

**Jhony Alexander Alvarez Vasquez:** pero bueno,

**Cubakilos:** Sí,

**Jhony Alexander Alvarez Vasquez:** ya retoman el lunes igualmente.

**Cubakilos:** vale. Dalo.

### La transcripción finalizó después de 01:28:05

*Esta transcripción editable se generó por computadora y puede contener errores. Los usuarios también pueden cambiar el texto después de que se cree.*