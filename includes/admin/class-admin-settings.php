<?php
namespace SmsEnLinea\ProConnect\Admin;

use SmsEnLinea\ProConnect\Api_Handler;
use SmsEnLinea\ProConnect\Scheduler;
use SmsEnLinea\ProConnect\Flow_Engine; // Importante: Importar el Motor
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
        add_action( 'wp_ajax_smsenlinea_check_connection', [ $this, 'ajax_check_connection' ] );
        add_action( 'wp_ajax_smsenlinea_manual_icebreaker', [ $this, 'ajax_manual_icebreaker' ] );
        
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
    /**
     * AJAX Handler: Recibe la petición del botón "Probar Conexión",
     * verifica credenciales y devuelve el plan actual.
     */
    public function ajax_check_connection() {
        // 1. Seguridad: Verificar permisos de administrador
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        // 2. Obtener el secreto enviado desde el formulario (sin guardar aún)
        $secret = isset( $_POST['secret'] ) ? sanitize_text_field( $_POST['secret'] ) : '';

        if ( empty( $secret ) ) {
            wp_send_json_error( 'Por favor ingresa un API Secret.' );
        }

        // 3. Llamar a la lógica que creamos en el paso anterior
        $api = \SmsEnLinea\ProConnect\Api_Handler::get_instance();
        $result = $api->check_connection_and_status( $secret );

        // 4. Devolver respuesta JSON al navegador
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        } else {
            // Si es exitoso, enviamos los datos del plan
            wp_send_json_success( $result );
        }
    }
    /**
     * AJAX: Envío manual del mensaje rompehielos a un carrito específico
     */
    public function ajax_manual_icebreaker() {
        // 1. Verificar permisos y seguridad
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }
        
        // Verificar nonce (seguridad contra CSRF)
        check_ajax_referer( 'smsenlinea_admin_nonce', 'nonce' );

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( 'ID de sesión inválido' );
        }

        // 2. Obtener la sesión de la base de datos
        global $wpdb;
        $table_name = $wpdb->prefix . 'smsenlinea_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );

        if ( ! $session ) {
            wp_send_json_error( 'Carrito no encontrado' );
        }

        // 3. Invocar al Motor de Flujos (Flow Engine)
        // Usamos el mismo motor que usa el automático para asegurar consistencia
        $engine = Flow_Engine::get_instance();
        
        // run_recovery_sequence devuelve true si envió, false si falló
        $result = $engine->run_recovery_sequence( $session );

        if ( $result ) {
            wp_send_json_success( '¡Mensaje Rompehielos enviado con éxito!' );
        } else {
            wp_send_json_error( 'Error al enviar. Revisa los logs o la configuración de API.' );
        }
    }
}


