<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Webhook Handler
 * Recibe los mensajes y se los pasa al Motor de Flujos (Cerebro).
 */
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
        $this->webhook_secret = isset($options['webhook_secret']) ? $options['webhook_secret'] : '';
        
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        // Endpoint: /wp-json/smsenlinea/v1/webhook
        register_rest_route( 'smsenlinea/v1', '/webhook', [
            'methods'  => 'POST',
            'callback' => [ $this, 'process_webhook' ],
            'permission_callback' => '__return_true', // Validamos el secreto manualmente
        ] );
    }

    public function process_webhook( \WP_REST_Request $request ) {
        $params = $request->get_params();

        // 1. Validación de Seguridad
        // Buscamos el secreto en el cuerpo (POST) o en la URL (GET)
        $incoming_secret = isset($params['secret']) ? $params['secret'] : ( isset($_GET['secret']) ? $_GET['secret'] : '' );

        if ( empty( $this->webhook_secret ) || $incoming_secret !== $this->webhook_secret ) {
            return new \WP_Error( 'forbidden', 'Invalid or Missing Secret', [ 'status' => 403 ] );
        }

        // 2. Extracción de Datos
        $type = isset($params['type']) ? $params['type'] : '';
        $data = isset($params['data']) ? $params['data'] : [];

        // Solo procesamos SMS y WhatsApp
        if ( ! in_array( $type, [ 'sms', 'whatsapp' ] ) ) {
            return rest_ensure_response( [ 'success' => true, 'msg' => 'Ignored type' ] );
        }

        // Obtener teléfono y mensaje de forma segura
        $incoming_phone = isset( $data['wid'] ) ? $data['wid'] : ( isset($data['phone']) ? $data['phone'] : '' );
        $incoming_msg   = isset( $data['message'] ) ? trim( $data['message'] ) : '';

        if ( empty( $incoming_phone ) || empty( $incoming_msg ) ) {
            return rest_ensure_response( [ 'success' => false, 'msg' => 'No phone/msg' ] );
        }

        // 3. CONEXIÓN CON EL CEREBRO
        // Delegamos la inteligencia al Flow_Engine. 
        // Él decidirá si este mensaje es una respuesta a un carrito abandonado o no.
        $engine = Flow_Engine::get_instance();
        $processed = $engine->process_reply( $incoming_phone, $incoming_msg );

        if ( $processed ) {
             return rest_ensure_response( [ 'success' => true, 'msg' => 'Processed by Flow Engine' ] );
        }

        // Si no había sesión activa, aquí podrías agregar lógica para mensajes nuevos (ej: soporte general)
        return rest_ensure_response( [ 'success' => true, 'msg' => 'No active flow for this user' ] );
    }
}
