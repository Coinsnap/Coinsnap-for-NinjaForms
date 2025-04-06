<?php if ( ! defined( 'ABSPATH' ) ){ exit;}

use NinjaForms\Includes\Entities\MetaboxOutputEntity;

class CoinsnapNF_Admin_Metaboxes_MetaboxEntityConstructorCoinsnapStatus {

    public function handle($extraValue, $nfSub): ?MetaboxOutputEntity {
        $return = null;

        // If coinsnap_status is not set, return null to cancel output
        if (!$nfSub->get_extra_value('coinsnap_status') && !$nfSub->get_extra_value('_coinsnap_status') ) {            
            return $return;
        }
        
        $labelValueCollection = self::extractResponses($nfSub);

        if (!empty($labelValueCollection)) {
            $array = [
                'title' => __('Coinsnap Payment Details', 'coinsnap-for-ninja-forms'),
                'labelValueCollection' => $labelValueCollection

            ];
            $return = MetaboxOutputEntity::fromArray($array);
        }
        return $return;
    }

    /**
     * Extract all Coinsnap 'extra' data and add to constructed entity
     *
     * @param NF_Database_Models_Submission $nfSub
     * @return array
     */
    protected static function extractResponses($nfSub): array {
        $return = [];

        if ($nfSub->get_extra_value('coinsnap_status')) {
            $return[] = [
                'label' => __("Payment Status", "coinsnap-for-ninja-forms"),
                'value' => $nfSub->get_extra_value('coinsnap_status'),
                'styling' => ''
            ];
        }
        if ($nfSub->get_extra_value('coinsnap_total')) {
            $return[] = [
                'label' => __("Payment Total", "coinsnap-for-ninja-forms"),
                'value' => $nfSub->get_extra_value('coinsnap_total'),
                'styling' => ''
            ];
        }
        if ($nfSub->get_extra_value('coinsnap_transaction_id')) {
            $return[] = [
                'label' => __("Transaction ID", "coinsnap-for-ninja-forms"),
                'value' => $nfSub->get_extra_value('coinsnap_transaction_id'),
                'styling' => ''
            ];
        }
        return $return;
    }
}
