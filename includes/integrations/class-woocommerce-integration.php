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
        // CORRECCIÓN: Usamos wp_footer para imprimir el script inline de forma segura
        add_action( 'wp_footer', [ $this, 'print_checkout_tracker_script' ] );

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
    public function print_checkout_tracker_script() {
        // Solo ejecutar en la página de finalizar compra y si no se ha recibido la orden
        if ( ! is_checkout() || is_order_received_page() ) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var captureTimer;
            
            // Escuchar cuando el usuario escribe en el teléfono, email o nombre
            // Usamos 'input' además de 'change' y 'blur' para mayor sensibilidad
            $('form.checkout').on('input change blur', '#billing_phone, #billing_email, #billing_first_name, #billing_last_name', function() {
                
                var phone = $('#billing_phone').val();
                var email = $('#billing_email').val();
                
                // Solo enviamos si hay un teléfono mínimamente válido (6 dígitos) O un email con arroba
                if ( (phone && phone.length > 6) || (email && email.includes('@')) ) {
                    
                    clearTimeout(captureTimer);
                    
                    // Esperar 1.5 segundos después de que deje de escribir para no saturar el servidor
                    captureTimer = setTimeout(function() {
                        var data = {
                            action: 'smsenlinea_capture_checkout',
                            phone: phone,
                            email: email,
                            first_name: $('#billing_first_name').val(),
                            last_name: $('#billing_last_name').val(),
                            nonce: '<?php echo wp_create_nonce( "smsenlinea_checkout_nonce" ); ?>'
                        };

                        $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
                            // Console log silencioso para depuración
                            // console.log('SmsEnLinea: Datos guardados');
                        });
                    }, 1500);
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
        // Verificar seguridad
        check_ajax_referer( 'smsenlinea_checkout_nonce', 'nonce' );

        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        // Si no hay teléfono ni email, no guardamos nada
        if ( empty($phone) && empty($email) ) {
            wp_die();
        }

        // Datos adicionales
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : 'Cliente';
        $last_name  = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        
        // CORRECCIÓN: Asegurar que WooCommerce y el Carrito estén cargados
        if ( ! WC()->cart ) {
            WC()->frontend_includes();
            if ( null === WC()->session ) {
                $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
                WC()->session = new $session_class();
                WC()->session->init();
            }
            if ( null === WC()->customer ) {
                WC()->customer = new \WC_Customer( get_current_user_id(), true );
            }
            if ( null === WC()->cart ) {
                WC()->cart = new \WC_Cart();
            }
        }
        
        // Obtener contenido del carrito actual
        $cart_content = WC()->cart->get_cart();
        $total        = WC()->cart->total;
        $currency     = get_woocommerce_currency();
        
        // Generar un link de recuperación del carrito
        $checkout_url = wc_get_checkout_url();

        // Guardar o Actualizar en DB
        $this->save_session([
            'phone'       => $phone,
            'email'       => $email,
            'name'        => trim($first_name . ' ' . $last_name),
            'amount'      => $total,
            'currency'    => $currency,
            'status'      => 'abandoned', // Estado inicial
            'cart_data'   => maybe_serialize($cart_content), // Serializar de forma segura
            'checkout_url'=> $checkout_url
        ]);

        wp_send_json_success();
    }

    /**
     * Lógica auxiliar para guardar en la tabla personalizada
     */
    private function save_session( $data ) {
        global $wpdb;
        
        // Limpiamos el teléfono para usarlo como ID único si existe
        $clean_phone = preg_replace('/[^0-9]/', '', $data['phone']);
        $email = $data['email'];

        // Intentamos buscar por teléfono O por email si el teléfono está vacío
        $existing = null;
        
        if ( ! empty( $clean_phone ) ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE phone LIKE %s AND status = 'abandoned'", 
                '%' . $clean_phone . '%'
            ) );
        } elseif ( ! empty( $email ) ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE email = %s AND status = 'abandoned'", 
                $email
            ) );
        }

        $current_time = current_time('mysql');

        if ( $existing ) {
            // Actualizamos la sesión existente
            $wpdb->update(
                $this->table_name,
                [
                    'phone'            => $data['phone'], // Actualizar teléfono por si lo añadió después del email
                    'email'            => $data['email'],
                    'customer_name'    => $data['name'],
                    'cart_total'       => $data['amount'],
                    'last_interaction' => $current_time,
                    'cart_data'        => $data['cart_data']
                ],
                [ 'id' => $existing->id ]
            );
        } else {
            // Creamos nueva sesión
            $wpdb->insert(
                $this->table_name,
                [
                    'phone'            => $data['phone'],
                    'email'            => $data['email'],
                    'customer_name'    => $data['name'],
                    'cart_total'       => $data['amount'],
                    'currency'         => $data['currency'],
                    'status'           => 'abandoned',
                    'step'             => 1,
                    'created_at'       => $current_time,
                    'last_interaction' => $current_time,
                    'cart_data'        => $data['cart_data'],
                    'checkout_url'     => $data['checkout_url']
                ]
            );
        }
    }

    /**
     * 3. MANEJO DE ORDEN FALLIDA
     * Si el pago falla explícitamente.
     */
    public function handle_order_failure( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) return;

        // Guardamos explícitamente
        $this->save_session([
            'phone'       => $phone,
            'email'       => $order->get_billing_email(),
            'name'        => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'amount'      => $order->get_total(),
            'currency'    => $order->get_currency(),
            'status'      => 'abandoned', 
            'cart_data'   => '', 
            'checkout_url'=> $order->get_checkout_payment_url()
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
        $email = $order->get_billing_email();
        
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);

        // Actualizamos cualquier sesión 'abandoned' a 'recovered' por teléfono O email
        if ( ! empty( $clean_phone ) ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$this->table_name} SET status = 'recovered' WHERE phone LIKE %s AND status = 'abandoned'",
                '%' . $clean_phone . '%'
            ) );
        }
        
        if ( ! empty( $email ) ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$this->table_name} SET status = 'recovered' WHERE email = %s AND status = 'abandoned'",
                $email
            ) );
        }
    }
}
