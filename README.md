# WPost Generator

## Descrição

O **WPost Generator** é um plugin para WordPress que gera posts automaticamente utilizando a API da OpenAI, com conteúdos otimizados para SEO.

## Funcionalidades

- Integração com a API da OpenAI para geração de conteúdo.
- Template de prompt customizável para gerar posts.
- Agendamento customizado (WP-Cron) para criação automática de posts.
- Configuração para definir posts como rascunho ou publicados.
- Interface de administração para ajuste de parâmetros (chave da API, frequência, quantidade, etc).

## Instalação

1. Faça o download dos arquivos do plugin.
2. Extraia o conteúdo para a pasta `wp-content/plugins/wpost-generator`.
3. Ative o plugin através do menu **Plugins** no WordPress.
4. Acesse o menu **Wpost Generator** no admin para configurar os parâmetros.

## Configurações

- **Template de Prompt:** Defina o template para a geração dos posts (exemplo: `Escreva um post otimizado para SEO sobre .... Detalhes: ....`).
- **Status do Post:** Escolha se os posts serão gerados como rascunho ou publicados.
- **Frequência:** Defina a frequência (em horas) para a geração dos posts.
- **Posts por Ciclo:** Quantidade de posts gerados a cada execução.

## Avisos

- **Custos e Limitações:** Fique atento aos custos e limites de requisições da API da OpenAI.
- **Desempenho:** A geração de um volume elevado de posts pode impactar o desempenho do site.
- **Personalização:** Ajuste os templates e os dados dinâmicos conforme sua necessidade para obter conteúdos mais relevantes.


