<?php
namespace SmsEnLinea\ProConnect\Integrations;

use SmsEnLinea\ProConnect\Api_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Woocommerce_Integration {

    public function __construct() {
        add_action( 'woocommerce_order_status_changed', [ $this, 'handle_order_status_change' ], 10, 4 );
    }

    public function handle_order_status_change( $order_id, $from_status, $to_status, $order ) {
        // PROTECCIÓN CONTRA FALLOS: Si $order no es válido, intentamos recuperarlo o salimos.
        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            $order = wc_get_order( $order_id );
        }
        
        if ( ! $order ) {
            return; // Evita el error fatal si no hay orden
        }

        $options = get_option( 'smsenlinea_wc_settings' ); 
        
        if ( empty( $options["msg_{$to_status}"] ) ) {
            return;
        }

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) {
            return;
        }

        // Obtener items de forma segura
        $items_list = [];
        foreach ( $order->get_items() as $item ) {
            $items_list[] = $item->get_quantity() . 'x ' . $item->get_name();
        }
        $items_string = implode( ', ', $items_list );

        // Variables seguras
        $replacements = [
            '{customer_name}'       => $order->get_billing_first_name(),
            '{customer_lastname}'   => $order->get_billing_last_name(),
            '{order_id}'            => $order_id,
            '{order_total}'         => $order->get_formatted_order_total(),
            '{order_status}'        => wc_get_order_status_name( $to_status ),
            '{site_name}'           => get_bloginfo( 'name' ),
            '{billing_address}'     => $order->get_billing_address_1() . ' ' . $order->get_billing_city(),
            '{shipping_method}'     => $order->get_shipping_method(),
            '{payment_method}'      => $order->get_payment_method_title(),
            '{order_date}'          => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
            '{order_items}'         => $items_string,
        ];

        $message_template = $options["msg_{$to_status}"];
        $message_final = str_replace( array_keys( $replacements ), array_values( $replacements ), $message_template );

        $api = Api_Handler::get_instance();
        $api->send_notification( $phone, $message_final, 'whatsapp' );

        if ( 'failed' === $to_status ) {
            $clean_phone = preg_replace( '/[^0-9]/', '', $phone );
            set_transient( 'smsenlinea_recovery_' . $clean_phone, [
                'order_id' => $order_id,
                'name'     => $order->get_billing_first_name(),
                'step'     => 'waiting_confirmation'
            ], 24 * 3600 );
        }
    }
}
