<?php
/**
 * ============================================================
 * FarmaPonto - Digital Model (impressoes digitais)
 * ============================================================
 * Guarda apenas o HASH do template lido pelo leitor biometrico
 * (ou do credencial do sensor do dispositivo). Nunca a imagem.
 * Maximo de 3 dedos por funcionario.
 */

namespace App\Models;

use App\Core\Database;

final class Digital {

    public const MAX_DEDOS = 3;

    private static bool $schemaOk = false;

    /** Cria a tabela em instalacoes antigas (idempotente e barato). */
    public static function ensureSchema(): void {
        if (self::$schemaOk) { return; }
        Database::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `funcionario_digitais` (
              `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `funcionario_id` INT UNSIGNED NOT NULL,
              `slot`           TINYINT UNSIGNED NOT NULL,
              `dedo`           VARCHAR(40) NOT NULL DEFAULT 'Indicador direito',
              `origem`         ENUM('leitor','dispositivo') NOT NULL DEFAULT 'leitor',
              `template_hash`  CHAR(64) NOT NULL,
              `criado_em`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `ultimo_uso`     DATETIME NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_digital_slot` (`funcionario_id`, `slot`),
              UNIQUE KEY `uq_digital_hash` (`template_hash`),
              CONSTRAINT `fk_digitais_funcionario`
                FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaOk = true;
    }

    public function __construct() { self::ensureSchema(); }

    /** Pepper persistido em config; gerado na primeira utilizacao. */
    public static function pepper(): string {
        $cfg = new Config();
        $p = $cfg->get('digital_pepper', '');
        if ($p === '') {
            $p = bin2hex(random_bytes(32));
            $cfg->set('digital_pepper', $p);
        }
        return $p;
    }

    /** Normaliza + faz hash do template recebido do leitor. */
    public static function hash(string $template): string {
        $t = preg_replace('/\s+/', '', $template);
        return hash_hmac('sha256', (string) $t, self::pepper());
    }

    public function listar(int $funcId): array {
        $st = Database::pdo()->prepare(
            "SELECT id, slot, dedo, origem, criado_em, ultimo_uso
             FROM funcionario_digitais WHERE funcionario_id = ? ORDER BY slot"
        );
        $st->execute([$funcId]);
        return $st->fetchAll();
    }

    public function total(int $funcId): int {
        $st = Database::pdo()->prepare("SELECT COUNT(*) c FROM funcionario_digitais WHERE funcionario_id = ?");
        $st->execute([$funcId]);
        return (int) ($st->fetch()['c'] ?? 0);
    }

    /** Primeiro slot livre (1..3) ou null se ja tiver 3. */
    public function slotLivre(int $funcId): ?int {
        $usados = array_column($this->listar($funcId), 'slot');
        for ($i = 1; $i <= self::MAX_DEDOS; $i++) {
            if (!in_array((int) $i, array_map('intval', $usados), true)) { return $i; }
        }
        return null;
    }

    /** Devolve o funcionario dono deste template (ou null). */
    public function porTemplate(string $template): ?array {
        $st = Database::pdo()->prepare(
            "SELECT d.id AS digital_id, d.slot, d.dedo, f.id, f.codigo, f.nome, f.ativo
             FROM funcionario_digitais d
             JOIN funcionarios f ON f.id = d.funcionario_id
             WHERE d.template_hash = ? LIMIT 1"
        );
        $st->execute([self::hash($template)]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public function existeHash(string $template): bool {
        $st = Database::pdo()->prepare("SELECT id FROM funcionario_digitais WHERE template_hash = ? LIMIT 1");
        $st->execute([self::hash($template)]);
        return (bool) $st->fetch();
    }

    public function guardar(int $funcId, int $slot, string $dedo, string $template, string $origem = 'leitor'): int {
        $st = Database::pdo()->prepare(
            "INSERT INTO funcionario_digitais (funcionario_id, slot, dedo, origem, template_hash, criado_em)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE dedo = VALUES(dedo), origem = VALUES(origem),
                                     template_hash = VALUES(template_hash), criado_em = NOW(), ultimo_uso = NULL"
        );
        $st->execute([$funcId, $slot, $dedo, $origem, self::hash($template)]);
        $id = (int) Database::pdo()->lastInsertId();
        if ($id === 0) {
            $q = Database::pdo()->prepare("SELECT id FROM funcionario_digitais WHERE funcionario_id = ? AND slot = ?");
            $q->execute([$funcId, $slot]);
            $id = (int) ($q->fetch()['id'] ?? 0);
        }
        return $id;
    }

    public function remover(int $id): ?int {
        $q = Database::pdo()->prepare("SELECT funcionario_id FROM funcionario_digitais WHERE id = ?");
        $q->execute([$id]);
        $r = $q->fetch();
        if (!$r) { return null; }
        $st = Database::pdo()->prepare("DELETE FROM funcionario_digitais WHERE id = ?");
        $st->execute([$id]);
        return (int) $r['funcionario_id'];
    }

    public function marcarUso(int $digitalId): void {
        $st = Database::pdo()->prepare("UPDATE funcionario_digitais SET ultimo_uso = NOW() WHERE id = ?");
        $st->execute([$digitalId]);
    }

    /** Contagem por funcionario, para listas (id => total). */
    public function contagens(): array {
        $st = Database::pdo()->query("SELECT funcionario_id, COUNT(*) c FROM funcionario_digitais GROUP BY funcionario_id");
        $out = [];
        foreach ($st->fetchAll() as $r) { $out[(int) $r['funcionario_id']] = (int) $r['c']; }
        return $out;
    }
}
