<?php if ( ! defined( 'ABSPATH' ) ){ exit;}

return apply_filters( 'coinsnapnf_plugin_settings', array(
    
    'coinsnap_title' => array(
        'id'    => 'coinsnap_title',
        'type'  => 'hr',
        'group' => 'coinsnap',
        'class' => '',
        'label' => '',
        'width' => '',
        'desc'  => '<span id="coinsnapConnectionStatus"></span>',
    ),

    'coinsnap_provider' => array(
        'id'    => 'coinsnap_provider',
        'type'  => 'select',
        'options' => array(
                array(
                    'value' => 'coinsnap',
                    'label' => 'Coinsnap'
                ),
                array(
                    'value' => 'btcpay',
                    'label' => 'BTCPay Server'
                ),
            ),
        'label' => __( 'Payment provider', 'coinsnap-for-ninja-forms' ),
    ),

    'coinsnap_store_id' => array(
        'id'    => 'coinsnap_store_id',
        'type'  => 'textbox',
        'group' => 'coinsnap',
        'class' => 'coinsnap',
        'label' => __( 'Store ID*', 'coinsnap-for-ninja-forms' ),
        'width' => 'one-half',
        'desc'  => __('Your Coinsnap Store ID. You can find it on the store settings page on your <a href="https://app.coinsnap.io/" target="_blank">Coinsnap account</a>.','coinsnap-for-ninja-forms'),
    ),

    'coinsnap_api_key' => array(
        'id'    => 'coinsnap_api_key',
        'type'  => 'textbox',
        'class' => 'coinsnap',
        'label' => __( 'API Key*', 'coinsnap-for-ninja-forms' ),
        'width' => 'one-half',
        'desc'  => __( 'Coinsnap API requires authentication with an API key.<br/>Generate your API key by visiting the <a href="https://app.coinsnap.io/register" target="_blank">Coinsnap registration Page</a>.', 'coinsnap-for-ninja-forms' ),
        'help'  => __('Your Coinsnap API Key. You can find it on the store settings page on your Coinsnap Server.','coinsnap-for-ninja-forms'),
    ),

    'btcpay_server_url' => array(
        'id'    => 'btcpay_server_url',
        'type'  => 'textbox',
        'class' => 'btcpay',
        'label' => __( 'BTCPay server URL*', 'coinsnap-for-ninja-forms' ),
        'width' => 'one-half',
        'desc'  => __( '<a href="#" class="btcpay-apikey-link">Check connection</a>', 'coinsnap-for-ninja-forms' ).'<br/><br/><button type="button" class="button btcpay-apikey-link" id="btcpay_wizard_button" target="_blank">'. __('Generate API key','coinsnap-for-ninja-forms').'</button>',
        'help'  => __('Your BTCPay server URL.','coinsnap-for-ninja-forms'),
    ),

    'btcpay_store_id' => array(
        'id'    => 'btcpay_store_id',
        'type'  => 'textbox',
        'class' => 'btcpay',
        'label' => __( 'Store ID*', 'coinsnap-for-ninja-forms' ),
        'width' => 'one-half',
        'desc'  => __('Your BTCPay Store ID. You can find it on the store settings page on your BTCPay Server.','coinsnap-for-ninja-forms'),
    ),

    'btcpay_api_key' => array(
        'id'    => 'btcpay_api_key',
        'type'  => 'textbox',
        'class' => 'btcpay',
        'label' => __( 'API Key*', 'coinsnap-for-ninja-forms' ),
        'width' => 'one-half',
        'desc'  => __('Your BTCPay server API Key. You can generate it in your BTCPay Server.','coinsnap-for-ninja-forms'),
    ),

    'coinsnap_autoredirect' => array(
        'id'    => 'coinsnap_autoredirect',
        'type'  => 'checkbox',
        'label' => __( 'Redirect after payment', 'coinsnap-for-ninja-forms' ),
    ),
    
    'coinsnap_returnurl' => array(
        'id'    => 'coinsnap_returnurl',
        'type'  => 'textbox',
        'label' => __( 'Return URL after payment', 'coinsnap-for-ninja-forms' ),
        'width' => 'one-half',
        'desc'  => __('Custom return URL after successful payment (default URL if blank)','coinsnap-for-ninja-forms'),
    ),
));
