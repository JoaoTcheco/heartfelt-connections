<?php
/**
 * ============================================================
 * FarmaPonto - Selfie Model
 * ============================================================
 * Responsabilidade: Persistir imagem em disco + metadados em BD.
 */

namespace App\Models;

use App\Core\Database;

final class Selfie {

    public function gravarBase64(int $funcId, string $b64): int {
        if (!preg_match('#^data:image/jpeg;base64,#', $b64)) {
            throw new \RuntimeException('Formato invalido. Use JPEG.');
        }

        $bin = base64_decode(preg_replace('#^data:image/jpeg;base64,#', '', $b64), true);
        if ($bin === false || strlen($bin) > 2 * 1024 * 1024) {
            throw new \RuntimeException('Selfie invalida ou demasiado grande (max 2MB).');
        }

        $hash = hash('sha256', $bin);
        $dir = __DIR__ . '/../../storage/selfies/' . date('Y/m');
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $caminho = $dir . '/' . $funcId . '_' . time() . '_' . substr($hash, 0, 8) . '.jpg';
        file_put_contents($caminho, $bin);

        $rel = 'storage/selfies/' . date('Y/m') . '/' . basename($caminho);

        $st = Database::pdo()->prepare(
            "INSERT INTO selfies (funcionario_id, caminho, hash_sha256, bytes) VALUES (?, ?, ?, ?)"
        );
        $st->execute([$funcId, $rel, $hash, strlen($bin)]);
        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * Apaga selfies com mais de N meses (ficheiro em disco + linha em BD).
     * Os registos de ponto sao preservados: apenas perdem a referencia a foto.
     *
     * @param int $meses 0 = nunca apagar.
     * @return int Numero de selfies removidas.
     */
    public function limparAntigas(int $meses): int {
        if ($meses <= 0) { return 0; }

        $pdo = Database::pdo();
        $limite = date('Y-m-d H:i:s', strtotime("-{$meses} months"));

        $st = $pdo->prepare("SELECT id, caminho FROM selfies WHERE criado_em < ? LIMIT 5000");
        $st->execute([$limite]);
        $antigas = $st->fetchAll();
        if (!$antigas) { return 0; }

        $ids = array_map(static fn ($s) => (int) $s['id'], $antigas);
        $ph = implode(',', array_fill(0, count($ids), '?'));

        Database::tx(function ($pdo) use ($ids, $ph) {
            $pdo->prepare("UPDATE registos SET selfie_id = NULL WHERE selfie_id IN ($ph)")->execute($ids);
            $pdo->prepare("DELETE FROM selfies WHERE id IN ($ph)")->execute($ids);
        });

        foreach ($antigas as $s) {
            $abs = __DIR__ . '/../../' . $s['caminho'];
            if (is_file($abs)) { @unlink($abs); }
        }
        return count($antigas);
    }

    /**
     * Purga automatica: corre no maximo uma vez por dia, guiada pelas
     * configuracoes 'selfies_retencao_meses' e 'selfies_purga_ultima'.
     */
    public function purgaAutomatica(): int {
        try {
            $cfg = new Config();
            $meses = (int) $cfg->get('selfies_retencao_meses', '0');
            if ($meses <= 0) { return 0; }
            if ($cfg->get('selfies_purga_ultima', '') === date('Y-m-d')) { return 0; }
            $cfg->set('selfies_purga_ultima', date('Y-m-d'));
            $n = $this->limparAntigas($meses);
            if ($n > 0) {
                Log::reg(null, 'selfies_purgadas', 'selfies', null, ['removidas' => $n, 'retencao_meses' => $meses]);
            }
            return $n;
        } catch (\Throwable $e) {
            error_log('[Selfie] purga: ' . $e->getMessage());
            return 0;
        }
    }
}
