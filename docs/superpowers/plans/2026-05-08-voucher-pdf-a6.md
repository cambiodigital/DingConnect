# Voucher PDF (A6) + “ID de transacción” — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Al usar “Guardar PDF” en la página de pedido confirmado (WooCommerce thank-you), imprimir un voucher tipo comprobante en formato pequeño A6, incluyendo “ID de transacción” (código canónico).

**Architecture:** Mantener el flujo actual basado en modal + `window.print()`, pero endurecer el contrato del campo de referencia (usar `transaction_id`) y aplicar CSS de impresión para A6 con jerarquía legible y salto de página por recarga.

**Tech Stack:** WordPress (PHP), WooCommerce hooks, HTML/CSS inline existente.

---

## Files impactados

- Modify: [class-dc-woocommerce.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php)
- Modify: [class-dc-voucher-renderer.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-voucher-renderer.php)
- Modify: [FLUJO_COMPLETO_FRONT_BACK.md](file:///x:/Proyectos/DingConnect/Documentación/FLUJO_COMPLETO_FRONT_BACK.md)
- Modify: [BACKLOG_FUNCIONAL_TECNICO.md](file:///x:/Proyectos/DingConnect/Documentación/BACKLOG_FUNCIONAL_TECNICO.md)
- Modify: [dingconnect-recargas.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php)

---

### Task 1: Canonizar referencia (transaction_id)

**Objetivo:** Asegurar que el voucher siempre tenga `transaction_id` poblado (v2 y legacy) y que el usuario vea el label “ID de transacción”.

**Files:**
- Modify: [class-dc-woocommerce.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php#L1767-L1860)
- Modify: [class-dc-voucher-renderer.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-voucher-renderer.php#L58-L134)

- [ ] **Step 1: En v2, completar `transaction_id` con fallback**

Editar el bloque v2 dentro de `render_thankyou_voucher_summary()` para que, antes de renderizar, garantice el campo:

```php
if (!isset($voucher['transaction_id']) || (string) ($voucher['transaction_id'] ?? '') === '') {
    $voucher['transaction_id'] = (string) ($voucher['transfer_ref'] ?? $item->get_meta('_dc_transfer_ref'));
}
```

Ubicación: en el `if ($voucher) { ... }` antes de `render_html($voucher)` en [class-dc-woocommerce.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php#L1802-L1821).

- [ ] **Step 2: Cambiar label “Ref” → “ID de transacción”**

En `DC_Recargas_Voucher_Renderer::render_rows()`, cambiar la clave del array:

```php
'ID de transacción' => $ref,
```

Reemplazando la línea actual:

```php
'Ref' => $ref,
```

Ubicación: [class-dc-voucher-renderer.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-voucher-renderer.php#L118-L131)

- [ ] **Step 3: Validar que legacy ya alimente `transaction_id`**

Confirmar que el payload legacy ya setea:

```php
'transaction_id' => (string) ($payload['transaction_id'] ?? $item->get_meta('_dc_transfer_ref')),
```

Ubicación: [class-dc-woocommerce.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php#L1838-L1855)

No requiere cambio, solo asegurar que el renderer se alimente del mismo campo.

---

### Task 2: Layout de impresión A6 (voucher “hoja pequeña”)

**Objetivo:** Al imprimir, el navegador genere un comprobante pequeño A6, centrado, con márgenes correctos y salto de página por cada recarga.

**Files:**
- Modify: [class-dc-woocommerce.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/includes/class-dc-woocommerce.php#L1869-L1879)

- [ ] **Step 1: Ajustar reglas `@media print` para tamaño A6**

Reemplazar el bloque existente de estilos de impresión por uno que:
- Define `@page { size: A6 portrait; margin: 8mm; }`
- Centra el contenido y fuerza ancho A6 (fallback) con `mm`
- Override de estilos inline con `!important`

Bloque propuesto:

```php
echo '<style>
@media print {
  @page { size: A6 portrait; margin: 8mm; }

  html, body { height: auto !important; }
  body { margin: 0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  body * { visibility: hidden; }
  #dc-voucher-modal, #dc-voucher-modal * { visibility: visible; }

  #dc-voucher-modal {
    position: static !important;
    inset: auto !important;
    width: auto !important;
    height: auto !important;
    background: transparent !important;
    display: block !important;
    padding: 0 !important;
  }

  .dc-voucher-modal-content {
    box-shadow: none !important;
    border-radius: 0 !important;
    max-height: none !important;
    overflow: visible !important;
    padding: 0 !important;

    width: 105mm !important;
    max-width: 105mm !important;
    margin: 0 auto !important;
  }

  #dc-voucher-modal button { display: none !important; }

  .dc-voucher-container {
    margin-top: 0 !important;
    padding: 0 !important;
    border: none !important;
    background: #fff !important;
    border-radius: 0 !important;
    break-after: page;
    page-break-after: always;
  }
  .dc-voucher-container:last-of-type {
    break-after: auto;
    page-break-after: auto;
  }

  .dc-voucher-container h3 {
    margin: 0 0 8px 0 !important;
    font-size: 14px !important;
    letter-spacing: 0 !important;
  }

  .dc-voucher-container table { font-size: 12px !important; }
  .dc-voucher-container th {
    width: 45% !important;
    padding: 6px 0 !important;
    color: #475569 !important;
    border-bottom: 1px solid #e2e8f0 !important;
  }
  .dc-voucher-container td {
    padding: 6px 0 !important;
    border-bottom: 1px solid #e2e8f0 !important;
  }
}
</style>';
```

- [ ] **Step 2: Re-validar en Edge/Chrome el tamaño resultante**

Confirmar en vista previa de impresión:
- Papel A6 (si el navegador/driver respeta `@page size`)
- Contenido centrado y compacto
- Botones ocultos
- En múltiples recargas: 1 voucher por página (page break)

---

### Task 3: Documentación + bump de versión

**Objetivo:** Cumplir regla del workspace: documentar el cambio y actualizar versión del plugin.

**Files:**
- Modify: [BACKLOG_FUNCIONAL_TECNICO.md](file:///x:/Proyectos/DingConnect/Documentación/BACKLOG_FUNCIONAL_TECNICO.md)
- Modify: [FLUJO_COMPLETO_FRONT_BACK.md](file:///x:/Proyectos/DingConnect/Documentación/FLUJO_COMPLETO_FRONT_BACK.md)
- Modify: [dingconnect-recargas.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php#L1-L33)

- [ ] **Step 1: Backlog**

Agregar un ítem (P2 recomendado) similar a:
- “Mejorar voucher PDF (WooCommerce thank-you): impresión A6 + etiqueta ‘ID de transacción’ + salto de página por recarga”.

- [ ] **Step 2: Flujo completo**

En Fase 7 (“Resultado y voucher”), ajustar el texto para que:
- El campo mostrado al usuario se llame “ID de transacción”
- El PDF generado por “Guardar PDF” imprime voucher en A6 (comprobante pequeño)

- [ ] **Step 3: Version bump**

Incrementar versión `2.8.09` → `2.8.10` en:
- Cabecera `Version:` (línea 6)
- Constante `DC_RECARGAS_VERSION` (línea 31)

Archivo: [dingconnect-recargas.php](file:///x:/Proyectos/DingConnect/dingconnect-wp-plugin/dingconnect-recargas/dingconnect-recargas.php#L1-L33)

---

### Task 4: Validaciones

**Objetivo:** Asegurar que no se rompe nada y que el PDF sale como voucher pequeño.

- [ ] **Step 1: Lint PHP (sintaxis)**

Run:

```powershell
php -l x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-woocommerce.php
php -l x:\Proyectos\DingConnect\dingconnect-wp-plugin\dingconnect-recargas\includes\class-dc-voucher-renderer.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 2: Validación manual end-to-end**

En WordPress + WooCommerce:
- Completar un checkout con recarga.
- En `order-received` verificar que el modal aparece.
- Click “Guardar PDF” y en vista previa confirmar:
  - Tamaño A6 / comprobante pequeño
  - Campo “ID de transacción” presente y con el código correcto
  - No aparece “DingConnect” en el texto al cliente (marca blanca)

- [ ] **Step 3: Multi-item**

Crear un pedido con 2 recargas y verificar 1 voucher por página en la impresión.

---

## Self-review (plan)

- Cobertura: Layout A6 (Task 2), “ID de transacción” + canonicalización (Task 1), documentación + bump (Task 3), validaciones (Task 4).
- Sin placeholders: cada task incluye snippet concreto y comandos.
