<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Scheduler {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'smsenlinea_recover_carts_event', [ $this, 'check_abandoned_carts' ] );
    }

    public function check_abandoned_carts() {
        $strategy = get_option( 'smsenlinea_strategy_settings' );
        $delay_minutes = isset( $strategy['cart_delay'] ) ? intval( $strategy['cart_delay'] ) : 60;
        if ( $delay_minutes < 1 ) $delay_minutes = 60;

        // Buscar pedidos creados entre hace 24h y hace X minutos
        $time_threshold = strtotime( "-{$delay_minutes} minutes" );
        $safety_threshold = strtotime( "-24 hours" );

        $args = [
            'status' => [ 'pending', 'failed' ], // Incluimos fallidos también
            'limit'  => 10,
            'date_created' => '>' . $safety_threshold, 
            'type' => 'shop_order',
        ];

        $orders = wc_get_orders( $args );

        foreach ( $orders as $order ) {
            $created_timestamp = $order->get_date_created()->getTimestamp();
            
            // Si es muy reciente según la configuración, esperar
            if ( $created_timestamp > $time_threshold ) {
                continue;
            }

            $this->process_single_cart( $order, $strategy );
        }
    }

    // Hacemos este método público para poder llamarlo manualmente desde el botón
    public function process_single_cart( $order, $strategy = null ) {
        if ( ! $strategy ) {
            $strategy = get_option( 'smsenlinea_strategy_settings' );
        }

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) return false;

        $flow_engine = Flow_Engine::get_instance();
        
        // --- RECOPILACIÓN EXTENDIDA DE DATOS ---
        $items_list = [];
        foreach ( $order->get_items() as $item ) {
            $items_list[] = $item->get_quantity() . 'x ' . $item->get_name();
        }
        $items_string = implode( ', ', $items_list );

        $context = [
            'order_id'          => $order->get_id(),
            'customer_name'     => $order->get_billing_first_name(),
            'customer_lastname' => $order->get_billing_last_name(),
            'checkout_url'      => $order->get_checkout_payment_url(),
            'total'             => $order->get_formatted_order_total(),
            'billing_address'   => $order->get_billing_address_1() . ' ' . $order->get_billing_city(),
            'order_items'       => $items_string,
            'order_date'        => $order->get_date_created()->date_i18n( get_option( 'date_format' ) ),
        ];
        // ---------------------------------------

        // Intentar iniciar flujo
        $session_id = $flow_engine->start_flow( $phone, 'abandoned_cart', $context );

        if ( $session_id ) {
            $api = Api_Handler::get_instance();
            
            // Reemplazo básico para el rompehielos (El resto se hace en Flow_Engine)
            $message_tpl = $strategy['icebreaker_msg'] ?? 'Hola {customer_name}, ¿necesitas ayuda con tu pedido?';
            
            // Usamos el motor para reemplazar variables incluso en el rompehielos
            $message_final = $flow_engine->replace_variables_public( $message_tpl, $context );

            $sent = $api->send_notification( $phone, $message_final, 'whatsapp' );
            
            if ( $sent ) {
                $order->add_order_note( '[SmsEnLinea] 🧊 Mensaje rompehielos enviado manualmente o por Cron.' );
                return true;
            }
        }
        return false;
    }
}
