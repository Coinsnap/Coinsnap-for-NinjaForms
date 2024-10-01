<?php
if ( ! defined( 'ABSPATH' ) ){
    exit;
}
?>
<ul>
    <?php foreach( $data as $label => $value ):?>
        <li>
            <strong><?php echo esc_html($label); ?></strong>
            <br /><?php echo esc_html($value); ?>
        </li>
    <?php endforeach; ?>
</ul>