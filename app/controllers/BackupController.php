<?php
/**
 * ============================================================
 * FarmaPonto - BackupController
 * ============================================================
 * Responsabilidade: ecra e acoes das copias de seguranca
 * (criar, descarregar, apagar, restaurar) e agendamento.
 * Comunica com: Backup, Saude, Log, Registador.
 * Apenas perfil 'admin'.
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Registador;
use App\Helpers\Auth;
use App\Helpers\Backup;
use App\Helpers\Saude;
use App\Models\Log;
use Throwable;

final class BackupController extends Controller {

    public function index(): void {
        $this->requireRole('admin');
        $this->view('admin/backup', [
            'titulo'    => 'Cópias de Segurança',
            'meta_desc' => 'Criar, descarregar e restaurar cópias de segurança do FarmaPonto.',
            'copias'    => Backup::listar(),
            'saude'     => Saude::estado(),
            'raiz'      => ROOT_PATH,
        ]);
    }

    /** Cria uma copia .sql (base de dados). */
    public function criar(): void {
        $this->requireRole('admin');
        $this->checkCsrf();
        try {
            $r = Backup::criarFicheiroSql();
            Log::reg(Auth::id(), 'backup_criado', 'backups', null, ['ficheiro' => $r['ficheiro'], 'bytes' => $r['bytes']]);
            Registador::info('Cópia de segurança criada', ['ficheiro' => $r['ficheiro']], 'backup');
            $this->json(['ok' => true, 'ficheiro' => $r['ficheiro'], 'tamanho' => Backup::tamanho($r['bytes'])]);
        } catch (Throwable $e) {
            Registador::erro('Falha ao criar cópia de segurança: ' . $e->getMessage(), [], 'backup');
            $this->json(['ok' => false, 'erro' => 'Não foi possível criar a cópia porque o sistema não conseguiu escrever em storage/backups. Verifique o espaço em disco e as permissões da pasta.'], 500);
        }
    }

    /** Cria uma copia completa .zip (base de dados + fotografias). */
    public function criarCompleto(): void {
        $this->requireRole('admin');
        $this->checkCsrf();
        try {
            $r = Backup::criarZipCompleto();
            Log::reg(Auth::id(), 'backup_completo_criado', 'backups', null, ['ficheiro' => $r['ficheiro'], 'bytes' => $r['bytes']]);
            $this->json(['ok' => true, 'ficheiro' => $r['ficheiro'], 'tamanho' => Backup::tamanho($r['bytes'])]);
        } catch (Throwable $e) {
            Registador::erro('Falha na cópia completa: ' . $e->getMessage(), [], 'backup');
            $this->json(['ok' => false, 'erro' => $e->getMessage()], 500);
        }
    }

    /** Descarrega uma copia existente. */
    public function download(): void {
        $this->requireRole('admin');
        $nome = basename((string) ($_GET['f'] ?? ''));
        $caminho = Backup::pasta() . '/' . $nome;
        if ($nome === '' || !is_file($caminho)) {
            http_response_code(404);
            $this->view('public/erro', ['titulo' => 'Cópia não encontrada', 'mensagem' => 'Esta cópia já não existe na pasta storage/backups.']);
            return;
        }
        Log::reg(Auth::id(), 'backup_descarregado', 'backups', null, ['ficheiro' => $nome]);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nome . '"');
        header('Content-Length: ' . filesize($caminho));
        header('Cache-Control: no-store');
        readfile($caminho);
        exit;
    }

    public function apagar(): void {
        $this->requireRole('admin');
        $this->checkCsrf();
        $nome = basename((string) ($_POST['ficheiro'] ?? ''));
        if (!Backup::apagar($nome)) {
            $this->json(['ok' => false, 'erro' => 'Não foi possível apagar: o ficheiro já não existe.'], 404);
        }
        Log::reg(Auth::id(), 'backup_eliminado', 'backups', null, ['ficheiro' => $nome]);
        $this->json(['ok' => true]);
    }

    /**
     * Restaura a base de dados a partir de um ficheiro .sql ou .zip
     * enviado pelo utilizador, ou de uma copia ja existente na pasta.
     * Antes de escrever, guarda automaticamente o estado actual.
     */
    public function restaurar(): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        if (strtoupper((string) ($_POST['confirmacao'] ?? '')) !== 'RESTAURAR') {
            $this->json(['ok' => false, 'erro' => 'Para evitar perdas acidentais, escreva RESTAURAR na caixa de confirmação.'], 422);
        }

        try {
            $sql = $this->obterSql();
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'erro' => $e->getMessage()], 422);
            return;
        }

        try {
            $r = Backup::restaurarSql($sql);
            Registador::aviso('Base de dados restaurada a partir de cópia', $r, 'backup');
            Log::reg(Auth::id(), 'backup_restaurado', 'backups', null, $r);
            $this->json([
                'ok' => true,
                'comandos' => $r['comandos'],
                'antes' => $r['antes'],
                'aviso' => 'A sessão vai terminar para recarregar os dados restaurados.',
            ]);
        } catch (Throwable $e) {
            Registador::erro('Falha ao restaurar: ' . $e->getMessage(), [], 'backup');
            $this->json(['ok' => false, 'erro' => $e->getMessage()], 500);
        }
    }

    /** Obtem o SQL a restaurar (upload .sql/.zip ou copia local). */
    private function obterSql(): string {
        $enviado = $_FILES['ficheiro'] ?? null;
        if ($enviado && ($enviado['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) $enviado['tmp_name'];
            $nome = strtolower((string) $enviado['name']);
            if (str_ends_with($nome, '.zip')) {
                return $this->sqlDoZip($tmp);
            }
            $sql = (string) file_get_contents($tmp);
            if (stripos($sql, 'CREATE TABLE') === false && stripos($sql, 'INSERT INTO') === false) {
                throw new \RuntimeException('O ficheiro enviado não parece ser uma cópia do FarmaPonto (não contém tabelas nem dados). Envie um ficheiro .sql ou .zip gerado por este ecrã.');
            }
            return $sql;
        }

        $local = basename((string) ($_POST['copia'] ?? ''));
        if ($local !== '') {
            $caminho = Backup::pasta() . '/' . $local;
            if (!is_file($caminho)) {
                throw new \RuntimeException('A cópia escolhida já não existe na pasta storage/backups.');
            }
            return str_ends_with($local, '.zip')
                ? $this->sqlDoZip($caminho)
                : (string) file_get_contents($caminho);
        }

        throw new \RuntimeException('Escolha um ficheiro de cópia (.sql ou .zip) ou uma das cópias já guardadas.');
    }

    private function sqlDoZip(string $caminho): string {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('A extensão ZipArchive do PHP não está activa; envie o ficheiro .sql em vez do .zip.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($caminho) !== true) {
            throw new \RuntimeException('Não foi possível abrir o ficheiro .zip (pode estar incompleto).');
        }
        $sql = $zip->getFromName('base-de-dados.sql');
        $zip->close();
        if ($sql === false) {
            throw new \RuntimeException('O .zip não contém base-de-dados.sql. Use um .zip criado por este ecrã.');
        }
        return $sql;
    }
}
