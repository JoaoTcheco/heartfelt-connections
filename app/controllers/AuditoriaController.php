<?php
/**
 * ============================================================
 * FarmaPonto - AuditoriaController
 * ============================================================
 * Vista enriquecida sobre logs do sistema (read-only).
 * Filtros: período, funcionário, acção, entidade, IP, texto livre.
 * Apenas perfis admin/gestor podem consultar.
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Funcionario;

final class AuditoriaController extends Controller {

    public function index(): void {
        $this->requireRole('admin', 'gestor');

        $de       = $_GET['de']  ?? date('Y-m-01');
        $ate      = $_GET['ate'] ?? date('Y-m-d');
        $funcId   = !empty($_GET['funcionario']) ? (int) $_GET['funcionario'] : null;
        $acao     = trim($_GET['acao'] ?? '') ?: null;
        $entidade = trim($_GET['entidade'] ?? '') ?: null;
        $ip       = trim($_GET['ip'] ?? '') ?: null;
        $q        = trim($_GET['q'] ?? '') ?: null;

        $where = [];
        $params = [];
        if ($de)       { $where[] = 'DATE(l.criado_em) >= ?'; $params[] = $de; }
        if ($ate)      { $where[] = 'DATE(l.criado_em) <= ?'; $params[] = $ate; }
        if ($funcId)   { $where[] = 'l.funcionario_id = ?';   $params[] = $funcId; }
        if ($acao)     { $where[] = 'l.acao = ?';             $params[] = $acao; }
        if ($entidade) { $where[] = 'l.entidade = ?';         $params[] = $entidade; }
        if ($ip)       { $where[] = 'INET6_NTOA(l.ip) LIKE ?'; $params[] = "%$ip%"; }
        if ($q) {
            $where[] = '(f.nome LIKE ? OR l.acao LIKE ? OR l.entidade LIKE ? OR l.detalhes LIKE ?)';
            $w = "%$q%";
            $params = array_merge($params, [$w, $w, $w, $w]);
        }

        $sql = "SELECT l.id, l.criado_em, l.acao, l.entidade, l.entidade_id, l.detalhes,
                       INET6_NTOA(l.ip) AS ip_addr,
                       l.funcionario_id, f.nome AS funcionario_nome, f.perfil
                FROM logs l
                LEFT JOIN funcionarios f ON f.id = l.funcionario_id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY l.id DESC LIMIT 2000';
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        $eventos = $st->fetchAll();

        // Estatísticas rápidas (no conjunto filtrado)
        $stats = [
            'total'           => count($eventos),
            'utilizadores'    => count(array_unique(array_filter(array_column($eventos, 'funcionario_id')))),
            'acoes_distintas' => count(array_unique(array_column($eventos, 'acao'))),
            'ips'             => count(array_unique(array_filter(array_column($eventos, 'ip_addr')))),
        ];

        // Listas para os <select>
        $acoes = Database::pdo()
            ->query("SELECT DISTINCT acao FROM logs WHERE acao IS NOT NULL AND acao != '' ORDER BY acao")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $entidades = Database::pdo()
            ->query("SELECT DISTINCT entidade FROM logs WHERE entidade IS NOT NULL AND entidade != '' ORDER BY entidade")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $funcionarios = (new Funcionario())->listarAtivos();

        $this->view('admin/auditoria', [
            'titulo'       => 'Auditoria',
            'eventos'      => $eventos,
            'stats'        => $stats,
            'de'           => $de,
            'ate'          => $ate,
            'funcionario'  => $funcId,
            'acao'         => $acao,
            'entidade'     => $entidade,
            'ip'           => $ip,
            'q'            => $q,
            'acoes'        => $acoes,
            'entidades'    => $entidades,
            'funcionarios' => $funcionarios,
        ]);
    }

    public function detalhe(int $id): void {
        $this->requireRole('admin', 'gestor');
        $st = Database::pdo()->prepare(
            "SELECT l.*, INET6_NTOA(l.ip) AS ip_addr, f.nome AS funcionario_nome, f.perfil
             FROM logs l LEFT JOIN funcionarios f ON f.id = l.funcionario_id
             WHERE l.id = ?"
        );
        $st->execute([$id]);
        $ev = $st->fetch();
        if (!$ev) $this->json(['ok' => false, 'erro' => 'Evento não encontrado.'], 404);
        if (!empty($ev['detalhes'])) {
            $dec = json_decode($ev['detalhes'], true);
            if ($dec !== null) $ev['detalhes_obj'] = $dec;
        }
        $this->json(['ok' => true, 'evento' => $ev]);
    }
}
