<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Lógica simple de pestañas
$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'api_settings';
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=smsenlinea-pro&tab=api_settings" class="nav-tab <?php echo $active_tab == 'api_settings' ? 'nav-tab-active' : ''; ?>">API & General</a>
        <a href="?page=smsenlinea-pro&tab=webhook_settings" class="nav-tab <?php echo $active_tab == 'webhook_settings' ? 'nav-tab-active' : ''; ?>">Webhooks</a>
        <a href="?page=smsenlinea-pro&tab=woocommerce" class="nav-tab <?php echo $active_tab == 'woocommerce' ? 'nav-tab-active' : ''; ?>">WooCommerce</a>
    </h2>

    <form method="post" action="options.php">
        
        <?php
        if ( $active_tab == 'api_settings' ) {
            settings_fields( 'smsenlinea_option_group' );
            $options = get_option( 'smsenlinea_settings' );
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Secret</th>
                    <td>
                        <input type="password" name="smsenlinea_settings[api_secret]" value="<?php echo esc_attr( $options['api_secret'] ); ?>" class="regular-text" />
                        <p class="description">Obtenido de Herramientas -> API Keys en SmsEnLinea.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Modo de Envío</th>
                    <td>
                        <select name="smsenlinea_settings[sending_mode]">
                            <option value="devices" <?php selected( $options['sending_mode'], 'devices' ); ?>>Dispositivos Vinculados (Android)</option>
                            <option value="credits" <?php selected( $options['sending_mode'], 'credits' ); ?>>Créditos / Gateway</option>
                        </select>
                        <p class="description">Define si usarás tu propio móvil o gateways de terceros.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Device Unique ID</th>
                    <td>
                        <input type="text" name="smsenlinea_settings[device_id]" value="<?php echo esc_attr( $options['device_id'] ?? '' ); ?>" class="regular-text" />
                        <p class="description">Requerido solo para modo 'devices'. Obtenlo en /get/devices.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">WhatsApp Account Unique ID</th>
                    <td>
                        <input type="text" name="smsenlinea_settings[wa_account_unique]" value="<?php echo esc_attr( $options['wa_account_unique'] ?? '' ); ?>" class="regular-text" />
                        <p class="description">ID único de la cuenta de WhatsApp para envíos. Ver /get/wa.accounts.</p>
                    </td>
                </tr>
            </table>
            <?php
        } elseif ( $active_tab == 'webhook_settings' ) {
            settings_fields( 'smsenlinea_option_group' ); // Guardamos en el mismo grupo general
            $options = get_option( 'smsenlinea_settings' );
            ?>
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3>Configuración de Webhook</h3>
                <p>Copia esta URL y pégala en tu panel de SmsEnLinea para recibir actualizaciones en tiempo real y respuestas de clientes.</p>
                <p><strong>Webhook URL:</strong> <code><?php echo esc_url( get_rest_url( null, 'smsenlinea/v1/webhook' ) ); ?></code></p>
            </div>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Webhook Secret</th>
                    <td>
                        <input type="text" name="smsenlinea_settings[webhook_secret]" value="<?php echo esc_attr( $options['webhook_secret'] ); ?>" class="regular-text" />
                        <p class="description">Define este mismo secreto en el panel de SmsEnLinea para validar que los datos vienen de ellos.</p>
                    </td>
                </tr>
            </table>
            <?php
        } elseif ( $active_tab == 'woocommerce' ) {
            settings_fields( 'smsenlinea_wc_group' );
            $wc_options = get_option( 'smsenlinea_wc_settings' );
            ?>
            <h3>Plantillas de Mensajes</h3>
            <p>Usa shortcodes: <code>{customer_name}</code>, <code>{order_id}</code>, <code>{order_total}</code>, <code>{site_name}</code>.</p>
            <table class="form-table">
                <?php
                $statuses = [
                    'pending'    => 'Pendiente de Pago',
                    'processing' => 'Procesando',
                    'completed'  => 'Completado',
                    'failed'     => 'Fallido (Activa recuperación)',
                ];
                foreach ( $statuses as $key => $label ) :
                ?>
                <tr valign="top">
                    <th scope="row"><?php echo esc_html( $label ); ?></th>
                    <td>
                        <textarea name="smsenlinea_wc_settings[msg_<?php echo $key; ?>]" rows="3" class="large-text"><?php echo esc_textarea( $wc_options["msg_$key"] ?? '' ); ?></textarea>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php
        }
        ?>

        <?php submit_button(); ?>
    </form>
</div>
