"""
TheSportsDB — usado pelo coletor externo apenas como FALLBACK do calendário
(quando a extração da Wikipédia falha). A coleta de jogos do dia/ao vivo é
feita diretamente pelo plugin (chamada leve, ver
includes/providers/class-thesportsdb-provider.php) — não duplicamos essa
responsabilidade aqui, pois "ao vivo" precisa de frequência maior do que o
GitHub Actions deveria rodar (seção 23).

A chave pública de teste '123' funciona para desenvolvimento. Para uso em
produção comercial, defina a variável de ambiente TENNIS_API_KEY com uma
chave paga (Patreon) do TheSportsDB — nunca obrigatório.
"""
from __future__ import annotations

import os

from . import commons

BASE_URL = "https://www.thesportsdb.com/api/v1/json/"
LEAGUE_IDS = {"atp": "4464", "wta": "4517"}


def _api_key() -> str:
    return os.environ.get("TENNIS_API_KEY", "").strip() or "123"


def collect_calendar_fallback(tour: str, *, timeout: int = 15, retries: int = 3) -> list[dict]:
    league_id = LEAGUE_IDS.get(tour)
    if not league_id:
        return []

    url = f"{BASE_URL}{_api_key()}/eventsnextleague.php?id={league_id}"
    payload = commons.fetch_json(url, timeout=timeout, retries=retries)
    events = payload.get("events") or []

    rows: list[dict] = []
    for event in events:
        name = (event.get("strLeague") or event.get("strEvent") or "").strip()
        date = (event.get("dateEvent") or "").strip()
        if not name or not date:
            continue
        rows.append({
            "name": name,
            "starts_at": date,
            "ends_at": None,
            "tour": tour,
            "category": "",
            "surface": "",
            "city": (event.get("strVenue") or "").strip(),
            "country": (event.get("strCountry") or "").strip(),
        })
    return rows
