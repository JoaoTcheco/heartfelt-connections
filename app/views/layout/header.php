<?php
use App\Helpers\Auth;
use App\Helpers\Csrf;

$user = Auth::user();
$perfil = $user['perfil'] ?? '';
$marcaPonto = $user ? Auth::marcaPonto() : false;
$nome = $user['nome'] ?? '';
$iniciais = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_filter(explode(' ', trim($nome)))));
$iniciais = substr($iniciais ?: 'F', 0, 2);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$current = basename(rtrim($uri, '/')) ?: 'dashboard';

$menu = [
    ['dashboard', 'Dashboard', 'fa-table-columns', ['admin','gestor','funcionario'], false],
    ['marcar', 'Marcar presença', 'fa-circle-dot', ['admin','gestor','funcionario'], true],
    ['historico', 'Histórico', 'fa-clock-rotate-left', ['admin','gestor','funcionario'], false],
    ['minha-assiduidade', 'Minha assiduidade', 'fa-chart-line', ['admin','gestor','funcionario'], true],
    ['funcionarios', 'Funcionários', 'fa-users', ['admin','gestor'], false],
    ['relatorios', 'Relatórios e salários', 'fa-file-invoice-dollar', ['admin','gestor'], false],
    ['folhas', 'Folhas salariais', 'fa-file-signature', ['admin','gestor'], false],
    ['logs', 'Logs do sistema', 'fa-terminal', ['admin'], false],
    ['auditoria', 'Auditoria', 'fa-shield-halved', ['admin','gestor'], false],
    ['lixeira', 'Lixeira', 'fa-trash-arrow-up', ['admin','gestor'], false],
    ['backup', 'Cópias de segurança', 'fa-database', ['admin'], false],
    ['diagnostico', 'Diagnóstico', 'fa-heart-pulse', ['admin'], false],
    ['configuracoes', 'Configurações', 'fa-gear', ['admin'], false],
    ['perfil', 'Meu perfil', 'fa-user', ['admin','gestor','funcionario'], false],
];

function menuAtivo(string $rota, string $current): bool {
    if ($rota === 'dashboard') return $current === 'dashboard';
    return $current === $rota || ($rota === 'funcionarios' && in_array($current, ['funcionario','funcionarios'], true));
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'FarmaPonto') ?> · FarmaPonto</title>
    <meta name="description" content="<?= htmlspecialchars($meta_desc ?? 'FarmaPonto — Sistema de gestão de assiduidade para farmácias em Moçambique.') ?>">
    <meta name="theme-color" content="#047857">
    <meta name="robots" content="index,follow">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FarmaPonto">
    <meta name="application-name" content="FarmaPonto">
    <link rel="canonical" href="<?= htmlspecialchars(($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="FarmaPonto">
    <meta property="og:title" content="<?= htmlspecialchars($titulo ?? 'FarmaPonto') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta_desc ?? 'Gestão de assiduidade para farmácias.') ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($titulo ?? 'FarmaPonto') ?>">

    <link rel="manifest" href="<?= BASE_PATH ?>/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%23047857'/><text x='50%25' y='54%25' font-size='38' font-family='Arial' font-weight='bold' fill='white' text-anchor='middle'>F</text></svg>">
    <link rel="apple-touch-icon" href="<?= BASE_PATH ?>/assets/images/icon-192.png">
    <link rel="apple-touch-icon" sizes="192x192" href="<?= BASE_PATH ?>/assets/images/icon-192.png">
    <link rel="apple-touch-icon" sizes="512x512" href="<?= BASE_PATH ?>/assets/images/icon-512.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= BASE_PATH ?>/assets/images/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= BASE_PATH ?>/assets/images/icon-512.png">

    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('<?= BASE_PATH ?>/sw.js', { scope: '<?= BASE_PATH ?>/' })
            .then(function (reg) {
              setInterval(function () { reg.update().catch(function(){}); }, 60 * 60 * 1000);
              if (reg.waiting) reg.waiting.postMessage('SKIP_WAITING');
              reg.addEventListener('updatefound', function () {
                var nw = reg.installing;
                if (!nw) return;
                nw.addEventListener('statechange', function () {
                  if (nw.state === 'installed' && navigator.serviceWorker.controller) nw.postMessage('SKIP_WAITING');
                });
              });
            }).catch(function () {});
        });
      }
    </script>

    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"SoftwareApplication","name":"FarmaPonto","applicationCategory":"BusinessApplication","operatingSystem":"Web","description":"Gestão de assiduidade para farmácias em Moçambique."}
    </script>

    <script src="<?= BASE_PATH ?>/assets/js/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: { 50:'#ecfdf5',100:'#d1fae5',200:'#a7f3d0',300:'#6ee7b7',400:'#34d399',500:'#10b981',600:'#059669',700:'#047857',800:'#065f46',900:'#064e3b' } }
            }}
        }
    </script>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/vendor/inter.css">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/vendor/fontawesome/all.min.css">
    <script>const BASE_PATH = '<?= BASE_PATH ?>';</script>
    <script defer src="<?= BASE_PATH ?>/assets/js/paginate.js?v=5"></script>
    <script defer src="<?= BASE_PATH ?>/assets/js/autorefresh.js?v=1"></script>
    <style>
        body { font-family:'Inter',sans-serif; }
        @keyframes fade-in { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
        @keyframes slide-in { from { opacity:0; transform:translateX(20px); } to { opacity:1; transform:translateX(0); } }
        .animate-fade-in { animation:fade-in .3s ease-out; }
        .animate-slide-in { animation:slide-in .25s ease-out; }
        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border-width:0; }
        .focus\\:not-sr-only:focus { position:static; width:auto; height:auto; margin:0; overflow:visible; clip:auto; white-space:normal; }
        a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,[tabindex]:focus-visible { outline:3px solid #047857; outline-offset:2px; border-radius:6px; }
        @media (max-width:480px) { button,.btn,a[role="button"] { min-height:44px; } }
        @media print { aside,.no-print,header.topbar { display:none !important; } main { overflow:visible !important; } }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
<a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[200] focus:bg-white focus:text-emerald-800 focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-lg focus:ring-2 focus:ring-emerald-600">Saltar para o conteúdo</a>

<div id="pwa-offline" role="status" aria-live="polite" class="hidden fixed top-0 inset-x-0 z-[120] bg-amber-500 text-white text-center text-sm py-2 no-print shadow">
    <i class="fa-solid fa-wifi mr-1"></i>
    Sem ligação à internet — algumas funcionalidades (marcação, gravação) estão indisponíveis.
    <button onclick="location.reload()" class="underline ml-1">Tentar novamente</button>
</div>
<script>
(function () {
    var bar = document.getElementById('pwa-offline');
    function upd() {
        if (!bar) return;
        bar.classList.toggle('hidden', navigator.onLine);
        document.body.style.paddingTop = navigator.onLine ? '' : '40px';
    }
    window.addEventListener('online', upd); window.addEventListener('offline', upd); upd();
})();
</script>

<?php if ($user && empty($kiosk)): ?>
<div class="flex h-screen relative">
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-950/50 backdrop-blur-[1px] z-30 hidden lg:hidden no-print" onclick="toggleSidebar(false)"></div>

    <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-40 w-72 bg-white border-r border-slate-200 flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200 no-print shadow-xl lg:shadow-none">
        <div class="px-5 py-5 border-b border-slate-200">
            <div class="flex items-center gap-3">
                <?php
                  $__cfg = new \App\Models\Config();
                  $__logo = $__cfg->get('instituicao_logo', '');
                  $__nomeInst = $__cfg->get('nome_empresa', 'FarmaPonto');
                  $__logoPath = $__logo ? (__DIR__ . '/../../../' . $__logo) : '';
                ?>
                <?php if ($__logoPath && is_file($__logoPath)): ?>
                    <img src="<?= BASE_PATH ?>/logo?v=<?= @filemtime($__logoPath) ?>" alt="Logo" class="w-11 h-11 rounded-xl object-cover border border-slate-200 bg-white shadow-sm">
                <?php else: ?>
                    <div class="w-11 h-11 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-xl shadow-sm" aria-hidden="true">F</div>
                <?php endif; ?>
                <div class="min-w-0">
                    <h1 class="font-bold text-slate-900 text-lg leading-tight truncate"><?= htmlspecialchars($__nomeInst) ?></h1>
                    <p class="text-xs text-slate-500 mt-0.5">Gestão de assiduidade</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto" aria-label="Menu principal">
            <?php foreach ($menu as $m):
                if (!in_array($perfil, $m[3], true)) continue;
                if (!empty($m[4]) && !$marcaPonto) continue;
                $isActive = menuAtivo($m[0], $current);
                $active = $isActive ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-700 hover:bg-emerald-50 hover:text-emerald-800';
            ?>
            <a href="<?= BASE_PATH ?>/<?= $m[0] ?>" <?= $isActive ? 'aria-current="page"' : '' ?> class="group flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-medium transition-all <?= $active ?>">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center <?= $isActive ? 'bg-white/15' : 'bg-slate-100 group-hover:bg-white' ?>">
                    <i class="fa-solid <?= $m[2] ?> w-5 text-center" aria-hidden="true"></i>
                </span>
                <span class="truncate"><?= $m[1] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-slate-200 bg-slate-50/70">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-700 text-sm font-bold ring-4 ring-white"><?= htmlspecialchars($iniciais) ?></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900 truncate"><?= htmlspecialchars($nome) ?></p>
                    <p class="text-xs text-slate-500 capitalize"><?= htmlspecialchars($perfil) ?></p>
                </div>
            </div>
            <a href="<?= BASE_PATH ?>/logout" class="flex items-center justify-center gap-2 w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium text-slate-600 hover:text-red-600 hover:border-red-200 transition-colors">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Sair
            </a>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto w-full">
        <header class="topbar lg:hidden sticky top-0 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between z-20 no-print">
            <button onclick="toggleSidebar(true)" class="text-slate-700 p-2 rounded-lg hover:bg-slate-100" aria-label="Abrir menu"><i class="fa-solid fa-bars text-xl" aria-hidden="true"></i></button>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">F</div>
                <span class="font-bold text-slate-900"><?= htmlspecialchars($__nomeInst ?? 'FarmaPonto') ?></span>
            </div>
            <a href="<?= BASE_PATH ?>/perfil" class="w-9 h-9 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-700 text-xs font-bold" aria-label="Abrir perfil"><?= htmlspecialchars($iniciais) ?></a>
        </header>
        <div id="conteudo" tabindex="-1" class="p-4 sm:p-6 lg:p-8 max-w-[1760px] mx-auto">
<?php else: ?>
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
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') toggleSidebar(false); });
</script>
