"""
Validação antes de publicar (seção 27). Cada validador devolve a lista de
linhas válidas e a contagem de linhas descartadas — nunca lança para uma
linha ruim isolada, mas o chamador (sync.py) decide interromper a
publicação inteira se a taxa de descarte for alta demais.
"""
from __future__ import annotations

import datetime
from typing import Any


def validate_ranking_rows(rows: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], int]:
    valid = []
    discarded = 0
    seen_ranks = set()
    for row in rows:
        name = (row.get("name") or "").strip()
        rank = row.get("rank")
        points = row.get("points")
        if not name or not isinstance(rank, int) or rank <= 0 or not isinstance(points, int) or points < 0:
            discarded += 1
            continue
        if rank in seen_ranks:
            discarded += 1
            continue
        seen_ranks.add(rank)
        valid.append(row)
    return valid, discarded


def validate_calendar_rows(rows: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], int]:
    valid = []
    discarded = 0
    for row in rows:
        name = (row.get("name") or "").strip()
        starts_at = row.get("starts_at")
        if not name or not _is_valid_date(starts_at):
            discarded += 1
            continue
        ends_at = row.get("ends_at")
        if ends_at and not _is_valid_date(ends_at):
            discarded += 1
            continue
        valid.append(row)
    return valid, discarded


def _is_valid_date(value: Any) -> bool:
    if not isinstance(value, str):
        return False
    try:
        datetime.date.fromisoformat(value)
        return True
    except ValueError:
        return False


def discard_rate_acceptable(total: int, discarded: int, *, max_rate: float = 0.3) -> bool:
    """Se mais de `max_rate` das linhas coletadas forem descartadas na
    validação, algo mudou na fonte (layout, idioma) e é mais seguro NÃO
    publicar um snapshot parcial/estranho do que arriscar dado ruim."""
    if total == 0:
        return False
    return (discarded / total) <= max_rate
