# Instalação

## Requisitos

- WordPress 6.4 ou superior
- PHP 8.1 ou superior
- MySQL/MariaDB com suporte a `dbDelta()` padrão do WordPress
- HTTPS recomendado (obrigatório para Application Passwords funcionarem sem aviso)

## Passo a passo

1. No painel do WordPress, vá em **Plugins → Adicionar plugin → Enviar plugin**.
2. Selecione `cartolanews-tennis-hub.zip` e clique em **Instalar agora**.
3. Clique em **Ativar**. Na ativação, o plugin automaticamente:
   - cria as tabelas `wp_cn_tennis_*` via `dbDelta()`;
   - registra as opções padrão (`cn_tennis_settings`);
   - registra os tamanhos de imagem (`cn-tennis-hero`, `cn-tennis-player-card` etc.);
   - agenda os eventos de cron (sincronização de ranking/calendário/jogos);
   - registra a URL amigável `/tenis/jogador/{slug}/` (pode ser necessário ir em **Configurações → Links permanentes** e clicar em Salvar uma vez, para o Apache/Nginx recarregar as regras).
4. Vá em **CartolaNews Tênis → Configurações** e preencha:
   - URLs dos feeds JSON de ranking/calendário (gerados pelo coletor deste repositório — ver [GITHUB-ACTIONS.md](GITHUB-ACTIONS.md));
   - categoria de notícias (opcional);
   - cores (já vêm com a identidade CartolaNews por padrão: `#144b9b` / `#7494c4`).
5. Crie uma página e insira o shortcode `[cn_tenis_hub]` — funciona no editor de blocos, editor clássico e no widget **Shortcode** do Elementor.
6. Rode uma sincronização manual em **CartolaNews Tênis → Fontes → Atualizar agora** para popular os dados imediatamente (sem esperar o próximo cron).
7. (Opcional, recomendado) Configure o GitHub Actions do repositório com os secrets `WP_URL`, `WP_USER`, `WP_APP_PASSWORD` para que o coletor externo publique os dados automaticamente todos os dias.

## Compatibilidade

- **Elementor:** o shortcode `[cn_tenis_hub]` (e os individuais) funcionam no widget Shortcode.
- **Foxiz:** o plugin usa `get_header()`/`get_footer()` na página de perfil do jogador para manter o layout do tema; o CSS detecta as classes de dark mode do Foxiz (`body.dark`, `body.dark-mode`, `[data-theme="dark"]`) além do `prefers-color-scheme`.
- **LiteSpeed Cache:** o plugin dispara `litespeed_purge_all` ao invalidar o cache interno; o bloco "Ao Vivo" se atualiza via REST mesmo com a página em cache de página inteira.

## Desativação e exclusão

- **Desativar** o plugin nunca apaga dados — apenas remove os eventos de cron.
- **Excluir** o plugin (via painel de Plugins) verifica a opção "Manter dados ao desinstalar" em Configurações (marcada por padrão). Se desmarcada antes da exclusão, as tabelas e opções do plugin são removidas.

## Atualização de versão

O plugin compara `cn_tennis_db_version` a cada carregamento e roda `dbDelta()` novamente quando necessário — atualizações de schema são aplicadas automaticamente, sem downtime.
