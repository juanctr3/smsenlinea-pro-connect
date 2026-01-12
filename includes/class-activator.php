<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    /**
     * Se ejecuta al activar el plugin.
     * Crea tablas y configura opciones iniciales.
     */
    public static function activate() {
        // 1. Crear Base de Datos
        self::create_tables();

        // 2. Configurar opciones por defecto si no existen
        self::set_default_options();

        // 3. Arrancar el programador de tareas (Cron)
        // Aseguramos que la clase Scheduler esté cargada antes de llamarla
        if ( class_exists( 'SmsEnLinea\ProConnect\Scheduler' ) ) {
            Scheduler::ensure_cron_is_scheduled();
        }
    }

    /**
     * Crea la tabla personalizada para guardar carritos abandonados
     */
    private static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smsenlinea_sessions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            email varchar(100) DEFAULT '' NOT NULL,
            customer_name varchar(100) DEFAULT 'Cliente' NOT NULL,
            cart_total varchar(20) DEFAULT '0' NOT NULL,
            currency varchar(10) DEFAULT 'USD' NOT NULL,
            status varchar(20) DEFAULT 'abandoned' NOT NULL,
            step int(2) DEFAULT 1 NOT NULL,
            cart_data longtext DEFAULT '' NOT NULL,
            checkout_url text DEFAULT '' NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            last_interaction datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Configura las opciones iniciales del plugin
     */
    private static function set_default_options() {
        // Configuración General
        if ( false === get_option( 'smsenlinea_settings' ) ) {
            $default_settings = [
                'api_secret'        => '',
                'sending_mode'      => 'devices', 
                'device_id'         => '',
                'gateway_id'        => '',
                'wa_account_unique' => '',
                'webhook_secret'    => wp_generate_password( 20, false ),
                'abandoned_timeout' => 15 // Minutos por defecto para considerar abandono
            ];
            add_option( 'smsenlinea_settings', $default_settings );
        }

        // Configuración de Estrategia (Mensajes)
        if ( false === get_option( 'smsenlinea_strategy_settings' ) ) {
            $default_strategy = [
                'cart_delay'        => 60,
                'icebreaker_msg'    => "Hola {name}, olvidaste tus productos en {site_name}. Recupéralos aquí: {checkout_url}",
                'keywords_positive' => 'si,claro,ayuda,quiero',
                'keywords_negative' => 'no,baja,stop,gracias',
                'msg_recovery'      => "¡Genial! Aquí tienes tu enlace directo: {checkout_url}",
                'msg_close'         => "Entendido, no te molestaremos más."
            ];
            add_option( 'smsenlinea_strategy_settings', $default_strategy );
        }

        // Configuración de WooCommerce (Estados de Pedido)
        if ( false === get_option( 'smsenlinea_wc_settings' ) ) {
            $default_wc = [
                'msg_pending'    => 'Hola {customer_name}, hemos recibido tu pedido #{order_id}. Total: {order_total}.',
                'msg_processing' => 'Tu pedido #{order_id} está siendo procesado en {site_name}.',
                'msg_completed'  => '¡Buenas noticias! Tu pedido #{order_id} ha sido completado. Gracias por tu compra.',
                'msg_failed'     => 'Hola {customer_name}, hubo un problema con el pago del pedido #{order_id}. Responde SI para ayuda.',
            ];
            add_option( 'smsenlinea_wc_settings', $default_wc );
        }
    }
}
