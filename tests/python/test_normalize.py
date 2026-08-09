import json
from pathlib import Path

from tennis import normalize


def test_parse_single_date_day_month_order():
    assert normalize.parse_wikipedia_date_range("2 Feb", 2099) == ("2099-02-02", None)


def test_parse_single_date_month_day_order():
    assert normalize.parse_wikipedia_date_range("Feb 2", 2099) == ("2099-02-02", None)


def test_parse_date_range_with_dash():
    assert normalize.parse_wikipedia_date_range("3-9 Feb", 2099) == ("2099-02-03", "2099-02-09")


def test_parse_date_range_spanning_months():
    assert normalize.parse_wikipedia_date_range("27 Jan - 2 Feb", 2099) == ("2099-01-27", "2099-02-02")


def test_parse_invalid_text_returns_none():
    assert normalize.parse_wikipedia_date_range("TBD", 2099) is None
    assert normalize.parse_wikipedia_date_range("", 2099) is None


def test_parse_never_returns_end_before_start():
    # "31 Dec - 1 Jan" cruzaria o ano — não adivinhamos, descartamos.
    assert normalize.parse_wikipedia_date_range("31 Dec - 1 Jan", 2099) is None


def test_build_envelope_has_required_schema_fields():
    envelope = normalize.build_envelope(source="Fonte de Teste", source_url="https://example.invalid/", data=[{"a": 1}])
    assert envelope["schema_version"] == 1
    assert envelope["source"] == "Fonte de Teste"
    assert envelope["data"] == [{"a": 1}]
    assert "generated_at" in envelope
    assert "verified_at" in envelope
    # Nunca deve haver chave que se pareça com segredo/API key no snapshot publicado.
    assert not any("key" in k.lower() or "secret" in k.lower() or "password" in k.lower() for k in envelope)


def test_write_snapshot_is_atomic_and_no_leftover_tmp(tmp_path: Path):
    target = tmp_path / "sub" / "snapshot.json"
    envelope = normalize.build_envelope(source="X", source_url="https://example.invalid/", data=[{"n": 1}])

    normalize.write_snapshot(target, envelope)

    assert target.exists()
    assert not target.with_suffix(".json.tmp").exists()
    assert json.loads(target.read_text(encoding="utf-8"))["data"] == [{"n": 1}]
