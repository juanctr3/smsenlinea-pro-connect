<?php
/**
 * Plugin Name: SmsEnLinea Pro Connect
 * Plugin URI:  https://smsenlinea.com
 * Description: Integración profesional para notificaciones WhatsApp/SMS y Marketing Conversacional con recuperación de carritos abandonados.
 * Version:     2.0.0
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
define( 'SMSENLINEA_VERSION', '2.0.0' );
define( 'SMSENLINEA_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMSENLINEA_URL', plugin_dir_url( __FILE__ ) );
define( 'SMSENLINEA_API_BASE', 'https://whatsapp.smsenlinea.com/api' );

// Autoloader simple para clases
spl_autoload_register( function ( $class ) {
    $prefix = 'SmsEnLinea\\ProConnect\\';
    $base_dir = SMSENLINEA_PATH . 'includes/';
    $len = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    
    // Mapeo de namespace a estructura de archivos
    // Ejemplo: Admin\Admin_Settings -> includes/admin/class-admin-settings.php
    $file_parts = explode( '\\', $relative_class );
    $class_name = array_pop( $file_parts );
    $folder_path = ! empty( $file_parts ) ? implode( '/', array_map( 'strtolower', $file_parts ) ) . '/' : '';
    
    // Normalizar nombre de archivo (PascalCase a kebab-case)
    $file_name = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
    
    $file = $base_dir . $folder_path . $file_name;

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
        // 1. Núcleo de Comunicaciones
        Api_Handler::get_instance();
        Webhook_Handler::get_instance();
        
        // 2. Motor de Recuperación (El Reloj y el Cerebro) [Nuevo V2]
        Scheduler::get_instance();
        Flow_Engine::get_instance();

        // 3. Panel de Administración
        if ( is_admin() ) {
            new Admin\Admin_Settings();
        }

        // 4. Integraciones (Solo si los plugins están activos)
        if ( class_exists( 'WooCommerce' ) ) {
            // Nota: El nombre de la clase debe coincidir con el archivo class-woocommerce-integration.php
            // El autoloader buscará Integrations/Woocommerce_Integration
            Integrations\WooCommerce_Integration::get_instance();
        }

        if ( class_exists( 'GFForms' ) ) {
            new Integrations\Gravity_Forms();
        }
        
        // CF7 integración
        if ( defined( 'WPCF7_VERSION' ) ) {
            new Integrations\Contact_Form_7();
        }
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'smsenlinea-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}

// Registrar Hook de Activación para crear tablas DB
register_activation_hook( __FILE__, [ 'SmsEnLinea\ProConnect\Activator', 'activate' ] );

// Arrancar el plugin
Main::get_instance();
