<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Models\Registo;
use App\Models\Selfie;
use App\Models\Log;

final class MarcarController extends Controller {

    public function index(): void {
        $this->requireLogin();
        $this->requireMarcaPonto();
        $prox = Registo::proximoTipo(Auth::id());
        $this->view('admin/marcar', [
            'titulo' => 'Marcar Presenca',
            'proximo' => $prox,
        ]);
    }

    /**
     * Bloqueia utilizadores administrativos (marca_ponto = 0): nao pertencem a
     * assiduidade, logo nao podem criar registos de ponto.
     */
    private function requireMarcaPonto(): void {
        if (Auth::marcaPonto()) { return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['ok' => false, 'erro' => 'Este utilizador e administrativo e nao marca ponto.'], 403);
        }
        http_response_code(403);
        $this->view('public/erro', [
            'titulo'    => 'Sem marcacao de ponto',
            'mensagem'  => 'Este utilizador e administrativo e nao marca ponto. Só colaboradores com "Marca ponto" activo registam entradas e saidas.',
        ]);
        exit;
    }

    /** POST /marcar/registar */
    public function registar(): void {
        $this->requireLogin();
        $this->requireMarcaPonto();
        $this->checkCsrf();

        $tipo = $_POST['tipo'] ?? '';
        if (!in_array($tipo, ['entrada', 'saida'], true)) {
            $this->json(['ok' => false, 'erro' => 'Tipo invalido'], 400);
        }

        $erro = (new Registo())->validarTransicao(Auth::id(), $tipo);
        if ($erro) {
            $this->json(['ok' => false, 'erro' => $erro], 400);
        }

        (new Registo())->processarFaltas(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'), Auth::id());

        $obs = $_POST['observacao'] ?? null;
        $selfieId = null;

        // A selfie e OPCIONAL: computadores sem camera (ou com camera recusada)
        // nao podem ficar impedidos de marcar o ponto.
        $b64 = trim((string) ($_POST['selfie_b64'] ?? ''));
        if ($b64 !== '') {
            try {
                $selfieId = (new Selfie())->gravarBase64(Auth::id(), $b64);
            } catch (\Throwable $e) {
                // Foto invalida nao invalida a marcacao: regista-se sem foto.
                $selfieId = null;
                Log::reg(Auth::id(), 'selfie_falhou', 'registos', null, ['erro' => $e->getMessage()]);
            }
        }

        $id = (new Registo())->registar(Auth::id(), $tipo, $selfieId, $obs, \App\Helpers\Metodo::PAINEL);
        Log::reg(Auth::id(), 'registo', 'registos', $id, ['tipo' => $tipo]);
        $this->json(['ok' => true, 'id' => $id]);
    }
}
