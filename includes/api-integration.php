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
    
    // Obtém o complemento da IA
    $ai_complemento = get_option('oapg_ai_complemento', '');
    
    $system_prompt = 'Você é um redator de criação de posts para blogs e redes sociais. Comece com "**Título: SEU TÍTULO AQUI**" na primeira linha, depois uma linha em branco e o texto fluido.';

    if (!empty($ai_complemento)) {
        $system_prompt .= ' ' . $ai_complemento;
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
        'timeout' => 60,
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

    $prompt = oapg_replace_keyword_in_prompt($prompt);

    $api_key = get_option( 'oapg_api_key' );
    if ( empty( $api_key ) ) {
        oapg_log_error( 'API key não configurada' );
        return false;
    }
    
    $endpoint = 'https://api.openai.com/v1/images/generations';
    
    $request_data = array_merge( array(
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => '1792x1024',
    ), $params );
    
    $body = wp_json_encode( $request_data );
    
    oapg_log_debug( "Iniciando chamada de API para geração de imagem. Prompt: " . $prompt );
    
    $response = wp_remote_post( $endpoint, array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
        'body'    => $body,
        'timeout' => 60,
    ));
    
    // Log da chamada de API
    oapg_log_api_call( $endpoint, $request_data, $response );
    
    if ( is_wp_error( $response ) ) {
        oapg_log_error( 'Erro na requisição à API de imagens: ' . $response->get_error_message() );
        return false;
    }
    
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    
    if ( isset( $data['data'][0]['url'] ) ) {
        $image_url = $data['data'][0]['url'];
        oapg_log_debug( "Imagem gerada com sucesso. URL: " . $image_url );
        return $image_url;
    }
    
    oapg_log_error( 'Resposta inválida da API de imagens: ' . wp_remote_retrieve_body( $response ) );
    return false;
}

/**
 * Baixa uma imagem da URL e a adiciona à biblioteca de mídia do WordPress.
 *
 * @param string $image_url URL da imagem a ser baixada.
 * @param string $title Título para a imagem na biblioteca de mídia.
 * @return int|false ID da imagem na biblioteca de mídia ou false em caso de erro.
 */
function oapg_download_and_attach_image( $image_url, $title ) {
    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/media.php' );
    
    // Baixa o arquivo
    $tmp = download_url( $image_url );
    
    if ( is_wp_error( $tmp ) ) {
        oapg_log_error( 'Erro ao baixar imagem: ' . $tmp->get_error_message() );
        return false;
    }
    
    $file_array = array(
        'name' => sanitize_title( $title ) . '.jpg',
        'tmp_name' => $tmp
    );
    
    // Usa media_handle_sideload para adicionar o arquivo à biblioteca de mídia
    $attachment_id = media_handle_sideload( $file_array, 0, $title );
    
    if ( file_exists( $tmp ) ) {
        @unlink( $tmp );
    }
    
    if ( is_wp_error( $attachment_id ) ) {
        oapg_log_error( 'Erro ao adicionar imagem à biblioteca: ' . $attachment_id->get_error_message() );
        return false;
    }
    
    return $attachment_id;
}

/**
 * Substitui [palavra_chave] no prompt por uma palavra-chave da lista em sequência
 */
function oapg_replace_keyword_in_prompt($prompt) {
    // Verifica se o prompt contém [palavra_chave]
    if (strpos($prompt, '[palavra_chave]') === false) {
        return $prompt;
    }
    
    // Obtém a lista de palavras-chave
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
