<?php
namespace SmsEnLinea\ProConnect\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Settings {

    private $options_slug = 'smsenlinea_settings';
    private $wc_options_slug = 'smsenlinea_wc_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
    }

    public function add_plugin_page() {
        add_menu_page(
            __( 'SmsEnLinea Pro', 'smsenlinea-pro' ),
            'SmsEnLinea',
            'manage_options',
            'smsenlinea-pro',
            [ $this, 'create_admin_page' ],
            'dashicons-smartphone', // Icono nativo
            55
        );
    }

    public function create_admin_page() {
        // Cargar datos
        $settings = get_option( $this->options_slug );
        $wc_settings = get_option( $this->wc_options_slug );
        
        // Cargar vista HTML separada para limpieza
        require_once SMSENLINEA_PATH . 'includes/admin/views/settings-page.php';
    }

    public function enqueue_styles( $hook ) {
        if ( 'toplevel_page_smsenlinea-pro' !== $hook ) {
            return;
        }
        // Aquí podrías cargar CSS real, por ahora usaremos estilos inline en la vista para simplificar
        // wp_enqueue_style( 'smsenlinea-admin', SMSENLINEA_URL . 'assets/css/admin.css', [], SMSENLINEA_VERSION );
    }

    public function page_init() {
        // Registro de grupo de opciones generales
        register_setting( 'smsenlinea_option_group', $this->options_slug, [ $this, 'sanitize_settings' ] );
        
        // Registro de grupo de opciones WooCommerce
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
        return array_map( 'sanitize_textarea_field', $input );
    }
}