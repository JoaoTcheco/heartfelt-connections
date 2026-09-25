/**
 * FarmaPonto - Service Worker (PWA)
 * Versao: v5 (dados sempre frescos)
 *
 * Regra de ouro: NADA de HTML nem de JSON e servido do cache quando ha rede.
 * O sistema e de assiduidade — mostrar uma pagina antiga seria pior do que
 * mostrar um erro. O cache existe apenas para os ficheiros estaticos.
 *
 * Estrategia:
 *   - POST e outros metodos  -> sempre rede (nunca intercetado)
 *   - Navegacao (HTML)       -> sempre rede; se falhar, pagina offline
 *   - JSON / APIs internas   -> sempre rede (nunca cacheado)
 *   - Estaticos locais e CDN -> cache-first com actualizacao em segundo plano
 */
const VERSION = 'v5-2026-06';
const CACHE   = 'farmaponto-' + VERSION;
const OFFLINE_URL = './offline.html';
const PRECACHE = [
  './offline.html',
  './manifest.webmanifest',
  './assets/images/icon-192.png',
  './assets/images/icon-512.png',
  './assets/js/paginate.js',
];

// Extensoes consideradas estaticas (seguras para cache)
const ESTATICO = /\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|webmanifest)$/i;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE).catch(() => {})));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

function cacheFirst(req) {
  return caches.match(req).then((cached) => {
    const rede = fetch(req).then((resp) => {
      if (resp && resp.status === 200) {
        const copia = resp.clone();
        caches.open(CACHE).then((c) => c.put(req, copia));
      }
      return resp;
    }).catch(() => cached);
    return cached || rede;
  });
}

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;              // POST/PUT/DELETE: sempre rede

  let url;
  try { url = new URL(req.url); } catch (_) { return; }
  if (url.protocol !== 'http:' && url.protocol !== 'https:') return;

  const aceita = req.headers.get('accept') || '';

  // Navegacao: rede primeiro, sem guardar HTML no cache
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // JSON / pedidos da aplicacao: nunca do cache
  if (aceita.includes('application/json') || aceita.includes('text/html')) return;

  // Sistema 100% offline: nada de terceiros e cacheado nem pedido.
  if (url.origin !== self.location.origin) return;

  // Estaticos locais
  if (ESTATICO.test(url.pathname)) {
    e.respondWith(cacheFirst(req));
  }
});

self.addEventListener('message', (e) => {
  if (e.data === 'SKIP_WAITING') self.skipWaiting();
});
