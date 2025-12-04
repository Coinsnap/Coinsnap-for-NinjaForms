<?php
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
?>
<ul>
    <?php foreach( $data as $coinsnapnf_label => $coinsnapnf_value ):?>
        <li>
            <strong><?php echo esc_html($coinsnapnf_label); ?></strong>
            <br /><?php echo esc_html($coinsnapnf_value); ?>
        </li>
    <?php endforeach; ?>
</ul>