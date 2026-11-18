<?php
if ( ! defined( 'ABSPATH' ) ){ exit;}

/**
 * Server-side payment checks, following the Coinsnap Plugin Security Guide:
 * a payment counts only after the Coinsnap or BTCPay API confirms it, secrets stay
 * on the server, and a BTCPay key is accepted only after BTCPay says what it may do.
 *
 * PHP 7.4 syntax only: the plugin declares PHP 7.4 as its minimum.
 */
final class CoinsnapNF_Security {

    const PAID       = 'paid';
    const PROCESSING = 'processing';
    const FAILED     = 'failed';   // Expired or Invalid
    const NONE       = 'none';     // New, or a status this plugin doesn't know
    const MISMATCH   = 'mismatch'; // Settled, but not for what this plugin asked (or marked by hand in BTCPay)

    const BAD_SIGNATURE   = 'bad signature';
    const STALE_TIMESTAMP = 'stale timestamp';
    const MAX_AGE_SECONDS = 300;

    const STATE_KEY     = 'coinsnapnf_btcpay_state';
    const RECORDS_SINCE = 'coinsnapnf_records_since';

    // Only what the plugin uses: invoices, the store's payment methods and rates, and its own webhooks.
    const BTCPAY_PERMISSIONS = array(
        'btcpay.store.canviewinvoices',
        'btcpay.store.cancreateinvoice',
        'btcpay.store.canviewstoresettings',
        'btcpay.store.webhooks.canmodifywebhooks',
    );

    /* ---------------------------------------------------------------------
     * Webhook signature (handler step 1). Returns null if valid, else the log reason.
     * ------------------------------------------------------------------- */

    // X-Coinsnap-Signature: t=<unix time>,v1=<hex HMAC-SHA256 of "t.body">
    public static function coinsnap_signature_problem( string $header, string $rawBody, string $secret, int $now ): ?string {
        parse_str( str_replace( ',', '&', $header ), $parts );
        $t = (int) ( $parts['t'] ?? 0 );
        $v1 = $parts['v1'] ?? '';
        if ( $secret === '' || $t <= 0 || ! is_string( $v1 ) || ! hash_equals( hash_hmac( 'sha256', $t . '.' . $rawBody, $secret ), $v1 ) ) {
            return self::BAD_SIGNATURE;
        }
        if ( abs( $now - $t ) > self::MAX_AGE_SECONDS ) {
            return self::STALE_TIMESTAMP;
        }
        return null;
    }

    // BTCPay-Sig: sha256=<hex HMAC-SHA256 of body>
    public static function btcpay_signature_problem( string $header, string $rawBody, string $secret ): ?string {
        if ( $secret === '' || $header === '' ) {
            return self::BAD_SIGNATURE;
        }
        return hash_equals( 'sha256=' . hash_hmac( 'sha256', $rawBody, $secret ), $header ) ? null : self::BAD_SIGNATURE;
    }

    /* ---------------------------------------------------------------------
     * When is an invoice paid (fix 3)
     * ------------------------------------------------------------------- */

    // $expectedAmount and $expectedCurrency are what the plugin sent when it created the invoice.
    public static function decide( array $invoice, bool $isBtcpay, string $expectedAmount, string $expectedCurrency ): string {
        $status = (string) ( $invoice['status'] ?? '' );

        if ( $status === 'Settled' ) {
            if ( $isBtcpay && ( $invoice['additionalStatus'] ?? '' ) === 'Marked' ) {
                return self::MISMATCH; // settled by hand in BTCPay
            }
            if ( $isBtcpay || strtoupper( $expectedCurrency ) === 'SATS' ) {
                $paid = $invoice['amount'] ?? null; // BTCPay: invoice currency; Coinsnap SATS invoice: sats
            } else {
                $paid = $invoice['invoiceAmount'] ?? ( $invoice['fiatAmount'] ?? null ); // Coinsnap: amount is in sats
            }
            return self::same_amount( $paid, $invoice['currency'] ?? null, $expectedAmount, $expectedCurrency ) ? self::PAID : self::MISMATCH;
        }
        if ( $status === 'Processing' ) {
            return self::PROCESSING;
        }
        if ( $status === 'Expired' || $status === 'Invalid' ) {
            return self::FAILED;
        }
        return self::NONE;
    }

    private static function same_amount( $paid, $paidCurrency, string $expectedAmount, string $expectedCurrency ): bool {
        $expected = self::to_units( $expectedAmount );
        $got = self::to_units( $paid );
        if ( $expected === null || $expected <= 0 || $got === null ) {
            return false;
        }
        if ( strtoupper( (string) $paidCurrency ) !== strtoupper( $expectedCurrency ) ) {
            return false;
        }
        // BTC: one unit of the 8th decimal. Everything else: one cent (whole sats for SATS).
        $tolerance = ( strtoupper( $expectedCurrency ) === 'BTC' ) ? 1 : 1000000;
        if ( strtoupper( $expectedCurrency ) === 'SATS' ) {
            $tolerance = 0;
        }
        return abs( $got - $expected ) <= $tolerance;
    }

    // Amount as an integer of 10^-8 units, parsed from the decimal string so it is never compared as a float.
    public static function to_units( $value ): ?int {
        if ( is_int( $value ) || is_float( $value ) ) {
            $value = number_format( (float) $value, 8, '.', '' );
        }
        if ( ! is_string( $value ) || ! preg_match( '/^(\d{1,12})(?:\.(\d+))?$/', trim( $value ), $m ) ) {
            return null;
        }
        $fraction = str_pad( $m[2] ?? '', 9, '0' );
        $roundUp = ( (int) $fraction[8] >= 5 ) ? 1 : 0;
        return (int) $m[1] * 100000000 + (int) substr( $fraction, 0, 8 ) + $roundUp;
    }

    // Orders that existed before invoice records were kept are accepted for one day after the upgrade (6.7).
    public static function legacy_allowed(): bool {
        $since = (int) get_option( self::RECORDS_SINCE, 0 );
        return $since > 0 && ( time() - $since ) < DAY_IN_SECONDS;
    }

    /* ---------------------------------------------------------------------
     * BTCPay connect callback and key permissions
     * ------------------------------------------------------------------- */

    // Called by the admin AJAX handler (capability and nonce checked there). The host travels with the state.
    public static function btcpay_start_state( string $host ): string {
        $state = bin2hex( random_bytes( 32 ) );
        set_transient( self::STATE_KEY, array( 'hash' => hash( 'sha256', $state ), 'host' => $host ), 10 * MINUTE_IN_SECONDS );
        return $state;
    }

    // The BTCPay URL the state was created for, or null. One use, even if the check fails.
    public static function btcpay_consume_state( string $state ): ?string {
        $saved = get_transient( self::STATE_KEY );
        delete_transient( self::STATE_KEY );
        if ( ! is_array( $saved ) || ! is_string( $saved['hash'] ?? null ) || ! is_string( $saved['host'] ?? null ) || $state === '' ) {
            return null;
        }
        return hash_equals( $saved['hash'], hash( 'sha256', $state ) ) ? $saved['host'] : null;
    }

    // The one store the key is limited to, or null if it is unrestricted, spans stores or lacks a needed permission.
    public static function btcpay_key_store( array $permissions ): ?string {
        if ( $permissions === array() ) {
            return null;
        }
        $storeId = null;
        foreach ( $permissions as $permission ) {
            $parts = explode( ':', (string) $permission, 2 );
            $scope = $parts[1] ?? '';
            if ( strpos( $parts[0], 'btcpay.store.' ) !== 0 || $scope === '' ) {
                return null; // unrestricted, server or user rights, or valid for every store
            }
            if ( $storeId !== null && $scope !== $storeId ) {
                return null; // more than one store
            }
            $storeId = $scope;
        }
        foreach ( self::BTCPAY_PERMISSIONS as $needed ) {
            if ( ! in_array( $needed . ':' . $storeId, $permissions, true ) ) {
                return null;
            }
        }
        return $storeId;
    }

    // Permissions as BTCPay reports them for this key (GET /api/v1/api-keys/current), or null.
    public static function btcpay_key_permissions( string $serverUrl, string $apiKey ): ?array {
        if ( $serverUrl === '' || $apiKey === '' ) {
            return null;
        }
        $response = wp_remote_get( rtrim( $serverUrl, '/' ) . '/api/v1/api-keys/current', array(
            'timeout' => 15,
            'headers' => array( 'Authorization' => 'token ' . $apiKey, 'Accept' => 'application/json' ),
        ) );
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }
        $key = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        return ( is_array( $key ) && is_array( $key['permissions'] ?? null ) ) ? array_values( array_map( 'strval', $key['permissions'] ) ) : null;
    }

    // The store the key is limited to, if BTCPay confirms it (and, when given, it equals $expectedStoreId).
    public static function btcpay_verify_key( string $serverUrl, string $apiKey, string $expectedStoreId = '' ): ?string {
        $permissions = self::btcpay_key_permissions( $serverUrl, $apiKey );
        $storeId = ( $permissions === null ) ? null : self::btcpay_key_store( $permissions );
        if ( $storeId === null || ( $expectedStoreId !== '' && $storeId !== $expectedStoreId ) ) {
            return null;
        }
        return $storeId;
    }

    /* ---------------------------------------------------------------------
     * Settings display and logs
     * ------------------------------------------------------------------- */

    public static function secret_hint( string $value ): string {
        return $value === '' ? '' : '••••••••' . substr( $value, -4 );
    }

    // A fixed reason and the invoice ID, never secrets, store IDs, bodies or customer data (5, 6.13).
    public static function log( string $level, string $reason, string $invoiceId = '' ): void {
        if ( $level === 'security' && ! self::security_log_allowed() ) {
            return;
        }
        $id = substr( (string) preg_replace( '/[^A-Za-z0-9_-]/', '', $invoiceId ), 0, 64 );
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( sprintf( '[coinsnap-for-ninja-forms] %s webhook reason=%s invoice=%s', strtoupper( $level ), $reason, $id !== '' ? $id : '-' ) );
    }

    // At most 10 security lines a minute, so a flood of fake requests can't fill the log.
    private static function security_log_allowed(): bool {
        $key = 'coinsnapnf_seclog_' . gmdate( 'YmdHi' );
        $count = (int) get_transient( $key ) + 1;
        set_transient( $key, $count, 2 * MINUTE_IN_SECONDS );
        if ( $count === 11 ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[coinsnap-for-ninja-forms] SECURITY webhook more rejections this minute, not logged' );
        }
        return $count <= 10;
    }
}
