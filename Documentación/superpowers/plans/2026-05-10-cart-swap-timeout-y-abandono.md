# Cart Swap Timeout y Abandono (Checkout) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mantener el “cart swap” de recargas en WooCommerce, pero asegurando que al abandonar checkout (volver a tienda, cerrar navegador o expirar 5 minutos) se elimina la recarga del carrito y se restaura el carrito anterior (si existía).

**Architecture:** Se refuerza el snapshot del carrito para que siempre exista (aunque el carrito anterior estuviera vacío), se añade TTL (5 minutos) persistido en sesión WooCommerce, y se agrega un guard JS en checkout + frontend que detecta “abandono” (sin marcador en `sessionStorage`) y redirige a tienda disparando restauración server-side.

**Tech Stack:** WordPress, WooCommerce (WC Cart + Session), JavaScript (frontend y checkout)

---

## File Map

**Modify**
- `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-woocommerce.php`
- `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\assets\js\frontend.js`
- `x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\dingconnect-recargas.php`
- `x:\Proyectos\DingConnect\Documentación\BACKLOG_FUNCIONAL_TECNICO.md`
- `x:\Proyectos\DingConnect\Documentación\GUIA_TECNICA_DING_CONNECT.md`

---

### Task 1: Persistir estado del Cart Swap (snapshot siempre + TTL)

**Files:**
- Modify: `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php`

- [ ] **Step 1: Guardar snapshot siempre (aunque el carrito esté vacío)**

En `handle_add_to_cart`, reemplazar el bloque actual de snapshot por uno que:
- No sobreescriba snapshot si ya existe un swap activo
- Guarde `cart` y `applied_coupons` siempre
- Setee `dc_cart_swap_active`, `dc_cart_swap_started_at`, `dc_cart_swap_expires_at`
- Vacíe carrito solo si hay ítems (para mantener el comportamiento actual)

```php
$now = time();
$ttl_seconds = 300;

$swap_active = WC()->session ? (bool) WC()->session->get('dc_cart_swap_active') : false;
if (!$swap_active && WC()->session) {
    $snapshot = [
        'cart' => WC()->session->get('cart'),
        'applied_coupons' => WC()->session->get('applied_coupons'),
    ];
    WC()->session->set('dc_cart_snapshot', $snapshot);
    WC()->session->set('dc_cart_swap_active', true);
    WC()->session->set('dc_cart_swap_started_at', $now);
    WC()->session->set('dc_cart_swap_expires_at', $now + $ttl_seconds);
}
```

- [ ] **Step 2: Ajustar restauración para soportar cancelación/expiración hacia tienda**

Actualizar `handle_cart_restoration_fallback` para:
- Restaurar si detecta `dc_cancel_recharge=1` o `dc_checkout_expired=1`
- Restaurar también si `time() > dc_cart_swap_expires_at` incluso estando en checkout
- Redirigir a tienda en cancelación/expiración (no a carrito)

```php
$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
```

- [ ] **Step 3: Eliminar inline styles del botón (mantener clases WooCommerce)**

En `render_cancel_recharge_button`, eliminar `style="..."` y cambiar el `cancel_url` para apuntar a tienda.

---

### Task 2: Marcador en frontend antes de redirigir a checkout (detectar “cerré navegador”)

**Files:**
- Modify: `dingconnect-wp-plugin/dingconnect-recargas/assets/js/frontend.js`

- [ ] **Step 1: En el flujo que redirige al checkout, setear sessionStorage**

Antes de redirigir a `wc_get_checkout_url()` (o la URL que use el frontend), setear:

```js
try {
  sessionStorage.setItem('dc_cart_swap_started_at', String(Date.now()));
  sessionStorage.setItem('dc_cart_swap_active', '1');
} catch (e) {}
```

---

### Task 3: Guard JS en checkout (abandono + TTL) con redirección a tienda

**Files:**
- Modify: `dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php`

- [ ] **Step 1: Renderizar un script solo en checkout cuando hay swap activo**

Agregar un hook (por ejemplo `wp_footer`) que:
- Si hay `dc_cart_snapshot` y `cart_has_only_recargas()` y estamos en checkout
- Lee `dc_cart_swap_expires_at`
- En JS: si no existe `sessionStorage.dc_cart_swap_active`, redirige a `shop_url?dc_cancel_recharge=1`
- En JS: si existe, calcula ms restantes y programa redirección a `shop_url?dc_checkout_expired=1` al expirar
- Limpia `sessionStorage` si ya no hay swap activo

---

### Task 4: Documentación + version bump

**Files:**
- Modify: `Documentación/BACKLOG_FUNCIONAL_TECNICO.md`
- Modify: `Documentación/GUIA_TECNICA_DING_CONNECT.md`
- Modify: `dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php`

- [ ] **Step 1: Agregar nota de avance funcional-técnico del comportamiento de abandono + TTL**
- [ ] **Step 2: Actualizar guía técnica describiendo reglas del cart swap reforzado**
- [ ] **Step 3: Incrementar versión del plugin en `dingconnect-recargas.php`**

---

### Task 5: Validación

- [ ] **Step 1: Revisar diagnósticos del IDE (PHP/JS)**
- [ ] **Step 2: Verificación manual sugerida**
  - Iniciar recarga con carrito previo con productos: debe aislar recarga y, al volver a tienda, restaurar productos previos.
  - Iniciar recarga con carrito vacío: al volver a tienda / expirar / volver después de cerrar navegador, el carrito debe quedar vacío (sin recarga).
  - Mantenerse en checkout >5 minutos: debe redirigir a tienda y restaurar.
  - Click en “Volver a la tienda” (botón): debe restaurar y redirigir a tienda.

