"""Ranking ATP masculino a partir do relatório numérico oficial semanal.

O HTML de atptour.com continua protegido por Cloudflare e não é contornado.
A própria ATP oferece um PDF numérico público hospedado no ProTennisLive.
"""
from __future__ import annotations

import io

from pypdf import PdfReader

from . import commons
from ..parsers.atp_report import extract_atp_report_rows

SOURCE_URL = "https://www.protennislive.com/posting/ramr/singles_entry_numerical.pdf"
SOURCE_NAME = "ATP Tour — PIF ATP Rankings, relatório numérico oficial"


def collect(*, timeout: int = 25, retries: int = 3, min_rows: int = 50) -> list[dict]:
    content = commons.fetch_bytes(
        SOURCE_URL,
        timeout=timeout,
        retries=retries,
        headers={"Accept": "application/pdf"},
    )
    try:
        reader = PdfReader(io.BytesIO(content))
        text = "\n".join((page.extract_text() or "") for page in reader.pages[:3])
    except Exception as error:
        raise commons.FetchError(f"PDF oficial da ATP inválido ou ilegível: {error}") from error

    rows = extract_atp_report_rows(text, limit=100)
    if len(rows) < min_rows:
        raise commons.FetchError(
            f"Apenas {len(rows)} linha(s) extraída(s) do relatório oficial da ATP; mínimo exigido é {min_rows}."
        )
    return rows
