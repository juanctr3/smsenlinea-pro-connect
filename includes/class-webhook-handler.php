<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Webhook_Handler {

    private static $instance = null;
    private $webhook_secret;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $options = get_option( 'smsenlinea_settings' );
        $this->webhook_secret = $options['webhook_secret'] ?? '';
        
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Endpoint: https://tusitio.com/wp-json/smsenlinea/v1/webhook
        register_rest_route( 'smsenlinea/v1', '/webhook', [
            'methods'  => 'POST',
            'callback' => [ $this, 'process_webhook' ],
            'permission_callback' => '__return_true', // Validamos el secreto manualmente dentro
        ] );
    }

    /**
     * Procesa la carga útil (Payload) del Webhook
     [cite_start]* [cite: 16, 29]
     */
    public function process_webhook( \WP_REST_Request $request ) {
        // Obtenemos parámetros tanto del cuerpo (POST) como de la URL (GET)
        $params = $request->get_params();

        // 1. Validación de Seguridad Flexible
        // Buscamos el secreto en el payload O en la URL (query params)
        $incoming_secret = $params['secret'] ?? ( $_GET['secret'] ?? '' );

        if ( empty( $this->webhook_secret ) || $incoming_secret !== $this->webhook_secret ) {
            return new \WP_Error( 'forbidden', 'Invalid or Missing Secret', [ 'status' => 403 ] );
        }

        [cite_start]// 2. Extraer datos según tipo [cite: 30]
        $type = $params['type'] ?? '';
        $data = $params['data'] ?? [];

        [cite_start]// Solo nos interesan mensajes entrantes (SMS o WhatsApp) [cite: 6]
        if ( ! in_array( $type, [ 'sms', 'whatsapp' ] ) ) {
            return rest_ensure_response( [ 'success' => true, 'msg' => 'Ignored type' ] );
        }

        [cite_start]// Normalizar datos (WhatsApp usa 'wid' o 'phone', SMS usa 'phone') [cite: 40, 49]
        $incoming_phone = isset( $data['wid'] ) ? $data['wid'] : ( $data['phone'] ?? '' );
        $incoming_msg   = trim( $data['message'] ?? '' ); [cite_start]// [cite: 41, 51]

        if ( empty( $incoming_phone ) || empty( $incoming_msg ) ) {
            return rest_ensure_response( [ 'success' => false, 'msg' => 'No phone/msg' ] );
        }

        // 3. Lógica de Recuperación Condicional (SI/NO)
        $this->handle_conversational_logic( $incoming_phone, $incoming_msg );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Lógica "Estrella" de recuperación de ventas
     */
    private function handle_conversational_logic( $phone, $message ) {
        // Limpiar teléfono para coincidir con la clave del transient
        $clean_phone = preg_replace( '/[^0-9]/', '', $phone );
        
        // Buscar si existe un proceso de recuperación activo para este teléfono
        $recovery_data = get_transient( 'smsenlinea_recovery_' . $clean_phone );

        if ( ! $recovery_data ) {
            return; // No hay contexto previo, es un mensaje normal.
        }

        $api = Api_Handler::get_instance();
        $message_upper = strtoupper( $message );

        // Caso: Cliente responde SI
        if ( $message_upper === 'SI' ) {
            
            // 1. Notificar al Admin
            $order_id = $recovery_data['order_id'];
            $admin_email = get_option( 'admin_email' );
            $subject = "Alerta de Soporte: Cliente intentando comprar (Orden #$order_id)";
            $body = "El cliente {$recovery_data['name']} ha respondido SI a la solicitud de ayuda por pedido fallido.";
            wp_mail( $admin_email, $subject, $body );

            // 2. Enviar WhatsApp de seguimiento al cliente con link de soporte
            $support_link = home_url( '/contacto' ); 
            $reply_msg = "¡Gracias por responder! Un agente revisará tu caso enseguida. Mientras tanto, puedes contactarnos directamente aquí: $support_link";
            
            $api->send_notification( $phone, $reply_msg, 'whatsapp' );

            // Borrar transient para cerrar el ciclo
            delete_transient( 'smsenlinea_recovery_' . $clean_phone );

        } 
        // Caso: Cliente responde NO
        elseif ( $message_upper === 'NO' ) {
            
            // Simplemente borramos el transient
            delete_transient( 'smsenlinea_recovery_' . $clean_phone );
        }
    }
}
