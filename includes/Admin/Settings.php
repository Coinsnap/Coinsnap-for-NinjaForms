<?php if ( ! defined( 'ABSPATH' ) ){ exit;}

/**
 * Class CoinsnapNF_Admin_Settings
 */
final class CoinsnapNF_Admin_Settings {

    const KEY_FIELDS = array( 'coinsnap_api_key', 'btcpay_api_key' );

    /** Settings as they were before this save request. */
    private static $before = null;

    public function __construct()
    {
        add_filter( 'ninja_forms_plugin_settings', array( $this, 'plugin_settings'), 10, 1 );
        add_filter( 'ninja_forms_plugin_settings_groups', array( $this, 'plugin_settings_groups'), 10, 1 );

        // The settings page proves a key exists; it never shows it (guide: showing secrets).
        foreach ( self::KEY_FIELDS as $field ) {
            add_filter( 'ninja_forms_check_setting_' . $field, array( $this, 'mask_key_field' ) );
        }
        // Ninja Forms checks its nonce and capability before it runs these filters.
        add_action( 'admin_init', array( $this, 'remember_settings' ), 1 );
        add_filter( 'ninja_forms_update_setting_coinsnap_api_key', array( $this, 'save_coinsnap_api_key' ) );
        add_filter( 'ninja_forms_update_setting_btcpay_api_key', array( $this, 'save_btcpay_api_key' ) );
    }

    public function plugin_settings( $settings )
    {
        $settings[ 'coinsnap' ] = CoinsnapNF()->config( 'PluginSettings' );
        return $settings;
    }

    public function plugin_settings_groups( $groups )
    {
        $groups = array_merge( $groups, CoinsnapNF()->config( 'PluginSettingsGroups' ) );
        return $groups;
    }

    /** An empty password field with a hint of the last 4 characters. Saving it empty keeps the stored key. */
    public function mask_key_field( $setting )
    {
        $hint = CoinsnapNF_Security::secret_hint( (string) ( $setting['value'] ?? '' ) );
        $setting['type'] = 'html';
        $setting['html'] = sprintf(
            '<input type="password" class="code widefat" name="%1$s" id="%1$s" value="" autocomplete="new-password" placeholder="%2$s" data-has-key="%3$s">',
            esc_attr( (string) $setting['id'] ),
            esc_attr( $hint !== '' ? $hint : __( 'Paste your API key', 'coinsnap-for-ninja-forms' ) ),
            $hint !== '' ? '1' : '0'
        );
        $setting['value'] = '';
        return $setting;
    }

    public function remember_settings()
    {
        if ( self::$before === null && isset( $_POST['update_ninja_forms_settings'] ) ) {
            self::$before = array(
                'coinsnap_api_key'  => (string) Ninja_Forms()->get_setting( 'coinsnap_api_key' ),
                'btcpay_api_key'    => (string) Ninja_Forms()->get_setting( 'btcpay_api_key' ),
                'btcpay_server_url' => (string) Ninja_Forms()->get_setting( 'btcpay_server_url' ),
            );
        }
    }

    private static function before( string $key ): string
    {
        return ( is_array( self::$before ) && isset( self::$before[ $key ] ) ) ? self::$before[ $key ] : (string) Ninja_Forms()->get_setting( $key );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Ninja Forms verified its settings nonce before these filters run.
    private static function submitted( string $key ): string
    {
        return isset( $_POST['ninja_forms'][ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST['ninja_forms'][ $key ] ) ) ) : '';
    }
    // phpcs:enable

    public function save_coinsnap_api_key( $value )
    {
        $value = trim( (string) $value );
        return ( $value === '' ) ? self::before( 'coinsnap_api_key' ) : $value;
    }

    /**
     * A pasted BTCPay key is saved only if BTCPay says it is limited to the configured store.
     * A new server URL with the key field left empty drops the stored key (guide 6.14).
     */
    public function save_btcpay_api_key( $value )
    {
        $value = trim( (string) $value );
        $oldKey = self::before( 'btcpay_api_key' );
        $urlChanged = rtrim( self::submitted( 'btcpay_server_url' ), '/' ) !== rtrim( self::before( 'btcpay_server_url' ), '/' );
        $notice = new \Coinsnap\Util\Notice();

        if ( $value === '' ) {
            if ( $urlChanged && $oldKey !== '' ) {
                $notice->addNotice( 'warning', __( 'The BTCPay server URL changed, so the stored BTCPay API key was removed. Please connect again.', 'coinsnap-for-ninja-forms' ) );
                return '';
            }
            return $oldKey;
        }

        $storeId = CoinsnapNF_Security::btcpay_verify_key( self::submitted( 'btcpay_server_url' ), $value, self::submitted( 'btcpay_store_id' ) );
        if ( $storeId === null || self::submitted( 'btcpay_store_id' ) === '' ) {
            $notice->addNotice( 'error', __( 'The BTCPay API key was not saved. It must work on this BTCPay server and be limited to the store ID entered, with only the permissions this plugin needs.', 'coinsnap-for-ninja-forms' ) );
            return $urlChanged ? '' : $oldKey;
        }
        return $value;
    }
}
