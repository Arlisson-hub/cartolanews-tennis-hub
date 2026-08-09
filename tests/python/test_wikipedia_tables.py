from pathlib import Path

from tennis.providers import commons
from tennis.parsers.wikipedia_tables import extract_calendar_rows, extract_ranking_rows

FIXTURES = Path(__file__).resolve().parents[1] / "fixtures"


def test_extract_ranking_rows_reads_country_and_movement():
    html = (FIXTURES / "sample-wikipedia-ranking.html").read_text(encoding="utf-8")
    tables = commons.parse_html_tables(html)
    table = commons.find_table_by_header(tables, ["player", "points", "move"])

    rows = extract_ranking_rows(table)

    assert [r["rank"] for r in rows] == [1, 2, 3]
    assert rows[0]["name"] == "Testerson Alpha"
    assert rows[0]["country_code"] == "FIC"
    assert rows[0]["points"] == 9999
    assert rows[0]["previous_rank"] is None  # "Steady" não é um número — nunca adivinhamos

    # Nome sem código entre parênteses, só com marcador de bandeira via alt="Brazil"
    beta_row = next(r for r in rows if r["name"] == "Testerson Gamma")
    assert beta_row["country_code"] == "BRA"
    assert beta_row["previous_rank"] == beta_row["rank"] + 1  # "+1" => subiu 1 posição, estava 1 acima (número maior)


def test_extract_ranking_rows_returns_empty_without_required_columns():
    table = [["Foo", "Bar"], ["1", "2"]]
    assert extract_ranking_rows(table) == []


def test_extract_calendar_rows_carries_week_forward_and_skips_doubles_subrows():
    html = (FIXTURES / "sample-calendar-table.html").read_text(encoding="utf-8")
    tables = commons.parse_html_tables(html)
    table = tables[0]

    rows = extract_calendar_rows(table, "atp")

    names = [r["name"] for r in rows]
    assert len(rows) == 3
    assert any(n.startswith("Test Open Testville, Fictionland") for n in names)
    assert any(n.startswith("Second Test Cup Otherville, Fictionland") for n in names)
    assert any(n.startswith("Third Test Championship Thirdcity, Fictionland") for n in names)
    # A linha de duplas (sem nome de torneio reconhecível) não vira uma entrada fantasma.
    assert not any("Doubles-only" in n for n in names)

    first = next(r for r in rows if r["name"].startswith("Test Open"))
    second = next(r for r in rows if r["name"].startswith("Second Test Cup"))
    assert first["date_text"] == second["date_text"] == "2 Feb"  # semana "carregada" para a 2ª linha

    third = next(r for r in rows if r["name"].startswith("Third Test"))
    assert third["date_text"] == "9 Feb"
    assert third["category"] == "grandslam"
    assert third["surface"] == "grass"


def test_extract_calendar_rows_requires_week_and_tournament_columns():
    table = [["Foo", "Bar"], ["1", "2"]]
    assert extract_calendar_rows(table, "atp") == []
