/**
 * JavaScript da área administrativa do WPost Generator
 */

(function($) {
    'use strict';
    
    // Variáveis globais
    var refreshInterval;
    var isUpdating = false;
    
    // Quando o documento estiver pronto
    $(document).ready(function() {
        // Exibe/oculta a chave API
        setupApiKeyToggle();
        
        // Setup para o botão de alternar geração
        setupGenerationToggle();
        
        // Setup para o botão de reiniciar contadores
        setupResetCounters();
        
        // Setup para contagem de palavras-chave
        setupKeywords();
        
        // Inicia a atualização automática do progresso
        startProgressRefresh();
    });
    
    // Controla a visibilidade da chave API
    function setupApiKeyToggle() {
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
    }
    
    // Configura o botão de alternar geração
    function setupGenerationToggle() {
        $('#oapg-generation-toggle').on('click', function(e) {
            e.preventDefault();
            
            if (isUpdating) return;
            isUpdating = true;
            
            var action = $(this).data('action');
            var confirmMsg = (action === 'stop') ? 
                'Tem certeza que deseja parar a geração de posts?' : 
                'Tem certeza que deseja iniciar a geração de posts?';
                
            if (confirm(confirmMsg)) {
                var $button = $(this);
                $button.prop('disabled', true);
                
                $.ajax({
                    url: oapg_admin_vars.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oapg_toggle_generation',
                        toggle_action: action,
                        nonce: oapg_admin_vars.toggle_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            updateProgress(true);
                        } else {
                            alert('Erro: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Erro ao processar a solicitação. Tente novamente.');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        isUpdating = false;
                    }
                });
            } else {
                isUpdating = false;
            }
        });
    }
    
    // Configura o botão de reiniciar contadores
    function setupResetCounters() {
        $('#oapg-reset-counter').on('click', function(e) {
            e.preventDefault();
            
            if (isUpdating) return;
            isUpdating = true;
            
            if (confirm('Tem certeza que deseja reiniciar os contadores de posts?')) {
                $.ajax({
                    url: oapg_admin_vars.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'oapg_reset_counters',
                        nonce: oapg_admin_vars.reset_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Contadores reiniciados com sucesso!');
                            updateProgress(true);
                        } else {
                            alert('Erro: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Erro ao processar a solicitação. Tente novamente.');
                    },
                    complete: function() {
                        isUpdating = false;
                    }
                });
            } else {
                isUpdating = false;
            }
        });
    }
    
    // Configura funcionalidades relacionadas às palavras-chave
    function setupKeywords() {
        updateKeywordsCount();
        
        $('#oapg_keywords_list').on('input', function() {
            updateKeywordsCount();
        });
        
        $('#oapg_import_keywords').on('click', function() {
            var fileInput = $('#oapg_keywords_file')[0];
            if (fileInput.files.length > 0) {
                var file = fileInput.files[0];
                var reader = new FileReader();
                reader.onload = function(e) {
                    var content = e.target.result;
                    // Remove o BOM (Byte Order Mark) se existir
                    content = content.replace(/^\uFEFF/, '');
                    // Garantir que a quebra de linha seja consistente
                    content = content.replace(/\r\n/g, "\n").replace(/\r/g, "\n");
                    $('#oapg_keywords_list').val('').val(content);
                    $('#oapg-keywords-status').text('Arquivo carregado! Salvando automaticamente...').css('color', '#0073aa');
                    updateKeywordsCount();
                    // Aciona automaticamente o botão de salvar
                    $('#oapg_save_keywords').trigger('click');
                };
                reader.readAsText(file, 'UTF-8');
            } else {
                alert('Por favor, selecione um arquivo para importar.');
            }
        });
        
        $('#oapg_save_keywords').on('click', function() {
            var button = $(this);
            var statusElem = $('#oapg-keywords-status');
            var keywords = $('#oapg_keywords_list').val();
            
            button.prop('disabled', true);
            statusElem.text('Salvando...').css('color', '#666');
            
            $.ajax({
                url: oapg_admin_vars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oapg_save_keywords',
                    keywords: keywords,
                    nonce: oapg_admin_vars.keywords_nonce
                },
                success: function(response) {
                    if (response.success) {
                        statusElem.text(response.data.message).css('color', '#46b450');
                    } else {
                        statusElem.text('Erro: ' + response.data).css('color', '#dc3232');
                    }
                },
                error: function() {
                    statusElem.text('Erro ao salvar. Tente novamente.').css('color', '#dc3232');
                },
                complete: function() {
                    button.prop('disabled', false);
                    
                    // Esconde a mensagem após 3 segundos
                    setTimeout(function() {
                        statusElem.text('').css('color', '#666');
                    }, 3000);
                }
            });
        });
    }
    
    // Atualiza a contagem de palavras-chave
    function updateKeywordsCount() {
        var keywords = $('#oapg_keywords_list').val();
        if (!keywords) {
            $('#oapg-keywords-count').text('0 palavras-chave cadastradas');
            return;
        }
        
        var lines = keywords.split('\n');
        var validLines = lines.filter(function(line) {
            return line.trim() !== '';
        });
        
        $('#oapg-keywords-count').text(validLines.length + ' palavras-chave cadastradas');
    }
    
    // Inicia a atualização automática do progresso
    function startProgressRefresh() {
        // Faz a primeira atualização imediatamente
        updateProgress();
        
        // Configura a atualização automática a cada 5 segundos
        refreshInterval = setInterval(updateProgress, 5000);
    }
    
    // Atualiza o progresso via AJAX
    function updateProgress(force) {
        $.ajax({
            url: oapg_admin_vars.ajaxurl,
            type: 'POST',
            data: {
                action: 'oapg_get_progress',
                nonce: oapg_admin_vars.progress_nonce
            },
            success: function(response) {
                if (response.success) {
                    updateProgressUI(response.data);
                } else {
                    console.error('Erro na resposta AJAX:', response);
                    // Adiciona log detalhado para ajudar na depuração
                    if (response.data) {
                        console.error('Detalhes do erro:', response.data);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Erro na requisição AJAX:', {
                    xhr: xhr,
                    status: status,
                    error: error
                });
                
                // Se ocorrer um erro, reduz a frequência das atualizações para não sobrecarregar
                clearInterval(refreshInterval);
                refreshInterval = setInterval(updateProgress, 15000); // Tenta a cada 15 segundos após um erro
            }
        });
    }
    
    // Atualiza a interface do usuário com os dados de progresso
    function updateProgressUI(data) {
        // Verifica e define valores padrão para propriedades indefinidas
        var data = data || {};
        var total_generated = data.total_generated || 0;
        var max_posts = data.max_posts || 50;
        var posts_remaining = data.posts_remaining || max_posts;
        var percentage = data.percentage || 0;
        var posts_no_mes = data.posts_no_mes || 0;
        var limite_mensal = data.limite_mensal || 500;
        var posts_restantes_mes = data.posts_restantes_mes || (limite_mensal - posts_no_mes);
        var is_paused = data.is_paused || false;
        var limite_mensal_atingido = data.limite_mensal_atingido || false;
        
        // Atualiza o status
        if (is_paused) {
            $('.oapg-status').removeClass('active').addClass('paused').text('Geração Pausada');
            
            // Limpa a barra de progresso quando pausado
            $('.oapg-progress').css('width', '0%');
            $('.oapg-progress-text').html('0% concluído (0 de ' + max_posts + ' posts)');
            
            // Zera as contagens no dashboard
            $('.oapg-stat-box:nth-child(1) .oapg-stat-value').text('0');
            $('.oapg-stat-box:nth-child(2) .oapg-stat-value').text(max_posts);
            
            // Remove mensagens de limite atingido
            $('.oapg-complete-message').hide();
        } else {
            $('.oapg-status').removeClass('paused').addClass('active').text('Geração Ativa');
            
            // Atualiza a barra de progresso quando ativo
            $('.oapg-progress').css('width', percentage + '%');
            $('.oapg-progress-text').html(percentage + '% concluído (' + total_generated + ' de ' + max_posts + ' posts)');
            
            // Atualiza os números de estatísticas
            $('.oapg-stat-box:nth-child(1) .oapg-stat-value').text(total_generated);
            $('.oapg-stat-box:nth-child(2) .oapg-stat-value').text(posts_remaining);
            
            // Mostra mensagem de limite atingido se necessário
            if (total_generated >= max_posts && max_posts > 0) {
                if ($('.oapg-complete-message').length === 0) {
                    $('.oapg-status-info').append('<p class="oapg-complete-message">Limite máximo de posts atingido!</p>');
                } else {
                    $('.oapg-complete-message').show();
                }
            } else {
                $('.oapg-complete-message').hide();
            }
        }
        
        // Atualiza o card do limite mensal (sempre, independente do status)
        var percentMensal = Math.round((posts_no_mes / limite_mensal) * 100);
        $('.oapg-monthly-progress').css('width', percentMensal + '%');
        $('.oapg-status-card .oapg-progress-text').html(percentMensal + '% do limite mensal utilizado (' + posts_no_mes + ' de ' + limite_mensal + ' posts)');
        
        // Verifica se o limite mensal foi atingido - independente do status
        if (limite_mensal_atingido) {
            // Adiciona status de limite mensal atingido
            $('.oapg-status').removeClass('active').addClass('paused').text('Limite Mensal Atingido');
            
            // Desativa o botão de iniciar geração
            $('#oapg-generation-toggle').prop('disabled', true);
            
            if ($('.oapg-limite-mensal-message').length === 0) {
                $('.oapg-status-info').append('<p class="oapg-limite-mensal-message">Limite mensal de 500 posts atingido!</p>');
            } else {
                $('.oapg-limite-mensal-message').show();
            }
        } else {
            // Remove a mensagem de limite mensal se existir
            $('.oapg-limite-mensal-message').hide();
            
            // Garante que o botão de geração esteja habilitado
            $('#oapg-generation-toggle').prop('disabled', false);
        }
        
        // Adiciona/atualiza info do limite mensal (sempre visível)
        if ($('.oapg-limite-mensal-info').length === 0) {
            $('.oapg-dashboard-stats').append('<div class="oapg-stat-box oapg-limite-mensal-info"><span class="dashicons dashicons-calendar"></span><div class="oapg-stat-content"><span class="oapg-stat-value">' + posts_restantes_mes + '</span><span class="oapg-stat-label">Limite Mensal Restante</span></div></div>');
        } else {
            $('.oapg-limite-mensal-info .oapg-stat-value').text(posts_restantes_mes);
        }
        
        // Destaca visualmente o limite mensal quando está próximo de atingir
        if (posts_restantes_mes < 50) {
            $('.oapg-limite-mensal-info').addClass('oapg-limite-alerta');
        } else {
            $('.oapg-limite-mensal-info').removeClass('oapg-limite-alerta');
        }
        
        // Atualiza o botão de acordo com o estado (se não estiver com limite mensal atingido)
        if (!limite_mensal_atingido) {
            var $button = $('#oapg-generation-toggle');
            if (is_paused) {
                $button.text('Iniciar Geração')
                    .removeClass('button-secondary')
                    .addClass('button-primary')
                    .data('action', 'start');
            } else {
                $button.text('Parar Geração')
                    .removeClass('button-primary')
                    .addClass('button-secondary')
                    .data('action', 'stop');
            }
        }
    }
    
})(jQuery);
