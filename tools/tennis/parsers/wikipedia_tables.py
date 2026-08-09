"""
Extração de linhas de ranking/calendário a partir das tabelas HTML da
Wikipédia (en.wikipedia.org). Regras:

- Nunca inventa um campo que não conseguiu ler com confiança (ex.: país ou
  variação de ranking) — nesse caso o campo fica vazio/None e é o WordPress
  quem decide como exibir a ausência (nunca fabricamos aqui).
- Aceita pequenas variações de nome de coluna (case-insensitive, substring),
  porque o layout da Wikipédia muda com o tempo.
"""
from __future__ import annotations

import re

COUNTRY_NAME_TO_ISO3 = {
    "italy": "ITA", "spain": "ESP", "germany": "GER", "serbia": "SRB", "russia": "RUS",
    "united states": "USA", "brazil": "BRA", "france": "FRA", "great britain": "GBR",
    "united kingdom": "GBR", "australia": "AUS", "argentina": "ARG", "canada": "CAN",
    "switzerland": "SUI", "greece": "GRE", "norway": "NOR", "poland": "POL",
    "czech republic": "CZE", "czechia": "CZE", "croatia": "CRO", "japan": "JPN",
    "china": "CHN", "kazakhstan": "KAZ", "belgium": "BEL", "netherlands": "NED",
    "austria": "AUT", "denmark": "DEN", "sweden": "SWE", "bulgaria": "BUL",
    "ukraine": "UKR", "chile": "CHI", "colombia": "COL", "mexico": "MEX",
    "portugal": "POR", "hungary": "HUN", "romania": "ROU", "slovenia": "SLO",
    "finland": "FIN", "india": "IND", "south korea": "KOR", "new zealand": "NZL",
    "south africa": "RSA", "egypt": "EGY", "turkey": "TUR", "israel": "ISR",
    "latvia": "LAT", "estonia": "EST", "lithuania": "LTU", "slovakia": "SVK",
    "taiwan": "TPE", "chinese taipei": "TPE", "philippines": "PHI",
}

_CODE_IN_PARENS = re.compile(r"\(([A-Z]{3})\)")
_COUNTRY_MARKER = re.compile(r"\[\[([^\]]+)\]\]")
_LEADING_INT = re.compile(r"-?\d+")


def _extract_country(cell_text: str) -> tuple[str, str | None]:
    """Retorna (nome_limpo_sem_marcadores, codigo_iso3_ou_None)."""
    code = None
    match = _CODE_IN_PARENS.search(cell_text)
    if match:
        code = match.group(1)

    marker = _COUNTRY_MARKER.search(cell_text)
    if not code and marker:
        code = COUNTRY_NAME_TO_ISO3.get(marker.group(1).strip().lower())

    clean = _COUNTRY_MARKER.sub("", cell_text)
    clean = _CODE_IN_PARENS.sub("", clean)
    return " ".join(clean.split()), code


def extract_ranking_rows(table: list[list[str]]) -> list[dict]:
    """`table` inclui a linha de cabeçalho em table[0]."""
    header = [cell.lower() for cell in table[0]]

    def col_index(*needles: str) -> int | None:
        for index, cell in enumerate(header):
            if any(needle in cell for needle in needles):
                return index
        return None

    rank_col = col_index("no.", "rank")
    player_col = col_index("player", "name")
    points_col = col_index("points")
    move_col = col_index("move", "change", "+/-")

    if rank_col is None or player_col is None or points_col is None:
        return []

    rows: list[dict] = []
    for raw_row in table[1:]:
        if len(raw_row) <= max(rank_col, player_col, points_col):
            continue

        rank_match = _LEADING_INT.search(raw_row[rank_col])
        if not rank_match:
            continue
        rank = int(rank_match.group())

        name, country_code = _extract_country(raw_row[player_col])
        if not name:
            continue

        points_digits = re.sub(r"[^\d]", "", raw_row[points_col])
        if not points_digits:
            continue
        points = int(points_digits)

        previous_rank = None
        if move_col is not None and move_col < len(raw_row):
            move_match = re.fullmatch(r"[+-]?\d+", raw_row[move_col].strip())
            if move_match:
                delta = int(move_match.group())
                # delta positivo = subiu no ranking (posição numérica menor);
                # a posição anterior era MAIOR: previous = rank + delta.
                previous_rank = rank + delta if delta != 0 else rank

        rows.append({
            "rank": rank,
            "name": name,
            "country_code": country_code,
            "points": points,
            "previous_rank": previous_rank,
        })

    rows.sort(key=lambda item: item["rank"])
    return rows


_WEEK_DATE = re.compile(r"^(?:\d{1,2}\s+[A-Za-z]{3,}|[A-Za-z]{3,}\s+\d{1,2})$")
_CATEGORY_PATTERN = re.compile(
    r"\b(Grand Slam|ATP\s?1000|ATP\s?500|ATP\s?250|WTA\s?1000|WTA\s?500|WTA\s?250|Masters\s?1000|Challenger|ATP\s?Finals|WTA\s?Finals|United\s?Cup|Laver\s?Cup|Olympics)\b",
    re.IGNORECASE,
)
_SURFACE_PATTERN = re.compile(r"\b(Hard|Clay|Grass|Carpet)\b\s*(\(i\))?", re.IGNORECASE)
_CUT_MARKERS = re.compile(r"[€$£]|\d")


def _guess_category(text: str) -> str:
    match = _CATEGORY_PATTERN.search(text)
    if not match:
        return ""
    return re.sub(r"\s+", "", match.group(1)).lower()


def _guess_surface(text: str) -> str:
    match = _SURFACE_PATTERN.search(text)
    if not match:
        return ""
    word = match.group(1).lower()
    indoor = bool(match.group(2))
    if word == "carpet":
        return "indoor"
    if indoor:
        return "indoor"
    return word


def extract_calendar_rows(table: list[list[str]], tour: str) -> list[dict]:
    """Extrai linhas de calendário das tabelas de temporada da Wikipédia
    (layout real: colunas Week / Tournament / Champions / Runners-up / ...,
    com `rowspan` na coluna Week quando várias tabelas acontecem na mesma
    semana — por isso o "carregamos" para as linhas seguintes até a
    próxima data aparecer). Linhas que não conseguimos classificar com
    confiança (ex.: sub-linhas de duplas, sem nome de torneio) são
    ignoradas — nunca inventamos a que torneio elas pertencem.

    O texto de nome/cidade/país do torneio vem concatenado na mesma célula
    na fonte original e não é possível separá-los com segurança só com
    regex (sem uma lista de cidades) — por isso `name` mantém o texto
    completo (torneio + cidade + país) em vez de arriscar um corte errado;
    o administrador pode editar manualmente em CartolaNews Tênis →
    Torneios se quiser normalizar.
    """
    header = [cell.lower() for cell in table[0]]
    if not any("week" in cell for cell in header) or not any("tournament" in cell for cell in header):
        return []

    rows: list[dict] = []
    current_week: str | None = None

    for raw_row in table[1:]:
        if not raw_row:
            continue

        first_cell = raw_row[0].strip()
        if _WEEK_DATE.match(first_cell):
            current_week = first_cell
            tournament_cell = raw_row[1] if len(raw_row) > 1 else ""
        elif len(raw_row) >= 4:
            # Rowspan da coluna Week "escondeu" a data nesta linha; ainda
            # estamos na mesma semana da última data vista.
            tournament_cell = first_cell
        else:
            continue  # provavelmente uma sub-linha de duplas; não há nome de torneio para associar com confiança

        if not current_week or not tournament_cell:
            continue

        cut = _CUT_MARKERS.search(tournament_cell)
        descriptive = tournament_cell[: cut.start()].strip(" –-") if cut else tournament_cell.strip()
        if not descriptive:
            continue

        rows.append({
            "name": descriptive,
            "date_text": current_week,
            "tour": tour,
            "category": _guess_category(tournament_cell),
            "surface": _guess_surface(tournament_cell),
            "city": "",
        })

    return rows
