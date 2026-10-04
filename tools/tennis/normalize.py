"""
Normalização final + escrita do envelope padrão (seção 25):

{
  "schema_version": 1,
  "generated_at": "...",
  "source": "...",
  "source_url": "...",
  "verified_at": "...",
  "data": [...]
}

Nunca inclui API keys nos arquivos gerados.
"""
from __future__ import annotations

import calendar
import datetime
import json
import re
from pathlib import Path
from typing import Any

_MONTHS = {name.lower(): index for index, name in enumerate(calendar.month_name) if name}
_MONTHS.update({name.lower(): index for index, name in enumerate(calendar.month_abbr) if name})

# "3–9 Feb", "27 Jan – 2 Feb", "3 Feb 2026 – 9 Feb 2026", "3 Feb"
_DATE_RANGE = re.compile(
    r"(?:(?P<d1>\d{1,2})\s*)?(?P<m1>[A-Za-z]+)?\s*[-–—]?\s*(?P<d2>\d{1,2})\s+(?P<m2>[A-Za-z]+)(?:\s+(?P<y2>\d{4}))?"
)


_SINGLE_DATE_DM = re.compile(r"^(?P<d>\d{1,2})\s+(?P<m>[A-Za-z]{3,})$")
_SINGLE_DATE_MD = re.compile(r"^(?P<m>[A-Za-z]{3,})\s+(?P<d>\d{1,2})$")


def parse_wikipedia_date_range(text: str, year: int) -> tuple[str, str | None] | None:
    """Converte um texto de data de calendário da Wikipédia em
    (starts_at, ends_at) ISO-8601. `ends_at` vem None quando a fonte só
    informa a data de início (ex.: coluna "Week" da tabela de temporada,
    que traz só "2 Feb" no calendário ATP ou "Feb 2" no calendário WTA —
    suportamos as duas ordens) — nunca estimamos quantos dias o torneio
    dura, isso seria inventar dado. Retorna None se não reconhecer nada
    com confiança.
    """
    text = text.replace("–", "-").replace("—", "-").strip()

    single = _SINGLE_DATE_DM.match(text) or _SINGLE_DATE_MD.match(text)
    if single:
        month = _MONTHS.get(single.group("m").lower())
        if not month:
            return None
        try:
            starts_at = datetime.date(year, month, int(single.group("d")))
        except ValueError:
            return None
        return starts_at.isoformat(), None

    match = _DATE_RANGE.search(text)
    if not match:
        return None

    month2 = _MONTHS.get((match.group("m2") or "").lower())
    if not month2:
        return None
    day2 = match.group("d2")
    if not day2:
        return None
    end_year = int(match.group("y2")) if match.group("y2") else year

    month1 = _MONTHS.get((match.group("m1") or "").lower(), month2)
    day1 = match.group("d1") or day2

    try:
        starts_at = datetime.date(end_year, month1, int(day1))
        ends_at = datetime.date(end_year, month2, int(day2))
    except ValueError:
        return None

    if starts_at > ends_at:
        # Torneio que atravessa a virada do ano (raro) — não adivinhamos,
        # descartamos para não publicar uma data inconsistente.
        return None

    return starts_at.isoformat(), ends_at.isoformat()


def merge_cross_tour_events(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Funde o mesmo evento visto pelas duas páginas de tour numa linha só.

    Grand Slam e United Cup têm chave masculina e feminina no MESMO torneio, e
    aparecem tanto em `<ano>_ATP_Tour` quanto em `<ano>_WTA_Tour`. Nos demais
    torneios o próprio nome traz o nível ("... ATP 250" / "... WTA 500"), então
    as duas páginas geram nomes diferentes e nada é fundido aqui. Para os de
    categoria neutra ("Grand Slam", "United Cup") o nome sai idêntico — e, como
    `external_id` é derivado de nome + data, as duas linhas colidiriam na
    UNIQUE KEY (provider, external_id) do banco.

    A evidência de que é o mesmo evento é estrutural: mesmo nome, mesma data de
    início e mesma superfície, vindos de tours diferentes. Nesse caso vira uma
    linha com `tour="both"` — a mesma convenção que o administrador já usa nos
    Grand Slams cadastrados à mão. Qualquer divergência de superfície impede a
    fusão: aí preferimos duas linhas a inventar uma.
    """
    agrupado: dict[tuple[str, str], list[dict[str, Any]]] = {}
    ordem: list[tuple[str, str]] = []
    for row in rows:
        chave = ((row.get("name") or "").strip(), str(row.get("starts_at") or ""))
        if chave not in agrupado:
            agrupado[chave] = []
            ordem.append(chave)
        agrupado[chave].append(row)

    saida: list[dict[str, Any]] = []
    for chave in ordem:
        grupo = agrupado[chave]
        tours = {row.get("tour") for row in grupo}
        superficies = {row.get("surface") or "" for row in grupo}
        if len(grupo) > 1 and tours == {"atp", "wta"} and len(superficies) == 1:
            fundido = dict(grupo[0])
            fundido["tour"] = "both"
            saida.append(fundido)
            continue
        saida.extend(grupo)
    return saida


def build_envelope(*, source: str, source_url: str, data: list[dict[str, Any]]) -> dict[str, Any]:
    now = datetime.datetime.now(datetime.timezone.utc).isoformat()
    return {
        "schema_version": 1,
        "generated_at": now,
        "source": source,
        "source_url": source_url,
        "verified_at": now,
        "data": data,
    }


def write_snapshot(path: Path, envelope: dict[str, Any]) -> None:
    """Escrita atômica (grava em .tmp e substitui) para nunca deixar um
    snapshot corrompido caso o processo seja interrompido no meio."""
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp_path = path.with_suffix(path.suffix + ".tmp")
    tmp_path.write_text(json.dumps(envelope, ensure_ascii=False, indent=2), encoding="utf-8")
    tmp_path.replace(path)
