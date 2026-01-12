<?php
namespace SmsEnLinea\ProConnect\Integrations;

use SmsEnLinea\ProConnect\Api_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Contact_Form_7 {

    public function __construct() {
        add_action( 'wpcf7_mail_sent', [ $this, 'send_notification' ] );
    }

    /**
     * @param \WPCF7_ContactForm $contact_form
     */
    public function send_notification( $contact_form ) {
        $submission = \WPCF7_Submission::get_instance();
        
        if ( ! $submission ) {
            return;
        }

        $posted_data = $submission->get_posted_data();

        // Buscamos campos comunes de teléfono
        // El usuario debe nombrar su campo 'your-phone', 'tel', 'whatsapp', o 'phone'
        $phone_field = '';
        $possible_keys = [ 'your-phone', 'tel', 'phone', 'whatsapp', 'movil' ];

        foreach ( $possible_keys as $key ) {
            if ( ! empty( $posted_data[ $key ] ) ) {
                $phone_field = $posted_data[ $key ];
                break;
            }
        }

        if ( empty( $phone_field ) ) {
            return;
        }

        // Construir mensaje
        $site_name = get_bloginfo( 'name' );
        $message = "Hola, gracias por contactar a {$site_name}. Hemos recibido tu mensaje y te responderemos pronto.";

        // Enviar
        $api = Api_Handler::get_instance();
        $api->send_notification( $phone_field, $message, 'whatsapp' );
    }
}