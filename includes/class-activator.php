<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function activate() {
        // 1. Configurar opciones por defecto si no existen
        if ( false === get_option( 'smsenlinea_settings' ) ) {
            $default_settings = [
                'api_secret'        => '',
                'sending_mode'      => 'devices', // 'devices' o 'credits'
                'device_id'         => '',
                'gateway_id'        => '',
                'wa_account_unique' => '',
                'webhook_secret'    => wp_generate_password( 20, false ), // Generar secreto seguro auto
            ];
            add_option( 'smsenlinea_settings', $default_settings );
        }

        // 2. Configurar mensajes por defecto de WooCommerce
        if ( false === get_option( 'smsenlinea_wc_settings' ) ) {
            $default_wc = [
                'msg_pending'    => 'Hola {customer_name}, hemos recibido tu pedido #{order_id}. Total: {order_total}.',
                'msg_processing' => 'Tu pedido #{order_id} está siendo procesado en {site_name}.',
                'msg_completed'  => '¡Buenas noticias! Tu pedido #{order_id} ha sido completado. Gracias por tu compra.',
                'msg_failed'     => 'Hola {customer_name}, notamos un problema con el pago de tu pedido #{order_id}. Responde SI para recibir ayuda de un humano.',
            ];
            add_option( 'smsenlinea_wc_settings', $default_wc );
        }

        // 3. Crear tabla personalizada para logs si fuera necesario (Opcional para V2)
        // self::create_log_table();
    }
}