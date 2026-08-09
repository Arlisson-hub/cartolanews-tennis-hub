<?php
defined('ABSPATH') || exit;

/**
 * Camada de banco de dados. Cria e versiona as tabelas custom do plugin via
 * dbDelta(), sempre usando o charset/collation do WordPress.
 *
 * Tabelas obrigatórias (spec): players, rankings, tournaments, matches,
 * legends, sources, sync_logs. Duas tabelas adicionais foram incluídas por
 * serem pré-requisito direto de outras seções do spec sem inventar escopo
 * novo: power_rankings (nota "Melhores do Momento", seção 9) e
 * ranking_snapshots (histórico, pré-requisito da evolução de ranking listada
 * como expansão futura permitida na seção 57).
 */
final class CN_Tennis_Database {
    private static ?bool $ready = null;

    public static function tables(): array {
        global $wpdb;
        $names = [
            'players', 'rankings', 'tournaments', 'matches', 'legends',
            'sources', 'sync_logs', 'power_rankings', 'ranking_snapshots',
        ];
        return array_combine($names, array_map(
            static fn($name) => $wpdb->prefix . 'cn_tennis_' . $name,
            $names
        ));
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = self::tables();
        $c = $wpdb->get_charset_collate();
        $sql = [];

        $sql[] = "CREATE TABLE {$t['players']} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            external_id varchar(100) NULL,
            provider varchar(40) NOT NULL DEFAULT 'manual',
            slug varchar(190) NOT NULL,
            name varchar(190) NOT NULL,
            full_name varchar(190) NULL,
            gender varchar(10) NOT NULL DEFAULT 'male',
            country_code char(3) NULL,
            country varchar(100) NULL,
            birth_date date NULL,
            height_cm smallint unsigned NULL,
            plays varchar(20) NULL,
            turned_pro smallint unsigned NULL,
            current_rank_singles smallint unsigned NULL,
            current_rank_points int unsigned NULL,
            best_rank_singles smallint unsigned NULL,
            best_rank_date date NULL,
            titles_count smallint unsigned NULL,
            is_brazilian tinyint NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            photo_attachment_id bigint unsigned NULL,
            photo_credit_author varchar(190) NULL,
            photo_credit_license varchar(100) NULL,
            photo_credit_license_url text NULL,
            photo_credit_source_url text NULL,
            photo_imported_at datetime NULL,
            photo_focal_x tinyint unsigned NOT NULL DEFAULT 50,
            photo_focal_y tinyint unsigned NOT NULL DEFAULT 50,
            manual_override longtext NULL,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            UNIQUE KEY provider_external (provider, external_id),
            KEY gender_rank (gender, current_rank_singles),
            KEY country_code (country_code),
            KEY is_brazilian (is_brazilian, gender)
        ) $c;";

        $sql[] = "CREATE TABLE {$t['rankings']} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            player_id bigint unsigned NOT NULL,
            gender varchar(10) NOT NULL,
            ranking_type varchar(20) NOT NULL DEFAULT 'singles',
            rank_position smallint unsigned NOT NULL,
            previous_rank smallint unsigned NULL,
            points int unsigned NOT NULL DEFAULT 0,
            tournaments_played smallint unsigned NULL,
            ranking_date date NOT NULL,
            source varchar(190) NOT NULL,
            source_url text NULL,
            manual_override tinyint NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY player_type (gender, ranking_type, player_id),
            KEY gender_position (gender, ranking_type, rank_position),
            KEY ranking_date (ranking_date)
        ) $c;";

        $sql[] = "CREATE TABLE {$t['tournaments']} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            external_id varchar(100) NULL,
            provider varchar(40) NOT NULL DEFAULT 'manual',
            name varchar(190) NOT NULL,
            slug varchar(190) NOT NULL,
            city varchar(120) NULL,
            country varchar(100) NULL,
            country_code char(3) NULL,
            tour varchar(10) NOT NULL DEFAULT 'atp',
            category varchar(40) NULL,
            surface varchar(20) NULL,
            starts_at date NULL,
            ends_at date NULL,
            prize_money bigint unsigned NULL,
            prize_currency varchar(10) NULL,
            defending_champion_id bigint unsigned NULL,
            draw_size smallint unsigned NULL,
            status varchar(20) NOT NULL DEFAULT 'upcoming',
            manual_override tinyint NOT NULL DEFAULT 0,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            UNIQUE KEY provider_external (provider, external_id),
            KEY starts_status (starts_at, status),
            KEY tour_category (tour, category)
        ) $c;";

        $sql[] = "CREATE TABLE {$t['matches']} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            external_id varchar(100) NULL,
            provider varchar(40) NOT NULL DEFAULT 'manual',
            tournament_id bigint unsigned NULL,
            round_name varchar(60) NULL,
            gender varchar(10) NOT NULL DEFAULT 'male',
            match_type varchar(10) NOT NULL DEFAULT 'singles',
            surface varchar(20) NULL,
            player1_id bigint unsigned NULL,
            player2_id bigint unsigned NULL,
            player1_name varchar(190) NULL,
            player2_name varchar(190) NULL,
            player1_country char(3) NULL,
            player2_country char(3) NULL,
            player1_rank smallint unsigned NULL,
            player2_rank smallint unsigned NULL,
            scheduled_at datetime NULL,
            status varchar(20) NOT NULL DEFAULT 'scheduled',
            winner tinyint NULL,
            score_json longtext NULL,
            duration_minutes smallint unsigned NULL,
            is_live_confirmed tinyint NOT NULL DEFAULT 0,
            source_updated_at datetime NULL,
            metadata longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY provider_external (provider, external_id),
            KEY scheduled_status (scheduled_at, status),
            KEY tournament_date (tournament_id, scheduled_at),
            KEY player1_date (player1_id, scheduled_at),
            KEY player2_date (player2_id, scheduled_at),
            KEY gender_status (gender, status)
        ) $c;";

        $sql[] = "CREATE TABLE {$t['legends']} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(190) NOT NULL,
            name varchar(190) NOT NULL,
            country_code char(3) NULL,
            country varchar(100) NULL,
            birth_date date NULL,
            death_date date NULL,
            pro_period_start smallint unsigned NULL,
            pro_period_end smallint unsigned NULL,
            grand_slams_count smallint unsigned NULL,
            titles_count smallint unsigned NULL,
            best_rank smallint unsigned NULL,
            best_rank_date date NULL,
            best_surface varchar(20) NULL,
            description text NULL,
            photo_attachment_id bigint unsigned NULL,
            photo_credit_author varchar(190) NULL,
            photo_credit_license varchar(100) NULL,
            photo_credit_license_url text NULL,
            photo_credit_source_url text NULL,
            photo_imported_at datetime NULL,
            source varchar(190) NULL,
            source_url text NULL,
            status varchar(20) NOT NULL DEFAULT 'published',
            sort_order smallint NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status_order (status, sort_order)
        ) $c;";

        $sql[] = "CREATE TABLE {$t['sources']} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            source_key varchar(60) NOT NULL,
            label varchar(190) NOT NULL,
            provider varchar(40) NOT NULL,
            fallback_provider varchar(40) NULL,
            url text NULL,
            frequency_minutes int unsigned NOT NULL DEFAULT 1440,
            timeout_seconds smallint unsigned NOT NULL DEFAULT 20,
            retries tinyint unsigned NOT NULL DEFAULT 2,
            priority tinyint NOT NULL DEFAULT 0,
            enabled tinyint NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'unknown',
            last_success_at datetime NULL,
            last_error_at datetime NULL,
            last_error_message text NULL,
            last_duration_ms int unsigned NULL,
            last_records int unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_key (source_key)
        ) $c;";

        $sql[] = "CREATE TABLE {$t['sync_logs']} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            task varchar(80) NOT NULL,
            provider varchar(40) NULL,
            endpoint varchar(190) NULL,
            status varchar(20) NOT NULL,
            http_code smallint NULL,
            duration_ms int unsigned NULL,
            received int unsigned NOT NULL DEFAULT 0,
            created_count int unsigned NOT NULL DEFAULT 0,
            updated_count int unsigned NOT NULL DEFAULT 0,
            message text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY task_date (task, created_at),
            KEY status_date (status, created_at)
        ) $c;";

        $sql[] = "CREATE TABLE {$t['power_rankings']} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            player_id bigint unsigned NOT NULL,
            gender varchar(10) NOT NULL,
            rank_position smallint unsigned NULL,
            previous_rank smallint unsigned NULL,
            score decimal(5,2) NULL,
            sample_matches int unsigned NOT NULL DEFAULT 0,
            insufficient_data tinyint NOT NULL DEFAULT 0,
            explanation text NULL,
            calculated_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY gender_player (gender, player_id),
            KEY gender_position (gender, rank_position),
            KEY calculated_at (calculated_at)
        ) $c;";

        $sql[] = "CREATE TABLE {$t['ranking_snapshots']} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            snapshot_type varchar(20) NOT NULL,
            gender varchar(10) NOT NULL,
            player_id bigint unsigned NOT NULL,
            rank_position smallint unsigned NOT NULL,
            score decimal(10,2) NULL,
            captured_at date NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY history (snapshot_type, gender, player_id, captured_at),
            KEY player_history (player_id, snapshot_type, captured_at)
        ) $c;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option('cn_tennis_db_version', CN_TENNIS_DB_VERSION, false);
        self::$ready = true;
    }

    public static function ready(): bool {
        if (self::$ready !== null) {
            return self::$ready;
        }
        global $wpdb;
        $table = self::tables()['players'];
        self::$ready = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        return self::$ready;
    }
}
