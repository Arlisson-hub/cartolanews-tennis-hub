from pathlib import Path

from tennis.providers import commons

FIXTURES = Path(__file__).resolve().parents[1] / "fixtures"


def test_parse_html_tables_keeps_nested_tables_separate():
    """Regressão: uma tabela de wrapper contendo duas tabelas de dados
    lado a lado não pode misturar as linhas das duas (bug real encontrado
    e corrigido durante o desenvolvimento — ver comentário em commons.py)."""
    html = (FIXTURES / "sample-wikipedia-ranking.html").read_text(encoding="utf-8")
    tables = commons.parse_html_tables(html)

    race_table = commons.find_table_by_header(tables, ["player", "points", "tourn"])
    official_table = commons.find_table_by_header(tables, ["player", "points", "move"])

    assert race_table is not None
    assert official_table is not None
    # As duas tabelas devem ter pontuações diferentes para o mesmo jogador
    # (prova de que não foram fundidas em uma lista só).
    assert race_table[1][2] == "1,000"
    assert official_table[1][2] == "9,999"


def test_find_table_by_header_returns_none_when_absent():
    tables = [[["A", "B"], ["1", "2"]]]
    assert commons.find_table_by_header(tables, ["player", "points"]) is None


def test_find_table_by_header_skips_caption_row():
    tables = [[
        ["Some caption line"],
        ["No.", "Player", "Points"],
        ["1", "Someone", "100"],
    ]]
    found = commons.find_table_by_header(tables, ["player", "points"])
    assert found is not None
    assert found[0] == ["No.", "Player", "Points"]


def test_fetch_text_retries_then_raises(monkeypatch):
    attempts = []

    def fake_urlopen(request, timeout):  # noqa: ARG001
        attempts.append(1)
        raise commons.urllib.error.URLError("boom")

    monkeypatch.setattr(commons.urllib.request, "urlopen", fake_urlopen)
    monkeypatch.setattr(commons.time, "sleep", lambda _seconds: None)

    try:
        commons.fetch_text("https://example.invalid/", retries=3)
        assert False, "deveria ter levantado FetchError"
    except commons.FetchError:
        pass

    assert len(attempts) == 3
