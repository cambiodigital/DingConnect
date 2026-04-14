# DingConnect Recargas — Plugin WordPress

Plugin para vender recargas y bundles internacionales con la API de [DingConnect](https://www.dingconnect.com) desde WordPress, con panel de administración, catálogo curado y shortcode listo para publicar.

Desarrollado por **[Cambiodigital.net](https://cambiodigital.net)**, personalizado para [Cubakilos.com](https://cubakilos.com).

---

## Características

- Búsqueda de productos por país y número de teléfono vía API DingConnect.
- Catálogo curado de bundles configurables desde el panel admin.
- Modo `ValidateOnly` para pruebas seguras sin ejecutar recargas reales.
- API REST propia (`/wp-json/dingconnect/v1/`) para integración con otros sistemas.
- Integración opcional con WooCommerce (carrito y checkout).
- Frontend responsivo embebible con shortcode `[dingconnect_recargas]`.

---

## Estructura del repositorio

```
dingconnect-wp-plugin/
  dingconnect-recargas/
    dingconnect-recargas.php        # Entrypoint del plugin
    includes/
      class-dc-api.php              # Cliente HTTP → DingConnect
      class-dc-rest.php             # Endpoints REST públicos
      class-dc-admin.php            # Panel de configuración y bundles
      class-dc-frontend.php         # Shortcode y carga de assets
      class-dc-woocommerce.php      # Integración WooCommerce (opcional)
    assets/
      js/frontend.js                # UI pública
      css/frontend.css              # Estilos del frontend
Documentación/
  CONTEXTO_IA.md                    # Contexto del proyecto para agentes IA
  GUIA_TECNICA_DING_CONNECT.md      # Guía técnica de la API
  BACKLOG_FUNCIONAL_TECNICO.md      # Backlog y estado de desarrollo
  API_DING_CONNECT_V1.md            # Referencia de endpoints DingConnect
Products-with-sku.csv               # Catálogo de SKUs exportado
recargas.html                       # Prototipo legado (referencia histórica)
```

---

## Requisitos

| Requisito       | Versión mínima |
|-----------------|---------------|
| WordPress       | 6.0           |
| PHP             | 7.4           |
| API Key         | DingConnect   |

---

## Instalación

Consultar el documento completo de instalación en [`dingconnect-wp-plugin/dingconnect-recargas/README-INSTALACION.md`](dingconnect-wp-plugin/dingconnect-recargas/README-INSTALACION.md).

Pasos resumidos:

1. Comprimir la carpeta `dingconnect-recargas/` como ZIP (ver manual para instrucciones en Windows/PowerShell).
2. En WordPress ir a **Plugins > Añadir nuevo > Subir plugin** e instalar el ZIP.
3. Activar el plugin.
4. En el panel **DingConnect**, configurar la API Key y la URL base.
5. Importar o crear bundles en el catálogo.
6. Insertar el shortcode `[dingconnect_recargas]` en cualquier página.

---

## Endpoints REST

| Método | Endpoint                              | Descripción                        |
|--------|---------------------------------------|------------------------------------|
| GET    | `/wp-json/dingconnect/v1/status`      | Estado de conexión con la API      |
| GET    | `/wp-json/dingconnect/v1/bundles`     | Listado de bundles curados         |
| GET    | `/wp-json/dingconnect/v1/products`    | Productos disponibles por número   |
| POST   | `/wp-json/dingconnect/v1/transfer`    | Ejecutar una recarga               |
| POST   | `/wp-json/dingconnect/v1/add-to-cart` | Agregar al carrito (WooCommerce)   |

---

## Seguridad

- Las credenciales de la API DingConnect se almacenan únicamente en el backend (WordPress).
- Todas las llamadas productivas a DingConnect salen desde el servidor, nunca desde el cliente.
- Se recomienda mantener `ValidateOnly` activo hasta completar pruebas reales controladas.

---

## Créditos

Desarrollado por **[Cambiodigital.net](https://cambiodigital.net)**  
Personalizado para **[Cubakilos.com](https://cubakilos.com)**

---

## Licencia

Uso privado. Todos los derechos reservados por Cambiodigital.net.
