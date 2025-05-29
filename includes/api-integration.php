<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita acesso direto.
}

/**
 * Integração com a API da OpenAI para gerar conteúdo.
 *
 * @param string $prompt Texto base para gerar o conteúdo.
 * @param array $params Parâmetros adicionais para a requisição.
 * @return string|false Conteúdo gerado ou false em caso de erro.
 */
function oapg_generate_content( $prompt, $params = array() ) {

    $prompt = oapg_replace_keyword_in_prompt($prompt);


    $api_key = get_option( 'oapg_api_key' );
    if ( empty( $api_key ) ) {
        oapg_log_error( 'API key não configurada' );
        return false;
    }
    
    $endpoint = 'https://api.openai.com/v1/chat/completions';
    
    // Obtém o prompt do sistema e o complemento da IA
    $system_prompt = get_option('oapg_system_prompt', 'Você é um redator de criação de posts. Comece com "**Título: TÍTULO AQUI**" na primeira linha e o texto fluido.');
    $ai_complemento = get_option('oapg_ai_complemento', '');
    
    // Adiciona o complemento ao prompt do usuário se estiver configurado
    if (!empty($ai_complemento)) {
        $prompt .= "\n\n" . $ai_complemento;
    }
    
    $messages = [
        [
            'role' => 'system',
            'content' => $system_prompt
        ],
        [
            'role' => 'user',
            'content' => $prompt
        ]
    ];
    
    $request_data = array_merge( array(
        'model' => 'gpt-3.5-turbo-1106',
        'messages' => $messages,
        'temperature' => 0.7,
    ), $params );
    
    $body = wp_json_encode( $request_data );
    
    oapg_log_debug( "Iniciando chamada de API para geração de texto. Prompt: " . substr($prompt, 0, 100) . "..." );
    
    $response = wp_remote_post( $endpoint, array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
        'body'    => $body,
        'timeout' => 300,
    ));
    
    oapg_log_api_call( $endpoint, $request_data, $response );
    
    if ( is_wp_error( $response ) ) {
        oapg_log_error( 'Erro na requisição à API: ' . $response->get_error_message() );
        return false;
    }
    
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    
    if ( isset( $data['choices'][0]['message']['content'] ) ) {
        $content = trim( $data['choices'][0]['message']['content'] );
        oapg_log_debug( "Conteúdo gerado com sucesso. Primeiros 100 caracteres: " . substr($content, 0, 100) . "..." );
        return $content;
    }
    
    oapg_log_error( 'Resposta inválida da API: ' . wp_remote_retrieve_body( $response ) );
    return false;
}

/**
 * Integração com a API da OpenAI para gerar imagens.
 *
 * @param string $prompt Descrição da imagem a ser gerada.
 * @param array $params Parâmetros adicionais para a requisição.
 * @return string|false URL da imagem gerada ou false em caso de erro.
 */
function oapg_generate_image( $prompt, $params = array() ) {
    // Substitui a palavra-chave no prompt
    $prompt = oapg_replace_keyword_in_prompt($prompt);

    $api_key = get_option( 'oapg_api_key' );
    if ( empty( $api_key ) ) {
        oapg_log_error( 'API key não configurada' );
        return false;
    }

    $endpoint = 'https://api.openai.com/v1/images/generations';
    
    $request_data = wp_parse_args( array(
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => '1024x1024',
    ), $params );
    
    $body = wp_json_encode( $request_data );
    
    oapg_log_debug( "Iniciando chamada de API para geração de imagem. Prompt: " . $prompt );
    
    $response = wp_remote_post( $endpoint, array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
        'body' => $body,
        'timeout' => 300, // Aumenta o timeout para 60 segundos
    ) );
    
    // Registra a chamada e resposta da API para debug
    oapg_log_api_call( $endpoint, $request_data, $response );
    
    if ( is_wp_error( $response ) ) {
        oapg_log_error( 'Erro na requisição à API de imagens: ' . $response->get_error_message() );
        return false;
    }
    
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    
    if ( isset( $data['data'][0]['url'] ) ) {
        $image_url = $data['data'][0]['url'];
        oapg_log_debug( 'Imagem gerada com sucesso. URL: ' . $image_url );
        return $image_url;
    }
    
    oapg_log_error( 'Resposta inválida da API de imagens: ' . wp_remote_retrieve_body( $response ) );
    return false;
}

/**
 * Baixa uma imagem de uma URL e a anexa à biblioteca de mídia.
 * 
 * @param string $image_url A URL da imagem a ser baixada
 * @param string $title Um título para a imagem
 * @return int|false ID do anexo em caso de sucesso, false em caso de falha
 */
function oapg_download_and_attach_image( $image_url, $title ) {
    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/media.php' );
    
    // Registra o início do processo de download
    oapg_log_debug( 'Iniciando download de imagem: ' . substr($image_url, 0, 100) . '...' );
    
    // Verifica se a URL é válida
    if (empty($image_url)) {
        oapg_log_error( 'URL de imagem vazia' );
        return false;
    }
    
    // Verifica se o título é válido e o sanitiza
    if (empty($title)) {
        $title = 'imagem-' . date('YmdHis');
    }
    $sanitized_title = sanitize_title($title);
    
    // Detecta se é uma URL da API DALL-E da OpenAI
    $is_dalle_url = strpos($image_url, 'oaidalleapiprodscus.blob.core.windows.net') !== false;
    oapg_log_debug('Verificando tipo de imagem. URL DALL-E: ' . ($is_dalle_url ? 'Sim' : 'Não'));
    
    // Para URLs da DALL-E, usamos um timeout maior
    $timeout = $is_dalle_url ? 300 : 180;
    oapg_log_debug('Usando timeout de ' . $timeout . ' segundos para o download');
    
    // Tenta baixar a imagem - primeiro método
    $tmp = download_url($image_url, $timeout);
    
    // Se falhar, tenta um método alternativo
    if (is_wp_error($tmp)) {
        oapg_log_error('Falha no download padrão: ' . $tmp->get_error_message() . '. Tentando método alternativo...');
        
        // Método alternativo com file_get_contents e fopen
        try {
            $tmp_dir = get_temp_dir();
            $tmp_file = tempnam($tmp_dir, 'oai_img_');
            
            if ($tmp_file === false) {
                throw new Exception('Não foi possível criar arquivo temporário');
            }
            
            // Tenta obter conteúdo da URL com contexto específico
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeout,
                    'follow_location' => true,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            
            $image_content = @file_get_contents($image_url, false, $context);
            
            if ($image_content === false) {
                throw new Exception('Falha ao baixar conteúdo da imagem');
            }
            
            if (file_put_contents($tmp_file, $image_content) === false) {
                throw new Exception('Falha ao escrever arquivo temporário');
            }
            
            oapg_log_debug('Download alternativo bem-sucedido. Tamanho: ' . strlen($image_content) . ' bytes');
            $tmp = $tmp_file;
            
        } catch (Exception $e) {
            oapg_log_error('Método alternativo também falhou: ' . $e->getMessage());
            if (isset($tmp_file) && file_exists($tmp_file)) {
                @unlink($tmp_file);
            }
            return false;
        }
    }
    
    oapg_log_debug('Arquivo temporário baixado para: ' . $tmp);
    
    // Verifica se o arquivo temporário existe e é legível
    if (!file_exists($tmp) || !is_readable($tmp)) {
        oapg_log_error('Arquivo temporário não existe ou não é legível: ' . $tmp);
        @unlink($tmp); // Tenta remover o arquivo temporário
        return false;
    }
    
    $filesize = filesize($tmp);
    oapg_log_debug('Tamanho do arquivo baixado: ' . $filesize . ' bytes');
    
    // Verifica se o arquivo não está vazio
    if ($filesize <= 0) {
        oapg_log_error('Arquivo baixado está vazio');
        @unlink($tmp);
        return false;
    }
    
    // Define o tipo de arquivo
    if ($is_dalle_url) {
        // Para URLs da OpenAI DALL-E, forçamos o tipo como PNG
        $file_type = array('type' => 'image/png', 'ext' => 'png');
        oapg_log_debug('URL DALL-E detectada, forçando tipo como image/png');
    } else {
        // Para outras URLs, tentamos detectar o tipo automaticamente
        $file_type = wp_check_filetype(basename($image_url), null);
        
        // Se não conseguir detectar o tipo, assume JPG
        if (empty($file_type['type'])) {
            $file_type['type'] = 'image/jpeg';
            $file_type['ext'] = 'jpg';
        }
    }
    
    // Prepara os dados do arquivo
    $file_array = array(
        'name' => $sanitized_title . '.' . $file_type['ext'],
        'tmp_name' => $tmp,
        'type' => $file_type['type']
    );
    
    oapg_log_debug('Anexando arquivo com tipo: ' . $file_type['type'] . ' e extensão: ' . $file_type['ext']);
    
    $attachment_title = sanitize_text_field($title);
    
    // Adiciona try-catch para capturar erros durante a anexação
    try {
        oapg_log_debug('Iniciando media_handle_sideload para: ' . $file_array['name']);
        $attachment_id = media_handle_sideload($file_array, 0, $attachment_title);
        
        if (file_exists($tmp)) {
            @unlink($tmp);
            oapg_log_debug('Arquivo temporário removido: ' . $tmp);
        }
        
        if (is_wp_error($attachment_id)) {
            oapg_log_error('Erro ao adicionar imagem à biblioteca: ' . $attachment_id->get_error_message());
            return false;
        }
        
        if (!$attachment_id || $attachment_id == 0) {
            oapg_log_error('ID de anexo inválido após upload');
            return false;
        }
        
        oapg_log_debug('Imagem baixada e anexada com sucesso. ID: ' . $attachment_id);
        
        return $attachment_id;
    } catch (Exception $e) {
        oapg_log_error('Exceção durante a anexação da imagem: ' . $e->getMessage());
        if (file_exists($tmp)) {
            @unlink($tmp);
        }
        return false;
    }
}

/**
 * Substitui [palavra_chave] no prompt por uma palavra-chave da lista em sequência
 */
function oapg_replace_keyword_in_prompt($prompt) {
    if (strpos($prompt, '[palavra_chave]') === false) {
        return $prompt;
    }
    
    $keywords_list = get_option('oapg_keywords_list', '');
    if (empty($keywords_list)) {
        return str_replace('[palavra_chave]', '', $prompt);
    }
    
    // Converte a lista em um array, garantindo a codificação UTF-8
    $keywords = explode("\n", $keywords_list);
    $keywords = array_map('trim', $keywords);
    $keywords = array_filter($keywords, function($keyword) {
        return !empty($keyword);
    });
    
    if (empty($keywords)) {
        return str_replace('[palavra_chave]', '', $prompt);
    }
    
    // Obtém o índice da última palavra-chave usada
    $ultimo_keyword_index = get_option('oapg_ultimo_keyword_index', -1);
    
    // Avança para a próxima palavra-chave
    $ultimo_keyword_index = ($ultimo_keyword_index + 1) % count($keywords);
    
    // Salva o novo índice
    update_option('oapg_ultimo_keyword_index', $ultimo_keyword_index);
    
    // Usa a palavra-chave atual
    $current_keyword = $keywords[$ultimo_keyword_index];
    
    // Certifica-se que a palavra-chave está em UTF-8
    if (!mb_check_encoding($current_keyword, 'UTF-8')) {
        $current_keyword = mb_convert_encoding($current_keyword, 'UTF-8', 'auto');
    }
    
    // Registra a palavra-chave usada
    oapg_log_debug("Usando palavra-chave: " . $current_keyword);
    
    // Permite que desenvolvedores modifiquem a palavra-chave antes de inserir no prompt
    $current_keyword = apply_filters('oapg_current_keyword', $current_keyword, $ultimo_keyword_index, $keywords);
    
    // Substitui [palavra_chave] pela palavra-chave selecionada
    $result = str_replace('[palavra_chave]', $current_keyword, $prompt);
    
    // Permite que desenvolvedores modifiquem o prompt final
    return apply_filters('oapg_processed_prompt', $result, $prompt, $current_keyword);
}
