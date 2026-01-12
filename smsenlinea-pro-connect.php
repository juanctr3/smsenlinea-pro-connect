<?php
/**
 * Plugin Name: SmsEnLinea Pro Connect
 * Plugin URI:  https://smsenlinea.com
 * Description: Integración profesional para notificaciones WhatsApp/SMS y Marketing Conversacional con recuperación de carritos.
 * Version:     1.0.0
 * Author:      SmsEnLinea
 * Author URI:  https://smsenlinea.com
 * Text Domain: smsenlinea-pro
 * Domain Path: /languages
 */

namespace SmsEnLinea\ProConnect;

// Prevención de acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

// Definición de Constantes
define( 'SMSENLINEA_VERSION', '1.0.0' );
define( 'SMSENLINEA_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMSENLINEA_URL', plugin_dir_url( __FILE__ ) );
define( 'SMSENLINEA_API_BASE', 'https://whatsapp.smsenlinea.com/api' );

// 1. HOOK DE ACTIVACIÓN (Vital para crear la tabla de base de datos)
register_activation_hook( __FILE__, [ 'SmsEnLinea\\ProConnect\\Activator', 'activate' ] );

// 2. HOOK DE DESACTIVACIÓN (Nuevo: Limpia el Cron Job al quitar el plugin)
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'smsenlinea_recover_carts_event' );
});

// 3. AUTOLOADER ROBUSTO
spl_autoload_register( function ( $class ) {
    $prefix = 'SmsEnLinea\\ProConnect\\';
    $base_dir = SMSENLINEA_PATH . 'includes/';
    $len = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $parts = explode( '\\', $relative_class );
    $class_name = array_pop( $parts );
    
    // Convertir nombre de clase a formato de archivo (class-nombre-clase.php)
    $file_name = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

    $sub_dir = '';
    if ( ! empty( $parts ) ) {
        $sub_dir = str_replace( '_', '-', strtolower( implode( '/', $parts ) ) ) . '/';
    }

    $file = $base_dir . $sub_dir . $file_name;

    if ( file_exists( $file ) ) {
        require $file;
    }
});

/**
 * Clase principal que inicializa todos los módulos
 */
class Main {
    
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        // Inicializar integración con API
        Api_Handler::get_instance();
        
        // Inicializar Webhook Listener
        Webhook_Handler::get_instance();
        
        // NUEVO: Inicializar el Vigilante (Scheduler)
        // Esto es obligatorio para que el Cron Job funcione
        Scheduler::get_instance();

        // Inicializar Integraciones
        if ( class_exists( 'WooCommerce' ) ) {
            new Integrations\Woocommerce_Integration();
        }
        
        if ( defined( 'WPCF7_VERSION' ) ) {
             new Integrations\Contact_Form_7();
        }
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        
        // Inicializar el Panel de Admin
        if ( is_admin() ) {
            new Admin\Admin_Settings();
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'smsenlinea-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}

// Arrancar el plugin
Main::get_instance();
