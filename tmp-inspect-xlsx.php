<?php

require __DIR__.'/vendor/autoload.php';

$path = 'C:\\Users\\ALAA-PC\\Downloads\\BRWA-KHOSHNAW COROLLA CROSS , BYD.xlsx';
echo file_exists($path) ? "exists\n" : "missing\n";
echo 'size='.filesize($path)."\n";

$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
echo 'sheets: '.implode(' | ', $ss->getSheetNames()).PHP_EOL;
$sheet = $ss->getSheetByName('Sorted Inventory') ?? $ss->getSheet(0);
echo 'active: '.$sheet->getTitle().PHP_EOL;
$rows = $sheet->toArray(null, true, false, false);
echo 'count: '.count($rows).PHP_EOL;

foreach ($rows as $i => $row) {
    $cells = array_map(static fn ($v) => trim((string) ($v ?? '')), $row);
    if (implode('', $cells) === '') {
        echo ($i + 1).": EMPTY\n";

        continue;
    }
    echo ($i + 1).': '.json_encode($cells, JSON_UNESCAPED_UNICODE).PHP_EOL;
    if ($i > 40) {
        echo "...\n";
        break;
    }
}
