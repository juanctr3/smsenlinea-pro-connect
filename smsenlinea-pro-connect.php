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
define( 'SMSENLINEA_API_BASE', 'https://whatsapp.smsenlinea.com/api' ); // [cite: 92]

// Autoloader simple para clases
spl_autoload_register( function ( $class ) {
    $prefix = 'SmsEnLinea\\ProConnect\\';
    $base_dir = SMSENLINEA_PATH . 'includes/';
    $len = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    // Mapeo simple de namespace a estructura de archivos
    $file = $base_dir . 'class-' . str_replace( '_', '-', strtolower( str_replace( '\\', '/', $relative_class ) ) ) . '.php';
    
    // Ajuste para subcarpetas 'integrations' si es necesario, o mantener estructura plana en includes/
    if ( strpos( $file, 'integrations/' ) === false && strpos( $relative_class, 'Integrations' ) !== false ) {
         $file = $base_dir . 'integrations/class-' . str_replace( '_', '-', strtolower( basename( str_replace( '\\', '/', $relative_class ) ) ) ) . '.php';
    }

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

        // Inicializar Integraciones si los plugins existen
        if ( class_exists( 'WooCommerce' ) ) {
            new Integrations\Woocommerce_Integration();
        }
    }

    private function init_hooks() {
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        // Aquí se instanciaría la clase Admin_Settings en el hook 'admin_menu'
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'smsenlinea-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
}

// Arrancar el plugin
Main::get_instance();