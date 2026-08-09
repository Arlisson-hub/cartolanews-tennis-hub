"""
Parser da tabela oficial de ranking publicada em wtatennis.com/rankings/singles
(HTML server-renderizado, confirmado por teste manual em 2026-08 — ver
docs/DATA-SOURCES.md). Layout típico de linha:

    rank_cell:   "1 -"        (posição + variação: "-", "+2", "-3" ou "New")
    player_cell: "Aryna Sabalenka [[BLR]] BLR"   (nome + país duplicado)
    points_cell: "8,550"

Assim como em wikipedia_tables.py, qualquer campo não reconhecido com
confiança fica vazio — nunca é adivinhado.
"""
from __future__ import annotations

import re

_RANK_MOVE = re.compile(r"^(\d+)\s*(.*)$")
# Sinal é opcional no site oficial (ex.: "1" para subiu 1 posição, "-1" para
# caiu 1 posição — confirmado observando linhas reais onde um jogador subindo
# N posições empurra os demais para baixo em 1, todos consistentes entre si).
_MOVE_DELTA = re.compile(r"^[+-]?\d+$")
_TRAILING_CODE = re.compile(r"\b([A-Z]{3})$")
_COUNTRY_MARKER = re.compile(r"\[\[([^\]]+)\]\]")


def extract_official_ranking_rows(table: list[list[str]]) -> list[dict]:
    header = [cell.lower() for cell in table[0]]

    def col_index(*needles: str) -> int | None:
        for index, cell in enumerate(header):
            if any(needle in cell for needle in needles):
                return index
        return None

    rank_col = col_index("rank")
    player_col = col_index("player")
    points_col = col_index("points")
    tournaments_col = col_index("tournament")

    if rank_col is None or player_col is None or points_col is None:
        return []

    rows: list[dict] = []
    for raw_row in table[1:]:
        if len(raw_row) <= max(rank_col, player_col, points_col):
            continue

        rank_match = _RANK_MOVE.match(raw_row[rank_col].strip())
        if not rank_match:
            continue
        rank = int(rank_match.group(1))
        movement_text = rank_match.group(2).strip()

        previous_rank = None
        if _MOVE_DELTA.match(movement_text):
            delta = int(movement_text)
            # delta positivo = subiu no ranking (posição numérica menor),
            # então a posição anterior era MAIOR: previous = rank + delta.
            previous_rank = rank + delta

        player_cell = _COUNTRY_MARKER.sub("", raw_row[player_col]).strip()
        code_match = _TRAILING_CODE.search(player_cell)
        country_code = code_match.group(1) if code_match else None
        name = _TRAILING_CODE.sub("", player_cell).strip() if code_match else player_cell
        if not name:
            continue

        points_digits = re.sub(r"[^\d]", "", raw_row[points_col])
        if not points_digits:
            continue

        tournaments_played = None
        if tournaments_col is not None and tournaments_col < len(raw_row):
            digits = re.sub(r"[^\d]", "", raw_row[tournaments_col])
            tournaments_played = int(digits) if digits else None

        rows.append({
            "rank": rank,
            "name": name,
            "country_code": country_code,
            "points": int(points_digits),
            "previous_rank": previous_rank,
            "tournaments_played": tournaments_played,
        })

    rows.sort(key=lambda item: item["rank"])
    return rows
