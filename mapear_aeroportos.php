<?php
set_time_limit(300);
ini_set('memory_limit', '256M');

$csvUrl = 'https://davidmegginson.github.io/ourairports-data/airports.csv';
$runwaysUrl = 'https://davidmegginson.github.io/ourairports-data/runways.csv';
$outputFile = __DIR__ . '/airports.json';
$tmpFile = __DIR__ . '/airports.csv';
$tmpRunwaysFile = __DIR__ . '/runways.csv';

$tiposPermitidos = ['large_airport', 'medium_airport'];

function baixarCsv($url, $destino) {
    echo "Baixando " . basename($destino) . "...\n";
    $fp = fopen($destino, 'w');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($httpCode !== 200) {
        unlink($destino);
        die("ERRO: HTTP $httpCode ao baixar $url\n");
    }
    echo "Download OK\n";
}

// --- Download CSVs ---
if (!file_exists($tmpFile)) {
    baixarCsv($csvUrl, $tmpFile);
} else {
    echo "Usando airports.csv existente\n";
}
if (!file_exists($tmpRunwaysFile)) {
    baixarCsv($runwaysUrl, $tmpRunwaysFile);
} else {
    echo "Usando runways.csv existente\n";
}

// --- Process CSV ---
echo "Processando...\n";

$fh = fopen($tmpFile, 'r');
if (!$fh) die("ERRO: não abriu $tmpFile\n");

$headers = fgetcsv($fh);
$idxIdent = array_search('ident', $headers);
$idxType = array_search('type', $headers);
$idxName = array_search('name', $headers);
$idxLat = array_search('latitude_deg', $headers);
$idxLon = array_search('longitude_deg', $headers);
$idxCountry = array_search('iso_country', $headers);
$idxElev = array_search('elevation_ft', $headers);

if ($idxIdent === false) die("ERRO: coluna ident não encontrada\n");

$mapa = [];
$count = 0;

while (($row = fgetcsv($fh)) !== false) {
    $pais = $row[$idxCountry] ?? '';
    if ($pais !== 'BR') continue;

    $tipo = $row[$idxType] ?? '';
    if (!in_array($tipo, $tiposPermitidos)) continue;

    $ident = strtoupper(trim($row[$idxIdent] ?? ''));
    if (empty($ident)) continue;

    $lat = isset($row[$idxLat]) ? (float) $row[$idxLat] : null;
    $lon = isset($row[$idxLon]) ? (float) $row[$idxLon] : null;
    if ($lat === null || $lon === null) continue;

    $elevFt = ($idxElev !== false && isset($row[$idxElev]) && $row[$idxElev] !== '') ? (float) $row[$idxElev] : 0;

    $mapa[$ident] = [
        'n' => $row[$idxName] ?? '',
        'lat' => $lat,
        'lon' => $lon,
        't' => $tipo === 'large_airport' ? 'grande' : 'medio',
        'elev' => $elevFt,
    ];
    $count++;
}
fclose($fh);

// --- Process runways.csv ---
echo "Processando pistas...\n";

$fhR = fopen($tmpRunwaysFile, 'r');
if (!$fhR) die("ERRO: não abriu $tmpRunwaysFile\n");

$headersR = fgetcsv($fhR);
$idxRIdent = array_search('airport_ident', $headersR);
$idxRClosed = array_search('closed', $headersR);
$idxRLength = array_search('length_ft', $headersR);
$idxRWidth = array_search('width_ft', $headersR);
$idxLeLat = array_search('le_latitude_deg', $headersR);
$idxLeLon = array_search('le_longitude_deg', $headersR);
$idxHeLat = array_search('he_latitude_deg', $headersR);
$idxHeLon = array_search('he_longitude_deg', $headersR);

$countPistas = 0;
while (($row = fgetcsv($fhR)) !== false) {
    $ident = strtoupper(trim($row[$idxRIdent] ?? ''));
    if (!isset($mapa[$ident])) continue;
    if (($row[$idxRClosed] ?? '0') === '1') continue;

    $leLat = $row[$idxLeLat] ?? '';
    $leLon = $row[$idxLeLon] ?? '';
    $heLat = $row[$idxHeLat] ?? '';
    $heLon = $row[$idxHeLon] ?? '';
    if ($leLat === '' || $leLon === '' || $heLat === '' || $heLon === '') continue;

    if (!isset($mapa[$ident]['r'])) $mapa[$ident]['r'] = [];
    $mapa[$ident]['r'][] = [
        'la' => (float) $leLat, 'lo' => (float) $leLon,
        'ha' => (float) $heLat, 'ho' => (float) $heLon,
        'c' => (int) ($row[$idxRLength] ?? 0),
        'l' => (int) ($row[$idxRWidth] ?? 100),
    ];
    $countPistas++;
}
fclose($fhR);

file_put_contents($outputFile, json_encode($mapa));
echo "OK: $count aeroportos mapeados, $countPistas pistas\n";
echo "Arquivo: airports.json (" . round(filesize($outputFile) / 1024) . " KB)\n";
