"""
Utilidades compartilhadas pelos coletores: HTTP com timeout/retry/backoff
exponencial, identificação de User-Agent e parsing de tabelas HTML sem
dependências pesadas (só biblioteca padrão do Python), seguindo as regras
obrigatorias da secao 20 do briefing:

- apenas conteudo publico
- respeitar robots.txt e termos de uso de cada fonte (verificado manualmente
  antes de configurar a fonte em config/tennis-sources.yml; este script nao
  contorna paywall, CAPTCHA nem protecao anti-bot)
- User-Agent identificado
- timeout, retry, backoff exponencial

Este script roda no GitHub Actions, nunca no servidor do WordPress
(secao 21).
"""
from __future__ import annotations

import json
import time
import urllib.error
import urllib.request
from html.parser import HTMLParser
from typing import Any

USER_AGENT = "CartolaNewsTennisBot/1.0 (+https://cartolanews.com.br/tenis/; coleta publica para o CartolaNews Tennis Hub)"


class FetchError(RuntimeError):
    """Erro definitivo depois de esgotar as tentativas configuradas."""


def fetch_text(url: str, *, timeout: int = 25, retries: int = 3, headers: dict[str, str] | None = None) -> str:
    """GET com backoff exponencial (0.5s, 1s, 2s, ...). Nunca levanta na
    primeira falha — só depois de esgotar `retries` tentativas."""
    last_error: Exception | None = None
    request_headers = {"User-Agent": USER_AGENT, "Accept": "text/html,application/json"}
    request_headers.update(headers or {})

    for attempt in range(1, max(1, retries) + 1):
        try:
            request = urllib.request.Request(url, headers=request_headers)
            with urllib.request.urlopen(request, timeout=timeout) as response:
                if response.status >= 400:
                    raise FetchError(f"HTTP {response.status} em {url}")
                return response.read().decode("utf-8", errors="replace")
        except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, FetchError) as error:
            last_error = error
            if attempt < retries:
                time.sleep(0.5 * (2 ** (attempt - 1)))

    raise FetchError(f"Falha ao buscar {url} após {retries} tentativa(s): {last_error}")


def fetch_json(url: str, *, timeout: int = 25, retries: int = 3) -> Any:
    body = fetch_text(url, timeout=timeout, retries=retries, headers={"Accept": "application/json"})
    return json.loads(body)


class _TableParser(HTMLParser):
    """Parser minimalista de <table>/<tr>/<td|th> -> lista de listas de
    strings, sem dependências externas (equivalente ao usado pelo Vôlei
    Hub para o mesmo tipo de fonte).

    Usa uma PILHA de tabelas em progresso: páginas da Wikipédia costumam
    aninhar tabelas (uma tabela "de layout" envolvendo duas tabelas de
    dados lado a lado). Se tratássemos só a tabela mais externa, linhas de
    tabelas completamente diferentes (ex.: ranking atual e ranking da
    corrida do ano) seriam misturadas na mesma lista — o que já aconteceu
    e foi pego em teste manual antes de publicar este coletor. Cada
    `<table>`, aninhada ou não, vira uma entrada própria em `self.tables`;
    uma `<tr>` sempre pertence à tabela mais interna que estiver aberta.
    """

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.tables: list[list[list[str]]] = []
        self._table_stack: list[list[list[str]]] = []
        self._current_row: list[str] = []
        self._current_cell: list[str] = []
        self._in_cell = False

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag == "table":
            self._table_stack.append([])
        elif tag == "tr" and self._table_stack:
            self._current_row = []
        elif tag in ("td", "th") and self._table_stack:
            self._in_cell = True
            self._current_cell = []
        elif tag == "br" and self._in_cell:
            self._current_cell.append(" ")
        elif tag == "img" and self._in_cell:
            # Bandeiras de país costumam ser <img alt="Italy" ...> sem texto
            # visível — capturamos o alt para não perder o país da linha.
            attrs_dict = dict(attrs)
            alt = attrs_dict.get("alt") or ""
            if alt and not alt.lower().endswith((".png", ".svg", ".jpg")):
                self._current_cell.append(f" [[{alt}]] ")

    def handle_endtag(self, tag: str) -> None:
        if tag == "table":
            if self._table_stack:
                finished = self._table_stack.pop()
                if finished:
                    self.tables.append(finished)
        elif tag == "tr" and self._table_stack:
            if self._current_row:
                self._table_stack[-1].append(self._current_row)
        elif tag in ("td", "th") and self._table_stack:
            self._current_row.append(" ".join("".join(self._current_cell).split()))
            self._in_cell = False

    def handle_data(self, data: str) -> None:
        if self._in_cell:
            self._current_cell.append(data)


def parse_html_tables(html: str) -> list[list[list[str]]]:
    """Retorna todas as tabelas de uma página como listas de linhas de
    texto puro (sem HTML), na ordem em que aparecem no documento."""
    parser = _TableParser()
    parser.feed(html)
    return parser.tables


def find_table_by_header(tables: list[list[list[str]]], required_headers: list[str]) -> list[list[str]] | None:
    """Acha a primeira tabela que tenha, em alguma das suas primeiras
    linhas, uma linha de cabeçalho contendo todos os textos de
    `required_headers` (case-insensitive, substring). Algumas tabelas da
    Wikipédia têm uma linha de legenda antes do cabeçalho real (ex.: linha
    0 = "Singles race rankings as of ..."), por isso verificamos as
    primeiras linhas, não só a linha 0 — e devolvemos a tabela já cortada a
    partir da linha de cabeçalho encontrada, para que o chamador possa
    sempre tratar o índice 0 do retorno como cabeçalho."""
    for table in tables:
        if not table:
            continue
        for row_index, row in enumerate(table[:3]):
            header_join = " | ".join(cell.lower() for cell in row)
            if all(needle.lower() in header_join for needle in required_headers):
                return table[row_index:]
    return None
