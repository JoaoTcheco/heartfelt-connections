<?php
/**
 * ============================================================
 * FarmaPonto - Registo Model
 * ============================================================
 * Responsabilidade: Marcacoes e agregacoes mensais.
 */

namespace App\Models;

use App\Core\Database;

final class Registo {

    /**
     * Garante as colunas de origem da marcacao (idempotente).
     * Permite actualizar instalacoes antigas sem migracao manual.
     */
    public static function ensureSchema(): void {
        static $feito = false;
        if ($feito) { return; }
        $feito = true;
        $pdo = Database::pdo();
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM registos LIKE 'metodo'")->fetchAll();
            if (!$cols) {
                $pdo->exec("ALTER TABLE registos
                    ADD COLUMN `metodo` VARCHAR(20) NOT NULL DEFAULT 'painel' AFTER `selfie_id`,
                    ADD COLUMN `metodo_detalhe` VARCHAR(60) NULL AFTER `metodo`,
                    ADD KEY `ix_registos_metodo` (`metodo`)");
            }
        } catch (\Throwable $e) {
            // Sem privilegios de ALTER: o sistema continua a funcionar
            // com o valor por omissao 'painel'.
        }
    }

    /**
     * Cria uma marcacao.
     * $metodo: painel | pin | digital | sistema  (ver App\Helpers\Metodo)
     * $metodoDetalhe: informacao extra (ex.: dedo usado na digital).
     */
    public function registar(
        int $funcId,
        string $tipo,
        ?int $selfieId = null,
        ?string $obs = null,
        string $metodo = \App\Helpers\Metodo::PAINEL,
        ?string $metodoDetalhe = null
    ): int {
        self::ensureSchema();
        $metodo = \App\Helpers\Metodo::normalizar($metodo);
        $st = Database::pdo()->prepare(
            "INSERT INTO registos (funcionario_id, tipo, marcado_em, ip, user_agent, selfie_id, metodo, metodo_detalhe, observacao)
             VALUES (?, ?, NOW(), INET6_ATON(?), ?, ?, ?, ?, ?)"
        );
        $st->execute([
            $funcId,
            $tipo,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $selfieId,
            $metodo,
            $metodoDetalhe !== null ? substr($metodoDetalhe, 0, 60) : null,
            $obs
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function listarPorDia(int $funcId, string $data): array {
        self::ensureSchema();
        $st = Database::pdo()->prepare(
            "SELECT id, tipo, marcado_em, observacao, metodo, metodo_detalhe, selfie_id FROM registos
             WHERE funcionario_id = ? AND DATE(marcado_em) = ? ORDER BY marcado_em"
        );
        $st->execute([$funcId, $data]);
        return $st->fetchAll();
    }

    public function listarPorPeriodo(?int $funcId, string $de, string $ate, ?string $tipo = null): array {
        self::ensureSchema();
        $base = "SELECT r.id, r.funcionario_id, r.tipo, r.marcado_em, r.observacao, r.selfie_id,
                        r.metodo, r.metodo_detalhe,
                        f.nome as funcionario_nome, s.id as selfie_existe,
                        f.hora_entrada, f.hora_saida
                 FROM registos r
                 JOIN funcionarios f ON f.id = r.funcionario_id
                 LEFT JOIN selfies s ON s.id = r.selfie_id";
        $where = [];
        $params = [];
        if ($funcId) { $where[] = "r.funcionario_id = ?"; $params[] = $funcId; }
        $where[] = "DATE(r.marcado_em) BETWEEN ? AND ?";
        $params[] = $de; $params[] = $ate;
        if ($tipo && in_array($tipo, ['entrada','saida','ferias','falta','atraso','atestado'], true)) {
            $where[] = "r.tipo = ?"; $params[] = $tipo;
        }
        $sql = "$base WHERE " . implode(' AND ', $where) . " ORDER BY r.marcado_em DESC";
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function proximoTipo(int $funcId): string {
        $r = (new self())->ultimoTipo($funcId);
        return match ($r) {
            'entrada' => 'saida',
            default => 'entrada',
        };
    }

    public function entradaHoje(int $funcId): ?string {
        $st = Database::pdo()->prepare(
            "SELECT marcado_em FROM registos WHERE funcionario_id = ? AND DATE(marcado_em) = CURDATE() AND tipo = 'entrada' ORDER BY marcado_em LIMIT 1"
        );
        $st->execute([$funcId]);
        $r = $st->fetch();
        return $r['marcado_em'] ?? null;
    }

    public function validarTransicao(int $funcId, string $tipo): ?string {
        $ultimo = $this->ultimoTipo($funcId);
        $mapa = [
            'entrada' => [null, 'saida', 'ferias', 'falta', 'atestado', 'atraso'],
            'saida'   => ['entrada'],
        ];
        $permitidos = $mapa[$tipo] ?? [];
        if (!in_array($ultimo, $permitidos, true)) {
            if ($tipo === 'entrada') {
                return 'Ja tem uma entrada aberta. Marque primeiro a saida.';
            }
            if ($tipo === 'saida') {
                return 'Ainda nao ha entrada registada hoje. Marque primeiro a entrada.';
            }
            return 'Nao e possivel marcar ' . str_replace('_', ' ', $tipo) . ' neste momento.';
        }
        return null;
    }

    public function ultimoTipo(int $funcId): ?string {
        $st = Database::pdo()->prepare(
            "SELECT tipo FROM registos WHERE funcionario_id = ? AND DATE(marcado_em) = CURDATE()
             ORDER BY marcado_em DESC LIMIT 1"
        );
        $st->execute([$funcId]);
        $r = $st->fetch();
        return $r['tipo'] ?? null;
    }

    public function agregarMensal(int $funcId, int $ano, int $mes): array {
        $st = Database::pdo()->prepare(
            "SELECT DATE(marcado_em) as dia,
                    MIN(CASE WHEN tipo = 'entrada' THEN marcado_em END) as entrada,
                    MAX(CASE WHEN tipo = 'saida' THEN marcado_em END) as saida
             FROM registos
             WHERE funcionario_id = ? AND YEAR(marcado_em) = ? AND MONTH(marcado_em) = ?
             GROUP BY DATE(marcado_em) ORDER BY dia"
        );
        $st->execute([$funcId, $ano, $mes]);
        return $st->fetchAll();
    }

    /**
     * Agrega entradas/saidas por dia num intervalo de datas (inclusivo).
     */
    public function agregarIntervalo(int $funcId, string $de, string $ate): array {
        $st = Database::pdo()->prepare(
            "SELECT DATE(marcado_em) as dia,
                    MIN(CASE WHEN tipo = 'entrada' THEN marcado_em END) as entrada,
                    MAX(CASE WHEN tipo = 'saida' THEN marcado_em END) as saida
             FROM registos
             WHERE funcionario_id = ? AND DATE(marcado_em) BETWEEN ? AND ?
             GROUP BY DATE(marcado_em) ORDER BY dia"
        );
        $st->execute([$funcId, $de, $ate]);
        return $st->fetchAll();
    }

    /**
     * Dias de ferias dentro de um intervalo de datas (inclusivo).
     */
    public function diasFeriasIntervalo(int $funcId, string $de, string $ate): array {
        $st = Database::pdo()->prepare(
            "SELECT DATE(marcado_em) as dia FROM registos
             WHERE funcionario_id = ? AND tipo = 'ferias'
               AND DATE(marcado_em) BETWEEN ? AND ?"
        );
        $st->execute([$funcId, $de, $ate]);
        $out = [];
        foreach ($st->fetchAll() as $r) { $out[$r['dia']] = true; }
        return $out;
    }



    public function agregarMensalTodos(int $ano, int $mes): array {
        $tol = gmdate('H:i:s', max(0, (int) (new \App\Models\Config())->get('tolerancia_atraso_min', '5')) * 60);
        $st = Database::pdo()->prepare(
            "SELECT f.id, f.nome, f.cargo, f.salario_base,
                    COUNT(DISTINCT DATE(r.marcado_em)) as dias_com_registo,
                    SUM(CASE WHEN r.tipo = 'entrada' AND TIME(r.marcado_em) > ADDTIME(f.hora_entrada, ?) THEN 1 ELSE 0 END) as atrasos,
                    SUM(CASE WHEN r.tipo = 'falta' THEN 1 ELSE 0 END) as faltas
             FROM funcionarios f
             LEFT JOIN registos r ON r.funcionario_id = f.id
                 AND YEAR(r.marcado_em) = ? AND MONTH(r.marcado_em) = ?
             WHERE f.ativo = 1 AND f.marca_ponto = 1
             GROUP BY f.id ORDER BY f.nome"
        );
        $st->execute([$tol, $ano, $mes]);
        return $st->fetchAll();
    }

    public function presentesHoje(): int {
        return (int) Database::pdo()->query(
            "SELECT COUNT(DISTINCT r.funcionario_id) FROM registos r
             JOIN funcionarios f ON f.id = r.funcionario_id
             WHERE r.tipo = 'entrada' AND DATE(r.marcado_em) = CURDATE()
               AND f.ativo = 1 AND f.marca_ponto = 1"
        )->fetchColumn();
    }

    /**
     * Atrasos de hoje, usando a tolerancia definida nas Configuracoes.
     */
    public function atrasosHoje(?int $toleranciaMin = null): int {
        if ($toleranciaMin === null) {
            $toleranciaMin = (int) (new \App\Models\Config())->get('tolerancia_atraso_min', '5');
        }
        $tol = gmdate('H:i:s', max(0, $toleranciaMin) * 60);
        $st = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM registos r
             JOIN funcionarios f ON f.id = r.funcionario_id
             WHERE r.tipo = 'entrada' AND DATE(r.marcado_em) = CURDATE()
             AND f.ativo = 1 AND f.marca_ponto = 1
             AND TIME(r.marcado_em) > ADDTIME(f.hora_entrada, ?)"
        );
        $st->execute([$tol]);
        return (int) $st->fetchColumn();
    }

    /**
     * Faltas de hoje = funcionarios ativos que:
     *   - tinham hoje como dia de trabalho (calendario do mes ou escala semanal),
     *   - nao estao de ferias hoje,
     *   - ja passaram da hora de entrada + tolerancia,
     *   - e ainda nao marcaram entrada.
     * Quem nao trabalha hoje ou esta de ferias NUNCA conta como falta.
     */
    public function faltasHoje(): int {
        return count($this->faltososHoje());
    }

    /** @return array<int,array{id:int,nome:string,cargo:string}> */
    public function faltososHoje(): array {
        $pdo = Database::pdo();
        $hoje = date('Y-m-d');
        $anoMes = date('Y-m');
        $diaNum = (int) date('j');
        $diaSemana = (int) date('N');
        $tolerancia = (int) (new \App\Models\Config())->get('tolerancia_atraso_min', '5');

        $ativos = $pdo->query(
            "SELECT id, nome, cargo, dias_trabalho, hora_entrada FROM funcionarios
             WHERE " . \App\Models\Funcionario::SQL_PONTUAVEL
        )->fetchAll();

        $entradas = array_column(
            $pdo->query("SELECT DISTINCT funcionario_id FROM registos WHERE tipo = 'entrada' AND DATE(marcado_em) = CURDATE()")->fetchAll(),
            'funcionario_id'
        );
        $ferias = array_column(
            $pdo->query("SELECT DISTINCT funcionario_id FROM registos WHERE tipo = 'ferias' AND DATE(marcado_em) = CURDATE()")->fetchAll(),
            'funcionario_id'
        );

        $calSt = $pdo->prepare("SELECT dias FROM funcionario_dias_trabalho WHERE funcionario_id = ? AND ano_mes = ? LIMIT 1");

        $faltosos = [];
        foreach ($ativos as $f) {
            $id = (int) $f['id'];
            if (in_array($id, array_map('intval', $entradas), true)) continue;
            if (in_array($id, array_map('intval', $ferias), true)) continue;

            // Dia de trabalho? Calendario do mes tem prioridade sobre a escala semanal.
            $calSt->execute([$id, $anoMes]);
            $raw = $calSt->fetchColumn();
            $cal = $raw ? json_decode((string) $raw, true) : null;
            if (is_array($cal) && $cal) {
                if (!in_array($diaNum, array_map('intval', $cal), true)) continue;
            } else {
                $escala = array_filter(array_map('intval', explode(',', (string) ($f['dias_trabalho'] ?: '1,2,3,4,5'))));
                if (!in_array($diaSemana, $escala, true)) continue;
            }

            // Ainda dentro da hora de entrada + tolerancia -> ainda nao e falta
            $limite = strtotime($hoje . ' ' . ($f['hora_entrada'] ?: '08:00:00')) + ($tolerancia * 60);
            if (time() <= $limite) continue;

            $faltosos[] = ['id' => $id, 'nome' => $f['nome'], 'cargo' => $f['cargo']];
        }
        return $faltosos;
    }

    public function atividadeHoje(): array {
        self::ensureSchema();
        $st = Database::pdo()->query(
            "SELECT f.nome, f.cargo, r.tipo, r.metodo, r.metodo_detalhe, r.selfie_id, TIME(r.marcado_em) as hora
             FROM registos r
             JOIN funcionarios f ON f.id = r.funcionario_id
             WHERE DATE(r.marcado_em) = CURDATE()
             ORDER BY r.marcado_em DESC LIMIT 20"
        );
        return $st->fetchAll();
    }

    /**
     * Inserir férias para um funcionário num intervalo de datas.
     * Cria 1 registo (tipo='ferias') por dia. Por defeito ignora fins-de-semana.
     * Evita duplicados: se já existir um registo de férias para esse dia, salta.
     *
     * @return int Número de dias efectivamente inseridos
     */
    public function inserirFerias(int $funcId, string $de, string $ate, bool $incluirFds = false, ?string $obs = null): int {
        $dt = new \DateTime($de);
        $fim = new \DateTime($ate);
        if ($dt > $fim) return 0;
        $pdo = Database::pdo();
        $verifica = $pdo->prepare(
            "SELECT 1 FROM registos WHERE funcionario_id = ? AND tipo = 'ferias' AND DATE(marcado_em) = ? LIMIT 1"
        );
        $insere = $pdo->prepare(
            "INSERT INTO registos (funcionario_id, tipo, marcado_em, ip, user_agent, observacao)
             VALUES (?, 'ferias', ?, INET6_ATON(?), ?, ?)"
        );
        $inseridos = 0;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'admin', 0, 255);
        while ($dt <= $fim) {
            $dow = (int) $dt->format('N'); // 1=Seg ... 7=Dom
            if ($incluirFds || $dow < 6) {
                $dia = $dt->format('Y-m-d');
                $verifica->execute([$funcId, $dia]);
                if (!$verifica->fetchColumn()) {
                    $insere->execute([$funcId, $dia . ' 09:00:00', $ip, $ua, $obs]);
                    $inseridos++;
                }
            }
            $dt->modify('+1 day');
        }
        return $inseridos;
    }

    /**
     * Devolve mapa [Y-m-d => true] dos dias com férias marcadas no mês.
     */
    public function diasFeriasNoMes(int $funcId, int $ano, int $mes): array {
        $st = Database::pdo()->prepare(
            "SELECT DATE(marcado_em) as dia FROM registos
             WHERE funcionario_id = ? AND tipo = 'ferias'
               AND YEAR(marcado_em) = ? AND MONTH(marcado_em) = ?"
        );
        $st->execute([$funcId, $ano, $mes]);
        $out = [];
        foreach ($st->fetchAll() as $r) { $out[$r['dia']] = true; }
        return $out;
    }

    /**
     * Processa e insere registos de falta para dias de trabalho nao marcados.
     * Percorre todos os funcionarios ativos e insere 'falta' na BD para dias
     * uteis passados sem qualquer registo de entrada e sem falta ja registada.
     *
     * @param string $de Data inicio (Y-m-d)
     * @param string $ate Data fim (Y-m-d)
     * @param int|null $apenasFuncionarioId Se definido, processa apenas este funcionario
     * @return array{processados:int, faltas_inseridas:int, erros:string[]}
     */
    public function processarFaltas(string $de, string $ate, ?int $apenasFuncionarioId = null): array {
        $pdo = Database::pdo();
        $funcModel = new \App\Models\Funcionario();
        $funcs = $apenasFuncionarioId
            ? array_filter([$funcModel->porId($apenasFuncionarioId)])
            : $funcModel->listarPontuaveis();
        $hoje = date('Y-m-d');
        $agora = time();
        $totalInseridas = 0;
        $erros = [];

        $dt = new \DateTime($de);
        $fim = new \DateTime($ate);
        if ($dt > $fim) {
            return ['processados' => 0, 'faltas_inseridas' => 0, 'erros' => ['Data inicio maior que data fim.']];
        }

        $checkFalta = $pdo->prepare(
            "SELECT 1 FROM registos WHERE funcionario_id = ? AND tipo = 'falta' AND DATE(marcado_em) = ? LIMIT 1"
        );
        $checkEntrada = $pdo->prepare(
            "SELECT 1 FROM registos WHERE funcionario_id = ? AND tipo = 'entrada' AND DATE(marcado_em) = ? LIMIT 1"
        );
        $checkFerias = $pdo->prepare(
            "SELECT 1 FROM registos WHERE funcionario_id = ? AND tipo = 'ferias' AND DATE(marcado_em) = ? LIMIT 1"
        );
        $insereFalta = $pdo->prepare(
            "INSERT INTO registos (funcionario_id, tipo, marcado_em, ip, user_agent, observacao)
             VALUES (?, 'falta', ?, INET6_ATON(?), ?, ?)"
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'system', 0, 255);

        $diasTrabalhoCache = [];
        $anoMesAtual = '';

        while ($dt <= $fim) {
            $diaStr = $dt->format('Y-m-d');
            $diaNum = (int) $dt->format('j');
            $diaSemana = (int) $dt->format('N');
            $anoMes = $dt->format('Y-m');

            // Ignorar dias futuros
            if ($diaStr > $hoje) {
                $dt->modify('+1 day');
                continue;
            }

            // Se o mes mudou, limpar cache de dias de trabalho
            if ($anoMes !== $anoMesAtual) {
                $anoMesAtual = $anoMes;
                $diasTrabalhoCache = [];
            }

            foreach ($funcs as $func) {
                $fid = (int) $func['id'];

                // Se ja tem falta, saltar
                $checkFalta->execute([$fid, $diaStr]);
                if ($checkFalta->fetchColumn()) continue;

                // Se ja tem entrada, saltar
                $checkEntrada->execute([$fid, $diaStr]);
                if ($checkEntrada->fetchColumn()) continue;

                // Se esta de ferias, saltar
                $checkFerias->execute([$fid, $diaStr]);
                if ($checkFerias->fetchColumn()) continue;

                // Verificar se e dia de trabalho
                if (!isset($diasTrabalhoCache[$fid])) {
                    $diasTrabalhoCache[$fid] = $funcModel->getDiasTrabalho($fid, $anoMes);
                }
                $diasTrabalho = $diasTrabalhoCache[$fid];

                if (!empty($diasTrabalho)) {
                    $isDiaTrabalho = in_array($diaNum, $diasTrabalho);
                } else {
                    $selectedDays = array_map('intval', explode(',', $func['dias_trabalho'] ?? '1,2,3,4,5'));
                    $isDiaTrabalho = in_array($diaSemana, $selectedDays);
                }

                if (!$isDiaTrabalho) continue;

                // Se for hoje, so marcar falta depois da hora de saida
                if ($diaStr === $hoje) {
                    $horaSaida = $func['hora_saida'] ?? '17:00:00';
                    $limiteSaida = strtotime($hoje . ' ' . $horaSaida);
                    if ($agora < $limiteSaida) continue;
                }

                $obs = 'Falta automatica';
                $marcadoEm = $diaStr . ' ' . ($func['hora_entrada'] ?? '08:00:00');
                $insereFalta->execute([$fid, $marcadoEm, $ip, $ua, $obs]);
                $totalInseridas++;
            }

            $dt->modify('+1 day');
        }

        return [
            'processados' => count($funcs),
            'faltas_inseridas' => $totalInseridas,
            'erros' => $erros,
        ];
    }

    /**
     * Remove um período de férias previamente lançado.
     */
    public function removerFerias(int $funcId, string $de, string $ate): int {
        $st = Database::pdo()->prepare(
            "DELETE FROM registos WHERE funcionario_id = ? AND tipo = 'ferias'
             AND DATE(marcado_em) BETWEEN ? AND ?"
        );
        $st->execute([$funcId, $de, $ate]);
        return $st->rowCount();
    }

    /**
     * Conta dias de férias já marcados num ano (para validar limite anual).
     */
    public function contarFeriasAno(int $funcId, int $ano): int {
        $st = Database::pdo()->prepare(
            "SELECT COUNT(DISTINCT DATE(marcado_em)) FROM registos
             WHERE funcionario_id = ? AND tipo = 'ferias' AND YEAR(marcado_em) = ?"
        );
        $st->execute([$funcId, $ano]);
        return (int) $st->fetchColumn();
    }

    /**
     * Dias úteis (ou todos, conforme $incluirFds) num intervalo — usado para
     * validar limites antes de inserir.
     */
    public static function contarDiasNoIntervalo(string $de, string $ate, bool $incluirFds = false): int {
        $dt = new \DateTime($de);
        $fim = new \DateTime($ate);
        if ($dt > $fim) return 0;
        $n = 0;
        while ($dt <= $fim) {
            $dow = (int) $dt->format('N');
            if ($incluirFds || $dow < 6) $n++;
            $dt->modify('+1 day');
        }
        return $n;
    }

    /**
     * Valida se ainda há tempo suficiente no dia para marcar entrada.
     * Retorna null se ok, ou mensagem de erro se não houver tempo.
     */
    public function validarHorarioEntrada(string $horaSaida, float $cargaDiaria): ?string {
        $agora = time();
        $hoje = date('Y-m-d');
        $limiteSaida = strtotime($hoje . ' ' . $horaSaida);
        $horasRestantes = ($limiteSaida - $agora) / 3600;

        if ($horasRestantes < $cargaDiaria) {
            $horaActual = date('H:i');
            $saidaPrevista = date('H:i', strtotime($horaSaida));
            return "Sao {$horaActual}. Nao ha tempo suficiente para completar as {$cargaDiaria}h de trabalho (saida as {$saidaPrevista}).";
        }

        return null;
    }

    /**
     * Detecta conflitos: dias do intervalo onde o funcionário já tem
     * registos de entrada/saída (não-férias). Retorna lista de datas.
     */
    public function diasComRegistoNoIntervalo(int $funcId, string $de, string $ate): array {
        $st = Database::pdo()->prepare(
            "SELECT DISTINCT DATE(marcado_em) as dia FROM registos
             WHERE funcionario_id = ? AND tipo <> 'ferias'
               AND DATE(marcado_em) BETWEEN ? AND ?
             ORDER BY dia"
        );
        $st->execute([$funcId, $de, $ate]);
        return array_column($st->fetchAll(), 'dia');
    }

    /**
     * Agrupa as férias de um funcionário em períodos contínuos
     * (tolerando fim-de-semana entre dias úteis).
     * Retorna [['de','ate','dias','observacao'], ...]
     */
    public function listarPeriodosFerias(int $funcId): array {
        $st = Database::pdo()->prepare(
            "SELECT DATE(marcado_em) as dia, MAX(observacao) as obs
             FROM registos WHERE funcionario_id = ? AND tipo = 'ferias'
             GROUP BY DATE(marcado_em) ORDER BY dia"
        );
        $st->execute([$funcId]);
        $rows = $st->fetchAll();
        $periodos = [];
        $cur = null;
        foreach ($rows as $r) {
            $d = $r['dia'];
            if ($cur === null) {
                $cur = ['de' => $d, 'ate' => $d, 'dias' => 1, 'observacao' => $r['obs']];
                continue;
            }
            // Próximo dia esperado (tolerando saltar sáb/dom)
            $next = date('Y-m-d', strtotime($cur['ate'] . ' +1 day'));
            while ((int) date('N', strtotime($next)) >= 6 && $next < $d) {
                $next = date('Y-m-d', strtotime($next . ' +1 day'));
            }
            if ($d <= $next) {
                $cur['ate'] = $d;
                $cur['dias']++;
                if (!$cur['observacao'] && $r['obs']) $cur['observacao'] = $r['obs'];
            } else {
                $periodos[] = $cur;
                $cur = ['de' => $d, 'ate' => $d, 'dias' => 1, 'observacao' => $r['obs']];
            }
        }
        if ($cur) $periodos[] = $cur;
        return $periodos;
    }
}
