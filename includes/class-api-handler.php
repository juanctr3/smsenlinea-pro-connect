<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Api_Handler {

    private static $instance = null;
    private $api_secret;
    private $mode;      
    private $device_id; 
    private $gateway_id;
    private $wa_account;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $options = get_option( 'smsenlinea_settings' );
        $this->api_secret = $options['api_secret'] ?? '';
        $this->mode       = $options['sending_mode'] ?? 'devices';
        $this->device_id  = $options['device_id'] ?? '';
        $this->gateway_id = $options['gateway_id'] ?? '';
        $this->wa_account = $options['wa_account_unique'] ?? '';
    }

    /**
     * Prueba la conexión con la API
     [cite_start]* [cite: 92] Endpoint /get/subscription es ideal para probar credenciales
     */
    public function test_connection() {
        if ( empty( $this->api_secret ) ) {
            return new \WP_Error( 'missing_config', 'Falta el API Secret.' );
        }

        $url = SMSENLINEA_API_BASE . '/get/subscription';
        
        // Añadimos el secreto como query param
        $url = add_query_arg( 'secret', $this->api_secret, $url );

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['status'] ) && $data['status'] == 200 ) {
            // Devolvemos datos del plan para mostrar al usuario si se quiere
            return [
                'success' => true,
                'msg'     => 'Conexión Exitosa. Plan: ' . ( $data['data']['name'] ?? 'Desconocido' )
            ];
        } else {
            return new \WP_Error( 'api_error', 'Error API: ' . ( $data['message'] ?? 'Desconocido' ) );
        }
    }

    public function send_notification( $to, $message, $channel = 'whatsapp' ) {
        
        if ( empty( $this->api_secret ) ) {
            return new \WP_Error( 'missing_config', __( 'API Secret falta configuración.', 'smsenlinea-pro' ) );
        }

        $endpoint = ( $channel === 'whatsapp' ) ? '/send/whatsapp' : '/send/sms'; 
        $url      = SMSENLINEA_API_BASE . $endpoint;

        // Construcción del Payload base
        $body = [
            'secret'   => $this->api_secret, 
            'message'  => $message,
            'priority' => 1, 
        ];

        if ( $channel === 'whatsapp' ) {
            $body['account']   = $this->wa_account;
            $body['recipient'] = $to;
            $body['type']      = 'text';
        } else {
            $body['mode']  = $this->mode;
            $body['phone'] = $to;
            $body['sim']   = 1; 
            
            if ( $this->mode === 'devices' ) {
                $body['device'] = $this->device_id;
            } else {
                $body['gateway'] = $this->gateway_id; 
            }
        }

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

        if ( isset( $data['status'] ) && $data['status'] == 200 ) {
            return true;
        }

        return false;
    }
}
