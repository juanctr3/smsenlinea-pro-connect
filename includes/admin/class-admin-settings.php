<?php
namespace SmsEnLinea\ProConnect\Admin;

use SmsEnLinea\ProConnect\Api_Handler;
use SmsEnLinea\ProConnect\Scheduler; // Importante: Importar el Scheduler

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Settings {

    private $options_slug = 'smsenlinea_settings';
    private $wc_options_slug = 'smsenlinea_wc_settings';
    private $strategy_slug = 'smsenlinea_strategy_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
        add_action( 'admin_init', [ $this, 'page_init' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        
        // AJAX Hooks
        add_action( 'wp_ajax_smsenlinea_test_connection', [ $this, 'ajax_test_connection' ] );
        
        // NUEVO: Hook para disparo manual de recuperación
        add_action( 'wp_ajax_smsenlinea_trigger_cron', [ $this, 'ajax_trigger_cron' ] );
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
        // Pasamos $wpdb a la vista para los reportes
        global $wpdb;
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
    }

    public function ajax_test_connection() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos' );

        $api = Api_Handler::get_instance();
        $result = $api->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( $result['msg'] );
        }
    }

    // NUEVO: Función para ejecutar el vigilante manualmente
    public function ajax_trigger_cron() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Sin permisos' );

        // Llamamos al Vigilante directamente
        $scheduler = Scheduler::get_instance();
        $scheduler->check_abandoned_carts();

        wp_send_json_success( 'Revisión de carritos ejecutada correctamente.' );
    }

    public function page_init() {
        register_setting( 'smsenlinea_option_group', $this->options_slug, [ $this, 'sanitize_settings' ] );
        register_setting( 'smsenlinea_wc_group', $this->wc_options_slug, [ $this, 'sanitize_text_array' ] );
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
        return array_map( 'wp_kses_post', $input );
    }
}
