# Fontes de Dados

Este documento registra, para cada fonte avaliada, o que foi **testado manualmente** antes de integrar (seção 20/46 do briefing) — não apenas suposições. Testes feitos em agosto de 2026 contra as fontes reais (`robots.txt`, resposta HTTP, estrutura da página).

## Prioridade 1 — APIs públicas/gratuitas

### TheSportsDB

- **URL:** `https://www.thesportsdb.com/api/v1/json/{key}/`
- **Uso no plugin:** jogos do dia / ao vivo (`CN_Tennis_TheSportsDB_Provider`), consumido diretamente pelo cron do WordPress (baixo volume, chamada leve — não é scraping).
- **Testado:** `eventsday.php?s=Tennis` devolve eventos reais da ATP World Tour (id 4464) e WTA Tour (id 4517), com placares por set, nomes dos jogadores e status. Confirmado com jogos reais no dia do teste.
- **Chave usada:** `123` (chave de teste pública, documentada pelo próprio serviço).
- **Licença/Termos:** o Termos de Uso (`docs_terms_of_use.php`) exige creditar a fonte e **restringe a chave gratuita a "projetos de desenvolvimento"** — publicar em produção comercial (caso do CartolaNews) formalmente pede uma assinatura paga (a partir de poucos dólares/mês, via Patreon). **Por isso o campo da chave é configurável em Configurações e nunca fixo no código** — o administrador pode (e, para uso comercial 100% aderente aos termos, deve) trocar por uma chave paga assim que possível. O plugin nunca deixa de funcionar sem ela (fallback: cache local).
- **Frequência:** a cada 30 minutos, via cron do próprio WordPress (não passa pelo GitHub Actions — ver seção 23 do briefing sobre não sobrecarregar a API).

## Prioridade 2 — Fontes oficiais públicas

### WTA — wtatennis.com (ranking feminino oficial)

- **URL:** `https://www.wtatennis.com/rankings/singles`
- **Uso no plugin:** ranking mundial feminino (`tools/tennis/providers/wta.py`).
- **Testado:** `robots.txt` sem nenhuma restrição (`Disallow:` vazio). Página é renderizada no servidor (HTML já contém a tabela completa — não depende de JavaScript) e responde HTTP 200 para um **User-Agent identificado** (`CartolaNewsTennisBot/1.0`, sem se passar por navegador). Extraídas **50 jogadoras reais** (Top 50) com posição, variação, país, idade, torneios disputados e pontos.
- **Limitação conhecida:** a visualização "Extended" (que sugere ranking estendido) é carregada via JavaScript client-side e não aparece no HTML inicial — por isso a cobertura automática vai até o Top 50, não Top 100. Torneios/posições além do Top 50 podem ser cadastrados manualmente em CartolaNews Tênis → Jogadores.
- **Licença:** dados factuais de ranking (posição/pontos) publicados pela própria entidade organizadora do esporte.
- **Frequência:** diária.

### ATP — relatório numérico oficial (Top 100)

- **URL:** `https://www.protennislive.com/posting/ramr/singles_entry_numerical.pdf`
- **Uso no plugin:** ranking mundial masculino (`tools/tennis/providers/atp.py`).
- **Origem oficial:** o PDF é vinculado pela própria página ATP Tour → Media → Rankings & Info Reports como “Singles PIF ATP Rankings Numerical” e hospedado no domínio operacional ProTennisLive.
- **O que é extraído:** posição, nome, país (quando publicado) e pontos das primeiras 100 posições. O relatório completo contém mais de mil jogadores.
- **Acesso responsável:** o HTML de `atptour.com` continua retornando Cloudflare 403 para o coletor e não é contornado; somente o relatório PDF público é baixado, uma vez ao dia, com User-Agent identificado, timeout e retry.
- **Validação:** o snapshot anterior é preservado se o PDF não puder ser lido ou se menos de 50 linhas válidas forem extraídas.
- **Frequência:** diária.

### Calendário ATP/WTA — Wikipédia (temporada do ano)

- **URLs:** `https://en.wikipedia.org/wiki/{ano}_ATP_Tour` e `https://en.wikipedia.org/wiki/{ano}_WTA_Tour`
- **Testado:** extraídos **50 torneios ATP e 46 WTA reais** (temporada 2026 completa) com nome, categoria (Grand Slam/Masters/500/250/Challenger), superfície e data de início.
- **Limitação conhecida:** a tabela de origem usa `rowspan` intenso e mistura simples/duplas na mesma estrutura; o parser separa isso com uma heurística (linha com data reconhecível = nova semana; linha sem data e com 4+ colunas = outro torneio na mesma semana; linhas menores = sub-linhas de duplas, ignoradas). O nome do torneio às vezes vem concatenado com a cidade (ex.: "Dallas Open Dallas, United States") porque a fonte não separa isso de forma inequívoca sem uma lista de cidades — nunca arriscamos cortar errado. **A data de término nem sempre é capturada** (a coluna "Week" só informa o início da semana) — nesse caso o campo `ends_at` fica vazio, nunca estimado.
- **Fallback:** `TheSportsDB` (`eventsnextleague.php`) — testado e, no momento da implementação, não retornou eventos futuros para as ligas de tênis (o esquema da API é voltado a esportes de mando fixo). Mantido como fallback mesmo assim: se a Wikipédia mudar de layout, o pipeline tenta essa fonte antes de desistir e preservar o snapshot anterior.
- **Frequência:** 3x por dia.

## Prioridade 3 — Raspagem controlada

Não foi necessário usar raspagem fora das páginas públicas da Wikipédia/wtatennis.com acima (ambas já enquadradas como fontes "oficiais públicas" da Prioridade 2, acessadas de forma respeitosa: `robots.txt` verificado, User-Agent identificado, timeout, retry com backoff exponencial, cache local, sem JavaScript/headless browser, sem burlar nenhuma proteção).

## Imagens — Wikimedia Commons

- **Uso:** importação manual pelo administrador (`CN_Tennis_Images::import_from_wikimedia()`), nunca automática/em lote.
- **Processo:** o admin informa o título do arquivo no Commons; o plugin consulta a API pública do Commons, valida que a licença é reconhecidamente livre (`CC BY`, `CC0`, domínio público — licenças `NC`/`ND` são recusadas automaticamente), baixa a imagem e grava autor, licença, URL da licença e URL de origem como créditos obrigatórios.
- **Licença:** varia por arquivo; só licenças compatíveis com uso comercial são aceitas (seção 46).

## Fontes avaliadas e descartadas

| Fonte | Motivo do descarte |
|---|---|
| **HTML de atptour.com** | Bloqueado por desafio anti-bot da Cloudflare (HTTP 403 confirmado). Não é contornado; usamos apenas o relatório PDF oficial que a própria ATP disponibiliza no ProTennisLive. |
| **Jeff Sackmann `tennis_atp`/`tennis_wta`** (dataset GitHub muito usado em análises de tênis) | Licenciado como **CC BY-NC-SA 4.0** — uso **não comercial apenas**. O CartolaNews é um portal com publicidade/monetização, então essa base foi descartada mesmo sendo tecnicamente excelente (seção 46: não usar base marcada como exclusivamente não comercial em projeto com fins comerciais). |
| **API-Sports / Sportradar / RapidAPI (tennis)** | APIs pagas. Não contratadas automaticamente (seção 20) — a arquitetura de providers permite plugá-las no futuro sem alterar o restante do plugin, caso o CartolaNews decida assinar uma. |
| **Google Imagens** | Explicitamente proibido pelo briefing (seção 29) — nunca usado. |

## Resumo de frequências

| Dado | Frequência | Onde roda |
|---|---|---|
| Ranking ATP/WTA | 1x/dia | GitHub Actions → coletor Python → REST `/sync` |
| Calendário | 3x/dia | GitHub Actions → coletor Python → REST `/sync` |
| Jogos do dia / Ao vivo | A cada 30 min | Cron do próprio WordPress → TheSportsDB |
| Power Ranking / Superfícies | Recalculado a cada sync de ranking/partidas | WordPress (cálculo local, sem chamada externa) |

## Secrets necessários (GitHub Actions)

| Secret | Uso | Obrigatório? |
|---|---|---|
| `WP_URL` | URL do site para enviar os snapshots | Sim, para publicar automaticamente (sem ele, o coletor só gera os arquivos localmente) |
| `WP_USER` | Usuário WordPress com `manage_options` | Sim (junto com `WP_APP_PASSWORD`) |
| `WP_APP_PASSWORD` | [Application Password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) do usuário acima | Sim |
| `TENNIS_API_KEY` | Chave paga do TheSportsDB (opcional) | Não — usa a chave de teste `123` se ausente |

Nenhuma credencial é publicada nos arquivos `data/tennis/*.json` (seção 25).
