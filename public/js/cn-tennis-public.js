/**
 * CartolaNews Tennis Hub — JavaScript vanilla do frontend (sem frameworks).
 * Todas as rotinas são no-ops seguros quando os elementos não existem na
 * página (cada shortcode pode ser usado isoladamente).
 */
(() => {
    'use strict';

    const cnt = window.cnTennisData || {};
    const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.addEventListener('DOMContentLoaded', () => {
        initSmoothScroll();
        initGenericTabs();
        initGamesFilter();
        initRankingControls();
        initSurfaceFilter();
        initCalendarFilter();
        initLiveRefresh();
    });

    // ---------------------------------------------------------------
    // Rolagem suave dos atalhos do hero.
    // ---------------------------------------------------------------
    function initSmoothScroll() {
        document.querySelectorAll('.cnt-hero__nav a[href^="#"]').forEach((link) => {
            link.addEventListener('click', (event) => {
                const target = document.querySelector(link.getAttribute('href'));
                if (!target) {
                    return;
                }
                event.preventDefault();
                target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
            });
        });
    }

    // ---------------------------------------------------------------
    // Padrão genérico de abas: [role=tablist] com botões [data-cnt-*-tab]
    // usado por várias seções (mantido para futuras extensões).
    // ---------------------------------------------------------------
    function initGenericTabs() {
        document.querySelectorAll('[role="tablist"]').forEach((group) => {
            const buttons = group.querySelectorAll('button[role="tab"]');
            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    buttons.forEach((b) => b.setAttribute('aria-selected', String(b === button)));
                });
            });
        });
    }

    // ---------------------------------------------------------------
    // [cn_tenis_jogos] — abas Todos/Masculino/Feminino/Duplas.
    // ---------------------------------------------------------------
    function initGamesFilter() {
        document.querySelectorAll('[data-cnt-games-tabs]').forEach((tabs) => {
            const section = tabs.closest('.cnt-games') || document;
            const list = section.querySelector('[data-cnt-games-list]');
            if (!list) {
                return;
            }
            const cards = () => list.querySelectorAll('.cnt-match-card');

            tabs.querySelectorAll('[data-cnt-games-tab]').forEach((button) => {
                button.addEventListener('click', () => {
                    const filter = button.getAttribute('data-cnt-games-tab');
                    cards().forEach((card) => {
                        const gender = card.getAttribute('data-cnt-gender');
                        const type = card.getAttribute('data-cnt-type');
                        let show = true;
                        if (filter === 'male') show = gender === 'male' && type !== 'doubles';
                        else if (filter === 'female') show = gender === 'female' && type !== 'doubles';
                        else if (filter === 'doubles') show = type === 'doubles';
                        card.hidden = !show;
                    });
                });
            });
        });
    }

    // ---------------------------------------------------------------
    // Ranking: seletor Top 10/20/50/100 + botão progressivo "Ver mais".
    // ---------------------------------------------------------------
    function initRankingControls() {
        document.querySelectorAll('[data-cnt-ranking-panel]').forEach((panel) => {
            const rows = () => panel.querySelectorAll('[data-cnt-rank-row]');
            const select = panel.querySelector('[data-cnt-top-select]');
            const toggle = panel.querySelector('[data-cnt-ranking-toggle]');

            const applyLimit = (limit) => {
                const total = rows().length;
                rows().forEach((row) => {
                    row.hidden = parseInt(row.getAttribute('data-index'), 10) >= limit;
                });
                panel.setAttribute('data-cnt-ranking-visible', String(limit));
                if (toggle) {
                    const fullyExpanded = limit >= total;
                    toggle.textContent = fullyExpanded ? 'Ver menos' : 'Ver mais';
                    toggle.setAttribute('aria-expanded', String(fullyExpanded));
                }
            };

            if (select) {
                select.addEventListener('change', () => {
                    applyLimit(parseInt(select.value, 10) || 20);
                });
            }

            if (toggle) {
                toggle.addEventListener('click', () => {
                    const total = rows().length;
                    const current = parseInt(panel.getAttribute('data-cnt-ranking-visible'), 10) || 20;
                    if (current >= total) {
                        const fallback = select ? parseInt(select.value, 10) || 20 : 20;
                        applyLimit(fallback);
                    } else {
                        const next = current < 20 ? 20 : (current < 50 ? 50 : 100);
                        applyLimit(Math.min(next, total));
                    }
                });
            }

            applyLimit(parseInt(panel.getAttribute('data-cnt-ranking-visible'), 10) || 20);
        });
    }

    // ---------------------------------------------------------------
    // Filtro por superfície (seção 6): sem recarregar a página, filtra
    // partidas/torneios já renderizados com data-cnt-surface e realça o
    // card correspondente em "Especial por Superfície".
    // ---------------------------------------------------------------
    function initSurfaceFilter() {
        const filter = document.querySelector('[data-cnt-surface-filter]');
        if (!filter) {
            return;
        }
        const buttons = filter.querySelectorAll('[data-cnt-surface]');
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const surface = button.getAttribute('data-cnt-surface');
                buttons.forEach((b) => b.setAttribute('aria-selected', String(b === button)));

                document.querySelectorAll('[data-cnt-surface]').forEach((el) => {
                    if (el.closest('[data-cnt-surface-filter]')) {
                        return;
                    }
                    const value = el.getAttribute('data-cnt-surface');
                    el.hidden = surface !== 'all' && value !== surface;
                });

                document.querySelectorAll('[data-cnt-surface-card]').forEach((card) => {
                    card.classList.toggle('is-highlighted', surface !== 'all' && card.getAttribute('data-cnt-surface-card') === surface);
                });

                if (surface !== 'all') {
                    const target = document.querySelector('[data-cnt-surface-card="' + surface + '"]');
                    if (target) {
                        target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'nearest' });
                    }
                }
            });
        });
    }

    // ---------------------------------------------------------------
    // [cn_tenis_calendario] — filtros Hoje/Semana/Mês/ATP/WTA/Grand
    // Slam/Challenger/Brasil, tudo client-side sobre as linhas já
    // renderizadas.
    // ---------------------------------------------------------------
    function initCalendarFilter() {
        document.querySelectorAll('[data-cnt-calendar-tabs]').forEach((tabs) => {
            const section = tabs.closest('.cnt-calendar') || document;
            const list = section.querySelector('[data-cnt-calendar-list]');
            const empty = section.querySelector('[data-cnt-calendar-empty]');
            if (!list) {
                return;
            }
            const rows = () => list.querySelectorAll('[data-cnt-cal-row]');

            tabs.querySelectorAll('[data-cnt-cal-filter]').forEach((button) => {
                button.addEventListener('click', () => {
                    tabs.querySelectorAll('[data-cnt-cal-filter]').forEach((b) => b.setAttribute('aria-selected', String(b === button)));
                    const filter = button.getAttribute('data-cnt-cal-filter');
                    let visibleCount = 0;
                    rows().forEach((row) => {
                        let show = true;
                        if (filter === 'today') show = row.getAttribute('data-today') === '1';
                        else if (filter === 'week') show = row.getAttribute('data-week') === '1';
                        else if (filter === 'month') show = row.getAttribute('data-month') === '1';
                        else if (filter === 'atp') show = row.getAttribute('data-tour') === 'atp' || row.getAttribute('data-tour') === 'both';
                        else if (filter === 'wta') show = row.getAttribute('data-tour') === 'wta' || row.getAttribute('data-tour') === 'both';
                        else if (filter === 'grand_slam') show = row.getAttribute('data-category') === 'grand_slam';
                        else if (filter === 'challenger') show = row.getAttribute('data-category') === 'challenger';
                        else if (filter === 'brasil') show = row.getAttribute('data-brasil') === '1';
                        row.hidden = !show;
                        if (show) visibleCount++;
                    });
                    if (empty) {
                        empty.hidden = visibleCount > 0;
                    }
                });
            });
        });
    }

    // ---------------------------------------------------------------
    // [cn_tenis_ao_vivo] — atualiza periodicamente via REST (fragmento
    // HTML já renderizado no servidor), para que o selo AO VIVO nunca
    // fique desatualizado mesmo com a página em cache de página inteira.
    // ---------------------------------------------------------------
    function initLiveRefresh() {
        const section = document.querySelector('[data-cnt-live-refresh]');
        if (!section || !cnt.restUrl) {
            return;
        }
        const REFRESH_MS = 45000;

        const refresh = () => {
            const url = cnt.restUrl + '/live-html?_cnt=' + Date.now();
            fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'Cache-Control': 'no-cache' } })
                .then((response) => (response.ok ? response.json() : null))
                .then((data) => {
                    if (!data || !data.html) {
                        return;
                    }
                    const wrapper = document.querySelector('[data-cnt-live-refresh]');
                    if (wrapper) {
                        const temp = document.createElement('div');
                        temp.innerHTML = data.html;
                        const fresh = temp.querySelector('[data-cnt-live-refresh]');
                        if (fresh) {
                            wrapper.replaceWith(fresh);
                        }
                    }
                })
                .catch(() => {
                    /* Falha de rede: mantém o último conteúdo válido exibido. */
                });
        };

        window.setInterval(refresh, REFRESH_MS);
    }
})();
