<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$table_sessions = $wpdb->prefix . 'smsenlinea_sessions';

// --- LÓGICA DE PESTAÑAS Y DATOS ---
$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

// Si estamos en la pestaña 'carts' (Carritos), consultamos la base de datos
$carts_list = [];
if ( $active_tab == 'carts' ) {
    // Obtenemos los últimos 50 carritos, ordenados por fecha
    $carts_list = $wpdb->get_results( "SELECT * FROM $table_sessions ORDER BY created_at DESC LIMIT 50" );
}

// Lógica para estadísticas (Pestaña Reportes)
$stats = [ 'total' => 0, 'recovered' => 0, 'lost' => 0, 'money' => 0 ];
if ( $active_tab == 'reports' ) {
    $stats['total']     = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sessions" );
    $stats['recovered'] = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sessions WHERE status = 'recovered'" );
    $stats['lost']      = $wpdb->get_var( "SELECT COUNT(*) FROM $table_sessions WHERE status = 'failed_send' OR status = 'rejected'" );
    $logs = get_option( 'smsenlinea_webhook_logs', [] );
}
?>

<style>
    .sms-wrap { max-width: 1050px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
    .sms-header { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
    .sms-header h1 { margin: 0; font-size: 24px; color: #1d2327; font-weight: 600; }
    .sms-badge { background: #2271b1; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
    
    /* Navegación */
    .sms-nav { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 1px solid #c3c4c7; }
    .sms-nav a { text-decoration: none; color: #50575e; padding: 10px 15px; border: 1px solid transparent; border-bottom: none; border-radius: 4px 4px 0 0; font-weight: 500; transition: all 0.2s; }
    .sms-nav a:hover { color: #2271b1; background: #f6f7f7; }
    .sms-nav a.active { background: #fff; border-color: #c3c4c7; border-bottom-color: #fff; color: #1d2327; font-weight: 600; margin-bottom: -1px; }

    /* Tarjetas */
    .sms-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .sms-card h3 { margin-top: 0; border-bottom: 1px solid #f0f0f1; padding-bottom: 10px; margin-bottom: 15px; color: #2c3338; }

    /* Formularios */
    .sms-form-group { margin-bottom: 15px; }
    .sms-form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #1d2327; }
    .sms-form-group input[type="text"], .sms-form-group input[type="password"], .sms-form-group input[type="number"], .sms-form-group textarea, .sms-form-group select { width: 100%; max-width: 500px; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; }
    .sms-helper { font-size: 13px; color: #646970; margin-top: 5px; font-style: italic; }

    /* Tablas */
    .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
    .st-abandoned { background: #ffe6e6; color: #d63638; } /* Rojo suave */
    .st-contacted { background: #fff8e5; color: #996800; } /* Amarillo */
    .st-recovered { background: #e7f7ed; color: #008a20; } /* Verde */
    .st-failed    { background: #333; color: #fff; }

    .btn-action { cursor: pointer; font-size: 12px; }
</style>

<div class="wrap sms-wrap">
    
    <div class="sms-header">
        <div>
            <h1>SmsEnLinea Pro</h1>
            <p>Conecta tu WhatsApp y recupera ventas automáticamente.</p>
        </div>
        <span class="sms-badge">VERSIÓN 2.0 PRO</span>
    </div>

    <div class="sms-nav">
        <a href="?page=smsenlinea-pro&tab=general" class="<?php echo $active_tab == 'general' ? 'active' : ''; ?>">⚙️ General</a>
        <a href="?page=smsenlinea-pro&tab=strategy" class="<?php echo $active_tab == 'strategy' ? 'active' : ''; ?>">🧠 Estrategia</a>
        <a href="?page=smsenlinea-pro&tab=wc_settings" class="<?php echo $active_tab == 'wc_settings' ? 'active' : ''; ?>">🛍️ WooCommerce</a>
        <a href="?page=smsenlinea-pro&tab=carts" class="<?php echo $active_tab == 'carts' ? 'active' : ''; ?>">🛒 Carritos en Vivo</a>
        <a href="?page=smsenlinea-pro&tab=reports" class="<?php echo $active_tab == 'reports' ? 'active' : ''; ?>">📊 Reportes y Logs</a>
    </div>

    <form method="post" action="options.php">
        <?php
        // Renderizamos grupos de opciones según la pestaña activa
        if ( $active_tab == 'general' ) {
            settings_fields( 'smsenlinea_settings_group' );
            $opts = get_option( 'smsenlinea_settings' );
        } elseif ( $active_tab == 'strategy' ) {
            settings_fields( 'smsenlinea_settings_group' ); // Usamos el mismo grupo general
            $opts = get_option( 'smsenlinea_strategy_settings' );
        } elseif ( $active_tab == 'wc_settings' ) {
            settings_fields( 'smsenlinea_settings_group' );
            $opts = get_option( 'smsenlinea_wc_settings' );
        }
        ?>

        <?php if ( $active_tab == 'general' ) : ?>
            <div class="sms-card">
                <h3>Credenciales API</h3>
                <div class="sms-form-group">
                    <label>API Secret</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <input type="password" id="sms_api_secret" name="smsenlinea_settings[api_secret]" value="<?php echo esc_attr( $opts['api_secret'] ?? '' ); ?>" style="flex-grow: 1;">
                        <button type="button" id="btn_test_connection" class="button button-secondary">Probar Conexión</button>
                    </div>
                    <p class="sms-helper">Obtenido de Herramientas -> API Keys en SmsEnLinea.</p>
                    <div id="connection_status_card" style="margin-top: 15px; display: none;"></div>
                </div>
            </div>

            <div class="sms-card">
                <h3>Configuración de Envío</h3>
                <div class="sms-form-group">
                    <label>Modo de Envío</label>
                    <select name="smsenlinea_settings[sending_mode]">
                        <option value="devices" <?php selected( $opts['sending_mode'] ?? 'devices', 'devices' ); ?>>Dispositivos Vinculados (Android)</option>
                        <option value="credits" <?php selected( $opts['sending_mode'] ?? '', 'credits' ); ?>>Gateway / Créditos</option>
                    </select>
                </div>
                <div class="sms-form-group">
                    <label>ID de Dispositivo (Device ID)</label>
                    <input type="text" name="smsenlinea_settings[device_id]" value="<?php echo esc_attr( $opts['device_id'] ?? '' ); ?>">
                    <p class="sms-helper">Solo si usas modo 'Dispositivos'.</p>
                </div>
                <div class="sms-form-group">
                    <label>Cuenta de WhatsApp (Unique ID)</label>
                    <input type="text" name="smsenlinea_settings[wa_account_unique]" value="<?php echo esc_attr( $opts['wa_account_unique'] ?? '' ); ?>">
                    <p class="sms-helper">Requerido para enviar WhatsApps.</p>
                </div>
            </div>

            <div class="sms-card">
                <h3>Webhook (Recepción de Mensajes)</h3>
                <div class="sms-form-group">
                    <label>URL del Webhook</label>
                    <input type="text" readonly value="<?php echo home_url( '/wp-json/smsenlinea/v1/webhook' ); ?>" style="background: #f0f0f1;">
                    <p class="sms-helper">Copia esto y pégalo en SmsEnLinea -> Webhooks.</p>
                </div>
                <div class="sms-form-group">
                    <label>Webhook Secret</label>
                    <?php $secret = $opts['webhook_secret'] ?? wp_generate_password( 20, false ); ?>
                    <input type="text" name="smsenlinea_settings[webhook_secret]" value="<?php echo esc_attr( $secret ); ?>">
                    <p class="sms-helper">Este secreto valida que los mensajes vengan realmente de SmsEnLinea.</p>
                </div>
            </div>

        <?php elseif ( $active_tab == 'strategy' ) : ?>
            <div class="sms-card">
                <h3>Tiempos y Reglas</h3>
                <div class="sms-form-group">
                    <label>Esperar antes de contactar (minutos)</label>
                    <input type="number" name="smsenlinea_settings[abandoned_timeout]" value="<?php echo esc_attr( get_option('smsenlinea_settings')['abandoned_timeout'] ?? 15 ); ?>" min="1">
                    <p class="sms-helper">Tiempo que debe pasar desde que el cliente abandona hasta que enviamos el mensaje.</p>
                </div>
            </div>

            <div class="sms-card">
                <h3>El Mensaje Rompehielo 🧊</h3>
                <div class="sms-form-group">
                    <label>Mensaje Inicial</label>
                    <textarea name="smsenlinea_strategy_settings[icebreaker_msg]" rows="4" id="msg_icebreaker"><?php echo esc_textarea( $opts['icebreaker_msg'] ?? '' ); ?></textarea>
                    <div style="margin-top: 5px;">
                        <button type="button" class="button button-small emoji-btn" data-target="msg_icebreaker">😀 Insertar Emoji</button>
                    </div>
                    <p class="sms-helper">Variables: <code>{name}</code>, <code>{site_name}</code>, <code>{total}</code>, <code>{checkout_url}</code></p>
                </div>
            </div>

            <div class="sms-card" style="border-left: 4px solid #f0b849;">
                <h3>🛠️ Zona de Pruebas</h3>
                <p>Si no quieres esperar 5 minutos, fuerza la ejecución del sistema ahora mismo.</p>
                <button type="button" id="trigger-cron-btn" class="button button-secondary">⚡ Ejecutar Tareas Programadas Ahora</button>
                <span id="cron-result" style="margin-left: 10px; font-weight: bold;"></span>
            </div>

        <?php elseif ( $active_tab == 'wc_settings' ) : ?>
            <div class="sms-card">
                <h3>Notificaciones de Estado de Pedido</h3>
                <p>Envía mensajes automáticos cuando cambia el estado del pedido en WooCommerce.</p>
                
                <div class="sms-form-group">
                    <label>Pedido Completado</label>
                    <textarea name="smsenlinea_wc_settings[msg_completed]" rows="3"><?php echo esc_textarea( $opts['msg_completed'] ?? '' ); ?></textarea>
                </div>
                <div class="sms-form-group">
                    <label>Pedido Fallido (Recuperación Inmediata)</label>
                    <textarea name="smsenlinea_wc_settings[msg_failed]" rows="3"><?php echo esc_textarea( $opts['msg_failed'] ?? '' ); ?></textarea>
                </div>
            </div>

        <?php elseif ( $active_tab == 'carts' ) : ?>
            <div class="sms-card">
                <h3>🛒 Gestión de Carritos Abandonados</h3>
                <p>Aquí aparecen en tiempo real los clientes que escriben sus datos en el Checkout pero no compran.</p>
                
                <?php if ( empty( $carts_list ) ) : ?>
                    <div style="padding: 20px; text-align: center; color: #666; background: #f9f9f9; border-radius: 4px;">
                        <p>📭 No hay carritos registrados todavía. Ve a tu tienda e intenta hacer una compra sin finalizarla para probar.</p>
                    </div>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Teléfono</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $carts_list as $cart ) : ?>
                                <tr>
                                    <td><?php echo date_i18n( 'd M H:i', strtotime( $cart->created_at ) ); ?></td>
                                    <td>
                                        <strong><?php echo esc_html( $cart->customer_name ); ?></strong><br>
                                        <small><?php echo esc_html( $cart->email ); ?></small>
                                    </td>
                                    <td><?php echo esc_html( $cart->phone ); ?></td>
                                    <td><?php echo esc_html( $cart->cart_total . ' ' . $cart->currency ); ?></td>
                                    <td>
                                        <?php 
                                        $status_label = $cart->status;
                                        $css_class = 'st-abandoned';
                                        
                                        if ( $cart->status == 'contacted' ) { $status_label = 'Mensaje Enviado'; $css_class = 'st-contacted'; }
                                        elseif ( $cart->status == 'recovered' ) { $status_label = '✅ Recuperado'; $css_class = 'st-recovered'; }
                                        elseif ( $cart->status == 'failed_send' ) { $status_label = '❌ Error Envío'; $css_class = 'st-failed'; }
                                        elseif ( $cart->status == 'abandoned' ) { $status_label = '⏳ Esperando'; $css_class = 'st-abandoned'; }
                                        ?>
                                        <span class="status-badge <?php echo $css_class; ?>"><?php echo esc_html( $status_label ); ?></span>
                                    </td>
                                    <td>
                                        <?php if ( $cart->status !== 'recovered' ) : ?>
                                            <button type="button" class="button button-small button-primary btn-manual-recovery" data-id="<?php echo $cart->id; ?>">
                                                📩 Enviar Ahora
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #aaa;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ( $active_tab == 'reports' ) : ?>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
                <div class="sms-card" style="text-align: center; border-left: 4px solid #2271b1;">
                    <h2 style="font-size: 30px; margin: 10px 0;"><?php echo $stats['total']; ?></h2>
                    <p style="margin: 0; color: #666;">Carritos Detectados</p>
                </div>
                <div class="sms-card" style="text-align: center; border-left: 4px solid #46b450;">
                    <h2 style="font-size: 30px; margin: 10px 0;"><?php echo $stats['recovered']; ?></h2>
                    <p style="margin: 0; color: #666;">Ventas Recuperadas</p>
                </div>
                <div class="sms-card" style="text-align: center; border-left: 4px solid #d63638;">
                    <h2 style="font-size: 30px; margin: 10px 0;"><?php echo $stats['lost']; ?></h2>
                    <p style="margin: 0; color: #666;">No Convertidos</p>
                </div>
            </div>

            <div class="sms-card">
                <h3>📡 Monitor de Tráfico (Webhooks)</h3>
                <p class="description">Registro técnico de mensajes entrantes y respuestas.</p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Nivel</th>
                            <th>Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $logs ) ) : ?>
                            <tr><td colspan="3">Sin actividad reciente.</td></tr>
                        <?php else : ?>
                            <?php foreach ( $logs as $log ) : 
                                $color = ($log['level']=='error') ? 'color:red;' : ''; 
                            ?>
                                <tr style="<?php echo $color; ?>">
                                    <td><?php echo esc_html( $log['time'] ); ?></td>
                                    <td><strong><?php echo esc_html( strtoupper($log['level']) ); ?></strong></td>
                                    <td><?php echo esc_html( $log['msg'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <form method="post" action="" style="margin-top: 10px; text-align: right;">
                    <input type="hidden" name="smsenlinea_action_clear_logs" value="1">
                    <button type="submit" class="button button-link-delete">Limpiar Logs</button>
                </form>
            </div>

        <?php endif; ?>

        <?php if ( $active_tab != 'carts' && $active_tab != 'reports' ) submit_button( 'Guardar Configuración', 'primary sms-btn-primary' ); ?>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // 1. Emoji Picker Simple
    $('.emoji-btn').click(function(e) {
        var t = $(this).data('target');
        var i = document.getElementById(t);
        var em = prompt("Copia y pega tu emoji aquí:", "😊");
        if(em) { 
            // Insertar en la posición del cursor
            if (i.selectionStart || i.selectionStart == '0') {
                var startPos = i.selectionStart;
                var endPos = i.selectionEnd;
                i.value = i.value.substring(0, startPos) + em + i.value.substring(endPos, i.value.length);
            } else {
                i.value += em;
            }
        }
    });

    // 2. Test Conexión API
    $('#btn_test_connection').on('click', function(e) {
        e.preventDefault();
        var secret = $('#sms_api_secret').val();
        var $btn = $(this);
        var $card = $('#connection_status_card');

        if(!secret) { alert('Ingresa un API Secret.'); return; }

        $btn.prop('disabled', true).text('Verificando...');
        $card.hide().html('');

        $.post(ajaxurl, {
            action: 'smsenlinea_check_connection',
            secret: secret
        }, function(response) {
            $btn.prop('disabled', false).text('Probar Conexión');
            
            if (response.success) {
                var plan = response.data.data;
                var html = '<div style="background:#f0f6fc; border-left: 4px solid #46b450; padding: 15px;">';
                html += '<h4>✅ Conectado: Plan ' + (plan.name || 'Activo') + '</h4>';
                // Detalles rápidos
                if(plan.usage && plan.usage.wa_send) {
                     html += '<small>WhatsApp Enviados: ' + plan.usage.wa_send.used + '</small>';
                }
                html += '</div>';
                $card.html(html).fadeIn();
            } else {
                $card.html('<div style="color:red; padding:10px;">❌ Error: ' + (response.data || 'Desconocido') + '</div>').fadeIn();
            }
        });
    });

    // 3. Trigger Cron Manual (Pruebas)
    $('#trigger-cron-btn').click(function() {
        var b=$(this), r=$('#cron-result');
        b.prop('disabled',true).text('Ejecutando...');
        $.post(ajaxurl, { action:'smsenlinea_trigger_cron' }, function(d) {
            b.prop('disabled',false).text('Ejecutar Ahora');
            r.text(d.success ? '✅ ' + d.data.message : '❌ Error');
        });
    });

    // 4. [NUEVO] Envío Manual Individual desde la Tabla de Carritos
    $('.btn-manual-recovery').click(function(e) {
        e.preventDefault();
        var $btn = $(this);
        var cartId = $btn.data('id');
        
        if(!confirm('¿Seguro que quieres enviar el mensaje de recuperación a este cliente ahora mismo?')) return;
        
        $btn.prop('disabled', true).text('Enviando...');
        
        $.post(ajaxurl, {
            action: 'smsenlinea_manual_recovery',
            id: cartId,
            nonce: '<?php echo wp_create_nonce("smsenlinea_manual_action"); ?>' // Seguridad
        }, function(response) {
            if(response.success) {
                alert('¡Mensaje enviado con éxito!');
                location.reload(); // Recargar para ver el cambio de estado
            } else {
                alert('Error: ' + response.data);
                $btn.prop('disabled', false).text('Reintentar');
            }
        });
    });

});
</script>
