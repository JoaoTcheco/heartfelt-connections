/**
 * FarmaPonto - Actualizacao automatica de listagens
 * Recarrega em segundo plano o conteudo das tabelas marcadas com [data-live]
 * sem perder filtros, scroll nem o que o utilizador esta a escrever.
 */
(function () {
  'use strict';
  var INTERVALO = 30000;
  var timer = null;
  var ocupado = false;

  function alvos() { return Array.prototype.slice.call(document.querySelectorAll('[data-live]')); }

  function podeActualizar() {
    if (document.hidden || ocupado) return false;
    var a = document.activeElement;
    if (a && /^(INPUT|SELECT|TEXTAREA)$/.test(a.tagName) && a.type !== 'button') return false;
    // Nao mexer se houver um modal/dialogo aberto
    if (document.querySelector('.fixed.inset-0:not(.hidden)')) return false;
    return true;
  }

  function actualizar() {
    var lista = alvos();
    if (!lista.length || !podeActualizar()) return;
    ocupado = true;
    fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'autorefresh' }, cache: 'no-store' })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        if (!doc.querySelector('[data-live]')) return; // ex.: redireccionado para o login
        lista.forEach(function (el) {
          var id = el.getAttribute('data-live');
          var novo = doc.querySelector('[data-live="' + id + '"]');
          if (!novo || novo.innerHTML === el.innerHTML) return;
          el.innerHTML = novo.innerHTML;
          if (el.tagName === 'TBODY' && window.FarmaPaginate) {
            window.FarmaPaginate.rebuild(el.closest('table'));
          } else if (window.FarmaPaginate) {
            window.FarmaPaginate.init();
          }
        });
        var carimbo = document.querySelector('[data-live-stamp]');
        if (carimbo) carimbo.textContent = 'Actualizado as ' + new Date().toLocaleTimeString('pt-PT');
      })
      .catch(function () {})
      .then(function () { ocupado = false; });
  }

  function arrancar() {
    if (!alvos().length) return;
    if (timer) clearInterval(timer);
    timer = setInterval(actualizar, INTERVALO);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) actualizar(); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', arrancar);
  else arrancar();

  window.FarmaLive = { actualizar: actualizar };
})();
