<?php

if ( ! defined( 'ABSPATH' ) ){
    exit;
}

return apply_filters( 'nf_coinsnap_plugin_settings', array(

    
    'coinsnap_store_id' => array(
        'id'    => 'coinsnap_store_id',
        'type'  => 'textbox',
        'label' => __( 'Store ID', 'coinsnap-for-ninjaforms' ),
    ),

    'coinsnap_api_key' => array(
        'id'    => 'coinsnap_api_key',
        'type'  => 'textbox',
        'label' => __( 'API Key', 'coinsnap-for-ninjaforms' ),
    )    

));
