<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$url = 'https://adsb.trafegoaereo.com/tar1090/data/aircraft.json';

$ctx = stream_context_create([
  'http' => [
    'timeout' => 10,
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) tracking-app',
  ],
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
  ],
]);

$json = @file_get_contents($url, false, $ctx);

if ($json === false) {
  http_response_code(502);
  echo json_encode(['erro' => 'Falha ao buscar dados do TrafegoAereo']);
  exit;
}

echo $json;
