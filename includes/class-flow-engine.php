<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Flow_Engine
 * El motor que orquesta el envío de mensajes de recuperación.
 */
class Flow_Engine {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Ejecuta la secuencia de recuperación.
     * Es llamado por el Scheduler cuando encuentra un carrito abandonado.
     * * @param object $session Fila de la base de datos con los datos del carrito.
     * @return bool True si se procesó correctamente.
     */
    public function run_recovery_sequence( $session ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smsenlinea_sessions';
        $api = Api_Handler::get_instance();
        
        // Obtenemos configuración de estrategia (mensajes personalizados)
        $strategy_opts = get_option( 'smsenlinea_strategy_settings' );

        // 1. Definir el Mensaje
        // Usamos el configurado en 'Estrategia' o uno por defecto si está vacío.
        $message_template = ! empty( $strategy_opts['icebreaker_msg'] ) 
            ? $strategy_opts['icebreaker_msg'] 
            : "Hola {name}, notamos que no completaste tu compra en {site_name}. Puedes retomarla aquí: {checkout_url}";

        // 2. Preparar Variables para Personalización
        $full_name  = ! empty( $session->customer_name ) ? $session->customer_name : 'Cliente';
        $name_parts = explode( ' ', trim( $full_name ) );
        $first_name = $name_parts[0]; // Usamos solo el primer nombre para ser más cercanos

        // URL de recuperación (si la guardamos en la sesión, la usamos; si no, la genérica)
        $checkout_url = ! empty( $session->checkout_url ) ? $session->checkout_url : wc_get_checkout_url();

        // 3. Reemplazar "Etiquetas" (Merge Tags)
        $replacements = [
            '{name}'         => $first_name,
            '{fullname}'     => $full_name,
            '{checkout_url}' => $checkout_url,
            '{total}'        => $session->cart_total . ' ' . $session->currency,
            '{site_name}'    => get_bloginfo( 'name' ),
        ];

        $message = str_replace( array_keys( $replacements ), array_values( $replacements ), $message_template );

        // 4. Enviar Mensaje
        // Por defecto usamos WhatsApp. En V2 podríamos añadir lógica para probar SMS si no tiene WP.
        $response = $api->send_notification( $session->phone, $message, 'whatsapp' );

        $current_time = current_time( 'mysql' );

        // 5. Actualizar Base de Datos según el resultado
        // Api_Handler devuelve 'false' o WP_Error si falla
        if ( $response === false || is_wp_error( $response ) ) {
            
            // Falló el envío: Marcamos error para revisarlo, pero actualizamos fecha para no reintentar inmediatamente
            $wpdb->update(
                $table_name,
                [ 
                    'status' => 'failed_send', 
                    'last_interaction' => $current_time 
                ],
                [ 'id' => $session->id ]
            );
            return false;

        } else {
            
            // ¡Éxito! Mensaje enviado.
            // Cambiamos estado a 'contacted' (esperando respuesta del cliente)
            $wpdb->update(
                $table_name,
                [ 
                    'status' => 'contacted', 
                    'step'   => 1, // Indica que ya enviamos el primer mensaje
                    'last_interaction' => $current_time 
                ],
                [ 'id' => $session->id ]
            );
            return true;
        }
    }
}
