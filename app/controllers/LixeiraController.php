<?php
/**
 * ============================================================
 * FarmaPonto - LixeiraController
 * ============================================================
 * Responsabilidade: ver, repor e eliminar definitivamente o que foi
 * apagado (registos de ponto, folhas salariais, funcionários).
 * Comunica com: Lixeira, Log, Registador.
 * Perfis: admin e gestor (gestor não elimina definitivamente).
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Registador;
use App\Helpers\Auth;
use App\Models\Lixeira;
use App\Models\Log;
use Throwable;

final class LixeiraController extends Controller {

    public function index(): void {
        $this->requireRole('admin', 'gestor');
        $tabela = (string) ($_GET['tabela'] ?? '');
        $lixeira = new Lixeira();
        $this->view('admin/lixeira', [
            'titulo'     => 'Lixeira',
            'meta_desc'  => 'Reponha registos de ponto, folhas salariais ou funcionários eliminados por engano.',
            'itens'      => $lixeira->listar($tabela),
            'contagens'  => $lixeira->contagens(),
            'tabela'     => $tabela,
        ]);
    }

    public function recuperar(): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $r = (new Lixeira())->recuperar($id);
            Log::reg(Auth::id(), 'lixeira_recuperado', $r['tabela'], $r['id'], ['lixeira_id' => $id]);
            Registador::info('Item recuperado da lixeira', $r, 'lixeira');
            $this->json(['ok' => true] + $r);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }
    }

    public function eliminarDefinitivo(): void {
        $this->requireRole('admin');
        $this->checkCsrf();
        if (strtoupper((string) ($_POST['confirmacao'] ?? '')) !== 'ELIMINAR') {
            $this->json(['ok' => false, 'erro' => 'Para eliminar para sempre, escreva ELIMINAR na confirmação.'], 422);
        }
        $id = (int) ($_POST['id'] ?? 0);
        if (!(new Lixeira())->eliminarDefinitivo($id)) {
            $this->json(['ok' => false, 'erro' => 'Este item já não está na lixeira.'], 404);
        }
        Log::reg(Auth::id(), 'lixeira_eliminado_definitivo', 'lixeira', $id);
        $this->json(['ok' => true]);
    }
}
