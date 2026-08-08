<?php

require __DIR__.'/vendor/autoload.php';

$files = [
    'C:/Users/ALAA-PC/Desktop/AMIR ABAD, JOLFA , BAZRGAN.xlsx',
    'C:/Users/ALAA-PC/Desktop/AMIRABAD - 60-remaining.xlsx',
];

foreach ($files as $path) {
    echo str_repeat('=', 80).PHP_EOL;
    echo basename($path).' '.(file_exists($path) ? 'exists' : 'missing').PHP_EOL;
    if (! file_exists($path)) {
        continue;
    }

    $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    foreach ($spreadsheet->getAllSheets() as $sheet) {
        echo '--- sheet: '.$sheet->getTitle().' ---'.PHP_EOL;
        $rows = $sheet->toArray(null, true, true, false);
        echo 'rows='.count($rows).PHP_EOL;
        $max = min(count($rows), 45);
        for ($i = 0; $i < $max; $i++) {
            $cells = array_slice($rows[$i] ?? [], 0, 12);
            $line = array_map(static function ($value) {
                return str_replace(["\r", "\n"], ' ', (string) ($value ?? ''));
            }, $cells);
            echo ($i + 1)."\t".implode(' | ', $line).PHP_EOL;
        }
        echo PHP_EOL;
    }
}
