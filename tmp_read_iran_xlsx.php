<?php

require __DIR__.'/vendor/autoload.php';

$files = [
    'C:/Users/ALAA-PC/Desktop/AMIR-ABAD.xlsx',
    'C:/Users/ALAA-PC/Desktop/IRAN-JOLFA.xlsx',
    'C:/Users/ALAA-PC/Desktop/AMIRABAD - 60-remaining.xlsx',
    'C:/Users/ALAA-PC/Desktop/All-Cars (2).xlsx',
];

foreach ($files as $path) {
    echo str_repeat('=', 80).PHP_EOL;
    echo basename($path).' '. (file_exists($path) ? 'exists' : 'missing').PHP_EOL;
    if (! file_exists($path)) {
        continue;
    }

    $ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    foreach ($ss->getAllSheets() as $sheet) {
        echo '--- sheet: '.$sheet->getTitle().' ---'.PHP_EOL;
        $rows = $sheet->toArray(null, true, true, false);
        echo 'rows='.count($rows).PHP_EOL;
        $max = min(count($rows), 25);
        for ($i = 0; $i < $max; $i++) {
            $cells = array_slice($rows[$i] ?? [], 0, 20);
            $line = array_map(static function ($v) {
                if ($v === null) {
                    return '';
                }

                return str_replace(["\r", "\n"], ' ', (string) $v);
            }, $cells);
            echo ($i + 1)."\t".implode(' | ', $line).PHP_EOL;
        }
        echo PHP_EOL;
    }
}
