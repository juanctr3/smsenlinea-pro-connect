<?php
namespace SmsEnLinea\ProConnect\Admin;

use SmsEnLinea\ProConnect\Api_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Settings {

    private $options_slug = 'smsenlinea_settings';
    private $wc_options_slug = 'smsenlinea_wc_settings';
    private $strategy_slug = 'smsenlinea_strategy_settings'; // Nuevo grupo

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_smsenlinea_test_connection', [ $this, 'ajax_test_connection' ] );
    }

    public function add_plugin_page() {
        add_menu_page(
            'SmsEnLinea Pro',
            'SmsEnLinea',
            'manage_options',
            'smsenlinea-pro',
            [ $this, 'create_admin_page' ],
            'dashicons-smartphone', 
            55
        );
    }

    public function create_admin_page() {
        $view_file = SMSENLINEA_PATH . 'includes/admin/views/settings-page.php';
        if ( file_exists( $view_file ) ) {
            require_once $view_file;
        }
    }

    public function enqueue_scripts( $hook ) {
        if ( 'toplevel_page_smsenlinea-pro' !== $hook ) {
            return;
        }
        wp_enqueue_script( 'jquery' );
        // Aquí podríamos cargar un CSS externo, pero lo haremos inline en la vista para facilitar la instalación
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
        // Registro de la nueva sección de estrategia
        register_setting( 'smsenlinea_strategy_group', $this->strategy_slug, [ $this, 'sanitize_text_array' ] );
    }

    public function sanitize_settings( $input ) {
        $new_input = [];
        if( is_array($input) ) {
            foreach($input as $key => $val) {
                $new_input[$key] = sanitize_text_field($val);
            }
        }
        return $new_input;
    }

    public function sanitize_text_array( $input ) {
        if ( ! is_array( $input ) ) { return []; }
        // Permitimos HTML básico y saltos de línea para los mensajes
        return array_map( 'wp_kses_post', $input );
    }
}
