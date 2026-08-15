<?php

require __DIR__.'/vendor/autoload.php';

$path = 'C:\\Users\\ALAA-PC\\Downloads\\BRWA-KHOSHNAW COROLLA CROSS , BYD.xlsx';
$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$sheet = $ss->getSheet(0);
$highestRow = $sheet->getHighestRow();
$highestCol = $sheet->getHighestColumn();
echo "highest {$highestRow} {$highestCol}\n";

$merged = $sheet->getMergeCells();
echo "merged: ".json_encode($merged)."\n";

for ($r = 1; $r <= $highestRow; $r++) {
    $a = trim((string) $sheet->getCell('A'.$r)->getValue());
    $b = trim((string) $sheet->getCell('B'.$r)->getValue());
    $c = trim((string) $sheet->getCell('C'.$r)->getValue());
    $d = trim((string) $sheet->getCell('D'.$r)->getValue());
    $e = trim((string) $sheet->getCell('E'.$r)->getValue());
    $line = "$a|$b|$c|$d|$e";
    if ($line === '||||') {
        continue;
    }
    $isElantra = stripos($line, 'elantra') !== false || stripos($line, 'النترا') !== false;
    $emptyVin = $d === '' && is_numeric($a) && (int) $a > 0;
    if ($isElantra || $emptyVin || $r <= 10 || ($r >= 120 && $r <= 140)) {
        echo str_pad((string) $r, 3, ' ', STR_PAD_LEFT).": A=[$a] B=[$b] C=[$c] D=[$d] E=[$e]\n";
    }
}
