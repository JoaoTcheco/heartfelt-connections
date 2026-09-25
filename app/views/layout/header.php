<?php
use App\Helpers\Auth;
use App\Helpers\Csrf;

$user = Auth::user();
$perfil = $user['perfil'] ?? '';
// Quem nao marca ponto (utilizadores administrativos) nao ve os ecras de ponto.
$marcaPonto = $user ? Auth::marcaPonto() : false;
$nome = $user['nome'] ?? '';
$iniciais = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), explode(' ', $nome, 2)));

// Determinar pagina atual para menu ativo
$uri = $_SERVER['REQUEST_URI'] ?? '';
$current = basename(parse_url($uri, PHP_URL_PATH));
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'FarmaPonto') ?> · FarmaPonto</title>
    <meta name="description" content="<?= htmlspecialchars($meta_desc ?? 'FarmaPonto — Sistema de gestão de assiduidade para farmácias em Moçambique. Marcação de ponto, controlo de atrasos, faltas e cortes salariais.') ?>">
    <meta name="theme-color" content="#047857">
    <meta name="robots" content="index,follow">
    <!-- PWA meta (iOS/Android) -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FarmaPonto">
    <meta name="application-name" content="FarmaPonto">
    <meta name="msapplication-TileColor" content="#047857">
    <meta name="msapplication-tap-highlight" content="no">
    <link rel="canonical" href="<?= htmlspecialchars(($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">

    <!-- Open Graph / Twitter -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="FarmaPonto">
    <meta property="og:title" content="<?= htmlspecialchars($titulo ?? 'FarmaPonto') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_desc ?? 'Gestão de assiduidade para farmácias.') ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($titulo ?? 'FarmaPonto') ?>">

    <!-- PWA -->
    <link rel="manifest" href="<?= BASE_PATH ?>/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%23047857'/><text x='50%25' y='54%25' font-size='38' font-family='Arial' font-weight='bold' fill='white' text-anchor='middle'>F</text></svg>">
    <link rel="apple-touch-icon" href="<?= BASE_PATH ?>/assets/images/icon-192.png">
    <link rel="apple-touch-icon" sizes="192x192" href="<?= BASE_PATH ?>/assets/images/icon-192.png">
    <link rel="apple-touch-icon" sizes="512x512" href="<?= BASE_PATH ?>/assets/images/icon-512.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= BASE_PATH ?>/assets/images/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= BASE_PATH ?>/assets/images/icon-512.png">
    <script>
      // Registo do Service Worker (PWA)
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('<?= BASE_PATH ?>/sw.js', { scope: '<?= BASE_PATH ?>/' })
            .then(function (reg) {
              // Verificar updates a cada 1h
              setInterval(function () { reg.update().catch(function(){}); }, 60 * 60 * 1000);
              if (reg.waiting) reg.waiting.postMessage('SKIP_WAITING');
              reg.addEventListener('updatefound', function () {
                var nw = reg.installing;
                if (!nw) return;
                nw.addEventListener('statechange', function () {
                  if (nw.state === 'installed' && navigator.serviceWorker.controller) {
                    nw.postMessage('SKIP_WAITING');
                  }
                });
              });
            })
            .catch(function () { /* silencioso */ });

          // Nao recarregar a pagina ao trocar de Service Worker (cancelava submissoes em curso)
        });
      }
    </script>

    <!-- JSON-LD -->
    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"SoftwareApplication","name":"FarmaPonto","applicationCategory":"BusinessApplication","operatingSystem":"Web","description":"Gestão de assiduidade para farmácias em Moçambique."}
    </script>

    <script src="<?= BASE_PATH ?>/assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857', 800: '#065f46', 900: '#064e3b' },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/vendor/inter.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/vendor/fontawesome/all.min.css">
    <script>const BASE_PATH = '<?= BASE_PATH ?>';</script>
    <script defer src="<?= BASE_PATH ?>/assets/js/paginate.js?v=5"></script>
    <script defer src="<?= BASE_PATH ?>/assets/js/autorefresh.js?v=1"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes fade-in { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slide-in { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        .animate-fade-in { animation: fade-in 0.3s ease-out; }
        .animate-slide-in { animation: slide-in 0.25s ease-out; }
        /* Acessibilidade: texto so para leitores de ecra */
        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden;
                   clip:rect(0,0,0,0); white-space:nowrap; border-width:0; }
        .focus\:not-sr-only:focus { position:static; width:auto; height:auto; margin:0; overflow:visible;
                   clip:auto; white-space:normal; }
        /* Foco sempre visivel (teclado) */
        a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible,
        textarea:focus-visible, [tabindex]:focus-visible {
            outline: 3px solid #047857; outline-offset: 2px; border-radius: 6px;
        }
        /* Alvos de toque confortaveis em ecras pequenos */
        @media (max-width: 480px) {
            button, .btn, a[role="button"] { min-height: 44px; }
        }
        @media print {
            aside, .no-print, header.topbar { display: none !important; }
            main { overflow: visible !important; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
<a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[200] focus:bg-white focus:text-emerald-800 focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-lg focus:ring-2 focus:ring-emerald-600">
    Saltar para o conteúdo
</a>
<!-- Indicador online/offline (PWA) -->
<div id="pwa-offline" role="status" aria-live="polite"
     class="hidden fixed top-0 inset-x-0 z-[120] bg-amber-500 text-white text-center text-sm py-2 no-print shadow">
    <i class="fa-solid fa-wifi mr-1"></i>
    Sem ligação à internet — algumas funcionalidades (marcação, gravação) estão indisponíveis. <button onclick="location.reload()" class="underline ml-1">Tentar novamente</button>
</div>
<script>
  (function () {
    var bar = document.getElementById('pwa-offline');
    function upd() {
      if (!bar) return;
      bar.classList.toggle('hidden', navigator.onLine);
      document.body.style.paddingTop = navigator.onLine ? '' : '40px';
    }
    window.addEventListener('online', upd);
    window.addEventListener('offline', upd);
    upd();
  })();
</script>
<?php if ($user && empty($kiosk)): ?>
<!-- Layout com Sidebar -->
<div class="flex h-screen relative">
    <!-- Overlay mobile -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden no-print" onclick="toggleSidebar(false)"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-40 w-64 bg-emerald-50 border-r border-emerald-100 flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200 no-print">
        <div class="p-5 border-b border-emerald-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <?php
                  $__cfg = new \App\Models\Config();
                  $__logo = $__cfg->get('instituicao_logo', '');
                  $__nomeInst = $__cfg->get('nome_empresa', 'FarmaPonto');
                  $__logoPath = $__logo ? (__DIR__ . '/../../../' . $__logo) : '';
                ?>
                <?php if ($__logoPath && is_file($__logoPath)): ?>
                    <img src="<?= BASE_PATH ?>/logo?v=<?= @filemtime($__logoPath) ?>"
                         alt="Logo" class="w-10 h-10 rounded-xl object-cover border border-emerald-200 bg-white">
                <?php else: ?>
                    <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white">
                        <i class="fa-solid fa-link"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="font-bold text-emerald-800 text-lg leading-tight"><?= htmlspecialchars($__nomeInst) ?></h1>
                    <p class="text-xs text-emerald-600">Gestao de Assiduidade</p>
                </div>
            </div>
            <button class="lg:hidden text-emerald-700 p-1" onclick="toggleSidebar(false)" aria-label="Fechar menu">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto" aria-label="Menu principal">
            <?php
            // [rota, rotulo, icone, perfis, requer marcar ponto]
            $menu = [
                ['dashboard', 'Dashboard', 'fa-table-columns', ['admin','gestor','funcionario'], false],
                ['marcar', 'Marcar Presenca', 'fa-circle-dot', ['admin','gestor','funcionario'], true],
                ['historico', 'Historico', 'fa-clock-rotate-left', ['admin','gestor','funcionario'], false],
                ['minha-assiduidade', 'Minha Assiduidade', 'fa-chart-line', ['admin','gestor','funcionario'], true],
                ['funcionarios', 'Funcionarios', 'fa-users', ['admin','gestor']],
                ['relatorios', 'Relatorios & Salarios', 'fa-file-invoice-dollar', ['admin','gestor']],
                ['folhas', 'Folhas Salariais', 'fa-file-signature', ['admin','gestor']],
                ['logs', 'Logs do Sistema', 'fa-terminal', ['admin']],
                ['auditoria', 'Auditoria', 'fa-shield-halved', ['admin','gestor']],
                ['lixeira', 'Lixeira', 'fa-trash-arrow-up', ['admin','gestor']],
                ['backup', 'Copias de Seguranca', 'fa-database', ['admin']],
                ['diagnostico', 'Diagnostico', 'fa-heart-pulse', ['admin']],
                ['configuracoes', 'Configuracoes', 'fa-gear', ['admin']],
                ['perfil', 'Meu Perfil', 'fa-user', ['admin','gestor','funcionario']],
            ];
            foreach ($menu as $m):
                if (!in_array($perfil, $m[3])) continue;
                if (!empty($m[4]) && !$marcaPonto) continue;
                $active = $current === $m[0] ? 'bg-emerald-600 text-white' : 'text-gray-700 hover:bg-emerald-100';
            ?>
            <a href="<?= BASE_PATH ?>/<?= $m[0] ?>"
               <?= $current === $m[0] ? 'aria-current="page"' : '' ?>
               class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $active ?>">
                <i class="fa-solid <?= $m[2] ?> w-5" aria-hidden="true"></i>
                <?= $m[1] ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-emerald-100">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 bg-emerald-200 rounded-full flex items-center justify-center text-emerald-700 text-sm font-bold">
                    <?= htmlspecialchars($iniciais) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($nome) ?></p>
                    <p class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($perfil) ?></p>
                </div>
            </div>
            <a href="<?= BASE_PATH ?>/logout" class="flex items-center gap-2 text-sm text-gray-600 hover:text-red-600 transition-colors">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                Sair
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto w-full">
        <!-- Topbar mobile -->
        <header class="topbar lg:hidden sticky top-0 bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between z-20 no-print">
            <button onclick="toggleSidebar(true)" class="text-gray-700 p-2" aria-label="Abrir menu">
                <i class="fa-solid fa-bars text-xl"></i>
            </button>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center text-white text-sm">
                    <i class="fa-solid fa-link"></i>
                </div>
                <span class="font-bold text-emerald-800">FarmaPonto</span>
            </div>
            <div class="w-9"></div>
        </header>
        <div id="conteudo" tabindex="-1" class="p-4 sm:p-6 lg:p-8 max-w-[1760px] mx-auto">
<?php else: ?>
<!-- Layout Publico -->
<main id="conteudo" tabindex="-1" class="min-h-screen">
<?php endif; ?>
<script>
function toggleSidebar(show) {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebar-overlay');
    if (!sb) return;
    if (show) { sb.classList.remove('-translate-x-full'); ov && ov.classList.remove('hidden'); }
    else { sb.classList.add('-translate-x-full'); ov && ov.classList.add('hidden'); }
}
</script>
