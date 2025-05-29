<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acesso direto.
}

/**
 * Adiciona uma página de debug no submenu de configurações
 */
add_action('admin_menu', 'oapg_add_debug_menu');
function oapg_add_debug_menu() {
    // Adiciona submenu apenas se o debug estiver ativado
    if (defined('OAPG_DEBUG') && OAPG_DEBUG === true) {
        add_submenu_page(
            'oapg-settings', 
            'Debug WPost Generator', 
            'Debug', 
            'manage_options', 
            'oapg-debug', 
            'oapg_debug_page'
        );
    }
}

/**
 * Renderiza a página de debug
 */
function oapg_debug_page() {
    // Verifica permissões
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Obtém o contador mensal
    $contador_mes = get_option('oapg_contador_mensal', array());
    $mes_atual = date('m');
    $ano_atual = date('Y');
    $chave_mes = $ano_atual . $mes_atual;
    
    $posts_no_mes = isset($contador_mes[$chave_mes]) ? $contador_mes[$chave_mes] : 0;
    $limite_mensal = 500;
    $posts_restantes = $limite_mensal - $posts_no_mes;
    
    ?>
    <div class="wrap">
        <h1>Debug WPost Generator</h1>
        
        <div class="oapg-card">
            <h2>Logs</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=oapg-debug&action=view_logs'); ?>" class="button">Ver Logs</a>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=oapg-debug&action=clear_logs'), 'oapg_clear_logs'); ?>" class="button" onclick="return confirm('Tem certeza que deseja limpar todos os logs?');">Limpar Logs</a>
            </p>
            
            <?php
            // Exibe os logs se solicitado
            if (isset($_GET['action']) && $_GET['action'] === 'view_logs') {
                $log_dir = OAPG_PLUGIN_DIR . 'logs/';
                $log_files = glob($log_dir . '*.log');
                
                if (!empty($log_files)) {
                    echo '<div class="oapg-logs-container">';
                    foreach ($log_files as $log_file) {
                        $file_name = basename($log_file);
                        $file_content = file_exists($log_file) ? file_get_contents($log_file) : 'Arquivo de log não encontrado';
                        
                        echo '<div class="oapg-log-file">';
                        echo '<h3>' . esc_html($file_name) . '</h3>';
                        echo '<pre>' . esc_html($file_content) . '</pre>';
                        echo '</div>';
                    }
                    echo '</div>';
                } else {
                    echo '<p>Nenhum arquivo de log encontrado.</p>';
                }
            }
            
            if (isset($_GET['action']) && $_GET['action'] === 'clear_logs' && check_admin_referer('oapg_clear_logs')) {
                $log_dir = OAPG_PLUGIN_DIR . 'logs/';
                $log_files = glob($log_dir . '*.log');
                
                foreach ($log_files as $log_file) {
                    @unlink($log_file);
                }
                
                echo '<div class="notice notice-success"><p>Logs limpos com sucesso!</p></div>';
            }
            ?>
        </div>
        
        <div class="oapg-card">
            <h2>Limite Mensal de Posts (Somente Debug)</h2>
            <p>Esta informação não é visível para o usuário final.</p>
            
            <table class="widefat">
                <tr>
                    <th>Limite Mensal</th>
                    <td><?php echo $limite_mensal; ?> posts</td>
                </tr>
                <tr>
                    <th>Posts Gerados (Mês Atual)</th>
                    <td><?php echo $posts_no_mes; ?> posts</td>
                </tr>
                <tr>
                    <th>Posts Restantes</th>
                    <td><?php echo $posts_restantes; ?> posts</td>
                </tr>
            </table>
            
            <h3>Histórico por Mês</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Mês/Ano</th>
                        <th>Posts Gerados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($contador_mes)) {
                        // Ordena por mês (mais recente primeiro)
                        krsort($contador_mes);
                        
                        foreach ($contador_mes as $key => $count) {
                            $ano = substr($key, 0, 4);
                            $mes = substr($key, 4, 2);
                            $nome_mes = date("F", mktime(0, 0, 0, $mes, 1, $ano));
                            
                            echo '<tr>';
                            echo '<td>' . $nome_mes . '/' . $ano . '</td>';
                            echo '<td>' . $count . ' posts</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="2">Nenhum histórico disponível</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
            
            <!-- <p>
                <a href="php echo wp_nonce_url(admin_url('admin.php?page=oapg-debug&action=reset_monthly_counter'), 'oapg_reset_monthly_counter'); ?>" class="button" onclick="return confirm('Tem certeza que deseja reiniciar o contador mensal? Isso permitirá gerar mais posts neste mês.');">Reiniciar Contador Mensal (Somente Debug)</a>
            </p> -->
            
            <?php
            if (isset($_GET['action']) && $_GET['action'] === 'reset_monthly_counter' && check_admin_referer('oapg_reset_monthly_counter')) {
                $contador_mes[$chave_mes] = 0;
                update_option('oapg_contador_mensal', $contador_mes);
                
                echo '<div class="notice notice-success"><p>Contador mensal reiniciado com sucesso!</p></div>';
            }
            ?>
        </div>
        
        <div class="oapg-card">
            <h2>Diagnóstico do Agendamento (WP-Cron)</h2>
            <p>Esta seção mostra informações sobre o agendamento das tarefas de geração de posts.</p>
            
            <?php 
            // Verifica o status do agendamento
            if (function_exists('oapg_check_cron_schedule_status')) {
                $cron_status = oapg_check_cron_schedule_status(true);
                ?>
                <table class="widefat oapg-cron-info">
                    <tr>
                        <th>Data/Hora Atual:</th>
                        <td><?php echo $cron_status['current_time_formatted']; ?></td>
                    </tr>
                    <tr>
                        <th>Próximo Agendamento:</th>
                        <td><?php echo $cron_status['next_scheduled_formatted']; ?></td>
                    </tr>
                    <tr>
                        <th>Tempo até Próxima Execução:</th>
                        <td><?php echo $cron_status['diff_formatted']; ?></td>
                    </tr>
                    <tr>
                        <th>Frequência Configurada:</th>
                        <td><?php echo $cron_status['frequency']; ?> horas</td>
                    </tr>
                    <tr>
                        <th>WP-Cron Ativo:</th>
                        <td><?php echo $cron_status['cron_disabled'] ? '<span style="color:red">Não</span>' : '<span style="color:green">Sim</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <?php if (!$cron_status['next_scheduled']): ?>
                                <span style="color:red">Não Agendado!</span>
                            <?php elseif ($cron_status['is_overdue']): ?>
                                <span style="color:orange">Atrasado</span>
                            <?php else: ?>
                                <span style="color:green">Agendado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (isset($cron_status['found_in_array']) && $cron_status['found_in_array']): ?>
                    <tr>
                        <th>Timestamp no Array do Cron:</th>
                        <td><?php echo isset($cron_status['scheduled_time']) ? $cron_status['scheduled_time'] : 'N/A'; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($cron_status['timestamp_mismatch']) && $cron_status['timestamp_mismatch']): ?>
                    <tr>
                        <th>Alerta:</th>
                        <td style="color:red">Discrepância entre wp_next_scheduled e _get_cron_array (<?php echo $cron_status['mismatch_diff']; ?> segundos)</td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <div class="oapg-cron-actions">
                    <h3>Ações de Diagnóstico</h3>
                    <p>Use estes botões para testar ou corrigir problemas com o agendamento.</p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('oapg_debug_cron_actions'); ?>
                        <button type="submit" name="oapg_debug_action" value="force_cron" class="button button-primary">Forçar Execução Agora</button>
                        <button type="submit" name="oapg_debug_action" value="fix_cron" class="button button-secondary">Reparar Agendamento</button>
                        <button type="submit" name="oapg_debug_action" value="rebuild_schedule" class="button button-secondary">Reconstruir Agendamento</button>
                    </form>
                    
                    <?php
                    // Processa ações de formulário
                    if (isset($_POST['oapg_debug_action']) && check_admin_referer('oapg_debug_cron_actions')) {
                        $action = sanitize_text_field($_POST['oapg_debug_action']);
                        
                        switch ($action) {
                            case 'force_cron':
                                echo "<div class='notice notice-info'><p>Executando função de geração manualmente...</p></div>";
                                if (function_exists('oapg_generate_posts')) {
                                    oapg_log_debug("==== EXECUÇÃO MANUAL DO CRON INICIADA (via Debug) ====");
                                    oapg_generate_posts();
                                    oapg_log_debug("==== EXECUÇÃO MANUAL DO CRON CONCLUÍDA (via Debug) ====");
                                    echo "<div class='notice notice-success'><p>Função executada com sucesso!</p></div>";
                                    echo "<script>setTimeout(function() { window.location.reload(); }, 5000);</script>";
                                } else {
                                    echo "<div class='notice notice-error'><p>Função 'oapg_generate_posts' não encontrada!</p></div>";
                                }
                                break;
                                
                            case 'fix_cron':
                                echo "<div class='notice notice-info'><p>Reparando agendamento...</p></div>";
                                if (function_exists('wp_clear_scheduled_hook')) {
                                    wp_clear_scheduled_hook('oapg_generate_posts_event');
                                    $frequency = absint(get_option('oapg_post_frequency', 1));
                                    $next_run = time() + ($frequency * 3600);
                                    wp_schedule_event($next_run, 'oapg_custom', 'oapg_generate_posts_event');
                                    oapg_log_debug("Agendamento do cron reparado manualmente via Debug. Próxima execução: " . date('Y-m-d H:i:s', $next_run));
                                    echo "<div class='notice notice-success'><p>Agendamento reparado com sucesso!</p></div>";
                                    echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
                                } else {
                                    echo "<div class='notice notice-error'><p>Função 'wp_clear_scheduled_hook' não encontrada!</p></div>";
                                }
                                break;
                                
                            case 'rebuild_schedule':
                                echo "<div class='notice notice-info'><p>Reconstruindo agendamento personalizado...</p></div>";
                                
                                // Recria o intervalo personalizado do cron
                                $frequency = absint(get_option('oapg_post_frequency', 1));
                                add_filter('cron_schedules', function($schedules) use ($frequency) {
                                    $schedules['oapg_custom'] = array(
                                        'interval' => $frequency * 3600,
                                        'display'  => sprintf(__('A cada %d horas', 'openai-auto-post-generator'), $frequency),
                                    );
                                    return $schedules;
                                });
                                
                                // Reagenda
                                wp_clear_scheduled_hook('oapg_generate_posts_event');
                                $next_run = time() + ($frequency * 3600);
                                $result = wp_schedule_event($next_run, 'oapg_custom', 'oapg_generate_posts_event');
                                
                                if ($result) {
                                    oapg_log_debug("Agendamento e intervalo personalizado reconstruídos via Debug. Próxima execução: " . date('Y-m-d H:i:s', $next_run));
                                    echo "<div class='notice notice-success'><p>Agendamento reconstruído com sucesso!</p></div>";
                                } else {
                                    echo "<div class='notice notice-error'><p>Falha ao reconstruir o agendamento!</p></div>";
                                }
                                
                                echo "<script>setTimeout(function() { window.location.reload(); }, 2000);</script>";
                                break;
                        }
                    }
                    ?>
                </div>
                
                <div class="oapg-cron-status">
                    <h3>Informações do Sistema</h3>
                    
                    <table class="widefat">
                        <tr>
                            <th>Versão do WordPress:</th>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <th>Servidor Web:</th>
                            <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                        </tr>
                        <tr>
                            <th>PHP Version:</th>
                            <td><?php echo phpversion(); ?></td>
                        </tr>
                        <tr>
                            <th>WP_CRON_DISABLED:</th>
                            <td><?php echo defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? 'Sim' : 'Não'; ?></td>
                        </tr>
                        <tr>
                            <th>ALTERNATE_WP_CRON:</th>
                            <td><?php echo defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON ? 'Sim' : 'Não'; ?></td>
                        </tr>
                        <tr>
                            <th>Database Size:</th>
                            <td>
                                <?php 
                                global $wpdb;
                                $sql = "SELECT sum(round(((data_length + index_length) / 1024 / 1024), 2)) as 'size'
                                        FROM information_schema.TABLES 
                                        WHERE table_schema = '{$wpdb->dbname}'";
                                $result = $wpdb->get_row($sql);
                                echo isset($result->size) ? $result->size . ' MB' : 'N/A'; 
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Posts:</th>
                            <td><?php echo wp_count_posts()->publish + wp_count_posts()->draft; ?></td>
                        </tr>
                    </table>
                </div>
            <?php } else { ?>
                <p>Função de diagnóstico do agendamento não disponível.</p>
            <?php } ?>
        </div>
    </div>
    
    <style>
    .oapg-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 15px;
        margin-top: 15px;
        margin-bottom: 15px;
    }
    .oapg-logs-container {
        max-height: 500px;
        overflow-y: auto;
    }
    .oapg-log-file {
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    .oapg-log-file pre {
        background: #f6f6f6;
        padding: 10px;
        overflow-x: auto;
        max-height: 300px;
    }
    .oapg-cron-info {
        margin-bottom: 20px;
    }
    .oapg-cron-info th {
        width: 35%;
        text-align: left;
        padding: 8px;
    }
    .oapg-cron-info td {
        padding: 8px;
    }
    .oapg-cron-actions {
        background: #f9f9f9;
        padding: 15px;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        margin-top: 20px;
        margin-bottom: 20px;
    }
    .oapg-cron-actions h3 {
        margin-top: 0;
        margin-bottom: 10px;
    }
    .oapg-cron-status {
        margin-top: 20px;
    }
    </style>
    <?php
}

/**
 * Adiciona endpoint AJAX para reset do contador mensal
 */
add_action('wp_ajax_oapg_reset_monthly_counter', 'oapg_reset_monthly_counter_callback');
function oapg_reset_monthly_counter_callback() {
    // Verifica permissões
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permissões insuficientes.');
    }
    
    // Verifica nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'oapg_reset_monthly_counter_nonce')) {
        wp_send_json_error('Verificação de segurança falhou.');
    }
    
    // Obtém o contador mensal
    $contador_mes = get_option('oapg_contador_mensal', array());
    $mes_atual = date('m');
    $ano_atual = date('Y');
    $chave_mes = $ano_atual . $mes_atual;
    
    // Reseta o contador do mês atual
    $contador_mes[$chave_mes] = 0;
    update_option('oapg_contador_mensal', $contador_mes);
    
    wp_send_json_success('Contador mensal reiniciado com sucesso!');
}
