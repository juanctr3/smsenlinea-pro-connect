<?php
namespace SmsEnLinea\ProConnect;

/**
 * Fired during plugin activation
 */
class Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// 1. Crear las tablas de la base de datos
		self::create_db_tables();

		// 2. Crear las opciones por defecto
		self::create_default_options();
		
		// 3. Programar el Cron Job (Lo usaremos más adelante, dejamos el hueco preparado)
		if ( ! wp_next_scheduled( 'smsenlinea_recover_carts_event' ) ) {
			wp_schedule_event( time(), 'every_10_minutes', 'smsenlinea_recover_carts_event' );
		}
	}

	private static function create_db_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'smsenlinea_sessions';
		$charset_collate = $wpdb->get_charset_collate();

		// SQL para crear la tabla de sesiones (El Cerebro)
		// phone_number: Identificador del usuario
		// flow_type: 'abandoned_cart', 'welcome', etc.
		// current_step: En qué parte del flujo está ('waiting_reply', 'sent_coupon')
		// context_data: JSON con info extra (ID del pedido, nombre, cupones)
		
		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			phone_number varchar(20) NOT NULL,
			flow_type varchar(50) NOT NULL,
			current_step varchar(50) NOT NULL,
			context_data longtext DEFAULT '',
			last_interaction datetime DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'active',
			PRIMARY KEY  (id),
			KEY phone (phone_number),
			KEY status (status)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	private static function create_default_options() {
		// Opciones Generales y API
		if ( false === get_option( 'smsenlinea_settings' ) ) {
			$default_settings = [
				'api_secret'      => '',
				'sending_mode'    => 'devices',
				'device_id'       => '',
				'gateway_id'      => '',
				'wa_account_unique' => '',
				'webhook_secret'  => wp_generate_password( 20, false ),
			];
			add_option( 'smsenlinea_settings', $default_settings );
		}

		// Opciones de Mensajes WooCommerce
		if ( false === get_option( 'smsenlinea_wc_settings' ) ) {
			$default_wc_settings = [
				'msg_pending'    => 'Hola {customer_name}, hemos recibido tu pedido #{order_id}. Paga aquí: {payment_url}',
				'msg_processing' => 'Hola {customer_name}, tu pedido #{order_id} se está procesando.',
				'msg_completed'  => '¡Buenas noticias! Tu pedido #{order_id} ha sido completado. Gracias por comprar en {site_name}.',
				'msg_failed'     => 'Hola {customer_name}, parece que hubo un error con el pago del pedido #{order_id}. ¿Necesitas ayuda?',
			];
			add_option( 'smsenlinea_wc_settings', $default_wc_settings );
		}
		
		// Opciones de Estrategia (NUEVO: Para el motor de flujos)
		if ( false === get_option( 'smsenlinea_strategy_settings' ) ) {
			$default_strategy = [
				'cart_delay'       => 60, // Minutos para considerar abandonado
				'icebreaker_msg'   => 'Hola {customer_name}, notamos que no finalizaste tu compra. ¿Tuviste algún problema técnico?',
				'keywords_positive'=> "si,sí,claro,yes,ok,ayuda,hola,buenos,tengo",
				'keywords_negative'=> "no,baja,stop,cancelar,ya compre,gracias",
				'msg_recovery'     => 'Entiendo. Aquí tienes un enlace directo para retomar tu pedido donde lo dejaste: {checkout_url}',
				'msg_close'        => 'Entendido, gracias por informarnos. Si cambias de opinión, aquí estamos.',
			];
			add_option( 'smsenlinea_strategy_settings', $default_strategy );
		}
	}
}
