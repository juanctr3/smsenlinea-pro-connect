<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * El Vigilante (Scheduler)
 * Ejecuta tareas programadas para detectar carritos abandonados.
 */
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

    /**
     * Lógica principal del Cron Job
     */
    public function check_abandoned_carts() {
        // 1. Obtener configuración
        $strategy = get_option( 'smsenlinea_strategy_settings' );
        $delay_minutes = isset( $strategy['cart_delay'] ) ? intval( $strategy['cart_delay'] ) : 60;

        if ( $delay_minutes < 1 ) $delay_minutes = 60; // Mínimo de seguridad

        // Calcular la ventana de tiempo (Pedidos creados ANTES de X minutos, pero NO más viejos de 24 horas)
        $time_threshold = strtotime( "-{$delay_minutes} minutes" );
        $safety_threshold = strtotime( "-24 hours" );

        // 2. Buscar pedidos pendientes en WooCommerce
        // Buscamos 'pending' (Pendiente de pago) y 'on-hold' (En espera)
        $args = [
            'status' => [ 'pending' ], 
            'limit'  => 10, // Procesamos de 10 en 10 para no saturar el servidor
            'date_created' => '>' . $safety_threshold, 
            'type' => 'shop_order',
        ];

        $orders = wc_get_orders( $args );

        foreach ( $orders as $order ) {
            $created_timestamp = $order->get_date_created()->getTimestamp();

            // Si el pedido es más reciente que el tiempo de espera, lo ignoramos por ahora
            if ( $created_timestamp > $time_threshold ) {
                continue;
            }

            $this->process_single_cart( $order, $strategy );
        }
    }

    /**
     * Procesa un pedido individual
     */
    private function process_single_cart( $order, $strategy ) {
        $phone = $order->get_billing_phone();
        
        if ( empty( $phone ) ) return;

        $flow_engine = Flow_Engine::get_instance();
        
        // Preparar datos para el cerebro
        $context = [
            'order_id'      => $order->get_id(),
            'customer_name' => $order->get_billing_first_name(),
            'checkout_url'  => $order->get_checkout_payment_url(),
            'total'         => $order->get_formatted_order_total(),
        ];

        // 3. Intentar iniciar el flujo en la base de datos
        // Si start_flow devuelve false, es que ya existe una conversación activa o terminada, así que no spameamos.
        $session_id = $flow_engine->start_flow( $phone, 'abandoned_cart', $context );

        if ( $session_id ) {
            // 4. Si se creó la sesión, enviamos el ROMPEHIELOS
            $api = Api_Handler::get_instance();
            
            $message_tpl = $strategy['icebreaker_msg'] ?? 'Hola {customer_name}, ¿necesitas ayuda con tu pedido?';
            $message_final = str_replace( 
                [ '{customer_name}', '{site_name}' ], 
                [ $context['customer_name'], get_bloginfo('name') ], 
                $message_tpl 
            );

            $api->send_notification( $phone, $message_final, 'whatsapp' );
            
            // Opcional: Agregar nota al pedido
            $order->add_order_note( '[SmsEnLinea] Mensaje rompehielos de recuperación enviado.' );
        }
    }
}
