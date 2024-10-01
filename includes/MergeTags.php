<?php

if ( ! defined( 'ABSPATH' ) ){
    exit;
}

/**
 * Class NF_Coinsnap_MergeTags
 */
final class NF_Coinsnap_MergeTags extends NF_Abstracts_MergeTags
{
    protected $id = 'coinsnap';

    private $transaction_id = '';

    public function __construct()
    {
        parent::__construct();
        $this->title = __( 'Coinsnap', 'ninja-forms' );

        $this->merge_tags = array(
            'transaction_id' => array(
                'id' => 'transaction_id',
                'tag' => '{coinsnap:transaction_id}',
                'label' => __( 'Transaction ID', 'ninjaforms-coinsnap' ),
                'callback' => 'get_transaction_id'
            ),
        );
    }

    public function set_transaction_id( $transaction_id = '' )
    {
        $this->transaction_id = $transaction_id;
    }

    public function get_transaction_id()
    {
        return $this->transaction_id;
    }

} // END CLASS NF_Coinsnap_MergeTags
