<?php
namespace SmsEnLinea\ProConnect\Integrations;

use SmsEnLinea\ProConnect\Api_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gravity_Forms {

    public function __construct() {
        add_action( 'gform_after_submission', [ $this, 'process_entry' ], 10, 2 );
    }

    public function process_entry( $entry, $form ) {
        
        $phone = '';
        
        // Recorrer campos para encontrar el tipo 'phone'
        foreach ( $form['fields'] as $field ) {
            if ( $field->type === 'phone' && ! empty( $entry[ $field->id ] ) ) {
                $phone = $entry[ $field->id ];
                break;
            }
        }

        if ( empty( $phone ) ) {
            return;
        }

        // Mensaje genérico (podría mejorarse añadiendo configuración por formulario en V2)
        $message = "Gracias por enviar el formulario en " . get_bloginfo( 'name' ) . ". Te contactaremos pronto.";

        $api = Api_Handler::get_instance();
        $api->send_notification( $phone, $message, 'whatsapp' );
    }
}