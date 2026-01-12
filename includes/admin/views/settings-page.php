<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
?>

<style>
    .sms-wrap { max-width: 1000px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
    .sms-header { background: #fff; padding: 20px 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; border-left: 5px solid #2271b1; }
    .sms-header h1 { margin: 0; font-size: 24px; color: #1d2327; font-weight: 600; }
    .sms-nav { display: flex; border-bottom: 2px solid #ddd; margin-bottom: 25px; }
    .sms-nav a { padding: 15px 25px; text-decoration: none; color: #50575e; font-weight: 500; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; font-size: 15px; }
    .sms-nav a:hover { color: #2271b1; }
    .sms-nav a.active { border-bottom: 2px solid #2271b1; color: #2271b1; font-weight: 600; }
    .sms-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); padding: 30px; margin-bottom: 20px; border: 1px solid #e2e4e7; }
    .sms-card h3 { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; font-size: 18px; color: #1d2327; display: flex; align-items: center; gap: 10px; }
    .sms-form-group { margin-bottom: 20px; }
    .sms-form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #3c434a; }
    .sms-form-group input[type="text"], .sms-form-group input[type="password"], .sms-form-group select, .sms-form-group textarea { width: 100%; max-width: 100%; padding: 10px; border: 1px solid #c3c4c7; border-radius: 4px; font-size: 14px; }
    .sms-form-group textarea { height: 100px; line-height: 1.4; }
    .sms-helper { font-size: 13px; color: #646970; margin-top: 6px; }
    .sms-btn-primary { background: #2271b1 !important; color: #fff !important; border: none !important; border-radius: 4px !important; padding: 10px 20px !important; font-size: 15px !important; cursor: pointer; transition: background 0.3s; }
    .sms-btn-primary:hover { background: #135e96 !important; }
    .emoji-btn { background: transparent; border: 1px solid #c3c4c7; border-radius: 4px; padding: 5px 10px; cursor: pointer; font-size: 16px; margin-top: 5px; }
    .emoji-btn:hover { background: #f0f0f1; }
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .status-success { background: #e7f7ed; color: #107e3e; }
    .status-error { background: #fbeaea; color: #d63638; }
    .variables-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; background: #f6f7f7; padding: 15px; border-radius: 4px; border: 1px solid #dcdcde; }
    .variables-grid code { background: #fff; padding: 2px 6px; border-radius: 3px; font-weight: 600; color: #2271b1; }
</style>

<div class="sms-wrap">
    
    <div class="sms-header">
        <div>
            <h1>SmsEnLinea Pro Connect</h1>
            <p style="margin:5px 0 0; color:#646970;">Marketing Conversacional y Automatización</p>
        </div>
        <div>
            <span class="status-badge" style="background:#f0f0f1; color:#50575e;">v<?php echo SMSENLINEA_VERSION; ?></span>
        </div>
    </div>

    <div class="sms-nav">
        <a href="?page=smsenlinea-pro&tab=general" class="<?php echo $active_tab == 'general' ? 'active' : ''; ?>">🔌 Conexión API</a>
        <a href="?page=smsenlinea-pro&tab=strategy" class="<?php echo $active_tab == 'strategy' ? 'active' : ''; ?>">🧠 Estrategia & Flujos</a>
        <a href="?page=smsenlinea-pro&tab=woocommerce" class="<?php echo $active_tab == 'woocommerce' ? 'active' : ''; ?>">🛒 Notificaciones Pedidos</a>
    </div>

    <form method="post" action="options.php">
        
        <?php if ( $active_tab == 'general' ) : 
            settings_fields( 'smsenlinea_option_group' );
            $opts = get_option( 'smsenlinea_settings' );
            $secret = isset($opts['webhook_secret']) ? $opts['webhook_secret'] : wp_generate_password( 20, false );
            $webhook_url = add_query_arg( 'secret', $secret, get_rest_url( null, 'smsenlinea/v1/webhook' ) );
        ?>
            <div class="sms-card">
                <h3>Credenciales API</h3>
                <div class="sms-form-group">
                    <label>API Secret</label>
                    <input type="password" name="smsenlinea_settings[api_secret]" value="<?php echo esc_attr( $opts['api_secret'] ); ?>" placeholder="Pega aquí tu API Secret">
                    <div style="margin-top:10px;">
                        <button type="button" id="test-connection-btn" class="button button-secondary">Probar Conexión</button>
                        <span id="connection-result" style="margin-left:10px; font-weight:bold;"></span>
                    </div>
                </div>
            </div>

            <div class="sms-card">
                <h3>Configuración de Envío</h3>
                <div class="sms-form-group">
                    <label>Modo de Envío</label>
                    <select name="smsenlinea_settings[sending_mode]">
                        <option value="devices" <?php selected( $opts['sending_mode'], 'devices' ); ?>>Dispositivos Vinculados (Android)</option>
                        <option value="credits" <?php selected( $opts['sending_mode'], 'credits' ); ?>>Créditos / Cloud API</option>
                    </select>
                </div>
                <div class="sms-form-group">
                    <label>ID Dispositivo / Gateway</label>
                    <input type="text" name="smsenlinea_settings[device_id]" value="<?php echo esc_attr( $opts['device_id'] ?? '' ); ?>">
                </div>
                <div class="sms-form-group">
                    <label>WhatsApp Account Unique ID</label>
                    <input type="text" name="smsenlinea_settings[wa_account_unique]" value="<?php echo esc_attr( $opts['wa_account_unique'] ?? '' ); ?>">
                </div>
            </div>

            <div class="sms-card" style="border-left: 4px solid #72aee6;">
                <h3>📡 Webhook (Recepción de Mensajes)</h3>
                <p>Para habilitar el "Cerebro" y las respuestas automáticas, configura esta URL en tu panel de SmsEnLinea.</p>
                <div class="sms-form-group">
                    <label>Tu Webhook URL</label>
                    <input type="text" value="<?php echo esc_url( $webhook_url ); ?>" readonly onclick="this.select();" style="background:#f9f9f9;">
                    <input type="hidden" name="smsenlinea_settings[webhook_secret]" value="<?php echo esc_attr( $secret ); ?>">
                </div>
            </div>

        <?php elseif ( $active_tab == 'strategy' ) : 
            settings_fields( 'smsenlinea_strategy_group' );
            $strat = get_option( 'smsenlinea_strategy_settings' );
        ?>
            <div class="sms-card">
                <h3>Recuperación de Carritos Abandonados</h3>
                <p style="color:#666;">Detecta pedidos "Pendientes de Pago" y envía un mensaje rompehielos para recuperar la venta.</p>
                
                <div class="sms-form-group">
                    <label>Tiempo de espera (Minutos)</label>
                    <input type="number" name="smsenlinea_strategy_settings[cart_delay]" value="<?php echo esc_attr( $strat['cart_delay'] ?? 60 ); ?>" min="15">
                    <p class="sms-helper">Recomendado: 45-60 minutos.</p>
                </div>

                <div class="sms-form-group">
                    <label>🧊 Mensaje Rompehielos (Paso 1)</label>
                    <textarea name="smsenlinea_strategy_settings[icebreaker_msg]" id="ice_msg"><?php echo esc_textarea( $strat['icebreaker_msg'] ?? '' ); ?></textarea>
                    <button type="button" class="emoji-btn" data-target="ice_msg">😊 Emoji</button>
                    <p class="sms-helper">Ej: "¿Tuviste problemas con tu pago?". Objetivo: Que el cliente responda.</p>
                </div>
            </div>

            <div class="sms-card">
                <h3>🧠 El Cerebro: Palabras Clave</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="sms-form-group">
                        <label>Palabras POSITIVAS (Sí, Quiero, Ayuda)</label>
                        <textarea name="smsenlinea_strategy_settings[keywords_positive]" style="height:60px;"><?php echo esc_textarea( $strat['keywords_positive'] ?? '' ); ?></textarea>
                        <p class="sms-helper">Separadas por comas.</p>
                    </div>
                    <div class="sms-form-group">
                        <label>Palabras NEGATIVAS (No, Baja, Stop)</label>
                        <textarea name="smsenlinea_strategy_settings[keywords_negative]" style="height:60px;"><?php echo esc_textarea( $strat['keywords_negative'] ?? '' ); ?></textarea>
                        <p class="sms-helper">Separadas por comas.</p>
                    </div>
                </div>
            </div>

            <div class="sms-card">
                <h3>Respuestas Automáticas</h3>
                <div class="sms-form-group">
                    <label>✅ Si responde POSITIVO (Enviar Link)</label>
                    <textarea name="smsenlinea_strategy_settings[msg_recovery]" id="rec_msg"><?php echo esc_textarea( $strat['msg_recovery'] ?? '' ); ?></textarea>
                    <button type="button" class="emoji-btn" data-target="rec_msg">😊 Emoji</button>
                    <p class="sms-helper">Usa <code>{checkout_url}</code> para el link directo al pago.</p>
                </div>
                <div class="sms-form-group">
                    <label>❌ Si responde NEGATIVO (Cierre)</label>
                    <textarea name="smsenlinea_strategy_settings[msg_close]" id="close_msg"><?php echo esc_textarea( $strat['msg_close'] ?? '' ); ?></textarea>
                    <button type="button" class="emoji-btn" data-target="close_msg">😊 Emoji</button>
                </div>
            </div>

        <?php elseif ( $active_tab == 'woocommerce' ) : 
            settings_fields( 'smsenlinea_wc_group' );
            $wc = get_option( 'smsenlinea_wc_settings' );
        ?>
            <div class="sms-card">
                <h3>Variables Disponibles</h3>
                <div class="variables-grid">
                    <div><code>{customer_name}</code></div>
                    <div><code>{order_id}</code></div>
                    <div><code>{order_total}</code></div>
                    <div><code>{checkout_url}</code></div>
                    <div><code>{billing_address}</code></div>
                    <div><code>{order_items}</code></div>
                </div>
            </div>

            <div class="sms-card">
                <h3>Estados del Pedido</h3>
                <?php
                $statuses = [
                    'pending'    => 'Pendiente de Pago (Creación)',
                    'processing' => 'Procesando / Pagado',
                    'completed'  => 'Completado / Enviado',
                    'failed'     => 'Fallido',
                ];
                foreach ( $statuses as $key => $label ) : ?>
                    <div class="sms-form-group">
                        <label><?php echo esc_html( $label ); ?></label>
                        <textarea name="smsenlinea_wc_settings[msg_<?php echo $key; ?>]" id="wc_<?php echo $key; ?>"><?php echo esc_textarea( $wc["msg_$key"] ?? '' ); ?></textarea>
                        <button type="button" class="emoji-btn" data-target="wc_<?php echo $key; ?>">😊 Emoji</button>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

        <div style="margin-top:20px;">
            <?php submit_button( 'Guardar Cambios', 'primary sms-btn-primary' ); ?>
        </div>

    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Emojis
    $('.emoji-btn').click(function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        var input = document.getElementById(targetId);
        var emoji = prompt("Inserta tu emoji (Win+. / Mac: Cmd+Ctrl+Space):", "😊");
        if(emoji) {
            input.setRangeText(emoji, input.selectionStart, input.selectionEnd, 'end');
            input.focus();
        }
    });

    // Test Connection
    $('#test-connection-btn').click(function() {
        var btn = $(this);
        var res = $('#connection-result');
        btn.prop('disabled', true).text('Conectando...');
        res.text('');
        $.post(ajaxurl, { action: 'smsenlinea_test_connection' }, function(response) {
            btn.prop('disabled', false).text('Probar Conexión');
            if(response.success) {
                res.text('✅ ' + response.data).css('color', '#107e3e');
            } else {
                res.text('❌ ' + response.data).css('color', '#d63638');
            }
        });
    });
});
</script>
