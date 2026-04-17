# Staging local WordPress + WooCommerce

Este entorno levanta WordPress + WooCommerce para validar la matriz 6.1-6.7 de `dingconnect-recargas-v2` en runtime.

## Requisitos

- Docker Desktop con `docker compose` habilitado.
- Puerto `8080` libre.

## Levantar staging

1. Copiar `.env.example` a `.env` (el script lo hace automaticamente si no existe).
2. Ejecutar:

```powershell
./scripts/staging-up.ps1
```

3. Abrir:
   - Sitio: `http://localhost:8080`
   - Admin: `http://localhost:8080/wp-admin`
   - Usuario: `admin`
   - Password: `admin`

El bootstrap activa:
- WooCommerce.
- Plugin `dingconnect-recargas` montado desde el repo.
- `wizard_enabled=1`, `validate_only=1`, `allow_real_recharge=0`.
- Gateways de prueba Woo internos: `bacs`, `cheque`, `cod`.
- Páginas de shortcode para recargas/gift cards/Cuba.

## Correr matriz 6.1-6.7 (smoke automatizado)

```powershell
./scripts/run-matrix-6.ps1
```

Esto ejecuta comprobaciones de:
- 6.1/6.2: endpoints wizard para recargas y gift cards country-fixed.
- 6.3/6.4: baseline de estados de pedido para validar payment-first/idempotencia.
- 6.5: gateways de prueba activos.
- 6.6: opciones de retry/reconciliacion cargadas.
- 6.7: shortcodes publicados en paginas staging.

## Limites

- Para evidencia E2E completa de gateway real (Stripe/PayPal/Redsys/Bizum) hay que instalar plugins sandbox y configurar credenciales del gateway.
- Para validar transferencias reales DingConnect mantener `validate_only=1` hasta completar pruebas controladas.
