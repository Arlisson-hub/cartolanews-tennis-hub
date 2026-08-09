from pathlib import Path

from tennis.parsers.atp_report import extract_atp_report_rows

FIXTURES = Path(__file__).resolve().parents[1] / "fixtures"


def test_extract_atp_report_rows_reads_official_numerical_format():
    text = (FIXTURES / "sample-atp-ranking-report.txt").read_text(encoding="utf-8")
    rows = extract_atp_report_rows(text, limit=100)

    assert rows == [
        {"rank": 1, "name": "Jannik Sinner", "country_code": "ITA", "points": 13450, "previous_rank": None},
        {"rank": 2, "name": "Carlos Alcaraz", "country_code": "ESP", "points": 8160, "previous_rank": None},
        {"rank": 3, "name": "Daniil Medvedev", "country_code": None, "points": 3620, "previous_rank": None},
        {"rank": 27, "name": "Joao Fonseca", "country_code": "BRA", "points": 1700, "previous_rank": None},
        {"rank": 50, "name": "Sebastian Korda", "country_code": "USA", "points": 990, "previous_rank": None},
    ]


def test_extract_atp_report_rows_respects_limit_and_ignores_noise():
    assert extract_atp_report_rows("header\n101 Player, Test (BRA) 500 0 0", limit=100) == []
