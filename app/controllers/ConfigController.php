<?php
/**
 * ============================================================
 * FarmaPonto - ConfigController
 * ============================================================
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Models\Config;
use App\Models\Log;

final class ConfigController extends Controller {

    public function index(): void {
        $this->requireRole('admin');
        $config = new Config();
        $this->view('admin/config', [
            'titulo' => 'Configuracoes',
            'configs' => $config->todas(),
        ]);
    }

    public function salvar(): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $config = new Config();
        $campos = [
            'nome_empresa', 'moeda',
            'tolerancia_atraso_min',
            // Seguranca / privacidade
            'bloqueio_tentativas', 'selfies_retencao_meses', 'logs_retencao_dias',
            // Férias
            'ferias_max_dias_ano', 'ferias_min_aviso_dias', 'ferias_permitir_passado',
            // Institucional
            'instituicao', 'relatorio_nota_rodape',
        ];

        foreach ($campos as $c) {
            if (isset($_POST[$c])) {
                $config->set($c, $_POST[$c]);
            }
        }

        Log::reg(Auth::id(), 'config_atualizada', 'config');
        $this->json(['ok' => true]);
    }

    /** Purga manual das selfies mais antigas do que a retencao configurada. */
    public function limparSelfies(): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $meses = (int) ($_POST['meses'] ?? (new Config())->get('selfies_retencao_meses', '0'));
        if ($meses <= 0) {
            $this->json(['ok' => false, 'erro' => 'Defina primeiro um período de retenção (em meses).'], 422);
        }
        $n = (new \App\Models\Selfie())->limparAntigas($meses);
        Log::reg(Auth::id(), 'selfies_purgadas', 'selfies', null, ['removidas' => $n, 'retencao_meses' => $meses]);
        $this->json(['ok' => true, 'removidas' => $n]);
    }

    /**
     * Upload de logotipo da instituição.
     * Aceita PNG/JPG/SVG/WEBP até 2 MB. Guarda em storage/uploads/logo.<ext>
     * Limpa logos anteriores com extensão diferente.
     */
    public function uploadLogo(): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        if (empty($_FILES['logo']) || ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'erro' => 'Nenhum ficheiro recebido.'], 400);
        }
        $tmp  = $_FILES['logo']['tmp_name'];
        $size = (int) $_FILES['logo']['size'];
        if ($size > 2 * 1024 * 1024) {
            $this->json(['ok' => false, 'erro' => 'Logo excede 2 MB.'], 422);
        }
        $mimeMap = [
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
        ];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp) ?: $_FILES['logo']['type'] ?? '';
        if (!isset($mimeMap[$mime])) {
            $this->json(['ok' => false, 'erro' => 'Formato inválido. Use PNG, JPG, WEBP ou SVG.'], 422);
        }
        $ext = $mimeMap[$mime];

        $dir = __DIR__ . '/../../storage/uploads';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        // limpar logos anteriores com extensões diferentes
        foreach (glob($dir . '/logo.*') ?: [] as $old) @unlink($old);

        $rel = 'storage/uploads/logo.' . $ext;
        $abs = __DIR__ . '/../../' . $rel;
        if (!move_uploaded_file($tmp, $abs)) {
            $this->json(['ok' => false, 'erro' => 'Falha a guardar ficheiro.'], 500);
        }
        @chmod($abs, 0644);

        (new Config())->set('instituicao_logo', $rel);
        Log::reg(Auth::id(), 'logo_atualizado', 'config', null, ['ficheiro' => $rel, 'bytes' => $size]);
        $this->json(['ok' => true, 'caminho' => $rel]);
    }

    public function removerLogo(): void {
        $this->requireRole('admin');
        $this->checkCsrf();
        $cfg = new Config();
        $rel = $cfg->get('instituicao_logo', '');
        if ($rel) {
            $abs = __DIR__ . '/../../' . $rel;
            if (is_file($abs)) @unlink($abs);
        }
        $cfg->set('instituicao_logo', '');
        Log::reg(Auth::id(), 'logo_removido', 'config');
        $this->json(['ok' => true]);
    }

    /**
     * Stream do logotipo (público). storage/ está bloqueado por .htaccess,
     * portanto servimos via PHP — como SelfieController faz com as selfies.
     */
    public function serveLogo(): void {
        $cfg = new Config();
        $rel = $cfg->get('instituicao_logo', '');
        $abs = $rel ? (__DIR__ . '/../../' . $rel) : '';
        if (!$rel || !is_file($abs)) {
            http_response_code(404);
            exit;
        }
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = [
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
        ][$ext] ?? 'application/octet-stream';
        $mtime = filemtime($abs) ?: time();
        // 304 se cliente já tem
        $ifMod = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
        if ($ifMod && strtotime($ifMod) >= $mtime) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        header('Cache-Control: public, max-age=300, must-revalidate');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        readfile($abs);
        exit;
    }
}