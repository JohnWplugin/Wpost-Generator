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
