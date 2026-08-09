<?php
defined('ABSPATH') || exit;

/**
 * Formatação compartilhada entre frontend e admin: rótulos em português,
 * bandeiras, categorias de torneio e datas relativas. Nenhuma função aqui
 * consulta banco — só formata valores já carregados.
 */
final class CN_Tennis_Helpers {
    public static function surface_label(string $surface): string {
        return match ($surface) {
            'hard' => 'Quadra dura',
            'clay' => 'Saibro',
            'grass' => 'Grama',
            'indoor' => 'Indoor',
            default => '—',
        };
    }

    public static function category_label(string $category): string {
        return match (sanitize_key($category)) {
            'grand_slam' => 'Grand Slam',
            'atp1000', 'masters1000' => 'Masters 1000',
            'wta1000' => 'WTA 1000',
            'atp500' => 'ATP 500',
            'wta500' => 'WTA 500',
            'atp250' => 'ATP 250',
            'wta250' => 'WTA 250',
            'challenger' => 'Challenger',
            'itf' => 'ITF',
            default => $category !== '' ? ucfirst($category) : '—',
        };
    }

    public static function match_status_label(string $status): string {
        return match ($status) {
            'live' => 'AO VIVO',
            'finished' => 'Encerrado',
            'postponed' => 'Adiado',
            'cancelled' => 'Cancelado',
            default => 'Próximo',
        };
    }

    public static function tournament_status_label(string $status): string {
        return match ($status) {
            'ongoing' => 'Em andamento',
            'finished' => 'Encerrado',
            'cancelled' => 'Cancelado',
            default => 'Próximo',
        };
    }

    public static function tour_label(string $tour): string {
        return match ($tour) {
            'wta' => 'WTA',
            'both' => 'ATP / WTA',
            default => 'ATP',
        };
    }

    public static function gender_label(string $gender): string {
        return $gender === 'female' ? 'Feminino' : 'Masculino';
    }

    /**
     * Emoji de bandeira a partir de um código ISO de 2 ou 3 letras. Retorna
     * string vazia (sem bandeira) quando o código não é reconhecido — nunca
     * mostra uma bandeira errada.
     */
    public static function flag(?string $code): string {
        if (!$code) {
            return '';
        }
        $code = strtoupper(trim($code));
        $alpha2_map = self::alpha3_to_alpha2();
        $alpha2 = strlen($code) === 2 ? $code : ($alpha2_map[$code] ?? '');
        if (!preg_match('/^[A-Z]{2}$/', $alpha2)) {
            return '';
        }
        $first = 127397 + ord($alpha2[0]);
        $second = 127397 + ord($alpha2[1]);
        return '&#' . $first . ';&#' . $second . ';';
    }

    private static function alpha3_to_alpha2(): array {
        return [
            'ITA' => 'IT', 'ESP' => 'ES', 'GER' => 'DE', 'SRB' => 'RS', 'RUS' => 'RU', 'USA' => 'US',
            'BRA' => 'BR', 'FRA' => 'FR', 'GBR' => 'GB', 'AUS' => 'AU', 'ARG' => 'AR', 'CAN' => 'CA',
            'SUI' => 'CH', 'GRE' => 'GR', 'NOR' => 'NO', 'POL' => 'PL', 'CZE' => 'CZ', 'CRO' => 'HR',
            'JPN' => 'JP', 'CHN' => 'CN', 'KAZ' => 'KZ', 'BEL' => 'BE', 'NED' => 'NL', 'AUT' => 'AT',
            'DEN' => 'DK', 'SWE' => 'SE', 'BUL' => 'BG', 'UKR' => 'UA', 'CHI' => 'CL', 'COL' => 'CO',
            'MEX' => 'MX', 'POR' => 'PT', 'HUN' => 'HU', 'ROU' => 'RO', 'SLO' => 'SI', 'FIN' => 'FI',
            'IND' => 'IN', 'KOR' => 'KR', 'NZL' => 'NZ', 'RSA' => 'ZA', 'EGY' => 'EG', 'TUR' => 'TR',
            'ISR' => 'IL', 'LAT' => 'LV', 'EST' => 'EE', 'LTU' => 'LT', 'SVK' => 'SK', 'TPE' => 'TW',
        ];
    }

    /** "há 3 minutos" / "há 2 horas" / data completa se for antigo demais. */
    public static function time_ago(string $mysql_datetime_utc): string {
        $timestamp = strtotime($mysql_datetime_utc . ' UTC');
        if (!$timestamp) {
            return '—';
        }
        $diff = time() - $timestamp;
        if ($diff < MINUTE_IN_SECONDS) {
            return 'agora mesmo';
        }
        if ($diff < HOUR_IN_SECONDS) {
            $m = (int) floor($diff / MINUTE_IN_SECONDS);
            return 'há ' . $m . ' minuto' . ($m === 1 ? '' : 's');
        }
        if ($diff < DAY_IN_SECONDS) {
            $h = (int) floor($diff / HOUR_IN_SECONDS);
            return 'há ' . $h . ' hora' . ($h === 1 ? '' : 's');
        }
        return get_date_from_gmt($mysql_datetime_utc, 'd/m/Y H:i');
    }

    public static function initials(string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_filter($parts);
        if (!$parts) {
            return '?';
        }
        if (count($parts) === 1) {
            return strtoupper(mb_substr($parts[0], 0, 2));
        }
        $first = mb_substr(reset($parts), 0, 1);
        $last = mb_substr(end($parts), 0, 1);
        return strtoupper($first . $last);
    }

    public static function calculate_age(?string $birth_date): ?int {
        if (!$birth_date) {
            return null;
        }
        try {
            $birth = new DateTimeImmutable($birth_date);
            return (int) $birth->diff(new DateTimeImmutable('today'))->y;
        } catch (Throwable) {
            return null;
        }
    }
}
