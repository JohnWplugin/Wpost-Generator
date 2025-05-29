<?php
// AJAX handler para obter o progresso atual
add_action('wp_ajax_oapg_get_progress', 'oapg_get_progress_callback');
function oapg_get_progress_callback() {
    // Verifica o nonce para segurança
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'oapg_get_progress_nonce')) {
        wp_send_json_error('Verificação de segurança falhou.');
        return;
    }
    
    // Obtém os dados atuais
    $total_generated = absint(get_option('oapg_total_generated', 0));
    $max_posts = absint(get_option('oapg_max_posts_to_generate', 50));
    $posts_remaining = $max_posts - $total_generated;
    $is_paused = get_option('oapg_generation_paused', false);
    
    // Obtém os dados do limite mensal
    $contador_mes = get_option('oapg_contador_mensal', array());
    $mes_atual = date('m');
    $ano_atual = date('Y');
    $chave_mes = $ano_atual . $mes_atual;
    $posts_no_mes = isset($contador_mes[$chave_mes]) ? $contador_mes[$chave_mes] : 0;
    $limite_mensal = 500;
    $posts_restantes_mes = $limite_mensal - $posts_no_mes;
    
    // Verifica se o limite mensal foi atingido
    $limite_mensal_atingido = ($posts_no_mes >= $limite_mensal);
    
    // Verifica se o limite do ciclo atual foi atingido
    $limite_ciclo_atingido = ($total_generated >= $max_posts);
    
    // Calcula a porcentagem do progresso atual (do agendamento específico)
    $percentage = ($max_posts > 0) ? round(($total_generated / $max_posts) * 100) : 0;
    
    // Retorna os dados como JSON
    wp_send_json_success(array(
        'total_generated' => $total_generated,
        'max_posts' => $max_posts,
        'posts_remaining' => $posts_remaining,
        'is_paused' => $is_paused,
        'percentage' => $percentage,
        'posts_no_mes' => $posts_no_mes,
        'limite_mensal' => $limite_mensal,
        'posts_restantes_mes' => $posts_restantes_mes,
        'limite_mensal_atingido' => $limite_mensal_atingido,
        'limite_ciclo_atingido' => $limite_ciclo_atingido
    ));
}

// AJAX handler para alternar o estado de geração
add_action('wp_ajax_oapg_toggle_generation', 'oapg_toggle_generation_callback');
function oapg_toggle_generation_callback() {
    // Verifica o nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'oapg_toggle_generation_nonce')) {
        wp_send_json_error('Erro de segurança.');
        return;
    }
    
    $action = isset($_POST['toggle_action']) ? sanitize_text_field($_POST['toggle_action']) : '';
    
    if ($action === 'stop') {
        // Pausa a geração
        update_option('oapg_generation_paused', true);
        
        // Zera o contador de posts gerados no ciclo atual, mas mantém o contador mensal
        update_option('oapg_posts_generated', 0);
        
        $message = 'Geração de posts pausada com sucesso! O contador do ciclo atual foi zerado.';
        $is_paused = true;
    } else if ($action === 'start') {
        // Inicia a geração
        update_option('oapg_generation_paused', false);
        
        // Reinicia todos os contadores para um novo ciclo completo
        update_option('oapg_posts_generated', 0);
        update_option('oapg_total_generated', 0);
        
        $message = 'Geração de posts iniciada com sucesso! Todos os contadores foram reiniciados para um novo ciclo.';
        $is_paused = false;
    } else if ($action === 'reset') {
        // Reinicia o contador total
        update_option('oapg_total_generated', 0);
        update_option('oapg_posts_generated', 0);
        update_option('oapg_generation_paused', false);
        
        $message = 'Contadores reiniciados com sucesso! Um novo ciclo foi iniciado.';
        $is_paused = false;
    } else {
        wp_send_json_error('Ação inválida.');
        return;
    }
    
    // Obtém os dados do limite mensal
    $contador_mes = get_option('oapg_contador_mensal', array());
    $mes_atual = date('m');
    $ano_atual = date('Y');
    $chave_mes = $ano_atual . $mes_atual;
    $posts_no_mes = isset($contador_mes[$chave_mes]) ? $contador_mes[$chave_mes] : 0;
    $limite_mensal = 500;
    $posts_restantes_mes = $limite_mensal - $posts_no_mes;
    
    // Verifica se o limite mensal foi atingido
    $limite_mensal_atingido = ($posts_no_mes >= $limite_mensal);
    
    // Obtém os dados atualizados
    $total_generated = absint(get_option('oapg_total_generated', 0));
    $max_posts = absint(get_option('oapg_max_posts_to_generate', 50));
    $posts_generated = absint(get_option('oapg_posts_generated', 0));
    $posts_remaining = $max_posts - $total_generated;
    
    // Verifica se o limite do ciclo atual foi atingido
    $limite_ciclo_atingido = ($total_generated >= $max_posts);
    
    $percentage = ($max_posts > 0) ? round(($total_generated / $max_posts) * 100) : 0;
    
    wp_send_json_success(array(
        'message' => $message,
        'is_paused' => $is_paused,
        'total_generated' => $total_generated,
        'max_posts' => $max_posts,
        'posts_generated' => $posts_generated,
        'posts_remaining' => $posts_remaining,
        'percentage' => $percentage,
        'posts_no_mes' => $posts_no_mes,
        'limite_mensal' => $limite_mensal,
        'posts_restantes_mes' => $posts_restantes_mes,
        'limite_mensal_atingido' => $limite_mensal_atingido,
        'limite_ciclo_atingido' => $limite_ciclo_atingido
    ));
}

// AJAX handler para forçar a execução do cron
add_action('wp_ajax_oapg_force_cron', 'oapg_force_cron_callback');
function oapg_force_cron_callback() {
    // Verifica o nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'oapg_force_cron_nonce')) {
        wp_send_json_error('Erro de segurança.');
        return;
    }
    
    // Verifica se a função existe
    if (!function_exists('oapg_generate_posts')) {
        wp_send_json_error('Função de geração não encontrada.');
        return;
    }
    
    // Obtém o status atual antes da execução
    $status_antes = function_exists('oapg_check_cron_schedule_status') ? oapg_check_cron_schedule_status(true) : array();
    
    // Força a execução imediata da função principal
    try {
        // Desativa temporariamente o limite de tempo de execução
        if (function_exists('set_time_limit')) {
            set_time_limit(300); // 5 minutos
        }
        
        // Executa a função de geração
        oapg_log_debug("==== EXECUÇÃO MANUAL DO CRON INICIADA ====");
        oapg_generate_posts();
        oapg_log_debug("==== EXECUÇÃO MANUAL DO CRON CONCLUÍDA ====");
        
        // Verifica o status após a execução
        $status_depois = function_exists('oapg_check_cron_schedule_status') ? oapg_check_cron_schedule_status(true) : array();
        
        wp_send_json_success(array(
            'message' => 'Função de geração executada com sucesso!',
            'status_antes' => $status_antes,
            'status_depois' => $status_depois
        ));
    } catch (Exception $e) {
        oapg_log_error("Erro ao executar função de geração manualmente: " . $e->getMessage());
        wp_send_json_error('Erro ao executar a função: ' . $e->getMessage());
    }
}

// AJAX handler para reparar o agendamento do cron
add_action('wp_ajax_oapg_fix_cron', 'oapg_fix_cron_callback');
function oapg_fix_cron_callback() {
    // Verifica o nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'oapg_fix_cron_nonce')) {
        wp_send_json_error('Erro de segurança.');
        return;
    }
    
    // Verifica o status atual antes da execução
    $status_antes = function_exists('oapg_check_cron_schedule_status') ? oapg_check_cron_schedule_status(true) : array();
    
    try {
        // Limpa o agendamento existente (se houver)
        wp_clear_scheduled_hook('oapg_generate_posts_event');
        
        // Agenda novamente
        $frequency = absint(get_option('oapg_post_frequency', 1));
        $next_run = time() + ($frequency * 3600);
        wp_schedule_event($next_run, 'oapg_custom', 'oapg_generate_posts_event');
        
        oapg_log_debug("Agendamento do cron reparado manualmente. Próxima execução: " . date('Y-m-d H:i:s', $next_run));
        
        // Verifica o status após o reparo
        $status_depois = function_exists('oapg_check_cron_schedule_status') ? oapg_check_cron_schedule_status(true) : array();
        
        wp_send_json_success(array(
            'message' => 'Agendamento reparado com sucesso! Próxima execução: ' . date('Y-m-d H:i:s', $next_run),
            'status_antes' => $status_antes,
            'status_depois' => $status_depois
        ));
    } catch (Exception $e) {
        oapg_log_error("Erro ao reparar agendamento: " . $e->getMessage());
        wp_send_json_error('Erro ao reparar agendamento: ' . $e->getMessage());
    }
}
