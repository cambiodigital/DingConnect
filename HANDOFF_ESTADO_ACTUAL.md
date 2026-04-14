# Handoff — Estado actual del proyecto Cubakilos / DingConnect

> **Propósito de este archivo:** Permitir que cualquier IA o desarrollador continúe el trabajo desde donde quedó, sin perder contexto. Última actualización: 13 de abril de 2026.

> **Nota de organización (14 de abril de 2026):** La ruta de lectura recomendada para nuevas sesiones está en `Documentación/CONTEXTO_IA.md` y `Documentación/BACKLOG_FUNCIONAL_TECNICO.md`. Este handoff se conserva como histórico.

---

## 1. Contexto del proyecto

**Nombre de la tienda:** CUBAKILOS
**Tipo:** Servicio de recargas internacionales de saldo móvil
**Plataforma destino:** WordPress (integración pendiente)
**Proveedor de recargas:** [DingConnect](https://www.dingconnect.com) — panel de distribuidor
**Cuenta DingConnect:** `jhonyalexalvarez@gmail.com` / rol: Administrator

### Objetivo de negocio

Permitir que los usuarios de la web de Cubakilos envíen recargas de saldo móvil a teléfonos en Cuba, Colombia, México y otros 23 países, pagando desde la web con su método de pago.

---

## 2. Estructura de archivos del workspace

```
x:\Proyectos\DingConnect\
├── recargas.html                    ← ARCHIVO PRINCIPAL — toda la lógica frontend está aquí
├── prueba.html                      ← prototipo inicial (ya obsoleto, no usar)
├── REPORTE_INTEGRACION_DING_CONNECT.md   ← reporte inicial de validación (marzo 2026)
├── HANDOFF_ESTADO_ACTUAL.md         ← este archivo
└── Documentación/
    ├── API_DING_CONNECT_V1.md       ← referencia de la API de DingConnect
    ├── GUIA_TECNICA_DING_CONNECT.md ← guía técnica de integración
    └── README.md                    ← índice de documentación
```

**Archivo de trabajo activo: `recargas.html`**
Es un único archivo HTML/CSS/JS (sin dependencias externas) que actúa como boceto funcional completo. En producción será portado a WordPress con un backend PHP.

---

## 3. Credenciales y endpoints conocidos

| Ítem | Valor |
|------|-------|
| **API Base** | `https://www.dingconnect.com/api/V1` |
| **API Key** | `6lLhhXvn1lC5fZXCW6UWjv` |
| **Panel DingConnect** | `https://www.dingconnect.com/en-US/PricingAndPromotions/Products` |

> ⚠️ **Seguridad crítica:** La API Key está actualmente expuesta en el JavaScript del cliente (`recargas.html`, línea ~765). Esto es aceptable solo para el boceto de desarrollo. En producción **DEBE** moverse a un proxy PHP server-side.

---

## 4. Arquitectura del archivo `recargas.html`

### Secciones de código (con números de línea aproximados)

| Línea | Sección |
|-------|---------|
| 1–760 | CSS completo (estilos de la UI) |
| 763 | `CONFIG` — configuración de API |
| 770 | `PRODUCT_POLICY` — catálogo curado por país/operador |
| 784 | `COUNTRIES` — lista de 26 destinos |
| 812 | `DEMO` — datos de fallback por país ISO |
| 975 | `state` — estado de runtime de la app |
| 978 | `applyProductPolicy()` — filtro de operadores |
| 1000+ | Lógica de UI: selector de países, API calls, renderizado, modales |

### Constantes clave

```javascript
const CONFIG = {
    apiBase : 'https://www.dingconnect.com/api/V1',
    apiKey  : '6lLhhXvn1lC5fZXCW6UWjv', // ⚠️ mover a servidor en producción
    demoMode: false  // se activa automáticamente ante CORS
};

// Catálogo curado: limita qué operadores se muestran por país.
// Añadir aquí nuevas entradas para incorporar más países/operadores.
const PRODUCT_POLICY = {
    CO: {
        allowedProviders: ['Wom Colombia', 'WOM', 'Wom']
    }
    // CU: { ... }  ← agregar cuando se configure Cuba
};
```

### Países configurados en `DEMO` (datos de muestra)

| ISO | Operador demo | Tipo |
|-----|---------------|------|
| `CU` | Cubacel (ETECSA) | Paquetes fijos + bono |
| `MX` | Telcel / AT&T México | Paquetes fijos + rango |
| `CO` | Wom Colombia | Rango EUR 2–28.80 + paquete fijo EUR 7.90 |
| `_default` | Operador detectado | Genérico |

---

## 5. Flujo de funcionamiento

### 5.1 Flujo del usuario

```
1. Elige país destino (selector de 26 países)
2. Ingresa número de teléfono (sin código de país — se añade automático)
3. Envía el formulario
4. Ve tarjetas de paquetes disponibles
5. Selecciona un paquete → modal de confirmación
6. Confirma → llamada SendTransfer (ValidateOnly: true en dev)
7. Ve resultado de la simulación
```

### 5.2 Flujo técnico de búsqueda de paquetes

```
POST /searchForm
  ├─ getProducts(+dialCode+phone)    → GET /GetProducts?AccountNumber=...
  ├─ getPromotions(countryIso)       → GET /GetPromotions?CountryIso=...
  ├─ applyProductPolicy(iso, [])     → filtra por operador según PRODUCT_POLICY
  │
  ├─ SI products.length > 0:
  │     getProductDescriptions([skus])  → GET /GetProductDescriptions
  │     → modo "live" (badge verde)
  │
  └─ SI products.length == 0:
        → modo "demo" con DEMO[iso] o DEMO._default (badge amarillo)
        → muestra catálogo curado sin llamada extra
```

### 5.3 Flujo de confirmación y simulación

```
onSelectProduct(product)
  → showConfirmModal()    ← muestra resumen: número, país, operador, monto, recibe, vigencia
  → usuario confirma
  → SI IsRange: usa rangeAmounts[sku]
  → executeTransfer()
      ├─ estimatePrices(sku, sendValue)  → POST /EstimatePrices
      └─ sendTransfer(product, sendValue) → POST /SendTransfer { ValidateOnly: true }
  → showResultModal(result)
```

### 5.4 Manejo de CORS

Las llamadas directas desde el navegador a `dingconnect.com` fallan por CORS (comportamiento esperado en desarrollo). El código detecta el error y activa automáticamente el modo demo. En producción WordPress, las llamadas se enrutarán a través de un proxy PHP local.

---

## 6. Estado de validaciones realizadas en DingConnect

### Cuba (CU)

- **Operador:** Cubacel / ETECSA
- **Estado en panel:** ✅ Productos visibles (checkbox "Visible" activo)
- **Tipo de producto:** Top-up, paquetes de datos, bonos
- **Moneda:** USD

### Colombia (CO) ← verificado en sesión más reciente

- **Operador:** Wom Colombia
- **Estado en panel:** ✅ Visible, activo
- **Tipo:** Top-up (rango)
- **Rango de envío:** EUR 2.00 – EUR 28.80
- **Rango de recepción:** COP 6,041.49 – COP 86,771.78
- **Comisión:** 14%
- **Producto llave de prueba:** WOM Colombia, 1 resultado en el buscador del panel

> **Nota:** Cuando se llama `GetProducts` con un número de teléfono de prueba colombiano (+573001234567), la API retorna 0 productos. Esto es normal — DingConnect valida el número. El sistema cae al catálogo demo y muestra los paquetes WOM configurados.

---

## 7. Prueba end-to-end validada (13 abril 2026)

Se realizó la siguiente prueba completa en el navegador con `recargas.html`:

1. **País:** 🇨🇴 Colombia
2. **Número:** +573001234567
3. **Resultado de búsqueda:**
   - Badge API: `⚠️ Sin productos para ese número. Mostrando catálogo configurado.`
   - 2 tarjetas WOM Colombia renderizadas:
     - "Saldo libre WOM Colombia (rango habilitado)" — EUR 2 a 28.80
     - "Paquete WOM datos + minutos" — EUR 7.90 → 23,864 COP, 1 mes
4. **Modal de confirmación al seleccionar paquete fijo:**
   ```
   📍 Número destino: +573001234567
   🌍 País: 🇨🇴 Colombia
   📱 Operador: Wom Colombia
   📦 Paquete: Paquete WOM datos + minutos
   💳 Valor a cobrar: EUR 7.90
   → Recibirá: 23.864 COP
   ⏱️ Vigencia: 1 mes
   ```
5. **Estado:** ✅ Flujo completo funcionando

---

## 8. Lo que ya está hecho

| Tarea | Estado |
|-------|--------|
| Boceto UI completo (HTML/CSS/JS) | ✅ |
| Integración `GetProducts` | ✅ |
| Integración `GetPromotions` | ✅ |
| Integración `GetProductDescriptions` | ✅ |
| Integración `EstimatePrices` | ✅ |
| Integración `SendTransfer` (ValidateOnly: true) | ✅ |
| Modo demo automático ante CORS | ✅ |
| Selector de 26 países con dial codes | ✅ |
| Cards de productos (fijo, rango, promoción) | ✅ |
| Modal de confirmación con detalle completo | ✅ |
| Modal de resultado de simulación | ✅ |
| `PRODUCT_POLICY` — filtro de operadores por país | ✅ |
| `applyProductPolicy()` — función de filtrado | ✅ |
| Demo Colombia → paquetes WOM (alineados con DingConnect real) | ✅ |
| Fallback zero-results → catálogo curado (no pantalla vacía) | ✅ |
| Prueba end-to-end Colombia/WOM en navegador | ✅ |
| Verificación de productos WOM en panel DingConnect | ✅ |

---

## 9. Próximos pasos (pendientes)

### Prioridad alta

1. **Proxy PHP para WordPress**
   - Crear `wp-content/plugins/cubakilos-recargas/api-proxy.php` (o similar)
   - El proxy recibe las peticiones del frontend y llama a DingConnect con la API Key desde el servidor
   - Elimina la exposición de la API Key en el cliente
   - Reemplazar las funciones `apiGet()` y `apiPost()` en `recargas.html` para apuntar a `/wp-json/cubakilos/v1/` en lugar de a DingConnect directamente

2. **Probar con número real colombiano**
   - Usar el flujo "Send Recharge" del panel DingConnect para obtener un número de prueba válido
   - Verificar que `GetProducts` devuelve resultados reales para WOM Colombia
   - (Puede ser numero propio con SIM WOM)

3. **Configurar Demo para Cuba**
   - El demo de Cuba en `DEMO.CU` usa datos ilustrativos
   - Validar que los montos y SKUs mencionados correspondan a los productos reales en el panel
   - Ajustar `PRODUCT_POLICY.CU` si se quieren priorizar paquetes específicos

### Prioridad media

4. **Catálogo editable por administrador**
   - Actualmente los paquetes demo están hardcodeados en `DEMO` y `PRODUCT_POLICY`
   - Ideal: JSON configurable (en WordPress: opciones del plugin) para que el operador elija qué mostrar sin tocar código

5. **Integración de pagos del cliente**
   - El flujo actual simula la recargas (`ValidateOnly: true`)
   - Para cobrar al usuario se necesita: WooCommerce / Stripe / PayPal o similar
   - El flujo sería: usuario paga → backend confirma pago → backend llama SendTransfer con `ValidateOnly: false`

6. **Expandir `PRODUCT_POLICY` a más países**
   - Cuba, México, y los demás países del `COUNTRIES` array no tienen política asignada
   - Sin política → todos los operadores de la API se muestran (comportamiento actual por defecto)

### Prioridad baja / producción

7. Autenticación de usuarios
8. Base de datos para historial de transacciones
9. Rate limiting y CSRF
10. Logs de auditoría
11. Cambiar `ValidateOnly` a `false` (solo cuando haya cobro real implementado)

---

## 10. Cómo continuar el desarrollo

### Para agregar un país/operador nuevo al catálogo

1. Verificar que el operador está en el panel DingConnect → Products con "Visible" activo
2. Anotar: `ProviderName`, rango de envío (Send), moneda, tipo (Top-up / Bundle)
3. Agregar entrada en `PRODUCT_POLICY` (en `recargas.html`):
   ```javascript
   const PRODUCT_POLICY = {
       CO: { allowedProviders: ['Wom Colombia', 'WOM', 'Wom'] },
       CU: { allowedProviders: ['Cubacel'] },  // ← ejemplo
   };
   ```
4. Agregar/actualizar entrada en `DEMO[ISO]` con productos representativos
5. Probar en navegador seleccionando el país

### Para iniciar la integración con WordPress

1. Crear plugin básico en `wp-content/plugins/cubakilos-recargas/`
2. Registrar endpoints REST: `GET /wp-json/cubakilos/v1/products`, `POST /wp-json/cubakilos/v1/transfer`
3. El plugin llama a DingConnect desde PHP (con la API Key en `wp-config.php`)
4. Reemplazar en `recargas.html` las URLs de `apiGet()` / `apiPost()` para que apunten a los endpoints WP
5. Incluir el HTML/CSS/JS de `recargas.html` como shortcode `[cubakilos_recargas]`

---

## 11. Endpoints DingConnect utilizados

| Método | Endpoint | Uso |
|--------|----------|-----|
| GET | `/GetProducts?AccountNumber=+...&Take=50` | Lista SKUs disponibles para un número |
| GET | `/GetProductDescriptions?SkuCodes[0]=...` | Descripción enriquecida de cada SKU |
| GET | `/GetPromotions?CountryIso=CU&Take=10` | Promociones activas por país |
| POST | `/EstimatePrices` | Estima el valor final de la recarga |
| POST | `/SendTransfer` | Ejecuta (o valida) la transferencia |

Autenticación: header `api_key: <valor>` en todas las llamadas.

---

## 12. Referencias

- [Documentación API DingConnect V1](Documentación/API_DING_CONNECT_V1.md)
- [Guía técnica DingConnect](Documentación/GUIA_TECNICA_DING_CONNECT.md)
- [Reporte de validación inicial (marzo 2026)](REPORTE_INTEGRACION_DING_CONNECT.md)
- Panel de administración: https://www.dingconnect.com/en-US/PricingAndPromotions/Products
