<?php
set_time_limit(300);
ini_set('memory_limit', '256M');

$csvUrl = 'https://opensky-network.org/datasets/metadata/aircraftDatabase.csv';
$outputFile = __DIR__ . '/aircraft_map.json';
$tmpFile = __DIR__ . '/aircraftDatabase.csv';

$prefixesBR = ['e4', 'e8'];

// --- Download CSV ---
if (!file_exists($tmpFile)) {
    echo "Baixando aircraftDatabase.csv...\n";
    $fp = fopen($tmpFile, 'w');
    $ch = curl_init($csvUrl);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($httpCode !== 200) {
        unlink($tmpFile);
        die("ERRO: HTTP $httpCode ao baixar CSV\nBaixe manualmente em:\n$csvUrl\nSalve como aircraftDatabase.csv na pasta tracking\n");
    }
    echo "Download OK\n";
} else {
    echo "Usando CSV existente\n";
}

// --- Process CSV ---
echo "Processando...\n";

$fh = fopen($tmpFile, 'r');
if (!$fh) die("ERRO: não abriu $tmpFile\n");

$headers = fgetcsv($fh);
$idxIcao = array_search('icao24', $headers);
$idxModel = array_search('model', $headers);
$idxMan = array_search('manufacturername', $headers);
$idxType = array_search('typecode', $headers);
$idxOp = array_search('operator', $headers);

if ($idxIcao === false) die("ERRO: coluna icao24 não encontrada\n");

$mapa = [];
$count = 0;
$pulo = 0;

while (($row = fgetcsv($fh)) !== false) {
    $icao = isset($row[$idxIcao]) ? strtolower(trim($row[$idxIcao])) : '';
    if (strlen($icao) < 5) continue;

    $prefix = substr($icao, 0, 2);
    if (!in_array($prefix, $prefixesBR)) continue;

    $modelo = $row[$idxModel] ?? '';
    $fab = $row[$idxMan] ?? '';
    $tipo = $row[$idxType] ?? '';
    $operador = $row[$idxOp] ?? '';

    if (empty($modelo) && empty($tipo)) { $pulo++; continue; }

    $mapa[$icao] = [
        'm' => $modelo,
        'f' => $fab,
        't' => strtoupper($tipo),
        'o' => $operador,
    ];
    $count++;
}
fclose($fh);

file_put_contents($outputFile, json_encode($mapa));
echo "OK: $count aeronaves mapeadas ($pulo ignoradas sem modelo)\n";
echo "Arquivo: aircraft_map.json (" . round(filesize($outputFile) / 1024) . " KB)\n";
