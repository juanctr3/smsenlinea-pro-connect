<?php
namespace SmsEnLinea\ProConnect;

/**
 * Scheduler Class
 * Se encarga de las tareas programadas (Cron Jobs) para recuperar carritos.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scheduler {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 1. Registrar intervalos personalizados (5 minutos)
        add_filter( 'cron_schedules', [ $this, 'add_custom_intervals' ] );

        // 2. Vincular el evento del Cron a nuestra función maestra
        add_action( 'smsenlinea_cron_recovery_event', [ $this, 'process_abandoned_carts' ] );

        // 3. Trigger Manual (AJAX) para pruebas desde el admin
        add_action( 'wp_ajax_smsenlinea_trigger_cron', [ $this, 'ajax_manual_trigger' ] );
    }

    /**
     * Agrega intervalo de 5 minutos a WordPress
     */
    public function add_custom_intervals( $schedules ) {
        $schedules['sms_five_minutes'] = [
            'interval' => 300, // 300 segundos = 5 minutos
            'display'  => __( 'Cada 5 Minutos (SmsEnLinea)', 'smsenlinea-pro' )
        ];
        return $schedules;
    }

    /**
     * FUNCIÓN MAESTRA: Busca y procesa carritos abandonados
     */
    public function process_abandoned_carts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'smsenlinea_sessions';

        // 1. Obtener configuración de tiempo de espera (Default: 15 min)
        $options = get_option( 'smsenlinea_settings' );
        $delay_minutes = isset( $options['abandoned_timeout'] ) ? intval( $options['abandoned_timeout'] ) : 15;
        
        if ( $delay_minutes < 1 ) $delay_minutes = 1;

        // 2. Calcular la hora de corte (Ej: Ahora - 15 minutos)
        // Buscamos carritos que se modificaron ANTES de esta hora.
        $cut_off_time = date( 'Y-m-d H:i:s', strtotime( "-$delay_minutes minutes" ) );

        // 3. Consulta a la Base de Datos
        // Buscamos: Estado 'abandoned' AND Última interacción < Tiempo de corte
        // Límite: 10 por ejecución para evitar timeouts en el servidor.
        $sessions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE status = 'abandoned' 
             AND last_interaction <= %s 
             LIMIT 10",
            $cut_off_time
        ) );

        if ( empty( $sessions ) ) {
            return "Sin carritos pendientes.";
        }

        // 4. Procesar cada sesión encontrada
        $engine = Flow_Engine::get_instance();
        $count = 0;

        foreach ( $sessions as $session ) {
            // Delegamos la lógica de envío y actualización al Motor de Flujos
            // Esto actualiza el estado a 'processing' o 'recovered_step_1'
            $result = $engine->run_recovery_sequence( $session );
            
            if ( $result ) {
                $count++;
            }
        }

        return "Procesados $count carritos abandonados.";
    }

    /**
     * AJAX: Permite al admin ejecutar el Cron manualmente
     */
    public function ajax_manual_trigger() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permisos insuficientes' );
        }

        // Ejecutar lógica inmediata
        $result_msg = $this->process_abandoned_carts();

        wp_send_json_success( [ 'message' => $result_msg ] );
    }

    /**
     * Helper para asegurar que el Cron esté programado (Autocuración)
     * Se puede llamar desde el Activator o init.
     */
    public static function ensure_cron_is_scheduled() {
        if ( ! wp_next_scheduled( 'smsenlinea_cron_recovery_event' ) ) {
            wp_schedule_event( time(), 'sms_five_minutes', 'smsenlinea_cron_recovery_event' );
        }
    }
}
