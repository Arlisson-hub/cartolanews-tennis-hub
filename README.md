# CartolaNews Tennis Hub

Central completa de Tênis do CartolaNews.com.br — ranking mundial (ATP/WTA), jogos e "ao vivo" com regras honestas de atualização, calendário, brasileiros no ranking, Power Ranking próprio ("Melhores do Momento"), lendas do tênis, especialistas por superfície, perfis de jogador com URL amigável e mais.

Plugin real, instalável, modular, pronto para produção — não é uma demonstração visual. Todo dado que aparece na tela vem do banco de dados; nada é inventado quando uma fonte falha ou está incompleta.

- **Slug:** `cartolanews-tennis-hub`
- **Prefixo de classes PHP:** `CN_Tennis_`
- **Shortcode principal:** `[cn_tenis_hub]`
- **Requer:** WordPress 6.4+, PHP 8.1+

## Instalação rápida

1. `Plugins → Adicionar plugin → Enviar plugin`, selecione `cartolanews-tennis-hub.zip`, clique em Instalar e depois em Ativar.
2. O plugin cria as tabelas, opções padrão e agenda o cron automaticamente — nenhum passo manual no banco é necessário.
3. Acesse **CartolaNews Tênis → Configurações** e informe as URLs dos feeds JSON (ver [docs/GITHUB-ACTIONS.md](docs/GITHUB-ACTIONS.md)) publicados pelo coletor deste repositório.
4. Crie uma página no WordPress com o shortcode `[cn_tenis_hub]` (funciona no editor clássico, blocos ou no widget Shortcode do Elementor).

Detalhes completos em [docs/INSTALL.md](docs/INSTALL.md).

## O que é automático e o que é manual

| Dado | Automático? | Fonte |
|---|---|---|
| Ranking mundial ATP/WTA | Sim | Feed JSON (GitHub Actions → coletor Python) |
| Calendário de torneios | Sim | Feed JSON (GitHub Actions → coletor Python) |
| Jogos do dia / Ao vivo | Sim | TheSportsDB, consultado diretamente pelo cron do plugin |
| Power Ranking ("Melhores do Momento") | Sim | Calculado no WordPress a partir das partidas já salvas |
| Especialistas por superfície | Sim | Calculado no WordPress a partir das partidas já salvas |
| Lendas do tênis | Manual (com opção de importar JSON) | Cadastro no admin |
| Fotos | Manual, com atalho de importação do Wikimedia Commons | Admin |
| Notícias | Automático a partir de posts do próprio WordPress | Categoria configurável |

## Shortcodes

| Shortcode | Descrição |
|---|---|
| `[cn_tenis_hub]` | Central completa (hero, resumo, rankings, brasileiros, power ranking, lendas, ao vivo, calendário, superfícies, grand slams, notícias) |
| `[cn_tenis_ao_vivo]` | Só as partidas confirmadamente ao vivo |
| `[cn_tenis_jogos]` | Jogos do dia com abas Todos/Masculino/Feminino/Duplas |
| `[cn_tenis_ranking sexo="ambos" limite="20"]` | Ranking mundial masculino e/ou feminino |
| `[cn_tenis_brasileiros]` | Brasileiros no ranking mundial |
| `[cn_tenis_power_ranking sexo="masculino" limite="10"]` | Power Ranking CartolaNews |
| `[cn_tenis_calendario]` | Calendário de torneios com filtros |
| `[cn_tenis_lendas]` | Lendas do tênis |
| `[cn_tenis_superficies]` | Especialistas por superfície |

Todos funcionam isolados (inclusive dentro do widget Shortcode do Elementor) e carregam CSS/JS somente nas páginas onde aparecem.

## Arquitetura

```
cartolanews-tennis-hub/
├── cartolanews-tennis-hub.php     Bootstrap do plugin
├── uninstall.php                  Limpeza opcional (preserva dados por padrão)
├── includes/                      Lógica do plugin (repositórios, sync, REST, power ranking...)
│   └── providers/                 Providers/adapters de dados (Manual, GitHub, TheSportsDB, ATP, WTA, Fallback)
├── admin/                         Painel administrativo (11 telas)
├── public/                        Templates, CSS e JS do frontend
├── assets/                        Ícones e SVGs próprios do plugin
├── tools/tennis/                  Coletor Python (roda no GitHub Actions, nunca no WordPress)
├── config/tennis-sources.yml      Configuração das fontes do coletor
├── data/tennis/                   Snapshots JSON publicados pelo coletor
├── tests/                         Testes PHP e Python + fixtures fictícias
└── docs/                          Documentação completa
```

Veja a arquitetura de providers em [docs/DATA-SOURCES.md](docs/DATA-SOURCES.md) e o fluxo de sincronização em [docs/GITHUB-ACTIONS.md](docs/GITHUB-ACTIONS.md).

## Documentação

- [docs/INSTALL.md](docs/INSTALL.md) — instalação e configuração inicial
- [docs/DATA-SOURCES.md](docs/DATA-SOURCES.md) — fontes avaliadas, usadas e descartadas, com motivos
- [docs/GITHUB-ACTIONS.md](docs/GITHUB-ACTIONS.md) — como funciona a sincronização automática
- [docs/API.md](docs/API.md) — endpoints REST
- [docs/IMAGE-GUIDE.md](docs/IMAGE-GUIDE.md) — tamanhos de imagem e posição focal
- [docs/TROUBLESHOOTING.md](docs/TROUBLESHOOTING.md) — solução de problemas comuns

## Testes

```bash
# PHP (sem dependências — roda com o php.exe do sistema)
php tests/php/run.php

# Python (coletor)
cd tools && pip install -r tennis/requirements-dev.txt
python -m pytest ../tests/python -v
```

## Licença

GPLv2 ou posterior, como todo plugin WordPress.
