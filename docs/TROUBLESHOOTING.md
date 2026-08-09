# Solução de Problemas

Primeiro passo sempre: **CartolaNews Tênis → Diagnóstico**. Ele testa banco, REST API, fontes, feeds do GitHub, cache, imagens, cron, permissões, URLs e último sync, mostrando ✅/⚠️/❌ para cada item.

## "Ranking ainda não sincronizado"

- Verifique se as URLs dos feeds estão preenchidas em **Configurações**.
- Vá em **Fontes** e clique em **Atualizar agora** na linha correspondente.
- Confira **Logs** para ver a mensagem de erro exata da última tentativa.
- Se o GitHub Actions estiver configurado, confira a aba **Actions** do repositório — a execução mais recente mostra exatamente qual etapa falhou.

## O selo "AO VIVO" não aparece / some rápido demais

Isso é esperado por design (seção 13): o selo só aparece quando a fonte confirma que a partida está em andamento **e** a última atualização está dentro da janela configurada em Configurações → "Validade do AO VIVO" (padrão 20 minutos). Depois desse tempo sem atualização, o plugin mostra "Atualização temporariamente indisponível" em vez de arriscar um placar desatualizado. Para aumentar a janela, ajuste o campo em Configurações — mas lembre-se de que isso aumenta o risco de mostrar um placar antigo como se fosse atual.

## Erro HTTP 403 ao sincronizar ranking ATP

O site oficial da ATP bloqueia acesso automatizado (Cloudflare) — isso é esperado e documentado em [DATA-SOURCES.md](DATA-SOURCES.md). O coletor usa a Wikipédia como fonte alternativa para o ranking masculino; se mesmo assim falhar, o snapshot anterior é preservado automaticamente.

## Página do jogador dá 404

Vá em **Configurações → Links permanentes** e clique em **Salvar alterações** (mesmo sem mudar nada) — isso força o WordPress a recarregar as regras de rewrite, necessário depois de ativar o plugin em alguns servidores.

## Imagens quebradas ou cortadas de forma estranha

- Isso não deveria acontecer: sem foto, o plugin sempre mostra o placeholder de iniciais, nunca um ícone de imagem quebrada.
- Se o enquadramento estiver ruim, ajuste a **posição focal** (X/Y) na tela de edição do jogador/lenda — ver [IMAGE-GUIDE.md](IMAGE-GUIDE.md).

## O painel de administração não mostra as telas / menu

Confirme que o usuário logado tem a capability `manage_options` (administrador). Todas as telas do plugin exigem essa permissão (seção 45).

## Erro ao importar do Wikimedia Commons

Mensagens comuns:

- **"Arquivo não encontrado"** — confira se digitou o título exato, incluindo o prefixo `File:`.
- **"Licença não identificada como compatível"** — o plugin só aceita `CC BY`/`CC0`/domínio público automaticamente. Para outras licenças, baixe manualmente e confirme que o uso é permitido antes de enviar pela Biblioteca de Mídia padrão do WordPress.

## LiteSpeed Cache / cache de página inteira mostra dado desatualizado

O plugin já dispara `litespeed_purge_all` sempre que sincroniza dados novos, e o bloco "Ao Vivo" se atualiza via REST independentemente do cache de página (`data-cnt-live-refresh`, ver `public/js/cn-tennis-public.js`). Se ainda assim ficar desatualizado, confira se o LiteSpeed está com "cache de página logada" ativo em excesso ou se há um CDN externo com TTL muito alto na frente do site.

## O GitHub Actions roda mas os dados não chegam no site

- Confirme os secrets `WP_URL`, `WP_USER`, `WP_APP_PASSWORD` (ver [GITHUB-ACTIONS.md](GITHUB-ACTIONS.md)).
- A Application Password precisa pertencer a um usuário com `manage_options`.
- Veja o log da execução na aba Actions — o coletor imprime `::error::` com a mensagem exata quando o WordPress recusa o envio.

## Testes falhando localmente

```bash
php tests/php/run.php          # não precisa de Composer/PHPUnit
python -m pytest tests/python  # precisa de pytest (ver tools/tennis/requirements-dev.txt)
```

Se um teste do coletor Python falhar por causa de mudança de layout numa fonte real, isso é esperado eventualmente — sites mudam. Ajuste o parser correspondente em `tools/tennis/parsers/` e adicione um caso ao fixture relevante em `tests/fixtures/`.
