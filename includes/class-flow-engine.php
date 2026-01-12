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
        $options = get_option( 'smsenlinea_settings' );

        // 1. Obtener la Plantilla del Mensaje
        // Si el usuario no configuró nada en el admin, usamos un mensaje por defecto seguro.
        $message_template = isset( $options['message_template_1'] ) && ! empty( $options['message_template_1'] )
            ? $options['message_template_1']
            : "Hola {name}, notamos que no completaste tu compra. Puedes retomarla aquí: {checkout_url}";

        // 2. Preparar Variables para Personalización
        $full_name = ! empty( $session->customer_name ) ? $session->customer_name : 'Cliente';
        // Extraer solo el primer nombre para ser más cercano (ej: "Juan" en vez de "Juan Perez")
        $name_parts = explode( ' ', trim( $full_name ) );
        $first_name = $name_parts[0];

        // Asegurar URL de recuperación
        $checkout_url = ! empty( $session->checkout_url ) ? $session->checkout_url : wc_get_checkout_url();

        // 3. Reemplazar "Etiquetas" (Merge Tags) en el mensaje
        $message = str_replace( '{name}', $first_name, $message_template );
        $message = str_replace( '{fullname}', $full_name, $message );
        $message = str_replace( '{checkout_url}', $checkout_url, $message );
        $message = str_replace( '{total}', $session->cart_total . ' ' . $session->currency, $message );

        // 4. Enviar Mensaje (Usando WhatsApp por defecto)
        // Nota: Esto depende de que Api_Handler tenga el método send_notification funcionando.
        $response = $api->send_notification( $session->phone, $message, 'whatsapp' );

        $current_time = current_time( 'mysql' );

        // 5. Actualizar Base de Datos según el resultado
        if ( is_wp_error( $response ) ) {
            // Si falla, marcamos error para no reintentar infinitamente en el mismo ciclo
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
            // ¡Éxito!
            // Cambiamos el estado a 'contacted' para cerrar el ciclo de "abandono"
            $wpdb->update(
                $table_name,
                [ 
                    'status' => 'contacted', 
                    'step' => 1, // Marcamos que ya pasó el paso 1
                    'last_interaction' => $current_time 
                ],
                [ 'id' => $session->id ]
            );
            return true;
        }
    }
}
