<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acesso direto.
}

/**
 * Adiciona uma agenda de cron personalizada com base na frequência definida.
 */
add_filter( 'cron_schedules', 'oapg_custom_cron_schedule' );
function oapg_custom_cron_schedule( $schedules ) {
    $frequency = absint( get_option( 'oapg_post_frequency', 1 ) );
    $schedules['oapg_custom'] = array(
        'interval' => $frequency * 3600, // Converte horas para segundos
        'display'  => sprintf( __( 'A cada %d horas', 'wpost-generator' ), $frequency ),
    );
    return $schedules;
}

/**
 * Função chamada pelo evento cron para gerar os posts.
 * 
 * @param int $force_count Força um número específico de posts a serem gerados, ignorando a configuração
 */
add_action( 'oapg_generate_posts_event', 'oapg_generate_posts' );
function oapg_generate_posts( $force_count = 0 ) {
    if (get_option('oapg_generation_paused', false)) {
        oapg_log_debug("Geração de posts pausada pelo usuário.");
        return;
    }
    
    if (!oapg_check_monthly_limit()) {
        oapg_log_debug("Limite mensal de posts atingido. Nenhum post será gerado até o próximo mês.");
        return;
    }
    
    // Verifica se o limite total definido pelo usuário foi atingido
    $max_posts = absint(get_option('oapg_max_posts_to_generate', 50));
    $total_generated = absint(get_option('oapg_total_generated', 0)); // Total acumulado desde o início
    
    if ($total_generated >= $max_posts) {
        oapg_log_debug("Limite total de posts ({$max_posts}) atingido. Nenhum post será gerado.");
        update_option('oapg_generation_paused', true);
        return;
    }
    
    $posts_per_cycle = absint(get_option('oapg_posts_per_cycle', 1));
    
    if ($force_count > 0) {
        $posts_per_cycle = $force_count;
        oapg_log_debug("Número de posts forçado: {$force_count}");
    }
    
    $posts_remaining_to_max = $max_posts - $total_generated;
    if ($posts_per_cycle > $posts_remaining_to_max) {
        $posts_per_cycle = $posts_remaining_to_max;
        oapg_log_debug("Ajustando número de posts para {$posts_per_cycle} para não ultrapassar o limite total.");
    }
    
    $post_status      = get_option('oapg_post_status', 'draft');
    $prompt_template  = get_option('oapg_prompt_template', 'Escreva um post otimizado para SEO sobre [TEMA]. Detalhes: [DETALHES].');
    $image_prompt_template = get_option('oapg_image_prompt_template', 'Uma imagem profissional representando [TEMA]');
    $generate_images = get_option('oapg_generate_images', 'yes') === 'yes';
    $footer_text = get_option('oapg_footer_text', '');
    $youtube_video = get_option('oapg_youtube_video', '');
    $post_title_template = get_option( 'oapg_post_title_template', '' );
    $post_title_position = get_option( 'oapg_post_title_position', 'inicio' );
    $ai_complemento = get_option( 'oapg_ai_complemento', '' );
    
    // Verifica se precisa usar produtos
    $usar_produtos = false;
    $produtos = array();
    
    if (strpos($prompt_template, '[produto]') !== false || strpos($image_prompt_template, '[produto]') !== false) {
        $usar_produtos = true;
        // Obtém os produtos WooCommerce se o plugin estiver ativo
        if (class_exists('WooCommerce')) {
            $produtos = oapg_get_produtos_woocommerce();
        } else {
          //print error
          oapg_log_error("WooCommerce não está ativo. Não é possível gerar posts com produtos.");
        }
        
        $ultimo_produto_index = get_option('oapg_ultimo_produto_index', -1);
    }
    
    // Extrai o ID do vídeo da URL do YouTube
    $youtube_id = oapg_extract_youtube_id($youtube_video);

    // Simplificando a lógica para garantir que sejam gerados posts_per_cycle posts
    $posts_to_generate_now = $posts_per_cycle;
    
    oapg_log_debug("Iniciando geração de posts. Total do ciclo: {$posts_per_cycle}, Gerando agora: {$posts_to_generate_now}");

    for ( $i = 0; $i < $posts_to_generate_now; $i++ ) {

        $tema = 'exemplo de tema';
        $detalhes = 'exemplo de detalhes';
        $produto_atual = null;
        
        if ($usar_produtos && !empty($produtos)) {
            $ultimo_produto_index = ($ultimo_produto_index + 1) % count($produtos);
            $produto_atual = $produtos[$ultimo_produto_index];
            update_option('oapg_ultimo_produto_index', $ultimo_produto_index);
            
            // Substitui o placeholder do produto no prompt
            $prompt_final = str_replace('[produto]', $produto_atual['nome'], $prompt_template);
            $detalhes_produto = isset($produto_atual['descricao']) ? $produto_atual['descricao'] : '';
            
            $tema = $produto_atual['nome'];
            $detalhes = $detalhes_produto;
        } else {
            $prompt_final = $prompt_template;
        }
        
        $prompt = str_replace( array( '[TEMA]', '[DETALHES]' ), array( $tema, $detalhes ), $prompt_final );
        
        if (!empty($ai_complemento)) {
            $prompt .= "\n\n" . $ai_complemento;
        }
        
        $content = oapg_generate_content( $prompt );
        
        if ( $content ) {
            $post_title = '';
            $content_without_title = $content;
            
            // Verifica se há um padrão de título comum no início do texto
            if (preg_match('/^\*\*Título:\s*(.*?)\*\*\s*\n/is', $content, $matches)) {
                $post_title = trim($matches[1]);
                $content_without_title = trim(str_replace($matches[0], '', $content));
            } elseif (preg_match('/^#\s*(.*?)\s*\n/is', $content, $matches)) {
                $post_title = trim($matches[1]);
                $content_without_title = trim(str_replace($matches[0], '', $content));
            } elseif (preg_match('/^<h1>(.*?)<\/h1>\s*\n?/is', $content, $matches)) {
                $post_title = trim(strip_tags($matches[1]));
                $content_without_title = trim(str_replace($matches[0], '', $content));
            } elseif (preg_match('/^Título:\s*(.*?)\s*\n/is', $content, $matches)) {
                $post_title = trim($matches[1]);
                $content_without_title = trim(str_replace($matches[0], '', $content));
            }
            
            // Se não encontrou um padrão de título, usa o método antigo de extrair as primeiras palavras
            if (empty($post_title)) {
                $post_title = wp_trim_words( $content, 10, '...' );
                $content_without_title = $content;
            }
            
            // Personaliza o título se o template estiver definido
            if (!empty($post_title_template)) {
                $personalizado = str_replace('[TITULO]', $post_title, $post_title_template);
                
                if ($post_title_position === 'inicio') {
                    $post_title = $personalizado;
                } else {
                    $post_title = $post_title . ' ' . str_replace('[TITULO]', '', $post_title_template);
                }
            }
            
            $youtube_embed = '';
            if ( !empty( $youtube_id ) ) {
                $youtube_embed = "\n\n" . '<div class="oapg-youtube-video">' . "\n";
                $youtube_embed .= '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . esc_attr( $youtube_id ) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>' . "\n";
                $youtube_embed .= '</div>' . "\n\n";
            }
            
            $footer_content = '';
            if ( !empty( $footer_text ) ) {
                $footer_content = "\n\n" . '<div class="oapg-footer-text">' . "\n";
                $footer_content .= wpautop( $footer_text ) . "\n";
                $footer_content .= '</div>';
            }
            
            $final_content = $content_without_title . $youtube_embed . $footer_content;
            
            $new_post = array(
                'post_title'   => $post_title,
                'post_content' => $final_content,
                'post_status'  => $post_status,
                'post_author'  => 1, // Ajuste conforme o ID do autor desejado
            );
            
            $post_id = wp_insert_post( $new_post );
            
            if ( $generate_images && $post_id ) {
                if ($usar_produtos && !empty($produto_atual)) {
                    $image_prompt = str_replace('[produto]', $produto_atual['nome'], $image_prompt_template);
                } else {
                    $image_prompt = str_replace('[TEMA]', $tema, $image_prompt_template);
                }
                
                // Gera a imagem usando a API da OpenAI
                $image_url = oapg_generate_image($image_prompt);
                
                if ($image_url) {
                    // Baixa a imagem e a adiciona à biblioteca de mídia
                    $attachment_id = oapg_download_and_attach_image($image_url, $post_title);
                    
                    if ($attachment_id) {
                        // Define a imagem como imagem destacada do post
                        set_post_thumbnail($post_id, $attachment_id);
                        oapg_log_debug("Imagem destacada definida para o post #{$post_id}");
                    } else {
                        oapg_log_error("Falha ao baixar e anexar a imagem para o post #{$post_id}");
                    }
                } else {
                    oapg_log_error("Falha ao gerar imagem para o post #{$post_id}");
                }
            }
            
            $posts_generated = $i + 1;
            update_option('oapg_posts_generated', $posts_generated);
            
            oapg_increment_monthly_counter();
            
            $total_generated++;
            update_option('oapg_total_generated', $total_generated);
            
            oapg_log_debug("Post #{$posts_generated}/{$posts_to_generate_now} gerado com sucesso. ID: {$post_id}. Total acumulado: {$total_generated}/{$max_posts}");
        }
    }
    
    // Verifica se todos os posts foram gerados
    oapg_log_debug("Ciclo de geração de posts concluído: {$posts_to_generate_now} posts foram gerados.");
}

/**
 * Função para obter produtos do WooCommerce.
 */
function oapg_get_produtos_woocommerce() {
    $produtos = array();
    
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            
            $produtos[] = array(
                'id' => $product_id,
                'nome' => get_the_title(),
                'descricao' => get_the_excerpt(),
                'preco' => $product->get_price(),
                'url' => get_permalink($product_id)
            );
        }
    }
    
    wp_reset_postdata();
    
    return $produtos;
}

/**
 * Registra o evento cron após a inicialização do WordPress
 * para garantir que o agendamento personalizado já esteja registrado.
 */
add_action( 'init', 'oapg_register_cron_event' );
function oapg_register_cron_event() {
    // Garante que o evento esteja agendado (caso não esteja, agenda usando o intervalo customizado)
    if ( ! wp_next_scheduled( 'oapg_generate_posts_event' ) ) {
        wp_schedule_event( time(), 'oapg_custom', 'oapg_generate_posts_event' );
    }
}

/**
 * Gera um post imediatamente quando as configurações são salvas
 */
add_action('update_option_oapg_posts_per_cycle', 'oapg_settings_saved', 10, 2);
add_action('update_option_oapg_post_frequency', 'oapg_settings_saved', 10, 2);
add_action('update_option_oapg_post_status', 'oapg_settings_saved', 10, 2);
function oapg_settings_saved($old_value, $new_value) {
    // Verifica se o valor realmente mudou para evitar execuções duplicadas
    if ($old_value !== $new_value) {
        $posts_per_cycle = absint(get_option('oapg_posts_per_cycle', 1));
        $max_posts = absint(get_option('oapg_max_posts_to_generate', 50));
        
        // Reinicia os contadores
        update_option('oapg_posts_generated', 0);
        update_option('oapg_total_generated', 0);
        
        oapg_log_debug("Configurações salvas. Gerando o primeiro ciclo imediatamente. Posts por ciclo: {$posts_per_cycle}");
        
        // Inicia a geração com o número configurado de posts por ciclo
        oapg_generate_posts();
        
        // Reagenda o próximo ciclo
        wp_clear_scheduled_hook('oapg_generate_posts_event');
        $next_run = time() + (absint(get_option('oapg_post_frequency', 1)) * 3600);
        wp_schedule_event($next_run, 'oapg_custom', 'oapg_generate_posts_event');
        
        oapg_log_debug("Próximo ciclo agendado para " . date('Y-m-d H:i:s', $next_run) . ". Limite máximo: {$max_posts} posts.");
    }
}

/**
 * Adiciona uma seção de status na página de configurações
 */
add_action('admin_init', 'oapg_add_status_section');
function oapg_add_status_section() {
    add_settings_section(
        'oapg_status_section',
        'Status de Geração de Posts',
        'oapg_status_section_callback',
        'oapg-settings'
    );
}

/**
 * Callback para exibir o status de geração de posts
 */
function oapg_status_section_callback() {
    $total_posts = absint(get_option('oapg_total_posts_to_generate', 0));
    $posts_generated = absint(get_option('oapg_posts_generated', 0));
    $posts_remaining = $total_posts - $posts_generated;
    $is_paused = get_option('oapg_generation_paused', false);
    
    echo '<div class="oapg-status-box">';
    echo '<p><strong>Posts Totais:</strong> ' . $total_posts . '</p>';
    echo '<p><strong>Posts Gerados:</strong> ' . $posts_generated . '</p>';
    echo '<p><strong>Posts Restantes:</strong> ' . $posts_remaining . '</p>';
    
    if ($total_posts > 0) {
        $percentage = round(($posts_generated / $total_posts) * 100);
        echo '<div class="oapg-progress-bar"><div class="oapg-progress" style="width: ' . $percentage . '%;"></div></div>';
        echo '<p>' . $percentage . '% concluído</p>';
    }
    
    echo '<p>';
    echo '<a href="' . admin_url('edit.php?post_type=post') . '" class="button">Ver Posts Gerados</a> ';
    // echo '<button id="oapg-reset-counter" class="button">Reiniciar Contadores</button> ';
    
    if ($is_paused) {
        echo '<button id="oapg-start-generation" class="button button-primary">Iniciar Geração</button> ';
    } else {
        echo '<button id="oapg-stop-generation" class="button button-secondary">Parar Geração</button> ';
    }
    
    echo '</p>';
    echo '</div>';
    
    // Adiciona JavaScript para controlar a geração
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#oapg-reset-counter').on('click', function(e) {
            e.preventDefault();
            if (confirm('Tem certeza que deseja reiniciar os contadores de posts?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oapg_reset_counters',
                        nonce: '<?php echo wp_create_nonce('oapg_reset_counters_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Contadores reiniciados com sucesso!');
                            location.reload();
                        } else {
                            alert('Erro ao reiniciar contadores: ' + response.data);
                        }
                    }
                });
            }
        });
        
        $('#oapg-stop-generation').on('click', function(e) {
            e.preventDefault();
            if (confirm('Tem certeza que deseja parar a geração de posts?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oapg_stop_generation',
                        nonce: '<?php echo wp_create_nonce('oapg_stop_generation_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Geração de posts pausada com sucesso!');
                            location.reload();
                        } else {
                            alert('Erro ao pausar geração: ' + response.data);
                        }
                    }
                });
            }
        });
        
        $('#oapg-start-generation').on('click', function(e) {
            e.preventDefault();
            if (confirm('Tem certeza que deseja iniciar a geração de posts?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oapg_start_generation',
                        nonce: '<?php echo wp_create_nonce('oapg_start_generation_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Geração de posts iniciada com sucesso!');
                            location.reload();
                        } else {
                            alert('Erro ao iniciar geração: ' + response.data);
                        }
                    }
                });
            }
        });
    });
    </script>
    <style>
    .oapg-status-box {
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 15px;
        margin-top: 10px;
    }
    .oapg-progress-bar {
        background-color: #e5e5e5;
        height: 20px;
        border-radius: 3px;
        margin-bottom: 10px;
    }
    .oapg-progress {
        background-color: #2271b1;
        height: 100%;
        border-radius: 3px;
    }
    </style>
    <?php
}

/**
 * Endpoint AJAX para reiniciar os contadores
 */
add_action('wp_ajax_oapg_reset_counters', 'oapg_reset_counters_callback');
function oapg_reset_counters_callback() {
    // Verifica o nonce para segurança
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'oapg_reset_counters_nonce')) {
        wp_send_json_error('Verificação de segurança falhou.');
    }
    
    // Reinicia os contadores
    update_option('oapg_posts_generated', 0);
    update_option('oapg_total_generated', 0);
    
    wp_send_json_success();
}

/**
 * Pausa ou retoma a geração de posts
 */
function oapg_toggle_generation_pause($pause = true) {
    update_option('oapg_generation_paused', $pause);
    return $pause;
}

/**
 * Handler para a ação AJAX de parar a geração
 */
add_action('wp_ajax_oapg_stop_generation', 'oapg_stop_generation_callback');
function oapg_stop_generation_callback() {
    check_ajax_referer('oapg_stop_generation_nonce', 'nonce');
    
    $paused = oapg_toggle_generation_pause(true);
    
    wp_send_json_success(array(
        'paused' => $paused,
        'message' => 'Geração de posts pausada com sucesso!'
    ));
}

/**
 * Handler para a ação AJAX de iniciar a geração
 */
add_action('wp_ajax_oapg_start_generation', 'oapg_start_generation_callback');
function oapg_start_generation_callback() {
    check_ajax_referer('oapg_start_generation_nonce', 'nonce');
    
    // Retoma a geração
    oapg_toggle_generation_pause(false);
    
    // Reinicia contadores
    update_option('oapg_posts_generated', 0);
    update_option('oapg_total_generated', 0);
    
    // Inicia a geração imediatamente com o número configurado de posts por ciclo
    oapg_generate_posts();
    
    wp_send_json_success(array(
        'message' => 'Geração de posts iniciada com sucesso! Todos os contadores foram reiniciados.'
    ));
}

/**
 * Retorna o HTML do progresso de geração de posts
 */
function oapg_get_progress_html() {
    $total_generated = absint(get_option('oapg_total_generated', 0));
    $max_posts = absint(get_option('oapg_max_posts_to_generate', 50));
    $posts_remaining = $max_posts - $total_generated;
    
    ob_start();
    
    if ($max_posts > 0) {
        $percentage = round(($total_generated / $max_posts) * 100);
        ?>
        <div class="oapg-progress-container">
            <div class="oapg-progress-bar">
                <div class="oapg-progress" style="width: <?php echo $percentage; ?>%;"></div>
            </div>
            <p class="oapg-progress-text"><?php echo $percentage; ?>% concluído (<?php echo $total_generated; ?> de <?php echo $max_posts; ?> posts)</p>
        </div>
        <?php
    }
    
    return ob_get_clean();
}

/**
 * Handler para a ação AJAX de obter o progresso atual
 */
add_action('wp_ajax_oapg_get_progress', 'oapg_get_progress_callback');
function oapg_get_progress_callback() {
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

/**
 * Handler para a ação AJAX de alternar a geração (iniciar/parar)
 */
add_action('wp_ajax_oapg_toggle_generation', 'oapg_toggle_generation_callback');
function oapg_toggle_generation_callback() {
    check_ajax_referer('oapg_toggle_generation_nonce', 'nonce');
    
    $action = isset($_POST['toggle_action']) ? sanitize_text_field($_POST['toggle_action']) : 'stop';
    $pause = ($action === 'stop');
    
    oapg_toggle_generation_pause($pause);
    
    if (!$pause) {
        // Reseta os contadores para um novo ciclo
        update_option('oapg_posts_generated', 0);
        update_option('oapg_total_generated', 0); // Reseta o contador total
        
        // Inicia a geração imediatamente com o número configurado de posts por ciclo
        oapg_generate_posts();
    }
    
    wp_send_json_success(array(
        'is_paused' => $pause,
        'html' => oapg_get_progress_html(),
        'message' => $pause ? 'Geração de posts pausada com sucesso!' : 'Geração de posts iniciada com sucesso! Todos os contadores foram reiniciados.'
    ));
}