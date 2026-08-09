"""
Ranking ATP (masculino).

O site oficial (atptour.com) foi avaliado e testado manualmente: está atrás
de um desafio anti-bot da Cloudflare (HTTP 403 "Just a moment..." para
qualquer requisição identificada como automatizada — testado com o
User-Agent real deste projeto). O projeto proíbe explicitamente contornar
Cloudflare/CAPTCHA ou se passar por navegador para burlar essa proteção,
então este provider NÃO acessa atptour.com (ver docs/DATA-SOURCES.md).

Fonte usada: a tabela "ATP rankings (singles)" da Wikipédia — é o ranking
OFICIAL de entrada (não confundir com a tabela vizinha "Singles race
rankings", que é a corrida de pontos do ano corrente para o ATP Finals e
tem números diferentes; o parser abaixo pede explicitamente a coluna
"Move" para pegar a tabela certa). Cobertura real: top 20 apenas — a
Wikipédia não mantém uma tabela completa do top 100 para o ranking oficial.
Para cobertura maior, cadastre manualmente em CartolaNews Tênis →
Jogadores/Rankings ou substitua este provider por uma API paga (a
arquitetura de providers/adapters permite isso sem alterar o resto do
plugin — seção 19).
"""
from __future__ import annotations

from . import commons
from ..parsers.wikipedia_tables import extract_ranking_rows

SOURCE_URL = "https://en.wikipedia.org/wiki/ATP_rankings"
SOURCE_NAME = "Wikipédia — ATP rankings (singles), ranking oficial de entrada"


def collect(*, timeout: int = 25, retries: int = 3, min_rows: int = 15) -> list[dict]:
    html = commons.fetch_text(SOURCE_URL, timeout=timeout, retries=retries)
    tables = commons.parse_html_tables(html)
    # Exigir "move" na busca evita pegar a tabela vizinha de "race rankings"
    # (que tem "player"+"points" mas não tem coluna "Move").
    table = commons.find_table_by_header(tables, ["player", "points", "move"])
    if table is None:
        raise commons.FetchError("Tabela de ranking ATP não encontrada na página da Wikipédia (layout pode ter mudado).")

    rows = extract_ranking_rows(table)
    if len(rows) < min_rows:
        raise commons.FetchError(f"Apenas {len(rows)} linha(s) de ranking ATP extraída(s); mínimo exigido é {min_rows}.")

    return rows
