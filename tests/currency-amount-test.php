<?php
if (PHP_SAPI !== 'cli') { exit; }
// Standalone test of SATS/BTC support, invoice amounts, the server-side total check, the
// dust-limit message and the form template, with minimal WordPress / Ninja Forms stubs and a fake HTTP API.
// Run: php tests/currency-amount-test.php
define('ABSPATH', '/');
define('COINSNAP_CURRENCIES', ['EUR', 'USD', 'SATS', 'BTC']);
$GLOBALS['t_settings'] = [];
$GLOBALS['t_requests'] = [];
$GLOBALS['t_extra'] = [];
$GLOBALS['t_filters'] = [];
$GLOBALS['t_fields'] = [];
function add_action(...$a) {}
function add_filter($name, $cb) { $GLOBALS['t_filters'][$name][] = $cb; }
function apply_filters($name, $value) { foreach ($GLOBALS['t_filters'][$name] ?? [] as $cb) { $value = call_user_func($cb, $value); } return $value; }
function is_admin() { return false; }
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return 'https://shop.test/'; }
function __($s, $d = null) { return $s; }
function esc_attr__($s, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return $s; }
function esc_url($s) { return $s; }
function admin_url($p) { return 'https://shop.test/wp-admin/' . $p; }
function wp_get_referer() { return 'https://shop.test/pay/'; }
function add_query_arg($a, $u) { return $u; }
function determine_locale() { return 'en_US'; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function is_wp_error($v) { return false; }
class T_Headers { public function getAll() { return []; } }
function wp_remote_retrieve_headers($r) { return new T_Headers(); }
function wp_remote_request($url, $args) {
    $GLOBALS['t_requests'][] = ['url' => $url, 'method' => $args['method'], 'body' => $args['body'] ?? null];
    $json = function ($v, $code = 200) { return ['response' => ['code' => $code], 'body' => json_encode($v)]; };
    if (strpos($url, 'coingecko') !== false) { return $json(['rates' => ['btc' => ['value' => 1], 'sats' => ['value' => 100000000], 'eur' => ['value' => 80000], 'usd' => ['value' => 90000]]]); }
    if ($args['method'] === 'POST' && substr($url, -9) === '/invoices') {
        $body = json_decode($args['body'], true);
        // The real API refuses on-chain invoices below 294 sats.
        if ($body['currency'] === 'SATS' && (float) $body['amount'] < 294) {
            return $json(['message' => 'Amount is below the on-chain dust threshold. Enable Lightning on this store to accept smaller amounts.'], 400);
        }
        return $json(['id' => 'inv' . count($GLOBALS['t_requests']), 'checkoutLink' => 'https://pay.test/i']);
    }
    if ($args['method'] === 'GET' && substr($url, -7) === '/store1') { return $json(['id' => 'store1']); }
    return ['response' => ['code' => 404], 'body' => '{}'];
}
class NF_Abstracts_PaymentGateway { public function __construct() {} }
class T_Setting { private $s; public function __construct(array $s) { $this->s = $s; } public function get_id() { return $this->s['id'] ?? 0; } public function get_setting($k) { return $this->s[$k] ?? null; } }
class T_Sub {
    public function get_seq_num() { return 1; }
    public function get_extra_value($k) { return $GLOBALS['t_extra'][$k] ?? null; }
    public function update_extra_value($k, $v) { $GLOBALS['t_extra'][$k] = $v; }
    public function save() {}
}
class T_Form {
    public function get_action($id) { return new T_Setting(['id' => $id, 'payment_total' => '{field:total_1}']); }
    public function get_fields() { return array_map(function ($s) { return new T_Setting($s); }, $GLOBALS['t_fields']); }
    public function sub($id = null) { return $this; }
    public function get() { return new T_Sub(); }
}
class T_NF {
    public function get_setting($k, $d = null) { return $GLOBALS['t_settings'][$k] ?? $d; }
    public function form($id = null) { return new T_Form(); }
}
function Ninja_Forms() { return new T_NF(); }
spl_autoload_register(function ($c) { if ($c === 'CoinsnapNF_Metadata') { require dirname(__DIR__) . '/includes/Metadata.php'; } if ($c === 'CoinsnapNF_Security') { require dirname(__DIR__) . '/includes/Security.php'; } });
require dirname(__DIR__) . '/coinsnap-for-ninja-forms.php';
require dirname(__DIR__) . '/includes/PaymentGateway.php';

$fail = 0;
$check = function ($ok, $label) use (&$fail) { echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n"; if (!$ok) { $fail++; } };

// --- Ninja Forms currency lists (shape of ninja-forms/includes/Config/Currency.php and CurrencySymbol.php, 3.16.1).
$currencies = apply_filters('ninja_forms_currency', ['EUR' => ['label' => 'Euros', 'value' => 'EUR']]);
$check(($currencies['SATS']['value'] ?? null) === 'SATS' && ($currencies['BTC']['value'] ?? null) === 'BTC', 'SATS and BTC are in the ninja_forms_currency list');
$check(($currencies['EUR']['value'] ?? null) === 'EUR', 'existing currencies are kept');
$symbols = apply_filters('ninja_forms_currency_symbol', ['BTC' => '&#3647;', 'EUR' => '&euro;']);
$check(isset($symbols['SATS']) && ($symbols['BTC'] ?? null) === '&#3647;', 'SATS gets a symbol, Ninja Forms\' BTC symbol is kept');

// --- create-invoice requests
$run = function (string $currency, array $product, string $quantity, string $browserTotal, array $settings = []) {
    $GLOBALS['t_requests'] = [];
    $GLOBALS['t_extra'] = [];
    $GLOBALS['t_settings'] = $settings + ['coinsnap_provider' => 'coinsnap', 'coinsnap_store_id' => 'store1', 'coinsnap_api_key' => 'key', 'currency' => 'USD'];
    $GLOBALS['t_fields'] = [
        array_merge(['id' => 2, 'type' => 'product', 'key' => 'product_2', 'label' => 'Product', 'product_type' => 'single', 'product_use_quantity' => 1], $product),
        ['id' => 3, 'type' => 'total', 'key' => 'total_1', 'label' => 'Total'],
    ];
    $f = function ($id, $type, $value) { $s = ['id' => $id, 'key' => $type . '_' . $id, 'type' => $type, 'label' => ucfirst($type), 'value' => $value]; return $s + ['settings' => $s]; };
    $fields = [2 => $f(2, 'product', $quantity), 3 => $f(3, 'total', $browserTotal)];
    $data = ['form_id' => 3, 'settings' => ['title' => 'Pay', 'currency' => $currency], 'fields' => $fields, 'fields_by_key' => $fields, 'actions' => ['save' => ['sub_id' => 42]]];
    $out = (new CoinsnapNF_PaymentGateway())->process(['id' => 7, 'payment_total' => $browserTotal], 3, $data);
    $create = array_values(array_filter($GLOBALS['t_requests'], function ($r) { return $r['method'] === 'POST' && substr($r['url'], -9) === '/invoices'; }));
    return [$out, $create ? json_decode($create[count($create) - 1]['body'], true) : null];
};

[$out, $body] = $run('SATS', ['product_price' => '500'], '3', '1,500.00');
$check(($body['currency'] ?? null) === 'SATS' && ($body['amount'] ?? null) === '1500', 'SATS: invoice for 1500 sats, no decimals');
$records = $GLOBALS['t_extra']['coinsnap_invoices'] ?? [];
$check(($records[array_key_first($records ?: [0])]['amount'] ?? null) === '1500', 'SATS: recorded expected amount is 1500');
$check(($GLOBALS['t_extra']['coinsnap_total'] ?? null) === '1,500', 'SATS: submission total shown without decimals');

[$out, $body] = $run('SATS', ['product_price' => 'sats 500'], '1', '500.00');
$check(($body['amount'] ?? null) === '500', 'SATS: price with a currency symbol is read');

[$out, $body] = $run('BTC', ['product_price' => '0.0005'], '2', '0.00');
$check(($body['currency'] ?? null) === 'BTC' && ($body['amount'] ?? null) === '0.00100000', 'BTC: invoice for 0.001 BTC with 8 decimals (browser total shows 0.00)');

[$out, $body] = $run('BTC', ['product_price' => '&#3647;0.00012345'], '1', '0.00');
$check(($body['amount'] ?? null) === '0.00012345', 'BTC: price with the ฿ symbol keeps all 8 decimals');

[$out, $body] = $run('EUR', ['product_price' => '25.50'], '2', '51.00');
$check(($body['currency'] ?? null) === 'EUR' && ($body['amount'] ?? null) === '51.00', 'EUR: invoice for 51.00');

[$out, $body] = $run('EUR', ['product_price' => '25.50'], '2', '50.00');
$check($body === null && strpos((string) ($out['errors']['form']['coinsnap'] ?? ''), 'could not be confirmed') !== false, 'EUR: a total changed in the browser is still refused');

[$out, $body] = $run('SATS', ['product_price' => '100'], '1', '100.00');
$check(($body['amount'] ?? null) === '100' && !isset($out['actions']['redirect']), 'SATS: 100 sats is refused by the API, no redirect');
$check(($out['errors']['form']['coinsnap'] ?? null) === 'This amount is too small for a Bitcoin payment. Please choose a larger amount.', 'SATS: the dust refusal is shown as a readable message');

// --- currency fallback
[$out, $body] = $run('', ['product_price' => '10.00'], '1', '10.00', ['currency' => '', 'coinsnap_currency' => 'EUR']);
$check(($body['currency'] ?? null) === 'EUR', 'no form or Ninja Forms currency: the old coinsnap_currency setting is used');
[$out, $body] = $run('', ['product_price' => '10.00'], '1', '10.00', ['currency' => 'USD', 'coinsnap_currency' => 'EUR']);
$check(($body['currency'] ?? null) === 'USD', 'Ninja Forms currency wins over the old coinsnap_currency setting');

// --- the form template
foreach (['coinsnap-payment.json', 'coinsnap-payment.nff'] as $file) {
    $tpl = json_decode(file_get_contents(dirname(__DIR__) . '/includes/Templates/' . $file), true);
    $product = array_values(array_filter($tpl['fields'], function ($f) { return $f['type'] === 'product'; }))[0] ?? [];
    $check(!empty($product['product_use_quantity']) ? (string) $product['default'] === '1' : true, "$file: product quantity starts at 1");
    $check((float) preg_replace('/[^\d.]/', '', $product['product_price'] ?? '') >= 1, "$file: product has a usable default price");
}

exit($fail ? 1 : 0);
