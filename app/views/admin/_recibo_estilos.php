<?php
/**
 * ============================================================
 * FarmaPonto - Estilos partilhados dos recibos (A4)
 * ============================================================
 * Usado por:
 *   - admin/recibo_pdf.php    (recibo individual)
 *   - admin/recibos_lote.php  (recibos em lote, 1 por pagina)
 * Nao recebe variaveis: e apenas a folha de estilo de impressao.
 */
?>
<style>
    @page { size: A4 portrait; margin: 16mm; }
    * { box-sizing: border-box; }
    body { font-family: "Times New Roman", Times, serif; font-size: 12pt; color: #000; margin: 0; padding: 16mm; }
    .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #047857; padding-bottom: 8px; margin-bottom: 14px; }
    .brand { color: #047857; font-weight: bold; font-size: 18pt; display: flex; align-items: center; gap: 10px; }
    .meta { font-size: 10pt; color: #555; text-align: right; }
    h1 { text-align: center; font-size: 16pt; margin: 14px 0 2px; }
    .sub { text-align: center; color: #555; font-size: 11pt; margin-bottom: 14px; }
    .grid { display: flex; gap: 16px; margin-bottom: 14px; }
    .card { flex: 1; border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 12px; font-size: 10.5pt; }
    .card h3 { margin: 0 0 6px; font-size: 10pt; text-transform: uppercase; letter-spacing: .5px; color: #047857; }
    .card div { margin: 2px 0; }
    .card b { font-weight: bold; }
    table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    th, td { border-bottom: 1px solid #ccc; padding: 5px 4px; text-align: left; }
    th { background: #ecfdf5; color: #064e3b; border-bottom: 2px solid #047857; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .corte { color: #b91c1c; }
    .liquido { color: #047857; font-weight: bold; }
    .resumo { margin-top: 14px; width: 60%; margin-left: auto; }
    .resumo td { border-bottom: 1px solid #e5e7eb; padding: 5px 4px; }
    .resumo tr.total td { border-top: 2px solid #047857; border-bottom: 0; font-size: 12pt; padding-top: 8px; }
    .assinaturas { margin-top: 36px; display: flex; gap: 48px; }
    .assinaturas div { flex: 1; border-top: 1px solid #333; padding-top: 6px; text-align: center; font-size: 10pt; color: #555; }
    .footer { margin-top: 22px; font-size: 9pt; color: #555; border-top: 1px solid #ccc; padding-top: 8px; }
    .actions { margin-bottom: 12px; }
    .actions button, .actions a { background: #047857; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-size: 11pt; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 6px; font-family: inherit; }
    .actions a.sec { background: #6b7280; }
    /* Lote: cada recibo ocupa uma pagina propria */
    .recibo + .recibo { page-break-before: always; margin-top: 28px; }
    .indice { border: 1px solid #d1d5db; border-radius: 6px; padding: 10px 12px; margin-bottom: 18px; font-size: 10pt; }
    .indice h3 { margin: 0 0 6px; font-size: 10pt; text-transform: uppercase; color: #047857; }
    @media print {
        .actions { display: none; }
        body { padding: 0; }
        .recibo + .recibo { margin-top: 0; }
    }
</style>
