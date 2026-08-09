# API REST

Namespace: `cartolanews-tennis/v1`

Todos os endpoints de leitura são públicos (`permission_callback: __return_true`) e somente-leitura. Endpoints administrativos exigem `manage_options` — nunca públicos (seção 45).

## Leitura (pública)

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `/players` | Lista jogadores. Parâmetros: `gender` (male/female), `brazilian` (1), `search`, `orderby` (rank/name), `limit` |
| GET | `/players/{slug}` | Perfil de um jogador (inclui `age`, `recent_matches`, `upcoming_matches`) |
| GET | `/rankings` | Ranking mundial. Parâmetros: `gender` (male/female), `limit` (até 100) |
| GET | `/rankings/brazilians` | Brasileiros no ranking (masculino e feminino) |
| GET | `/power-ranking` | Melhores do Momento CartolaNews. Parâmetros: `gender`, `limit` |
| GET | `/matches` | Jogos de uma data. Parâmetros: `date` (Y-m-d), `gender`, `type` (singles/doubles), `status`, `limit`. Cada item traz `is_live` e `live_stale` já calculados |
| GET | `/calendar` | Torneios. Parâmetros: `from`, `to`, `tour` (atp/wta), `category`, `country`, `limit` |
| GET | `/legends` | Lendas publicadas |
| GET | `/surfaces/{surface}` | Especialistas por superfície (`hard`/`clay`/`grass`/`indoor`). Parâmetros: `gender`, `limit` |
| GET | `/head-to-head` | Confronto direto. Parâmetros: `player_a`, `player_b` (slugs) |
| GET | `/live-html` | Fragmento HTML já renderizado do bloco "Ao Vivo" (usado internamente pelo JS do frontend para atualizar sob cache de página inteira) |
| GET | `/health` | Diagnóstico completo (mesmo resultado da tela Diagnóstico do admin) |

Respostas de `/matches` e `/live-html` incluem cabeçalhos `Cache-Control: no-store` — nunca ficam presas em cache HTTP intermediário.

## Escrita (autenticado — Application Password)

| Método | Endpoint | Descrição |
|---|---|---|
| POST | `/sync` | Ingestão de snapshots do GitHub Actions (ver [GITHUB-ACTIONS.md](GITHUB-ACTIONS.md)) |
| POST | `/admin/recalculate-power-ranking` | Recalcula o Power Ranking imediatamente |

Autenticação via **WordPress Application Passwords** (HTTP Basic Auth). Gere uma em **Usuários → Seu perfil → Senhas de Aplicativo**.

```bash
curl -u "usuario:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -X POST https://cartolanews.com.br/wp-json/cartolanews-tennis/v1/admin/recalculate-power-ranking
```

## Exemplo de resposta — `/rankings?gender=male&limit=5`

```json
{
  "gender": "male",
  "last_update": "2026-08-09 20:10:51",
  "rows": [
    {
      "rank_position": 1,
      "previous_rank": 1,
      "points": 13450,
      "player_slug": "jannik-sinner",
      "player_name": "Jannik Sinner",
      "country_code": "ITA",
      "is_brazilian": 0
    }
  ]
}
```

## Segurança

- `sanitize_text_field`/`sanitize_key`/`absint`/`esc_url_raw` em toda entrada.
- Consultas SQL sempre via `$wpdb->prepare()`.
- Nenhum campo sensível (senha, chave de API) é aceito ou retornado por qualquer endpoint.
