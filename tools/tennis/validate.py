"""
Validação antes de publicar (seção 27). Cada validador devolve a lista de
linhas válidas e a contagem de linhas descartadas — nunca lança para uma
linha ruim isolada, mas o chamador (sync.py) decide interromper a
publicação inteira se a taxa de descarte for alta demais.
"""
from __future__ import annotations

import datetime
import re
import unicodedata
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


# Limites reais das colunas do WordPress (wp_cn_tennis_tournaments):
# `name` varchar(190) e `external_id` varchar(100). O lado PHP deriva o
# external_id de `name:<slug>:<starts_at>`, então o nome também limita o
# identificador. Publicar uma linha acima disso faz o wpdb recusar o INSERT —
# foi o que aconteceu com a eliminatória da Davis Cup agregada em 2026.
NAME_MAX = 190
EXTERNAL_ID_MAX = 100


def derived_external_id(name: str, starts_at: str) -> str:
    """Mesmo identificador que CN_Tennis_Data_Normalizer::tournament_row()
    monta no WordPress, para poder medir o tamanho aqui."""
    slug = unicodedata.normalize("NFKD", name)
    slug = "".join(ch for ch in slug if not unicodedata.combining(ch))
    slug = re.sub(r"[^a-z0-9]+", "-", slug.lower()).strip("-")
    return f"name:{slug}:{starts_at}"


def calendar_row_too_long(row: dict[str, Any]) -> str | None:
    """Motivo pelo qual a linha não cabe no banco, ou None se couber."""
    name = (row.get("name") or "").strip()
    starts_at = str(row.get("starts_at") or "")
    if len(name) > NAME_MAX:
        return f"name com {len(name)} caracteres (limite {NAME_MAX})"
    external_id = derived_external_id(name, starts_at)
    if len(external_id) > EXTERNAL_ID_MAX:
        return f"external_id derivado com {len(external_id)} caracteres (limite {EXTERNAL_ID_MAX})"
    return None


def validate_calendar_rows(rows: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], int, list[str]]:
    """@return (linhas válidas, descartadas, motivos dos descartes por tamanho)"""
    valid = []
    discarded = 0
    oversized: list[str] = []
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
        too_long = calendar_row_too_long(row)
        if too_long:
            discarded += 1
            oversized.append(f"{name[:60]}...: {too_long}")
            continue
        valid.append(row)
    return valid, discarded, oversized


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
