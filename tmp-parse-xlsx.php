<?php

require __DIR__.'/vendor/autoload.php';

$path = 'C:\\Users\\ALAA-PC\\Downloads\\BRWA-KHOSHNAW COROLLA CROSS , BYD.xlsx';
$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
$sheet = $ss->getSheetByName('Sorted Inventory') ?? $ss->getSheet(0);
$rows = $sheet->toArray(null, true, false, false);

function isHeaderRow(array $cells): bool
{
    foreach ($cells as $cell) {
        $value = strtolower($cell);
        if (str_contains($value, 'vehicle model') || str_contains($value, 'vin number') || str_contains($value, 'cmr')) {
            return true;
        }
    }

    return false;
}

function looksLikeConsigneeHeader(array $cells): bool
{
    $first = trim($cells[0] ?? '');
    if ($first === '' || is_numeric($first)) {
        return false;
    }
    if (isHeaderRow($cells)) {
        return false;
    }
    $joined = implode(' ', array_filter($cells));

    return ! preg_match('/(corolla|elantra|byd|vehicle|vin|cmr)/i', $joined);
}

function mapColumns(array $cells): array
{
    $map = [];
    foreach ($cells as $index => $cell) {
        $value = strtolower(trim($cell));
        if (str_contains($value, 'vehicle model') || $value === 'car name') {
            $map['model'] = $index;
        } elseif (str_contains($value, 'cmr') || str_contains($value, 'waybill')) {
            $map['cmr'] = $index;
        } elseif (str_contains($value, 'vin')) {
            $map['vin'] = $index;
        } elseif ($value === '#' || $value === 'no') {
            $map['serial'] = $index;
        } elseif ($index >= 4 && ! isset($map['status'])) {
            $map['status'] = $index;
        }
    }
    if (! isset($map['status'])) {
        $map['status'] = 4;
    }

    return $map;
}

function normalizeChassis(string $value): ?string
{
    $chassis = strtoupper((string) preg_replace('/\s+/', '', trim($value)));

    return strlen($chassis) < 8 ? null : $chassis;
}

$defaultConsignee = null;
$columns = null;
$ready = 0;
$skipped = 0;
$ignoredBeforeHeader = 0;
$models = [];
$firstReady = [];
$skippedRows = [];

foreach ($rows as $index => $row) {
    $rowNumber = $index + 1;
    $cells = array_map(static fn ($value) => trim((string) ($value ?? '')), $row);
    if (implode('', $cells) === '') {
        continue;
    }
    if ($defaultConsignee === null && looksLikeConsigneeHeader($cells)) {
        $defaultConsignee = trim($cells[0]);
        echo "consignee row {$rowNumber}: {$defaultConsignee}\n";
        continue;
    }
    if (isHeaderRow($cells)) {
        $columns = mapColumns($cells);
        echo "header row {$rowNumber}: ".json_encode($columns)." cells=".json_encode($cells)."\n";
        continue;
    }
    if ($columns === null) {
        $ignoredBeforeHeader++;
        echo "ignored before header row {$rowNumber}: ".json_encode($cells[0])."\n";
        continue;
    }
    $model = $cells[$columns['model'] ?? 99] ?? '';
    $cmr = $cells[$columns['cmr'] ?? 99] ?? '';
    $chassisRaw = $cells[$columns['vin'] ?? 99] ?? '';
    $chassis = normalizeChassis($chassisRaw);
    $status = $cells[$columns['status'] ?? 99] ?? '';
    if ($chassis === null && $model === '' && $cmr === '') {
        continue;
    }
    if ($chassis === null) {
        $skipped++;
        $skippedRows[] = [$rowNumber, $model, $cmr, $chassisRaw, $status];
        continue;
    }
    $ready++;
    $models[$model] = ($models[$model] ?? 0) + 1;
    if (count($firstReady) < 5) {
        $firstReady[] = [$rowNumber, $model, $cmr, $chassis, $status];
    }
}

echo "ready={$ready} skipped={$skipped} ignoredBeforeHeader={$ignoredBeforeHeader}\n";
echo "models: ".json_encode($models, JSON_UNESCAPED_UNICODE)."\n";
echo "first ready:\n";
foreach ($firstReady as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE).PHP_EOL;
}
echo "skipped rows:\n";
foreach ($skippedRows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE).PHP_EOL;
}
echo "last 3 rows:\n";
for ($i = count($rows) - 5; $i < count($rows); $i++) {
    if ($i < 0) continue;
    echo ($i+1).': '.json_encode(array_slice($rows[$i], 0, 6), JSON_UNESCAPED_UNICODE).PHP_EOL;
}
