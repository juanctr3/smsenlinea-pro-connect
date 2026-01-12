<?php
namespace SmsEnLinea\ProConnect\Integrations;

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

        // 1. Inyectar "El Espía" en el Checkout (Usamos wp_footer con prioridad alta)
        add_action( 'wp_footer', [ $this, 'print_checkout_tracker_script' ], 9999 );

        // 2. Receptor AJAX
        add_action( 'wp_ajax_smsenlinea_capture_checkout', [ $this, 'capture_checkout_data' ] );
        add_action( 'wp_ajax_nopriv_smsenlinea_capture_checkout', [ $this, 'capture_checkout_data' ] );

        // 3. Hooks de Orden
        add_action( 'woocommerce_order_status_failed', [ $this, 'handle_order_failure' ] );
        add_action( 'woocommerce_order_status_cancelled', [ $this, 'handle_order_failure' ] );
        
        // 4. Limpieza tras compra exitosa
        add_action( 'woocommerce_payment_complete', [ $this, 'mark_session_recovered' ] );
        add_action( 'woocommerce_order_status_processing', [ $this, 'mark_session_recovered' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'mark_session_recovered' ] );
    }

    /**
     * SCRIPT DE RASTREO (JS) MEJORADO
     * Incluye logs en consola para verificar que funciona.
     */
    public function print_checkout_tracker_script() {
        // Solo en checkout y si no es la página de "Gracias por su compra"
        if ( ! is_checkout() || is_order_received_page() ) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('SmsEnLinea: Iniciando monitor de carrito...');
            var captureTimer;
            
            // Detectar escritura en campos clave
            $(document.body).on('input change blur', '#billing_phone, #billing_email, #billing_first_name', function() {
                
                var phone = $('#billing_phone').val();
                var email = $('#billing_email').val();
                
                // Limpiar timer anterior
                clearTimeout(captureTimer);
                
                // Validación básica antes de enviar nada
                var hasPhone = phone && phone.replace(/\D/g,'').length > 6;
                var hasEmail = email && email.indexOf('@') > -1;

                if ( hasPhone || hasEmail ) {
                    
                    // Esperar 1.5 segundos a que termine de escribir
                    captureTimer = setTimeout(function() {
                        console.log('SmsEnLinea: Intentando capturar datos...');
                        
                        var data = {
                            action: 'smsenlinea_capture_checkout',
                            phone: phone,
                            email: email,
                            first_name: $('#billing_first_name').val(),
                            last_name: $('#billing_last_name').val(),
                            nonce: '<?php echo wp_create_nonce( "smsenlinea_checkout_nonce" ); ?>'
                        };

                        // Usar la URL oficial de Ajax de WooCommerce si existe, sino la de WP
                        var ajaxUrl = (typeof wc_checkout_params !== 'undefined') ? wc_checkout_params.ajax_url : '<?php echo admin_url('admin-ajax.php'); ?>';

                        $.post(ajaxUrl, data, function(response) {
                            if(response.success) {
                                console.log('SmsEnLinea: ✅ Datos guardados en BD');
                            } else {
                                console.log('SmsEnLinea: ❌ Error al guardar', response);
                            }
                        }).fail(function(xhr) {
                            console.log('SmsEnLinea: ❌ Error de conexión', xhr.responseText);
                        });

                    }, 1500);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * CAPTURAR DATOS VIA AJAX
     */
    public function capture_checkout_data() {
        // Verificación de seguridad silenciosa (sin wp_die para no romper JS)
        if ( ! check_ajax_referer( 'smsenlinea_checkout_nonce', 'nonce', false ) ) {
            wp_send_json_error('Nonce inválido');
        }

        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        if ( empty($phone) && empty($email) ) {
            wp_send_json_error('Datos vacíos');
        }

        // Cargar entorno de WooCommerce si es necesario
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

        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : 'Cliente';
        $last_name  = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $full_name  = trim($first_name . ' ' . $last_name);
        
        // Datos del carrito
        $cart_content = WC()->cart->get_cart();
        $total        = WC()->cart->total;
        $currency     = get_woocommerce_currency();
        $checkout_url = wc_get_checkout_url();

        // Preparar guardado
        global $wpdb;
        
        // Limpiar teléfono para búsqueda
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Buscar sesión existente por Teléfono O Email
        $existing = null;
        if ( ! empty( $clean_phone ) ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE phone LIKE %s AND status = 'abandoned'", 
                '%' . $clean_phone . '%'
            ) );
        } 
        
        if ( ! $existing && ! empty( $email ) ) {
            $existing = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE email = %s AND status = 'abandoned'", 
                $email
            ) );
        }

        $data_db = [
            'phone'         => $phone,
            'email'         => $email,
            'customer_name' => $full_name,
            'cart_total'    => $total,
            'currency'      => $currency,
            'cart_data'     => maybe_serialize($cart_content),
            'checkout_url'  => $checkout_url,
            'last_interaction' => current_time('mysql')
        ];

        if ( $existing ) {
            $wpdb->update( $this->table_name, $data_db, ['id' => $existing->id] );
        } else {
            $data_db['status']     = 'abandoned';
            $data_db['created_at'] = current_time('mysql');
            $wpdb->insert( $this->table_name, $data_db );
        }

        wp_send_json_success('Guardado ID: ' . ($existing ? $existing->id : $wpdb->insert_id));
    }

    /**
     * MÉTODOS AUXILIARES (Sin cambios mayores, solo mantenimiento)
     */
    public function handle_order_failure( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $phone = $order->get_billing_phone();
        if ( empty( $phone ) ) return;

        $this->manual_db_insert([
            'phone' => $phone,
            'email' => $order->get_billing_email(),
            'name'  => $order->get_billing_first_name(),
            'total' => $order->get_total(),
            'curr'  => $order->get_currency(),
            'url'   => $order->get_checkout_payment_url()
        ]);
    }

    public function mark_session_recovered( $order_id ) {
        global $wpdb;
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $phone = preg_replace('/[^0-9]/', '', $order->get_billing_phone());
        $email = $order->get_billing_email();

        if ( ! empty( $phone ) ) {
            $wpdb->query( $wpdb->prepare("UPDATE {$this->table_name} SET status = 'recovered' WHERE phone LIKE %s", '%' . $phone . '%') );
        }
        if ( ! empty( $email ) ) {
            $wpdb->query( $wpdb->prepare("UPDATE {$this->table_name} SET status = 'recovered' WHERE email = %s", $email) );
        }
    }

    private function manual_db_insert($d) {
        global $wpdb;
        $wpdb->insert($this->table_name, [
            'phone' => $d['phone'], 'email' => $d['email'], 'customer_name' => $d['name'],
            'cart_total' => $d['total'], 'currency' => $d['curr'], 'status' => 'abandoned',
            'checkout_url' => $d['url'], 'created_at' => current_time('mysql')
        ]);
    }
}
