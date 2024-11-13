<?php

if ( ! defined( 'ABSPATH' ) ){
    exit;
}

return apply_filters( 'nf_coinsnap_plugin_settings_groups', array(

    'coinsnap' => array(
        'id' => 'coinsnap',
        'label' => __( 'Coinsnap', 'coinsnap-for-ninjaforms' ),
    ),
));