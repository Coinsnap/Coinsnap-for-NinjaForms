<?php
/**
 * Plugin Name:     Bitcoin payment for Ninja Forms
 * Plugin URI:      https://coinsnap.io/coinsnap-for-ninja-forms-plugin/
 * Description:     Accept Bitcoin-Lightning payments with Ninja Forms
 * Author:          Coinsnap
 * Author URI:      https://coinsnap.io
 * Text Domain:     coinsnap-for-ninja-forms
 * Domain Path:     /languages
 * Version:         1.2.2
 * Requires PHP:    7.4
 * Tested up to:    6.8
 * Requires at least: 6.0
 * Requires Plugins: ninja-forms
 * NF tested up to: 3.10.4
 * License:         GPL2
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Network:         true
 */

if(!defined( 'ABSPATH' ) ){ exit;}
if(!defined('COINSNAPNF_REFERRAL_CODE')){ define( 'COINSNAPNF_REFERRAL_CODE', 'D17725' ); }
if(!defined('COINSNAPNF_VERSION')){define( 'COINSNAPNF_VERSION', '1.2.2' );}
if(!defined('COINSNAPNF_PLUGIN_ID')){define( 'COINSNAPNF_PLUGIN_ID', 'coinsnap-for-ninja-forms' );}
if(!defined('COINSNAP_SERVER_URL')){define( 'COINSNAP_SERVER_URL', 'https://app.coinsnap.io' );}
if(!defined('COINSNAP_API_PATH')){define( 'COINSNAP_API_PATH', '/api/v1/');}
if(!defined('COINSNAP_SERVER_PATH')){define( 'COINSNAP_SERVER_PATH', 'stores' );}
if(!defined('COINSNAP_CURRENCIES')){ define( 'COINSNAP_CURRENCIES', array("EUR","USD","SATS","BTC","CAD","JPY","GBP","CHF","RUB") ); }

add_filter('ninja_forms_upgrade_settings','CoinsnapNF_Settings', 9999 );
add_action('admin_init','coinsnapnf_dependency_check' );

/**
* Class CoinsnapNF
*/
final class CoinsnapNF {
        
    const SLUG    = 'coinsnap';
    const NAME    = 'Coinsnap';
    const AUTHOR  = 'Coinsnap';
    const PREFIX  = 'CoinsnapNF';
        
    private static $instance;        
    public static $dir = '';        
    public static $url = '';
        
    public static function instance(){
        if (!isset(self::$instance) && !(self::$instance instanceof CoinsnapNF)) {
            self::$instance = new CoinsnapNF();
            self::$dir = plugin_dir_path(__FILE__);
            self::$url = plugin_dir_url(__FILE__);
            spl_autoload_register(array(self::$instance, 'autoloader'));
        }
        return self::$instance;
    }

    public function __construct(){        
            add_action('admin_init', array( $this, 'setup_admin' ) );
            add_filter('ninja_forms_register_payment_gateways', array( $this, 'register_payment_gateways' ) );            
	    add_filter('ninja_forms_register_actions', array( $this, 'register_actions' ) );            
            add_filter('nf_subs_csv_extra_values', array( $this, 'export_transaction_data' ), 10, 3 );
            add_filter('ninja_forms_new_form_templates', array( $this, 'register_templates' ) );
            add_filter('nf_react_table_extra_value_keys', array($this, 'addMetabox'));
            add_action('init', array($this, 'process_webhook'));
            add_action('admin_notices', array($this, 'coinsnapnf_notice'));
    }
    
    public function coinsnapnf_notice(){
            
        $notice = new \Coinsnap\Util\Notice();
        $notice->showNotices();
        
        $page = (filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) !== null)? filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
        
        if($page === 'ninja-forms' && !isset($_COOKIE['coinsnap_notices'])){
            
            $CoinsnapPG = new CoinsnapNF_PaymentGateway();
                
            $coinsnap_url = ($CoinsnapPG->getApiUrl() !== null)? $CoinsnapPG->getApiUrl() : "";
            $coinsnap_api_key = ($CoinsnapPG->getApiKey() !== null)? $CoinsnapPG->getApiKey() : "";
            $coinsnap_store_id = ($CoinsnapPG->getStoreId() !== null)? $CoinsnapPG->getStoreId() : "";
                
            echo '<div class="coinsnap-notices">';
            
                if(empty($coinsnap_url)){
                    echo '<div class="notice notice-error is-dismissible"><p>';
                    esc_html_e('Ninja Forms: API URL is not set', 'coinsnap-for-ninja-forms');
                    echo '</p></div>';
                }

                if(empty($coinsnap_store_id)){
                    echo '<div class="notice notice-error is-dismissible"><p>';
                    esc_html_e('Ninja Forms: Store ID is not set', 'coinsnap-for-ninja-forms');
                    echo '</p></div>';
                }

                if(empty($coinsnap_api_key)){
                    echo '<div class="notice notice-error is-dismissible"><p>';
                    esc_html_e('Ninja Forms: API Key is not set', 'coinsnap-for-ninja-forms');
                    echo '</p></div>';
                }
                
                if(!empty($coinsnap_api_key) && !empty($coinsnap_store_id)){
                    $client = new \Coinsnap\Client\Store($coinsnap_url, $coinsnap_api_key);
                    try {
                    
                        $store = $client->getStore($coinsnap_store_id);
                        if ($store['code'] === 200) {
                            echo '<div class="notice notice-success is-dismissible"><p>';
                            esc_html_e('Ninja Forms: Established connection to Coinsnap Server', 'coinsnap-for-ninja-forms');
                            echo '</p></div>';

                            $form_id = filter_input(INPUT_GET,'form_id',FILTER_VALIDATE_INT);

                            if($form_id > 0){

                                if ( ! $CoinsnapPG->webhookExists( $coinsnap_url, $coinsnap_api_key, $coinsnap_store_id ) ) {
                                    if ( ! $CoinsnapPG->registerWebhook( $coinsnap_url, $coinsnap_api_key, $coinsnap_store_id ) ) {
                                        echo '<div class="notice notice-error is-dismissible"><p>';
                                        esc_html_e('Unable to create webhook on Coinsnap Server', 'coinsnap-for-ninja-forms');
                                        echo '</p></div>';
                                    }
                                    else {
                                        echo '<div class="notice notice-success is-dismissible"><p>';
                                        esc_html_e('Successfully registered webhook on Coinsnap Server', 'coinsnap-for-ninja-forms');
                                        echo '</p></div>';
                                    }
                                }
                                else {
                                    echo '<div class="notice notice-info is-dismissible"><p>';
                                    esc_html_e('Webhook already exists, skipping webhook creation', 'coinsnap-for-ninja-forms');
                                    echo '</p></div>';
                                }

                            }
                        }
                        else {
                            echo '<div class="notice notice-error is-dismissible"><p>';
                            esc_html_e('Coinsnap connection error:', 'coinsnap-for-ninja-forms');
                            echo esc_html($store['result']['message']);
                            echo '</p></div>';
                        }
                    }
                    catch (\Throwable $e){
                        echo '<div class="notice notice-error is-dismissible"><p>';
                        esc_html_e('Coinsnap connection error:', 'coinsnap-for-ninja-forms');
                        echo esc_html($e->getMessage());
                        echo '</p></div>';
                    }  
                }
            echo '</div>';
        }            
    }
        
    public function process_webhook(){
        if(null === filter_input(INPUT_GET,'nf-listener',FILTER_SANITIZE_FULL_SPECIAL_CHARS) || filter_input(INPUT_GET,'nf-listener',FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== 'coinsnap' ) { return; }
        $CoinsnapPG = new CoinsnapNF_PaymentGateway();
        $CoinsnapPG->webhook();
    }
        
    public function addMetabox(array $metaboxHandlers): array {
        $metaboxHandlers['coinsnap_status'] = 'CoinsnapNF_Admin_Metaboxes_MetaboxEntityConstructorCoinsnapStatus';            
        return $metaboxHandlers;
    }

    public function setup_admin(){
        Ninja_Forms()->merge_tags[ 'coinsnap' ] = new CoinsnapNF_MergeTags();
        if( ! is_admin() ){ return; }               
        new CoinsnapNF_Admin_Settings();            
        new CoinsnapNF_Admin_Metaboxes_Submission();            
    }
        
    public function register_payment_gateways($payment_gateways){
        $payment_gateways[ 'coinsnap' ] = new CoinsnapNF_PaymentGateway();
        return $payment_gateways;
    }

    public function register_actions( $actions ){  	
        $coinsnap_action = new NF_Actions_CollectPayment( 'Coinsnap' , 'coinsnap' );		    
        $actions[ 'coinsnap' ] = $coinsnap_action;
        return $actions;
    }
                
    public function register_templates( $templates ){
        $templates[ 'coinsnap-payment' ] = array(
            'id'            => 'coinsnap-payment',
            'title'         => __( 'Coinsnap Payment', 'coinsnap-for-ninja-forms' ),
            'template-desc' => __( 'Collect a payment using Coinsnap. You can add and remove fields as needed.', 'coinsnap-for-ninja-forms' ),
            'form'          => self::form_templates( 'coinsnap-payment.json' ),
        );
        return $templates;
    }

    public static function form_templates( $file_name = '', array $data = array() ){
        $path = self::$dir . 'includes/Templates/' . $file_name;
        if( ! file_exists(  $path ) ){ return '';}
        extract( $data );
        ob_start();
        include $path;
        return ob_get_clean();
    }

    public function autoloader($class_name){
        if (class_exists($class_name)){ return; }
        if ( false === strpos( $class_name, self::PREFIX ) ){ return; }

        $class_file_name = str_replace( self::PREFIX, '', $class_name );
        $classes_dir = realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
        $class_file = str_replace('_', DIRECTORY_SEPARATOR, $class_file_name) . '.php';

        if (file_exists($classes_dir . $class_file)) {
            require_once $classes_dir . $class_file;
        }
    }

    public static function config( $file_name ){
        return include self::$dir . 'includes/Config/' . $file_name . '.php';
    }

    public static function template( $file_name = '', array $data = array()){
        if( ! $file_name ){ return; }
        extract( $data );
        if( file_exists( self::$dir . 'includes/Templates/' . $file_name )) {
            include self::$dir . 'includes/Templates/' . $file_name;
        }
    }
        
    public function export_transaction_data( $csv_array, $subs, $form_id ){
        $add_transactions = false;
        $actions = Ninja_Forms()->form($form_id)->get_actions();
        // Loop over our actions to see if Coinsnap exists.
        foreach( $actions as $action ){
            $settings = $action->get_settings();
            // check for collectpayment or coinsnap types
            if(in_array($settings['type'],array('collectpayment','coinsnap')) && 'coinsnap' == $settings['payment_gateways']){ $add_transactions = true; }
        }
            
        // If we didn't find a Coinsnap action, bail.
        if( ! $add_transactions ) return $csv_array;
            
        // Add our labels.
        $csv_array[ 0 ][ 0 ][ 'coinsnap_status' ] = __( 'Coinsnap Status', 'coinsnap-for-ninja-forms' );
        $csv_array[ 0 ][ 0 ][ 'coinsnap_transaction_id' ] = __( 'Coinsnap Transaction ID', 'coinsnap-for-ninja-forms' );
        // Add our values.
        $i = 0;
        foreach( $subs as $sub ){
            $csv_array[ 1 ][ 0 ][ $i ][ 'coinsnap_status' ] = $sub->get_extra_value( 'coinsnap_status' );
            $csv_array[ 1 ][ 0 ][ $i ][ 'coinsnap_transaction_id' ] = $sub->get_extra_value( 'coinsnap_transaction_id' );
            $i++;
        }
        return $csv_array;
    }
}

add_action('init', function() {    
// Setting up and handling custom endpoint for api key redirect from BTCPay Server.
    add_rewrite_endpoint('coinsnap-for-ninja-forms-btcpay-settings-callback', EP_ROOT);
});

// To be able to use the endpoint without appended url segments we need to do this.
add_filter('request', function($vars) {
    if (isset($vars['coinsnap-for-ninja-forms-btcpay-settings-callback'])) {
        $vars['coinsnap-for-ninja-forms-btcpay-settings-callback'] = true;
    }
    return $vars;
});


function CoinsnapNF() {
    require_once (plugin_dir_path(__FILE__) . 'library/loader.php');
    return CoinsnapNF::instance();
}

function coinsnapnf_dependency_check(){        
    if (!is_plugin_active('ninja-forms/ninja-forms.php')) {
        add_action('admin_notices', 'coinsnapnf_dependency_notice');
        deactivate_plugins(plugin_basename(__FILE__));
    }
}
    
function coinsnapnf_dependency_notice(){?>
    <div class="notice notice-error">
        <p><?php echo esc_html_e('Bitcoin payment for Ninja Forms requires Ninja Forms to be installed and activated.','coinsnap-for-ninja-forms');?></p>
    </div>
    <?php        
}

function CoinsnapNF_Settings($data){
    
    $plugin_settings = get_option( 'ninja_forms_coinsnap', array(        
        'store_id' => '',
        'api_key' => '',        
        'currency' => 'USD',
    ));
    
    $new_settings = array(
        'coinsnap_currency' => $plugin_settings['currency'],
        'coinsnap_store_id' => $plugin_settings['store_id'],
        'coinsnap_api_key' => $plugin_settings['api_key'],
        'btcpay_store_id' => $plugin_settings['btcpay_store_id'],
        'btcpay_api_key' => $plugin_settings['btcpay_api_key']
    );
    
    $current_settings = Ninja_Forms()->get_settings();
    foreach( $new_settings as $setting => &$value ) {
        if( isset( $current_settings[ $setting ] ) && !empty( $current_settings[ $setting ] ) ) {
            $value = $current_settings[ $setting ];
        }
    }
    
    Ninja_Forms()->update_settings( $new_settings );
    
    if( isset( $data[ 'settings' ][ 'coinsnap' ] ) && $data[ 'settings' ][ 'coinsnap' ] === 1 ){

        $new_action = array(
            'type' => 'coinsnap',
            'label' => __( 'Coinsnap', 'coinsnap-for-ninja-forms' ),
            'payment_gateways' => 'coinsnap',
            'coinsnap_description' => array(),
        );
        
        if( isset( $data[ 'settings' ][ 'coinsnap_default_total' ] ) && $data[ 'settings' ][ 'coinsnap_default_total' ] ) {
            $new_action[ 'payment_total' ] = $data[ 'settings' ][ 'coinsnap_default_total' ];
            $new_action[ 'payment_total_type' ] = 'fixed';
        }

        foreach( $data[ 'fields' ] as $field ){
            if( '_calc' != $field[ 'type' ] ){ continue;}
            if( !isset( $field[ 'data' ][ 'calc_name' ] ) || 'total' != $field[ 'data' ][ 'calc_name' ] ){ continue; }
            $new_action[ 'payment_total' ] = '{calc:calc_' . $field[ 'id' ] . '}';
        }        

        if( isset( $data[ 'settings' ][ 'coinsnap_product_name' ] ) && $data[ 'settings' ][ 'coinsnap_product_name' ] ) {
            $new_action[ 'coinsnap_description' ][] = $data[ 'settings' ][ 'coinsnap_product_name' ];
        }

        if( isset( $data[ 'settings' ][ 'coinsnap_product_desc' ] ) && $data[ 'settings' ][ 'coinsnap_product_desc' ] ) {
            $new_action[ 'coinsnap_description' ][] = $data[ 'settings' ][ 'coinsnap_product_desc' ];
        }

        $new_action[ 'coinsnap_description' ] = implode( ': ', $new_action[ 'coinsnap_description' ] );
        $data[ 'actions' ][] = $new_action;
    }
    return $data;
}

// Go ninja, go ninja, go!
CoinsnapNF();


