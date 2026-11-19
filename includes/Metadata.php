<?php
if ( ! defined( 'ABSPATH' ) ){ exit;}

/**
 * Invoice metadata for a Ninja Forms submission: the submission ID as orderId, the form, the
 * products and the submitted fields. Plain values (no HTML escaping); empty values are left out.
 * PHP 7.4 syntax only.
 */
final class CoinsnapNF_Metadata {

    const PLATFORM = 'ninja-forms';
    const MAX_LENGTH = 500;

    /** Never sent: files, passwords, card, captcha and spam fields, hidden and layout fields. Pricing fields go to lineItems. */
    const SKIP_TYPES = array(
        'submit', 'button', 'timedsubmit', 'html', 'hr', 'hidden', 'signature', 'password', 'passwordconfirm', 'recaptcha', 'recaptcha_v3', 'hcaptcha', 'turnstile', 'spam',
        'file_upload', 'fileupload', 'creditcard', 'creditcardcvc', 'creditcardexpiration', 'creditcardfullname', 'creditcardnumber',
        'creditcardzip', 'note', 'confirm', 'terms', 'save', 'unknown', 'product', 'quantity', 'shipping', 'total',
    );

    /** Address parts by Ninja Forms field type. */
    const ADDRESS_TYPES = array(
        'address' => 'buyerAddress1', 'address2' => 'buyerAddress2', 'city' => 'buyerCity',
        'liststate' => 'buyerState', 'zip' => 'buyerZip', 'listcountry' => 'buyerCountry',
    );

    /**
     * @param array $args sub_id, seq_num, form_id, form_title, order_url, page_url, version, language,
     *                    fields (the submission's $data['fields']: id, key, type, label, value, and product/shipping settings)
     */
    public static function for_submission( array $args ) {
        $subId = (string) ( $args['sub_id'] ?? '' );
        $meta = array();
        // The webhook matches the invoice to the submission by this ID (top-level orderId, metadata orderId / orderNumber).
        self::put( $meta, 'orderId', $subId );
        self::put( $meta, 'orderNumber', $subId );
        self::put( $meta, 'entryId', $subId );
        self::put( $meta, 'submissionNumber', (string) ( $args['seq_num'] ?? '' ) );
        self::put( $meta, 'orderUrl', $args['order_url'] ?? '' );
        self::put( $meta, 'formId', (string) ( $args['form_id'] ?? '' ) );
        self::put( $meta, 'formTitle', $args['form_title'] ?? '' );
        self::put( $meta, 'itemDesc', $args['form_title'] ?? '' );
        self::put( $meta, 'platform', self::PLATFORM );
        self::put( $meta, 'pluginVersion', $args['version'] ?? '' );
        self::put( $meta, 'pageUrl', $args['page_url'] ?? '' );
        self::put( $meta, 'language', $args['language'] ?? '' );

        $fields = array_values( array_filter( (array) ( $args['fields'] ?? array() ), 'is_array' ) );

        // Products: price from the field settings, quantity from the visitor (as Ninja Forms counts it).
        $quantities = array();
        foreach ( $fields as $field ) {
            if ( ( $field['type'] ?? '' ) === 'quantity' && ! empty( $field['product_assignment'] ) ) {
                $quantities[ (string) $field['product_assignment'] ] = is_scalar( $field['value'] ?? null ) ? trim( (string) $field['value'] ) : '';
            }
        }
        $items = array();
        foreach ( $fields as $field ) {
            $type = (string) ( $field['type'] ?? '' );
            if ( $type === 'product' ) {
                $qty = $quantities[ (string) ( $field['id'] ?? '' ) ] ?? '';
                if ( ( $qty === '' || $qty === '0' ) && ! empty( $field['product_use_quantity'] ) ) {
                    $qty = is_scalar( $field['value'] ?? null ) ? trim( (string) $field['value'] ) : '';
                }
                $qty = preg_match( '/^\d{1,6}$/', $qty ) && (int) $qty > 0 ? (int) $qty : 1;
                $price = (float) str_replace( array( ',', '$' ), '', (string) ( $field['product_price'] ?? '0' ) );
                $line = array();
                self::put( $line, 'name', $field['label'] ?? '' );
                self::put( $line, 'sku', (string) ( $field['key'] ?? '' ) );
                $line['quantity'] = $qty;
                $line['price'] = self::money( $price );
                $line['total'] = self::money( $price * $qty );
                $items[] = $line;
            }
            elseif ( $type === 'shipping' ) {
                self::put( $meta, 'shippingMethod', $field['label'] ?? '' );
                $meta['shippingTotal'] = self::money( (float) str_replace( array( ',', '$' ), '', (string) ( $field['shipping_cost'] ?? '0' ) ) );
            }
        }
        if ( $items ) {
            $meta['lineItems'] = $items;
        }

        // Personal data. The plugin has no "send customer data" setting, so it is always sent.
        $first = '';
        $last = '';
        $other = array();
        foreach ( $fields as $field ) {
            $type = (string) ( $field['type'] ?? '' );
            if ( in_array( $type, self::SKIP_TYPES, true ) ) {
                continue;
            }
            $value = self::clean( $field['value'] ?? '' );
            if ( $value === null ) {
                continue;
            }
            if ( $type === 'firstname' && $first === '' ) {
                $first = (string) $value;
            } elseif ( $type === 'lastname' && $last === '' ) {
                $last = (string) $value;
            } elseif ( $type === 'email' && ! isset( $meta['buyerEmail'] ) ) {
                $meta['buyerEmail'] = $value;
            } elseif ( $type === 'phone' && ! isset( $meta['buyerPhone'] ) ) {
                $meta['buyerPhone'] = $value;
            } elseif ( isset( self::ADDRESS_TYPES[ $type ] ) && ! isset( $meta[ self::ADDRESS_TYPES[ $type ] ] ) ) {
                $meta[ self::ADDRESS_TYPES[ $type ] ] = $value;
            } elseif ( $type === 'textarea' && ! isset( $meta['buyerMessage'] ) ) {
                $meta['buyerMessage'] = $value;
            } else {
                $label = trim( (string) ( $field['label'] ?? '' ) );
                $label = ( $label === '' ) ? (string) ( $field['key'] ?? ( 'Field ' . ( $field['id'] ?? '' ) ) ) : $label;
                $other[ isset( $other[ $label ] ) ? $label . ' (' . ( $field['key'] ?? $field['id'] ?? '' ) . ')' : $label ] = $value;
            }
        }
        $name = trim( $first . ' ' . $last );
        self::put( $meta, 'customerName', $name );
        self::put( $meta, 'buyerName', $name );
        if ( $other ) {
            $meta['formFields'] = $other;
        }
        return $meta;
    }

    private static function money( float $value ): string {
        return number_format( $value, 2, '.', '' );
    }

    /** Adds $value under $key unless it is empty. */
    public static function put(array &$meta, string $key, $value): void {
        $value = self::clean($value);
        if ($value !== null) {
            $meta[$key] = $value;
        }
    }

    /** A plain value for metadata, or null to leave it out (empty, not text, or a card number). */
    public static function clean($value) {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $part) {
                if (is_scalar($part) && !is_bool($part)) {
                    $part = self::clean($part);
                    if ($part !== null) {
                        $parts[] = (string) $part;
                    }
                }
            }
            return $parts ? self::cut(implode(', ', $parts)) : null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || self::looks_like_card_number($value)) {
            return null;
        }
        return self::cut($value);
    }

    /** 13 to 19 digits (spaces and dashes allowed) that pass the Luhn check. */
    public static function looks_like_card_number(string $value): bool {
        $digits = preg_replace('/[\s-]/', '', $value);
        if (!is_string($digits) || !preg_match('/^\d{13,19}$/', $digits)) {
            return false;
        }
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = !$double;
        }
        return $sum % 10 === 0;
    }

    private static function cut(string $value): string {
        if (function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > self::MAX_LENGTH ? mb_substr($value, 0, self::MAX_LENGTH, 'UTF-8') : $value;
        }
        return strlen($value) > self::MAX_LENGTH ? substr($value, 0, self::MAX_LENGTH) : $value;
    }
}
