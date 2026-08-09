from tennis import validate


def test_validate_ranking_rows_rejects_missing_name_and_bad_numbers():
    rows = [
        {"rank": 1, "name": "Valid Player", "points": 100},
        {"rank": 2, "name": "", "points": 90},           # sem nome
        {"rank": 0, "name": "Bad Rank", "points": 80},    # rank não positivo
        {"rank": 4, "name": "Bad Points", "points": -5},  # pontos negativos
        {"rank": "x", "name": "Non Numeric Rank", "points": 70},
    ]
    valid, discarded = validate.validate_ranking_rows(rows)
    assert len(valid) == 1
    assert valid[0]["name"] == "Valid Player"
    assert discarded == 4


def test_validate_ranking_rows_rejects_duplicate_rank():
    rows = [
        {"rank": 1, "name": "Player A", "points": 100},
        {"rank": 1, "name": "Player B", "points": 95},
    ]
    valid, discarded = validate.validate_ranking_rows(rows)
    assert len(valid) == 1
    assert discarded == 1


def test_validate_calendar_rows_requires_valid_iso_dates():
    rows = [
        {"name": "Valid Tournament", "starts_at": "2099-02-02", "ends_at": "2099-02-09"},
        {"name": "No Date", "starts_at": None},
        {"name": "", "starts_at": "2099-02-02"},
        {"name": "Bad Format", "starts_at": "02/02/2099"},
        {"name": "Bad End Date", "starts_at": "2099-02-02", "ends_at": "not-a-date"},
        {"name": "Open Ended Ok", "starts_at": "2099-02-02", "ends_at": None},
    ]
    valid, discarded = validate.validate_calendar_rows(rows)
    names = [r["name"] for r in valid]
    assert names == ["Valid Tournament", "Open Ended Ok"]
    assert discarded == 4


def test_discard_rate_acceptable_boundary():
    assert validate.discard_rate_acceptable(total=10, discarded=3) is True   # 30% == limite
    assert validate.discard_rate_acceptable(total=10, discarded=4) is False  # 40% > limite
    assert validate.discard_rate_acceptable(total=0, discarded=0) is False   # sem dados nunca é "aceitável"
