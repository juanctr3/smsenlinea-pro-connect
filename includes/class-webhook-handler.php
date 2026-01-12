<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Webhook_Handler {

    private static $instance = null;
    private $webhook_secret;
    private $log_limit = 50; // Guardaremos los últimos 50 eventos para verlos en el admin

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
     * Ahora con logs y validación estricta.
     */
    public function process_webhook( \WP_REST_Request $request ) {
        $params = $request->get_params();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';

        // 1. Logging Inicial (Trazabilidad)
        // Registramos que alguien está tocando la puerta
        $this->log( "Webhook entrante desde IP: $ip" );

        // 2. Validación de Seguridad
        // El 'secret' debe venir en la URL (ej: ?secret=xyz) o en el cuerpo
        $received_secret = $request->get_param( 'secret' );

        if ( empty( $this->webhook_secret ) ) {
            $this->log( "ERROR CRÍTICO: No has configurado un Webhook Secret en el plugin.", 'error' );
            return new \WP_Error( 'config_error', 'Plugin no configurado', [ 'status' => 500 ] );
        }

        if ( $received_secret !== $this->webhook_secret ) {
            $this->log( "ACCESO DENEGADO: Secreto incorrecto o faltante.", 'error' );
            return new \WP_Error( 'forbidden', 'Invalid Secret', [ 'status' => 403 ] );
        }

        // 3. Extraer y Validar Datos
        $type = $params['type'] ?? '';
        $data = $params['data'] ?? [];

        // Solo procesamos SMS y WhatsApp (según documentación)
        if ( ! in_array( $type, [ 'sms', 'whatsapp' ] ) ) {
            $this->log( "Evento ignorado: Tipo '$type' no soportado." );
            return rest_ensure_response( [ 'success' => true, 'msg' => 'Ignored type' ] );
        }

        // Normalizar datos según el PDF:
        // WhatsApp usa 'wid' como remitente. SMS usa 'phone'.
        $sender = '';
        if ( $type === 'whatsapp' && isset( $data['wid'] ) ) {
            $sender = $data['wid'];
        } elseif ( $type === 'sms' && isset( $data['phone'] ) ) {
            $sender = $data['phone'];
        }

        $message = trim( $data['message'] ?? '' );

        if ( empty( $sender ) || empty( $message ) ) {
            $this->log( "Datos incompletos: Falta remitente o mensaje.", 'warning' );
            return rest_ensure_response( [ 'success' => false, 'msg' => 'No phone/msg' ] );
        }

        $this->log( "Mensaje recibido ($type) de $sender: \"$message\"" );

        // 4. Lógica de Recuperación (Conversación)
        $this->handle_conversational_logic( $sender, $message );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Lógica "Estrella" de recuperación de ventas
     * Maneja las respuestas SI/NO de los clientes.
     */
    private function handle_conversational_logic( $phone, $message ) {
        // Limpiar teléfono (solo números) para buscar en la base de datos temporal
        $clean_phone = preg_replace( '/[^0-9]/', '', $phone );
        
        // Buscar si existe un "Caso Abierto" para este número
        $recovery_data = get_transient( 'smsenlinea_recovery_' . $clean_phone );

        if ( ! $recovery_data ) {
            // Si no hay caso abierto, es solo un mensaje normal.
            return; 
        }

        $this->log( "¡Respuesta vinculada a Orden #{$recovery_data['order_id']}!" );

        $api = Api_Handler::get_instance();
        $message_upper = strtoupper( trim( $message ) );

        // Palabras clave aceptadas para SI
        if ( in_array( $message_upper, ['SI', 'SÍ', 'YES', 'CLARO', 'OK'] ) ) {
            
            // 1. Notificar al Admin
            $order_id = $recovery_data['order_id'];
            $admin_email = get_option( 'admin_email' );
            $subject = "🔥 ¡Venta Recuperada! Cliente respondió SI (Orden #$order_id)";
            $body = "El cliente {$recovery_data['name']} ha respondido afirmativamente.\n\nTeléfono: $phone\nOrden: #$order_id\n\n¡Contacta al cliente ahora!";
            
            wp_mail( $admin_email, $subject, $body );
            $this->log( "Acción: Correo de alerta enviado al admin." );

            // 2. Respuesta automática al cliente
            $support_link = home_url( '/contacto' ); 
            $reply_msg = "¡Genial! Un asesor revisará tu pedido #$order_id enseguida. Gracias por confirmar.";
            
            // Usamos whatsapp por defecto para la respuesta si el origen fue whatsapp
            $api->send_notification( $phone, $reply_msg, 'whatsapp' );

            // Borrar transient para cerrar el ciclo
            delete_transient( 'smsenlinea_recovery_' . $clean_phone );

        } 
        // Palabras clave aceptadas para NO
        elseif ( in_array( $message_upper, ['NO', 'NOP', 'CANCELAR'] ) ) {
            
            $this->log( "Cliente respondió NO. Cerrando caso." );
            delete_transient( 'smsenlinea_recovery_' . $clean_phone );
            
        } else {
            $this->log( "Respuesta ambigua del cliente. No se toma acción automática." );
        }
    }

    /**
     * Sistema de Logs Interno
     * Guarda en base de datos (para mostrar en admin) y en archivo debug.
     */
    private function log( $message, $level = 'info' ) {
        $timestamp = current_time( 'mysql' );
        $entry = array(
            'time' => $timestamp,
            'level' => $level,
            'msg' => $message
        );

        // 1. Guardar en opción de WP (Rotativo, últimos 50)
        $logs = get_option( 'smsenlinea_webhook_logs', [] );
        if ( ! is_array( $logs ) ) $logs = [];
        
        // Añadir al principio
        array_unshift( $logs, $entry );
        
        // Recortar si excede el límite
        if ( count( $logs ) > $this->log_limit ) {
            $logs = array_slice( $logs, 0, $this->log_limit );
        }
        
        // Guardar sin 'autoload' para no afectar rendimiento global
        update_option( 'smsenlinea_webhook_logs', $logs, false );

        // 2. Guardar en error_log de PHP (para debug técnico)
        // Solo si WP_DEBUG está activo, para no ensuciar logs de producción innecesariamente
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[SmsEnLinea Webhook][$level] $message" );
        }
    }
}
