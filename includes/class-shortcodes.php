<?php
defined('ABSPATH') || exit;

/**
 * Todos os shortcodes do plugin, incluindo o principal [cn_tenis_hub]
 * (seção 44). Cada shortcode isolado também funciona sozinho dentro do
 * widget Shortcode do Elementor. Erros de renderização nunca quebram a
 * página: são capturados e, para quem não é administrador, o bloco
 * simplesmente não aparece (para administradores, aparece um aviso).
 */
final class CN_Tennis_Shortcodes {
    public const MAP = [
        'cn_tenis_hub' => 'hub',
        'cn_tenis_ao_vivo' => 'live',
        'cn_tenis_jogos' => 'games',
        'cn_tenis_ranking' => 'ranking',
        'cn_tenis_brasileiros' => 'brazilians',
        'cn_tenis_power_ranking' => 'power_ranking',
        'cn_tenis_calendario' => 'calendar',
        'cn_tenis_lendas' => 'legends',
        'cn_tenis_superficies' => 'surfaces',
    ];

    public function register(): void {
        foreach (array_keys(self::MAP) as $tag) {
            add_shortcode($tag, [$this, 'dispatch']);
        }
    }

    public function dispatch($atts = [], ?string $content = null, string $tag = ''): string {
        $method = self::MAP[$tag] ?? '';
        if (!$method || !method_exists($this, $method)) {
            return '';
        }
        try {
            if (!CN_Tennis_Database::ready()) {
                return current_user_can('manage_options')
                    ? $this->wrap('<p class="cnt-empty">O Tennis Hub está em modo seguro enquanto conclui a instalação do banco. Desative e reative o plugin.</p>')
                    : '';
            }
            $atts = is_array($atts) ? $atts : [];
            return $this->{$method}($atts);
        } catch (Throwable $error) {
            error_log('CN Tennis Hub shortcode ' . $tag . ': ' . $error->getMessage());
            return current_user_can('manage_options')
                ? $this->wrap('<p class="cnt-empty">Este bloco do Tennis Hub está temporariamente indisponível.</p>')
                : '';
        }
    }

    private function wrap(string $content, string $class = ''): string {
        CN_Tennis_Assets::enqueue();
        return '<div class="cnt ' . esc_attr($class) . '">' . $content . '</div>';
    }

    // ------------------------------------------------------------------
    // [cn_tenis_hub] — monta a central completa, na ordem da seção 56.
    // ------------------------------------------------------------------
    public function hub($atts = []): string {
        $a = shortcode_atts([
            'mostrar_hero' => 'sim',
            'mostrar_resumo' => 'sim',
            'mostrar_ranking' => 'sim',
            'mostrar_brasileiros' => 'sim',
            'mostrar_power_ranking' => 'sim',
            'mostrar_lendas' => 'sim',
            'mostrar_ao_vivo' => 'sim',
            'mostrar_calendario' => 'sim',
            'mostrar_superficies' => 'sim',
            'mostrar_grand_slams' => 'sim',
            'mostrar_noticias' => 'sim',
        ], $atts, 'cn_tenis_hub');

        ob_start();
        include CN_TENNIS_PATH . 'public/templates/hub.php';
        return $this->wrap((string) ob_get_clean(), 'cnt--hub');
    }

    // ------------------------------------------------------------------
    // Hero + resumo rápido + filtro de superfície (usados pelo hub.php)
    // ------------------------------------------------------------------
    public function render_hero(): string {
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-hero.php';
        return (string) ob_get_clean();
    }

    public function render_summary(): string {
        $today_games = CN_Tennis_Matches::count_today();
        $ongoing_tournaments = CN_Tennis_Calendar::count_ongoing();
        $number_one_male = CN_Tennis_Rankings::number_one('male');
        $number_one_female = CN_Tennis_Rankings::number_one('female');
        $best_brazilian = CN_Tennis_Rankings::best_brazilian();

        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-summary.php';
        return (string) ob_get_clean();
    }

    public function render_surface_filter(): string {
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-surface-filter.php';
        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------
    // [cn_tenis_ranking sexo="masculino|feminino|ambos" limite="10|20|50|100"]
    // ------------------------------------------------------------------
    public function ranking($atts = []): string {
        $a = shortcode_atts(['sexo' => 'ambos', 'limite' => 20, 'titulo' => 'sim'], $atts, 'cn_tenis_ranking');
        // "limite" define a seleção inicial do combo Top 10/20/50/100; o
        // combo permite trocar sem recarregar (os 100 primeiros já vêm no
        // HTML, ocultos por JS conforme a opção escolhida).
        $default_top = in_array((int) $a['limite'], [10, 20, 50, 100], true) ? (int) $a['limite'] : 20;

        $genders = match (sanitize_key((string) $a['sexo'])) {
            'masculino', 'male' => ['male'],
            'feminino', 'female' => ['female'],
            default => ['male', 'female'],
        };

        ob_start();
        echo '<section class="cnt-rankings" id="cnt-ranking" aria-labelledby="cnt-ranking-title">';
        if ($a['titulo'] !== 'nao') {
            echo '<h2 id="cnt-ranking-title">Ranking Mundial</h2>';
        }
        echo '<div class="cnt-rankings__grid cnt-rankings__grid--' . (count($genders) > 1 ? '2' : '1') . '">';
        foreach ($genders as $gender) {
            $this->render_ranking_table($gender, $default_top);
        }
        echo '</div></section>';
        return $this->wrap((string) ob_get_clean());
    }

    private function render_ranking_table(string $gender, int $default_top): void {
        $rows = CN_Tennis_Rankings::query($gender, 100);
        $last_update = CN_Tennis_Rankings::last_update($gender);
        $title = $gender === 'female' ? 'Ranking Mundial Feminino' : 'Ranking Mundial Masculino';
        include CN_TENNIS_PATH . 'public/templates/partial-ranking-table.php';
    }

    // ------------------------------------------------------------------
    // [cn_tenis_brasileiros]
    // ------------------------------------------------------------------
    public function brazilians($atts = []): string {
        $male = CN_Tennis_Rankings::brazilians('male');
        $female = CN_Tennis_Rankings::brazilians('female');
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-brazilians.php';
        return $this->wrap((string) ob_get_clean());
    }

    // ------------------------------------------------------------------
    // [cn_tenis_power_ranking sexo="masculino|feminino" limite="10"]
    // ------------------------------------------------------------------
    public function power_ranking($atts = []): string {
        $a = shortcode_atts(['sexo' => 'masculino', 'limite' => 10, 'titulo' => 'sim'], $atts, 'cn_tenis_power_ranking');
        $gender = sanitize_key((string) $a['sexo']) === 'feminino' ? 'female' : 'male';
        $rows = CN_Tennis_Power_Ranking::query($gender, (int) $a['limite']);
        $title = 'Melhores do Momento CartolaNews — ' . CN_Tennis_Helpers::gender_label($gender);
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-power-ranking.php';
        return $this->wrap((string) ob_get_clean(), 'cnt--widget');
    }

    // ------------------------------------------------------------------
    // [cn_tenis_lendas]
    // ------------------------------------------------------------------
    public function legends($atts = []): string {
        $legends = CN_Tennis_Legends::all('published');
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-legends.php';
        return $this->wrap((string) ob_get_clean());
    }

    // ------------------------------------------------------------------
    // [cn_tenis_ao_vivo]
    // ------------------------------------------------------------------
    public function live($atts = []): string {
        $today = current_time('mysql', true);
        $candidates = CN_Tennis_Matches::query(['status' => 'live', 'limit' => 30, 'from' => gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)]);
        $live_matches = array_values(array_filter($candidates, [CN_Tennis_Matches::class, 'is_currently_live']));
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-live.php';
        return $this->wrap((string) ob_get_clean(), 'cnt--widget');
    }

    // ------------------------------------------------------------------
    // [cn_tenis_jogos data="hoje" limite="20"]
    // ------------------------------------------------------------------
    public function games($atts = []): string {
        $a = shortcode_atts(['data' => 'hoje', 'limite' => 30, 'titulo' => 'Jogos de Hoje'], $atts, 'cn_tenis_jogos');
        [$from, $to] = $this->date_range((string) $a['data']);
        $rows = CN_Tennis_Matches::query(['from' => $from, 'to' => $to, 'limit' => (int) $a['limite']]);
        $title = sanitize_text_field((string) $a['titulo']);
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-games.php';
        return $this->wrap((string) ob_get_clean(), 'cnt--widget');
    }

    private function date_range(string $value): array {
        $tz = wp_timezone();
        $utc = new DateTimeZone('UTC');
        $today = new DateTimeImmutable('today', $tz);
        $date = match ($value) {
            'ontem' => $today->modify('-1 day'),
            'amanha', 'amanhã' => $today->modify('+1 day'),
            default => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? new DateTimeImmutable($value, $tz) : $today,
        };
        return [
            $date->setTime(0, 0)->setTimezone($utc)->format('Y-m-d H:i:s'),
            $date->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
    }

    // ------------------------------------------------------------------
    // [cn_tenis_calendario]
    // ------------------------------------------------------------------
    public function calendar($atts = []): string {
        $a = shortcode_atts(['limite' => 60, 'titulo' => 'sim'], $atts, 'cn_tenis_calendario');
        $rows = CN_Tennis_Calendar::query(['from' => gmdate('Y-m-d', time() - 7 * DAY_IN_SECONDS), 'limit' => (int) $a['limite']]);
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-calendar.php';
        return $this->wrap((string) ob_get_clean(), 'cnt--widget');
    }

    // ------------------------------------------------------------------
    // [cn_tenis_superficies] — "Especial por Superfície"
    // ------------------------------------------------------------------
    public function surfaces($atts = []): string {
        $surfaces = ['clay' => 'Saibro', 'grass' => 'Grama', 'hard' => 'Quadra Dura', 'indoor' => 'Indoor'];
        $data = [];
        foreach ($surfaces as $key => $label) {
            $data[$key] = ['label' => $label, 'top' => CN_Tennis_Surfaces::leaderboard($key, '', 3)];
        }
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-surfaces.php';
        return $this->wrap((string) ob_get_clean());
    }

    // ------------------------------------------------------------------
    // Grand Slams (renderizado dentro do hub, sem shortcode próprio dedicado)
    // ------------------------------------------------------------------
    public function render_grand_slams(): string {
        $names = [
            'australian_open' => 'Australian Open',
            'roland_garros' => 'Roland Garros',
            'wimbledon' => 'Wimbledon',
            'us_open' => 'US Open',
        ];
        $tournaments = CN_Tennis_Calendar::query(['category' => 'grand_slam', 'limit' => 40, 'from' => gmdate('Y-m-d', time() - 365 * DAY_IN_SECONDS)]);
        $matched = [];
        foreach ($names as $key => $label) {
            $found = null;
            foreach ($tournaments as $tournament) {
                if (str_contains(remove_accents(strtolower($tournament['name'])), str_replace('_', ' ', $key === 'us_open' ? 'us open' : $key))) {
                    $found = $tournament;
                    break;
                }
            }
            $matched[$key] = ['label' => $label, 'tournament' => $found];
        }
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-grand-slams.php';
        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------
    // Notícias (posts nativos do WordPress — seção 55)
    // ------------------------------------------------------------------
    public function render_news(): string {
        $settings = CN_Tennis_Settings::all();
        $category = (string) $settings['news_category'];
        if ($category === '') {
            return '';
        }
        $query = new WP_Query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(24, (int) $settings['news_limit'])),
            'category_name' => $category,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ]);
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-news.php';
        wp_reset_postdata();
        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------
    // Helpers de card reutilizados pelos templates parciais.
    // ------------------------------------------------------------------
    public static function match_card(array $match): string {
        ob_start();
        include CN_TENNIS_PATH . 'public/templates/partial-match-card.php';
        return (string) ob_get_clean();
    }
}
