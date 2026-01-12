<?php
namespace SmsEnLinea\ProConnect\Admin;

/**
 * Class Admin_Settings
 * Maneja la creación del menú, el registro de opciones y las peticiones AJAX del admin.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Settings {

    private $options;

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        
        // Hooks AJAX
        // 1. Probar conexión API
        add_action( 'wp_ajax_smsenlinea_check_connection', [ $this, 'ajax_check_connection' ] );
        
        // 2. Disparador Manual de Cron (Para pruebas generales)
        add_action( 'wp_ajax_smsenlinea_trigger_cron', [ 'SmsEnLinea\ProConnect\Scheduler', 'ajax_manual_trigger' ] );

        // 3. [NUEVO] Recuperación Manual de un Carrito Específico
        add_action( 'wp_ajax_smsenlinea_manual_recovery', [ $this, 'ajax_manual_recovery' ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            'SmsEnLinea Pro', 
            'SmsEnLinea Pro', 
            'manage_options', 
            'smsenlinea-pro', 
            [ $this, 'create_admin_page' ], 
            'dashicons-whatsapp', 
            56
        );
    }

    public function register_settings() {
        register_setting( 'smsenlinea_settings_group', 'smsenlinea_settings' );
        register_setting( 'smsenlinea_settings_group', 'smsenlinea_strategy_settings' );
        register_setting( 'smsenlinea_settings_group', 'smsenlinea_wc_settings' );
    }

    public function create_admin_page() {
        $this->options = get_option( 'smsenlinea_settings' );
        // Cargamos la vista (el HTML)
        require_once SMSENLINEA_PATH . 'includes/admin/views/settings-page.php';
    }

    /**
     * AJAX: Prueba de conexión y obtención de plan
     */
    public function ajax_check_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        $secret = isset( $_POST['secret'] ) ? sanitize_text_field( $_POST['secret'] ) : '';

        if ( empty( $secret ) ) {
            wp_send_json_error( 'Por favor ingresa un API Secret.' );
        }

        $api = \SmsEnLinea\ProConnect\Api_Handler::get_instance();
        $result = $api->check_connection_and_status( $secret );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            wp_send_json_success( $result );
        }
    }

    /**
     * AJAX [NUEVO]: Fuerza el envío del mensaje de recuperación para un carrito específico
     */
    public function ajax_manual_recovery() {
        // 1. Seguridad
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Acceso denegado.' );
        }

        check_ajax_referer( 'smsenlinea_manual_action', 'nonce' );

        // 2. Obtener ID de sesión
        $session_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( $session_id <= 0 ) {
            wp_send_json_error( 'ID de sesión inválido.' );
        }

        // 3. Obtener datos de la BD
        global $wpdb;
        $table_name = $wpdb->prefix . 'smsenlinea_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $session_id ) );

        if ( ! $session ) {
            wp_send_json_error( 'El carrito no existe.' );
        }

        // 4. Ejecutar el Motor de Flujo
        $engine = \SmsEnLinea\ProConnect\Flow_Engine::get_instance();
        
        // Forzamos el envío aunque el tiempo no haya pasado (es manual)
        $result = $engine->run_recovery_sequence( $session );

        if ( $result ) {
            wp_send_json_success( 'Mensaje enviado correctamente.' );
        } else {
            wp_send_json_error( 'Falló el envío. Revisa los logs o credenciales.' );
        }
    }
}
