<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
                        <input type="password" name="smsenlinea_settings[api_secret]" id="api_secret" value="<?php echo esc_attr( $options['api_secret'] ); ?>" class="regular-text" />
                        <button type="button" id="test-connection-btn" class="button button-secondary">Probar Conexión</button>
                        <p class="description">Obtenido de Herramientas -> API Keys en SmsEnLinea.</p>
                        <span id="connection-result" style="margin-left:10px; font-weight:bold;"></span>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Modo de Envío</th>
                    <td>
                        <select name="smsenlinea_settings[sending_mode]">
                            <option value="devices" <?php selected( $options['sending_mode'], 'devices' ); ?>>Dispositivos Vinculados (Android)</option>
                            <option value="credits" <?php selected( $options['sending_mode'], 'credits' ); ?>>Créditos / Gateway</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Device Unique ID</th>
                    <td>
                        <input type="text" name="smsenlinea_settings[device_id]" value="<?php echo esc_attr( $options['device_id'] ?? '' ); ?>" class="regular-text" />
                        <p class="description">Requerido solo para modo 'devices'.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">WhatsApp Account Unique ID</th>
                    <td>
                        <input type="text" name="smsenlinea_settings[wa_account_unique]" value="<?php echo esc_attr( $options['wa_account_unique'] ?? '' ); ?>" class="regular-text" />
                        <p class="description">ID de cuenta WhatsApp.</p>
                    </td>
                </tr>
            </table>
            <?php
        } elseif ( $active_tab == 'webhook_settings' ) {
            settings_fields( 'smsenlinea_option_group' );
            $options = get_option( 'smsenlinea_settings' );
            $secret = $options['webhook_secret'] ?? wp_generate_password( 20, false );
            $webhook_url = add_query_arg( 'secret', $secret, get_rest_url( null, 'smsenlinea/v1/webhook' ) );
            ?>
            <div class="card" style="max-width: 100%; margin-top: 20px;">
                <h3>Configuración de Webhook</h3>
                <p>Configura esta URL en tu panel. Incluye el secreto automáticamente.</p>
                <p>
                    <strong>Webhook URL:</strong><br>
                    <input type="text" value="<?php echo esc_url( $webhook_url ); ?>" class="large-text" readonly onclick="this.select();" />
                </p>
            </div>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Webhook Secret (Interno)</th>
                    <td>
                        <input type="text" name="smsenlinea_settings[webhook_secret]" value="<?php echo esc_attr( $secret ); ?>" class="regular-text" />
                        <p class="description">Este código se añade al final de la URL anterior para seguridad.</p>
                    </td>
                </tr>
            </table>
            <?php
        } elseif ( $active_tab == 'woocommerce' ) {
            settings_fields( 'smsenlinea_wc_group' );
            $wc_options = get_option( 'smsenlinea_wc_settings' );
            ?>
            <h3>Plantillas de Mensajes</h3>
            
            <div style="background:#fff; border:1px solid #ccd0d4; padding:15px; margin-bottom:20px;">
                <strong>💡 Variables Disponibles:</strong>
                <ul style="margin-top:5px; display:grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <li><code>{customer_name}</code> - Nombre Cliente</li>
                    <li><code>{customer_lastname}</code> - Apellido Cliente</li>
                    <li><code>{order_id}</code> - ID Pedido</li>
                    <li><code>{order_total}</code> - Total (con moneda)</li>
                    <li><code>{order_status}</code> - Estado del pedido</li>
                    <li><code>{order_date}</code> - Fecha de compra</li>
                    <li><code>{billing_address}</code> - Dirección Facturación</li>
                    <li><code>{shipping_method}</code> - Método Envío</li>
                    <li><code>{payment_method}</code> - Método Pago</li>
                    <li><code>{order_items}</code> - Lista de productos</li>
                    <li><code>{site_name}</code> - Nombre Sitio Web</li>
                </ul>
            </div>

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
                    <th scope="row">
                        <?php echo esc_html( $label ); ?><br>
                    </th>
                    <td>
                        <textarea name="smsenlinea_wc_settings[msg_<?php echo $key; ?>]" id="msg_<?php echo $key; ?>" rows="4" class="large-text"><?php echo esc_textarea( $wc_options["msg_$key"] ?? '' ); ?></textarea>
                        <br>
                        <button type="button" class="button emoji-btn" data-target="msg_<?php echo $key; ?>">😊 Insertar Emoji</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php
        }
        ?>

        <?php submit_button(); ?>
    </form>
    
    <script>
    jQuery(document).ready(function($) {
        // Test Connection Logic
        $('#test-connection-btn').click(function() {
            var btn = $(this);
            var res = $('#connection-result');
            
            btn.prop('disabled', true).text('Conectando...');
            res.text('').css('color', 'black');

            $.post(ajaxurl, {
                action: 'smsenlinea_test_connection'
            }, function(response) {
                btn.prop('disabled', false).text('Probar Conexión');
                if(response.success) {
                    res.text('✅ ' + response.data).css('color', 'green');
                } else {
                    res.text('❌ ' + response.data).css('color', 'red');
                }
            });
        });

        // Simple Emoji Inserter (Nativo del OS)
        $('.emoji-btn').click(function(e) {
            e.preventDefault();
            var targetId = $(this).data('target');
            var textarea = document.getElementById(targetId);
            textarea.focus();
            
            // Sugerencia visual: en Windows es Win+., en Mac Cmd+Ctrl+Space
            // Como no podemos abrir el teclado nativo por JS, insertaremos uno común como ejemplo
            // O podemos usar una librería. Para simplificar sin dependencias externas:
            var emoji = prompt("Copia y pega tu emoji aquí (Tip: Win+. o Cmd+Ctrl+Space):", "😊");
            if(emoji) {
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var text = textarea.value;
                var before = text.substring(0, start);
                var after  = text.substring(end, text.length);
                textarea.value = (before + emoji + after);
                textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
                textarea.focus();
            }
        });
    });
    </script>
</div>
