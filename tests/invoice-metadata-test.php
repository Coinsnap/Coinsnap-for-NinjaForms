<?php
if (PHP_SAPI !== 'cli') { exit; }
// Standalone test of the create-invoice request sent by CoinsnapNF_PaymentGateway::process(), for
// both providers, with minimal WordPress / Ninja Forms stubs and a fake HTTP API.
// Run: php tests/invoice-metadata-test.php
define('ABSPATH', '/');
define('COINSNAPNF_VERSION', '9.9.9');
define('COINSNAPNF_REFERRAL_CODE', 'REF');
define('COINSNAP_SERVER_URL', 'https://coinsnap.test');
define('COINSNAP_SERVER_PATH', 'stores');
define('COINSNAP_CURRENCIES', ['EUR', 'USD', 'SATS', 'BTC']);
$GLOBALS['t_settings'] = [];
$GLOBALS['t_requests'] = [];
$GLOBALS['t_extra'] = [];
function add_action(...$a) {}
function is_admin() { return false; }
function __($s, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return $s; }
function esc_url($s) { return $s; }
function admin_url($p) { return 'https://shop.test/wp-admin/' . $p; }
function wp_get_referer() { return 'https://shop.test/book/'; }
function add_query_arg($a, $u) { return $u; }
function determine_locale() { return 'de_DE'; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function is_wp_error($v) { return false; }
class T_Headers { public function getAll() { return []; } }
function wp_remote_retrieve_headers($r) { return new T_Headers(); }
function wp_remote_request($url, $args) {
    $GLOBALS['t_requests'][] = ['url' => $url, 'method' => $args['method'], 'body' => $args['body']];
    $json = function ($v) { return ['response' => ['code' => 200], 'body' => json_encode($v)]; };
    if (strpos($url, 'coingecko') !== false) { return $json(['rates' => ['eur' => ['value' => 80000], 'usd' => ['value' => 90000]]]); }
    if (substr($url, -16) === '/payment-methods') { return $json([['paymentMethodId' => 'BTC-LN', 'enabled' => true]]); }
    if (strpos($url, '/rates?') !== false) { return $json([['currencyPair' => 'BTC_EUR', 'rate' => '80000', 'errors' => []]]); }
    if ($args['method'] === 'POST' && substr($url, -9) === '/invoices') { return $json(['id' => 'inv' . count($GLOBALS['t_requests']), 'checkoutLink' => 'https://pay.test/i']); }
    if ($args['method'] === 'GET' && substr($url, -7) === '/store1') { return $json(['id' => 'store1']); }
    return ['response' => ['code' => 404], 'body' => '{}'];
}
class NF_Abstracts_PaymentGateway { public function __construct() {} }
class T_Sub {
    public function get_seq_num() { return 7; }
    public function get_extra_value($k) { return $GLOBALS['t_extra'][$k] ?? null; }
    public function update_extra_value($k, $v) { $GLOBALS['t_extra'][$k] = $v; }
    public function save() {}
}
class T_Form {
    public function get_action($id) { return null; } // the payment total is a fixed amount here
    public function sub($id = null) { return $this; }
    public function get() { return new T_Sub(); }
}
class T_NF {
    public function get_setting($k, $d = null) { return $GLOBALS['t_settings'][$k] ?? $d; }
    public function form($id = null) { return new T_Form(); }
}
function Ninja_Forms() { return new T_NF(); }
spl_autoload_register(function ($c) { if ($c === 'CoinsnapNF_Metadata') { require dirname(__DIR__) . '/includes/Metadata.php'; } if ($c === 'CoinsnapNF_Security') { require dirname(__DIR__) . '/includes/Security.php'; } });
require dirname(__DIR__) . '/library/loader.php';
require dirname(__DIR__) . '/includes/PaymentGateway.php';

// Ninja Forms flattens each field's settings into the field and keeps them under 'settings' too.
$f = function ($id, $type, $label, $value, $extra = []) { $s = array_merge(['id' => $id, 'key' => $type . '_' . $id, 'type' => $type, 'label' => $label, 'value' => $value], $extra); return $s + ['settings' => $s]; };
$fields = [
    1 => $f(1, 'firstname', 'First Name', 'Ana'), 2 => $f(2, 'lastname', 'Last Name', 'Buyer'),
    3 => $f(3, 'email', 'Email', 'ana@example.com'), 4 => $f(4, 'phone', 'Phone', '+381 11 123'),
    5 => $f(5, 'address', 'Address', 'Main St 1'), 6 => $f(6, 'city', 'City', 'Belgrade'), 7 => $f(7, 'zip', 'Zip', '11000'),
    8 => $f(8, 'liststate', 'State', 'NY'), 9 => $f(9, 'listcountry', 'Country', 'RS'),
    10 => $f(10, 'textarea', 'Message', "Please\nbring coffee"), 11 => $f(11, 'listselect', 'Session', 'morning'),
    12 => $f(12, 'listcheckbox', 'Extras', ['lunch', 'parking']), 13 => $f(13, 'textbox', 'Card', '4111 1111 1111 1111'),
    14 => $f(14, 'hidden', 'Ref', 'abc'), 15 => $f(15, 'password', 'Password', 'hunter2'), 16 => $f(16, 'recaptcha', 'Captcha', 'tok'),
    17 => $f(17, 'product', 'Ticket', '', ['product_price' => '20.00', 'product_use_quantity' => 0]),
    18 => $f(18, 'quantity', 'Tickets', '2', ['product_assignment' => 17]),
    19 => $f(19, 'shipping', 'Standard shipping', '', ['shipping_cost' => '$5.00']), 20 => $f(20, 'total', 'Total', '45.00'),
    21 => $f(21, 'textbox', 'Empty', ''), 22 => $f(22, 'submit', 'Pay', ''),
];
$data = ['form_id' => 3, 'settings' => ['title' => 'Workshop booking', 'currency' => 'EUR'], 'fields' => $fields,
    'fields_by_key' => $fields, 'actions' => ['save' => ['sub_id' => 42]]];

$fail = 0;
$check = function ($ok, $label) use (&$fail) { echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n"; if (!$ok) { $fail++; } };

foreach (['coinsnap', 'btcpay'] as $provider) {
    $GLOBALS['t_requests'] = [];
    $GLOBALS['t_extra'] = [];
    $GLOBALS['t_settings'] = ['coinsnap_provider' => $provider, 'coinsnap_store_id' => 'store1', 'coinsnap_api_key' => 'key',
        'btcpay_store_id' => 'store1', 'btcpay_api_key' => 'key', 'btcpay_server_url' => 'https://btcpay.test',
        'coinsnap_returnurl' => 'https://shop.test/thanks/', 'coinsnap_autoredirect' => 1];
    $gateway = new CoinsnapNF_PaymentGateway();
    $out = $gateway->process(['id' => 0, 'payment_total' => '45.00'], 3, $data);
    $check(($out['actions']['redirect'] ?? null) === 'https://pay.test/i', "$provider: buyer is sent to the checkout");
    $create = array_values(array_filter($GLOBALS['t_requests'], function ($r) { return $r['method'] === 'POST' && substr($r['url'], -9) === '/invoices'; }));
    $check(count($create) === 1, "$provider: one create-invoice request");
    if (!$create) { continue; }
    $body = json_decode($create[0]['body'], true);
    $m = $body['metadata'] ?? [];
    $check((string) ($body['orderId'] ?? '') === '42', "$provider: top-level orderId is the submission ID");
    $check(($body['buyerEmail'] ?? null) === 'ana@example.com', "$provider: top-level buyerEmail");
    $expected = [
        'orderId' => '42', 'orderNumber' => '42', 'entryId' => '42', 'submissionNumber' => '7',
        'orderUrl' => 'https://shop.test/wp-admin/post.php?post=42&action=edit',
        'formId' => '3', 'formTitle' => 'Workshop booking', 'itemDesc' => 'Workshop booking', 'platform' => 'ninja-forms',
        'pluginVersion' => '9.9.9', 'pageUrl' => 'https://shop.test/book/', 'language' => 'de_DE',
        'shippingMethod' => 'Standard shipping', 'shippingTotal' => '5.00',
        'customerName' => 'Ana Buyer', 'buyerName' => 'Ana Buyer', 'buyerEmail' => 'ana@example.com', 'buyerPhone' => '+381 11 123',
        'buyerAddress1' => 'Main St 1', 'buyerCity' => 'Belgrade', 'buyerState' => 'NY', 'buyerZip' => '11000', 'buyerCountry' => 'RS',
        'buyerMessage' => "Please\nbring coffee",
    ];
    foreach ($expected as $k => $v) {
        $check(($m[$k] ?? null) === $v, "$provider: metadata.$k");
    }
    $check(($m['lineItems'] ?? null) === [['name' => 'Ticket', 'sku' => 'product_17', 'quantity' => 2, 'price' => '20.00', 'total' => '40.00']], "$provider: lineItems");
    $check(($m['formFields'] ?? null) === ['Session' => 'morning', 'Extras' => 'lunch, parking'], "$provider: formFields has only the other filled-in fields");
    $flat = json_encode($m);
    $leaked = array_filter(['4111 1111 1111 1111', 'abc', 'hunter2', 'tok'], function ($v) use ($flat) { return strpos($flat, $v) !== false; });
    $check(!$leaked, "$provider: no card number, hidden, password or captcha values");
    $check(isset($GLOBALS['t_extra']['coinsnap_invoices']), "$provider: invoice recorded on the submission");
}
exit($fail ? 1 : 0);
