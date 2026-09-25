<?php
/**
 * ============================================================
 * FarmaPonto - AuthController
 * ============================================================
 * Login, logout, auto-registo e recuperação de password.
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Helpers\Mailer;
use App\Helpers\RateLimit;
use App\Models\Config;
use App\Models\Funcionario;
use App\Models\Log;
use App\Models\PasswordReset;

final class AuthController extends Controller {

    public function root(): void {
        if (Auth::user()) {
            $this->redirect(BASE_PATH . '/dashboard');
        }
        $this->redirect(BASE_PATH . '/login');
    }

    public function loginForm(): void {
        if (Auth::user()) {
            $this->redirect(BASE_PATH . '/dashboard');
        }
        $autoRegisto = (new Config())->get('auto_registo_aberto', '0') === '1';
        $this->view('public/login', [
            'titulo' => 'Entrar',
            'auto_registo' => $autoRegisto,
        ]);
    }

    public function login(): void {
        $this->checkCsrf();
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';

        // Rate limit: 5 tentativas/min por IP
        $cfgMax = (int) (new Config())->get('rate_limit_login', '5');
        if (!RateLimit::attempt('login', $cfgMax, 60)) {
            Log::reg(null, 'login_rate_limited', 'funcionarios', null, ['email' => $email]);
            $this->view('public/login', [
                'titulo' => 'Entrar',
                'erro' => 'Demasiadas tentativas. Tente novamente em 1 minuto.',
                'auto_registo' => (new Config())->get('auto_registo_aberto', '0') === '1',
            ]);
            return;
        }

        if (Auth::login($email, $pass)) {
            RateLimit::limpar('login'); // sucesso: nao penalizar o utilizador legitimo
            Log::reg(Auth::id(), 'login', 'funcionarios', Auth::id());
            // Voltar para a pagina que o utilizador tentou abrir (apenas caminhos internos)
            $redir = (string) ($_POST['redir'] ?? '');
            if ($redir !== '' && preg_match('#^/[A-Za-z0-9_\-/?&=.%]*$#', $redir) && !str_contains($redir, '//')) {
                $this->redirect($redir);
            }
            $this->redirect(BASE_PATH . '/dashboard');
        }

        Log::reg(null, 'login_falhou', 'funcionarios', null, ['email' => $email]);
        $this->view('public/login', [
            'titulo' => 'Entrar',
            'erro' => 'Credenciais invalidas.',
            'auto_registo' => (new Config())->get('auto_registo_aberto', '0') === '1',
        ]);
    }

    public function logout(): void {
        $uid = Auth::id();
        Log::reg($uid, 'logout');
        Auth::logout();
        $this->redirect(BASE_PATH . '/login');
    }

    // ---------- AUTO-REGISTO ----------

    public function registerForm(): void {
        if (Auth::user()) { $this->redirect(BASE_PATH . '/dashboard'); }
        if ((new Config())->get('auto_registo_aberto', '0') !== '1') {
            $this->view('public/erro', ['titulo' => 'Indisponivel', 'mensagem' => 'O auto-registo esta desactivado. Contacte o administrador.']);
            return;
        }
        $this->view('public/register', ['titulo' => 'Criar conta']);
    }

    public function register(): void {
        $this->checkCsrf();
        if ((new Config())->get('auto_registo_aberto', '0') !== '1') {
            $this->json(['ok' => false, 'erro' => 'Auto-registo desactivado'], 403);
        }
        if (!RateLimit::attempt('register', 3, 600)) {
            $this->json(['ok' => false, 'erro' => 'Demasiadas tentativas. Tente mais tarde.'], 429);
        }

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';
        $pin = $_POST['pin'] ?? '';

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6 || !preg_match('/^\d{4,8}$/', $pin)) {
            $this->json(['ok' => false, 'erro' => 'Dados invalidos. Verifique nome, email, password (6+) e PIN (4-8 digitos).'], 422);
        }

        $fmod = new Funcionario();
        if ($fmod->porEmail($email)) {
            $this->json(['ok' => false, 'erro' => 'Email ja registado.'], 409);
        }

        // Código auto-gerado
        $codigo = 'F' . str_pad((string) random_int(1000, 99999), 5, '0', STR_PAD_LEFT);

        $id = $fmod->criar([
            'codigo'   => $codigo,
            'nome'     => $nome,
            'email'    => $email,
            'cargo'    => 'Funcionario',
            'password' => $pass,
            'pin'      => $pin,
            'perfil'   => 'funcionario',
            'salario_base' => 0,
            'carga_diaria' => 8,
            'hora_entrada' => '08:00:00',
            'hora_saida'   => '17:00:00',
        ]);

        Log::reg($id, 'auto_registo', 'funcionarios', $id, ['email' => $email]);
        Auth::login($email, $pass);
        $this->json(['ok' => true, 'redirect' => BASE_PATH . '/dashboard']);
    }

    // ---------- RECUPERAÇÃO DE PASSWORD ----------

    public function forgotForm(): void {
        $this->view('public/forgot', ['titulo' => 'Recuperar palavra-passe']);
    }

    public function forgot(): void {
        $this->checkCsrf();
        $email = trim($_POST['email'] ?? '');

        if (!RateLimit::attempt('forgot', 3, 600)) {
            $this->json(['ok' => false, 'erro' => 'Demasiadas tentativas. Tente mais tarde.'], 429);
        }

        $func = (new Funcionario())->porEmail($email);
        // Sempre responde ok (não revela se o email existe)
        if ($func) {
            $token = (new PasswordReset())->criar((int) $func['id'], 60);
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $link = $proto . '://' . $host . BASE_PATH . '/reset-password?token=' . $token;

            $html = "<p>Olá, " . htmlspecialchars($func['nome']) . ",</p>"
                  . "<p>Recebemos um pedido de recuperação de palavra-passe para a sua conta FarmaPonto.</p>"
                  . "<p><a href=\"$link\" style=\"background:#047857;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none\">Definir nova palavra-passe</a></p>"
                  . "<p>Ou copie o link: <br><code>$link</code></p>"
                  . "<p>O link é válido por 60 minutos. Se não foi você, ignore este email.</p>";

            Mailer::send($email, 'FarmaPonto — Recuperar palavra-passe', $html);
            Log::reg((int) $func['id'], 'password_reset_pedido', 'funcionarios', (int) $func['id']);
        }
        $this->json(['ok' => true, 'msg' => 'Se o email existir, receberá instruções.']);
    }

    public function resetForm(): void {
        $token = $_GET['token'] ?? '';
        $row = (new PasswordReset())->valido($token);
        if (!$row) {
            $this->view('public/erro', ['titulo' => 'Link invalido', 'mensagem' => 'Este link de recuperação é inválido ou expirou.']);
            return;
        }
        $this->view('public/reset', ['titulo' => 'Nova palavra-passe', 'token' => $token]);
    }

    public function reset(): void {
        $this->checkCsrf();
        $token = $_POST['token'] ?? '';
        $nova = $_POST['password'] ?? '';

        if (strlen($nova) < 6) {
            $this->json(['ok' => false, 'erro' => 'Palavra-passe deve ter pelo menos 6 caracteres.'], 422);
        }

        $pr = new PasswordReset();
        $row = $pr->valido($token);
        if (!$row) {
            $this->json(['ok' => false, 'erro' => 'Link invalido ou expirado.'], 400);
        }

        (new Funcionario())->alterarPassword((int) $row['funcionario_id'], $nova);
        $pr->marcarUsado((int) $row['id']);
        Log::reg((int) $row['funcionario_id'], 'password_reset_concluido', 'funcionarios', (int) $row['funcionario_id']);

        $this->json(['ok' => true, 'redirect' => BASE_PATH . '/login']);
    }
}
