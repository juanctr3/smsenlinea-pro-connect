<?php
namespace SmsEnLinea\ProConnect\Admin;

use SmsEnLinea\ProConnect\Api_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Settings {

    private $options_slug = 'smsenlinea_settings';
    private $wc_options_slug = 'smsenlinea_wc_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        
        // AJAX para Test de Conexión
        add_action( 'wp_ajax_smsenlinea_test_connection', [ $this, 'ajax_test_connection' ] );
    }

    public function add_plugin_page() {
        add_menu_page(
            __( 'SmsEnLinea Pro', 'smsenlinea-pro' ),
            'SmsEnLinea',
            'manage_options',
            'smsenlinea-pro',
            [ $this, 'create_admin_page' ],
            'dashicons-smartphone', 
            55
        );
    }

    public function create_admin_page() {
        $settings = get_option( $this->options_slug );
        $wc_settings = get_option( $this->wc_options_slug );
        
        require_once SMSENLINEA_PATH . 'includes/admin/views/settings-page.php';
    }

    public function enqueue_scripts( $hook ) {
        if ( 'toplevel_page_smsenlinea-pro' !== $hook ) {
            return;
        }
        // Cargamos script para el botón de Test y Emojis (usando WP Color Picker como base o JS simple)
        wp_enqueue_script( 'jquery' );
    }

    public function ajax_test_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes.' );
        }

        $api = Api_Handler::get_instance();
        $result = $api->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( $result['msg'] );
        }
    }

    public function page_init() {
        register_setting( 'smsenlinea_option_group', $this->options_slug, [ $this, 'sanitize_settings' ] );
        register_setting( 'smsenlinea_wc_group', $this->wc_options_slug, [ $this, 'sanitize_text_array' ] );
    }

    public function sanitize_settings( $input ) {
        $new_input = [];
        if( isset( $input['api_secret'] ) ) $new_input['api_secret'] = sanitize_text_field( $input['api_secret'] );
        if( isset( $input['sending_mode'] ) ) $new_input['sending_mode'] = sanitize_text_field( $input['sending_mode'] );
        if( isset( $input['device_id'] ) ) $new_input['device_id'] = sanitize_text_field( $input['device_id'] );
        if( isset( $input['gateway_id'] ) ) $new_input['gateway_id'] = sanitize_text_field( $input['gateway_id'] );
        if( isset( $input['wa_account_unique'] ) ) $new_input['wa_account_unique'] = sanitize_text_field( $input['wa_account_unique'] );
        if( isset( $input['webhook_secret'] ) ) $new_input['webhook_secret'] = sanitize_text_field( $input['webhook_secret'] );
        
        return $new_input;
    }

    public function sanitize_text_array( $input ) {
        // Permitimos emojis y multilínea
        return array_map( 'sanitize_textarea_field', $input );
    }
}
