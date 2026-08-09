#!/usr/bin/env python3
"""
Orquestrador principal do coletor de tênis do CartolaNews. Roda no GitHub
Actions (nunca no servidor WordPress — seção 21). Fluxo por seção:

  1. obter dados (providers/*.py)
  2. validar dados (validate.py)
  3. normalizar dados (parsers/*.py + normalize.py)
  4. comparar com o snapshot anterior (evita commits/envios sem mudança real)
  5. gravar snapshot local (data/tennis/*.json)
  6. enviar somente informações válidas para o WordPress (REST /sync)

Uso:
    python -m tennis.sync --target all
    python -m tennis.sync --target rankings --dry-run

Executar a partir da pasta tools/:
    cd tools && python -m tennis.sync
"""
from __future__ import annotations

import argparse
import base64
import datetime
import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path

import yaml

from .providers import atp, commons, thesportsdb, wta
from .parsers.wikipedia_tables import extract_calendar_rows
from . import normalize, validate

REPO_ROOT = Path(__file__).resolve().parents[2]
CONFIG_PATH = REPO_ROOT / "config" / "tennis-sources.yml"
DATA_DIR = REPO_ROOT / "data" / "tennis"


def log(message: str, *, level: str = "notice") -> None:
    """Mensagem no formato de anotação do GitHub Actions, também legível em
    execução local (workflow_dispatch manual — seção 23)."""
    print(f"::{level}::{message}" if os.environ.get("GITHUB_ACTIONS") else f"[{level}] {message}")


def load_config() -> dict:
    with CONFIG_PATH.open("r", encoding="utf-8") as handle:
        return yaml.safe_load(handle)


def existing_data(path: Path) -> list | None:
    if not path.exists():
        return None
    try:
        return json.loads(path.read_text(encoding="utf-8")).get("data")
    except (json.JSONDecodeError, OSError):
        return None


def maybe_write_snapshot(path: Path, envelope: dict) -> bool:
    """Só reescreve o arquivo se os dados realmente mudaram — evita commits
    diários só por causa do timestamp (seção 24, passo "comparar com dados
    anteriores")."""
    if existing_data(path) == envelope["data"]:
        log(f"{path.name}: sem mudanças desde o último snapshot, mantendo arquivo atual.")
        return False
    normalize.write_snapshot(path, envelope)
    log(f"{path.name}: snapshot atualizado com {len(envelope['data'])} registro(s).")
    return True


def sync_rankings(config: dict, *, changed: dict[str, bool]) -> None:
    for gender, provider_module, out_name in (("male", atp, "rankings-atp.json"), ("female", wta, "rankings-wta.json")):
        cfg = config["rankings"]["male" if gender == "male" else "female"]
        if not cfg.get("enabled", True):
            log(f"Ranking {gender}: fonte desativada em tennis-sources.yml, pulando.")
            continue
        try:
            rows = provider_module.collect(
                timeout=cfg.get("timeout_seconds", 25),
                retries=cfg.get("retries", 3),
                min_rows=cfg.get("min_rows", 15),
            )
            valid_rows, discarded = validate.validate_ranking_rows(rows)
            if not validate.discard_rate_acceptable(len(rows), discarded):
                raise commons.FetchError(f"Taxa de descarte alta na validação ({discarded}/{len(rows)}); snapshot anterior preservado.")

            envelope = normalize.build_envelope(source=provider_module.SOURCE_NAME, source_url=provider_module.SOURCE_URL, data=valid_rows)
            changed[out_name] = maybe_write_snapshot(DATA_DIR / out_name, envelope)
        except Exception as error:  # noqa: BLE001 — uma fonte falhar não pode derrubar as outras
            log(f"Ranking {gender} falhou, snapshot anterior preservado: {error}", level="warning")


def sync_calendar(config: dict, *, changed: dict[str, bool]) -> None:
    cfg = config["calendar"]
    if not cfg.get("enabled", True):
        log("Calendário: fonte desativada em tennis-sources.yml, pulando.")
        return

    year = datetime.date.today().year
    all_rows: list[dict] = []
    failures: list[str] = []

    for tour, url_template in cfg["urls"].items():
        url = url_template.format(year=year)
        try:
            html = commons.fetch_text(url, timeout=cfg.get("timeout_seconds", 25), retries=cfg.get("retries", 3))
            tables = commons.parse_html_tables(html)
            found_any = False
            for table in tables:
                rows = extract_calendar_rows(table, tour)
                if len(rows) >= 3:  # tabelas de calendário reais têm dezenas de linhas; tabelas pequenas geralmente são outra coisa na página
                    all_rows.extend(rows)
                    found_any = True
            if not found_any:
                raise commons.FetchError(f"Nenhuma tabela de calendário reconhecida em {url}.")
        except Exception as error:  # noqa: BLE001
            failures.append(tour)
            log(f"Calendário {tour} (Wikipédia) falhou: {error}", level="warning")
            try:
                fallback_rows = thesportsdb.collect_calendar_fallback(tour, timeout=15, retries=2)
                all_rows.extend([
                    {**row, "date_text": None} for row in fallback_rows
                ])
                log(f"Calendário {tour}: usando TheSportsDB como fallback ({len(fallback_rows)} evento(s)).")
            except Exception as fallback_error:  # noqa: BLE001
                log(f"Fallback do calendário {tour} também falhou: {fallback_error}", level="warning")

    normalized_rows = []
    for row in all_rows:
        if row.get("date_text"):
            parsed = normalize.parse_wikipedia_date_range(row["date_text"], year)
            if not parsed:
                continue
            row["starts_at"], row["ends_at"] = parsed
        normalized_rows.append({k: v for k, v in row.items() if k != "date_text"})

    valid_rows, discarded = validate.validate_calendar_rows(normalized_rows)
    if not valid_rows:
        log("Calendário: nenhuma linha válida coletada nesta execução; snapshot anterior preservado.", level="warning")
        return
    if not validate.discard_rate_acceptable(len(normalized_rows), discarded):
        log(f"Calendário: taxa de descarte alta ({discarded}/{len(normalized_rows)}); snapshot anterior preservado.", level="warning")
        return

    envelope = normalize.build_envelope(
        source="Wikipédia — temporada ATP/WTA" + (" (com fallback TheSportsDB)" if failures else ""),
        source_url="https://en.wikipedia.org/wiki/" + f"{year}_ATP_Tour",
        data=valid_rows,
    )
    changed["calendar.json"] = maybe_write_snapshot(DATA_DIR / "calendar.json", envelope)


def push_to_wordpress(out_name: str, type_key: str, *, dry_run: bool) -> None:
    wp_url = os.environ.get("WP_URL", "").rstrip("/")
    wp_user = os.environ.get("WP_USER", "")
    wp_app_password = os.environ.get("WP_APP_PASSWORD", "")

    if dry_run or not (wp_url and wp_user and wp_app_password):
        log(f"{out_name}: envio ao WordPress pulado (--dry-run ou secrets ausentes).")
        return

    path = DATA_DIR / out_name
    envelope = json.loads(path.read_text(encoding="utf-8"))
    payload = {**envelope, "type": type_key}
    body = json.dumps(payload).encode("utf-8")

    request = urllib.request.Request(
        f"{wp_url}/wp-json/cartolanews-tennis/v1/sync",
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "Authorization": "Basic " + base64.b64encode(f"{wp_user}:{wp_app_password}".encode()).decode(),
            "User-Agent": commons.USER_AGENT,
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            log(f"{out_name}: enviado ao WordPress (HTTP {response.status}).")
    except urllib.error.HTTPError as error:
        log(f"{out_name}: WordPress recusou o envio (HTTP {error.code}): {error.read().decode(errors='replace')[:300]}", level="error")
    except urllib.error.URLError as error:
        log(f"{out_name}: não foi possível contatar o WordPress: {error}", level="error")


def main() -> int:
    parser = argparse.ArgumentParser(description="Coletor de dados de tênis do CartolaNews.")
    parser.add_argument("--target", choices=["all", "rankings", "calendar"], default="all")
    parser.add_argument("--dry-run", action="store_true", help="Não envia ao WordPress, só gera os snapshots locais.")
    args = parser.parse_args()

    config = load_config()
    changed: dict[str, bool] = {}

    if args.target in ("all", "rankings"):
        sync_rankings(config, changed=changed)
    if args.target in ("all", "calendar"):
        sync_calendar(config, changed=changed)

    type_map = {"rankings-atp.json": "rankings_male", "rankings-wta.json": "rankings_female", "calendar.json": "calendar"}
    for out_name, did_change in changed.items():
        if did_change and out_name in type_map:
            push_to_wordpress(out_name, type_map[out_name], dry_run=args.dry_run)

    return 0


if __name__ == "__main__":
    sys.exit(main())
