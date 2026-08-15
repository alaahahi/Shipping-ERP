<?php

require __DIR__.'/vendor/autoload.php';

$path = 'C:\\Users\\ALAA-PC\\Downloads\\BRWA-KHOSHNAW COROLLA CROSS , BYD.xlsx';
$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
echo 'sheetCount='.$ss->getSheetCount().PHP_EOL;
foreach ($ss->getAllSheets() as $sheet) {
    echo $sheet->getTitle().' rows='.$sheet->getHighestRow().' hidden='.(int) $sheet->getSheetState().PHP_EOL;
}
