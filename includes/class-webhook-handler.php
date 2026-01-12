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
        $this->webhook_secret = isset($options['webhook_secret']) ? $options['webhook_secret'] : '';
        
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'smsenlinea/v1', '/webhook', [
            'methods'  => 'POST',
            'callback' => [ $this, 'process_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function process_webhook( \WP_REST_Request $request ) {
        $params = $request->get_params();

        // Validación de Seguridad
        $incoming_secret = isset($params['secret']) ? $params['secret'] : ( isset($_GET['secret']) ? $_GET['secret'] : '' );

        if ( empty( $this->webhook_secret ) || $incoming_secret !== $this->webhook_secret ) {
            return new \WP_Error( 'forbidden', 'Invalid or Missing Secret', [ 'status' => 403 ] );
        }

        $type = isset($params['type']) ? $params['type'] : '';
        $data = isset($params['data']) ? $params['data'] : [];

        if ( ! in_array( $type, [ 'sms', 'whatsapp' ] ) ) {
            return rest_ensure_response( [ 'success' => true, 'msg' => 'Ignored type' ] );
        }

        $incoming_phone = isset( $data['wid'] ) ? $data['wid'] : ( isset($data['phone']) ? $data['phone'] : '' );
        $incoming_msg   = isset( $data['message'] ) ? trim( $data['message'] ) : '';

        if ( empty( $incoming_phone ) || empty( $incoming_msg ) ) {
            return rest_ensure_response( [ 'success' => false, 'msg' => 'No phone/msg' ] );
        }

        $this->handle_conversational_logic( $incoming_phone, $incoming_msg );

        return rest_ensure_response( [ 'success' => true ] );
    }

    private function handle_conversational_logic( $phone, $message ) {
        $clean_phone = preg_replace( '/[^0-9]/', '', $phone );
        $recovery_data = get_transient( 'smsenlinea_recovery_' . $clean_phone );

        if ( ! $recovery_data || ! is_array( $recovery_data ) ) {
            return;
        }

        $api = Api_Handler::get_instance();
        $message_upper = strtoupper( $message );

        if ( $message_upper === 'SI' ) {
            $order_id = $recovery_data['order_id'];
            $admin_email = get_option( 'admin_email' );
            
            wp_mail( $admin_email, "Alerta Soporte Orden #$order_id", "El cliente quiere recuperar su pedido #$order_id." );

            $support_link = home_url( '/contacto' ); 
            $api->send_notification( $phone, "¡Gracias! Un agente te contactará. Link: $support_link", 'whatsapp' );

            delete_transient( 'smsenlinea_recovery_' . $clean_phone );

        } elseif ( $message_upper === 'NO' ) {
            delete_transient( 'smsenlinea_recovery_' . $clean_phone );
        }
    }
}
