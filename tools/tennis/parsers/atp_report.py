"""Parser do relatório numérico oficial de ranking publicado pela ATP."""
from __future__ import annotations

import re

_WITH_COUNTRY = re.compile(r"^\s*(\d+)(?:T)?\s+(.+?)\s+\(([A-Z]{3})\)\s+([\d,]+)\s+")
_WITHOUT_COUNTRY = re.compile(r"^\s*(\d+)(?:T)?\s+(.+?)\s+([\d,]+)\s+")


def _display_name(report_name: str) -> str:
    report_name = " ".join(report_name.split())
    if "," not in report_name:
        return report_name
    family, given = (part.strip() for part in report_name.split(",", 1))
    return f"{given} {family}".strip()


def extract_atp_report_rows(text: str, limit: int = 100) -> list[dict]:
    rows: list[dict] = []
    seen_ranks: set[int] = set()

    for line in text.splitlines():
        match = _WITH_COUNTRY.match(line) or _WITHOUT_COUNTRY.match(line)
        if not match:
            continue

        rank = int(match.group(1))
        if rank <= 0 or rank > limit or rank in seen_ranks:
            continue

        if match.re is _WITH_COUNTRY:
            report_name, country_code, points_text = match.group(2), match.group(3), match.group(4)
        else:
            report_name, country_code, points_text = match.group(2), None, match.group(3)

        name = _display_name(report_name)
        points_digits = re.sub(r"[^\d]", "", points_text)
        if not name or not points_digits:
            continue

        rows.append({
            "rank": rank,
            "name": name,
            "country_code": country_code,
            "points": int(points_digits),
            "previous_rank": None,
        })
        seen_ranks.add(rank)

    rows.sort(key=lambda item: item["rank"])
    return rows
