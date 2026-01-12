<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Motor de Flujos Conversacionales (State Machine)
 * Gestiona el estado de la conversación con el cliente.
 */
class Flow_Engine {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smsenlinea_sessions';
    }

    /**
     * Inicia un nuevo flujo (Ej: Carrito Abandonado)
     */
    public function start_flow( $phone, $flow_type, $context_data = [] ) {
        global $wpdb;

        // Limpiar teléfono
        $phone = preg_replace( '/[^0-9]/', '', $phone );

        // Verificar si ya existe una sesión activa para no duplicar
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE phone_number = %s AND status = 'active'",
            $phone
        ) );

        if ( $existing ) {
            return false; // Ya hay una conversación en curso
        }

        // Insertar nueva sesión
        $wpdb->insert(
            $this->table_name,
            [
                'phone_number' => $phone,
                'flow_type'    => $flow_type,
                'current_step' => 'icebreaker_sent', // Primer paso
                'context_data' => json_encode( $context_data ),
                'status'       => 'active'
            ]
        );

        return $wpdb->insert_id;
    }

    /**
     * Procesa una respuesta entrante del cliente
     */
    public function process_reply( $phone, $message ) {
        global $wpdb;
        $phone = preg_replace( '/[^0-9]/', '', $phone );

        // 1. Buscar sesión activa
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE phone_number = %s AND status = 'active'",
            $phone
        ) );

        if ( ! $session ) {
            return false; // No hay contexto, el Webhook normal lo ignorará o manejará por defecto
        }

        // 2. Cargar Estrategia (Palabras Clave)
        $strategy = get_option( 'smsenlinea_strategy_settings' );
        $keywords_yes = array_map( 'trim', explode( ',', strtolower( $strategy['keywords_positive'] ?? 'si,yes,claro' ) ) );
        $keywords_no  = array_map( 'trim', explode( ',', strtolower( $strategy['keywords_negative'] ?? 'no,baja,stop' ) ) );

        $msg_lower = trim( strtolower( $message ) );
        $context = json_decode( $session->context_data, true );
        
        // 3. Enrutador según el tipo de flujo
        if ( $session->flow_type === 'abandoned_cart' ) {
            $this->handle_cart_logic( $session, $msg_lower, $keywords_yes, $keywords_no, $strategy, $context );
        }
        
        // Aquí podrías añadir más 'elseif' para otros flujos (bienvenida, soporte, etc.)

        return true;
    }

    /**
     * Lógica específica para Recuperación de Carritos
     */
    private function handle_cart_logic( $session, $msg, $yes_arr, $no_arr, $strategy, $context ) {
        $api = Api_Handler::get_instance();
        
        // Detectar Intención
        $is_positive = false;
        $is_negative = false;

        // Comprobación simple de palabras clave
        foreach ( $yes_arr as $word ) {
            if ( strpos( $msg, $word ) !== false ) { $is_positive = true; break; }
        }
        foreach ( $no_arr as $word ) {
            if ( strpos( $msg, $word ) !== false ) { $is_negative = true; break; }
        }

        // ACCIÓN: Respuesta POSITIVA (Quiere recuperar)
        if ( $is_positive ) {
            $response_msg = $this->replace_variables( $strategy['msg_recovery'], $context );
            $api->send_notification( $session->phone_number, $response_msg, 'whatsapp' );
            
            // Opcional: Notificar al admin por email
            $this->notify_admin( "Recuperación Exitosa", "El cliente {$context['customer_name']} solicitó el link de recuperación." );

            $this->close_session( $session->id, 'recovered' );
        }
        // ACCIÓN: Respuesta NEGATIVA (No interesa)
        elseif ( $is_negative ) {
            $response_msg = $this->replace_variables( $strategy['msg_close'], $context );
            $api->send_notification( $session->phone_number, $response_msg, 'whatsapp' );
            
            $this->close_session( $session->id, 'rejected' );
        }
        // ACCIÓN: No entendió (Opcional: Reintentar o esperar humano)
        else {
            // Por ahora no hacemos nada para no spamear, o podrías enviar un mensaje de ayuda.
            // Dejamos la sesión abierta para que intervenga un humano si lee el chat en el panel.
        }
    }

    /**
     * Cierra la sesión en la base de datos
     */
    private function close_session( $session_id, $final_status ) {
        global $wpdb;
        $wpdb->update(
            $this->table_name,
            [ 'status' => $final_status, 'current_step' => 'completed' ],
            [ 'id' => $session_id ]
        );
    }

    /**
     * Reemplaza variables dinámicas en los mensajes
     */
    private function replace_variables( $text, $context ) {
        $replacements = [
            '{customer_name}' => $context['customer_name'] ?? 'Cliente',
            '{order_id}'      => $context['order_id'] ?? '',
            '{checkout_url}'  => $context['checkout_url'] ?? '#',
            '{site_name}'     => get_bloginfo( 'name' ),
        ];
        return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
    }

    private function notify_admin( $subject, $message ) {
        wp_mail( get_option( 'admin_email' ), "[SmsEnLinea] $subject", $message );
    }
}
