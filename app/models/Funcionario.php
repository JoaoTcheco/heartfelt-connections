<?php
/**
 * ============================================================
 * FarmaPonto - Funcionario Model
 * ============================================================
 * Responsabilidade: CRUD de funcionarios, autenticacao, PIN.
 */

namespace App\Models;

use App\Core\Database;
use App\Helpers\Cripto;

final class Funcionario {

    /** Colunas seguras devolvidas nas listagens (sem hashes). */
    private const COLUNAS_LISTA = 'id, codigo, nome, email, cargo, perfil, marca_ponto, ativo, salario_base, dias_uteis_mes, dias_trabalho, salario_diario, salario_hora, salario_minuto, valor_hora_extra, carga_diaria, hora_entrada, hora_saida';

    /** Criterio unico de "quem entra na assiduidade" (ponto, faltas, salarios). */
    public const SQL_PONTUAVEL = 'ativo = 1 AND marca_ponto = 1';

    private static bool $esquemaOk = false;

    /**
     * Auto-migracao idempotente: garante colunas novas em instalacoes antigas
     * sem obrigar a reimportar a base de dados.
     */
    private static function garantirEsquema(): void {
        if (self::$esquemaOk) { return; }
        self::$esquemaOk = true;
        Database::garantirColuna('funcionarios', 'valor_hora_extra', "DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor pago por hora extra' AFTER salario_minuto");
        // Credenciais operacionais cifradas (consultaveis apenas por admin).
        Database::garantirColuna('funcionarios', 'pin_cifrado', "VARCHAR(255) NULL COMMENT 'PIN actual cifrado (AES-256-CBC)' AFTER pin_hash");
        Database::garantirColuna('funcionarios', 'password_cifrada', "VARCHAR(255) NULL COMMENT 'Password actual cifrada (AES-256-CBC)' AFTER pin_cifrado");

        // Separacao entre utilizadores administrativos e colaboradores que picam
        // o ponto. Na primeira migracao os perfis 'admin' passam a nao pontuar e
        // as faltas automaticas que lhes foram geradas sao removidas.
        $novaColuna = !Database::temColuna('funcionarios', 'marca_ponto');
        Database::garantirColuna(
            'funcionarios',
            'marca_ponto',
            "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = entra na assiduidade; 0 = utilizador administrativo' AFTER perfil"
        );
        if ($novaColuna && Database::temColuna('funcionarios', 'marca_ponto')) {
            $pdo = Database::pdo();
            $pdo->exec("UPDATE funcionarios SET marca_ponto = 0 WHERE perfil = 'admin'");
            $pdo->exec(
                "DELETE r FROM registos r
                 JOIN funcionarios f ON f.id = r.funcionario_id
                 WHERE f.marca_ponto = 0 AND r.tipo = 'falta'"
            );
        }
    }

    /**
     * Normaliza o campo marca_ponto de um formulario.
     * Por omissao: perfis 'admin' nao pontuam, os restantes pontuam.
     */
    private static function normalizarMarcaPonto(array $d): int {
        if (array_key_exists('marca_ponto', $d) && $d['marca_ponto'] !== '' && $d['marca_ponto'] !== null) {
            return ((int) $d['marca_ponto']) === 1 ? 1 : 0;
        }
        return ($d['perfil'] ?? 'funcionario') === 'admin' ? 0 : 1;
    }

    /** Um funcionario entra na assiduidade? */
    public function pontua(int $id): bool {
        self::garantirEsquema();
        $st = Database::pdo()->prepare("SELECT marca_ponto FROM funcionarios WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return (int) $st->fetchColumn() === 1;
    }



    public function porEmail(string $email): ?array {
        self::garantirEsquema();
        $st = Database::pdo()->prepare("SELECT * FROM funcionarios WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        return $st->fetch() ?: null;
    }


    public function porId(int $id): ?array {
        self::garantirEsquema();
        $st = Database::pdo()->prepare("SELECT * FROM funcionarios WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }


    public function porCodigo(string $codigo): ?array {
        $st = Database::pdo()->prepare("SELECT * FROM funcionarios WHERE codigo = ? AND ativo = 1 LIMIT 1");
        $st->execute([$codigo]);
        return $st->fetch() ?: null;
    }

    public function codigoExiste(string $codigo, ?int $excluirId = null): bool {
        $sql = "SELECT 1 FROM funcionarios WHERE codigo = ?";
        $params = [$codigo];
        if ($excluirId) {
            $sql .= " AND id != ?";
            $params[] = $excluirId;
        }
        $sql .= " LIMIT 1";
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function listar(?string $filtro = null, bool $incluirInativos = false): array {
        self::garantirEsquema();
        $sql = "SELECT " . self::COLUNAS_LISTA . " FROM funcionarios";
        $where = [];

        $p = [];
        if (!$incluirInativos) {
            $where[] = "ativo = 1";
        }
        if ($filtro) {
            $where[] = "(nome LIKE ? OR email LIKE ? OR codigo LIKE ? OR cargo LIKE ?)";
            $f = "%$filtro%";
            $p = [$f, $f, $f, $f];
        }
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY nome";
        $st = Database::pdo()->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    public function listarAtivos(): array {
        self::garantirEsquema();
        $st = Database::pdo()->query("SELECT " . self::COLUNAS_LISTA . " FROM funcionarios WHERE ativo = 1 ORDER BY nome");
        return $st->fetchAll();
    }

    /**
     * Colaboradores que entram na assiduidade (ponto, faltas, atrasos, salarios).
     * Utilizadores administrativos (marca_ponto = 0) ficam sempre de fora.
     */
    public function listarPontuaveis(): array {
        self::garantirEsquema();
        $st = Database::pdo()->query(
            "SELECT " . self::COLUNAS_LISTA . " FROM funcionarios WHERE " . self::SQL_PONTUAVEL . " ORDER BY nome"
        );
        return $st->fetchAll();
    }




    public function criar(array $d): int {
        self::garantirEsquema();
        $st = Database::pdo()->prepare(
            "INSERT INTO funcionarios (codigo, nome, email, cargo, password_hash, pin_hash, password_cifrada, pin_cifrado, perfil, marca_ponto, salario_base, dias_uteis_mes, dias_trabalho, salario_diario, salario_hora, salario_minuto, valor_hora_extra, carga_diaria, hora_entrada, hora_saida, ativo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $st->execute([
            $d['codigo'],
            $d['nome'],
            $d['email'],
            $d['cargo'] ?? 'Funcionario',
            password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            password_hash($d['pin'], PASSWORD_BCRYPT, ['cost' => 12]),
            Cripto::cifrar((string) $d['password']),
            Cripto::cifrar((string) $d['pin']),
            $d['perfil'] ?? 'funcionario',
            self::normalizarMarcaPonto($d),
            $d['salario_base'] ?? 0,
            $d['dias_uteis_mes'] ?? 22,
            $d['dias_trabalho'] ?? '1,2,3,4,5',
            $d['salario_diario'] ?? 0,
            $d['salario_hora'] ?? 0,
            $d['salario_minuto'] ?? 0,
            $d['valor_hora_extra'] ?? 0,
            $d['carga_diaria'] ?? 8,
            $d['hora_entrada'] ?? '08:00:00',
            $d['hora_saida'] ?? '17:00:00'
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function atualizar(int $id, array $d): void {
        self::garantirEsquema();
        $sets = [
            'codigo = ?', 'nome = ?', 'email = ?', 'cargo = ?', 'perfil = ?', 'marca_ponto = ?',
            'salario_base = ?', 'dias_uteis_mes = ?', 'dias_trabalho = ?', 'salario_diario = ?', 'salario_hora = ?', 'salario_minuto = ?',
            'valor_hora_extra = ?', 'carga_diaria = ?', 'hora_entrada = ?', 'hora_saida = ?'
        ];
        $vals = [
            $d['codigo'] ?? '', $d['nome'] ?? '', $d['email'] ?? '', $d['cargo'] ?? '', $d['perfil'] ?? 'funcionario',
            self::normalizarMarcaPonto($d),
            $d['salario_base'] ?? 0, $d['dias_uteis_mes'] ?? 22, $d['dias_trabalho'] ?? '1,2,3,4,5',
            $d['salario_diario'] ?? 0, $d['salario_hora'] ?? 0, $d['salario_minuto'] ?? 0,
            $d['valor_hora_extra'] ?? 0,
            $d['carga_diaria'] ?? 8, $d['hora_entrada'] ?? '08:00:00', $d['hora_saida'] ?? '17:00:00'
        ];

        if (!empty($d['password'])) {
            $sets[] = 'password_hash = ?';
            $vals[] = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $sets[] = 'password_cifrada = ?';
            $vals[] = Cripto::cifrar((string) $d['password']);
        }
        if (!empty($d['pin'])) {
            $sets[] = 'pin_hash = ?';
            $vals[] = password_hash($d['pin'], PASSWORD_BCRYPT, ['cost' => 12]);
            $sets[] = 'pin_cifrado = ?';
            $vals[] = Cripto::cifrar((string) $d['pin']);
        }
        $vals[] = $id;
        Database::pdo()->prepare(
            "UPDATE funcionarios SET " . implode(', ', $sets) . " WHERE id = ?"
        )->execute($vals);
    }

    public function atualizarPerfil(int $id, array $d): void {
        $st = Database::pdo()->prepare("UPDATE funcionarios SET nome = ?, email = ? WHERE id = ?");
        $st->execute([$d['nome'], $d['email'], $id]);
    }

    public function toggleAtivo(int $id): void {
        Database::pdo()->prepare("UPDATE funcionarios SET ativo = 1 - ativo WHERE id = ?")->execute([$id]);
    }

    /** Devolve todos os funcionarios ativos cujo PIN corresponde (para detetar PINs repetidos). */
    public function todosPorPin(string $pin): array {
        self::garantirEsquema();
        $st = Database::pdo()->query("SELECT id, codigo, nome, email, cargo, pin_hash, carga_diaria, hora_entrada, hora_saida FROM funcionarios WHERE " . self::SQL_PONTUAVEL);
        $encontrados = [];
        while ($r = $st->fetch()) {
            if ($r['pin_hash'] && password_verify($pin, $r['pin_hash'])) {
                unset($r['pin_hash']);
                $encontrados[] = $r;
            }
        }
        return $encontrados;
    }

    /** Valida o PIN de um funcionario identificado pelo codigo. */
    public function porCodigoPin(string $codigo, string $pin): ?array {
        self::garantirEsquema();
        $st = Database::pdo()->prepare("SELECT id, codigo, nome, email, cargo, pin_hash, carga_diaria, hora_entrada, hora_saida FROM funcionarios WHERE " . self::SQL_PONTUAVEL . " AND codigo = ? LIMIT 1");
        $st->execute([$codigo]);
        $r = $st->fetch();
        if (!$r || !$r['pin_hash'] || !password_verify($pin, $r['pin_hash'])) {
            return null;
        }
        unset($r['pin_hash']);
        return $r;
    }

    public function porPin(string $pin): ?array {
        self::garantirEsquema();
        $st = Database::pdo()->query("SELECT id, codigo, nome, email, cargo, pin_hash, carga_diaria, hora_entrada, hora_saida FROM funcionarios WHERE " . self::SQL_PONTUAVEL);
        while ($r = $st->fetch()) {
            if (password_verify($pin, $r['pin_hash'])) {
                unset($r['pin_hash']);
                return $r;
            }
        }
        return null;
    }

    public function resetarPin(int $id, string $pin): void {
        self::garantirEsquema();
        Database::pdo()->prepare("UPDATE funcionarios SET pin_hash = ?, pin_cifrado = ? WHERE id = ?")
            ->execute([password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]), Cripto::cifrar($pin), $id]);
    }

    /**
     * Verifica se o PIN ja pertence a outro funcionario (PINs tem de ser unicos).
     * Compara contra o hash bcrypt de cada funcionario (activos e inactivos).
     * @return array|null Dados minimos do funcionario que ja usa este PIN.
     */
    public function pinEmUso(string $pin, ?int $excluirId = null): ?array {
        self::garantirEsquema();
        $st = Database::pdo()->query("SELECT id, codigo, nome, pin_hash FROM funcionarios");
        while ($r = $st->fetch()) {
            if ($excluirId !== null && (int) $r['id'] === $excluirId) { continue; }
            if (!empty($r['pin_hash']) && password_verify($pin, $r['pin_hash'])) {
                return ['id' => (int) $r['id'], 'codigo' => $r['codigo'], 'nome' => $r['nome']];
            }
        }
        return null;
    }

    /** Credenciais actuais legiveis (apenas as que foram definidas apos a cifra existir). */
    public function credenciais(int $id): array {
        self::garantirEsquema();
        $st = Database::pdo()->prepare("SELECT pin_cifrado, password_cifrada FROM funcionarios WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch() ?: [];
        return [
            'pin'      => Cripto::decifrar($r['pin_cifrado'] ?? null),
            'password' => Cripto::decifrar($r['password_cifrada'] ?? null),
        ];
    }

    public function alterarPassword(int $id, string $novaPassword): void {
        self::garantirEsquema();
        Database::pdo()->prepare("UPDATE funcionarios SET password_hash = ?, password_cifrada = ? WHERE id = ?")
            ->execute([password_hash($novaPassword, PASSWORD_BCRYPT, ['cost' => 12]), Cripto::cifrar($novaPassword), $id]);
    }

    public function contarAtivos(): int {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM funcionarios WHERE ativo = 1")->fetchColumn();
    }

    /** Total de colaboradores que entram na assiduidade. */
    public function contarPontuaveis(): int {
        self::garantirEsquema();
        return (int) Database::pdo()
            ->query("SELECT COUNT(*) FROM funcionarios WHERE " . self::SQL_PONTUAVEL)
            ->fetchColumn();
    }

    /**
     * Save working days for a specific month (YYYY-MM).
     * @param int $id Employee ID
     * @param string $anoMes YYYY-MM format
     * @param array $dias Array of day numbers (e.g. [1,2,3,4,5,8,...])
     */
    public function salvarDiasTrabalho(int $id, string $anoMes, array $dias): void {
        $diasJson = json_encode(array_map('intval', $dias));
        $st = Database::pdo()->prepare(
            "INSERT INTO funcionario_dias_trabalho (funcionario_id, ano_mes, dias)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE dias = VALUES(dias)"
        );
        $st->execute([$id, $anoMes, $diasJson]);
    }

    /**
     * Get working days for a specific month.
     * @param int $id Employee ID
     * @param string $anoMes YYYY-MM format
     * @return array Array of day numbers (e.g. [1,2,3,4,5])
     */
    /**
     * Elimina um funcionario e todos os seus dados em cascata.
     * Remove registos, selfies (ficheiros + BD), dias de trabalho, logs, tokens.
     */
    /**
     * Elimina um funcionário e todos os seus dados.
     * Antes de apagar, guarda tudo na lixeira (funcionário, registos de
     * ponto, fotografias e dias de trabalho), pelo que a operação pode
     * ser desfeita durante 60 dias. Corre numa só transação: ou apaga
     * tudo, ou não apaga nada.
     */
    public function eliminar(int $id): void {
        Database::transacao(function ($pdo) use ($id) {
            $func = $this->porId($id);
            if (!$func) { return; }

            $dependentes = [];
            foreach (['registos', 'selfies', 'funcionario_dias_trabalho'] as $tabela) {
                if (!Database::temTabela($tabela)) { continue; }
                $st = $pdo->prepare("SELECT * FROM `{$tabela}` WHERE funcionario_id = ?");
                $st->execute([$id]);
                $dependentes[$tabela] = $st->fetchAll();
            }

            (new \App\Models\Lixeira())->arquivar(
                'funcionarios', $func, null,
                'Funcionário eliminado com todos os seus dados',
                (string) ($func['nome'] ?? ''),
                $dependentes
            );

            $pdo->prepare("DELETE FROM selfies WHERE funcionario_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM registos WHERE funcionario_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM funcionario_dias_trabalho WHERE funcionario_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM logs WHERE funcionario_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM password_resets WHERE funcionario_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM funcionarios WHERE id = ?")->execute([$id]);
        });
        // As fotografias em disco só são removidas pela purga por retenção:
        // assim a reposição a partir da lixeira devolve também as imagens.
    }

    public function getDiasTrabalho(int $id, string $anoMes): array {
        $st = Database::pdo()->prepare(
            "SELECT dias FROM funcionario_dias_trabalho WHERE funcionario_id = ? AND ano_mes = ? LIMIT 1"
        );
        $st->execute([$id, $anoMes]);
        $row = $st->fetchColumn();
        if (!$row) return [];
        $dias = json_decode($row, true);
        return is_array($dias) ? $dias : [];
    }
}
