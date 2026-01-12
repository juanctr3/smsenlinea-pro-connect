<?php
namespace SmsEnLinea\ProConnect\Integrations;

use SmsEnLinea\ProConnect\Api_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Woocommerce_Integration {

    public function __construct() {
        // Hook para cambios de estado en WooCommerce
        add_action( 'woocommerce_order_status_changed', [ $this, 'handle_order_status_change' ], 10, 4 );
    }

    public function handle_order_status_change( $order_id, $from_status, $to_status, $order ) {
        $options = get_option( 'smsenlinea_wc_settings' ); // Obtener templates de mensajes
        
        // Verificar si hay mensaje configurado para este estado
        if ( empty( $options["msg_{$to_status}"] ) ) {
            return;
        }

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) {
            return;
        }

        // Preparar variables para reemplazo
        $replacements = [
            '{customer_name}' => $order->get_billing_first_name(),
            '{order_id}'      => $order_id,
            '{order_total}'   => $order->get_total(),
            '{site_name}'     => get_bloginfo( 'name' ),
        ];

        $message_template = $options["msg_{$to_status}"];
        $message_final    = str_replace( array_keys( $replacements ), array_values( $replacements ), $message_template );

        // Enviar la notificación
        $api = Api_Handler::get_instance();
        // Por defecto usamos WhatsApp, configurable en settings
        $api->send_notification( $phone, $message_final, 'whatsapp' );

        // LÓGICA DE RECUPERACIÓN "ESTRELLA"
        // Si el estado es fallido, guardamos un "Transient" para esperar respuesta del cliente
        if ( 'failed' === $to_status ) {
            // Clave: smsenlinea_recovery_[telefono_limpio]
            // Valor: array con datos del pedido
            // Expiración: 24 horas
            $clean_phone = preg_replace( '/[^0-9]/', '', $phone );
            set_transient( 'smsenlinea_recovery_' . $clean_phone, [
                'order_id' => $order_id,
                'name'     => $order->get_billing_first_name(),
                'step'     => 'waiting_confirmation'
            ], 24 * HOUR_IN_SECONDS );
        }
    }
}