<?php
namespace SmsEnLinea\ProConnect;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Flow_Engine {

    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smsenlinea_sessions';
    }

    public function start_flow( $phone, $flow_type, $context_data = [] ) {
        global $wpdb;
        $phone = preg_replace( '/[^0-9]/', '', $phone );

        // Verificar si ya existe sesión activa
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE phone_number = %s AND status = 'active'",
            $phone
        ) );

        if ( $existing ) { return false; }

        $wpdb->insert(
            $this->table_name,
            [
                'phone_number' => $phone,
                'flow_type'    => $flow_type,
                'current_step' => 'icebreaker_sent',
                'context_data' => json_encode( $context_data ),
                'status'       => 'active'
            ]
        );
        return $wpdb->insert_id;
    }

    public function process_reply( $phone, $message ) {
        global $wpdb;
        $phone = preg_replace( '/[^0-9]/', '', $phone );

        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE phone_number = %s AND status = 'active'",
            $phone
        ) );

        if ( ! $session ) { return false; }

        $strategy = get_option( 'smsenlinea_strategy_settings' );
        $keywords_yes = array_map( 'trim', explode( ',', strtolower( $strategy['keywords_positive'] ?? 'si,yes' ) ) );
        $keywords_no  = array_map( 'trim', explode( ',', strtolower( $strategy['keywords_negative'] ?? 'no,stop' ) ) );

        $msg_lower = trim( strtolower( $message ) );
        $context = json_decode( $session->context_data, true );
        
        if ( $session->flow_type === 'abandoned_cart' ) {
            $this->handle_cart_logic( $session, $msg_lower, $keywords_yes, $keywords_no, $strategy, $context );
        }
        return true;
    }

    private function handle_cart_logic( $session, $msg, $yes_arr, $no_arr, $strategy, $context ) {
        $api = Api_Handler::get_instance();
        
        $is_positive = false;
        $is_negative = false;

        foreach ( $yes_arr as $word ) { if ( strpos( $msg, $word ) !== false ) { $is_positive = true; break; } }
        foreach ( $no_arr as $word ) { if ( strpos( $msg, $word ) !== false ) { $is_negative = true; break; } }

        if ( $is_positive ) {
            $response_msg = $this->replace_variables( $strategy['msg_recovery'], $context );
            $api->send_notification( $session->phone_number, $response_msg, 'whatsapp' );
            $this->close_session( $session->id, 'recovered' );
        }
        elseif ( $is_negative ) {
            $response_msg = $this->replace_variables( $strategy['msg_close'], $context );
            $api->send_notification( $session->phone_number, $response_msg, 'whatsapp' );
            $this->close_session( $session->id, 'rejected' );
        }
    }

    private function close_session( $session_id, $final_status ) {
        global $wpdb;
        $wpdb->update( $this->table_name, [ 'status' => $final_status, 'current_step' => 'completed' ], [ 'id' => $session_id ] );
    }

    // Función auxiliar privada
    private function replace_variables( $text, $context ) {
        $replacements = [
            '{customer_name}'     => $context['customer_name'] ?? 'Cliente',
            '{customer_lastname}' => $context['customer_lastname'] ?? '',
            '{order_id}'          => $context['order_id'] ?? '',
            '{checkout_url}'      => $context['checkout_url'] ?? '#',
            '{total}'             => $context['total'] ?? '',
            '{order_total}'       => $context['total'] ?? '', // Alias
            '{billing_address}'   => $context['billing_address'] ?? '',
            '{order_items}'       => $context['order_items'] ?? '',
            '{order_date}'        => $context['order_date'] ?? '',
            '{site_name}'         => get_bloginfo( 'name' ),
        ];
        return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
    }

    // Método público para usar desde el Scheduler
    public function replace_variables_public( $text, $context ) {
        return $this->replace_variables( $text, $context );
    }
}
