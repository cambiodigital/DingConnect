# 📝 Las notas

abr 21, 2026

## Reunión del 21 abr 2026 a las 16:00 CEST

Registros de la reunión [Transcripción](https://docs.google.com/document/d/1VtPr_fuSrlJOwJ2PaW2FyfIzbglqqolo4L4c6H8xUQ4/edit?usp=drive_web&tab=t.11e5eayf1i3j) [Grabación](https://drive.google.com/file/d/1loQB9ZndQM4c4s-D9UalCyeizMsa2vyr/view?usp=drive_web)

### Resumen

Revisión técnica de plugin para optimizar la selección de paquetes, mejorar la gestión comercial y definir pagos.

**Optimización del plugin técnico**
El flujo de creación de landings requiere filtros por país y tipo de producto para mejorar la legibilidad. Se ajustarán reglas internas para presentar información clara del producto en lugar de datos técnicos.

**Gestión comercial y precios**
Se implementarán campos para diferenciar el costo y el precio al público, permitiendo gestionar promociones y beneficios editables. Se decidió que el flujo de pago utilizará el estándar de WCommerce.

**Decisión sobre el pago**
Se decidió que el flujo de trabajo procesará los pagos mediante WCommerce y enviará la confirmación al servicio DIN tras completar la transacción.

*Califica este resumen:* [Útil](https://google.qualtrics.com/jfe/form/SV_4YkxrBAaiTVqYCi?isGoogler=false&isHelpful=true) o [Poco útil](https://google.qualtrics.com/jfe/form/SV_4YkxrBAaiTVqYCi?isGoogler=false&isHelpful=false)

### Próximos pasos

- [ ] \[Jhony Alexander Alvarez Vasquez\] Cambiar Regla Divisa: Modificar la regla de cálculo para mostrar los precios en euros en lugar de dólares. ✅
- [ ] \[Jhony Alexander Alvarez Vasquez\] Renombrar Botón: Cambiar texto de botón de Crear Bundle desde API a Seleccionar Producto.✅
- [ ] \[Jhony Alexander Alvarez Vasquez\] Deshabilitar Duplicados: Deshabilitar la validación de duplicados para permitir crear múltiples paquetes con el mismo SKU.✅
- [ ] \[Jhony Alexander Alvarez Vasquez\] Ordenar Paquetes: Implementar la función para definir el orden de visualización de los paquetes en la página de aterrizaje (front-end). ✅
- [ ] \[Jhony Alexander Alvarez Vasquez\] Agregar Filtros Landing: Añadir filtros de País y Tipo de Producto en la sección Landings para seleccionar paquetes previamente guardados. ✅
- [ ] \[Jhony Alexander Alvarez Vasquez\] Usar Excel Directorio: Estudiar la integración del archivo Excel como directorio usando el SKU para obtener información comercial de los paquetes.✅
- [ ] \[Jhony Alexander Alvarez Vasquez\] Agregar Campos Precio: Modificar la configuración de paquetes para incluir los campos Coste (costo DIN) y Precio al Público (precio de venta al cliente). ✅
- [ ] \[Cubakilos\] Instalar Plugin: Instalar el plugin de prueba modificado en la tienda Cuakilos. Revisar las modificaciones mañana.
- [ ] \[Jhony Alexander Alvarez Vasquez\] Entregar Plugin: Aplicar las modificaciones discutidas al plugin. Entregar el plugin actualizado esta noche para pruebas.
- [ ] \[Jhony Alexander Alvarez Vasquez\] Comunicar Prioridad: Informar a Sebastián sobre la necesidad de terminar Cero Water y Cuakilos este mes.
- [ ] \[Jhony Alexander Alvarez Vasquez\] Revisar Obstáculos: Preguntar a Sebastián por el estado de los problemas pendientes o credenciales faltantes.
- [ ] \[Jhony Alexander Alvarez Vasquez\] Implementar JISIN: Comenzar la implementación de la API JISIN en la tienda. Conectar la API por región, incluyendo España, para planes de compra.

### Detalles

* **Configuración Inicial del Plugin y Flujo de Pruebas**: El flujo de trabajo implica que el interlocutor (Cubakilos) instale el plugin en su página en modo de pruebas, con la expectativa de que cualquier requerimiento o modificación identificada se aborde inmediatamente ([00:01:17](#00:01:17)). Jhony Alexander Alvarez Vasquez confirmó que actualizaría el plugin para reflejar los cambios en los minutos siguientes ([00:02:41](#00:02:41)).
* **Diseño y Personalización de Landings**: Se está dando forma a los \*bundles\* y se requiere asistencia para perfeccionar el proceso ([00:01:17](#00:01:17)). El proceso de creación de \*landings\* incluye asignar un nombre a la \*landing\* (solo para uso interno), generar un \*shortcode\* para publicación, definir título y subtítulo, y seleccionar los \*bundles\*. Se notó que la función de seleccionar el tipo de paquete se implementó basada en el patrón de uso más común ([00:02:41](#00:02:41)).
* **Análisis de Tipos de Paquetes en Excel**: Se revisó el archivo Excel para identificar la información disponible sobre los productos, como el tipo de paquete (datos, combos) y detalles del producto. Cubakilos sugirió que la columna adyacente a la comisión en el Excel, la cual detalla si un producto es un \*voucher\* o un \*reward\*, podría ser útil para el filtrado ([00:03:56](#00:03:56)).
* **Identificación y Filtrado de Tipos de Producto**: Se determinaron los tipos de producto presentes en el Excel, como \*Topup\* (recargas), \*Bundle\* (paquetes) y \*Voucher\* (tarjetas de regalo, especialmente en Cuba). Cubakilos enfatizó la necesidad de que el plugin pueda mostrar el tipo de producto (como \*Giftcard\* o \*Voucher\*) para facilitar la selección de productos al crear \*landings\* ([00:06:46](#00:06:46)).
* **Detalle del Contenido del Paquete y Discrepancias en la API**: Se examinó el detalle de los productos, como \*Data\*, \*Bundle\* y \*Topup\* ([00:07:55](#00:07:55)). Se identificó que la información proveniente de la API al seleccionar un producto (\*Data\*) no coincidía con el nombre original, lo cual es necesario para una correcta identificación ([00:09:19](#00:09:19)).
* **Mejora en la Presentación de la Información del Producto**: El listado de productos que se muestra en el plugin debe ser más legible, incluyendo el operador, el \*receive\* y, posiblemente, el precio, en lugar del SKU, para facilitar la selección. Jhony Alexander Alvarez Vasquez explicó que, aunque la información llega desde la API, se pueden crear reglas para modificarla antes de que el usuario la vea ([00:11:36](#00:11:36)).
* **Consistencia en la Información y Conversión de Moneda**: Se confirmó que el selector de tipos de paquete debe depender del país seleccionado y mostrar los tipos de producto que figuran en el \*cheat\* (ej. \*Bundle\*, \*Data\*, \*Topup\*, \*Voucher\* para Cuba) ([00:12:45](#00:12:45)). Se detectó una inconsistencia en la conversión de precios (de euros a dólares) que requiere una corrección en las reglas internas para que el precio se muestre en euros ([00:13:46](#00:13:46)).
* **Flujo de Creación de Landings y Filtros Requeridos**: Se reiteró el flujo de trabajo deseado: seleccionar país, luego tipo de producto, y que los paquetes se muestren de manera legible (operador, lo que recibe y precio) ([00:15:23](#00:15:23)) ([00:20:20](#00:20:20)). Se identificó una discrepancia donde la API no está trayendo todos los paquetes disponibles para Venezuela, lo que requiere una revisión de la consulta a la API ([00:17:47](#00:17:47)).
* **Organización del Plugin para la Selección de Paquetes**: Se propuso cambiar el texto del botón "Crear Bundle desde API" por "Seleccionar Producto" para una mayor claridad en el proceso ([00:21:25](#00:21:25)). La selección de paquetes debe permitir que se muestre el país, el nombre del producto, el código, el valor, la moneda y el operador, información adaptada de la API ([00:22:26](#00:22:26)).
* **Flujo de Creación de Shortcode y Edición de Contenido**: Se demostró la creación de un \*shortcode\* para una \*landing\* y cómo este muestra el título, subtítulo y los paquetes seleccionados en el \*frontend\* ([00:23:35](#00:23:35)). Se señaló que el diseño de los paquetes disponibles será refinado. Se confirmó que la descripción del paquete es editable manualmente para adaptarla a las necesidades comerciales ([00:25:14](#00:25:14)).
* **Gestión de Promociones y Edición de Beneficios**: La flexibilidad de editar los beneficios es crucial, ya que el mismo producto (\*Topup\*) puede tener diferentes montos y beneficios durante las promociones mensuales de \*Ding\*. Esto implica poder seleccionar y duplicar el mismo producto varias veces, pero con diferentes montos de ingreso y descripciones de lo que recibe el cliente ([00:27:23](#00:27:23)) ([00:30:20](#00:30:20)).
* **Edición y Ordenamiento de Paquetes Guardados**: Se demostró la capacidad de duplicar y editar los paquetes guardados para ajustar la descripción de lo que recibe el cliente y el monto de ingreso a \*Ding\* ([00:34:02](#00:34:02)). Se solicitó la capacidad de ordenar los paquetes en el \*frontend\* (desplegable) según el valor (de menor a mayor) o para destacar un producto específico ([00:36:07](#00:36:07)).
* **Necesidad de Filtrado en la Sección de Landings**: Se identificó la necesidad de añadir filtros de "País" y "Tipo de Producto" en la sección de \*Landings\* para facilitar la selección de paquetes al crear un nuevo \*shortcode\*, especialmente a medida que la lista de paquetes seleccionados se alargue. Jhony Alexander Alvarez Vasquez estuvo de acuerdo con la necesidad de esta funcionalidad para la consistencia ([00:42:43](#00:42:43)) ([00:44:58](#00:44:58)).
* **Uso del Archivo Excel como Directorio de Información No API**: Se discutió la posibilidad de usar el archivo Excel como una base de datos de referencia para obtener información más legible (operador, \*receive\*, tipo de producto) y asociarla con el SKU que llega de la API ([00:48:42](#00:48:42)). Esto permitiría evitar la información técnica del API y mejorar los filtros ([00:51:05](#00:51:05)).
* **Ajuste en los Campos de Monto por Razones Comerciales**: Se concluyó que en la edición de paquetes se necesitan dos campos de monto: "Costo" (el valor que le llega a \*Ding\* o el costo del producto) y "Precio al Público" (el precio de venta al cliente) ([00:56:11](#00:56:11)). Esto es vital para manejar la estrategia de precios y la variación de beneficios en las promociones ([00:58:19](#00:58:19)).
* **Configuración de la pasarela de pago y flujo de trabajo**: La discusión se centró en cómo integrar la pasarela de pago 'Molly' de WCommerce con el servicio 'DIN' para la post-compra. Se decidió que el flujo de trabajo seguirá el proceso estándar de WCommerce, donde la señal a DIN solo se enviará una vez que WCommerce confirme que el pago se ha realizado, utilizando el estado del pedido "procesando" o "completado" como disparador ([00:59:39](#00:59:39)). La operación se completará con el cambio de estado a "completado" en WCommerce después de que DIN devuelva el código de identificación de la operación ([01:00:56](#01:00:56)).
* **Estado del plugin y desarrollo en curso**: Se aclaró que el ajuste de "pago directo" en el plugin es principalmente para pruebas y que, al pasar el flujo por WCommerce, este se encargará de gestionar la pasarela de pago, extrayendo el monto y permitiendo la selección de las reglas de pasarela disponibles ([01:00:56](#01:00:56)). Jhony Alexander Alvarez Vasquez se comprometió a realizar los cambios discutidos y a subir el plugin actualizado esa noche para que Cubakilos pueda probarlo al día siguiente ([01:01:54](#01:01:54)).
* **Gestión de la carga de trabajo y plazos del proyecto**: Cubakilos expresó su preocupación por la limitación de la ventaja competitiva debido a los retrasos en la finalización del proyecto, que ya se encuentra a finales de abril ([01:01:54](#01:01:54)). Jhony Alexander Alvarez Vasquez informó que han tenido conversaciones internas para enfocarse en los proyectos actuales, específicamente el de Cubakilos y otro cliente llamado "cerowater", con el objetivo de no terminar el mes sin completarlos ([01:03:17](#01:03:17)) ([01:05:58](#01:05:58)). Manifestó que no tomarán más clientes en este momento para evitar retrasos adicionales ([01:04:41](#01:04:41)).
* **Próximos pasos del proyecto y colaboración futura**: Cubakilos mencionó la necesidad de integrar la API de Jisin en su tienda, lo cual implica manejar planes por región y realizar la conexión técnica, similar al trabajo realizado con DIN ([01:07:17](#01:07:17)). Jhony Alexander Alvarez Vasquez indicó que comenzará las modificaciones en el plugin inmediatamente y que se asegurará de que esté disponible para las pruebas de Cubakilos al día siguiente. También se comprometió a hablar con Sebas para confirmar que el objetivo sigue siendo cerrar el mes con todo en funcionamiento ([01:08:29](#01:08:29)).

*Revisa las notas de Gemini para asegurarte de que sean precisas. [Obtén sugerencias y descubre cómo Gemini toma notas](https://support.google.com/meet/answer/14754931)*

*Cómo es la calidad de **estas notas específicas?** [Responde una breve encuesta](https://google.qualtrics.com/jfe/form/SV_9vK3UZEaIQKKE7A?confid=CH0rQs35XNGCNDpFD94tDxIXOAIIigIgABgDCA&detailid=standard&screenshot=false) para darnos tu opinión; por ejemplo, cuán útiles te resultaron las notas.*

# 📖 Transcripción

abr 21, 2026

## Reunión del 21 abr 2026 a las 16:00 CEST \- Transcripción

### 00:01:17

**Jhony Alexander Alvarez Vasquez:** Hola, Ren.

**Cubakilos:** Oh. Hola. Listo por

**Jhony Alexander Alvarez Vasquez:** Listo, ahora sí. Uy, discúlpame,

**Cubakilos:** acá.

**Jhony Alexander Alvarez Vasquez:** está más temprano que se ver los mensajes del cliente yo, ¿no? ¿Qué hago? ¿Qué hago? Déjame ver.

**Cubakilos:** E

**Jhony Alexander Alvarez Vasquez:** Eh, a ver, es listo. Uy, uy, ¿dónde está? ¿Dónde está? Bien, listo. Bueno, estamos por acá. Mm. Eh, a ver, a ver, ¿dónde? ¿Dónde? Bondol. Bueno, ya sabemos, le estamos dando forma y bueno, ya tú nos vas eh ayudando un poco para que lo hagamos. Bueno, el punto también que quería comentarte hoy era que, o sea, a este punto ya es que tú puedas instalar el plugin en tu página, en modo de pruebas, obviamente, y cada cosa que requieras pues o no sé, Johnny, cada vez que yo presioné aquí necesito que esto desaparezca o que esto, bueno, cositas así paso a paso se hacen así en muy puntuales, se hacen en el momento.

### 00:02:41

**Jhony Alexander Alvarez Vasquez:** Yo te actualizo el plugin y ya ves ya ves el cambio y demás. Listo. Para que ya cualquier modificación pues que tengas eh quede en los minutos siguientes.

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** Bueno, entonces estamos por aquí en las landings, que esta parte era lo que eh empieza la parte personalizada para cada una de las páginas. Entonces, ya bueno, lo básico es dale un nombre a la landing. Esto es para tu para ti no más. El shortcode sí es para publicar en el en la página. el título, el subtítulo y los bundles que tú vas creando en esta parte. Ah, bueno, no te había terminado de decir que al buscar acá pues bueno, ya eliges aquí con eh ah,

**Cubakilos:** Y ahí veo que tienes un selector de tipo de paquete.

**Jhony Alexander Alvarez Vasquez:** sí, sí. Es que ese fue como el más el patrón más que patrón que más se dio.

**Cubakilos:** Sí,

**Jhony Alexander Alvarez Vasquez:** Yo he volteado mucho con este Excel y es

**Cubakilos:** sí.

**Jhony Alexander Alvarez Vasquez:** que no había como una información y en la documentación no lo tenía muy claro, pero yo supuse que como esto es tan personalizado de que eliges y vas acomodando tus paquetes, pues no sé qué tanto sea necesario.

### 00:03:56

**Jhony Alexander Alvarez Vasquez:** Entonces, esta parte de paquetes, tú le das datos, descargan solo los de datos, eh, combos.

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** Eh, por ejemplo, hay alguna algunos que yo no vi. Ah, espera, tengo que ampliarte esto aquí también que no no alcanza a verse completo, pero si tú le das acá ya se ve bien. Ya se ve bien. Por ejemplo, aquí no me dice la eh más información, por ejemplo, del proveedor o si estas ciclas de pronto lo dicen. Bueno,

**Cubakilos:** Ve un momento al Exel,

**Jhony Alexander Alvarez Vasquez:** de pronto es lo

**Cubakilos:** al Exel que me mostraste al Excel

**Jhony Alexander Alvarez Vasquez:** cuál.

**Cubakilos:** cheat,

**Jhony Alexander Alvarez Vasquez:** Ah, ya.

**Cubakilos:** si te fijas el producto en al lado de comisión te te lo dice, por ejemplo, es un voucher, eh, un sí de por ejemplo los voucher, que esos son los reward, por ejemplo, no sé si de ahí puedes filtrar, eh, tiene aplica el filtro, ve un momento, o sea, amplía la la preliminar

**Jhony Alexander Alvarez Vasquez:** que esto es una vista. Sí, sí.

**Cubakilos:** Si te

**Jhony Alexander Alvarez Vasquez:** Eh, ¿dónde está?

### 00:05:01

**Cubakilos:** fijas.

**Jhony Alexander Alvarez Vasquez:** Bueno, porque esa es la otra cosa, que si lo vemos acá, eh, ya tendríamos que,

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** bueno, y solo lo desactivé, ya tendríamos que utilizar eso qué

**Cubakilos:** Es loarte.

**Jhony Alexander Alvarez Vasquez:** me tardo.

**Cubakilos:** Si eso lo subes a un lechilla.

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, está bien. Espérame que

**Cubakilos:** Ha.

**Jhony Alexander Alvarez Vasquez:** formate como todo el fin de semana. Uy, se me cuesta el mouse listo. Eh, ¿qué le pasa este botón? me combino con esto. Bueno, datos, filtros.

**Cubakilos:** Si te fías, por ejemplo, en, por ejemplo, filtra país, en país ponombia para ver una cosa, porque creo en Colombia los vi. Colombia. Ajá. Vale, si te fijas, ve los voucher, esos son los reward, esos son los reward,

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** algo así.

**Jhony Alexander Alvarez Vasquez:** aquí

**Cubakilos:** V, si te ves el tipo de producto, amplíalo un tincito si quieres.

**Jhony Alexander Alvarez Vasquez:** porque no veo el tipo de producción.

### 00:06:46

**Cubakilos:** E el otro ahí. Ahí,

**Jhony Alexander Alvarez Vasquez:** Ah,

**Cubakilos:** ahora sí.

**Jhony Alexander Alvarez Vasquez:** es que no

**Cubakilos:** tipo de producto, eh, o sea, tienes los top, los bond que son los paquetes,

**Jhony Alexander Alvarez Vasquez:** Tal.

**Cubakilos:** el topup es la recarga y el voucher, que son como la, en este caso no son como las giftcard, pero pero parecido. Eh, me parece que en en Colombia lo que tienen es como como voucher o ellos le llaman voucher. Dame darle cancelar un momento ahora

**Jhony Alexander Alvarez Vasquez:** Mhm.

**Cubakilos:** Cuba. Vamos a probar Cuba y después te pongo España. A ver, ve, le llaman baja,

**Jhony Alexander Alvarez Vasquez:** Ok.

**Cubakilos:** le llaman voucher. Ellos a la giftcar le llaman voucher. ¿Ves todo eso? Ellos le llaman, ellos le llaman voucher, pero al final son, o sea, también se le puede llamar gift card, pero ahí tienes el tipo de producto. O sea, que lo que quiero que con esta con esta columna, si vas ahora al pluin que tú me a la parte del pluing en ahí en el tipo de paquete, ahí lo que me interesa es el tipo de producto,

**Jhony Alexander Alvarez Vasquez:** Vale.

**Cubakilos:** ¿no?

### 00:07:55

**Cubakilos:** Entiendo yo. A ver, dame un segundo.

**Jhony Alexander Alvarez Vasquez:** Bien.

**Cubakilos:** Sí. Em, por ejemplo, ahí tendría que aparecer, te pudiera aparecer el tipo de producto, porque ahí yo puedo eh poner rápido eh por ejemplo la giftcard, solo las giftcard, por ejemplo, y ya okay, selecciono las que me interesen seleccionar porque a lo mejor no me interesan todos los productos de que ofrece DIN, porque adás ya yo tengo contrato con otro proveedor de tarjetas de regalo. que que tengo que voy a utilizar. Em, entonces, ajá,

**Jhony Alexander Alvarez Vasquez:** Vale, me lo anoté y acá de una vez se lo va a pedir a

**Cubakilos:** vale. Ahora,

**Jhony Alexander Alvarez Vasquez:** Ahí.

**Cubakilos:** si yo voy a eh, dame un segundo. Si vas ahora de nuevo a la a la a la hoja, al cheat que estábamos viendo, eh, déjame ver ahora de nuevo. Cuba. Vouer. Vouer.

**Jhony Alexander Alvarez Vasquez:** Ok.

**Cubakilos:** Quítale voucher. Vale, ve tiene data, top y bundle. Ahí está, ¿ves? Ya. Esa está bien, está perfecto. Los bundle son los paqueticos, los data es dato puro, es que te dan 20 GB, 10 GB, 50 GB.

### 00:09:19

**Cubakilos:** Ahora, de ahí habría que saber cuáles son, por ejemplo, los que son eh sinca, la sin ahí está. Agrégale un poquito a ese. Ahí ahí está. Vale. Ahí tienes en mod, en nauta y mira la arriba. Los tres primeros son touris car data. Touris sin carard, turouris sin car tour car. Por eso me interesa porque eso lo voy a vender también. Nta plus. Vale, pero eso ya cuando yo cuando tú tienes los los productos, ese nombre sale en el plugin, ¿verdad? O sea,

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** cuando Vale,

**Jhony Alexander Alvarez Vasquez:** tú lo modificas como quieras, pero eso justo lo que iba a hacer ahora es que es que mira,

**Cubakilos:** vale, ahí está.

**Jhony Alexander Alvarez Vasquez:** con esto de acá se me está trayendo solo este.

**Cubakilos:** Solo esa. Míralo ahí. Vale. Sí. Lo que quiero decir es que, por ejemplo, si ahora yo voy ahí a aj seleccionar.

**Jhony Alexander Alvarez Vasquez:** Espérame.

**Cubakilos:** Ajá. Bueno, te sale una sola. El día que yo diga dato, dame datos, o sea, el tipo de producto dato por hacer una prueba.

### 00:10:22

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** Eh, ahí es data. V data pide pro type data.

**Jhony Alexander Alvarez Vasquez:** ¿cómo?

**Cubakilos:** Tienes que tener 1 2 3 6 7 8 tienen que haber ocho data. Vale,

**Jhony Alexander Alvarez Vasquez:** Sí.

**Cubakilos:** ahora tú le dices ahí, ¿no? Dale arriba el tipo de producto, por ejemplo. Dame los datas ocho. Míralos ahí.

**Jhony Alexander Alvarez Vasquez:** Ah, entonces sí es que están llegando con otro

**Cubakilos:** Exacto. No están llegando con Tienes que el nombre original. Mira a ver. Ahora compáralo con la tabla,

**Jhony Alexander Alvarez Vasquez:** información

**Cubakilos:** con el cheat. A ver qué es lo que te estás jalando, si es la descripción o el reif. A lo mejor te estás jalando el código y el

**Jhony Alexander Alvarez Vasquez:** el scop,

**Cubakilos:** reif.

**Jhony Alexander Alvarez Vasquez:** que es lo que necesito,

**Cubakilos:** El reif tiene tiene que llegarte el operador y el reif.

**Jhony Alexander Alvarez Vasquez:** Pero

**Cubakilos:** Compara a ver qué te llega. Te está llegando eh está llegando una mezcla rara de cosas. O sea, te está llegando como el código primero el SKU, después te llega como el reif, creo, por lo que estoy viendo, 10 GB y el precio.

### 00:11:36

**Cubakilos:** Ahí estaría bien, que estaría genial si que te llegue, por ejemplo, el operador, el reif y si acaso el precio y el precio puede ser, pero el SKU no es necesario porque cuando yo lo seleccione ya voy a tener el SKU después.

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** No sé si me fui muy rápido

**Jhony Alexander Alvarez Vasquez:** no te estoy entendiendo porque bueno, tú desde la parte de de negocio,

**Cubakilos:** o

**Jhony Alexander Alvarez Vasquez:** ¿sabes los paquetes que que necesitas y bueno, se basa más que todo en esto del CSV. Es lo que veo que es lo que tú también ves en siempre has visto en D connect directo, pero yo siempre he tenido esta información que viene del API y aquí confirmando estaba mirando si que creo que ese ese van a ser los datos que me van a llegar siempre, aunque bueno, puedo preguntar, pero no lo creo

**Cubakilos:** O sea, no,

**Jhony Alexander Alvarez Vasquez:** difícil.

**Cubakilos:** tú no puedes personalizar los datos que tú quieres recibir.

**Jhony Alexander Alvarez Vasquez:** Eh, después de recibir.

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, no, mentira. Sí, también. No sabes, yo cuando lo antes de que tú lo veas, yo también puedo modificarlos, sino que hay que crear esas reglas.

### 00:12:45

**Jhony Alexander Alvarez Vasquez:** Decirle cada vez que diga C9U, entonces aquí eh quiere decir esto. Entonces ya ese C9U lo podemos cambiar por cubacing, cuba data. La cosa es que tú ya después de que lleguen también puedes hacer eso.

**Cubakilos:** Sí, ahí yo lo que te digo desde el punto de vista de experiencia de de usuario para, o sea, para mí o a la persona que vaya a trabajar haciendo esto es que por ejemplo mira está genial ahí que decíamos,

**Jhony Alexander Alvarez Vasquez:** Mhm.

**Cubakilos:** "Okay, pages país, okay, por Cuba. Listo. Después dame los tipos de paquetes, no tipo de producto. Si te fijas en el cheat, esa esta columna, este selector va a tener que ofrecer en dependencia del país los tipos de producto que está en el chip, que van a ser, por ejemplo, data,

**Jhony Alexander Alvarez Vasquez:** Okay,

**Cubakilos:** eh, míralo aquí, por ejemplo, si seaste Cuba, te va a salir bundle, data, topup y voucher,

**Jhony Alexander Alvarez Vasquez:** ya miro

**Cubakilos:** ¿ve? Por ejemplo,

**Jhony Alexander Alvarez Vasquez:** los

**Cubakilos:** entonces si vas ahora al Ajá. pluing.

**Jhony Alexander Alvarez Vasquez:** espérame un segundo que ya lo habíamos visto.

**Cubakilos:** Sí,

### 00:13:46

**Jhony Alexander Alvarez Vasquez:** Por ejemplo, tengo 20 GB.

**Cubakilos:** sí,

**Jhony Alexander Alvarez Vasquez:** Este, este lo creo que es el que tengo acá.

**Cubakilos:** sí. Internet

**Jhony Alexander Alvarez Vasquez:** Es que a mí me llegaste Jason y al sistema ya eso es interno y yo ya le yo empiezo a acomodar esta

**Cubakilos:** limitado.

**Jhony Alexander Alvarez Vasquez:** información según como la también me sugiera y

**Cubakilos:** Entonces,

**Jhony Alexander Alvarez Vasquez:** entonces mira ah, espera,

**Cubakilos:** ajá.

**Jhony Alexander Alvarez Vasquez:** por los precios eh,

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** ejemplo este de 110 es que aquí ya no tiene el precio igualito Data Cuba. Data Cuba 110\. Era este. Míralo acá.

**Cubakilos:** Como único que eso te esté dando el precio no en euros, sino en dólares y por eso se da 11 y aquí 115\.

**Jhony Alexander Alvarez Vasquez:** Ah, con razón. Tengo que cambiar esa regla

**Cubakilos:** 105 €

**Jhony Alexander Alvarez Vasquez:** también.

**Cubakilos:** porque ellos a mí igual el precio creo que me lo dan en dólares porque me hacen un cambio ahí por el tipo de cambio mensualmente que hay en Google o algo así.

**Jhony Alexander Alvarez Vasquez:** Y voy a cambiar la regla para que me lo dejes en euros y y tú ya Bueno, tú ya lo Okay,

### 00:15:23

**Cubakilos:** Entonces ahí lo chulo, lo lo lo tocado,

**Jhony Alexander Alvarez Vasquez:** Tam.

**Cubakilos:** lo bueno sería, mira, que yo seleccione país. Listo, de independencia del país. Bueno, ahí sería Ajá. En Buscar API, ¿vale? Ya yo selecciono mi el país que voy a yo voy a crear ahora una landing que sea eh giftc para Venezuela, voucher para Venezuela o Exacto. Por ejemplo, ¿ves? Vengo acá y ahí digo, "Ve, tipo de producto, me tiene que decir tipo de producto." El tipo de producto tiene que ser eh topop, si tienen voucher, eh si tienen data, si tienen Exacto.

**Jhony Alexander Alvarez Vasquez:** Pero Venezuela poco se me parece.

**Cubakilos:** Sí, es muy poco. Deja ver un buen ejemplo.

**Jhony Alexander Alvarez Vasquez:** Estoy en Sí, estoy en

**Cubakilos:** Vene

**Jhony Alexander Alvarez Vasquez:** contra.

**Cubakilos:** a search.

**Jhony Alexander Alvarez Vasquez:** No sale. Ah, ya sé, ya sé por qué.

**Cubakilos:** Claro, ahí lo de último, ¿ves? Mira, ya ahí hay algo, ¿ves? Vouer, tienes varios tipos.

**Jhony Alexander Alvarez Vasquez:** Y acá no me está trayendo todos

**Cubakilos:** Entonces ahí tú tú dices, "Mira,

### 00:16:37

**Jhony Alexander Alvarez Vasquez:** ahí.

**Cubakilos:** yo quiero los tipo de producto voucher, no sé cuál, no sé cuál." Bueno, como yo voy a crear una landing que es para gift card de Venezuela, yo filtro por tipo producto voucher en el ahí en el pluin y entonces en el listado me va a mandar los productos de voucher. Entonces de esos productos de voucher, digamos que esos son los productos, ¿vale? Okay. Topup. voy a mandar top up. Entonces, en este listado me van a aparecer todos y yo voy a seleccionar los que me interesa, mostrarle al cliente que me compre. Entonces, aquí estaría bien que me llegara en ese listado me llegara lo que como tal el operador, o sea, y la y el recif con el precio. Si vas ahí, vale, pártele a selecciona un topop en producte para que sea Ajá. Tienendo top, ¿vale? Digisel Venezuela Digisel. Bum, bum, bum, bum. B y Receive 300\. Movistar 400\. Es Ajá. Si voy al cambio y el Só di

**Jhony Alexander Alvarez Vasquez:** Bueno,

**Cubakilos:** Venezuela 120\. Bueno, ahí está. Okay. Puede estar, puede estar,

### 00:17:47

**Jhony Alexander Alvarez Vasquez:** pero

**Cubakilos:** o sea, yo lo que digo es que para que me sea más fácil reconocer los productos, ¿entiendes? Porque, o sea, ahí yo leo DVD DV. Bueno, okay. Venezuela ve 100\. No entiendo, pero por ejemplo, en ese caso, ¿ves? Claro, ¿ves? Son los pesos venezolanos debe

**Jhony Alexander Alvarez Vasquez:** es que a mí me llega me llega muy poca información que en ese

**Cubakilos:** ser.

**Jhony Alexander Alvarez Vasquez:** Excel. M, bueno, lo único que me preocupó de esta parte es que Venezuela si tiene muchos más paquetes y aquí no me llegó.

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** Entonces y connecto lo miro muy rápido. Exact. Sí, sí, sí, sí. Faltan muchos. Faltan muchos. Yo me dejo esto pendiente. Encontro una discrepancia, pero revisa el API.

**Cubakilos:** ¿Estás usando

**Jhony Alexander Alvarez Vasquez:** Consulta,

**Cubakilos:** culsor?

**Jhony Alexander Alvarez Vasquez:** no, esto es GHub, no sé si verifica.

**Cubakilos:** Ah, sí.

**Jhony Alexander Alvarez Vasquez:** los datos que llegan de Venezuela. Veo muy pocos el panelamiento y

### 00:20:20

**Cubakilos:** tienen igual, o sea, Guij también permite eh integración de IA.

**Jhony Alexander Alvarez Vasquez:** sí, sí, a nivel de desarrollo si hay más más herramientas con

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** Gub.

**Cubakilos:** vale.

**Jhony Alexander Alvarez Vasquez:** Entonces,

**Cubakilos:** Entonces, para terminar el flujo, para terminarte describiéndote el flujo, lo ideal es, okay, país, tipo de producto,

**Jhony Alexander Alvarez Vasquez:** eh.

**Cubakilos:** a partir de ahí que me salgan los paquetes, obviamente de la manera eh me interesa eh lo que es, lo que recibe y el precio para, o sea, de una manera que yo pueda leerlo, ahí se puede alargar un poco el cuadro, eh, estirar horizontalmente para que se pueda leer más. para yo poder, o sea, leer más fácilmente y poder seleccionar los paquetes que me interesan. De ahí voy seleccionando para que luego sean los que voy a mostrar con el show co en la página que que queime, ¿no?

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, pasemos a eso. Esa esta parte que tú estabas mencionando justo al final, bueno, lo voy a dejar así. país, tipo de producto, lo que recibe, bueno, el mando,

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** el paquete y el precio.

### 00:21:25

**Jhony Alexander Alvarez Vasquez:** Eh, para esta parte que ves acá, voy a intentar crearte más filtro aparte tipo de paquete y país, que es lo que me habías comentado en la última reunión. Cuando tú eliges el paquete, ya vamos a pasar al plugin, o sea, empezar a organizar la información en el plugin y cómo la va a ver el cliente.

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** Entonces, si yo le doy aquí creas bondel desde el API, pues el de una va a mandarlo aquí. Ya hecho. Si tú le das que cargar en alta

**Cubakilos:** Sí, me gustaría más,

**Jhony Alexander Alvarez Vasquez:** manual.

**Cubakilos:** te digo, son detalles, pero me parecen más lo entiendo mejor si en vez de crear bounder desde API sea seleccionar

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, sí,

**Cubakilos:** producto,

**Jhony Alexander Alvarez Vasquez:** hay varios comentarios también por ahí que son muy técnicos, pero eso yo arraso con eso ya también.

**Cubakilos:** ¿vale?

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** O sea,

**Jhony Alexander Alvarez Vasquez:** crear

**Cubakilos:** seleccionar producto en vez por el botón que sea crear bundle es producto,

**Jhony Alexander Alvarez Vasquez:** eso.

**Cubakilos:** seleccionar producto. Yo digo, "Vale, yo quiero meter en la landing de Cuba en data, voy a o hacer un show call que sea de las de la SIM de turista. Selecciono la cada una,

### 00:22:26

**Jhony Alexander Alvarez Vasquez:** Okay.

**Cubakilos:** las tres. Una, dos, tres.

**Jhony Alexander Alvarez Vasquez:** Eh, ah, bueno, debes ir de a paquete, pero ya te voy a mostrar por qué.

**Cubakilos:** Sí, sí, sí.

**Jhony Alexander Alvarez Vasquez:** Entonces vamos a probar este 1 € Bueno, ya sé que son son dólares. Entonces, fíjate que aquí se saltó de buscar API alta manual. Entonces deja el país,

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** deja el nombre que con el que venía del API, el código que ese así es, ya te lo mostré el otro día también el valor y luego dólares,

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** el operador, eso es la información que me traje de allá, entonces no estoy muy seguro. Déjame a ver mientras esto consulta. Ya está haciendo pruebas. Sí. Y eh este beneficios recibidos lo trae después de este guion. Lo le he puesto lo trae, lo pone acá y aquí tú lo adaptas. Eh, elige, elige, elige un paquete. René prueba. Listo, le doy añadir. Y acá en landings, perdón, en bondes guardados, ya nos aparece el último paquete.

### 00:23:35

**Jhony Alexander Alvarez Vasquez:** Míralo. Aquí tú puedes seguir gestionándole y editándolo según lo que tú quieras. Mira, aquí sigue elige un paquete ren prueba. Listo, están los otros paquetes. Y aquí en landing es donde ya empezamos a a posicionar todos esos paquetes. En este caso vamos a crear solo uno y vamos a a escribir para prueba. Aquí prueba René. Este es el título. Entonces recibe 300\. Elige un y le damos crear shortcut de landing. Ya lo tenemos aquí para prueba, prueba René y ya tenemos el shortcode para agregarlo. Listo. Vamos a añadir una página nueva. Sí, seremos que ya haga pruebas. prueba REN y aquí un HTML. publicar. Vamos a la página. Entonces, bueno, obviamente con más diseño y demás, eh, ya ves, ahí trajo el título, trajo el subtítulo, es está el país, porque elegimos ese país o o si él agregamos más paquetes, pues él puede crear otros otros países también. agregamos más paquetes de otros países.

### 00:25:14

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** Digo, aquí colocamos un número y entonces ya empieza el paso a paso. Yo le he dado unas vueltas a esto, pero bueno, contigo yo creo que voy a decidir un diseño final. Entonces,

**Cubakilos:** Vale.

**Jhony Alexander Alvarez Vasquez:** eh, paquetes disponibles solo va a aparecer uno, obvio, ¿cierto?

**Cubakilos:** Sí, porque se le seleccionó se seleccionó uno

**Jhony Alexander Alvarez Vasquez:** Agreguemos uno. Sí, porque en el Exacto. Aquí elegimos uno solo y bueno,

**Cubakilos:** solo.

**Jhony Alexander Alvarez Vasquez:** ya como la tarjeta principal. Si yo le doy continuar, ya se va a parar la confirmación de recarga. Entonces si le doy aquí confirmar, creo que el número este no es válido. Ah, bueno, el número valida todo. Queríamos probar un número de Venezuela. Bien.

**Cubakilos:** Sí, ahí lo que veo es elige un paquete. Ah, claro, esa es la descripción como tal del producto. Ya. Sí, sí,

**Jhony Alexander Alvarez Vasquez:** Sí,

**Cubakilos:** sí,

**Jhony Alexander Alvarez Vasquez:** eso lo pusimos manualmente.

**Cubakilos:** sí,

### 00:26:11

**Jhony Alexander Alvarez Vasquez:** Gracias.

**Cubakilos:** sí,

**Jhony Alexander Alvarez Vasquez:** Es que siempre está esa conexión entre tu edición y el front y

**Cubakilos:** sí. Porque te explico por qué va a ser porque mira, por ejemplo, yo puedo seleccionar el vea los paquetes de nuevo de Cuba. Lo vas a entender rapidísimo porque es que eh es por eso porque el paquete básico de Cuba, por ejemplo, digamos, el de 500\. Ajá. Venezuela. Ajá. Ahí ahora Cuba. Vamos a decir que Ajá. ¿Quieres topop? Exacto. Topop. Vale, baja.

**Jhony Alexander Alvarez Vasquez:** ¿Y

**Cubakilos:** Eh, 600\. Uy, ¿cuál es el V? Ahí me pierde. ¿Cuál es el Es el que es de

**Jhony Alexander Alvarez Vasquez:** qué buscarías inicialmente?

**Cubakilos:** 500 pesos? ¿Cómo se llama él? Vale. Estop. Sí, stop. Está raro que no está no est No,

**Jhony Alexander Alvarez Vasquez:** De pronto por la conversión a dólares y a ver si es 500 debería ser este,

**Cubakilos:** es el código. Creo que el SKU,

### 00:27:23

**Jhony Alexander Alvarez Vasquez:** ¿no?

**Cubakilos:** si vas al código, el SKU es TP, es to. Vea el cheat,

**Jhony Alexander Alvarez Vasquez:** Oh.

**Cubakilos:** vea el cheat, porque a lo mejor esto sirve para saber que hay cosas que no están llegando. Vale, Cuba, estás en en Topía Cuba Cuba. Ahí está. Es el CU, míralo ahí.

**Jhony Alexander Alvarez Vasquez:** Este

**Cubakilos:** Ahora está. Míralo ahí. Ese mismo es. Ese yo ahora lo puedo, yo voy a seleccionar, por ejemplo, ese tres veces y uno lo voy a poner con un coste de que le llegue a DIN de 22 € uno con 2670 y otro con 32\.

**Jhony Alexander Alvarez Vasquez:** Okay.

**Cubakilos:** ¿Entiendes?

**Jhony Alexander Alvarez Vasquez:** Aunque yo tengo una opción de seguridad entre comillas, pero bueno, ya que me dices eso de que no se pueda crear más de uno porque ya está

**Cubakilos:** Claro, porque no sí porque mira,

**Jhony Alexander Alvarez Vasquez:** repetido.

**Cubakilos:** yo eso lo que voy a hacer es si vas a a cómo decirte e es ese paquete eso es el famoso de las promociones que son que es el básico y entonces en dependencia del monto que tú le

**Jhony Alexander Alvarez Vasquez:** Ok.

**Cubakilos:** pongas la promoción se duplica, se triplica, ¿entiendes?

### 00:28:52

**Cubakilos:** Por ejemplo, por 22 € si vas a DIN,

**Jhony Alexander Alvarez Vasquez:** Mhm.

**Cubakilos:** abre tu pestaña de din, o sea, de tu cuenta de DIN. Ahora busca enviar una recarga. Ajá. Ahí vale. Pon Cuba 402, no sé cualquier 402\. 4709\. Ajá. No, ahí ya tienes número. 3409\. Vale, ya listo. Va a ser Cuba. Es ese, es este el que va a ser Cuba, el primero. Vale, ve va desde C desde 5 hasta 52\. ¿Entiendes? El monto que tú puedes ingresar. Si tú le pones 22 € Exacto.

**Jhony Alexander Alvarez Vasquez:** que sería acá.

**Cubakilos:** Si yo si yo lo creo con 22 € ve ahí a por ejemplo a Ending, ¿vale? Sí. Si tú le pones 22 € das clic fuera, te van a llegar, por ejemplo, 526 CP. Pero la cuando la promoción está activa, ese 22 € te va a dar, por ejemplo, 500 CUP, pero te va a dar 30 GB. Pero si ese mismo ahí mismo tú pones en B2, pones 26 70, por ejemplo, te va a dar, vamos a decir eh 600 pesos, pero 60 GB de dato,

### 00:30:20

**Jhony Alexander Alvarez Vasquez:** Hm.

**Cubakilos:** ¿entiendes? Entonces,

**Jhony Alexander Alvarez Vasquez:** M.

**Cubakilos:** yo utilizaría este producto en tiempos de promoción, yo lo seleccionaría y lo seleccionaría en lo haría como tres veces, ¿no? Repetido, pero cambiándole los montos de ingreso, donde primero sería 22, le cambio el la descripción, lo que recibes, después lo vuelvo a hacer donde le pongo 26 € y te cambio la la descripción de lo que recibes, ¿me entiendes?

**Jhony Alexander Alvarez Vasquez:** y eso es algo como establecido así muy puntualmente o de repente ellos tienen promociones, de repente no.

**Cubakilos:** Eh, todos los meses hay dos dos al menos dos promociones al mes.

**Jhony Alexander Alvarez Vasquez:** Pero aleatorio, ¿cierto? cualquier paquete. Sí,

**Cubakilos:** Ese ese es básico. Ese básico. O sea, ellos tienen ellos tienen productos fijos, por ejemplo, los bonders. Si vas a los bond ve change operator.

**Jhony Alexander Alvarez Vasquez:** cualquier

**Cubakilos:** Change operator. Ve, mira, esos son fijos que son el producto bondo. Estos son fijos. ve que tienen predefinido 20 GB, 165 minutos, etcétera.

### 00:31:26

**Cubakilos:** Pero si vas ahora exchange operator y le das al producto que es eh por ejemplo W turismo son tres, por ejemplo, dar la turismo y sin ese mismo v tienes tres paquetes o el de turismo, ¿ve? Tienes tres paquetes predefinidos de data, ¿viste? El producto, el producto es data, tipo de producto. Ahora, si vas al Qaceler, chain operator, cubacel Cuba, ese es el producto recarga. Entonces, como ves, oscila desde 5 hasta 52\. Pero entonces ellos todos los meses, desde hace años ese producto lo que hacen es que eh varía la promoción que dan. Por ejemplo, si pones 22 € recibes 500 pesos y y 20 GB de datos más 120 minutos, pero ahí mismo, pero si lo pones en si pones 30 € vas a recibir lo duplicado, no más todavía. Pero entonces dentro de 15 días cambia la promoción y lo que cambia es si pones 22 € ahora ya no recibe, no sé, tal cosa, recibes 200 GB, 200 GB.

**Jhony Alexander Alvarez Vasquez:** Vale.

**Cubakilos:** Por eso es la cuestión de yo poder, o sea, selecciono el producto, pero sé que en dependencia del monto varía lo que recibe el cliente y por eso es que te decía que yo tenía que ser capaz de editar los beneficios.

### 00:32:58

**Cubakilos:** No sé si puedes abrir, abren otra pestaña. Eh, por ejemplo, eh, dimecuba.com que te lo enseñé el otro día en una pestaña. Dime, ese mismo ahí lo tienes, ¿vale? Y a ver si lo tienen. Ve, selecciona un plan, ¿viste? Mira, eso es, mira, el básico, 250 CP. Eso es si ponen 5 € la idea. Es el mismo producto. Si pones y si yo le pongo en el producto 5 € vas a recibir 250 CP. Pero si te pongo el mismo producto, el monto que yo ingreso que le llega a DIN es eh 10 € reciben 1250, ¿entiendes?

**Jhony Alexander Alvarez Vasquez:** Okay. Sí.

**Cubakilos:** Pero pero entonces yo tengo que yo puedo yo tengo que ser capaz de elegir el producto en en el en el en plugin nuestro. Si vas al pluin, yo voy al pluin y te digo, déjame seleccionar un producto eh al inicio, por ejemplo, o bueno, si ya lo seleccioné, después lo que lo ha duplicar, pero bueno. Que ya lo seleccioné su, ¿verdad? Ese que el primero, el saldo topop 1\.

### 00:34:02

**Jhony Alexander Alvarez Vasquez:** Sí,

**Cubakilos:** Vale, ya lo tenemos seleccionado, guardado. Vea guardado.

**Jhony Alexander Alvarez Vasquez:** inclusive inclusive aquí ya lo dupliqué.

**Cubakilos:** Vale, exacto. Ve, ahora edito el primero. Ajá. Ve, el primero está bien. Ve, 22 € Abajo reciben, ponle, en vez de que recibe vas a recibir eh 100\. Ajá. recibe 1000 bl cualquier cosa lo guardo y ahora vengo al otro que por ejemplo ajá voy al de abajo que es el de ese. Ahora lo ese loito que es el mismo producto y digo, "Vera, ahora sí, pero el monto que va a llegar a DIN no va a ser va a ser en vez de 22 € va a ser digamos 5 € y vas a recibir xzz porque es menos dinero del monto, ¿entiendes?

**Jhony Alexander Alvarez Vasquez:** Vale. Sí,

**Cubakilos:** O sea, el producto es el CUCU top,

**Jhony Alexander Alvarez Vasquez:** sí.

**Cubakilos:** como lo ves ahí en el SKU, el producto, pero en dependencia del monto que yo le ponga DIN, cambian los beneficios. Creo que así está ya bastante más claro,

**Jhony Alexander Alvarez Vasquez:** Sí, sí, claro.

### 00:35:05

**Cubakilos:** ¿no?

**Jhony Alexander Alvarez Vasquez:** Y y es porque es esa flexibilidad que tú puedas manejar esas versus las promociones que suelen llegar de Ding.

**Cubakilos:** Sí, que yo las que conozco, esto nada más se aplica de lo que yo conozco a este producto de Cubacel en Cuba. Yo no sé si eso aplica también en Colombia o pero como te enseñé, por ejemplo, en el mismo Cuba, en los otros tipos de productos son fijos las ofertas. Pero es que en ese producto específico de Cuba eh se lanzan constantemente promociones que son las que los clientes mayormente cobran compran, ¿no? Y van

**Jhony Alexander Alvarez Vasquez:** Vale, de momento,

**Cubakilos:** variando.

**Jhony Alexander Alvarez Vasquez:** ¿qué tanto nos acercaríamos entonces a eso? Cuando yo me refiero a que, o sea, tú puedes crearle al landing y asignar los paquetes,

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** inclusive después puedes editar y ya decir, no, ya no es este paquete de 22,

**Cubakilos:** Perfecto. Mira, vamos a hacerlo ahora mismo.

**Jhony Alexander Alvarez Vasquez:** sino que ya es el de el de CCO.

**Cubakilos:** Sí, vamos a hacerlo ahora mismo. Mira, eh,

**Jhony Alexander Alvarez Vasquez:** va a

**Cubakilos:** prueba para Cuba. En vez de recibe Venezuela,

### 00:36:07

**Jhony Alexander Alvarez Vasquez:** creer.

**Cubakilos:** vamos a hacerlo para Cuba. Ajá. No sé. O edita un mismo show code ahí que tengas.

**Jhony Alexander Alvarez Vasquez:** Sí, sí,

**Cubakilos:** Ese mismo.

**Jhony Alexander Alvarez Vasquez:** este puede ser.

**Cubakilos:** Vale. Selecciona los dos de Cuba.

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, estos

**Cubakilos:** Vale. No sé.

**Jhony Alexander Alvarez Vasquez:** dos

**Cubakilos:** Ahí estaría bueno que se pudieran ordenar, pero bueno, para que la gente lo viera como de menor a mayor, pero vale.

**Jhony Alexander Alvarez Vasquez:** aquí se puede ordenar que el

**Cubakilos:** Eh, sí, en el desplegable ahora e que va a ver el cliente,

**Jhony Alexander Alvarez Vasquez:** valor

**Cubakilos:** porque por ejemplo primero ve primero el paquete más barato, después uno más caro, uno más caro, uno más caro.

**Jhony Alexander Alvarez Vasquez:** Ah, sí, sí.

**Cubakilos:** Vale, ya seleccionaste dos.

**Jhony Alexander Alvarez Vasquez:** Segundito,

**Cubakilos:** Ahora tú le dices ahí en

**Jhony Alexander Alvarez Vasquez:** me anoto esa parte que si me interesa también de

**Cubakilos:** sí,

**Jhony Alexander Alvarez Vasquez:** landing

**Cubakilos:** sí, que yo pueda yo pueda ordenar la la el orden de aparición en el desplegable de después

**Jhony Alexander Alvarez Vasquez:** en

**Cubakilos:** como se ve en la en la landing comercial, ¿no?

### 00:37:10

**Jhony Alexander Alvarez Vasquez:** se pueda ordenar el front.

**Cubakilos:** seleccionar el orden,

**Jhony Alexander Alvarez Vasquez:** Listo. Sí,

**Cubakilos:** ¿vale?

**Jhony Alexander Alvarez Vasquez:** sí.

**Cubakilos:** Por ejemplo, vale,

**Jhony Alexander Alvarez Vasquez:** Solo puedes poner tres rayitas y uno los va moviendo para arriba y para

**Cubakilos:** vale. Ahora, por ejemplo, cambia,

**Jhony Alexander Alvarez Vasquez:** abajo.

**Cubakilos:** recibe para hacerlo, o sea, para que para que recibe arriba en 300 Venezuela. Bueno, ahí nos recibes.

**Jhony Alexander Alvarez Vasquez:** Ah,

**Cubakilos:** Ahí sería,

**Jhony Alexander Alvarez Vasquez:** sí.

**Cubakilos:** eh, imagínate tú, elige tu paquete o algo así, ¿no? Ah, no, elige paquete, título del formulario, envía recarga Cuba y eh recarga Cuba. Quítale, quítale elige paquete para que se quede debajo para que ya quede. Quítale paquete también y ahí en envía. Ya, listo. Guardar cambio. Ya vamos a la web. Refrescamos. Listo. Envía recarga Cuba. Genial. Elige el paquete. Bueno, ahí sería introduce tu número. Introduce el número a recargar.

### 00:38:09

**Cubakilos:** El subtítulo. Ahí yo pondría.

**Jhony Alexander Alvarez Vasquez:** Ah,

**Cubakilos:** Yo lo que pongo es introduce el número que quieres recargar,

**Jhony Alexander Alvarez Vasquez:** sí,

**Cubakilos:** ¿me entiendes?

**Jhony Alexander Alvarez Vasquez:** correcto. Bueno, esa esa parte ya es editable. Sí,

**Cubakilos:** Eso es una bobería. Vale, listo. Ve, elige el paquete y mira,

**Jhony Alexander Alvarez Vasquez:** sí.

**Cubakilos:** paquetes disponible. V, te sale el selector donde ves si lo ordeno más bonito que quede primero 5 € y después 22\. O por ejemplo, yo puedo poner, como yo lo puedo ordenar, yo puedo ponerte de primero el 22 porque es el que me interesa a lo mejor que recargues, ¿no? Pero bueno, si te fijas ahora y vas a Dime Cuba,

**Jhony Alexander Alvarez Vasquez:** Hm.

**Cubakilos:** ve ahora la otra página de Dime Cuba que tienes en una pestaña, darle desplegable, ¿ves? Ahí están los paquetes que seleccionaste, ¿ves? Fíjate que ellos no te están ellos no te están ofertando ahí. Todos esos paquetes los editaron ellos. Eso no son los bundel, si te fijaste, nos fijamos,

### 00:38:56

**Jhony Alexander Alvarez Vasquez:** Mhm.

**Cubakilos:** es eh los que dicen que 16 minutos más llamada, más SMS. Esto es el mismo producto de CUC Topop, lo que ellos cambian el monto que tiene que introducir en DIN y en dependencia de eso es el beneficio que te va a dar. V.

**Jhony Alexander Alvarez Vasquez:** Sí. Y ya tú lo adaptas comercialmente para que se haya

**Cubakilos:** Exactamente, porque ese que te interesa vender, el que no te interesa vender, el que se vende más,

**Jhony Alexander Alvarez Vasquez:** más.

**Cubakilos:** el que se vende menos. Por ejemplo, la gente no compra, no compra. El que siempre casi siempre compra es el primero, el de 500, el último. Fíjate que lo tienen recomendado porque no es recomendado.

**Jhony Alexander Alvarez Vasquez:** Ah,

**Cubakilos:** Ese es el que compra la gente siempre.

**Jhony Alexander Alvarez Vasquez:** este

**Cubakilos:** Por eso le ponen recomendado, porque los otros son más caros. Los otros son 30 y pico de euros.

**Jhony Alexander Alvarez Vasquez:** ah, yo te puedo colocar un como aquí como un check de una estrellita o algo para que Bueno, aquí como algo para destacar este que se pinte también ahí un poco.

**Cubakilos:** Pero fíjate que ya si comparas eso con lo que tú hiciste, ya lo tuyo ya ya es eso.

### 00:40:04

**Cubakilos:** Lo que tú hiciste ya es eso. Es eso mismo. Ve ve al frontend.

**Jhony Alexander Alvarez Vasquez:** Hm.

**Cubakilos:** Míralo ahí. selecciona tu paquete. Mm. Y hay operador CCU, eso me sobra porque se sabe que operador, además no es CUCU, sería Cubacel, pero bueno. Ah, bueno, digo el operador,

**Jhony Alexander Alvarez Vasquez:** Este,

**Cubakilos:** creo que lo de arriba es el texto que yo puedo editar, pero el operador abajo si quieres lo puedes quitar o bueno, ¿verdad? Porque en otro país sí hay varios operadores. Puede ser,

**Jhony Alexander Alvarez Vasquez:** mm,

**Cubakilos:** da igual, eso ya es otro detalle final.

**Jhony Alexander Alvarez Vasquez:** de todas maneras ese esta parte de operador, no sé si viste esa la puedes la puedes poner poner aquí en en los

**Cubakilos:** Ah,

**Jhony Alexander Alvarez Vasquez:** paquetes.

**Cubakilos:** míralo ahí. Ahí sería por ejemplo.

**Jhony Alexander Alvarez Vasquez:** Uy, uy, me salió un error. Ya existe otro con el mismo. Ah, bueno, eso es lo que tengo que que deshabilitar.

**Cubakilos:** M.

**Jhony Alexander Alvarez Vasquez:** Lo anoto de una vez.

**Cubakilos:** Ahí lo que estoy viendo, por ejemplo, que sería otro plus, si entras al momento de seleccionar la landing en landings.

### 00:41:14

**Jhony Alexander Alvarez Vasquez:** Un segundito, sin necesidad.

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** Esper un segundo que se me está juntando ya. Ahí ya me está vibrando el celular. M Jimmy. Ay, no estaba en Jimmy, un favor, dígame. ¿Puedes ir mandándole el enlace a este señor que que había pedido reunión?

**Cubakilos:** Ah.

**Jhony Alexander Alvarez Vasquez:** Dígale que unos 10 minutos más, no más. ¿Cuál le mando? Eh, un meet, un mid para que sepa que nos vamos a hablar por ahí. Ah, ya creo. Dale, gracias. Listo, ya. Eh, espérame un segundo. Poder crearitos necesidad necesario validar duplicados en paquetes. Listo. Ahora sí. Y okay. ¿Qué me ibas a

**Cubakilos:** Vale, en landings,

**Jhony Alexander Alvarez Vasquez:** decir?

**Cubakilos:** o sea, un plus, un plus ahí sería que en Landings yo pudiera eh filtrar también los paquetes eh que tengo eh como tal disponibles, seleccionados anteriormente, porque si te lía que ahora yo o sea, el día de mañana eh empiezo a crear landing, por ejemplo, los vouchers para Venezuela, los vouchers para Colombia, los topó para Venezuela, los tocó para Colombia, los de Cuba, los de, por ejemplo, Gucard de España.

### 00:42:43

**Cubakilos:** Y entonces cuando yo llegue aquí a esta a estas pestañas y empiezo a seleccionar para crear short go, si te fijas ahí en el listado arriba, no, ahí no sería arriba, ahí me voy a volver loco,

**Jhony Alexander Alvarez Vasquez:** Ah, ¿quién

**Cubakilos:** ¿entiendes? O sea,

**Jhony Alexander Alvarez Vasquez:** es?

**Cubakilos:** ahí lo ideal sería igual el mismo filtro que tienes anteriormente de tipo de producto con país y ya sabes, o sea, ve a catálogo y alta,

**Jhony Alexander Alvarez Vasquez:** filtrar.

**Cubakilos:** catálogo y alta, ve agregar ahí país y tipo de paquete para que entonces, o sea,

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** el filtro de país y tipo de paquete para que entonces en landings cuando yo seleccione cuando Yo seleccione ante, ¿entiendes? Subtítulo del formulario.

**Jhony Alexander Alvarez Vasquez:** esta lista esta lista va a ser muy larga.

**Cubakilos:** Claro, porque si no me van a salir miles. Bueno, miles no, que crees 40\. Pero, por ejemplo, entonces si yo ahí en

**Jhony Alexander Alvarez Vasquez:** Entonces,

**Cubakilos:** Ajá.

**Jhony Alexander Alvarez Vasquez:** yo necesito darte acá una consistencia también en eso. Eh, creo que está bien de momento lo que había yo hecho pensado antes, me refiero a esto que ah, no, aquí no está.

### 00:43:54

**Jhony Alexander Alvarez Vasquez:** Vamos, eh, a ver si acá sí. Entonces, tú a medida que vayas guardando los operadores, él te va también añadiendo a la lista para que mantengas el nombre correcto. ¿Para qué te estoy diciendo eso? Porque cuando vayas aquí al landing, esos datos son los que se van a poder filtrar aquí también.

**Cubakilos:** Pero ahí en Landing, no sé No sé si esto, me imagino que sí es posible, pero yo yo lo que te pido nada más es si tú vas a landing, ves a landing, aquí lo que me aparece a mí es el listado de todos los productos que yo he seleccionado anteriormente.

**Jhony Alexander Alvarez Vasquez:** Sí. ¿Qué creaste?

**Cubakilos:** ¿Vale?

**Jhony Alexander Alvarez Vasquez:** Ajá. Los

**Cubakilos:** Exacto. Ahora, si yo voy arriba un momentico,

**Jhony Alexander Alvarez Vasquez:** van.

**Cubakilos:** entonces antes de nombre del objetivo o luego del nombre, como sea, pones un dos filtros, dos electores, país y producto y tipo de producto. Y entonces ahí yo digo, voy a crear el show code de Cuba. Selecciono Cuba y

**Jhony Alexander Alvarez Vasquez:** Y acá esta lista se te filtra. Ah,

**Cubakilos:** Exactamente.

**Jhony Alexander Alvarez Vasquez:** sí, sí, sí.

### 00:44:58

**Jhony Alexander Alvarez Vasquez:** No, no, estamos estamos en sintonía.

**Cubakilos:** Exacto.

**Jhony Alexander Alvarez Vasquez:** Es que para poder eh para poder filtrar eso, los nombres y demás tienen que ser consistentes acá de lo que tú vayas guardando. Entonces yo, ah, bueno, de momento te creo es Cuba, eh, el país y te serviría de país y tipo de

**Cubakilos:** y tipo de producto.

**Jhony Alexander Alvarez Vasquez:** producto.

**Cubakilos:** Ya con esos dos, yo pongo Cuba y pongo, por ejemplo, Topop y ya ahí en el listado de abajo me va a salir los productos de Topop y de Cuba que yo

**Jhony Alexander Alvarez Vasquez:** To.

**Cubakilos:** seleccioné previamente. por supuesto en los bundes guardados. Y ahí yo dije, "Okay, ahora voy a decirle, ¿vale? Este se va a llamar eh nombre Pepito, clavec." Bueno, clave ch. El nombre del objetivo va a ser tops para Cuba. Clave show code tocó Cuba. Título del formulario, envía tu recarga a Cuba. Subtítulo, elige, introduce el número que quieres encargar y selecciono los dos productos de Topop o los tres que me interesan y creo show cup. Y luego digo, voy a crear ahora los vouchers. para Cuba, creo un nuevo landing aquí y subo arriba y digo, vuelvo a filtrar por Cuba y por el tipo de producto vouchers.

### 00:46:08

**Cubakilos:** Y entonces aquí me quedan los vouchers que ha seleccionado previamente de Cuba. Y el nombre del objetivo aquí va a ser vouchers para Cuba. Clave show code voucher Cuba. Título del formulario, envía tus tarjetas de regalo, no sé qué más. Subtítulo, elige el correo electrónico el que quiere que llegue el regalo, porque las giftcard llegan por correo electrónico. Selecciono las tres giftcard que me interesa, los vouchers y creo el show code del landing. Sigo siguiente paso, eh, crear un nuevo landing para eh Colombia. Selecciono país, Colombia. tipo de producto eh eh voucher y vengo nombre del objetivo, eh voucher Colombia, show coach Colombia, título del formulario, regala tu tarjeta de regalo a tu familiar en Colombia, subtítulo e introduce el correo electrónico de tal y debajo me me salen todos los vouchers de Colombia y creo mi show co y todo y después voy introduciendo en cada landing comercial. M.

**Jhony Alexander Alvarez Vasquez:** Vale,

**Cubakilos:** Ese sería el flujo de de trabajo

**Jhony Alexander Alvarez Vasquez:** hagámoslo así.

**Cubakilos:** ahí.

**Jhony Alexander Alvarez Vasquez:** Parece excelente esa esa aclaración. Ya habían varias cosas que yo sí decía, bueno, se tiene que hacer mucho mejor, pero sí es eso lo que tenemos que que adaptar.

### 00:47:26

**Jhony Alexander Alvarez Vasquez:** ¿Vale? Entonces, mira, ¿qué hacemos hoy? Eh, ya te voy a agregar eso. Eh, bueno, lo que hablamos ahora sobre el tema de de los filtros y voy a estudiar un poco esta parte versus el API, porque como ves acá yo juego es con los datos que me llegan de D connectudo. Entonces, con base a esto es que yo empiezo a hacer cálculo y le digo a la idea que compare, que que busque o que bueno. Entonces, a ver hasta qué punto podemos tenerlo como en este Excel. Eh,

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** aunque bueno, después de que te tú reconozcas el paquete, pues ya es darle un nombre y darle nombre y darle el nombre llamativo y todo y todo lo demás. Es después de ahí es cuando ya se identifica mejor la cosa.

**Cubakilos:** Sí, ahí sería país y tipo de producto y y después abajo, bueno,

**Jhony Alexander Alvarez Vasquez:** Exacto.

**Cubakilos:** que tratar de traer la columna que es la primera y la de receif y la del precio para así, bueno, la persona que vaya a seleccionar los productos, pues le sea más sencillo entenderlo, ¿no? Porque ahora sería un FCU. Okay.

### 00:48:42

**Cubakilos:** Ya el Cuba Cubac Touris más o menos, pero pero igual si lo comparas con lo que dice el Google Cheat,

**Jhony Alexander Alvarez Vasquez:** Correcto.

**Cubakilos:** con lo que dice el cheat, es mucho más sencillo entenderlo. Por ejemplo, ahí sería filtrar por Cuba, pero por ejemplo eh en el otro que teníamos, ¿ves? Ahí me va a decir, eh, por ejemplo, si yo selecciono, ¿ve? Por ejemplo, el data, el primer data, ahí me va a llegar Cuba Modern Data Cuba y eh al guion promo datos

**Jhony Alexander Alvarez Vasquez:** Ay, ¿sabes? Ahora que me estás diciendo eso,

**Cubakilos:** y

**Jhony Alexander Alvarez Vasquez:** yo podría utilizar este este Excel de referencia y manejar los SC, porque esos SC sí son únicos.

**Cubakilos:** sí.

**Jhony Alexander Alvarez Vasquez:** Creo que se me acabas de iluminar algo. Eh, ¿a qué voy? A que podría usar esto como una base de datos. Ingresarla ahí no cuesta nada. Lo que sí tendría que hacer es que cada SC me tragues esta información.

**Cubakilos:** Sí, lo que te interesa es el operador guion,

**Jhony Alexander Alvarez Vasquez:** De pronto debe de pronto eso,

**Cubakilos:** el res gu

### 00:49:46

**Jhony Alexander Alvarez Vasquez:** el precio, el precio,

**Cubakilos:** precio.

**Jhony Alexander Alvarez Vasquez:** el precio no lo traería ahí porque ese sí me interesa que venga siempre actualizado,

**Cubakilos:** Claro. Sí, por

**Jhony Alexander Alvarez Vasquez:** pero el lo informativo, ay, ya sé qué hacer, ¿no? Ya, ya creo que te voy a solucionar esta parte más más fácil porque no lo había pensado por ese lado.

**Cubakilos:** supuesto.

**Jhony Alexander Alvarez Vasquez:** No sé si sabes a qué me estoy refiriendo, que yo me ponía me ponía a jugar con esto, que son los datos que llegan de cada consulta y aquí por ejemplo hay un band, este es el sc y mira la información que trae. Si yo utilizo ese Excel para coger este bandle y decirle, "No, es que esto no se llama C9SU, sino que se llama Roblox tal cosa, o sea, ya el nombre correcto que tú reconoces fácil, ahí sí.

**Cubakilos:** Claro.

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** Total.

**Jhony Alexander Alvarez Vasquez:** lo que puedo hacer es habilitar de nuevo ese esa opción de de actualizar Excel, pero es para que de vez en cuando traigas los datos por si algún algún nombre ha cambiado. Pero bueno, creo que si sabes a qué voy con esa parte de para poder lograr filtrar como tú quieres más fácil, porque si yo utilizo el SC que viene del API y le digo, "Mira, es que cada SC tiene sus datos, no son los de API,

### 00:51:05

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** son estos que venden en este Excel." Ahí sí vamos a ser muy muy mucho más fijos, mucho más exactos, porque mira que el nombre de acá es muy técnico. Mira, 1 Hu F y puedes reconocer en cierto punto,

**Cubakilos:** Claro.

**Jhony Alexander Alvarez Vasquez:** pero lo mejor yo creo que mejor cojo este este SC y le traigo esta información.

**Cubakilos:** Claro que a que ahí la información que me interesa es, por ejemplo, operador guion receif, sería la fórmula es operador guion receif. gu el precio que es el

**Jhony Alexander Alvarez Vasquez:** Ah, bueno,

**Cubakilos:** seno que o sea que el precio sí lo traes de de de la API,

**Jhony Alexander Alvarez Vasquez:** este. Okay.

**Cubakilos:** pero te digo por por el diseño que tú tienes, si vas al al a tu al plugin, el plugin B, por ejemplo, operador guion receive gu

**Jhony Alexander Alvarez Vasquez:** Listo.

**Cubakilos:** precio reive a un

**Jhony Alexander Alvarez Vasquez:** Al final hay otros datos a veces,

**Cubakilos:** precio

**Jhony Alexander Alvarez Vasquez:** pero bueno, creo que va a agarrar los datos del Excel mejor.

**Cubakilos:** y no sé si eso si eso influye en algo, pero si las consultas en buscar en API van más filtradas no son mejores, porque por ejemplo que yo filtre siempre primero por el país, después por el tipo de producto y le dé filtrar, no es mejor, o sea, para consultar, para que lleguen menos productos en la consulta, etcétera.

### 00:52:34

**Jhony Alexander Alvarez Vasquez:** Ah, no, eso se va a mantener, sino que ya internamente creo un script que traiga esta información y la la coloque según la información que hay acá, pero no, esto sigue funcionando, solo que estos datos que ves acá que DFHU, esto esto Sí,

**Cubakilos:** Sí,

**Jhony Alexander Alvarez Vasquez:** ya no va a ser tanto de esa manera, sino que va a ser lo que está en el Excel.

**Cubakilos:** sí.

**Jhony Alexander Alvarez Vasquez:** a la parte como comercial o informativa lo traemos del Excel que le pongamos de vez en cuando y del API va a traer el SC y el precio. Entonces,

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** aunque bueno, aquí parece muy completo, sí, pero es que aquí tú me mostraste ayudándome a filtrar, encontramos información mucho mejor en el tipo de producto. Inclusive podemos hacer esto más gradual. Si tú me dices, "Joh, no, mira, es que aquí está ya perfecto y eso no esto no viene en el API porque ya hemos visto que no está en el API. Entonces yo simplemente cojo el Excel es para saber qué SQU es qué tipo de

**Cubakilos:** Sí, sí. El tipo de producto ayuda mucho porque eh bueno,

**Jhony Alexander Alvarez Vasquez:** producto.

**Cubakilos:** por supuesto yo voy a poder decir, me interesa seleccionar voucher, ¿entiendes?

### 00:53:49

**Cubakilos:** Otop. Y entonces a partir de ahí se desplegan nada más esos paquetes, esos

**Jhony Alexander Alvarez Vasquez:** Vea esto, esto no creo que me inter.

**Cubakilos:** productos,

**Jhony Alexander Alvarez Vasquez:** Ah, bueno, de pronto esto sí es la validez. La validad también suele revisarla bastante porque un

**Cubakilos:** eh, pues de momento no lo tengo en la No tengo Pon

**Jhony Alexander Alvarez Vasquez:** paquete 10, por ejemplo,

**Cubakilos:** Cuba.

**Jhony Alexander Alvarez Vasquez:** este r es

**Cubakilos:** Pon Cuba, Afganistán. Ni idea. Sería a

**Jhony Alexander Alvarez Vasquez:** mejor

**Cubakilos:** Cuba.

**Jhony Alexander Alvarez Vasquez:** Cuba. Ah, es que estoy en los

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** paquetes.

**Cubakilos:** Vale, si vas ahí pon los topop. Creo que es donde aplica. Ajá. Vale, ahí. Ajá. Sí, sí. Está bien. ¿Ves? Aplica en el nauta. Sí, claro. En el internet, en los bondos. Y en el Nauta.

**Jhony Alexander Alvarez Vasquez:** Se apenas se compra tiene 30 días para usarlo, ¿cierto?

### 00:54:53

**Cubakilos:** Sí, exactamente, exactamente. Que normalmente lo dice en la descripción.

**Jhony Alexander Alvarez Vasquez:** Vale.

**Cubakilos:** No, no.

**Jhony Alexander Alvarez Vasquez:** ¡Uf\! Pero yo por no había pensado eso antes, pero sí puedo utilizar el mismo Excel para alimentar la información y no no tener algo en paralelo. Comisión, si no porque eso ya lo manejas en connect. Okay. Entonces, mira, lo que estás viendo en este momento, yo lo que voy a hacer es coger el SC y llevarlo al sistema. el precio,

**Cubakilos:** Mhm.

**Jhony Alexander Alvarez Vasquez:** el precio y demás información más técnica, pues bueno, sí va a llegar, pero ya vas a poder utilizar este. Me lo guardo y con eso creo como un directorio para que cada vez que le llegue la un él sepa ah, este SC es de Afganistán, tiene $10, es un voucher y es de país Afganistán. Ah, bueno, este es el operador y lo ponemos y ahí y ya agregamos los filtros que tú que tú tenías de interés aquí.

**Cubakilos:** Sí, que ahí el filtro es país y el tipo producto.

**Jhony Alexander Alvarez Vasquez:** Sí, sí,

**Cubakilos:** Y ya debajo que se lea mejor lo que lo que voy a seleccionar.

### 00:56:11

**Cubakilos:** Ya después ahí yo selecciono,

**Jhony Alexander Alvarez Vasquez:** correcto.

**Cubakilos:** paso a productos seleccionados que entiendo que ahí es donde yo lo editaría con lo que va a recibir todo. Por ejemplo, edito el el de Cuba, el de 22 € Vale, para que llegue a DIN. Ahora lo que yo no sé si ese monto que tú pones ahí es el que le llega DIN.

**Jhony Alexander Alvarez Vasquez:** Ese monto es el que paga el

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** cliente

**Cubakilos:** pues entonces ahí hay que tener ya dos montos, dos campos de monto.

**Jhony Alexander Alvarez Vasquez:** para que tú puedas ver el valor que que vas a pagar y que vas a

**Cubakilos:** Claro, porque Claro,

**Jhony Alexander Alvarez Vasquez:** cobrar.

**Cubakilos:** porque eh por ejemplo si yo en el monto, vamos a decir que ese monto sea el que le llega a DIN, si yo a ese monto que de hecho eh el monto es el precio, debe debe venir de din mismo, del producto. De hecho, ese monto debe ser precio, por ejemplo, o monto va, ¿okay? Va a significar el el precio que cuesta ese producto en din. Esto, esto es importante. Esto es importante.

**Jhony Alexander Alvarez Vasquez:** Sí, sí,

**Cubakilos:** Aquí hay dos, van a haber dos campos.

### 00:57:14

**Jhony Alexander Alvarez Vasquez:** sí.

**Cubakilos:** Uno que es el monto, que es el coste. Acuérdate que di me cobra, los productos tienen un coste que por ejemplo en este caso puede ser cinco, como es el cubacet básico, empieza en 5.5 € Lo que pasa es que después cuando yo le compro ese producto a DIN, DIN me da una comisión, digamos, del 14, 15, 20%. Pero si yo a ese monto yo le pongo, por ejemplo, en vez de 5,5, lo que hablamos ahorita, le pongo 22 din eh va a ponerme otro producto más caro, o sea, le va a poner al cliente otros beneficios. Entonces, yo necesito que tú agregues otro campo que sea el precio de venta mío.

**Jhony Alexander Alvarez Vasquez:** ¿Qué tal si le ponemos a este monte le ponemos más bien costo o coste?

**Cubakilos:** Coste. Perfecto. Costo, que es el costo de que me cobra din coste. Y luego precio de venta, que es el precio que yo le voy a poner al cliente.

**Jhony Alexander Alvarez Vasquez:** Y aquí precio al público.

**Cubakilos:** Okay. Sí, vale.

**Jhony Alexander Alvarez Vasquez:** Pues aquí va a quedar dos campos y ya sabes que uno es para el otro.

**Cubakilos:** Exacto.

### 00:58:19

**Cubakilos:** Aunque así yo puedo ver decir ahora este voy a crear el paquete que es el de 22 € que di le va a

**Jhony Alexander Alvarez Vasquez:** Bueno,

**Cubakilos:** dar beneficios al cliente de 10 GB y luego creo lo duplico y le pongo eh ahora le voy a poner el otro paquete que en vez de 22 € sea de 30 y el cliente va a recibir de beneficio 100 GB. ¿Correcto? Y obviamente el precio de venta de uno,

**Jhony Alexander Alvarez Vasquez:** sí.

**Cubakilos:** a lo mejor de 22 € lo vendo en 25 y el de 30 € lo vendo en 35\.

**Jhony Alexander Alvarez Vasquez:** Perfecto.

**Cubakilos:** Sí, sí, me me expliqué

**Jhony Alexander Alvarez Vasquez:** Sí, sí, por supuesto. La parte importante ahí es saber mantener informado el coste y bueno,

**Cubakilos:** bien.

**Jhony Alexander Alvarez Vasquez:** el precio al público. Inclusive puedo agregarte ahí eso tampoco es mucho que parezca el porcentaje de utilidad.

**Cubakilos:** Bueno, pero bueno,

**Jhony Alexander Alvarez Vasquez:** por

**Cubakilos:** igual eso lo esos esas pulideras, esas cositas lo lo vemos lo a mí lo que

**Jhony Alexander Alvarez Vasquez:** sí lo vamos agregando de a poco.

**Cubakilos:** me ahora me pregunto es eh que esto es importante porque es la segunda parte del del de este de este sistema es para venderlo en en Wocommerce, me imagino que después tú tienes que editar el código de Wocommerce en la postcompra, ¿no?

### 00:59:39

**Cubakilos:** O sea, el hook de en la postcompra mandar el aviso a a

**Jhony Alexander Alvarez Vasquez:** Eh,

**Cubakilos:** Din.

**Jhony Alexander Alvarez Vasquez:** yo me yo me alineo al a la pasarela actual que tengas.

**Cubakilos:** Bueno, yo en WCommerce en la pasarela que tengo instalada es eh

**Jhony Alexander Alvarez Vasquez:** Eh, perfecto.

**Cubakilos:** Molly.

**Jhony Alexander Alvarez Vasquez:** Entonces, lo que yo hago es seguir, o sea, seguir el flujo que tiene Wocommerce para cualquier tipo de pago y hasta que Wocommerce no me diga, "He hecho el pago, yo no mando la señal a Ding." que normalmente tú reconoces ese

**Cubakilos:** Claro.

**Jhony Alexander Alvarez Vasquez:** tipo de de disparadores cuando el eh con un estado del pedido, que tú sabes que el pedido cambia según según el proceso que lleves. Entonces,

**Cubakilos:** Sí,

**Jhony Alexander Alvarez Vasquez:** podemos elegir uno procesando. Procesando suele ser el que antes del final como por defecto lo tiene o

**Cubakilos:** sí.

**Jhony Alexander Alvarez Vasquez:** completado, que tú dices, "No, yo ni cuando esté completado, sino que completado." Y hay que decirle a a Wocommerce que cambie el pedido a completado cuando esté pagado. Él normalmente lo pasa procesando. normalmente lo pasas procesando y ahí yo ya puedo tener como esa autorización de pedir el paquete

### 01:00:56

**Cubakilos:** Y ahí lo que pudieras hacer es que luego que DIN te devuelva el código del ID de la operación

**Jhony Alexander Alvarez Vasquez:** ading.

**Cubakilos:** es que lo cambias de estado a completado.

**Jhony Alexander Alvarez Vasquez:** Ah, ahí estamos en sintonía. Sí, correcto. Sí,

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** sí, sí.

**Cubakilos:** Entonces, si vas un momentico al pluin tuyo,

**Jhony Alexander Alvarez Vasquez:** es.

**Cubakilos:** al pluin, ¿cuál es entonces? ¿Qué significaría pago directo?

**Jhony Alexander Alvarez Vasquez:** Ah, no, este pago lo tengo es es de más que todo de pruebas. Lo puse desde el principio.

**Cubakilos:** Ah.

**Jhony Alexander Alvarez Vasquez:** Yo lo tengo pago directo porque si yo termino el flujo, el de una va a mandar la señal. Aunque aunque lo tengo en pruebas, mira, aunque no va a pasar nada,

**Cubakilos:** Ya.

**Jhony Alexander Alvarez Vasquez:** pero lo tengo en pruebas. Si yo paso Wocommerce, él ya cuando yo le de confirmar, él mismo se me se meinea, se me encamina a Wcommerce, a la pasarela de Wcommerce. extrae el monto, cuánto tiene que pagar el campo este del monto se lo se lo pongo ahí a a Wcommerce y yo elijo aquí qué disponibles, qué pasaré reglas tengo permitidas.

### 01:01:54

**Jhony Alexander Alvarez Vasquez:** Entonces, aquí va a salir la tuya cuando instales el el

**Cubakilos:** Vale,

**Jhony Alexander Alvarez Vasquez:** plugin.

**Cubakilos:** vale, vale, vale. Pues nada, bueno, en cuanto eso lo instalamos en en Cuaquilo

**Jhony Alexander Alvarez Vasquez:** Sí,

**Cubakilos:** tienda.

**Jhony Alexander Alvarez Vasquez:** sí. Eh, podemos instalaro de pruebas. Voy a hacer esos cambios de que hemos hablado ahora y

**Cubakilos:** Sí.

**Jhony Alexander Alvarez Vasquez:** bueno, me pongo ahí una señal para que inviértelo esta noche, que mañana tú lo puedas probar.

**Cubakilos:** Entonces me vemos mañana. Seguimos mañana.

**Jhony Alexander Alvarez Vasquez:** sí, perfecto.

**Cubakilos:** Venga, un saludo. Johny.

**Jhony Alexander Alvarez Vasquez:** igualmente, hasta luego.

### La transcripción finalizó después de 01:10:14

*Esta transcripción editable se generó por computadora y puede contener errores. Los usuarios también pueden cambiar el texto después de que se cree.*
