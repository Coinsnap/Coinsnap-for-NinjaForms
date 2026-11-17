<?php 
if ( ! defined( 'ABSPATH' ) ){ exit;}

if( ! class_exists( 'NF_Abstracts_PaymentGateway' ) ){
    return;
}

use Coinsnap\Client\Webhook;
/**
 * The Coinsnap payment gateway for the Collect Payment action.
 */
class CoinsnapNF_PaymentGateway extends NF_Abstracts_PaymentGateway
{
    protected $_slug = 'coinsnap';
    public const COINSNAP_WEBHOOK_EVENTS = ['New','Expired','Settled','Processing','Invalid'];
    public const BTCPAY_WEBHOOK_EVENTS = ['InvoiceCreated','InvoiceExpired','InvoiceSettled','InvoiceProcessing','InvoiceInvalid'];

    public function __construct(){
        parent::__construct();

        $this->_name = 'Coinsnap';
        add_action( 'ninja_forms_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts'] );
            add_action('wp_ajax_coinsnapnf_connection_handler', [$this, 'coinsnapConnectionHandler'] );
            add_action('wp_ajax_coinsnapnf_btcpay_server_apiurl_handler', [$this, 'btcpayApiUrlHandler']);
        }
        
        // BTCPay posts the new API key back to this public URL. The proof is the one-time state
        // created when the admin clicked "Generate API key"; what the key may do is asked from BTCPay.
        add_action( 'template_redirect', array( $this, 'btcpayCallback' ) );
    }

    public function btcpayCallback(){
        global $wp_query;
        if (!isset( $wp_query->query_vars['coinsnap-for-ninja-forms-btcpay-settings-callback'])) {
            return;
        }
        $notice = new \Coinsnap\Util\Notice();
        $settingsUrl = admin_url('admin.php?page=nf-settings');

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the one-time state is the proof
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        // The BTCPay URL comes from the state saved on the server, never from this request.
        $host = CoinsnapNF_Security::btcpay_consume_state($state);
        if ($host === null) {
            $notice->addNotice('error', __('The BTCPay connection request has expired or is invalid. Please try again.', 'coinsnap-for-ninja-forms'));
            wp_safe_redirect($settingsUrl);
            exit();
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the one-time state is the proof
        $apiKey = isset($_POST['apiKey']) ? sanitize_text_field(wp_unslash($_POST['apiKey'])) : '';
        // Permissions and store ID from BTCPay itself; anything else in the POST is ignored.
        $storeId = CoinsnapNF_Security::btcpay_verify_key($host, $apiKey);
        if ($storeId === null) {
            $notice->addNotice('error', __('The BTCPay API key was refused. It must be limited to one store and have only the requested permissions.', 'coinsnap-for-ninja-forms'));
            wp_safe_redirect($settingsUrl);
            exit();
        }

        Ninja_Forms()->update_setting('btcpay_server_url', $host);
        Ninja_Forms()->update_setting('btcpay_api_key', $apiKey);
        Ninja_Forms()->update_setting('btcpay_store_id', $storeId);
        Ninja_Forms()->update_setting('coinsnap_provider', 'btcpay');

        $notice->addNotice('success', __('Successfully received api key and store id from BTCPay Server API. Please finish setup by saving this settings form.', 'coinsnap-for-ninja-forms'));
        wp_safe_redirect($settingsUrl);
        exit();
    }

    public function enqueueAdminScripts(): void {
        // The nonce below lets the page run the connection check; only admins get it.
        if (!current_user_can('manage_options')) {
            return;
        }
        // Register the CSS file
	wp_register_style( 'coinsnap-admin-styles', CoinsnapNF::$url . 'assets/css/coinsnap-style.css',array(),COINSNAPNF_VERSION);
        // Enqueue the CSS file
	wp_enqueue_style( 'coinsnap-admin-styles' );
        
        if('nf-settings' === filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS)){
            wp_enqueue_script('coinsnapnf-admin-fields',CoinsnapNF::$url . 'assets/js/adminFields.js',[ 'jquery' ],COINSNAPNF_VERSION,true);
        }
        
        wp_enqueue_script('coinsnapnf-connection-check',CoinsnapNF::$url . 'assets/js/connectionCheck.js',[ 'jquery' ],COINSNAPNF_VERSION,true);
        wp_localize_script('coinsnapnf-connection-check', 'coinsnap_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce( 'coinsnap-ajax-nonce' )
        ));
    }
    
    public function coinsnapConnectionHandler(){
        
        // Capability and nonce first: nothing below may run for anyone else (guide 6.5).
        $_nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!current_user_can('manage_options') || !wp_verify_nonce($_nonce,'coinsnap-ajax-nonce')) {
            wp_send_json(['result' => false, 'message' => __('Unauthorized', 'coinsnap-for-ninja-forms')], 403);
        }
        
        if(empty($this->getApiUrl()) || empty($this->getApiKey())){
            $response = [
                    'result' => false,
                    'message' => __('Ninja Forms: empty gateway URL or API Key', 'coinsnap-for-ninja-forms')
            ];
            $this->sendJsonResponse($response);
        }
        
        $_provider = $this->get_payment_provider();
        $client = new \Coinsnap\Client\Invoice($this->getApiUrl(),$this->getApiKey());
        $store = new \Coinsnap\Client\Store($this->getApiUrl(),$this->getApiKey());
        $currency = Ninja_Forms()->get_setting('currency');
        
        
        if($_provider === 'btcpay'){
            try {
                $storePaymentMethods = $store->getStorePaymentMethods($this->getStoreId());

                if ($storePaymentMethods['code'] === 200) {
                    if($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData(0,$currency,'bitcoin','calculation');
                    }
                    elseif($storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData(0,$currency,'lightning','calculation');
                    }
                }
            }
            catch (\Exception $e) {
                $response = [
                        'result' => false,
                        'message' => __('Ninja Forms: API connection is not established', 'coinsnap-for-ninja-forms')
                ];
                $this->sendJsonResponse($response);
            }
        }
        else {
            $checkInvoice = $client->checkPaymentData(0,$currency,'coinsnap','calculation');
        }
        
        if(isset($checkInvoice) && $checkInvoice['result']){
            $connectionData = __('Min order amount is', 'coinsnap-for-ninja-forms') .' '. $checkInvoice['min_value'].' '.$currency;
        }
        else {
            $connectionData = __('No payment method is configured', 'coinsnap-for-ninja-forms');
        }
        
        $_message_disconnected = ($_provider !== 'btcpay')? 
            __('Ninja Forms: Coinsnap server is disconnected', 'coinsnap-for-ninja-forms') :
            __('Ninja Forms: BTCPay server is disconnected', 'coinsnap-for-ninja-forms');
        $_message_connected = ($_provider !== 'btcpay')?
            __('Ninja Forms: Coinsnap server is connected', 'coinsnap-for-ninja-forms') : 
            __('Ninja Forms: BTCPay server is connected', 'coinsnap-for-ninja-forms');
        
        $response = ['result' => true,'message' => $_message_connected.' ('.$connectionData.')'];

        try {
            $this_store = $store->getStore($this->getStoreId());

            if ($this_store['code'] !== 200) {
                $response = ['result' => false,'message' => $_message_disconnected];
                $this->sendJsonResponse($response);
            }
        }
        catch (\Throwable $e) {
            $response = ['result' => false,'message' => __('Ninja Forms: API connection is not established', 'coinsnap-for-ninja-forms')];
            $this->sendJsonResponse($response);
        }

        // In the form builder, make sure this form's webhook exists. Only here, after the checks above.
        $form_id = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
        if ($form_id > 0) {
            $webhookResult = $this->ensureWebhook($form_id);
            if ($webhookResult === 'created') {
                $response['message'] .= ' '.__('Successfully registered webhook on Coinsnap Server', 'coinsnap-for-ninja-forms');
            }
            elseif ($webhookResult === 'error') {
                $response = ['result' => false,'message' => $response['message'].' '.__('Unable to create webhook on Coinsnap Server', 'coinsnap-for-ninja-forms')];
            }
        }

        $this->sendJsonResponse($response);
    }
    
    private function sendJsonResponse(array $response): void {
        echo wp_json_encode($response);
        exit();
    }
    
    public function enqueue_scripts( $data )
    {        
        wp_enqueue_script('coinsnapnf-response', CoinsnapNF::$url . 'assets/js/error-handler.js', array( 'nf-front-end' ), COINSNAPNF_VERSION, true );
    }
    
    
    /**
     * Handles the BTCPay server AJAX callback from the settings form.
     */
    public function btcpayApiUrlHandler() {
        // Capability and nonce before anything else (guide 6.5).
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error('Unauthorized', 403);
        }
        $_nonce = isset($_POST['apiNonce']) ? sanitize_text_field(wp_unslash($_POST['apiNonce'])) : '';
        if ( !wp_verify_nonce( $_nonce, 'coinsnap-ajax-nonce' ) ) {
            wp_die('Unauthorized!', '', ['response' => 401]);
        }

        $host = isset($_POST['host']) ? filter_var(esc_url_raw(wp_unslash($_POST['host'])), FILTER_VALIDATE_URL) : false;
        if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
            wp_send_json_error("Error validating BTCPayServer URL.");
        }
        $host = rtrim($host, '/');

        try {
            // The URL is saved only together with a key that BTCPay confirms (guide 6.14), so it travels with the state.
            $state = CoinsnapNF_Security::btcpay_start_state($host);

            // Only the permissions the plugin uses, for one store (strict, selectiveStores).
            $url = \Coinsnap\Client\BTCPayApiKey::getAuthorizeUrl(
                $host,
                CoinsnapNF_Security::BTCPAY_PERMISSIONS,
                'NinjaForms',
                true,
                true,
                add_query_arg(['coinsnap-for-ninja-forms-btcpay-settings-callback' => '1', 'state' => $state], home_url('/')),
                null
            );
            wp_send_json_success(['url' => $url]);
        }
        catch (\Throwable $e) {
            wp_send_json_error("Error processing Ajax request.");
        }
    }

    private function respond(int $code): void {
        status_header($code);
        echo ($code === 200) ? 'OK' : (($code === 401) ? 'Unauthorized' : 'Retry later');
        exit;
    }

    /**
     * Webhook handler. Only the invoice ID is taken from the request; whether a submission
     * is paid is decided from the invoice the API returns (guide fixes 2-4).
     */
    public function webhook(){
        
        //  nf-listener get parameter check
        if ( null === ( filter_input(INPUT_GET,'nf-listener') ) || filter_input(INPUT_GET,'nf-listener') !== 'coinsnap' ) {
            return;
        }
        //  form_id get parameter check
        $form_id = filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT);
        if ( $form_id < 1 ) {
            return;
        }

        $rawPostData = (string) file_get_contents("php://input");
        $isBtcpay = ($this->get_payment_provider() === 'btcpay');

        // 1. Signature over the raw body, with this form's own secret. An empty secret rejects everything.
        $secret = $this->webhookSecret($form_id);
        if ($isBtcpay) {
            $header = isset($_SERVER['HTTP_BTCPAY_SIG']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_BTCPAY_SIG'])) : '';
            $problem = CoinsnapNF_Security::btcpay_signature_problem($header, $rawPostData, $secret);
        }
        else {
            $header = isset($_SERVER['HTTP_X_COINSNAP_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_COINSNAP_SIGNATURE'])) : '';
            $problem = CoinsnapNF_Security::coinsnap_signature_problem($header, $rawPostData, $secret, time());
        }
        if ($problem !== null) {
            CoinsnapNF_Security::log('security', $problem);
            $this->respond(401);
        }

        // 2. Take only the invoice ID from the body.
        $postData = json_decode($rawPostData, true);
        $invoice_id = (is_array($postData) && is_string($postData['invoiceId'] ?? null)) ? $postData['invoiceId'] : '';
        if ($invoice_id === '') {
            $this->respond(200);
        }
        $claimsPaid = in_array($postData['type'] ?? '', ['Settled', 'InvoiceSettled'], true);

        // 3. Fetch the invoice: error -> 503 (the provider retries), unknown -> 200.
        $invoice = $this->fetchInvoice($invoice_id);
        if ($invoice === false) {
            CoinsnapNF_Security::log('info', 'retry: API unavailable', $invoice_id);
            $this->respond(503);
        }
        if ($invoice === null) {
            // The dashboard's "Send test webhook" button lands here.
            CoinsnapNF_Security::log('info', 'unknown invoice', $invoice_id);
            $this->respond(200);
        }

        // 4. Find the submission from the API's answer and check the invoice is one this plugin created for it.
        $sub_id = $this->submissionFromInvoice($invoice, $isBtcpay);
        $expected = ($sub_id > 0) ? $this->expectedFor($sub_id, $form_id, $invoice_id) : null;
        if ($expected === null) {
            CoinsnapNF_Security::log('info', 'not ours', $invoice_id);
            $this->respond(200);
        }

        $decision = CoinsnapNF_Security::decide($invoice, $isBtcpay, $expected['amount'], $expected['currency']);
        if ($decision !== CoinsnapNF_Security::PAID && ($claimsPaid || $decision === CoinsnapNF_Security::MISMATCH)) {
            CoinsnapNF_Security::log('security', $claimsPaid ? 'forged status' : 'amount mismatch', $invoice_id);
            $this->respond(200);
        }
        if ($decision === CoinsnapNF_Security::NONE) {
            CoinsnapNF_Security::log('info', 'status '.preg_replace('/[^A-Za-z]/', '', (string) ($invoice['status'] ?? '?')), $invoice_id);
            $this->respond(200);
        }

        // 5. Act once, then reply 200.
        if (!$this->applyDecision($sub_id, $decision, $invoice_id, (string) ($invoice['status'] ?? ''))) {
            $this->respond(503);
        }
        $this->respond(200);
    }

    // The invoice, null if the API doesn't know it (Coinsnap 400, BTCPay 404), or false on any other error.
    private function fetchInvoice(string $invoice_id) {
        try {
            $client = new \Coinsnap\Client\Invoice( $this->getApiUrl(), $this->getApiKey() );
            return $client->getInvoice((string) $this->getStoreId(), $invoice_id)->getData();
        }
        catch (\Coinsnap\Exception\RequestException $e) {
            return in_array((int) $e->getCode(), [400, 404], true) ? null : false;
        }
        catch (\Throwable $e) {
            return false;
        }
    }

    private function submissionFromInvoice(array $invoice, bool $isBtcpay): int {
        $metadata = is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];
        $orderId = $isBtcpay ? ($metadata['orderId'] ?? '') : ($invoice['orderId'] ?? ($metadata['orderNumber'] ?? ''));
        return (is_scalar($orderId) && preg_match('/^\d+$/', (string) $orderId)) ? (int) $orderId : 0;
    }

    /**
     * What the plugin asked for when it created this invoice for this submission, or null if
     * the invoice isn't one this plugin created for a submission of this form.
     */
    private function expectedFor(int $sub_id, int $form_id, string $invoice_id): ?array {
        if (get_post_type($sub_id) !== 'nf_sub') {
            return null;
        }
        $sub = Ninja_Forms()->form()->sub($sub_id)->get();
        if (!$sub || (int) $sub->get_form_id() !== $form_id) {
            return null;
        }
        $records = $sub->get_extra_value('coinsnap_invoices');
        if (is_array($records) && $records !== []) {
            if (!isset($records[$invoice_id]['amount'], $records[$invoice_id]['currency'])) {
                return null;
            }
            return ['amount' => (string) $records[$invoice_id]['amount'], 'currency' => (string) $records[$invoice_id]['currency']];
        }
        // Submissions from before invoice records were kept: one day after the upgrade, by the stored total (6.7).
        if (!CoinsnapNF_Security::legacy_allowed()) {
            return null;
        }
        $form = Ninja_Forms()->form($form_id)->get();
        $settings = $form ? ['settings' => ['currency' => (string) $form->get_setting('currency')]] : [];
        return [
            'amount' => str_replace(',', '', (string) $sub->get_extra_value('coinsnap_total')),
            'currency' => strtoupper((string) $this->get_currency($settings)),
        ];
    }

    /**
     * Statuses only move forward: a paid submission never changes again, and the expiry of one
     * invoice does not overwrite another invoice that is being paid (6.8). Returns false if the
     * lock could not be taken, so the provider retries.
     */
    private function applyDecision(int $sub_id, string $decision, string $invoice_id, string $apiStatus): bool {
        global $wpdb;
        $lock = 'coinsnapnf_sub_' . $sub_id;
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) {
            return false;
        }
        try {
            $sub = Ninja_Forms()->form()->sub($sub_id)->get();
            $current = (string) $sub->get_extra_value('coinsnap_status');
            $paidBy = (string) $sub->get_extra_value('coinsnap_paid_invoice');

            if ($paidBy !== '' || $current === 'Settled') {
                CoinsnapNF_Security::log('info', 'already processed', $invoice_id);
                return true;
            }
            if ($decision === CoinsnapNF_Security::PAID) {
                $this->update_submission($sub_id, ['coinsnap_status' => 'Settled', 'coinsnap_transaction_id' => $invoice_id, 'coinsnap_paid_invoice' => $invoice_id]);
                CoinsnapNF_Security::log('info', 'paid', $invoice_id);
                return true;
            }
            if ($decision === CoinsnapNF_Security::FAILED && $current === 'Processing' && (string) $sub->get_extra_value('coinsnap_transaction_id') !== $invoice_id) {
                CoinsnapNF_Security::log('info', 'no change', $invoice_id); // another invoice of this submission is being paid
                return true;
            }
            $status = ($decision === CoinsnapNF_Security::PROCESSING) ? 'Processing' : (($apiStatus === 'Invalid') ? 'Invalid' : 'Expired');
            $this->update_submission($sub_id, ['coinsnap_status' => $status, 'coinsnap_transaction_id' => $invoice_id]);
            CoinsnapNF_Security::log('info', 'status '.$status, $invoice_id);
            return true;
        }
        finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    /**
     * The customer is back from the payment page. Opening this URL proves nothing, so ask the API
     * (guide 6.6): the remaining form actions run only if an invoice of this submission is paid or
     * being paid.
     */
    private function verifyOnReturn(array $data, int $form_id): array {
        $sub_id = (int) $this->get_sub_id($data);
        $records = [];
        if ($sub_id > 0) {
            $sub = Ninja_Forms()->form()->sub($sub_id)->get();
            $records = $sub ? $sub->get_extra_value('coinsnap_invoices') : [];
        }
        if ((!is_array($records) || $records === []) && isset($data['coinsnap_invoices']) && is_array($data['coinsnap_invoices'])) {
            $records = $data['coinsnap_invoices']; // no saved submission: the record kept in the server-side session
        }
        $isBtcpay = ($this->get_payment_provider() === 'btcpay');
        $apiError = false;

        if (is_array($records)) {
            foreach (array_reverse($records, true) as $invoice_id => $expected) {
                $invoice = $this->fetchInvoice((string) $invoice_id);
                if ($invoice === false) {
                    $apiError = true;
                    continue;
                }
                if (!is_array($invoice) || !is_array($expected)) {
                    continue;
                }
                $decision = CoinsnapNF_Security::decide($invoice, $isBtcpay, (string) ($expected['amount'] ?? ''), (string) ($expected['currency'] ?? ''));
                if ($decision === CoinsnapNF_Security::PAID || $decision === CoinsnapNF_Security::PROCESSING) {
                    if ($sub_id > 0) {
                        $this->applyDecision($sub_id, $decision, (string) $invoice_id, (string) ($invoice['status'] ?? ''));
                    }
                    return $data;
                }
            }
        }

        $data['errors']['form']['coinsnap'] = $apiError
            ? __('The payment could not be checked right now. Please reload this page in a moment.', 'coinsnap-for-ninja-forms')
            : __('The payment has not been received yet.', 'coinsnap-for-ninja-forms');
        return $data;
    }

    function coinsnapnf_amount_validation( $amount, $currency ) {
        $client =new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
        
        try {
            $this_store = $store->getStore($this->getStoreId());
            $_provider = $this->get_payment_provider();
            if($_provider === 'btcpay'){
                try {
                    $storePaymentMethods = $store->getStorePaymentMethods($this->getStoreId());

                    if ($storePaymentMethods['code'] === 200) {
                        if(!$storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                            $errorMessage = __( 'No payment method is configured on BTCPay server', 'coinsnap-for-ninja-forms' );
                            $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
                        }
                    }
                    else {
                        $errorMessage = __( 'Error store loading. Wrong or empty Store ID', 'coinsnap-for-ninja-forms' );
                        $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
                    }

                    if($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ),'bitcoin');
                    }
                    elseif($storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ),'lightning');
                    }
                }
                catch (\Throwable $e){
                    $errorMessage = __( 'API connection is not established', 'coinsnap-for-ninja-forms' );
                    $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
                }
            }
            else {
                $checkInvoice = $client->checkPaymentData((float)$amount,strtoupper( $currency ));
            }
        }
        catch (\Throwable $e){
            $errorMessage = __( 'API connection is not established', 'coinsnap-for-ninja-forms' );
            $checkInvoice = array('result' => false,'error' => esc_html($errorMessage));
        }
        return $checkInvoice;
    }
    
    public function process( $action_settings, $form_id, $data ){
        
        // Back from the payment page: continue only if the API says an invoice of this submission is paid.
        if(isset( $data[ 'resume' ] )){
            return $this->verifyOnReturn($data, (int) $form_id);
        }

        // A Total field's value comes from the browser; recompute it from the saved form (guide: price never from the browser).
        $serverTotal = $this->serverTotal( (int) $form_id, $action_settings[ 'id' ] ?? 0, $data );
        if ( $serverTotal !== null ) {
            if ( $serverTotal === false || CoinsnapNF_Security::to_units( str_replace( ',', '', (string) $action_settings[ 'payment_total' ] ) ) !== CoinsnapNF_Security::to_units( $serverTotal ) ) {
                $data['errors']['form']['coinsnap'] = __( 'The order total could not be confirmed. Please reload the page and try again.', 'coinsnap-for-ninja-forms' );
                return $data;
            }
            $action_settings[ 'payment_total' ] = $serverTotal;
        }

        $client =new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        $currency = $this->get_currency( $data );
        $amount = (float)$action_settings[ 'payment_total' ];
        
        $checkInvoice = $this->coinsnapnf_amount_validation($amount,strtoupper( $currency ));
        
        if($checkInvoice['result'] === true){
        
            $payment_total = number_format( $action_settings[ 'payment_total' ], 2, '.', ',' );        
            $return_url = (!empty(Ninja_Forms()->get_setting( 'coinsnap_returnurl' )))? Ninja_Forms()->get_setting( 'coinsnap_returnurl' ) : add_query_arg( array( 'nf_resume' => $form_id, 'coinsnap_act' => 'success' ), wp_get_referer() );


            $invoice_no = $this->get_sub_id( $data );
            $first_name = $this->get_nf_data($data, 'firstname');
            $last_name = $this->get_nf_data($data, 'lastname');
            $buyerEmail = $this->get_nf_data($data, 'email');
            $buyerName = $first_name.' '.$last_name;
            $metadata = [];
            $metadata['orderNumber'] = $invoice_no;
            $metadata['customerName'] = $buyerName;
            
            if($this->get_payment_provider() === 'btcpay') {
                $metadata['orderId'] = $invoice_no;
            }

            // Handle currencies non-supported by BTCPay Server, we need to change them BTC and adjust the amount.
            if($this->get_payment_provider() === 'btcpay' && $currency !== 'BTC'){
                $store = new \Coinsnap\Client\Store($this->getApiUrl(), $this->getApiKey());
                $btcpayCurrencies = $store -> getStoreCurrenciesRates($this->getStoreId(),array($currency));
                $isCurrency = true;
                if(!isset($btcpayCurrencies['result']['error']) && count($btcpayCurrencies['result']['currencies'])>0){
                        if(!isset($btcpayCurrencies['result']['currencies']['BTC_'.$currency])){
                            $isCurrency = false;
                        }
                }
                else {
                    $isCurrency = false;
                }
                    
                // Handle currencies non-supported by BTCPay Server, we need to change them BTC and adjust the amount.
                if( !$isCurrency ){
                        $currency = 'BTC';
                        $rate = 1/$checkInvoice['rate'];
                        $amountBTC = bcdiv(strval($amount), strval($rate), 8);
                        $amount = (float)$amountBTC;
                }
            }
                
            $camount = ($currency === 'BTC')? \Coinsnap\Util\PreciseNumber::parseFloat($amount,8) : \Coinsnap\Util\PreciseNumber::parseFloat($amount,2);
            
            $redirectAutomatically = ($this->getAutoRedirect() == 1)? true : false;
            $walletMessage = '';
            
            try {

                $csinvoice = $client->createInvoice(
                    $this->getStoreId(),  
                    strtoupper( $currency ),
                    $camount,
                    $invoice_no,
                    $buyerEmail,
                    $buyerName, 
                    $return_url,
                    COINSNAPNF_REFERRAL_CODE,     
                    $metadata,
                    $redirectAutomatically,
                    $walletMessage
                );

                $payurl = $csinvoice->getData()['checkoutLink'] ;   
                $newInvoiceId = $csinvoice->getData()['id'] ?? null;
                if (isset($payurl) && is_string($newInvoiceId) && $newInvoiceId !== ''){
                    $data[ 'halt' ] = TRUE;
                    $data[ 'actions' ][ 'redirect' ] = $payurl;

                    // Keep every invoice created for this submission with what it asked for (guide 3, 6.7, 6.9).
                    $sub_id = $this->get_sub_id( $data );
                    $records = [];
                    if ($sub_id) {
                        $sub = Ninja_Forms()->form()->sub( $sub_id )->get();
                        $records = $sub ? $sub->get_extra_value( 'coinsnap_invoices' ) : [];
                    }
                    $records = is_array($records) ? $records : [];
                    $records[$newInvoiceId] = ['amount' => (string) $camount, 'currency' => strtoupper( $currency ), 'created' => time()];
                    // Also in the server-side session, for the return page when the form doesn't save submissions.
                    $data['coinsnap_invoices'] = $records;

                    $this->update_submission( $sub_id, array(
                        'coinsnap_status' => esc_html__( 'Pending', 'coinsnap-for-ninja-forms' ),
                        'coinsnap_total' => $payment_total,
                        'coinsnap_invoices' => $records,
                    ) );    
                }
            
            }
            catch (\Throwable $e){
                $errorMessage = __( 'API connection is not established', 'coinsnap-for-ninja-forms' );
                $data['errors']['form']['coinsnap'] = $errorMessage;
                return $data;
            }
        }
        
        else {
            if($checkInvoice['error'] === 'currencyError'){
                $errorMessage = sprintf( 
                /* translators: 1: Currency */
                __( 'Currency %1$s is not supported by Coinsnap', 'coinsnap-for-ninja-forms' ), strtoupper( $currency ));
            }      
            elseif($checkInvoice['error'] === 'amountError'){
                $errorMessage = sprintf( 
                /* translators: 1: Amount, 2: Currency */
                __( 'Invoice amount cannot be less than %1$s %2$s', 'coinsnap-for-ninja-forms' ), $checkInvoice['min_value'], strtoupper( $currency ));
            }
            else {
                $errorMessage = $checkInvoice['error'];
            }
            $data['errors']['form']['coinsnap'] = $errorMessage;
        }
                
        return $data;
    }

    
    /**
     * When the payment total is a Total field, the total recomputed from the saved form: product
     * prices and shipping costs from the database, quantities from the visitor. Returns null if
     * the payment total is something else (a fixed amount, a calculation or an amount the visitor
     * chooses), and false if it can't be recomputed safely.
     */
    private function serverTotal( int $form_id, $action_id, array $data ) {
        $action = $action_id ? Ninja_Forms()->form( $form_id )->get_action( $action_id ) : null;
        $raw = $action ? trim( (string) $action->get_setting( 'payment_total' ) ) : '';
        if ( ! preg_match( '/^\{field:([^}]+)\}$/', $raw, $m ) ) {
            return null;
        }
        $fields = Ninja_Forms()->form( $form_id )->get_fields();
        $isTotal = false;
        foreach ( $fields as $field ) {
            if ( $field->get_setting( 'key' ) === $m[1] && $field->get_setting( 'type' ) === 'total' ) {
                $isTotal = true;
            }
        }
        if ( ! $isTotal ) {
            return null;
        }

        $submitted = function ( $id ) use ( $data ) {
            $value = $data['fields'][ $id ]['value'] ?? '';
            return is_scalar( $value ) ? trim( (string) $value ) : '';
        };
        $quantities = array();
        foreach ( $fields as $field ) {
            if ( $field->get_setting( 'type' ) === 'quantity' && $field->get_setting( 'product_assignment' ) ) {
                $quantities[ (string) $field->get_setting( 'product_assignment' ) ] = $submitted( $field->get_id() );
            }
        }

        $sum = 0;
        foreach ( $fields as $field ) {
            $type = $field->get_setting( 'type' );
            if ( $type === 'product' ) {
                $productType = (string) $field->get_setting( 'product_type' );
                if ( ! in_array( $productType, array( '', 'single', 'hidden' ), true ) ) {
                    return false;
                }
                $price = CoinsnapNF_Security::to_units( str_replace( array( ',', '$' ), '', (string) $field->get_setting( 'product_price' ) ) );
                if ( $price === null ) {
                    return false;
                }
                // The same quantity rules as Ninja Forms' own product field.
                $qty = $quantities[ (string) $field->get_id() ] ?? '';
                if ( $qty === '' || $qty === '0' ) {
                    $qty = $field->get_setting( 'product_use_quantity' ) ? $submitted( $field->get_id() ) : '';
                }
                if ( $qty !== '' && ! preg_match( '/^\d{1,6}$/', $qty ) ) {
                    return false;
                }
                $sum += $price * ( ( $qty === '' || (int) $qty === 0 ) ? 1 : (int) $qty );
            }
            elseif ( $type === 'shipping' ) {
                if ( ! in_array( (string) $field->get_setting( 'shipping_type' ), array( '', 'single' ), true ) ) {
                    return false;
                }
                $cost = CoinsnapNF_Security::to_units( str_replace( array( ',', '$' ), '', (string) $field->get_setting( 'shipping_cost' ) ) );
                $sum += (int) $cost;
            }
        }
        return number_format( $sum / 100000000, 2, '.', '' );
    }

    private function is_success( $response ){
        if( ! is_array( $response ) ){ return FALSE; }
        if( ! in_array( $response[ 'ACK' ], array( 'Success', 'SuccessWithWarning' ) ) ){ return FALSE; }
        return TRUE;
    }

    
    private function update_submission( $sub_id, $data = array()){
        if( ! $sub_id ) return;

        $sub = Ninja_Forms()->form()->sub( $sub_id )->get();

        foreach( $data as $key => $value ){
            $sub->update_extra_value( $key, $value );
        }

        $sub->save();
    }

   
    private function get_sub_id( $data )
    {
        if( isset( $data[ 'actions' ][ 'save' ][ 'sub_id' ] ) ){
            return $data[ 'actions' ][ 'save' ][ 'sub_id' ];
        }
        return FALSE;
    }

    private function get_status( $status )
    {
        $lookup = array(
            'pending' => __( 'Pending', 'coinsnap-for-ninja-forms' ),
            'cancel'  => __( 'Cancelled', 'coinsnap-for-ninja-forms' ),
            'success' => __( 'Completed', 'coinsnap-for-ninja-forms' ),
        );

        return ( isset( $lookup[ $status ] ) ) ? $lookup[ $status ] : $lookup[ 'pending' ];
    }

    private function get_currency( $form_data )
    {
        
        $coinsnap_currency = Ninja_Forms()->get_setting( 'coinsnap_currency', 'USD' );
        $plugin_currency = Ninja_Forms()->get_setting( 'currency', $coinsnap_currency );
        $form_currency   = ( isset( $form_data[ 'settings' ][ 'currency' ] ) && $form_data[ 'settings' ][ 'currency' ] !== '' ) ? $form_data[ 'settings' ][ 'currency' ] : $plugin_currency;
        return $form_currency;
    }

    public function get_nf_data($data, $key) {		
        foreach ($data['fields_by_key'] as $row){
            if ($row['settings']['type'] == $key){ return $row['value']; }
        }
        return '';
    }
    
    public function get_payment_provider() {
        return (Ninja_Forms()->get_setting( 'coinsnap_provider') === 'btcpay')? 'btcpay' : 'coinsnap';
    }

    public function get_webhook_url($form_id) {		
        return get_site_url() . '/?nf-listener=coinsnap&form_id='.$form_id;
    }
    
    public function getStoreId(){
        return ($this->get_payment_provider() === 'btcpay')? Ninja_Forms()->get_setting( 'btcpay_store_id' ) : Ninja_Forms()->get_setting( 'coinsnap_store_id' );
    }
    
    public function getApiKey(){
        return ($this->get_payment_provider() === 'btcpay')? Ninja_Forms()->get_setting( 'btcpay_api_key' ) : Ninja_Forms()->get_setting( 'coinsnap_api_key' );
    }
    
    public function getAutoRedirect(){
        return Ninja_Forms()->get_setting( 'coinsnap_autoredirect' );
    }
    
    public function getApiUrl() {
        return ($this->get_payment_provider() === 'btcpay')? Ninja_Forms()->get_setting( 'btcpay_server_url' ) : COINSNAP_SERVER_URL;
    }	

    private function webhookOption(int $form_id): string {
        return 'ninja_forms_settings_coinsnap_webhook_'.$form_id;
    }

    // A stored webhook belongs to the provider and store it was created on (6.4). Entries from
    // before 1.3.8 have no provider and are taken to be for the current one.
    private function webhookMatchesConfig($stored): bool {
        if (!is_array($stored) || !is_string($stored['secret'] ?? null) || $stored['secret'] === '') {
            return false;
        }
        if (!isset($stored['provider'])) {
            return true;
        }
        return $stored['provider'] === $this->get_payment_provider() && (string) ($stored['store_id'] ?? '') === (string) $this->getStoreId();
    }

    private function webhookSecret(int $form_id): string {
        $stored = get_option($this->webhookOption($form_id));
        return $this->webhookMatchesConfig($stored) ? (string) $stored['secret'] : '';
    }

    /**
     * Make sure this form's webhook exists on the configured store. Callers check capability and
     * nonce first. Returns 'exists', 'created' or 'error'. An API error is never "no webhook":
     * it changes nothing, so a network glitch can't replace the secret.
     */
    public function ensureWebhook(int $form_id): string {
        $apiUrl = (string) $this->getApiUrl();
        $apiKey = (string) $this->getApiKey();
        $storeId = (string) $this->getStoreId();
        if ($apiUrl === '' || $apiKey === '' || $storeId === '') {
            return 'error';
        }
        $url = $this->get_webhook_url($form_id);
        $whClient = new Webhook( $apiUrl, $apiKey );
        $stored = get_option($this->webhookOption($form_id));

        if ($this->webhookMatchesConfig($stored) && !empty($stored['id'])) {
            try {
                $existing = $whClient->getWebhook( $storeId, (string) $stored['id'] )->getData();
                if (($existing['id'] ?? '') === $stored['id'] && ($existing['url'] ?? '') === $url) {
                    if (!isset($stored['provider'])) {
                        $stored['provider'] = $this->get_payment_provider();
                        $stored['store_id'] = $storeId;
                        update_option($this->webhookOption($form_id), $stored, false);
                    }
                    return 'exists';
                }
            }
            catch (\Coinsnap\Exception\RequestException $e) {
                if (!in_array((int) $e->getCode(), [400, 404], true)) {
                    return 'error';
                }
            }
            catch (\Throwable $e) {
                return 'error';
            }
        }

        try {
            // Remove old webhooks for this form's URL, then create one with a new secret.
            foreach ($whClient->getWebhooks( $storeId ) as $webhook) {
                if (($webhook->getData()['url'] ?? '') === $url) {
                    $whClient->deleteWebhook( $storeId, (string) $webhook->getData()['id'] );
                }
            }
            $secret = bin2hex(random_bytes(32));
            $webhook_events = ($this->get_payment_provider() === 'btcpay')? self::BTCPAY_WEBHOOK_EVENTS : self::COINSNAP_WEBHOOK_EVENTS;
            $webhook = $whClient->createWebhook( $storeId, $url, $webhook_events, $secret )->getData();
        }
        catch (\Throwable $e) {
            return 'error';
        }
        if (!is_string($webhook['id'] ?? null) || $webhook['id'] === '') {
            return 'error';
        }

        // Not autoloaded; never sent to the browser.
        update_option($this->webhookOption($form_id), [
            'id' => $webhook['id'],
            'secret' => $secret,
            'url' => $url,
            'provider' => $this->get_payment_provider(),
            'store_id' => $storeId,
        ], false);
        return 'created';
    }

} // END CLASS CoinsnapNF_PaymentGateway
