from pathlib import Path

from tennis.providers import commons
from tennis.parsers.tour_site_tables import extract_official_ranking_rows

FIXTURES = Path(__file__).resolve().parents[1] / "fixtures"


def test_extract_official_ranking_rows_parses_rank_movement_and_country():
    html = (FIXTURES / "sample-tour-site-ranking.html").read_text(encoding="utf-8")
    tables = commons.parse_html_tables(html)
    table = commons.find_table_by_header(tables, ["rank", "player", "points"])

    rows = extract_official_ranking_rows(table)

    assert len(rows) == 4
    assert rows[0] == {
        "rank": 1, "name": "Testera Prima", "country_code": "BRA",
        "points": 8550, "previous_rank": None, "tournaments_played": 18,
    }
    assert rows[1]["previous_rank"] == 3  # rank 2, subiu 1 => estava em 3
    assert rows[2]["previous_rank"] == 2  # rank 3, caiu 1 => estava em 2
    assert rows[3]["previous_rank"] is None  # "New" não é numérico — não inventamos


def test_extract_official_ranking_rows_empty_without_columns():
    assert extract_official_ranking_rows([["Foo", "Bar"], ["1", "2"]]) == []
