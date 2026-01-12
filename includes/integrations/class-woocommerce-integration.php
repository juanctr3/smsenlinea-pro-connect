<?php
namespace SmsEnLinea\ProConnect\Integrations;

use SmsEnLinea\ProConnect\Api_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCommerce_Integration {

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

        // 1. Inyectar "El Espía" en el Checkout
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_checkout_tracker' ] );

        // 2. Receptor AJAX (Guarda los datos en vivo)
        add_action( 'wp_ajax_smsenlinea_capture_checkout', [ $this, 'capture_checkout_data' ] );
        add_action( 'wp_ajax_nopriv_smsenlinea_capture_checkout', [ $this, 'capture_checkout_data' ] );

        // 3. Hooks de Estado de Orden (Para recuperar fallidos o cerrar exitosos)
        add_action( 'woocommerce_order_status_failed', [ $this, 'handle_order_failure' ] );
        add_action( 'woocommerce_order_status_cancelled', [ $this, 'handle_order_failure' ] );
        
        // Si la orden se completa, borramos la sesión de recuperación para no molestar
        add_action( 'woocommerce_payment_complete', [ $this, 'mark_session_recovered' ] );
        add_action( 'woocommerce_order_status_processing', [ $this, 'mark_session_recovered' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'mark_session_recovered' ] );
    }

    /**
     * 1. INYECTAR SCRIPT DE RASTREO (JS)
     * Detecta cuando el usuario escribe en el campo teléfono.
     */
    public function enqueue_checkout_tracker() {
        if ( ! is_checkout() || is_order_received_page() ) {
            return;
        }

        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var captureTimer;
            
            // Escuchar cuando el usuario escribe en el teléfono o email
            $('#billing_phone, #billing_email, #billing_first_name').on('blur change', function() {
                var phone = $('#billing_phone').val();
                
                // Solo enviamos si hay un teléfono mínimamente válido (6 dígitos)
                if (phone && phone.length > 6) {
                    clearTimeout(captureTimer);
                    
                    // Esperar 1 segundo después de que deje de escribir para no saturar
                    captureTimer = setTimeout(function() {
                        var data = {
                            action: 'smsenlinea_capture_checkout',
                            phone: phone,
                            email: $('#billing_email').val(),
                            first_name: $('#billing_first_name').val(),
                            last_name: $('#billing_last_name').val(),
                            nonce: '<?php echo wp_create_nonce( "smsenlinea_checkout_nonce" ); ?>'
                        };

                        $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
                            console.log('SmsEnLinea: Carrito guardado');
                        });
                    }, 1000);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * 2. CAPTURAR DATOS VIA AJAX
     * Guarda la sesión en la base de datos temporal.
     */
    public function capture_checkout_data() {
        check_ajax_referer( 'smsenlinea_checkout_nonce', 'nonce' );

        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        
        if ( empty($phone) ) {
            wp_die();
        }

        // Datos adicionales
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : 'Cliente';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        
        // Obtener contenido del carrito actual
        $cart_content = WC()->cart->get_cart();
        $total = WC()->cart->total;
        $currency = get_woocommerce_currency();
        
        // Generar un link de recuperación del carrito
        // Nota: En una versión avanzada, aquí crearíamos un hash único para restaurar la sesión.
        $checkout_url = wc_get_checkout_url();

        // Guardar o Actualizar en DB
        $this->save_session([
            'phone' => $phone,
            'email' => $email,
            'name'  => $first_name . ' ' . $last_name,
            'amount'=> $total,
            'currency' => $currency,
            'status' => 'abandoned', // Estado inicial
            'cart_data' => serialize($cart_content), // Guardamos serializado por si acaso
            'checkout_url' => $checkout_url
        ]);

        wp_send_json_success();
    }

    /**
     * Lógica auxiliar para guardar en la tabla personalizada
     */
    private function save_session( $data ) {
        global $wpdb;
        
        // Limpiamos el teléfono para usarlo como ID único
        $clean_phone = preg_replace('/[^0-9]/', '', $data['phone']);
        
        // Verificamos si ya existe una sesión activa para este teléfono
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE phone LIKE %s AND status = 'abandoned'", 
            '%' . $clean_phone . '%'
        ) );

        $current_time = current_time('mysql');

        if ( $existing ) {
            // Actualizamos la sesión existente
            $wpdb->update(
                $this->table_name,
                [
                    'customer_name' => $data['name'],
                    'cart_total'    => $data['amount'],
                    'last_interaction' => $current_time,
                    'cart_data'     => $data['cart_data'] // Actualizamos items
                ],
                [ 'id' => $existing->id ]
            );
        } else {
            // Creamos nueva sesión
            $wpdb->insert(
                $this->table_name,
                [
                    'phone' => $data['phone'],
                    'customer_name' => $data['name'],
                    'cart_total' => $data['amount'],
                    'currency' => $data['currency'],
                    'status' => 'abandoned',
                    'step' => 1, // Paso 1 del flujo de recuperación
                    'created_at' => $current_time,
                    'last_interaction' => $current_time,
                    'cart_data' => $data['cart_data']
                ]
            );
        }
    }

    /**
     * 3. MANEJO DE ORDEN FALLIDA
     * Si el pago falla explícitamente, podemos enviar mensaje inmediato (Opcional)
     * o simplemente dejar que el Cron de "abandonado" lo recoja.
     */
    public function handle_order_failure( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) return;

        // Marcamos la sesión como 'failed' para prioridad alta
        // Opcional: Enviar mensaje inmediato aquí.
        // Por ahora, solo aseguramos que esté en la DB para el Cron.
        $this->save_session([
            'phone' => $phone,
            'email' => $order->get_billing_email(),
            'name'  => $order->get_billing_first_name(),
            'amount'=> $order->get_total(),
            'currency' => $order->get_currency(),
            'status' => 'abandoned', // Lo dejamos como abandonado para que el flujo normal lo tome
            'cart_data' => '', 
            'checkout_url' => $order->get_checkout_payment_url() // Link directo a pagar orden
        ]);
    }

    /**
     * 4. MARCAR COMO RECUPERADO (ÉXITO)
     * Si el cliente compra, ya no debemos enviarle mensajes de carrito abandonado.
     */
    public function mark_session_recovered( $order_id ) {
        global $wpdb;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $phone = $order->get_billing_phone();
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);

        if ( empty( $clean_phone ) ) return;

        // Actualizamos cualquier sesión 'abandoned' a 'recovered'
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table_name} SET status = 'recovered' WHERE phone LIKE %s AND status = 'abandoned'",
            '%' . $clean_phone . '%'
        ) );
    }
}
