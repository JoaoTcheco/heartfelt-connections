<?php
/**
 * ============================================================
 * FarmaPonto - DigitaisController
 * ============================================================
 * Gestao das impressoes digitais de cada funcionario (max. 3)
 * e identificacao no terminal de marcacao rapida.
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\RateLimit;
use App\Models\Config;
use App\Models\Digital;
use App\Models\Funcionario;
use App\Models\Log;
use App\Models\Registo;

final class DigitaisController extends Controller {

    /** GET /funcionarios/digitais/{id} - lista dedos registados. */
    public function listar(int $id): void {
        $this->requireRole('admin', 'gestor');
        $d = new Digital();
        $this->json([
            'ok'        => true,
            'max'       => Digital::MAX_DEDOS,
            'slotLivre' => $d->slotLivre($id),
            'digitais'  => $d->listar($id),
        ]);
    }

    /** POST /funcionarios/digitais/{id} - regista/substitui um dedo. */
    public function guardar(int $id): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $func = (new Funcionario())->porId($id);
        if (!$func) { $this->json(['ok' => false, 'erro' => 'Funcionário não encontrado.'], 404); }

        $template = trim((string) ($_POST['template'] ?? ''));
        $dedo     = trim((string) ($_POST['dedo'] ?? 'Indicador direito'));
        $origem   = ($_POST['origem'] ?? 'leitor') === 'dispositivo' ? 'dispositivo' : 'leitor';
        $slot     = (int) ($_POST['slot'] ?? 0);

        if (strlen($template) < 8) {
            $this->json(['ok' => false, 'erro' => 'Leitura inválida. Coloque o dedo no leitor até a leitura terminar.'], 422);
        }
        if ($dedo === '' || mb_strlen($dedo) > 40) { $dedo = 'Indicador direito'; }

        $d = new Digital();

        // O mesmo dedo nao pode pertencer a duas pessoas.
        $dono = $d->porTemplate($template);
        if ($dono && (int) $dono['id'] !== $id) {
            $this->json(['ok' => false, 'erro' => 'Esta impressão digital já está registada em ' . $dono['nome'] . '.'], 409);
        }
        if ($dono && (int) $dono['id'] === $id && (int) $dono['slot'] !== $slot) {
            $this->json(['ok' => false, 'erro' => 'Este dedo já está registado neste funcionário (' . $dono['dedo'] . ').'], 409);
        }

        if ($slot < 1 || $slot > Digital::MAX_DEDOS) {
            $slot = $d->slotLivre($id) ?? 0;
        }
        if ($slot < 1) {
            $this->json(['ok' => false, 'erro' => 'Limite de ' . Digital::MAX_DEDOS . ' dedos atingido. Remova um antes de adicionar outro.'], 422);
        }

        $digId = $d->guardar($id, $slot, $dedo, $template, $origem);
        Log::reg(Auth::id(), 'digital_registada', 'funcionario_digitais', $digId, ['funcionario_id' => $id, 'slot' => $slot, 'dedo' => $dedo, 'origem' => $origem]);

        $this->json(['ok' => true, 'id' => $digId, 'slot' => $slot, 'digitais' => $d->listar($id), 'slotLivre' => $d->slotLivre($id)]);
    }

    /** POST /funcionarios/digitais/remover/{id} - {id} = id da digital. */
    public function remover(int $id): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $d = new Digital();
        $funcId = $d->remover($id);
        if ($funcId === null) { $this->json(['ok' => false, 'erro' => 'Registo não encontrado.'], 404); }

        Log::reg(Auth::id(), 'digital_removida', 'funcionario_digitais', $id, ['funcionario_id' => $funcId]);
        $this->json(['ok' => true, 'digitais' => $d->listar($funcId), 'slotLivre' => $d->slotLivre($funcId)]);
    }

    /**
     * POST /quickpunch/digital
     * Identifica pelo template e marca entrada/saida automaticamente.
     */
    public function punch(): void {
        $this->checkCsrf();

        $cfgMax = (int) (new Config())->get('rate_limit_quickpunch', '10');
        if (!RateLimit::attempt('quickpunch_digital', max(1, $cfgMax), 60)) {
            $this->json(['ok' => false, 'erro' => 'Demasiadas leituras. Aguarde 1 minuto.'], 429);
        }

        $template = trim((string) ($_POST['template'] ?? ''));
        if (strlen($template) < 8) {
            $this->json(['ok' => false, 'erro' => 'Leitura inválida. Tente novamente.'], 400);
        }

        $d = new Digital();
        $reg = $d->porTemplate($template);
        if (!$reg) {
            Log::reg(null, 'digital_nao_reconhecida');
            $this->json(['ok' => false, 'erro' => 'Impressão digital não reconhecida. Use outro dedo ou o PIN.'], 401);
        }
        if (!(int) $reg['ativo']) {
            $this->json(['ok' => false, 'erro' => 'Funcionário inativo. Contacte a gestão.'], 403);
        }

        $funcId = (int) $reg['id'];
        (new Registo())->processarFaltas(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'), $funcId);

        $tipo = Registo::proximoTipo($funcId);
        $erro = (new Registo())->validarTransicao($funcId, $tipo);
        if ($erro) { $this->json(['ok' => false, 'erro' => $erro], 400); }

        $regId = (new Registo())->registar($funcId, $tipo, null, 'Marcação por impressão digital (' . $reg['dedo'] . ')', \App\Helpers\Metodo::DIGITAL, $reg['dedo']);
        $d->marcarUso((int) $reg['digital_id']);
        Log::reg($funcId, 'digital_' . $tipo, 'registos', $regId, ['dedo' => $reg['dedo'], 'slot' => $reg['slot']]);

        $this->json([
            'ok'       => true,
            'tipo'     => $tipo,
            'nome'     => $reg['nome'],
            'codigo'   => $reg['codigo'],
            'dedo'     => $reg['dedo'],
            'hora'     => date('H:i:s'),
            'mensagem' => $tipo === 'entrada'
                ? 'Ponto de entrada marcado com sucesso'
                : 'Ponto de saída marcado com sucesso',
        ]);
    }
}
