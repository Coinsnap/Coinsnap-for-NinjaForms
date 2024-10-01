<?php 

if ( ! defined( 'ABSPATH' ) ){
    exit;
}

if( ! class_exists( 'NF_Abstracts_PaymentGateway' ) ){
    return;
}

/**
 * The Coinsnap payment gateway for the Collect Payment action.
 */
class NF_Coinsnap_PaymentGateway extends NF_Abstracts_PaymentGateway
{
    protected $_slug = 'coinsnap';
    public const WEBHOOK_EVENTS = ['New','Expired','Settled','Processing'];	 

    public function __construct()
    {
        parent::__construct();

        $this->_name = esc_html__( 'Coinsnap', 'ninjaforms-coinsnap' );
        add_action( 'ninja_forms_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        $this->_settings[ 'coinsnap_details' ] = array(
            'name' => 'coinsnap_details',
            'type' => 'textarea',
            'placeholder' => '',
            'value' => '',
            'label' => __( 'Details', 'ninja-forms' ),
            'width' => 'full',
            'group' => 'advanced',
            'deps'  => array(
                'payment_gateways' => $this->_slug
            ),
            'help' => __( 'Extra information associated with the payment, such as shipping address, email, etc. This will be saved as Transaction Data in your Coinsnap Account.', 'coinsnap-for-ninjaforms' ),
            'use_merge_tags' => TRUE
        );
        
        $this->_settings[ 'coinsnap_description' ] = array(
            'name' => 'coinsnap_description',
            'type' => 'textbox',
            'label' => __( 'Note to Buyer', 'ninja-forms' ),
            'width' => 'full',
            'group' => 'advanced',
            'deps' => array(
                'payment_gateways' => $this->_slug
            ),
            /* translators: 1: A note from the merchant to the buyer that will be displayed in the Coinsnap checkout window. */
            'help' => sprintf( esc_html__( 'A note from the merchant to the buyer that will be displayed in the Coinsnap checkout window. Limit %1$s characters', 'coinsnap-for-ninjaforms' ), '165' ),
            'use_merge_tags' => TRUE
        );

        


    }

    public function webhook()
    {
        $notify_json = file_get_contents('php://input');     
        //$notify_json = '{"type":"New","invoiceId":"AWcvgqG1pnCfcrD1aW2qUy"}';
        $notify_ar = json_decode($notify_json, true);
        $form_id = filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT);
        $invoice_id = $notify_ar['invoiceId'];

        try {
            $client = new \Coinsnap\Client\Invoice( $this->getApiUrl(), $this->getApiKey() );			
            $csinvoice = $client->getInvoice($this->getStoreId(), $invoice_id);
            $status = $csinvoice->getData()['status'] ;
            $order_id = $csinvoice->getData()['orderId'] ;				
        
        }catch (\Throwable $e) {													
                echo "Error";
                exit;
        }
                
        $this->update_submission( $order_id, array('coinsnap_status' => $status, 'coinsnap_transaction_id' => $invoice_id ) );       
        echo "OK";
        exit;

    }

    
    public function process( $action_settings, $form_id, $data )
    {
        
        
        if( $action_settings[ 'payment_total' ] <= 0 || NULL == $action_settings[ 'payment_total' ] ) return $data;        
        $payment_total = number_format( $action_settings[ 'payment_total' ], 2, '.', ',' );
        $return_url = add_query_arg( array( 'nf_resume' => $form_id, 'coinsnap_act' => 'success' ), wp_get_referer() );
        

        
        if( isset( $data[ 'resume' ] ) ){
            $data[ 'actions' ][ 'success_message' ] .= '<style> .nf-ppe-spinner { display: none !important; } </style>';
            return $data;
        }

        $webhook_url = $this->get_webhook_url($form_id);        
                        
				
        if (! $this->webhookExists($this->getStoreId(), $this->getApiKey(), $webhook_url)){
            if (! $this->registerWebhook($this->getStoreId(), $this->getApiKey(), $webhook_url)) {                
                echo (esc_html__('Unable to set Webhook url.', 'coinsnap-for-ninjaforms'));
                exit;
            }
         }      

        $currency = $this->get_currency( $data );
        $store_id = Ninja_Forms()->get_setting( 'coinsnap_store_id' );
        $api_key = Ninja_Forms()->get_setting( 'coinsnap_api_key' );     
        $invoice_no = $this->get_sub_id( $data );
        $first_name = $this->get_nf_data($data, 'firstname');
        $last_name = $this->get_nf_data($data, 'lastname');
        $buyerEmail = $this->get_nf_data($data, 'email');
        $buyerName = $first_name.' '.$last_name;
        $metadata = [];
        $metadata['orderNumber'] = $invoice_no;
        $metadata['customerName'] = $buyerName;
				

        $checkoutOptions = new \Coinsnap\Client\InvoiceCheckoutOptions();
        $checkoutOptions->setRedirectURL( $return_url );
        $client =new \Coinsnap\Client\Invoice($this->getApiUrl(), $this->getApiKey());
        $camount = \Coinsnap\Util\PreciseNumber::parseFloat($payment_total,2);
								
        $csinvoice = $client->createInvoice(
				    $this->getStoreId(),  
			    	strtoupper( $currency ),
			    	$camount,
			    	$invoice_no,
			    	$buyerEmail,
			    	$buyerName, 
			    	$return_url,
			    	NF_Coinsnap::COINSNAP_REFERRAL_CODE,     
			    	$metadata,
			    	$checkoutOptions
		    	);
				
		
        $payurl = $csinvoice->getData()['checkoutLink'] ;   
        if (isset($payurl)){
            $data[ 'halt' ] = TRUE;
            $data[ 'actions' ][ 'redirect' ] = $payurl;

            $this->update_submission( $this->get_sub_id( $data ), array(
                'coinsnap_status' => esc_html__( 'Pending', 'coinsnap-for-ninjaforms' ),
                'coinsnap_total' => $payment_total
            ) );    
        }

      

        return $data;
    }

    public function enqueue_scripts( $data )
    {        
        wp_enqueue_script('nf-coinsnap-debug', NF_Coinsnap::$url . 'assets/js/debug.js', array( 'nf-front-end' ), '6.6.2' );
        wp_enqueue_script('nf-coinsnap-response', NF_Coinsnap::$url . 'assets/js/error-handler.js', array( 'nf-front-end' ), '6.6.2' );
    }

    private function is_success( $response )
    {
        if( ! is_array( $response ) ){ return FALSE; }
        if( ! in_array( $response[ 'ACK' ], array( 'Success', 'SuccessWithWarning' ) ) ){ return FALSE; }
        return TRUE;
    }

    
    private function update_submission( $sub_id, $data = array() )
    {
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
            'pending' => __( 'Pending', 'ninjaforms-coinsnap' ),
            'cancel'  => __( 'Cancelled', 'ninjaforms-coinsnap' ),
            'success' => __( 'Completed', 'ninjaforms-coinsnap' ),
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
          if ($row['settings']['type'] == $key) return $row['value'];
        }
        return '';
    }

    public function get_webhook_url($form_id) {		
        return get_site_url() . '/?nf-listener=coinsnap&form_id='.$form_id;
    }
	public function getStoreId() {
        
        return Ninja_Forms()->get_setting( 'coinsnap_store_id' );
    }
    public function getApiKey() {
        return Ninja_Forms()->get_setting( 'coinsnap_api_key' );
    }
    
    public function getApiUrl() {
        return 'https://app.coinsnap.io';
    }	

    public function webhookExists(string $storeId, string $apiKey, string $webhook): bool {	
        try {		
            $whClient = new \Coinsnap\Client\Webhook( $this->getApiUrl(), $apiKey );		
            $Webhooks = $whClient->getWebhooks( $storeId );
            
            foreach ($Webhooks as $Webhook){					
                //self::deleteWebhook($storeId,$apiKey, $Webhook->getData()['id']);
                if ($Webhook->getData()['url'] == $webhook) return true;
            }
        }catch (\Throwable $e) {			
            return false;
        }
    
        return false;
    }
    public  function registerWebhook(string $storeId, string $apiKey, string $webhook): bool {	
        try {			
            $whClient = new \Coinsnap\Client\Webhook($this->getApiUrl(), $apiKey);
            
            $webhook = $whClient->createWebhook(
                $storeId,   //$storeId
                $webhook, //$url
                self::WEBHOOK_EVENTS,   
                null    //$secret
            );		
            
            return true;
        } catch (\Throwable $e) {
            return false;	
        }

        return false;
    }

    public function deleteWebhook(string $storeId, string $apiKey, string $webhookid): bool {	    
        
        try {			
            $whClient = new \Coinsnap\Client\Webhook($this->getApiUrl(), $apiKey);
            
            $webhook = $whClient->deleteWebhook(
                $storeId,   //$storeId
                $webhookid, //$url			
            );					
            return true;
        } catch (\Throwable $e) {
            
            return false;	
        }
    }    

} // END CLASS NF_Coinsnap_PaymentGateway
