"""
Ranking WTA (feminino) — fonte oficial: wtatennis.com/rankings/singles.

Verificado manualmente antes de implementar (seção 20/46):
- robots.txt de wtatennis.com não restringe nenhum caminho;
- a página é renderizada no servidor (HTML já contém a tabela completa,
  não depende de JavaScript) e responde HTTP 200 para um User-Agent
  identificado (não precisa se passar por navegador);
- retorna a tabela oficial completa (~100 jogadoras), não uma versão
  resumida.

Ver docs/DATA-SOURCES.md para o registro completo desta avaliação.
"""
from __future__ import annotations

from . import commons
from ..parsers.tour_site_tables import extract_official_ranking_rows

SOURCE_URL = "https://www.wtatennis.com/rankings/singles"
SOURCE_NAME = "WTA Tennis — Official Rankings (wtatennis.com)"


def collect(*, timeout: int = 25, retries: int = 3, min_rows: int = 15) -> list[dict]:
    html = commons.fetch_text(SOURCE_URL, timeout=timeout, retries=retries)
    tables = commons.parse_html_tables(html)
    table = commons.find_table_by_header(tables, ["rank", "player", "points"])
    if table is None:
        raise commons.FetchError("Tabela de ranking WTA não encontrada em wtatennis.com (layout pode ter mudado).")

    rows = extract_official_ranking_rows(table)
    if len(rows) < min_rows:
        raise commons.FetchError(f"Apenas {len(rows)} linha(s) de ranking WTA extraída(s); mínimo exigido é {min_rows}.")

    return rows
