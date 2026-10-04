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

# Marcador de <br> preservado pelo parser de tabelas (ver commons.parse_html_tables).
# Precisa ser invisível, nunca presente no texto da Wikipédia e — crítico —
# NÃO ser espaço em branco para o Python: a normalização de cada célula usa
# `str.split()`, e os separadores \x1c a \x1f CONTAM como espaço
# ("\x1f".isspace() devolve True), ou seja, seriam descartados. SOH (\x01) não.
BR_MARKER = "\x01"

# Uma sede de confronto, como a fonte escreve: "Cidade, País – superfície (i)".
_VENUE_LINE = re.compile(
    r"^(?P<city>[^,]+),\s*(?P<country>[^–-]+?)\s*[–-]\s*"
    r"(?P<surface>hard|clay|grass|carpet)\s*(?P<indoor>\(i\))?$",
    re.IGNORECASE,
)


def _split_multi_venue_cell(cell: str) -> tuple[str, list[dict]] | None:
    """Separa uma célula que agrega várias sedes do mesmo confronto.

    A Wikipédia escreve a eliminatória da Davis Cup assim, numa única célula:

        <a>Davis Cup Qualifiers first round</a><br>
        <a>Düsseldorf</a>, Germany – hard (i)<br>
        <a>Quito</a>, Ecuador – clay<br>
        ... 13 sedes ...

    Cada `<br>` é uma sede com cidade, país, superfície e indoor PRÓPRIOS — a
    fonte sustenta que são confrontos distintos, e é por isso que a separação
    aqui não inventa nada: cidade, país e superfície saem da própria célula.
    O nome individual é composto de "competição + cidade, país", no mesmo
    formato dos outros torneios do calendário.

    Devolve None quando a célula não tem exatamente essa forma — nesse caso o
    chamador mantém o comportamento antigo em vez de arriscar um corte errado.
    """
    if BR_MARKER not in cell:
        return None
    segments = [segment.strip() for segment in cell.split(BR_MARKER)]
    segments = [segment for segment in segments if segment]
    if len(segments) < 3:
        return None  # competição + ao menos duas sedes
    venues = []
    for segment in segments[1:]:
        match = _VENUE_LINE.match(segment)
        if not match:
            return None  # qualquer linha fora do padrão: não separa
        venues.append({
            "city": match.group("city").strip(),
            "country": match.group("country").strip(),
            "surface": _guess_surface(segment),
        })
    return segments[0].strip(), venues


def _guess_category(text: str) -> str:
    match = _CATEGORY_PATTERN.search(text)
    if not match:
        return ""
    normalized = re.sub(r"\s+", "", match.group(1)).lower()
    # "Grand Slam" é o único rótulo que o lado PHP espera com underscore
    # (CN_Tennis_Helpers::category_label, CN_Tennis_Power_Ranking, o filtro
    # do calendário e o dropdown do admin usam todos 'grand_slam') — os
    # demais rótulos (atp1000, wta500, unitedcup, ...) não têm separador.
    return "grand_slam" if normalized == "grandslam" else normalized


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

        first_cell = raw_row[0].replace(BR_MARKER, " ").strip()
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

        # Célula que agrega várias sedes do mesmo confronto vira uma linha
        # por sede; o resto do calendário segue exatamente como antes.
        multi = _split_multi_venue_cell(tournament_cell)
        if multi:
            competition, venues = multi
            for venue in venues:
                rows.append({
                    "name": f"{competition} {venue['city']}, {venue['country']}",
                    "date_text": current_week,
                    "tour": tour,
                    "category": _guess_category(competition),
                    "surface": venue["surface"],
                    "city": venue["city"],
                    "country": venue["country"],
                })
            continue

        flat_cell = tournament_cell.replace(BR_MARKER, " ")
        flat_cell = " ".join(flat_cell.split())
        cut = _CUT_MARKERS.search(flat_cell)
        descriptive = flat_cell[: cut.start()].strip(" –-") if cut else flat_cell.strip()
        if not descriptive:
            continue

        rows.append({
            "name": descriptive,
            "date_text": current_week,
            "tour": tour,
            "category": _guess_category(flat_cell),
            "surface": _guess_surface(flat_cell),
            "city": "",
        })

    return rows
