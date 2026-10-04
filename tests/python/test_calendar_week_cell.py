"""
Regressão da célula de semana com duas datas (2026-10-04).

Causa real, medida na fonte: a coluna *Week* traz UMA data por semana que o
torneio ocupa. Torneio de duas semanas (todo Grand Slam e vários 1000) traz
DUAS, separadas por `<br>`:

    <td>Jan 19<br>Jan 26</td><td>Australian Open<br>Melbourne, Australia<br>
    Grand Slam<br>Hard – $43,608,375 – 128S/128Q/64D/32X</td>

`_WEEK_DATE` era ancorado em `$` e só reconhecia uma data. A linha caía no ramo
de `rowspan`, a célula de DATAS era tomada como nome do torneio e o torneio real
(célula seguinte) era perdido em silêncio. Dois sintomas, uma causa:

1. a célula `Jan 19 Jan 26` era cortada no primeiro dígito e virava o "torneio"
   de nome `Jan` — o mesmo para Apr/May/Jun/Sep no calendário WTA (que escreve
   mês antes do dia); no ATP (dia antes do mês) o corte caía no caractere 0, o
   nome saía vazio e a linha era descartada sem deixar rastro;
2. `current_week` ficava `None` até a próxima semana de data única, então TODAS
   as linhas do bloco eram descartadas junto — por isso faltavam no feed também
   Brisbane, Auckland, Charleston, Genebra, Hamburgo e Winston-Salem.

A regra nova é estrutural, não lista de meses: a célula é de semana quando
TODOS os seus segmentos são datas. Nome de torneio nunca é só tokens de data.
"""
from tennis import normalize, validate
from tennis.parsers import wikipedia_tables
from tennis.parsers.wikipedia_tables import extract_calendar_rows
from tennis.providers import commons

BR = wikipedia_tables.BR_MARKER

MESES = ("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec")

# Recorte fiel de en.wikipedia.org/wiki/2026_WTA_Tour (ordem mês-dia).
TABELA_WTA = [
    ["Week", "Tournament", "Champions", "Runners-up", "Semifinalists", "Quarterfinalists"],
    [
        f"Dec 29{BR}Jan 5",
        f"United Cup{BR}Perth/Sydney, Australia{BR}United Cup{BR}Hard – $5,903,345 – 18 teams",
        "x", "y", "z", "w",
    ],
    [
        "Brisbane International Brisbane, Australia WTA 500 Hard – $1,064,510 – 32S/16Q/16D",
        "a", "b", "c", "d",
    ],
    ["Jan 12", "Hobart International Hobart, Australia WTA 250 Hard – $275,094", "x", "y", "z", "w"],
    [
        f"Jan 19{BR}Jan 26",
        f"Australian Open{BR}Melbourne, Australia{BR}Grand Slam{BR}Hard – $43,608,375 – 128S/128Q/64D/32X",
        "x", "y", "z", "w",
    ],
    [
        f"Apr 20{BR}Apr 27",
        f"Madrid Open{BR}Madrid, Spain{BR}WTA 1000{BR}Clay – €8,235,540 – 96S/48Q/32D",
        "x", "y", "z", "w",
    ],
    [
        f"May 25{BR}Jun 1",
        f"French Open{BR}Paris, France{BR}Grand Slam{BR}Clay – €29,137,500 – 128S/64D/32X",
        "x", "y", "z", "w",
    ],
    [
        f"Jun 29{BR}Jul 6",
        f"Wimbledon{BR}London, United Kingdom{BR}Grand Slam{BR}Grass – €64,200,000 – 128S/64D/32X",
        "x", "y", "z", "w",
    ],
    [
        f"Sep 28{BR}Oct 5",
        f"China Open{BR}Beijing, China{BR}WTA 1000{BR}Hard – $9,415,725 – 96S/48Q/32D",
        "x", "y", "z", "w",
    ],
]

# Recorte do ATP (ordem dia-mês) e com prefixo de moeda "A$".
TABELA_ATP = [
    ["Week", "Tournament", "Champions", "Runners-up", "Semifinalists", "Quarterfinalists"],
    [
        f"19 Jan{BR}26 Jan",
        f"Australian Open{BR}Melbourne, Australia{BR}Grand Slam{BR}Hard – A$49,171,000 – 128S/128Q/64D/32X",
        "x", "y", "z", "w",
    ],
    ["2 Feb", "Dallas Open Dallas, United States ATP 500 Hard (i) – $2,195,015", "x", "y", "z", "w"],
    [
        f"5 Oct{BR}12 Oct",
        f"Shanghai Masters{BR}Shanghai, China{BR}ATP 1000{BR}Hard – $9,415,725 – 96S/48Q/32D",
        "x", "y", "z", "w",
    ],
]


def _nomes(tabela, tour="wta"):
    return [linha["name"] for linha in extract_calendar_rows(tabela, tour)]


def _por_nome(tabela, tour="wta"):
    return {linha["name"]: linha for linha in extract_calendar_rows(tabela, tour)}


# ---------------------------------------------------------------- 1 a 5
def test_jan_nao_vira_torneio():
    assert "Jan" not in _nomes(TABELA_WTA)


def test_apr_nao_vira_torneio():
    assert "Apr" not in _nomes(TABELA_WTA)


def test_may_nao_vira_torneio():
    assert "May" not in _nomes(TABELA_WTA)


def test_jun_nao_vira_torneio():
    assert "Jun" not in _nomes(TABELA_WTA)


def test_sep_nao_vira_torneio():
    assert "Sep" not in _nomes(TABELA_WTA)


def test_nenhum_nome_e_so_um_mes_abreviado():
    for nome in _nomes(TABELA_WTA) + _nomes(TABELA_ATP, "atp"):
        assert nome.strip() not in MESES, nome


# ------------------------------------------------------------------- 6
def test_torneio_real_com_mes_no_nome_permanece():
    """Nome que CONTÉM mês não pode ser confundido com célula de semana."""
    tabela = [
        ["Week", "Tournament", "Champions", "Runners-up"],
        ["2 Feb", "May Festival Open Mayfair, United Kingdom ATP 250 Hard", "x", "y"],
        ["9 Feb", "Jan Kodeš Memorial Prague, Czech Republic ATP 250 Clay", "x", "y"],
        ["16 Feb", "Rio Open Rio de Janeiro, Brazil ATP 500 Clay", "x", "y"],
    ]
    nomes = _nomes(tabela, "atp")
    assert "May Festival Open Mayfair, United Kingdom ATP" in nomes
    assert "Jan Kodeš Memorial Prague, Czech Republic ATP" in nomes


def test_celula_mista_data_mais_texto_nao_e_semana():
    """Se sobra texto que não é data, a célula não é de semana."""
    assert wikipedia_tables._week_cell_dates(f"Jan 19{BR}Australian Open") is None
    assert wikipedia_tables._week_cell_dates("Jan 19 Australian Open") is None
    assert wikipedia_tables._week_cell_dates("") is None
    assert wikipedia_tables._week_cell_dates(f"Jan 19{BR}Jan 26") == ["Jan 19", "Jan 26"]
    assert wikipedia_tables._week_cell_dates("2 Feb") == ["2 Feb"]


# ------------------------------------------------------------------- 7
def test_estrutura_wta_valida_continua_parseada():
    linhas = _por_nome(TABELA_WTA)
    # o torneio de semana única segue igual
    assert "Hobart International Hobart, Australia WTA" in linhas
    assert linhas["Hobart International Hobart, Australia WTA"]["date_text"] == "Jan 12"
    # e os de duas semanas, que antes eram perdidos, agora aparecem
    assert "Australian Open Melbourne, Australia Grand Slam Hard" in linhas
    assert linhas["Australian Open Melbourne, Australia Grand Slam Hard"]["date_text"] == "Jan 19"
    assert "Madrid Open Madrid, Spain WTA" in linhas
    assert "Wimbledon London, United Kingdom Grand Slam Grass" in linhas
    assert "China Open Beijing, China WTA" in linhas


def test_bloco_apos_celula_de_duas_datas_nao_e_mais_descartado():
    """`current_week` ficava None e levava o bloco inteiro junto."""
    linhas = _por_nome(TABELA_WTA)
    assert "United Cup Perth/Sydney, Australia United Cup Hard" in linhas
    assert "Brisbane International Brisbane, Australia WTA" in linhas


def test_virada_do_ano_usa_a_semana_da_temporada():
    """"Dec 29" + "Jan 5" numa página de 2026: a semana válida é Jan 5."""
    linhas = _por_nome(TABELA_WTA)
    assert linhas["United Cup Perth/Sydney, Australia United Cup Hard"]["date_text"] == "Jan 5"
    assert wikipedia_tables._season_week(["Dec 29", "Jan 5"]) == "Jan 5"
    assert wikipedia_tables._season_week(["Jan 19", "Jan 26"]) == "Jan 19"
    assert wikipedia_tables._season_week(["Aug 31", "Sep 7"]) == "Aug 31"
    assert wikipedia_tables._season_week(["29 Dec", "5 Jan"]) == "5 Jan"


def test_atp_dia_antes_do_mes_tambem_funciona():
    linhas = _por_nome(TABELA_ATP, "atp")
    assert "Australian Open Melbourne, Australia Grand Slam Hard" in linhas
    assert linhas["Australian Open Melbourne, Australia Grand Slam Hard"]["date_text"] == "19 Jan"
    assert "Shanghai Masters Shanghai, China ATP" in linhas
    assert "Dallas Open Dallas, United States ATP" in linhas


def test_prefixo_de_moeda_nao_deixa_letra_solta_no_nome():
    """"– A$49,171,000" cortava em "$" e o nome terminava num "A" órfão."""
    for nome in _nomes(TABELA_ATP, "atp"):
        assert not nome.endswith(" A"), nome
        assert not nome.endswith("A$"), nome


# ------------------------------------------------------------------- 8
def test_davis_cup_continua_intacta():
    celula = BR.join([
        "Davis Cup Qualifiers first round",
        "Düsseldorf, Germany – hard (i)",
        "Quito, Ecuador – clay",
        "Plovdiv, Bulgaria – clay",
    ])
    tabela = [
        ["Week", "Tournament", "Champions", "Runners-up"],
        ["2 Feb", celula, "x", "y"],
        ["9 Feb", "Dallas Open Dallas, United States ATP 500 Hard (i)", "x", "y"],
        ["16 Feb", "Rio Open Rio de Janeiro, Brazil ATP 500 Clay", "x", "y"],
    ]
    linhas = extract_calendar_rows(tabela, "atp")
    sedes = [linha for linha in linhas if linha["name"].startswith("Davis Cup Qualifiers first round")]
    assert len(sedes) == 3
    assert {linha["city"] for linha in sedes} == {"Düsseldorf", "Quito", "Plovdiv"}
    assert {linha["surface"] for linha in sedes} == {"indoor", "clay"}


def test_davis_cup_com_celula_de_semana_de_duas_datas():
    """A eliminatória também pode cair numa semana de duas datas."""
    celula = BR.join([
        "Davis Cup Qualifiers first round",
        "Tokyo, Japan – hard (i)",
        "Oslo, Norway – hard (i)",
    ])
    tabela = [
        ["Week", "Tournament", "Champions", "Runners-up"],
        [f"Feb 2{BR}Feb 9", celula, "x", "y"],
        ["Feb 16", "Rio Open Rio de Janeiro, Brazil ATP 500 Clay", "x", "y"],
        ["Feb 23", "Chile Open Santiago, Chile ATP 250 Clay", "x", "y"],
    ]
    sedes = [l for l in extract_calendar_rows(tabela, "atp") if l["name"].startswith("Davis Cup")]
    assert len(sedes) == 2
    assert all(l["date_text"] == "Feb 2" for l in sedes)


# ------------------------------------------------------------------- 9
def test_rankings_que_usam_parse_html_tables_continuam_intactos():
    html = """
    <table class="wikitable">
      <tr><th>Rank</th><th>Player</th><th>Points</th></tr>
      <tr><td>1</td><td>Carlos Alcaraz</td><td>11,540</td></tr>
      <tr><td>2</td><td>Jannik Sinner</td><td>10,780</td></tr>
      <tr><td>3</td><td>Alexander Zverev</td><td>7,215</td></tr>
    </table>
    """
    tabelas = commons.parse_html_tables(html)
    assert len(tabelas) == 1
    assert tabelas[0][0] == ["Rank", "Player", "Points"]
    assert tabelas[0][1] == ["1", "Carlos Alcaraz", "11,540"]
    # e a tabela de ranking não é confundida com calendário
    assert extract_calendar_rows(tabelas[0], "atp") == []


# ------------------------------------------------------------------ 10
def test_marcador_br_continua_funcionando():
    html = "<table><tr><th>Week</th></tr><tr><td>Jan 19<br>Jan 26</td></tr></table>"
    sem_marcador = commons.parse_html_tables(html)
    com_marcador = commons.parse_html_tables(html, br_marker=BR)
    assert sem_marcador[0][1] == ["Jan 19 Jan 26"], "padrão histórico preservado"
    assert com_marcador[0][1] == [f"Jan 19{BR}Jan 26"]
    assert BR not in " ".join(sem_marcador[0][1])
    # o marcador escolhido não pode ser espaço em branco para o Python
    assert not BR.isspace()


# ------------------------------- fusão entre tours e external_id repetido
def test_evento_das_duas_paginas_vira_uma_linha_both():
    linhas = [
        {"name": "Australian Open Melbourne, Australia Grand Slam Hard", "tour": "atp",
         "starts_at": "2026-01-19", "surface": "hard"},
        {"name": "Australian Open Melbourne, Australia Grand Slam Hard", "tour": "wta",
         "starts_at": "2026-01-19", "surface": "hard"},
        {"name": "Dallas Open Dallas, United States ATP", "tour": "atp",
         "starts_at": "2026-02-09", "surface": "hard"},
    ]
    fundido = normalize.merge_cross_tour_events(linhas)
    assert len(fundido) == 2
    assert fundido[0]["tour"] == "both"
    assert fundido[1]["tour"] == "atp"


def test_fusao_nao_acontece_se_a_superficie_divergir():
    linhas = [
        {"name": "X Open", "tour": "atp", "starts_at": "2026-01-19", "surface": "hard"},
        {"name": "X Open", "tour": "wta", "starts_at": "2026-01-19", "surface": "clay"},
    ]
    assert len(normalize.merge_cross_tour_events(linhas)) == 2


def test_fusao_nao_acontece_entre_datas_diferentes():
    linhas = [
        {"name": "X Open", "tour": "atp", "starts_at": "2026-01-19", "surface": "hard"},
        {"name": "X Open", "tour": "wta", "starts_at": "2026-02-19", "surface": "hard"},
    ]
    assert len(normalize.merge_cross_tour_events(linhas)) == 2


def test_external_id_repetido_e_descartado_com_motivo():
    """A tabela tem UNIQUE KEY (provider, external_id): o 2o INSERT falharia."""
    linhas = [
        {"name": "Wimbledon London, United Kingdom Grand Slam Grass", "tour": "atp",
         "starts_at": "2026-06-29", "surface": "grass"},
        {"name": "Wimbledon London, United Kingdom Grand Slam Grass", "tour": "wta",
         "starts_at": "2026-06-29", "surface": "grass"},
    ]
    validas, descartadas, motivos = validate.validate_calendar_rows(linhas)
    assert len(validas) == 1
    assert descartadas == 1
    assert any("external_id repetido" in motivo for motivo in motivos)
