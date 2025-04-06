<?php if ( ! defined( 'ABSPATH' ) ){ exit;}

final class CoinsnapNF_Admin_Metaboxes_Submission extends NF_Abstracts_SubmissionMetabox
{
    public function __construct(){
        parent::__construct();

        $this->_title = __( 'Payment Details', 'coinsnap-for-ninja-forms' );        

        if( $this->sub && ! $this->sub->get_extra_value( 'coinsnap_status' ) && ! $this->sub->get_extra_value( '_coinsnap_status' ) ){
            remove_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        }
    }

    public function render_metabox( $post, $metabox ){
        
        $status = $this->sub->get_extra_value( 'coinsnap_status' );
        if( ! $status ){
            $status = $this->sub->get_extra_value( '_coinsnap_status' );
        }

        $total = $this->sub->get_extra_value( 'coinsnap_total' );
        if( ! $total ){
            $total = $this->sub->get_extra_value( '_coinsnap_total' );
        }

        $transaction_id = $this->sub->get_extra_value( 'coinsnap_transaction_id' );
        if( ! $transaction_id ){
            $transaction_id = $this->sub->get_extra_value( '_coinsnap_transaction_id' );
        }

        $data = array(
            __( 'Status', 'coinsnap-for-ninja-forms' ) => $status,
            __( 'Total', 'coinsnap-for-ninja-forms' )  => $total,
            __( 'Transaction ID', 'coinsnap-for-ninja-forms' ) => $transaction_id
        );

        CoinsnapNF::template( 'admin-metaboxes-submission.html.php', $data );
    }
}