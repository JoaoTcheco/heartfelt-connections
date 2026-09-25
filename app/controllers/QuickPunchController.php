<?php
/**
 * ============================================================
 * FarmaPonto - QuickPunchController (Marcacao Rapida)
 * ============================================================
 * Terminal de marcacao rapida (kiosk) - sem login.
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\RateLimit;
use App\Models\Config;
use App\Models\Funcionario;
use App\Models\Registo;
use App\Models\Selfie;
use App\Models\Log;

final class QuickPunchController extends Controller {

    public function index(): void {
        // Terminal kiosk: nunca mostra o menu lateral, mesmo com sessao aberta.
        $this->view('public/quickpunch', ['titulo' => 'Marcacao Rapida', 'kiosk' => true]);
    }


    /**
     * Identifica o funcionario no terminal.
     * Com codigo: valida o par codigo+PIN. Sem codigo: usa o PIN, mas exige o
     * codigo quando mais do que um funcionario partilha o mesmo PIN.
     */
    private function identificar(string $codigo, string $pin): array {
        $modelo = new Funcionario();
        if ($codigo !== '') {
            $func = $modelo->porCodigoPin($codigo, $pin);
            if (!$func) {
                return [null, 'Codigo ou PIN invalido', 401];
            }
            return [$func, null, 200];
        }
        $candidatos = $modelo->todosPorPin($pin);
        if (count($candidatos) === 0) {
            return [null, 'PIN invalido', 401];
        }
        if (count($candidatos) > 1) {
            return [null, 'Este PIN pertence a mais do que um funcionario. Indique tambem o seu codigo.', 409];
        }
        return [$candidatos[0], null, 200];
    }

    public function verificarPin(): void {
        $this->checkCsrf();

        $cfgMax = (int) (new Config())->get('rate_limit_quickpunch', '8');
        if (!RateLimit::attempt('quickpunch_pin', $cfgMax, 60)) {
            $this->json(['ok' => false, 'erro' => 'Demasiadas tentativas. Aguarde 1 minuto.'], 429);
        }

        $pin = $_POST['pin'] ?? '';
        if (!preg_match('/^\d{4}$/', $pin)) {
            $this->json(['ok' => false, 'erro' => 'PIN deve ter exatamente 4 dígitos numéricos.'], 400);
        }

        [$func, $erroId, $status] = $this->identificar(trim((string) ($_POST['codigo'] ?? '')), $pin);
        if (!$func) {
            Log::reg(null, 'quickpunch_pin_invalido');
            $this->json(['ok' => false, 'erro' => $erroId, 'pedir_codigo' => $status === 409], $status);
        }

        $tipo = Registo::proximoTipo((int) $func['id']);
        $this->json(['ok' => true, 'codigo' => $func['codigo'], 'nome' => $func['nome'], 'tipo' => $tipo]);
    }

    public function punch(): void {
        $this->checkCsrf();

        $cfgMax = (int) (new Config())->get('rate_limit_quickpunch', '8');
        if (!RateLimit::attempt('quickpunch_punch', $cfgMax, 60)) {
            $this->json(['ok' => false, 'erro' => 'Demasiadas marcacoes. Aguarde 1 minuto.'], 429);
        }

        $pin = $_POST['pin'] ?? '';

        [$func, $erroId, $status] = $this->identificar(trim((string) ($_POST['codigo'] ?? '')), $pin);
        if (!$func) {
            $this->json(['ok' => false, 'erro' => $erroId, 'pedir_codigo' => $status === 409], $status);
        }

        (new Registo())->processarFaltas(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'), (int) $func['id']);

        $tipo = Registo::proximoTipo((int) $func['id']);

        $erro = (new Registo())->validarTransicao((int) $func['id'], $tipo);
        if ($erro) {
            $this->json(['ok' => false, 'erro' => $erro], 400);
        }

        $selfieId = null;
        if (in_array($tipo, ['entrada', 'saida'], true)) {
            try {
                $selfieId = (new Selfie())->gravarBase64((int) $func['id'], $_POST['selfie_b64'] ?? '');
            } catch (\Throwable $e) {
                error_log('[QuickPunch selfie] ' . $e->getMessage());
            }
        }

        $regId = (new Registo())->registar((int) $func['id'], $tipo, $selfieId, null, \App\Helpers\Metodo::PIN);
        Log::reg((int) $func['id'], 'quickpunch_' . $tipo, 'registos', $regId);

        $this->json(['ok' => true, 'tipo' => $tipo, 'nome' => $func['nome'], 'hora' => date('H:i:s')]);
    }
}
