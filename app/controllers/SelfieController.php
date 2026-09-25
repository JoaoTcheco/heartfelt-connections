<?php
/**
 * ============================================================
 * FarmaPonto - SelfieController
 * ============================================================
 * Serve imagens de selfie protegidas (storage/ tem Deny from all).
 * Apenas admin/gestor ou o proprio funcionario podem ver.
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Auth;

final class SelfieController extends Controller {

    public function ver(string $id): void {
        $this->requireLogin();
        $id = (int) $id;

        $st = Database::pdo()->prepare("SELECT funcionario_id, caminho FROM selfies WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $s = $st->fetch();

        if (!$s) { http_response_code(404); echo 'Selfie nao encontrada'; return; }

        // Permissao: gestor/admin OU dono
        if (!Auth::isGestor() && (int)$s['funcionario_id'] !== Auth::id()) {
            http_response_code(403); echo 'Acesso negado'; return;
        }

        $path = __DIR__ . '/../../' . ltrim($s['caminho'], '/');
        if (!is_file($path)) { http_response_code(404); echo 'Ficheiro ausente'; return; }

        // A fotografia nunca muda depois de gravada: cache privada longa
        // com validacao por ETag evita reenviar a imagem a cada visita.
        $etag = '"' . md5($path . filemtime($path)) . '"';
        $modificado = gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT';
        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=604800, immutable');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . $modificado);

        $enviado = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($enviado === $etag || ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '') === $modificado) {
            http_response_code(304);
            exit;
        }

        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
