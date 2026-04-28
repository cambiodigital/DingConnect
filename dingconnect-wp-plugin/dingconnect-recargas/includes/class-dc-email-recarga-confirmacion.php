<?php
if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('WC_DC_Email_Recarga_Confirmacion')) {
    return;
}

/**
 * Email de confirmación de recarga DingConnect.
 *
 * Se envía al cliente cuando la transferencia en DingConnect finaliza exitosamente,
 * incluyendo el ID de transacción oficial de Ding y todos los datos operativos.
 *
 * Usa la infraestructura de correo de WooCommerce (wp_mail + cualquier SMTP configurado).
 */
class WC_DC_Email_Recarga_Confirmacion extends WC_Email {
    /**
     * Datos de recarga procesada (ID transacción, monto, operador, etc.)
     *
     * @var array
     */
    public $recarga_data = [];

    public function __construct() {
        $this->id             = 'dc_recarga_confirmacion';
        $this->customer_email = true;
        $this->title          = __('Confirmación de recarga DingConnect', 'dingconnect-recargas');
        $this->description    = __('Se envía al cliente cuando su recarga ha sido procesada exitosamente por DingConnect.', 'dingconnect-recargas');
        $this->heading        = __('¡Tu recarga fue procesada!', 'dingconnect-recargas');
        $this->subject        = __('Confirmación de recarga #{order_number}', 'dingconnect-recargas');

        // Datos de recarga que se inyectan antes del trigger
        $this->recarga_data = [];

        $this->template_html  = '';   // Usamos get_content() directo, sin archivo de plantilla
        $this->template_plain = '';

        parent::__construct();
    }

    /**
     * Dispara el envío del email de confirmación.
     *
     * @param mixed ...$args Argumentos variádicos: $order_id, $item, $snapshot[]
     */
    public function trigger( ...$args ) {
        // Extraer argumentos
        $order_id = $args[0] ?? 0;
        $item = $args[1] ?? null;
        $snapshot = $args[2] ?? [];
        
        $this->setup_locale();

        $order = wc_get_order( (int) $order_id );
        if ( ! $order instanceof WC_Order ) {
            $this->restore_locale();
            return;
        }

        $this->object = $order;
        $this->recarga_data = $this->build_recarga_data( $item, $snapshot, $order );

        $this->recipient = $order->get_billing_email();
        if ( ! $this->recipient ) {
            $this->restore_locale();
            return;
        }

        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );

        if ( $this->is_enabled() && $this->get_recipient() ) {
            $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );
        }

        $this->restore_locale();
    }

    /**
     * Construye el array de datos visibles en el email a partir del item y snapshot.
     */
    private function build_recarga_data( $item, array $snapshot, WC_Order $order ) {
        $transfer_ref      = ! empty( $snapshot['transfer_ref'] ) ? $snapshot['transfer_ref'] : (string) $item->get_meta( '_dc_transfer_ref' );
        $distributor_ref   = ! empty( $snapshot['distributor_ref'] ) ? $snapshot['distributor_ref'] : (string) $item->get_meta( '_dc_distributor_ref' );
        $status_label      = ! empty( $snapshot['status_label'] ) ? $snapshot['status_label'] : (string) $item->get_meta( '_dc_transfer_status' );
        $receive_value     = ! empty( $snapshot['receive_value'] ) ? (float) $snapshot['receive_value'] : 0.0;
        $receive_currency  = ! empty( $snapshot['receive_currency'] ) ? $snapshot['receive_currency'] : '';
        $receipt_text      = ! empty( $snapshot['receipt_text'] ) ? $snapshot['receipt_text'] : (string) $item->get_meta( '_dc_receipt_text' );

        $send_value    = (float) $item->get_meta( '_dc_send_value' );
        $send_currency = (string) $item->get_meta( '_dc_send_currency_iso' );
        $account       = (string) $item->get_meta( '_dc_account_number' );
        $provider      = (string) $item->get_meta( '_dc_provider_name' );
        $bundle        = (string) $item->get_meta( '_dc_bundle_label' );
        $country_iso   = (string) $item->get_meta( '_dc_country_iso' );

        // PIN / providerRef desde receipt_params
        $receipt_params_raw = (string) $item->get_meta( '_dc_receipt_params' );
        $receipt_params     = [];
        if ( $receipt_params_raw ) {
            $decoded = json_decode( $receipt_params_raw, true );
            if ( is_array( $decoded ) ) {
                $receipt_params = $decoded;
            }
        }
        $pin          = $receipt_params['pin'] ?? $receipt_params['Pin'] ?? '';
        $provider_ref = $receipt_params['providerRef'] ?? $receipt_params['ProviderRef'] ?? '';

        return compact(
            'transfer_ref',
            'distributor_ref',
            'status_label',
            'receive_value',
            'receive_currency',
            'receipt_text',
            'send_value',
            'send_currency',
            'account',
            'provider',
            'bundle',
            'country_iso',
            'pin',
            'provider_ref',
            'order'
        );
    }

    // ---------------------------------------------------------------
    // Contenido del email
    // ---------------------------------------------------------------

    public function get_content_html() {
        return $this->render_html();
    }

    public function get_content_plain() {
        return $this->render_plain();
    }

    private function render_html() {
        $d           = $this->recarga_data;
        $order       = $d['order'];
        $site_name   = get_bloginfo( 'name' );
        $site_url    = get_home_url();
        $order_url   = $order->get_view_order_url();
        $order_num   = $order->get_order_number();
        $order_date  = wc_format_datetime( $order->get_date_created() );
        $logo_url    = $this->get_logo_url();
        $email_bg    = '#f4f7fb';
        $card_bg     = '#ffffff';
        $accent      = '#1e40af';      // azul corporativo
        $success_clr = '#16a34a';
        $label_clr   = '#6b7280';
        $value_clr   = '#111827';
        $border_clr  = '#e5e7eb';
        $footer_clr  = '#9ca3af';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $this->get_subject() ); ?></title>
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $email_bg ); ?>;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:<?php echo esc_attr( $email_bg ); ?>;padding:32px 16px;">
<tr><td align="center">

  <!-- Card principal -->
  <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:<?php echo esc_attr( $card_bg ); ?>;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);">

    <!-- Header con logo -->
    <tr>
      <td style="background:<?php echo esc_attr( $accent ); ?>;padding:28px 36px;text-align:center;">
        <?php if ( $logo_url ) : ?>
          <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="max-height:48px;max-width:200px;display:inline-block;vertical-align:middle;margin-bottom:10px;">
          <br>
        <?php endif; ?>
        <span style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-.3px;"><?php echo esc_html( $site_name ); ?></span>
      </td>
    </tr>

    <!-- Título éxito -->
    <tr>
      <td style="padding:32px 36px 0;text-align:center;">
        <div style="display:inline-block;background:#dcfce7;border-radius:50%;width:56px;height:56px;line-height:56px;font-size:28px;text-align:center;margin-bottom:16px;">✓</div>
        <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:<?php echo esc_attr( $value_clr ); ?>;"><?php echo esc_html( $this->get_heading() ); ?></h1>
        <p style="margin:0 0 4px;font-size:14px;color:<?php echo esc_attr( $label_clr ); ?>;">
          <?php printf( esc_html__( 'Pedido #%s &bull; %s', 'dingconnect-recargas' ), esc_html( $order_num ), esc_html( $order_date ) ); ?>
        </p>
        <p style="margin:0;font-size:13px;color:<?php echo esc_attr( $label_clr ); ?>;">
          <?php printf( esc_html__( 'Confirmado para %s', 'dingconnect-recargas' ), esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ); ?>
        </p>
      </td>
    </tr>

    <!-- ID de transacción DingConnect (destacado) -->
    <tr>
      <td style="padding:24px 36px 0;">
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px 20px;text-align:center;">
          <p style="margin:0 0 4px;font-size:11px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:<?php echo esc_attr( $accent ); ?>;"><?php esc_html_e( 'ID de Transacción DingConnect', 'dingconnect-recargas' ); ?></p>
          <p style="margin:0;font-size:20px;font-weight:700;color:<?php echo esc_attr( $value_clr ); ?>;letter-spacing:.5px;font-family:monospace,monospace;">
            <?php echo esc_html( $d['transfer_ref'] !== '' ? $d['transfer_ref'] : '—' ); ?>
          </p>
          <?php if ( $d['distributor_ref'] ) : ?>
          <p style="margin:6px 0 0;font-size:11px;color:<?php echo esc_attr( $label_clr ); ?>;">
            <?php printf( esc_html__( 'Ref. interna: %s', 'dingconnect-recargas' ), esc_html( $d['distributor_ref'] ) ); ?>
          </p>
          <?php endif; ?>
        </div>
      </td>
    </tr>

    <!-- Tabla de detalles de la operación -->
    <tr>
      <td style="padding:24px 36px 0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
          <tr>
            <td colspan="2" style="padding-bottom:12px;">
              <span style="font-size:12px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:<?php echo esc_attr( $label_clr ); ?>;"><?php esc_html_e( 'Detalles de la operación', 'dingconnect-recargas' ); ?></span>
            </td>
          </tr>

          <?php
          $rows = [
              __( 'Teléfono recargado', 'dingconnect-recargas' ) => $d['account'],
              __( 'Operador',           'dingconnect-recargas' ) => $d['provider'],
              __( 'Paquete',            'dingconnect-recargas' ) => $d['bundle'],
              __( 'País',               'dingconnect-recargas' ) => $d['country_iso'],
              __( 'Monto enviado',      'dingconnect-recargas' ) => $d['send_currency'] . ' ' . number_format( $d['send_value'], 2 ),
          ];

          if ( $d['receive_value'] > 0 ) {
              $rows[ __( 'Monto recibido', 'dingconnect-recargas' ) ] = $d['receive_currency'] . ' ' . number_format( $d['receive_value'], 2 );
          }

          if ( $d['pin'] ) {
              $rows[ __( 'PIN', 'dingconnect-recargas' ) ] = $d['pin'];
          }

          if ( $d['provider_ref'] ) {
              $rows[ __( 'Ref. proveedor', 'dingconnect-recargas' ) ] = $d['provider_ref'];
          }

          $rows[ __( 'Estado', 'dingconnect-recargas' ) ] = strtoupper( $d['status_label'] );

          $is_first = true;
          foreach ( $rows as $label => $value ) :
              if ( empty( $value ) ) continue;
              $top_border = $is_first ? 'none' : '1px solid ' . $border_clr;
              $is_first   = false;
              $is_status  = ( $label === __( 'Estado', 'dingconnect-recargas' ) );
          ?>
          <tr>
            <td style="padding:11px 0;border-top:<?php echo esc_attr( $top_border ); ?>;font-size:13px;color:<?php echo esc_attr( $label_clr ); ?>;width:50%;vertical-align:top;">
              <?php echo esc_html( $label ); ?>
            </td>
            <td style="padding:11px 0;border-top:<?php echo esc_attr( $top_border ); ?>;font-size:13px;font-weight:600;color:<?php echo $is_status ? esc_attr( $success_clr ) : esc_attr( $value_clr ); ?>;text-align:right;vertical-align:top;">
              <?php echo esc_html( $value ); ?>
            </td>
          </tr>
          <?php endforeach; ?>

        </table>
      </td>
    </tr>

    <!-- Texto de recibo (si lo hay) -->
    <?php if ( $d['receipt_text'] ) : ?>
    <tr>
      <td style="padding:16px 36px 0;">
        <div style="background:#f9fafb;border:1px solid <?php echo esc_attr( $border_clr ); ?>;border-radius:6px;padding:12px 16px;">
          <p style="margin:0 0 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:<?php echo esc_attr( $label_clr ); ?>;"><?php esc_html_e( 'Recibo del operador', 'dingconnect-recargas' ); ?></p>
          <p style="margin:0;font-size:13px;color:<?php echo esc_attr( $value_clr ); ?>;white-space:pre-wrap;"><?php echo esc_html( $d['receipt_text'] ); ?></p>
        </div>
      </td>
    </tr>
    <?php endif; ?>

    <!-- CTA: Ver pedido -->
    <tr>
      <td style="padding:28px 36px;text-align:center;">
        <a href="<?php echo esc_url( $order_url ); ?>" style="display:inline-block;background:<?php echo esc_attr( $accent ); ?>;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;padding:12px 32px;border-radius:8px;">
          <?php esc_html_e( 'Ver mi pedido', 'dingconnect-recargas' ); ?>
        </a>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="border-top:1px solid <?php echo esc_attr( $border_clr ); ?>;padding:24px 36px;text-align:center;">
        <p style="margin:0 0 6px;font-size:12px;color:<?php echo esc_attr( $footer_clr ); ?>;">
          <?php printf(
              esc_html__( 'Gracias por tu compra en %s', 'dingconnect-recargas' ),
              '<a href="' . esc_url( $site_url ) . '" style="color:' . esc_attr( $accent ) . ';text-decoration:none;">' . esc_html( $site_name ) . '</a>'
          ); ?>
        </p>
        <p style="margin:0;font-size:11px;color:<?php echo esc_attr( $footer_clr ); ?>;">
          <?php esc_html_e( 'Este es un correo automático. Si tienes dudas, contáctanos a través de nuestra web.', 'dingconnect-recargas' ); ?>
        </p>
      </td>
    </tr>

  </table><!-- /card -->

</td></tr>
</table>

</body>
</html>
        <?php
        return ob_get_clean();
    }

    private function render_plain() {
        $d      = $this->recarga_data;
        $order  = $d['order'];
        $lines  = [];

        $lines[] = $this->get_heading();
        $lines[] = '';
        $lines[] = sprintf( __( 'Pedido: #%s', 'dingconnect-recargas' ), $order->get_order_number() );
        $lines[] = sprintf( __( 'Fecha: %s', 'dingconnect-recargas' ), wc_format_datetime( $order->get_date_created() ) );
        $lines[] = '';
        $lines[] = __( 'ID DE TRANSACCIÓN DINGCONNECT:', 'dingconnect-recargas' );
        $lines[] = $d['transfer_ref'] !== '' ? $d['transfer_ref'] : '—';
        if ( $d['distributor_ref'] ) {
            $lines[] = sprintf( __( 'Ref. interna: %s', 'dingconnect-recargas' ), $d['distributor_ref'] );
        }
        $lines[] = '';
        $lines[] = __( 'DETALLES DE LA OPERACIÓN', 'dingconnect-recargas' );
        $lines[] = sprintf( __( 'Teléfono:        %s', 'dingconnect-recargas' ), $d['account'] );
        $lines[] = sprintf( __( 'Operador:        %s', 'dingconnect-recargas' ), $d['provider'] );
        $lines[] = sprintf( __( 'Paquete:         %s', 'dingconnect-recargas' ), $d['bundle'] );
        $lines[] = sprintf( __( 'País:            %s', 'dingconnect-recargas' ), $d['country_iso'] );
        $lines[] = sprintf( __( 'Monto enviado:   %s %.2f', 'dingconnect-recargas' ), $d['send_currency'], $d['send_value'] );

        if ( $d['receive_value'] > 0 ) {
            $lines[] = sprintf( __( 'Monto recibido:  %s %.2f', 'dingconnect-recargas' ), $d['receive_currency'], $d['receive_value'] );
        }

        if ( $d['pin'] ) {
            $lines[] = sprintf( __( 'PIN:             %s', 'dingconnect-recargas' ), $d['pin'] );
        }

        if ( $d['provider_ref'] ) {
            $lines[] = sprintf( __( 'Ref. proveedor:  %s', 'dingconnect-recargas' ), $d['provider_ref'] );
        }

        $lines[] = sprintf( __( 'Estado:          %s', 'dingconnect-recargas' ), strtoupper( $d['status_label'] ) );

        if ( $d['receipt_text'] ) {
            $lines[] = '';
            $lines[] = __( 'RECIBO DEL OPERADOR:', 'dingconnect-recargas' );
            $lines[] = $d['receipt_text'];
        }

        $lines[] = '';
        $lines[] = sprintf( __( 'Ver mi pedido: %s', 'dingconnect-recargas' ), $order->get_view_order_url() );

        return implode( "\n", $lines );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Devuelve la URL del logo del sitio.
     * Prioridad: (1) imagen de encabezado configurada en WC emails, (2) logo del sitio WordPress.
     */
    private function get_logo_url() {
        // 1. Imagen de encabezado de emails WooCommerce
        $wc_header_image = get_option( 'woocommerce_email_header_image', '' );
        if ( $wc_header_image ) {
            return esc_url_raw( $wc_header_image );
        }

        // 2. Logo del sitio (WordPress 5.5+)
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $logo_src = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
            if ( $logo_src ) {
                return esc_url_raw( $logo_src );
            }
        }

        // 3. Site icon / favicon como fallback
        $site_icon_id = get_option( 'site_icon' );
        if ( $site_icon_id ) {
            $icon_src = wp_get_attachment_image_url( $site_icon_id, 'thumbnail' );
            if ( $icon_src ) {
                return esc_url_raw( $icon_src );
            }
        }

        return '';
    }

    public function get_content_type() {
        return 'text/html';
    }
}
