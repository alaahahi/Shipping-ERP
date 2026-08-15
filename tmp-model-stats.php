<?php

require __DIR__.'/vendor/autoload.php';

$path = 'C:\\Users\\ALAA-PC\\Downloads\\BRWA-KHOSHNAW COROLLA CROSS , BYD.xlsx';
$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$sheet = $ss->getSheet(0);

$byModel = [];
$noStatus = 0;
$noVin = 0;
$totalData = 0;
for ($r = 5; $r <= 189; $r++) {
    $a = trim((string) $sheet->getCell('A'.$r)->getValue());
    $b = trim((string) $sheet->getCell('B'.$r)->getValue());
    $c = trim((string) $sheet->getCell('C'.$r)->getValue());
    $d = trim((string) $sheet->getCell('D'.$r)->getValue());
    $e = trim((string) $sheet->getCell('E'.$r)->getValue());
    if ($a === '' && $b === '' && $d === '') {
        continue;
    }
    $totalData++;
    $model = $b !== '' ? $b : '(empty-model)';
    if ($d === '') {
        $noVin++;
    }
    if ($e === '') {
        $noStatus++;
    }
    if (! isset($byModel[$model])) {
        $byModel[$model] = ['n' => 0, 'noVin' => 0, 'noStatus' => 0, 'first' => $r];
    }
    $byModel[$model]['n']++;
    if ($d === '') {
        $byModel[$model]['noVin']++;
    }
    if ($e === '') {
        $byModel[$model]['noStatus']++;
    }
}

echo "total rows with something: $totalData noVin=$noVin noStatus=$noStatus\n";
print_r($byModel);
