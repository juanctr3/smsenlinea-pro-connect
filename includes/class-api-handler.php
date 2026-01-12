<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Api_Handler {

    private static $instance = null;
    private $api_secret;
    private $mode;      // 'devices' o 'credits' [cite: 278]
    private $device_id; // Para modo devices [cite: 282]
    private $gateway_id;// Para modo credits [cite: 284]
    private $wa_account;// ID de cuenta WhatsApp [cite: 462]

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Cargar opciones desde la base de datos (configuradas en el panel admin)
        $options = get_option( 'smsenlinea_settings' );
        $this->api_secret = $options['api_secret'] ?? '';
        $this->mode       = $options['sending_mode'] ?? 'devices';
        $this->device_id  = $options['device_id'] ?? '';
        $this->gateway_id = $options['gateway_id'] ?? '';
        $this->wa_account = $options['wa_account_unique'] ?? '';
    }

    /**
     * Envía un mensaje (SMS o WhatsApp)
     *
     * @param string $to Número de teléfono destino (E.164)
     * @param string $message El mensaje a enviar
     * @param string $channel 'sms' o 'whatsapp'
     */
    public function send_notification( $to, $message, $channel = 'whatsapp' ) {
        
        if ( empty( $this->api_secret ) ) {
            return new \WP_Error( 'missing_config', __( 'API Secret falta configuración.', 'smsenlinea-pro' ) );
        }

        $endpoint = ( $channel === 'whatsapp' ) ? '/send/whatsapp' : '/send/sms'; // [cite: 290, 478]
        $url      = SMSENLINEA_API_BASE . $endpoint;

        // Construcción del Payload base
        $body = [
            'secret'   => $this->api_secret, // [cite: 277, 461]
            'message'  => $message,
            'priority' => 1, // Enviar inmediatamente [cite: 287, 465]
        ];

        if ( $channel === 'whatsapp' ) {
            // Configuración específica de WhatsApp [cite: 461]
            $body['account']   = $this->wa_account;
            $body['recipient'] = $to;
            $body['type']      = 'text';
        } else {
            // Configuración específica de SMS [cite: 277]
            $body['mode']  = $this->mode;
            $body['phone'] = $to;
            $body['sim']   = 1; // Default SIM 1
            
            if ( $this->mode === 'devices' ) {
                $body['device'] = $this->device_id;
            } else {
                $body['gateway'] = $this->gateway_id; // [cite: 284]
            }
        }

        // Envío seguro usando la API HTTP de WordPress
        $response = wp_remote_post( $url, [
            'body'    => $body,
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( 'SmsEnLinea Error: ' . $response->get_error_message() );
            return false;
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        // Verificar status 200 según documentación [cite: 290, 484]
        if ( isset( $data['status'] ) && $data['status'] == 200 ) {
            return true;
        }

        return false;
    }
}