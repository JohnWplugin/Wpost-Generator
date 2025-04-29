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
    $log_dir = OAPG_PLUGIN_DIR . 'logs';
    if ( ! file_exists( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
    }
    
    $log_file = $log_dir . '/debug-' . date('Y-m-d') . '.log';
    $timestamp = date( '[Y-m-d H:i:s]' );
    file_put_contents( $log_file, $timestamp . ' ' . $message . "\n", FILE_APPEND );
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
