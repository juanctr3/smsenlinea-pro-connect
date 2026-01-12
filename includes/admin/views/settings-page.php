<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Lógica para limpiar logs manualmente
if ( isset( $_POST['smsenlinea_action_clear_logs'] ) && current_user_can( 'manage_options' ) ) {
    update_option( 'smsenlinea_webhook_logs', [] );
    echo '<div class="notice notice-success is-dismissible"><p>Historial de logs borrado.</p></div>';
}
global $wpdb;
$table_sessions = $wpdb->prefix . 'smsenlinea_sessions';

// Lógica básica para obtener estadísticas si estamos en la pestaña reportes
$stats = [
    'total' => 0, 'recovered' => 0, 'lost' => 0, 'money' => 0
];
$logs = [];

$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

if ( $active_tab == 'reports' ) {
    // 1. Contar totales
    $stats['total']     = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sessions" );
    $stats['recovered'] = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sessions WHERE status = 'recovered'" );
    $stats['lost']      = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sessions WHERE status = 'rejected'" );
    
    // 2. Obtener historial reciente
    $logs = $wpdb->get_results( "SELECT * FROM $table_sessions ORDER BY last_interaction DESC LIMIT 50" );
}
?>

<style>
    .sms-wrap { max-width: 1050px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
    
    /* Header & Nav */
    .sms-header { background: #fff; padding: 20px 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; border-left: 5px solid #2271b1; }
    .sms-header h1 { margin: 0; font-size: 24px; color: #1d2327; font-weight: 600; }
    .sms-nav { display: flex; border-bottom: 2px solid #ddd; margin-bottom: 25px; padding-bottom: 1px; }
    .sms-nav a { padding: 12px 20px; text-decoration: none; color: #50575e; font-weight: 500; border-bottom: 2px solid transparent; margin-bottom: -3px; transition: all 0.2s; font-size: 14px; display: flex; align-items: center; gap: 6px; }
    .sms-nav a:hover { color: #2271b1; background: #f6f7f7; }
    .sms-nav a.active { border-bottom: 2px solid #2271b1; color: #2271b1; font-weight: 600; background: #fff; border-radius: 4px 4px 0 0; }
    
    /* Cards & Forms */
    .sms-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); padding: 30px; margin-bottom: 20px; border: 1px solid #e2e4e7; position: relative; }
    .sms-card h3 { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #f0f0f1; font-size: 16px; text-transform: uppercase; letter-spacing: 0.5px; color: #646970; }
    .sms-form-group { margin-bottom: 20px; }
    .sms-form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; }
    .sms-form-group input[type="text"], .sms-form-group input[type="password"], .sms-form-group select, .sms-form-group textarea, .sms-form-group input[type="number"] { width: 100%; max-width: 100%; padding: 8px 12px; border: 1px solid #c3c4c7; border-radius: 4px; font-size: 14px; }
    .sms-form-group textarea { height: 100px; line-height: 1.4; }
    .sms-helper { font-size: 13px; color: #646970; margin-top: 6px; font-style: italic; }
    
    /* Buttons */
    .sms-btn-primary { background: #2271b1 !important; color: #fff !important; border: none !important; border-radius: 4px !important; padding: 10px 20px !important; font-size: 15px !important; cursor: pointer; transition: background 0.3s; }
    .sms-btn-primary:hover { background: #135e96 !important; }
    .emoji-btn { background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; padding: 4px 10px; cursor: pointer; font-size: 13px; margin-top: 5px; }
    .emoji-btn:hover { background: #fff; border-color: #2271b1; color: #2271b1; }
    
    /* Dashboard Stats */
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
    .stat-box { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e4e7; text-align: center; }
    .stat-number { font-size: 32px; font-weight: 700; color: #2271b1; display: block; margin-bottom: 5px; }
    .stat-label { font-size: 13px; text-transform: uppercase; color: #646970; font-weight: 600; }
    
    /* Table */
    .logs-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e4e7; }
    .logs-table th { background: #f6f7f7; text-align: left; padding: 15px; font-weight: 600; color: #1d2327; border-bottom: 1px solid #e2e4e7; }
    .logs-table td { padding: 15px; border-bottom: 1px solid #f0f0f1; color: #50575e; font-size: 14px; }
    .logs-table tr:last-child td { border-bottom: none; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .badge.recovered { background: #e7f7ed; color: #107e3e; }
    .badge.rejected { background: #fbeaea; color: #d63638; }
    .badge.active { background: #fff8e5; color: #996800; }
</style>

<div class="sms-wrap">
    
    <div class="sms-header">
        <div>
            <h1>SmsEnLinea Pro Connect</h1>
            <p style="margin:5px 0 0; color:#646970;">Marketing Conversacional</p>
        </div>
        <div>
            <span class="badge active" style="font-size:12px;">v<?php echo SMSENLINEA_VERSION; ?></span>
        </div>
    </div>

    <div class="sms-nav">
        <a href="?page=smsenlinea-pro&tab=general" class="<?php echo $active_tab == 'general' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings"></span> Configuración
        </a>
        <a href="?page=smsenlinea-pro&tab=strategy" class="<?php echo $active_tab == 'strategy' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-networking"></span> Estrategia
        </a>
        <a href="?page=smsenlinea-pro&tab=woocommerce" class="<?php echo $active_tab == 'woocommerce' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-cart"></span> Notificaciones
        </a>
        <a href="?page=smsenlinea-pro&tab=abandoned" class="<?php echo $active_tab == 'abandoned' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-warning"></span> Carritos Abandonados
        </a>
        <a href="?page=smsenlinea-pro&tab=reports" class="<?php echo $active_tab == 'reports' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-chart-bar"></span> Reportes
        </a>
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
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <input type="password" id="sms_api_secret" name="smsenlinea_settings[api_secret]" value="<?php echo esc_attr( $opts['api_secret'] ); ?>" style="flex-grow: 1;">
                    <button type="button" id="btn_test_connection" class="button button-secondary">Probar Conexión</button>
                </div>
                <p class="sms-helper">Obtenido de Herramientas -> API Keys en SmsEnLinea.</p>

                <div id="connection_status_card" style="margin-top: 15px; display: none;"></div>
            </div>
        </div>
            </div>

            <div class="sms-card">
                <h3>Configuración de Envío</h3>
                <div class="sms-form-group">
                    <label>Modo</label>
                    <select name="smsenlinea_settings[sending_mode]">
                        <option value="devices" <?php selected( $opts['sending_mode'], 'devices' ); ?>>Dispositivos Vinculados</option>
                        <option value="credits" <?php selected( $opts['sending_mode'], 'credits' ); ?>>Créditos API</option>
                    </select>
                </div>
                <div class="sms-form-group">
                    <label>Device ID</label>
                    <input type="text" name="smsenlinea_settings[device_id]" value="<?php echo esc_attr( $opts['device_id'] ?? '' ); ?>">
                </div>
                <div class="sms-form-group">
                    <label>WhatsApp Account ID</label>
                    <input type="text" name="smsenlinea_settings[wa_account_unique]" value="<?php echo esc_attr( $opts['wa_account_unique'] ?? '' ); ?>">
                </div>
            </div>

            <div class="sms-card" style="border-left: 4px solid #72aee6;">
                <h3>📡 Webhook (Requerido)</h3>
                <p>Copia esta URL en tu panel de SmsEnLinea:</p>
                <input type="text" value="<?php echo esc_url( $webhook_url ); ?>" readonly onclick="this.select();" style="width:100%; background:#f9f9f9; padding:10px; border:1px dashed #ccc;">
                <input type="hidden" name="smsenlinea_settings[webhook_secret]" value="<?php echo esc_attr( $secret ); ?>">
            </div>
        <div style="margin-top: 30px;">
                <h3>📡 Monitor de Tráfico en Vivo (Últimos 50 eventos)</h3>
                <p class="description">Aquí verás en tiempo real qué mensajes llegan a tu sistema y si son aceptados o rechazados.</p>
                
                <div class="sms-card" style="padding: 0; overflow: hidden;">
                    <table class="widefat striped" style="border: none;">
                        <thead>
                            <tr style="background: #f0f0f1;">
                                <th style="padding: 15px;">Hora</th>
                                <th style="padding: 15px;">Nivel</th>
                                <th style="padding: 15px;">Detalle del Evento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Recuperamos los logs guardados
                            $webhook_logs = get_option( 'smsenlinea_webhook_logs', [] );
                            
                            if ( empty( $webhook_logs ) ) {
                                echo '<tr><td colspan="3" style="padding: 20px; text-align: center; color: #666;">⏳ No hay actividad registrada aún. Envía un mensaje a tu WhatsApp para probar.</td></tr>';
                            } else {
                                foreach ( $webhook_logs as $log ) {
                                    $color_style = '';
                                    $badge = '';
                                    
                                    if ( $log['level'] === 'error' ) {
                                        $color_style = 'color: #d63638; background: #fbeaea;';
                                        $badge = '❌ ERROR';
                                    } elseif ( $log['level'] === 'warning' ) {
                                        $color_style = 'color: #996800;';
                                        $badge = '⚠️ ALERTA';
                                    } else {
                                        $badge = 'ℹ️ INFO';
                                    }

                                    echo "<tr style='$color_style'>";
                                    echo "<td style='padding: 10px; white-space: nowrap;'>" . esc_html( $log['time'] ) . "</td>";
                                    echo "<td style='padding: 10px;'><strong>" . esc_html( $badge ) . "</strong></td>";
                                    echo "<td style='padding: 10px;'>" . esc_html( $log['msg'] ) . "</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                    
                    <div style="padding: 10px; background: #f9f9f9; border-top: 1px solid #ddd; text-align: right;">
                        <input type="hidden" name="smsenlinea_clear_logs" value="0"> <button type="submit" name="smsenlinea_action_clear_logs" value="1" class="button button-link-delete" onclick="return confirm('¿Borrar historial?');">Limpiar Historial</button>
                    </div>
                </div>
            </div>
        <?php elseif ( $active_tab == 'abandoned' ) : 
            // Consultar solo los abandonados (sin recuperar)
            $abandoned_carts = $wpdb->get_results( "SELECT * FROM $table_sessions WHERE status = 'abandoned' ORDER BY created_at DESC LIMIT 50" );
        ?>
            <div class="sms-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h3>🚨 Carritos por Recuperar</h3>
                    <span class="description">Mostrando los últimos 50 abandonos pendientes.</span>
                </div>

                <?php if ( empty( $abandoned_carts ) ) : ?>
                    <div style="padding:40px; text-align:center; color:#888; background:#f9f9f9; border-radius:4px;">
                        <span class="dashicons dashicons-yes" style="font-size:40px; height:40px; margin-bottom:10px; color:#46b450;"></span><br>
                        ¡Todo limpio! No hay carritos abandonados pendientes.
                    </div>
                <?php else : ?>
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Teléfono</th>
                                <th>Carrito</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $abandoned_carts as $cart ) : 
                                $cart_data = maybe_unserialize( $cart->cart_data );
                                $item_count = is_array($cart_data) ? count($cart_data) : 0;
                            ?>
                            <tr>
                                <td>
                                    <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $cart->created_at ) ) ); ?>
                                    <br><small style="color:#999;"><?php echo human_time_diff( strtotime( $cart->created_at ), current_time( 'timestamp' ) ); ?> atrás</small>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $cart->customer_name ); ?></strong><br>
                                    <small><?php echo esc_html( $cart->email ); ?></small>
                                </td>
                                <td>
                                    <code><?php echo esc_html( $cart->phone ); ?></code>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $cart->phone); ?>" target="_blank" style="text-decoration:none; margin-left:5px;">↗️</a>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $cart->cart_total . ' ' . $cart->currency ); ?></strong><br>
                                    <small><?php echo $item_count; ?> artículos</small>
                                </td>
                                <td>
                                    <button type="button" class="button button-primary btn-manual-send" data-id="<?php echo esc_attr( $cart->id ); ?>">
                                        <span class="dashicons dashicons-paperplane" style="margin-top:4px;"></span> Enviar Rompehielos
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ( $active_tab == 'strategy' ) : 
            settings_fields( 'smsenlinea_strategy_group' );
            $strat = get_option( 'smsenlinea_strategy_settings' );
        ?>
            <div class="sms-card">
                <div style="float:right;">
                    <button type="button" id="trigger-cron-btn" class="button button-small">⚡ Ejecutar Ahora</button>
                    <span id="cron-result" style="font-size:12px; margin-left:5px;"></span>
                </div>
                <h3>Recuperación de Carritos</h3>
                <p>Configura cuándo contactar al cliente.</p>
                
                <div class="sms-form-group">
                    <label>Retraso (Minutos)</label>
                    <input type="number" name="smsenlinea_strategy_settings[cart_delay]" value="<?php echo esc_attr( $strat['cart_delay'] ?? 60 ); ?>" min="1">
                </div>

                <div class="sms-form-group">
                    <label>Mensaje Rompehielos (Paso 1)</label>
                    <textarea name="smsenlinea_strategy_settings[icebreaker_msg]" id="ice_msg"><?php echo esc_textarea( $strat['icebreaker_msg'] ?? '' ); ?></textarea>
                    <button type="button" class="emoji-btn" data-target="ice_msg">😊 Emoji</button>
                </div>
            </div>

            <div class="sms-card">
                <h3>Inteligencia Artificial (Palabras Clave)</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="sms-form-group">
                        <label>Si responde POSITIVO:</label>
                        <input type="text" name="smsenlinea_strategy_settings[keywords_positive]" value="<?php echo esc_attr( $strat['keywords_positive'] ?? '' ); ?>">
                        <p class="sms-helper">Ej: si, claro, ayuda, quiero</p>
                    </div>
                    <div class="sms-form-group">
                        <label>Si responde NEGATIVO:</label>
                        <input type="text" name="smsenlinea_strategy_settings[keywords_negative]" value="<?php echo esc_attr( $strat['keywords_negative'] ?? '' ); ?>">
                        <p class="sms-helper">Ej: no, baja, stop, gracias</p>
                    </div>
                </div>
            </div>

            <div class="sms-card">
                <h3>Flujo de Respuesta</h3>
                <div class="sms-form-group">
                    <label>✅ Mensaje de Recuperación (Link)</label>
                    <textarea name="smsenlinea_strategy_settings[msg_recovery]" id="rec_msg"><?php echo esc_textarea( $strat['msg_recovery'] ?? '' ); ?></textarea>
                    <button type="button" class="emoji-btn" data-target="rec_msg">😊 Emoji</button>
                </div>
                <div class="sms-form-group">
                    <label>❌ Mensaje de Despedida</label>
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
                <div style="background:#f6f7f7; padding:15px; border-radius:4px; font-size:13px; color:#2271b1;">
                    <code>{customer_name}</code>, <code>{order_id}</code>, <code>{order_total}</code>, <code>{checkout_url}</code>, <code>{site_name}</code>
                </div>
            </div>

            <div class="sms-card">
                <h3>Estados del Pedido</h3>
                <?php
                $statuses = ['pending'=>'Pendiente','processing'=>'Procesando','completed'=>'Completado','failed'=>'Fallido'];
                foreach ( $statuses as $key => $label ) : ?>
                    <div class="sms-form-group">
                        <label><?php echo esc_html( $label ); ?></label>
                        <textarea name="smsenlinea_wc_settings[msg_<?php echo $key; ?>]" id="wc_<?php echo $key; ?>"><?php echo esc_textarea( $wc["msg_$key"] ?? '' ); ?></textarea>
                        <button type="button" class="emoji-btn" data-target="wc_<?php echo $key; ?>">😊 Emoji</button>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ( $active_tab == 'reports' ) : ?>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <span class="stat-number"><?php echo intval( $stats['total'] ); ?></span>
                    <span class="stat-label">Conversaciones Iniciadas</span>
                </div>
                <div class="stat-box" style="border-bottom: 3px solid #107e3e;">
                    <span class="stat-number" style="color:#107e3e;"><?php echo intval( $stats['recovered'] ); ?></span>
                    <span class="stat-label">Recuperados</span>
                </div>
                <div class="stat-box" style="border-bottom: 3px solid #d63638;">
                    <span class="stat-number" style="color:#d63638;"><?php echo intval( $stats['lost'] ); ?></span>
                    <span class="stat-label">Rechazados / Perdidos</span>
                </div>
            </div>

            <div class="sms-card" style="padding:0; border:none; box-shadow:none;">
                <h3>Historial Reciente</h3>
                <?php if ( empty( $logs ) ) : ?>
                    <div style="padding:20px; text-align:center; color:#888;">No hay actividad registrada aún.</div>
                <?php else : ?>
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Teléfono</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $logs as $log ) : 
                                $ctx = json_decode( $log->context_data, true );
                                $status_class = $log->status == 'recovered' ? 'recovered' : ($log->status == 'rejected' ? 'rejected' : 'active');
                                $status_label = $log->status == 'recovered' ? 'Recuperado' : ($log->status == 'rejected' ? 'Rechazado' : 'Esperando');
                            ?>
                            <tr>
                                <td><?php echo esc_html( $log->last_interaction ); ?></td>
                                <td><?php echo esc_html( $log->phone_number ); ?></td>
                                <td><?php echo esc_html( $ctx['customer_name'] ?? '-' ); ?></td>
                                <td><?php echo esc_html( $ctx['total'] ?? '-' ); ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <?php if ( $active_tab != 'reports' ) submit_button( 'Guardar Configuración', 'primary sms-btn-primary' ); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Lógica para envío manual de Rompehielos
    $('.btn-manual-send').click(function(e) {
        e.preventDefault();
        var $btn = $(this);
        var cartId = $btn.data('id');
        
        if( !confirm('¿Estás seguro de enviar el mensaje Rompehielos a este cliente ahora?') ) {
            return;
        }

        $btn.prop('disabled', true).text('Enviando...');

        $.post(ajaxurl, {
            action: 'smsenlinea_manual_icebreaker',
            id: cartId,
            nonce: '<?php echo wp_create_nonce( "smsenlinea_admin_nonce" ); ?>' // Seguridad Envato
        }, function(response) {
            if (response.success) {
                alert('✅ ' + response.data);
                location.reload(); // Recargar para actualizar la tabla (el carrito pasará a contactado)
            } else {
                alert('❌ Error: ' + (response.data || 'Desconocido'));
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-paperplane" style="margin-top:4px;"></span> Reintentar');
            }
        }).fail(function() {
            alert('Error de conexión con el servidor.');
            $btn.prop('disabled', false).text('Reintentar');
        });
    });
    // Emojis simple
    $('.emoji-btn').click(function(e) {
        var t = $(this).data('target');
        var i = document.getElementById(t);
        var em = prompt("Inserta Emoji:", "😊");
        if(em) { i.setRangeText(em,i.selectionStart,i.selectionEnd,'end'); i.focus(); }
    });

    // [Nuevo V2] Test Conexión Avanzado y Estado del Plan
    $('#btn_test_connection').on('click', function(e) {
        e.preventDefault();
        var secret = $('#sms_api_secret').val();
        var $btn = $(this);
        var $card = $('#connection_status_card');

        if(!secret) {
            alert('Por favor ingresa un API Secret primero.');
            return;
        }

        $btn.prop('disabled', true).text('Verificando...');
        $card.hide().html('');

        $.post(ajaxurl, {
            action: 'smsenlinea_check_connection', // Debe coincidir con el hook en class-admin-settings.php
            secret: secret
        }, function(response) {
            $btn.prop('disabled', false).text('Probar Conexión');

            if (response.success) {
                var plan = response.data.data;
                // Construir HTML de la tarjeta de Plan
                var html = '<div style="background:#f0f6fc; border-left: 4px solid #46b450; padding: 15px; border-radius: 4px;">';
                html += '<h4 style="margin: 0 0 10px 0; color: #1d2327;">✅ Conexión Exitosa: Plan ' + plan.name + '</h4>';
                html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; font-size: 13px;">';

                // Datos de WhatsApp
                if(plan.usage.wa_send) {
                    html += '<div><strong>WhatsApp:</strong> ' + plan.usage.wa_send.used + ' envíos</div>';
                }
                // Datos de SMS
                if(plan.usage.sms_send) {
                    var limit = plan.usage.sms_send.limit > 0 ? '/' + plan.usage.sms_send.limit : '';
                    html += '<div><strong>SMS:</strong> ' + plan.usage.sms_send.used + limit + '</div>';
                }
                // Datos de Dispositivos
                if(plan.usage.devices) {
                    html += '<div><strong>Dispositivos:</strong> ' + plan.usage.devices.used + '/' + plan.usage.devices.limit + '</div>';
                }

                html += '</div>';
                html += '<div style="margin-top: 12px;"><a href="https://smsenlinea.com/panel/billing" target="_blank" class="button button-small">Gestionar Plan / Recargar</a></div>';
                html += '</div>';

                $card.html(html).fadeIn();
            } else {
                var msg = response.data || 'Error desconocido';
                $card.html('<div style="background:#fbeaea; border-left: 4px solid #d63638; padding: 10px; color: #d63638;">❌ Error: ' + msg + '</div>').fadeIn();
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Probar Conexión');
            $card.html('<div style="padding: 10px; color: red;">Error de red o servidor.</div>').fadeIn();
        });
    });

    // Trigger Manual Cron
    $('#trigger-cron-btn').click(function() {
        var b=$(this), r=$('#cron-result');
        b.prop('disabled',true).text('Ejecutando...');
        $.post(ajaxurl,{action:'smsenlinea_trigger_cron'},function(d){
            b.prop('disabled',false).text('⚡ Ejecutar Ahora');
            if(d.success) alert('✅ ' + d.data);
            else alert('❌ Error');
        });
    });
});
</script>




