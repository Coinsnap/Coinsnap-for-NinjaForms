<?php if ( ! defined( 'ABSPATH' ) ){ exit;}

/**
 * Class CoinsnapNF_MergeTags
 */
final class CoinsnapNF_MergeTags extends NF_Abstracts_MergeTags
{
    protected $id = 'coinsnap';

    private $transaction_id = '';

    public function __construct()
    {
        parent::__construct();
        $this->title = __( 'Coinsnap', 'coinsnap-for-ninja-forms' );

        $this->merge_tags = array(
            'transaction_id' => array(
                'id' => 'transaction_id',
                'tag' => '{coinsnap:transaction_id}',
                'label' => __( 'Transaction ID', 'coinsnap-for-ninja-forms' ),
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

} // END CLASS CoinsnapNF_MergeTags
