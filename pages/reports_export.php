<?php
declare(strict_types=1);

$reportMonth = $_GET['month'] ?? date('Y-m');
$reportView = $_GET['view'] ?? 'month';
$reportExport = $_GET['export'] ?? '';
$report = report_data($reportMonth);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="report-' . $reportMonth . '-' . $reportExport . '.csv"');
$out = fopen('php://output', 'w');
if ($out !== false) {
    fputcsv($out, ['Month', $reportMonth]);
    if ($reportExport === 'list') {
        fputcsv($out, []);
        fputcsv($out, ['Date', 'Category', 'Purse', 'User', 'Note', 'Amount']);
        foreach ($report['expenses'] as $tx) {
            fputcsv($out, [
                $tx['date'] ?? '',
                find_name($categories, $tx['category_id'] ?? ''),
                find_name($purses, $tx['purse_id'] ?? ''),
                find_name($users, $tx['user_id'] ?? ''),
                $tx['note'] ?? '',
                (float)($tx['amount'] ?? 0),
            ]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Total', $report['total']]);
    } else {
        fputcsv($out, ['Summary Type', $reportView === 'week' ? 'Weekly Categories' : 'Monthly Categories']);
        fputcsv($out, ['Total', $report['total']]);
        fputcsv($out, []);
        if ($reportView === 'week') {
            fputcsv($out, ['Week', 'Category', 'Amount']);
            foreach ($report['byWeek'] as $week => $cats) {
                foreach ($cats as $catId => $amount) {
                    fputcsv($out, ['Week ' . $week, find_name($categories, $catId), $amount]);
                }
            }
        } else {
            fputcsv($out, ['Category', 'Amount']);
            foreach ($report['byCategory'] as $catId => $amount) {
                fputcsv($out, [find_name($categories, $catId), $amount]);
            }
        }
    }
    fclose($out);
}
