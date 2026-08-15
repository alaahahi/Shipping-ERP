<?php

require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'C:\\Users\\ALAA-PC\\Downloads\\BRWA-KHOSHNAW COROLLA CROSS , BYD.xlsx';
$ss = IOFactory::load($path);
$sheet = $ss->getSheet(0);

foreach ([5, 126, 127, 128, 189] as $r) {
    echo "==== ROW $r ====\n";
    foreach (range('A', 'I') as $col) {
        $cell = $sheet->getCell($col.$r);
        $val = $cell->getValue();
        $calc = $cell->getCalculatedValue();
        $fmt = $cell->getFormattedValue();
        $type = $cell->getDataType();
        echo "$col$type val=".json_encode($val)." calc=".json_encode($calc)." fmt=".json_encode($fmt)."\n";
    }
}

echo "\nstyles/fill row 127: ".$sheet->getCell('B127')->getXfIndex()."\n";
echo "row dimension 127: ".json_encode($sheet->getRowDimension(127)->getRowHeight())."\n";
