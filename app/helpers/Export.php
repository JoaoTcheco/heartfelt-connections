<?php
/**
 * ============================================================
 * FarmaPonto - Export Helper (CSV)
 * ============================================================
 */

namespace App\Helpers;

final class Export {

    public static function csv(array $linhas, string $nome): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nome . '.csv"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        foreach ($linhas as $l) {
            fputcsv($out, $l, ';', '"', '');
        }
        fclose($out);
        exit;
    }
}