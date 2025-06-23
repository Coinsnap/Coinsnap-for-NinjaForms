<?php if ( ! defined( 'ABSPATH' ) ){ exit;}

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
    public const WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];	 

    public function __construct(){
        parent::__construct();

        $this->_name = 'Coinsnap';
        add_action( 'ninja_forms_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts'] );
            add_action('wp_ajax_coinsnap_connection_handler', [$this, 'coinsnapConnectionHandler'] );
            add_action('wp_ajax_btcpay_server_apiurl_handler', [$this, 'btcpayApiUrlHandler']);
            
        }
        
        // Adding template redirect handling for coinsnap-for-ninja-forms-btcpay-settings-callback.
        add_action( 'template_redirect', function(){
            global $wp_query;
            $notice = new \Coinsnap\Util\Notice();

            // Only continue on a coinsnap-for-ninja-forms-btcpay-settings-callback request.
            if (!isset( $wp_query->query_vars['coinsnap-for-ninja-forms-btcpay-settings-callback'])) {
                return;
            }

            $CoinsnapBTCPaySettingsUrl = admin_url('admin.php?page=nf-settings');

            $rawData = file_get_contents('php://input');

            $btcpay_server_url = Ninja_Forms()->get_setting( 'btcpay_server_url' );
            $btcpay_api_key  = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $client = new \Coinsnap\Client\Store($btcpay_server_url,$btcpay_api_key);
            if (count($client->getStores()) < 1) {
                $messageAbort = __('Error on verifiying redirected API Key with stored BTCPay Server url. Aborting API wizard. Please try again or continue with manual setup.', 'coinsnap-for-ninja-forms');
                $notice->addNotice('error', $messageAbort);
                wp_redirect($CoinsnapBTCPaySettingsUrl);
            }

            // Data does get submitted with url-encoded payload, so parse $_POST here.
            if (!empty($_POST) || wp_verify_nonce(filter_input(INPUT_POST,'wp_nonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS),'-1')) {
                $data['apiKey'] = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;
                $permissions = (isset($_POST['permissions']) && is_array($_POST['permissions']))? $_POST['permissions'] : null;
                if (isset($permissions)) {
                    foreach ($permissions as $key => $value) {
                        $data['permissions'][$key] = sanitize_text_field($permissions[$key] ?? null);
                    }
                }
            }

            if (isset($data['apiKey']) && isset($data['permissions'])) {

                $apiData = new \Coinsnap\Client\BTCPayApiAuthorization($data);
                if ($apiData->hasSingleStore() && $apiData->hasRequiredPermissions()) {

                    Ninja_Forms()->update_setting('btcpay_api_key', $apiData->getApiKey());
                    Ninja_Forms()->update_setting('btcpay_store_id', $apiData->getStoreID());
                    Ninja_Forms()->update_setting('coinsnap_provider', 'btcpay');

                    $notice->addNotice('success', __('Successfully received api key and store id from BTCPay Server API. Please finish setup by saving this settings form.', 'coinsnap-for-ninja-forms'));
                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
                else {
                    $notice->addNotice('error', __('Please make sure you only select one store on the BTCPay API authorization page.', 'coinsnap-for-ninja-forms'));
                    wp_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
            }

            $notice->addNotice('error', __('Error processing the data from Coinsnap. Please try again.', 'coinsnap-for-ninja-forms'));
            wp_redirect($CoinsnapBTCPaySettingsUrl);
        });
    }
    
    public function enqueueAdminScripts(): void {
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
        
        $_nonce = filter_input(INPUT_POST,'_wpnonce',FILTER_SANITIZE_STRING);
        
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
        
        if( wp_verify_nonce($_nonce,'coinsnap-ajax-nonce') ){
            
            $response = ['result' => true,'message' => $_message_connected.' ('.$connectionData.')'];

            try {
                $this_store = $store->getStore($this->getStoreId());
                
                if ($this_store['code'] !== 200) {
                    $response = ['result' => false,'message' => $_message_disconnected];
                    $this->sendJsonResponse($response);
                }
            }
            catch (\Exception $e) {
                $response = ['result' => false,'message' => __('Ninja Forms: API connection is not established', 'coinsnap-for-ninja-forms')];
            }

            $this->sendJsonResponse($response);
        }      
    }
    
    private function sendJsonResponse(array $response): void {
        echo wp_json_encode($response);
        exit();
    }
    
    public function enqueue_scripts( $data )
    {        
        wp_enqueue_script('coinsnapnf-debug', CoinsnapNF::$url . 'assets/js/debug.js', array( 'nf-front-end' ), COINSNAPNF_VERSION, true );
        wp_enqueue_script('coinsnapnf-response', CoinsnapNF::$url . 'assets/js/error-handler.js', array( 'nf-front-end' ), COINSNAPNF_VERSION, true );
    }
    
    
        /**
     * Handles the BTCPay server AJAX callback from the settings form.
     */
    public function btcpayApiUrlHandler() {
        $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_STRING);
        if ( !wp_verify_nonce( $_nonce, 'coinsnap-ajax-nonce' ) ) {
            wp_die('Unauthorized!', '', ['response' => 401]);
        }
        
        if ( current_user_can( 'manage_options' ) ) {
            $host = filter_var(filter_input(INPUT_POST,'host',FILTER_SANITIZE_STRING), FILTER_VALIDATE_URL);

            if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
                wp_send_json_error("Error validating BTCPayServer URL.");
            }

            $permissions = array_merge([
		'btcpay.store.canviewinvoices',
		'btcpay.store.cancreateinvoice',
		'btcpay.store.canviewstoresettings',
		'btcpay.store.canmodifyinvoices'
            ],
            [
		'btcpay.store.cancreatenonapprovedpullpayments',
		'btcpay.store.webhooks.canmodifywebhooks',
            ]);

            try {
		// Create the redirect url to BTCPay instance.
		$url = \Coinsnap\Client\BTCPayApiKey::getAuthorizeUrl(
                    $host,
                    $permissions,
                    'NinjaForms',
                    true,
                    true,
                    home_url('?coinsnap-for-ninja-forms-btcpay-settings-callback'),
                    null
		);

		// Store the host to options before we leave the site.
		Ninja_Forms()->update_setting( 'btcpay_server_url' , $host);

		// Return the redirect url.
		wp_send_json_success(['url' => $url]);
            }
            
            catch (\Throwable $e) {
                
            }
	}
        wp_send_json_error("Error processing Ajax request.");
    }    

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
        
        try {
            // First check if we have any input
            $rawPostData = file_get_contents("php://input");
            if (!$rawPostData) {
                wp_die('No raw post data received', '', ['response' => 400]);
            }

            // Get headers and check for signature
            $headers = getallheaders();
            $signature = null; $payloadKey = null;
            $_provider = ($this->get_payment_provider() === 'btcpay')? 'btcpay' : 'coinsnap';
                
            foreach ($headers as $key => $value) {
                if ((strtolower($key) === 'x-coinsnap-sig' && $_provider === 'coinsnap') || (strtolower($key) === 'btcpay-sig' && $_provider === 'btcpay')) {
                        $signature = $value;
                        $payloadKey = strtolower($key);
                }
            }

            // Handle missing or invalid signature
            if (!isset($signature)) {
                wp_die('Authentication required', '', ['response' => 401]);
            }

            // Validate the signature
            $webhook = get_option( 'ninja_forms_settings_coinsnap_webhook_'.$form_id);
            if (!Webhook::isIncomingWebhookRequestValid($rawPostData, $signature, $webhook['secret'])) {
                wp_die('Invalid authentication signature', '', ['response' => 401]);
            }

            // Parse the JSON payload
            $postData = json_decode($rawPostData, false, 512, JSON_THROW_ON_ERROR);

            if (!isset($postData->invoiceId)) {
                wp_die('No Coinsnap invoiceId provided', '', ['response' => 400]);
            }
            
            $invoice_id = $postData->invoiceId;
            
            if(strpos($invoice_id,'test_') !== false){
                wp_die('Successful webhook test', '', ['response' => 200]);
            }
            
            $client = new \Coinsnap\Client\Invoice( $this->getApiUrl(), $this->getApiKey() );			
            $csinvoice = $client->getInvoice($this->getStoreId(), $invoice_id);
            $status = $csinvoice->getData()['status'] ;
            $order_id = $csinvoice->getData()['orderId'] ;
            
            $this->update_submission( $order_id, array('coinsnap_status' => $status, 'coinsnap_transaction_id' => $invoice_id ) );       
            echo "OK";
            exit;
        
        }
        catch (JsonException $e) {
            wp_die('Invalid JSON payload', '', ['response' => 400]);
        }
        catch (\Throwable $e) {
            wp_die('Internal server error', '', ['response' => 500]);
        }        
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
        
        $client =new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        $currency = $this->get_currency( $data );
        $amount = (float)$action_settings[ 'payment_total' ];
        
        $checkInvoice = $this->coinsnapnf_amount_validation($amount,strtoupper( $currency ));
        
        if($checkInvoice['result'] === true){
        
            $payment_total = number_format( $action_settings[ 'payment_total' ], 2, '.', ',' );        
            $return_url = add_query_arg( array( 'nf_resume' => $form_id, 'coinsnap_act' => 'success' ), wp_get_referer() );
                
            if(isset( $data[ 'resume' ] )){
                $data[ 'actions' ][ 'success_message' ] .= '<style> .nf-ppe-spinner { display: none !important; } </style>';
                return $data;
            }

            $webhook_url = $this->get_webhook_url($form_id);
            
            $invoice_no = $this->get_sub_id( $data );
            $first_name = $this->get_nf_data($data, 'firstname');
            $last_name = $this->get_nf_data($data, 'lastname');
            $buyerEmail = $this->get_nf_data($data, 'email');
            $buyerName = $first_name.' '.$last_name;
            $metadata = [];
            $metadata['orderNumber'] = $invoice_no;
            $metadata['customerName'] = $buyerName;

            $camount = \Coinsnap\Util\PreciseNumber::parseFloat($payment_total,2);
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
                if (isset($payurl)){
                    $data[ 'halt' ] = TRUE;
                    $data[ 'actions' ][ 'redirect' ] = $payurl;

                    $this->update_submission( $this->get_sub_id( $data ), array(
                        'coinsnap_status' => esc_html__( 'Pending', 'coinsnap-for-ninja-forms' ),
                        'coinsnap_total' => $payment_total
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

    public function webhookExists(string $apiUrl, string $apiKey, string $storeId): bool {
        
        $form_id = filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT);
        if($form_id > 0){
        
            $whClient = new Webhook( $apiUrl, $apiKey );
            if ($storedWebhook = get_option( 'ninja_forms_settings_coinsnap_webhook_'.$form_id)) {

                try {
                    $existingWebhook = $whClient->getWebhook( $storeId, $storedWebhook['id'] );

                    if($existingWebhook->getData()['id'] === $storedWebhook['id'] && strpos( $existingWebhook->getData()['url'], $storedWebhook['url'] ) !== false){
                        return true;
                    }
                }
                catch (\Throwable $e) {
                    $errorMessage = __( 'Error fetching existing Webhook. Message: ', 'coinsnap-for-ninja-forms' ).$e->getMessage();
                    $data['errors']['form']['coinsnap'] = esc_html($errorMessage);
                }
            }
            try {
                $storeWebhooks = $whClient->getWebhooks( $storeId );
                foreach($storeWebhooks as $webhook){
                    if(strpos( $webhook->getData()['url'], $this->get_webhook_url($form_id) ) !== false){
                        $whClient->deleteWebhook( $storeId, $webhook->getData()['id'] );
                    }
                }
            }
            catch (\Throwable $e) {
                $errorMessage = sprintf( 
                    /* translators: 1: StoreId */
                    __( 'Error fetching webhooks for store ID %1$s Message: ', 'coinsnap-for-ninja-forms' ), $storeId).$e->getMessage();
                $data['errors']['form']['coinsnap'] = esc_html($errorMessage);
            }
        }
	return false;
    }
    
    public function registerWebhook(string $apiUrl, $apiKey, $storeId){
        
        $form_id = filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT);
        if($form_id > 0){
        
            try {
                $whClient = new Webhook( $apiUrl, $apiKey );
                $webhook = $whClient->createWebhook(
                    $storeId,   //$storeId
                    $this->get_webhook_url($form_id), //$url
                    self::WEBHOOK_EVENTS,   //$specificEvents
                    null    //$secret
                );

                update_option(
                    'ninja_forms_settings_coinsnap_webhook_'.$form_id,
                    [
                        'id' => $webhook->getData()['id'],
                        'secret' => $webhook->getData()['secret'],
                        'url' => $webhook->getData()['url']
                    ]
                );

                return $webhook;

            }
            catch (\Throwable $e) {
                $errorMessage = __('Error creating a new webhook on Coinsnap instance: ', 'coinsnap-for-ninja-forms' ) . $e->getMessage();
                $data['errors']['form']['coinsnap'] = esc_html($errorMessage);
            }
        }
	return null;
    }

    public function updateWebhook(string $webhookId,string $webhookUrl,string $secret,bool $enabled,bool $automaticRedelivery,?array $events): ?WebhookResult {
        try {
            $whClient = new Webhook($this->getApiUrl(), $this->getApiKey() );
            $webhook = $whClient->updateWebhook(
                $this->getStoreId(),
                $webhookUrl,
		$webhookId,
		$events ?? self::WEBHOOK_EVENTS,
		$enabled,
		$automaticRedelivery,
		$secret
            );
            return $webhook;
        }
        catch (\Throwable $e) {
            $errorMessage = __('Error updating existing Webhook from Coinsnap: ', 'coinsnap-for-ninja-forms' ) . $e->getMessage();
            $data['errors']['form']['coinsnap'] = esc_html($errorMessage);
	}
    }    

} // END CLASS CoinsnapNF_PaymentGateway
