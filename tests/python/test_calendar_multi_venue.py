"""
Regressão da eliminatória agregada (2026-10-03).

A Wikipédia escreve a eliminatória da Davis Cup numa única célula, com a
competição e depois uma sede por `<br>`:

    <a>Davis Cup Qualifiers first round</a><br>
    <a>Düsseldorf</a>, Germany – hard (i)<br>
    <a>Quito</a>, Ecuador – clay<br>  ... 13 sedes ...

O parser antigo achatava tudo num `name` de 387 caracteres. A coluna do
WordPress é `varchar(190)` e o `$wpdb->insert()` falhava em silêncio: o
confronto nunca entrou no banco.
"""
from tennis.parsers import wikipedia_tables
from tennis.parsers.wikipedia_tables import extract_calendar_rows
from tennis.providers import commons
from tennis import validate

BR = wikipedia_tables.BR_MARKER


def _tabela(celula: str) -> list[list[str]]:
    return [
        ["Week", "Tournament", "Champions", "Runners-up"],
        ["2 Feb", celula, "x", "y"],
        ["9 Feb", "Dallas Open Dallas, United States ATP 500 Hard (i)", "x", "y"],
        ["16 Feb", "Rio Open Rio de Janeiro, Brazil ATP 500 Clay", "x", "y"],
    ]


CELULA_DAVIS = BR.join([
    "Davis Cup Qualifiers first round",
    "Düsseldorf, Germany – hard (i)",
    "Quito, Ecuador – clay",
    "Plovdiv, Bulgaria – clay",
])


def test_celula_multi_sede_vira_uma_linha_por_sede():
    linhas = extract_calendar_rows(_tabela(CELULA_DAVIS), "atp")
    davis = [linha for linha in linhas if "Davis Cup" in linha["name"]]
    assert len(davis) == 3
    assert [linha["city"] for linha in davis] == ["Düsseldorf", "Quito", "Plovdiv"]
    assert [linha["country"] for linha in davis] == ["Germany", "Ecuador", "Bulgaria"]
    # (i) vira "indoor" pela convenção que já existia no projeto
    assert [linha["surface"] for linha in davis] == ["indoor", "clay", "clay"]


def test_nome_de_cada_sede_cabe_na_coluna():
    linhas = extract_calendar_rows(_tabela(CELULA_DAVIS), "atp")
    for linha in linhas:
        assert len(linha["name"]) <= validate.NAME_MAX


def test_external_id_derivado_cabe_e_e_unico():
    linhas = extract_calendar_rows(_tabela(CELULA_DAVIS), "atp")
    ids = [validate.derived_external_id(linha["name"], "2026-02-02") for linha in linhas]
    assert all(len(i) <= validate.EXTERNAL_ID_MAX for i in ids)
    assert len(ids) == len(set(ids))


def test_celula_normal_continua_igual():
    """Uma linha de torneio comum não pode mudar de comportamento."""
    linhas = extract_calendar_rows(_tabela(CELULA_DAVIS), "atp")
    comuns = [linha for linha in linhas if "Davis Cup" not in linha["name"]]
    assert [linha["name"] for linha in comuns] == ["Dallas Open Dallas, United States ATP", "Rio Open Rio de Janeiro, Brazil ATP"]
    assert all(linha["city"] == "" for linha in comuns)


def test_celula_fora_do_padrao_nao_e_separada():
    """Se a fonte mudar de forma, mantém o comportamento antigo em vez de
    arriscar um corte errado."""
    celula = BR.join(["Alguma Competição", "linha que não é sede", "outra coisa"])
    linhas = extract_calendar_rows(_tabela(celula), "atp")
    alvo = [linha for linha in linhas if "Alguma Competi" in linha["name"]]
    assert len(alvo) == 1
    assert BR not in alvo[0]["name"]


def test_marcador_de_br_nao_e_espaco_para_o_python():
    """`str.split()` descarta \x1c a \x1f; o marcador tem de sobreviver à
    normalização de cada célula."""
    assert not BR.isspace()
    html = "<table><tr><td>A<br/>B</td></tr></table>"
    assert commons.parse_html_tables(html, br_marker=BR) == [[[f"A{BR}B"]]]
    assert commons.parse_html_tables(html) == [[["A B"]]]


def test_linha_grande_demais_e_descartada_com_motivo():
    rows = [
        {"name": "Torneio Normal", "starts_at": "2026-02-02", "ends_at": None},
        {"name": "D" * 400, "starts_at": "2026-02-02", "ends_at": None},
    ]
    valid, discarded, oversized = validate.validate_calendar_rows(rows)
    assert [r["name"] for r in valid] == ["Torneio Normal"]
    assert discarded == 1
    assert len(oversized) == 1
    assert "name com 400 caracteres" in oversized[0]
