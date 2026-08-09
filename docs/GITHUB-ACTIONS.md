# GitHub Actions — Sincronização Automática

A coleta de dados pesada (ranking, calendário) roda **fora do WordPress**, neste repositório, e nunca no servidor de produção (seção 21 do briefing).

## Workflow

Arquivo: [`.github/workflows/tennis-sync.yml`](../.github/workflows/tennis-sync.yml)

- **`workflow_dispatch`** — permite rodar manualmente pela aba Actions do GitHub, escolhendo o alvo (`all`/`rankings`/`calendar`) e um modo `dry_run` que não envia nada ao WordPress (só gera os arquivos locais, útil para testar).
- **`schedule`** — três agendamentos:
  - `17 6 * * *` (diário) → rankings ATP/WTA;
  - `27 10 * * *` e `27 18 * * *` (2x/dia) → calendário.
- **"Ao vivo"/jogos do dia não fazem parte deste workflow** — essa frequência (30 em 30 minutos) é alta demais para depender do agendador do GitHub Actions e é tratada diretamente pelo cron do próprio plugin no WordPress, que consulta o TheSportsDB (ver `includes/class-cron.php`).

## O que o workflow faz

1. `actions/checkout` + `actions/setup-python`.
2. Instala `tools/tennis/requirements.txt` (única dependência real: `pyyaml`).
3. Roda `python -m tennis.sync --target <alvo>`, que:
   1. **obtém os dados** (`tools/tennis/providers/*.py`);
   2. **valida** (`tools/tennis/validate.py`) — descarta linhas ruins, e se a taxa de descarte for alta demais, não publica nada naquela execução;
   3. **normaliza** para o envelope padrão (`tools/tennis/normalize.py`);
   4. **compara com o snapshot anterior** — só reescreve `data/tennis/*.json` se os dados realmente mudaram (evita commits diários só por causa do timestamp);
   5. **envia ao WordPress** via `POST /wp-json/cartolanews-tennis/v1/sync`, autenticado com Application Password — só quando o arquivo mudou e os secrets estão configurados.
4. Faz commit dos snapshots atualizados de volta no repositório (`data/tennis/*.json`), com um bot de commit próprio (`cartolanews-tennis-bot`).

## Secrets necessários

Configure em **Settings → Secrets and variables → Actions** do repositório:

| Secret | Descrição |
|---|---|
| `WP_URL` | URL base do site, ex.: `https://cartolanews.com.br` |
| `WP_USER` | Usuário do WordPress com permissão `manage_options` |
| `WP_APP_PASSWORD` | [Application Password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) gerada em **Usuários → Seu perfil → Senhas de Aplicativo** |
| `TENNIS_API_KEY` | (Opcional) chave paga do TheSportsDB, se o CartolaNews assinar um plano — sem ela, usa a chave de teste `123` |

Nenhum secret é impresso em log nem gravado nos arquivos `data/tennis/*.json`.

## Rodando localmente

```bash
cd tools
pip install -r tennis/requirements.txt
python -m tennis.sync --target all --dry-run   # gera os snapshots sem enviar ao WordPress

# Com envio real (defina as variáveis de ambiente antes):
WP_URL=https://cartolanews.com.br WP_USER=usuario WP_APP_PASSWORD="xxxx xxxx xxxx xxxx" \
  python -m tennis.sync --target all
```

## Endpoint REST que recebe os dados

`POST /wp-json/cartolanews-tennis/v1/sync` — implementado em `includes/class-rest.php::sync_ingest()`.

Corpo esperado (mesmo envelope de `data/tennis/*.json`, com um campo `type` adicional):

```json
{
  "schema_version": 1,
  "generated_at": "2026-08-09T20:10:00+00:00",
  "source": "WTA Tennis — Official Rankings (wtatennis.com)",
  "source_url": "https://www.wtatennis.com/rankings/singles",
  "type": "rankings_female",
  "data": [ { "rank": 1, "name": "...", "country_code": "BLR", "points": 8550 } ]
}
```

`type` aceito: `rankings_male`, `rankings_female`, `calendar`.

Autenticação: **WordPress Application Passwords** (HTTP Basic Auth nativo do core) — o `permission_callback` exige `manage_options`. Nenhum endpoint administrativo é público (seção 45).

## Configuração das fontes sem editar código

[`config/tennis-sources.yml`](../config/tennis-sources.yml) controla URL, provider, parser, mínimo de linhas, timeout, tentativas e prioridade de cada fonte — editável diretamente no GitHub, sem precisar mexer no código Python (seção 22).
