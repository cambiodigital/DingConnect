#!/usr/bin/env sh
set -eu

if [ ! -f wp-config.php ]; then
  wp core config \
    --dbname="${WORDPRESS_DB_NAME}" \
    --dbuser="${WORDPRESS_DB_USER}" \
    --dbpass="${WORDPRESS_DB_PASSWORD}" \
    --dbhost="${WORDPRESS_DB_HOST}" \
    --allow-root
fi

if ! wp core is-installed --allow-root >/dev/null 2>&1; then
  wp core install \
    --url="${WP_URL}" \
    --title="${WP_TITLE}" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email \
    --allow-root
fi

wp plugin install woocommerce --activate --allow-root
wp plugin activate dingconnect-recargas --allow-root

wp rewrite structure '/%postname%/' --allow-root
wp rewrite flush --allow-root

wp eval --allow-root '
$opts = wp_parse_args(get_option("dc_recargas_options", []), []);
$opts["wizard_enabled"] = 1;
$opts["validate_only"] = 1;
$opts["allow_real_recharge"] = 0;
$opts["wizard_transfer_retry_attempts"] = 2;
$opts["wizard_transfer_retry_delay_minutes"] = 15;
update_option("dc_recargas_options", $opts);
'

wp eval --allow-root '
$gateways = ["woocommerce_bacs_settings", "woocommerce_cheque_settings", "woocommerce_cod_settings"];
foreach ($gateways as $key) {
  $settings = get_option($key, []);
  if (!is_array($settings)) {
    $settings = [];
  }
  $settings["enabled"] = "yes";
  update_option($key, $settings);
}
'

wp post create \
  --post_type=page \
  --post_status=publish \
  --post_title='Wizard Recargas Test' \
  --post_name='wizard-recargas-test' \
  --post_content='[dingconnect_wizard_recargas]' \
  --allow-root >/dev/null

wp post create \
  --post_type=page \
  --post_status=publish \
  --post_title='Wizard GiftCards Test' \
  --post_name='wizard-giftcards-test' \
  --post_content='[dingconnect_wizard_giftcards]' \
  --allow-root >/dev/null

wp post create \
  --post_type=page \
  --post_status=publish \
  --post_title='Wizard Cuba Test' \
  --post_name='wizard-cuba-test' \
  --post_content='[dingconnect_wizard_cuba]' \
  --allow-root >/dev/null

echo "Staging bootstrap complete."
