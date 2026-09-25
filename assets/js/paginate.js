/**
 * FarmaPonto - Paginação client-side reutilizável
 * v1 — 2026-06
 *
 * Como usar:
 *   <table data-paginate="20" data-paginate-id="hist">
 *     <thead>...</thead>
 *     <tbody>...</tbody>
 *   </table>
 *
 *   Filtro de texto opcional (filtra linhas pelo conteúdo textual):
 *   <input data-filter-rows="#tabelaHist" placeholder="Filtrar...">
 *
 * Recursos:
 *  - Botões: « Início, ‹ Anterior, números (com janela), Próximo ›, Fim »
 *  - Selector de "linhas por página" (10 / 20 / 50 / 100 / Todos)
 *  - Indicador "A mostrar X-Y de Z"
 *  - Filtro de texto multi-campo que reage e re-pagina
 *  - Não toca em layout existente; injecta um <nav> imediatamente abaixo do <table>
 */
(function () {
  'use strict';

  const PAGE_SIZES = [10, 20, 50, 100, 0]; // 0 = "Todos"

  function build(table) {
    if (table.__paginated) return;
    table.__paginated = true;

    const tbody = table.tBodies[0];
    if (!tbody) return;

    const defaultSize = parseInt(table.dataset.paginate, 10) || 20;
    const allRows = Array.from(tbody.rows);
    // Ignora linhas-placeholder (ex.: "Sem dados")
    const dataRows = allRows.filter(r => !r.hasAttribute('data-skip-paginate'));
    // Linhas auxiliares (detalhe expansivel) seguem sempre a linha-mae
    const extraRows = allRows.filter(r => r.hasAttribute('data-skip-paginate'));

    if (dataRows.length === 0) return;

    const state = { page: 1, size: defaultSize, filtered: dataRows.slice() };

    const nav = document.createElement('div');
    nav.className = 'flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 bg-white border-t border-gray-100 text-sm';
    nav.innerHTML = `
      <div class="flex items-center gap-2 text-gray-600">
        <span data-info>—</span>
      </div>
      <div class="flex items-center gap-3 flex-wrap">
        <label class="flex items-center gap-2 text-gray-600">
          <span class="text-xs">Por página:</span>
          <select data-size class="px-2 py-1 border border-gray-300 rounded text-xs focus:ring-2 focus:ring-emerald-500 outline-none">
            ${PAGE_SIZES.map(s => `<option value="${s}" ${s===defaultSize?'selected':''}>${s===0?'Todos':s}</option>`).join('')}
          </select>
        </label>
        <div class="flex items-center gap-1" data-pages role="navigation" aria-label="Paginação"></div>
      </div>
    `;
    // Inserir logo após o table (mesmo container)
    if (table.parentNode) table.parentNode.insertBefore(nav, table.nextSibling);

    const info = nav.querySelector('[data-info]');
    const sizeSel = nav.querySelector('[data-size]');
    const pages = nav.querySelector('[data-pages]');

    function btn(label, opts) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'px-2.5 py-1 rounded border border-gray-200 text-xs font-medium hover:bg-emerald-50 hover:border-emerald-300 transition ' +
        (opts.active ? 'bg-emerald-600 text-white border-emerald-600 hover:bg-emerald-700' :
                       opts.disabled ? 'opacity-40 cursor-not-allowed' : 'bg-white text-gray-700');
      b.innerHTML = label;
      if (opts.disabled) b.disabled = true;
      if (opts.title) b.title = opts.title;
      if (opts.onClick && !opts.disabled) b.addEventListener('click', opts.onClick);
      return b;
    }

    function render() {
      const total = state.filtered.length;
      const size = state.size === 0 ? Math.max(total, 1) : state.size;
      const pageCount = Math.max(1, Math.ceil(total / size));
      if (state.page > pageCount) state.page = pageCount;
      if (state.page < 1) state.page = 1;

      const start = (state.page - 1) * size;
      const end = Math.min(total, start + size);

      // Esconder todas as linhas de dados (e as respectivas linhas de detalhe)
      dataRows.forEach(r => r.style.display = 'none');
      extraRows.forEach(r => r.style.display = 'none');
      // Mostrar apenas as do intervalo actual
      for (let i = start; i < end; i++) {
        const row = state.filtered[i];
        row.style.display = '';
        // Linhas de detalhe imediatamente a seguir voltam ao fluxo normal
        let nx = row.nextElementSibling;
        while (nx && nx.hasAttribute('data-skip-paginate')) {
          nx.style.display = '';
          nx = nx.nextElementSibling;
        }
      }

      info.textContent = total === 0 ? 'Sem resultados' :
        `A mostrar ${start + 1}–${end} de ${total} registo${total !== 1 ? 's' : ''}`;

      // Botões
      pages.innerHTML = '';
      pages.appendChild(btn('«', { onClick: () => { state.page = 1; render(); }, disabled: state.page === 1, title: 'Início' }));
      pages.appendChild(btn('‹', { onClick: () => { state.page--; render(); }, disabled: state.page === 1, title: 'Anterior' }));

      // Janela de números: até 7 botões
      const WIN = 7;
      let from = Math.max(1, state.page - 3);
      let to = Math.min(pageCount, from + WIN - 1);
      from = Math.max(1, to - WIN + 1);
      if (from > 1) {
        pages.appendChild(btn('1', { onClick: () => { state.page = 1; render(); } }));
        if (from > 2) pages.appendChild(btn('…', { disabled: true }));
      }
      for (let p = from; p <= to; p++) {
        pages.appendChild(btn(String(p), { onClick: () => { state.page = p; render(); }, active: p === state.page }));
      }
      if (to < pageCount) {
        if (to < pageCount - 1) pages.appendChild(btn('…', { disabled: true }));
        pages.appendChild(btn(String(pageCount), { onClick: () => { state.page = pageCount; render(); } }));
      }
      pages.appendChild(btn('›', { onClick: () => { state.page++; render(); }, disabled: state.page === pageCount, title: 'Próximo' }));
      pages.appendChild(btn('»', { onClick: () => { state.page = pageCount; render(); }, disabled: state.page === pageCount, title: 'Fim' }));
    }

    sizeSel.addEventListener('change', () => {
      state.size = parseInt(sizeSel.value, 10) || 20;
      state.page = 1;
      render();
    });

    // Expor API para filtros externos
    table.__paginator = {
      filter(predicate) {
        state.filtered = predicate ? dataRows.filter(predicate) : dataRows.slice();
        state.page = 1;
        render();
      },
      refresh: render,
    };

    render();
  }

  function bindFilterInputs() {
    document.querySelectorAll('[data-filter-rows]').forEach(input => {
      if (input.__bound) return;
      input.__bound = true;
      const sel = input.dataset.filterRows;
      const target = document.querySelector(sel);
      if (!target || !target.__paginator) return;
      const apply = () => {
        const q = input.value.trim().toLowerCase();
        if (!q) { target.__paginator.filter(null); return; }
        target.__paginator.filter(row => row.textContent.toLowerCase().includes(q));
      };
      input.addEventListener('input', apply);
      apply();
    });
  }

  function init() {
    document.querySelectorAll('table[data-paginate]').forEach(build);
    bindFilterInputs();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function rebuild(table) {
    if (!table) return;
    // Remover a barra de paginacao antiga (inserida logo a seguir a tabela)
    const nav = table.nextElementSibling;
    if (nav && nav.querySelector && nav.querySelector('[data-pages]')) nav.remove();
    table.__paginated = false;
    table.__paginator = null;
    build(table);
    document.querySelectorAll('[data-filter-rows]').forEach(i => { i.__bound = false; });
    bindFilterInputs();
  }

  window.FarmaPaginate = { init, build, rebuild };
})();
