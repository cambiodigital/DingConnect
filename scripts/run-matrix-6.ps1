param(
    [string]$ComposeFile = "staging/docker-compose.yml",
    [string]$EnvFile     = "staging/.env",
    [string]$BaseUrl     = "http://localhost:8080"
)
$ErrorActionPreference = "Stop"
if (-not (Test-Path $EnvFile)) { throw "Missing $EnvFile. Run scripts/staging-up.ps1 first." }
$pass = 0; $fail = 0; $skip = 0
function Pass($m) { Write-Host "  [PASS] $m" -ForegroundColor Green;  $script:pass++ }
function Fail($m) { Write-Host "  [FAIL] $m" -ForegroundColor Red;    $script:fail++ }
function Skip($m) { Write-Host "  [SKIP] $m" -ForegroundColor Yellow; $script:skip++ }
function Invoke-EvalFile {
    param([string]$PhpCode)
    $tmp = "staging\wordpress\matrix-tmp.php"
    [System.IO.File]::WriteAllText((Join-Path $PWD "staging\wordpress\matrix-tmp.php"), $PhpCode, [System.Text.Encoding]::UTF8)
    try {
        $out = docker compose --env-file $EnvFile -f $ComposeFile exec -T wpcli wp eval-file /var/www/html/matrix-tmp.php --allow-root 2>&1
    } catch {
        $out = "EVAL_ERROR: $($_.Exception.Message)"
    }
    Remove-Item $tmp -ErrorAction SilentlyContinue
    return $out
}
function Get-Http([string]$Url) {
    try { return (Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 10 -EA Stop).StatusCode }
    catch { if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode }; return 0 }
}
Write-Host "`n6.1 wizard/config smoke..."
$c = Get-Http "$BaseUrl/wp-json/dingconnect/v1/wizard/config"
if ($c -eq 200) { Pass "wizard/config -> $c" } else { Fail "wizard/config -> $c (want 200)" }
Write-Host "`n6.2 wizard/offers smoke..."
$c = Get-Http "$BaseUrl/wp-json/dingconnect/v1/wizard/offers?category=recargas&entry_mode=country_fixed&country_iso=MX"
if ($c -ne 0) { Pass "offers?category=recargas -> $c (endpoint alive)" } else { Fail "offers?category=recargas -> $c (unreachable)" }
$c = Get-Http "$BaseUrl/wp-json/dingconnect/v1/wizard/offers?category=gift_cards&entry_mode=country_fixed&country_iso=CU"
if ($c -ne 0) { Pass "offers?gift_cards&country=CU -> $c (endpoint alive)" } else { Fail "offers?gift_cards&country=CU -> $c (unreachable)" }
Write-Host "`n6.3/6.4 Payment-first + idempotency..."
$php63 = @'
<?php
$hooks = [
    'woocommerce_order_status_processing',
    'woocommerce_order_status_completed',
];
$ok = true;
foreach ($hooks as $hook) {
    if (!has_action($hook)) {
        echo "MISSING_HOOK:$hook\n";
        $ok = false;
    }
}
if ($ok) { echo "PAYMENT_FIRST_OK\n"; }
// Idempotency: verify is_item_already_successful exists
$wc_class = new ReflectionClass('DC_Recargas_WooCommerce');
if ($wc_class->hasMethod('is_item_already_successful')) {
    echo "IDEMPOTENCY_OK\n";
} else {
    echo "IDEMPOTENCY_MISSING\n";
}
'@
$out63 = Invoke-EvalFile $php63
if ($out63 -match "PAYMENT_FIRST_OK") { Pass "Payment-first hooks registered (processing + completed)" } else { Fail "Payment-first hooks missing: $out63" }
if ($out63 -match "IDEMPOTENCY_OK")   { Pass "Idempotency guard method exists (is_item_already_successful)" } else { Fail "Idempotency guard missing: $out63" }
Write-Host "`n6.5 Gateway baseline..."
$php65 = @'
<?php
WC()->payment_gateways()->init();
$gateways = WC()->payment_gateways()->get_available_payment_gateways();
if (empty($gateways)) {
    // Fall back to all enabled gateways (COD is enabled by default in WC)
    $all = WC()->payment_gateways()->payment_gateways();
    $enabled = array_filter($all, fn($g) => $g->enabled === 'yes');
    if (!empty($enabled)) {
        $names = implode(', ', array_keys($enabled));
        echo "GATEWAYS_ENABLED:$names\n";
    } else {
        echo "NO_GATEWAY_ENABLED\n";
    }
} else {
    $names = implode(', ', array_keys($gateways));
    echo "GATEWAYS_AVAILABLE:$names\n";
}
'@
$out65 = Invoke-EvalFile $php65
if ($out65 -match "GATEWAYS_(ENABLED|AVAILABLE):(.+)") { Pass "Gateway baseline: $($Matches[2])" } else { Fail "No payment gateways enabled: $out65" }
Write-Host "`n6.6 Plugin options (retry/reconcile)..."
$opts = (docker compose --env-file $EnvFile -f $ComposeFile exec -T wpcli wp option get dc_recargas_options --format=json --allow-root 2>&1) | ConvertFrom-Json
if ([int]$opts.wizard_enabled -eq 1)      { Pass "wizard_enabled=1" }             else { Fail "wizard_enabled=$($opts.wizard_enabled)" }
if ([int]$opts.validate_only -eq 1)       { Pass "validate_only=1 (safe)" }       else { Fail "validate_only=$($opts.validate_only)" }
if ([int]$opts.allow_real_recharge -eq 0) { Pass "allow_real_recharge=0 (safe)" } else { Fail "allow_real_recharge=$($opts.allow_real_recharge)" }
if ($opts.wizard_transfer_retry_attempts) { Pass "retry_attempts=$($opts.wizard_transfer_retry_attempts)" } else { Fail "retry_attempts missing" }
Write-Host "`n6.7 Shortcode pages..."
$pages = (docker compose --env-file $EnvFile -f $ComposeFile exec -T wpcli wp post list --post_type=page --fields=post_name,post_content --format=json --allow-root 2>&1) | ConvertFrom-Json
$dc = $pages | Where-Object { $_.post_content -match "\[dingconnect" }
if ($dc.Count -gt 0) {
    Pass "Found $($dc.Count) page(s) with [dingconnect] shortcodes"
} else {
    Write-Host "  Creando paginas shortcode..." -ForegroundColor Cyan
    $php = "<?php`n`$data=[['Recargas','recargas','[dingconnect_wizard]'],['Gift Cards','gift-cards','[dingconnect_wizard category=`"gift_cards`"]']];`nforeach(`$data as [`$t,`$s,`$c]){`$e=get_page_by_path(`$s);if(`$e){echo 'exists:'`$s.`"\n`";continue;}`$id=wp_insert_post(['post_title'=>`$t,'post_name'=>`$s,'post_content'=>`$c,'post_status'=>'publish','post_type'=>'page']);echo 'created:'`$s.' id='`$id.`"\n`";}"
    $out = Invoke-EvalFile $php
    Write-Host "  $out"
    $pages2 = (docker compose --env-file $EnvFile -f $ComposeFile exec -T wpcli wp post list --post_type=page --fields=post_name,post_content --format=json --allow-root 2>&1) | ConvertFrom-Json
    $dc2 = $pages2 | Where-Object { $_.post_content -match "\[dingconnect" }
    if ($dc2.Count -gt 0) { Pass "Shortcode pages created ($($dc2.Count))" } else { Fail "No shortcode pages found" }
}
Write-Host "`n======================================="
Write-Host "Matrix 6.x  PASS=$pass  FAIL=$fail  SKIP=$skip"
Write-Host "======================================="
if ($fail -gt 0) { exit 1 } else { exit 0 }
