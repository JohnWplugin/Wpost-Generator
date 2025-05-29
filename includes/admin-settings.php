<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acesso direto.
}
/**
 * Extrai o ID do vídeo do YouTube a partir de uma URL
 */
function oapg_extract_youtube_id($url) {
    if (empty($url)) {
        return '';
    }
    
    // Padrões de URL do YouTube
    $patterns = array(
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/youtu\.be\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/user\/[a-zA-Z0-9_-]+\/?\?v=([a-zA-Z0-9_-]+)/'
    );
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
        return $url;
    }
    
    return '';
}

/**
 * Adiciona a página de configurações no menu do admin
 */
add_action( 'admin_menu', 'oapg_add_admin_menu' );
function oapg_add_admin_menu() {
    add_menu_page(
        'WPost Generator',
        'WPost Generator',
        'manage_options',
        'oapg-settings',
        'oapg_settings_page',
        'dashicons-edit'
    );
}

/**
 * Registra e enfileira os estilos e scripts do admin
 */
add_action('admin_enqueue_scripts', 'oapg_admin_enqueue_scripts');
function oapg_admin_enqueue_scripts($hook) {
    if ('toplevel_page_oapg-settings' !== $hook) {
        return;
    }
    
    wp_enqueue_style('oapg-admin-style', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin-style.css', array(), '1.0.0');
    wp_enqueue_script('oapg-admin-script', plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin-script.js', array('jquery'), '1.0.0', true);
    
    // Adiciona variáveis para o JavaScript
    wp_localize_script('oapg-admin-script', 'oapg_admin_vars', array(
        'toggle_nonce' => wp_create_nonce('oapg_toggle_generation_nonce'),
        'reset_nonce' => wp_create_nonce('oapg_reset_counters_nonce'),
        'run_now_nonce' => wp_create_nonce('oapg_run_now_nonce'),
        'unlock_nonce' => wp_create_nonce('oapg_unlock_generation_nonce'),
        'keywords_nonce' => wp_create_nonce('oapg_save_keywords_nonce'),
        'progress_nonce' => wp_create_nonce('oapg_get_progress_nonce'),
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}

/**
 * Renderiza a página de configurações do plugin
 */
function oapg_settings_page() {
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
    ?>
    <div class="wrap">
        <div class="oapg-admin-wrap">
            <div class="oapg-admin-header">
                <h1>WPost Generator</h1>
                <div class="oapg-admin-actions">
                    <a href="<?php echo admin_url('edit.php?post_type=post'); ?>" class="button">Ver Posts</a>
                </div>
            </div>
            
            <div class="oapg-admin-container">
                <div class="oapg-admin-sidebar">
                    <ul>
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=oapg-settings&tab=dashboard'); ?>" class="<?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>">
                                <span class="dashicons dashicons-dashboard"></span> Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=oapg-settings&tab=api'); ?>" class="<?php echo $active_tab == 'api' ? 'active' : ''; ?>">
                                <span class="dashicons dashicons-admin-network"></span> Configurações da API
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=oapg-settings&tab=content'); ?>" class="<?php echo $active_tab == 'content' ? 'active' : ''; ?>">
                                <span class="dashicons dashicons-editor-paste-text"></span> Configurações de Conteúdo
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=oapg-settings&tab=schedule'); ?>" class="<?php echo $active_tab == 'schedule' ? 'active' : ''; ?>">
                                <span class="dashicons dashicons-calendar-alt"></span> Configurações de Agendamento
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="oapg-admin-content">
                    <form method="post" action="options.php" accept-charset="UTF-8">
                        <?php
                        if ($active_tab == 'dashboard') {
                            ?>
                            <div class="oapg-tab-content active">
                                <div class="oapg-card">
                                    <h2>Dashboard</h2>
                                    
                                    <?php 
                                    // Exibe o status de geração de posts
                                    $posts_generated = absint(get_option('oapg_posts_generated', 0));
                                    $total_generated = absint(get_option('oapg_total_generated', 0));
                                    $max_posts = absint(get_option('oapg_max_posts_to_generate', 50));
                                    $posts_remaining = $max_posts - $total_generated;
                                    $is_paused = get_option('oapg_generation_paused', false);
                                    
                                    // Obtém os dados do contador mensal
                                    $contador_mes = get_option('oapg_contador_mensal', array());
                                    $mes_atual = date('m');
                                    $ano_atual = date('Y');
                                    $chave_mes = $ano_atual . $mes_atual;
                                    $posts_no_mes = isset($contador_mes[$chave_mes]) ? $contador_mes[$chave_mes] : 0;
                                    $limite_mensal = 500;
                                    $posts_restantes_mes = $limite_mensal - $posts_no_mes;
                                    ?>
                                    
                                    <div class="oapg-dashboard-stats">
                                        <div class="oapg-stat-box">
                                            <span class="dashicons dashicons-edit"></span>
                                            <div class="oapg-stat-content">
                                                <span class="oapg-stat-value"><?php echo $total_generated; ?></span>
                                                <span class="oapg-stat-label">Posts Gerados</span>
                                            </div>
                                        </div>
                                        
                                        <div class="oapg-stat-box">
                                            <span class="dashicons dashicons-clock"></span>
                                            <div class="oapg-stat-content">
                                                <span class="oapg-stat-value"><?php echo $posts_remaining; ?></span>
                                                <span class="oapg-stat-label">Posts Pendentes</span>
                                            </div>
                                        </div>
                                        
                                        <div class="oapg-stat-box">
                                            <span class="dashicons dashicons-calendar-alt"></span>
                                            <div class="oapg-stat-content">
                                                <span class="oapg-stat-value"><?php echo esc_html(get_option('oapg_post_frequency', '1')); ?>h</span>
                                                <span class="oapg-stat-label">Frequência</span>
                                            </div>
                                        </div>
                                        
                                        <div class="oapg-stat-box oapg-limite-mensal-info">
                                            <span class="dashicons dashicons-calendar"></span>
                                            <div class="oapg-stat-content">
                                                <span class="oapg-stat-value"><?php echo $posts_restantes_mes; ?></span>
                                                <span class="oapg-stat-label">Limite Mensal Restante</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($max_posts > 0): ?>
                                        <?php $percentage = round(($total_generated / $max_posts) * 100); ?>
                                        <div class="oapg-progress-container">
                                            <div class="oapg-progress-bar">
                                                <div class="oapg-progress" style="width: <?php echo $percentage; ?>%;"></div>
                                            </div>
                                            <p class="oapg-progress-text"><?php echo $percentage; ?>% concluído (<?php echo $total_generated; ?> de <?php echo $max_posts; ?> posts)</p>
                                        </div>
                                        
                                        <div class="oapg-status-info">
                                            <p><strong>Status:</strong> 
                                                <?php if ($is_paused): ?>
                                                    <span class="oapg-status paused">Geração Pausada</span>
                                                <?php else: ?>
                                                    <span class="oapg-status active">Geração Ativa</span>
                                                <?php endif; ?>
                                            </p>
                                            <?php if ($total_generated >= $max_posts && $max_posts > 0): ?>
                                                <p class="oapg-complete-message">Geração de post concluída!</p>
                                            <?php endif; ?>
                                            
                                            <?php if ($posts_no_mes >= $limite_mensal): ?>
                                                <p class="oapg-limite-mensal-message">Limite mensal de 500 posts atingido!</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="oapg-quick-actions">
                                        <h3>Ações Rápidas</h3>
                                        <div class="oapg-action-buttons">
                                            <?php 
                                            $button_disabled = ($posts_no_mes >= $limite_mensal) ? 'disabled' : '';
                                            if ($is_paused) {
                                                echo '<button id="oapg-generation-toggle" class="button button-primary" data-action="start" ' . $button_disabled . '>Iniciar Geração</button>';
                                            } else {
                                                echo '<button id="oapg-generation-toggle" class="button button-secondary" data-action="stop" ' . $button_disabled . '>Parar Geração</button>';
                                            }
                                            ?>
                                            <a href="<?php echo admin_url('edit.php?post_type=post'); ?>" class="button">Ver Posts Gerados</a>
                                        </div>
                                    </div>
                                    
                                    <div class="oapg-card oapg-status-card">
                                        <h3>Status do Limite Mensal</h3>
                                        <div class="oapg-progress-container">
                                            <div class="oapg-progress-bar">
                                                <div class="oapg-monthly-progress" style="width: <?php echo ($posts_no_mes / max(1, $limite_mensal)) * 100; ?>%;"></div>
                                            </div>
                                            <p class="oapg-progress-text">
                                                <?php echo round(($posts_no_mes / max(1, $limite_mensal)) * 100); ?>% do limite mensal utilizado 
                                                (<?php echo $posts_no_mes; ?> de <?php echo $limite_mensal; ?> posts)
                                            </p>
                                        </div>
                                        <p class="oapg-monthly-info">
                                            O sistema tem um limite de 500 posts por mês. Este contador se mantém mesmo quando a geração é pausada.
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="oapg-card">
                                    <h2>Criar Post de Teste</h2>
                                    </form><!-- Fechamos o formulário principal aqui -->
                                    <form method="post" action="" class="oapg-test-form">
                                        <?php wp_nonce_field('oapg_test_post_nonce'); ?>
                                        <div class="oapg-form-row">
                                            <label for="test_post_tema">Tema do post:</label>
                                            <input type="text" name="test_post_tema" id="test_post_tema" value="" placeholder="Digite o tema do post" required>
                                        </div>
                                        <div class="oapg-form-row">
                                            <label for="test_post_detalhes">Detalhes adicionais:</label>
                                            <textarea name="test_post_detalhes" id="test_post_detalhes" rows="4" cols="50" placeholder="Digite os detalhes adicionais para o post"></textarea>
                                        </div>
                                        <div class="oapg-form-row">
                                            <input type="submit" name="oapg_create_test_post" class="button button-primary" value="Criar Post de Teste">
                                        </div>
                                    </form>
                                    <form method="post" action="options.php" accept-charset="UTF-8"><!-- Reabrimos o formulário principal aqui -->
                                    
                                    <?php if (isset($_POST['oapg_create_test_post']) && check_admin_referer('oapg_test_post_nonce')): ?>
                                        <?php 
                                        // Lógica para criar post de teste
                                        $tema = sanitize_text_field($_POST['test_post_tema']);
                                        $detalhes = sanitize_textarea_field($_POST['test_post_detalhes']);
                                        
                                        // Configurações para o post de teste
                                        $prompt_template = "Escreva um post otimizado para SEO sobre [TEMA]. Detalhes adicionais: [DETALHES]";
                                        
                                        // Gera o conteúdo
                                        $prompt = str_replace(
                                            array('[TEMA]', '[DETALHES]'),
                                            array($tema, $detalhes),
                                            $prompt_template
                                        );
                                        
                                        $content = oapg_generate_content($prompt);
                                        
                                        // Na parte onde o post de teste é criado
                                        if ($content) {
                                            // Exibe o conteúdo gerado
                                            echo '<div class="oapg-preview-content">';
                                            echo '<h3>Conteúdo Gerado:</h3>';
                                            echo '<div class="oapg-content-preview">';
                                            echo wpautop($content);
                                            echo '</div>';
                                            echo '</div>';

                                            // Extrai o título do conteúdo
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
                                                $post_title = wp_trim_words($content, 10, '...');
                                                $content_without_title = $content;
                                            }
                                            
                                            $new_post = array(
                                                'post_title'   => $post_title,
                                                'post_content' => $content_without_title,
                                                'post_status'  => 'draft',
                                                'post_author'  => get_current_user_id(),
                                            );
                                            
                                            $post_id = wp_insert_post($new_post);
                                            
                                            if ($post_id) {
                                                $post_result = '<div class="oapg-notice success"><span class="dashicons dashicons-yes"></span> Post de teste criado com sucesso! <a href="' . get_edit_post_link($post_id) . '" target="_blank">Editar post</a></div>';
                                            } else {
                                                $post_result = '<div class="oapg-notice error"><span class="dashicons dashicons-no"></span> Erro ao criar o post de teste.</div>';
                                            }
                                        } else {
                                            $post_result = '<div class="oapg-notice error"><span class="dashicons dashicons-no"></span> Erro ao gerar conteúdo para o post.</div>';
                                        }
                                        ?>
                                        <div class="oapg-result">
                                            <?php echo $post_result; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        } elseif ($active_tab == 'api') {
                            settings_fields('oapg_api_settings_group');
                            ?>
                            <div class="oapg-tab-content active">
                                <div class="oapg-card">
                                    <h2>Configurações da API OpenAI</h2>
                                    <div class="oapg-form-row">
                                        <label for="oapg_api_key">Chave da API OpenAI</label>
                                        <div class="oapg-api-key-field">
                                            <input type="password" id="oapg_api_key" name="oapg_api_key" value="<?php echo esc_attr(get_option('oapg_api_key')); ?>" size="50" />
                                            <span class="oapg-toggle-visibility dashicons dashicons-visibility"></span>
                                        </div>
                                        <p class="description">Insira sua chave de API da OpenAI.</p>
                                    </div>
                                    <?php submit_button('Salvar Configurações da API'); ?>
                                </div>
                            </div>
                            <?php
                        } elseif ($active_tab == 'content') {
                            settings_fields('oapg_content_settings_group');
                            ?>
                            <div class="oapg-tab-content active">
                                <div class="oapg-card">
                                    <h2>Configurações de Conteúdo</h2>
                                    <input type="hidden" name="option_page" value="oapg_content_settings_group" />
                                    <input type="hidden" name="action" value="update" />
                                    <?php wp_nonce_field('oapg_content_settings_group-options', '_wpnonce'); ?>
                                    <div class="oapg-form-row">
                                        <label for="oapg_keywords_file">Importar Palavras-chave</label>
                                        <input type="file" id="oapg_keywords_file" name="oapg_keywords_file" accept=".txt" />
                                        <button type="button" id="oapg_import_keywords" class="button">Importar Palavras-chave</button>
                                        <p class="description">Importe um arquivo TXT com uma palavra-chave por linha para usar nos prompts.</p>
                                    </div>
                            
                                    <div class="oapg-form-row">
                                        <label for="oapg_keywords_list">Lista de Palavras-chave</label>
                                        <textarea id="oapg_keywords_list" name="oapg_keywords_list" rows="12" cols="50" accept-charset="UTF-8"><?php echo htmlspecialchars(get_option('oapg_keywords_list', ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        <p class="description">Uma palavra-chave por linha. Estas serão usadas nos prompts usando [palavra_chave]. Você pode editar diretamente nesta caixa.</p>
                                        <div class="oapg-submit-keywords">
                                            <button type="button" id="oapg_save_keywords" class="button button-primary">Salvar Palavras-chave</button>
                                            <span id="oapg-keywords-status" class="oapg-keywords-status"></span>
                                        </div>
                                        <div class="oapg-keywords-stats">
                                            <div id="oapg-keywords-count">0 palavras-chave cadastradas</div>
                                        </div>
                                    </div>
                                   
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_prompt_template">Prompt para Texto</label>
                                        <textarea id="oapg_prompt_template" name="oapg_prompt_template" rows="4" cols="50"><?php echo esc_textarea(get_option('oapg_prompt_template', 'Escreva um post otimizado para SEO sobre [TEMA]. Detalhes: [DETALHES].')); ?></textarea>
                                        <p class="description">Para fazer referência ao título do produto utilize [produto] ou [palavra_chave] para a lista de palavras chaves.</p>
                                    </div>
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_image_prompt_template">Prompt para Imagem</label>
                                        <textarea id="oapg_image_prompt_template" name="oapg_image_prompt_template" rows="4" cols="50"><?php echo esc_textarea(get_option('oapg_image_prompt_template', 'Uma imagem profissional representando [TEMA]')); ?></textarea>
                                        <p class="description">Para fazer referência ao título do produto utilize [produto] ou [palavra_chave] para a lista de palavras chaves.</p>
                                    </div>
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_post_title_template">Personalização de Título</label>
                                        <input type="text" id="oapg_post_title_template" name="oapg_post_title_template" value="<?php echo esc_attr(get_option('oapg_post_title_template', '')); ?>" size="50" />
                                        <p class="description">Personalize os títulos dos posts. Deixe em branco para usar o título gerado automaticamente. Use [TITULO] para representar o título original.</p>
                                    </div>
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_post_title_position">Posição do Texto Adicional</label>
                                        <select id="oapg_post_title_position" name="oapg_post_title_position">
                                            <option value="inicio" <?php selected(get_option('oapg_post_title_position', 'inicio'), 'inicio'); ?>>No início</option>
                                            <option value="fim" <?php selected(get_option('oapg_post_title_position', 'inicio'), 'fim'); ?>>No final</option>
                                        </select>
                                        <p class="description">Escolha onde o texto adicional será adicionado no título.</p>
                                    </div>
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_ai_complemento">Dados complementares</label>
                                        <textarea id="oapg_ai_complemento" name="oapg_ai_complemento" rows="4" cols="50"><?php echo esc_textarea(get_option('oapg_ai_complemento', '')); ?></textarea>
                                        <p class="description">Texto adicional que será enviado em todas as requisições para a IA. Útil para definir estilos ou regras específicas.</p>
                                    </div>
                                    
                                    <!-- <div class="oapg-form-row">
                                        <label for="oapg_system_prompt">Instruções para a IA</label>
                                        <textarea id="oapg_system_prompt" name="oapg_system_prompt" rows="4" cols="50"><?php echo esc_textarea(get_option('oapg_system_prompt', 'Você é um redator profissional. Crie um post bem estruturado com título.')); ?></textarea>
                                        <p class="description">Instruções de sistema para a IA. Define como a IA deve se comportar ao gerar conteúdo. Se vazio, será usado um prompt básico.</p>
                                    </div> -->
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_generate_images">Gerar Imagens</label>
                                        <select id="oapg_generate_images" name="oapg_generate_images">
                                            <option value="yes" <?php selected(get_option('oapg_generate_images', 'yes'), 'yes'); ?>>Sim</option>
                                            <option value="no" <?php selected(get_option('oapg_generate_images', 'yes'), 'no'); ?>>Não</option>
                                        </select>
                                        <p class="description">Escolha se deseja gerar imagens em destaque para os posts.</p>
                                    </div>
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_footer_text">Texto Padrão de Rodapé</label>
                                        <textarea id="oapg_footer_text" name="oapg_footer_text" rows="4" cols="50"><?php echo esc_textarea(get_option('oapg_footer_text', '')); ?></textarea>
                                        <p class="description">Texto padrão que será adicionado ao final de cada post gerado. Deixe em branco para não adicionar.</p>
                                    </div>
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_youtube_video">ID do Vídeo do YouTube</label>
                                        <input type="text" id="oapg_youtube_video" name="oapg_youtube_video" value="<?php echo esc_attr(get_option('oapg_youtube_video', '')); ?>" size="50" />
                                        <p class="description">URL ou ID do vídeo do YouTube a ser incorporado nos posts. Deixe em branco para não adicionar.</p>
                                    </div>
                                    
                                    <?php submit_button('Salvar Configurações de Conteúdo'); ?>
                                </div>
                            </div>
                            <?php
                        } elseif ($active_tab == 'schedule') {
                            settings_fields('oapg_schedule_settings_group');
                            ?>
                            <div class="oapg-tab-content active">
                                <div class="oapg-card">
                                    <h2>Configurações de Agendamento</h2>
                                    <div class="oapg-form-row">
                                        <label for="oapg_post_status">Status do Post</label>
                                        <select id="oapg_post_status" name="oapg_post_status">
                                            <option value="draft" <?php selected(get_option('oapg_post_status', 'draft'), 'draft'); ?>>Rascunho</option>
                                            <option value="publish" <?php selected(get_option('oapg_post_status', 'draft'), 'publish'); ?>>Publicado</option>
                                        </select>
                                        <p class="description">Status dos posts gerados.</p>
                                    </div>
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_post_frequency">Frequência (em horas)</label>
                                        <input type="number" id="oapg_post_frequency" name="oapg_post_frequency" value="<?php echo esc_attr(get_option('oapg_post_frequency', '1')); ?>" min="1" />
                                        <p class="description">Frequência de execução em horas.</p>
                                    </div>
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_posts_per_cycle">Posts por Ciclo</label>
                                        <input type="number" id="oapg_posts_per_cycle" name="oapg_posts_per_cycle" value="<?php echo esc_attr(get_option('oapg_posts_per_cycle', '1')); ?>" min="1" />
                                        <p class="description">Quantidade de posts gerados a cada ciclo.</p>
                                    </div>
                                    
                                    <div class="oapg-form-row">
                                        <label for="oapg_max_posts_to_generate">Limite Total de Posts</label>
                                        <input type="number" id="oapg_max_posts_to_generate" name="oapg_max_posts_to_generate" value="<?php echo esc_attr(get_option('oapg_max_posts_to_generate', '50')); ?>" min="1" />
                                        <p class="description">Número máximo de posts que serão gerados com esse agendamento. A geração será interrompida quando atingir este limite.</p>
                                    </div>
                                    
                                    <?php submit_button('Salvar Configurações de Agendamento'); ?>
                                </div>
                                
                                <?php 
                                // Exibe o status de geração de posts
                                $posts_generated = absint(get_option('oapg_posts_generated', 0));
                                $total_generated = absint(get_option('oapg_total_generated', 0));
                                $max_posts = absint(get_option('oapg_max_posts_to_generate', 50));
                                $posts_remaining = $max_posts - $total_generated;
                                $is_paused = get_option('oapg_generation_paused', false);
                                ?>
                                <div class="oapg-card">
                                    <h2>Status de Geração de Posts</h2>
                                    <div class="oapg-status-info">
                                        <p><strong>Posts Totais:</strong> <?php echo $max_posts; ?></p>
                                        <p><strong>Posts Gerados:</strong> <?php echo $total_generated; ?></p>
                                        <p><strong>Posts Restantes:</strong> <?php echo $posts_remaining; ?></p>
                                        
                                        <?php if ($max_posts > 0): ?>
                                            <?php $percentage = round(($total_generated / $max_posts) * 100); ?>
                                            <div class="oapg-progress-bar">
                                                <div class="oapg-progress" style="width: <?php echo $percentage; ?>%;"></div>
                                            </div>
                                            <p><?php echo $percentage; ?>% concluído</p>
                                        <?php endif; ?>
                                        
                                        <div class="oapg-actions">
                                            <a href="<?php echo admin_url('edit.php?post_type=post'); ?>" class="button">Ver Posts Gerados</a>
                                            <!-- <button id="oapg-reset-counter" class="button">Reiniciar Contadores</button> -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function resetCounters() {
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
        }
        
        // Botão de reiniciar contadores no dashboard
        $('#oapg-reset-counter').on('click', function(e) {
            e.preventDefault();
            resetCounters();
        });
        
        // Botão de parar geração
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
        
        // Botão de iniciar geração
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
        
        // Modifica a mensagem ao salvar configurações de agendamento
        $('#submit').on('click', function(e) {
            if ($('input[name="option_page"]').val() === 'oapg_schedule_settings_group') {
                // Mostra mensagem apenas quando estiver salvando as configurações de agendamento
                alert('As configurações serão salvas. Para iniciar a geração de posts, use o botão "Iniciar Geração" no Dashboard.');
            }
        });
        
        // Toggle de visibilidade da chave API
        $('.oapg-toggle-visibility').on('click', function() {
            var input = $(this).prev('input');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                $(this).removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                input.attr('type', 'password');
                $(this).removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });
        
        // Função para atualizar o progresso
        function updateProgress() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oapg_get_progress',
                    nonce: oapg_admin_vars.progress_nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Atualiza os contadores
                        $('.oapg-stat-box:eq(0) .oapg-stat-value').text(data.total_generated);
                        $('.oapg-stat-box:eq(1) .oapg-stat-value').text(data.posts_remaining);
                        
                        // Atualiza o limite mensal restante
                        $('.oapg-limite-mensal-info .oapg-stat-value').text(data.posts_restantes_mes);
                        
                        // Atualiza a barra de progresso
                        $('.oapg-progress').css('width', data.percentage + '%');
                        $('.oapg-progress-text').text(data.percentage + '% concluído (' + data.total_generated + ' de ' + data.max_posts + ' posts)');
                        
                        // Atualiza a barra de progresso mensal
                        var monthlyPercentage = Math.round((data.posts_no_mes / Math.max(1, data.limite_mensal)) * 100);
                        $('.oapg-monthly-progress').css('width', monthlyPercentage + '%');
                        $('.oapg-progress-text').eq(1).text(monthlyPercentage + '% do limite mensal utilizado (' + data.posts_no_mes + ' de ' + data.limite_mensal + ' posts)');
                        
                        // Atualiza o status
                        var statusText = data.is_paused ? 'Geração Pausada' : 'Geração Ativa';
                        var statusClass = data.is_paused ? 'paused' : 'active';
                        $('.oapg-status').text(statusText).removeClass('active paused').addClass(statusClass);
                        
                        // Mostra/esconde mensagens
                        if (data.limite_ciclo_atingido) {
                            $('.oapg-complete-message').show();
                        } else {
                            $('.oapg-complete-message').hide();
                        }
                        
                        if (data.limite_mensal_atingido) {
                            $('.oapg-limite-mensal-message').show();
                        } else {
                            $('.oapg-limite-mensal-message').hide();
                        }
                        
                        // Atualiza o botão de toggle
                        $('#oapg-generation-toggle').text(data.is_paused ? 'Iniciar Geração' : 'Parar Geração')
                            .data('action', data.is_paused ? 'start' : 'stop')
                            .removeClass('button-primary button-secondary')
                            .addClass(data.is_paused ? 'button-primary' : 'button-secondary');
                    } else if (response.data) {
                        console.error('Erro na resposta AJAX de progresso:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro na requisição AJAX de progresso:', {
                        xhr: xhr,
                        status: status,
                        error: error
                    });
                    
                    // Depois do primeiro erro, reduz a frequência das tentativas para não sobrecarregar
                    clearInterval(progressInterval);
                    progressInterval = setInterval(updateProgress, 15000); // Tenta a cada 15 segundos depois de um erro
                }
            });
        }
        
        // Tenta obter o progresso inicial, depois configura o intervalo
        updateProgress();
        
        // Configura o intervalo após a primeira execução
        var progressInterval = setInterval(updateProgress, 5000);

        // Funções para gerenciar o agendamento
        jQuery(document).ready(function($) {
            // Forçar execução manual do cron
            $('#oapg-force-cron').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Isso irá forçar a execução imediata da geração de posts. Continuar?')) {
                    return;
                }
                
                var button = $(this);
                var resultDiv = $('#oapg-cron-result');
                
                button.prop('disabled', true).text('Executando...');
                resultDiv.html('<p>Executando a função de geração de posts. Isso pode levar alguns minutos...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oapg_force_cron',
                        nonce: button.data('nonce')
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Forçar Execução Agora');
                        
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            // Recarrega a página após alguns segundos
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>Erro: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Forçar Execução Agora');
                        resultDiv.html('<div class="notice notice-error"><p>Falha na comunicação com o servidor.</p></div>');
                    }
                });
            });
            
            // Reparar agendamento do cron
            $('#oapg-fix-cron').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Isso irá reparar o agendamento do cron. Continuar?')) {
                    return;
                }
                
                var button = $(this);
                var resultDiv = $('#oapg-cron-result');
                
                button.prop('disabled', true).text('Reparando...');
                resultDiv.html('<p>Reparando o agendamento do cron...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oapg_fix_cron',
                        nonce: button.data('nonce')
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Reparar Agendamento');
                        
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            // Recarrega a página após alguns segundos
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>Erro: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Reparar Agendamento');
                        resultDiv.html('<div class="notice notice-error"><p>Falha na comunicação com o servidor.</p></div>');
                    }
                });
            });
        });
    });
    </script>
    
    <style>
    /* Estilos para as ações rápidas */
    .oapg-quick-actions {
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 15px;
        margin-bottom: 15px;
    }
    .oapg-action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
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
    .oapg-status-info {
        margin-top: 10px;
    }
    .oapg-status {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-weight: bold;
    }
    .oapg-status.active {
        background-color: #d1e7dd;
        color: #0f5132;
    }
    .oapg-status.paused {
        background-color: #fff3cd;
        color: #856404;
    }
    .oapg-complete-message {
        background-color: #d1e7dd;
        color: #0f5132;
        padding: 10px;
        border-radius: 3px;
        margin-top: 10px;
        font-weight: bold;
        text-align: center;
    }
    
    .oapg-limite-mensal-message {
        background-color: #f8d7da;
        color: #842029;
        padding: 10px;
        border-radius: 3px;
        margin-top: 10px;
        font-weight: bold;
        text-align: center;
    }
    
    .oapg-limite-mensal-info .dashicons {
        color: #d63384;
    }
    
    .oapg-limite-alerta .dashicons {
        color: #dc3545;
    }
    
    .oapg-limite-alerta .oapg-stat-value {
        color: #dc3545;
        font-weight: bold;
    }
    
    .oapg-status-card {
        margin-top: 20px;
    }
    
    .oapg-monthly-progress {
        background-color: #20c997;
        height: 100%;
        border-radius: 3px;
        transition: width 0.6s ease;
    }
    
    .oapg-monthly-info {
        font-style: italic;
        color: #666;
        margin-top: 15px;
        font-size: 0.9em;
    }
    
    /* Estilos para o sistema de palavras-chave */
    .oapg-submit-keywords {
        display: flex;
        align-items: center;
        margin-top: 10px;
    }
    .oapg-keywords-status {
        margin-left: 15px;
        font-weight: 500;
        font-style: italic;
    }
    #oapg_keywords_list {
        transition: background-color 0.3s ease;
        width: 100%;
        font-family: monospace;
        font-size: 14px;
    }
    .oapg-keywords-stats {
        margin-top: 10px;
        color: #666;
        font-style: italic;
        font-size: 12px;
    }
    .oapg-keywords-stats div {
        background: #f7f7f7;
        padding: 5px 10px;
        border-radius: 3px;
        display: inline-block;
    }

    /* Estilos para a área de preview do conteúdo */
    .oapg-preview-content {
        margin: 20px 0;
        padding: 20px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    
    .oapg-preview-content h3 {
        margin-top: 0;
        color: #1d2327;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    
    .oapg-content-preview {
        background: #fff;
        padding: 15px;
        border: 1px solid #e5e5e5;
        border-radius: 3px;
        max-height: 500px;
        overflow-y: auto;
    }
    
    .oapg-content-preview p {
        margin-bottom: 1em;
        line-height: 1.6;
    }

    /* Estilos para o formulário de teste */
    .oapg-test-form {
        background: #fff;
        padding: 20px;
        border: 1px solid #e5e5e5;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .oapg-test-form .oapg-form-row {
        margin-bottom: 15px;
    }
    
    .oapg-test-form label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }
    
    .oapg-test-form input[type="text"],
    .oapg-test-form textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .oapg-test-form input[type="text"]:focus,
    .oapg-test-form textarea:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
        outline: none;
    }
    
    .oapg-test-form input[type="submit"] {
        margin-top: 10px;
    }

    /* Estilos para a seção de informações do agendamento */
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
    }
    
    .oapg-cron-actions h3 {
        margin-top: 0;
        margin-bottom: 10px;
    }
    
    .oapg-cron-result {
        margin-top: 15px;
    }
    
    #oapg-force-cron, #oapg-fix-cron {
        margin-right: 10px;
        margin-top: 10px;
    }
    </style>
    <?php
}

/**
 * Registra as configurações e campos de opções
 */
add_action('admin_init', 'oapg_register_settings');
function oapg_register_settings() {
    // Grupo de configurações da API
    register_setting('oapg_api_settings_group', 'oapg_api_key');
    
    // Grupo de configurações de conteúdo
    register_setting('oapg_content_settings_group', 'oapg_prompt_template');
    register_setting('oapg_content_settings_group', 'oapg_image_prompt_template');
    register_setting('oapg_content_settings_group', 'oapg_generate_images');
    register_setting('oapg_content_settings_group', 'oapg_footer_text');
    register_setting('oapg_content_settings_group', 'oapg_youtube_video');
    register_setting('oapg_content_settings_group', 'oapg_post_title_template');
    register_setting('oapg_content_settings_group', 'oapg_post_title_position');
    register_setting('oapg_content_settings_group', 'oapg_ai_complemento');
    register_setting('oapg_content_settings_group', 'oapg_keywords_list');
    register_setting('oapg_content_settings_group', 'oapg_system_prompt');
    
    // Grupo de configurações de agendamento
    register_setting('oapg_schedule_settings_group', 'oapg_post_status');
    register_setting('oapg_schedule_settings_group', 'oapg_post_frequency');
    register_setting('oapg_schedule_settings_group', 'oapg_posts_per_cycle');
    register_setting('oapg_schedule_settings_group', 'oapg_max_posts_to_generate');
}