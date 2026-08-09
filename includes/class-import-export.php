<?php
defined('ABSPATH') || exit;

/**
 * Importação/exportação manual (seção 53). Toda importação valida e mostra
 * contagem de sucesso/erro antes — nunca insere registros claramente
 * inválidos (sem nome, datas quebradas etc.), mas não bloqueia o restante
 * do lote por causa de uma linha ruim.
 */
final class CN_Tennis_Import_Export {
    public function export_players(): string {
        return $this->encode(CN_Tennis_Players::query(['limit' => 500]));
    }

    public function export_legends(): string {
        return $this->encode(CN_Tennis_Legends::all(''));
    }

    public function export_tournaments(): string {
        return $this->encode(CN_Tennis_Calendar::query(['limit' => 300, 'from' => gmdate('Y-m-d', time() - 5 * YEAR_IN_SECONDS)]));
    }

    public function export_settings(): string {
        return $this->encode(CN_Tennis_Settings::all());
    }

    private function encode(array $data): string {
        return (string) wp_json_encode([
            'schema_version' => 1,
            'generated_at' => current_time('c'),
            'source' => 'cartolanews-tennis-hub-admin-export',
            'data' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array{ok:bool,imported:int,skipped:int,message:string} */
    public function import_legends(string $json): array {
        $rows = $this->decode($json);
        if ($rows === null) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'JSON inválido.'];
        }
        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['name'])) {
                $skipped++;
                continue;
            }
            $result = CN_Tennis_Legends::save($row);
            $result['ok'] ? $imported++ : $skipped++;
        }
        return ['ok' => true, 'imported' => $imported, 'skipped' => $skipped, 'message' => "{$imported} lenda(s) importada(s), {$skipped} ignorada(s)."];
    }

    public function import_players(string $json): array {
        $rows = $this->decode($json);
        if ($rows === null) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'JSON inválido.'];
        }
        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['name'])) {
                $skipped++;
                continue;
            }
            $row['provider'] = 'manual';
            $row['external_id'] = $row['external_id'] ?? ('manual:' . sanitize_title((string) $row['name']));
            CN_Tennis_Players::upsert_from_provider($row) > 0 ? $imported++ : $skipped++;
        }
        return ['ok' => true, 'imported' => $imported, 'skipped' => $skipped, 'message' => "{$imported} jogador(es) importado(s), {$skipped} ignorado(s)."];
    }

    public function import_tournaments(string $json): array {
        $rows = $this->decode($json);
        if ($rows === null) {
            return ['ok' => false, 'imported' => 0, 'skipped' => 0, 'message' => 'JSON inválido.'];
        }
        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['name']) || empty($row['starts_at'])) {
                $skipped++;
                continue;
            }
            $row['provider'] = 'manual';
            $row['external_id'] = $row['external_id'] ?? ('manual:' . sanitize_title((string) $row['name']) . ':' . $row['starts_at']);
            CN_Tennis_Calendar::upsert_from_provider($row) > 0 ? $imported++ : $skipped++;
        }
        return ['ok' => true, 'imported' => $imported, 'skipped' => $skipped, 'message' => "{$imported} torneio(s) importado(s), {$skipped} ignorado(s)."];
    }

    private function decode(string $json): ?array {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        $rows = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;
        return array_is_list($rows) ? $rows : null;
    }
}
