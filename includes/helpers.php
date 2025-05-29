<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acesso direto.
}

/**
 * Função para log de erros do plugin.
 *
 * @param string $message Mensagem de erro.
 */
function oapg_log_error( $message ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
        error_log( '[WPost Generator] ' . $message );
    }
    
    if ( defined( 'OAPG_DEBUG' ) && OAPG_DEBUG === true ) {
        oapg_write_debug_log( 'ERROR: ' . $message );
    }
}

/**
 * Função para log de debug do plugin.
 *
 * @param string $message Mensagem de debug.
 */
function oapg_log_debug( $message ) {
    if ( defined( 'OAPG_DEBUG' ) && OAPG_DEBUG === true ) {
        oapg_write_debug_log( 'DEBUG: ' . $message );
    }
}

/**
 * Função para log de chamadas de API.
 *
 * @param string $endpoint Endpoint da API.
 * @param array $request_data Dados da requisição.
 * @param array|WP_Error $response Resposta da API.
 */
function oapg_log_api_call( $endpoint, $request_data, $response ) {
    if ( defined( 'OAPG_DEBUG' ) && OAPG_DEBUG === true ) {
        $log = "API CALL: {$endpoint}\n";
        $log .= "REQUEST: " . wp_json_encode( $request_data, JSON_PRETTY_PRINT ) . "\n";
        
        if ( is_wp_error( $response ) ) {
            $log .= "RESPONSE ERROR: " . $response->get_error_message() . "\n";
        } else {
            $body = wp_remote_retrieve_body( $response );
            $log .= "RESPONSE CODE: " . wp_remote_retrieve_response_code( $response ) . "\n";
            $log .= "RESPONSE: " . $body . "\n";
        }
        
        oapg_write_debug_log( $log );
    }
}

/**
 * Escreve mensagem no arquivo de log.
 *
 * @param string $message Mensagem a ser registrada.
 */
function oapg_write_debug_log( $message ) {
    // Verifica e cria o diretório de logs se necessário
    oapg_ensure_log_directory();
    
    $log_file = OAPG_PLUGIN_DIR . 'logs/debug-' . date('Y-m-d') . '.log';
    $timestamp = date( '[Y-m-d H:i:s]' );
    file_put_contents( $log_file, $timestamp . ' ' . $message . "\n", FILE_APPEND );
}

/**
 * Verifica se o diretório de logs existe e cria se necessário.
 */
function oapg_ensure_log_directory() {
    $log_dir = OAPG_PLUGIN_DIR . 'logs';
    
    // Verifica se a constante está definida
    if (!defined('OAPG_PLUGIN_DIR')) {
        // Tenta determinar o diretório do plugin
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        define('OAPG_PLUGIN_DIR', $plugin_dir);
        $log_dir = $plugin_dir . 'logs';
    }
    
    // Cria o diretório de logs se não existir
    if (!file_exists($log_dir)) {
        // Tenta criar usando wp_mkdir_p
        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p($log_dir);
        } else {
            // Fallback para o mkdir nativo do PHP
            @mkdir($log_dir, 0755, true);
        }
        
        // Cria um arquivo .htaccess para proteger os logs
        if (file_exists($log_dir)) {
            $htaccess = $log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Order deny,allow\nDeny from all");
            }
        }
    }
    
    return file_exists($log_dir) && is_dir($log_dir) && is_writable($log_dir);
}

/**
 * Verifica se o limite mensal de posts foi atingido.
 *
 * @return bool True se ainda pode gerar posts, False se o limite foi atingido.
 */
function oapg_check_monthly_limit() {
    $limit_mensal = 500; 
    
    $mes_atual = date('m');
    $ano_atual = date('Y');
    
    $contador_mes = get_option('oapg_contador_mensal', array());
    
    // Cria a chave para o mês atual (formato: AAAAMM)
    $chave_mes = $ano_atual . $mes_atual;
    
    if (!isset($contador_mes[$chave_mes])) {
        $contador_mes[$chave_mes] = 0;
        
        if (count($contador_mes) > 3) {
            krsort($contador_mes);
            $contador_mes = array_slice($contador_mes, 0, 3, true);
        }
        
        update_option('oapg_contador_mensal', $contador_mes);
    }
    
    $contador_atual = $contador_mes[$chave_mes];
    
    // Verifica se o limite foi atingido
    $pode_gerar = $contador_atual < $limit_mensal;
    
    if (!$pode_gerar) {
        oapg_log_debug("LIMITE MENSAL: Limite de {$limit_mensal} posts atingido para o mês {$mes_atual}/{$ano_atual}. Total gerado: {$contador_atual}");
    } else {
        oapg_log_debug("LIMITE MENSAL: {$contador_atual}/{$limit_mensal} posts gerados este mês. Restantes: " . ($limit_mensal - $contador_atual));
    }
    
    return $pode_gerar;
}

/**
 * Incrementa o contador mensal de posts.
 */
function oapg_increment_monthly_counter() {
    // Obtém o mês e ano atual
    $mes_atual = date('m');
    $ano_atual = date('Y');
    
    $chave_mes = $ano_atual . $mes_atual;
    
    $contador_mes = get_option('oapg_contador_mensal', array());
    
    if (!isset($contador_mes[$chave_mes])) {
        $contador_mes[$chave_mes] = 0;
    }
    
    $contador_mes[$chave_mes]++;
    
    oapg_log_debug("CONTADOR MENSAL: Incrementado para {$contador_mes[$chave_mes]} posts em {$mes_atual}/{$ano_atual}");
    
    update_option('oapg_contador_mensal', $contador_mes);
    
    return $contador_mes[$chave_mes];
}

/**
 * Verifica o status atual do agendamento do wp-cron
 * 
 * @param bool $force_log Se true, força o log mesmo com o debug desativado
 * @return array Informações sobre o agendamento
 */
function oapg_check_cron_schedule_status($force_log = false) {
    $next_scheduled = wp_next_scheduled('oapg_generate_posts_event');
    $current_time = current_time('timestamp');
    $cron_array = _get_cron_array();
    $result = array(
        'next_scheduled' => $next_scheduled,
        'next_scheduled_formatted' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Não agendado',
        'current_time' => $current_time,
        'current_time_formatted' => date('Y-m-d H:i:s', $current_time),
        'diff_seconds' => $next_scheduled ? $next_scheduled - $current_time : 0,
        'is_overdue' => $next_scheduled && $next_scheduled < $current_time,
        'frequency' => absint(get_option('oapg_post_frequency', 1)),
        'cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        'found_in_array' => false,
        'hooks_count' => 0
    );
    
    $result['diff_formatted'] = '';
    if ($result['diff_seconds'] > 0) {
        $hours = floor($result['diff_seconds'] / 3600);
        $minutes = floor(($result['diff_seconds'] / 60) % 60);
        $result['diff_formatted'] = "{$hours}h {$minutes}m";
    } elseif ($result['diff_seconds'] < 0) {
        $abs_diff = abs($result['diff_seconds']);
        $hours = floor($abs_diff / 3600);
        $minutes = floor(($abs_diff / 60) % 60);
        $result['diff_formatted'] = "-{$hours}h {$minutes}m (atrasado)";
    }
    
    // Verifica se o hook está listado no array de cron
    if (is_array($cron_array)) {
        $result['hooks_count'] = count($cron_array);
        
        foreach ($cron_array as $timestamp => $hooks) {
            if (isset($hooks['oapg_generate_posts_event'])) {
                $result['found_in_array'] = true;
                $result['scheduled_timestamp'] = $timestamp;
                $result['scheduled_time'] = date('Y-m-d H:i:s', $timestamp);
                
                // Verifica se há discrepância entre wp_next_scheduled e _get_cron_array
                if ($timestamp != $next_scheduled) {
                    $result['timestamp_mismatch'] = true;
                    $result['mismatch_diff'] = abs($timestamp - $next_scheduled);
                }
            }
        }
    }
    
    if ($force_log || (defined('OAPG_DEBUG') && OAPG_DEBUG === true)) {
        oapg_log_debug("==== STATUS DO AGENDAMENTO WP-CRON ====");
        oapg_log_debug("Data/hora atual: {$result['current_time_formatted']}");
        oapg_log_debug("Próximo agendamento: {$result['next_scheduled_formatted']}");
        
        if ($result['diff_seconds'] !== 0) {
            oapg_log_debug("Tempo até o próximo agendamento: {$result['diff_formatted']}");
        }
        
        oapg_log_debug("Frequência configurada: {$result['frequency']} horas");
        oapg_log_debug("WP-Cron desativado: " . ($result['cron_disabled'] ? 'Sim' : 'Não'));
        oapg_log_debug("Hook encontrado no array: " . ($result['found_in_array'] ? 'Sim' : 'Não'));
        
        if (isset($result['timestamp_mismatch']) && $result['timestamp_mismatch']) {
            oapg_log_debug("ALERTA: Há uma discrepância entre wp_next_scheduled e _get_cron_array!");
            oapg_log_debug("Diferença: {$result['mismatch_diff']} segundos");
        }
        
        oapg_log_debug("Total de hooks no cron: {$result['hooks_count']}");
        oapg_log_debug("=========================================");
    }
    
    return $result;
}
