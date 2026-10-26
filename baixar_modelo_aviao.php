<?php
// Baixa, uma única vez, o modelo "Airplane" do Poly Pizza (CC-BY 3.0,
// https://poly.pizza/m/8ciDd9k8wha) pra servir localmente — o host original não envia
// cabeçalho CORS, então não dá pra carregar direto do navegador num domínio diferente.
// Usado SEM textura no radar 3D (só a geometria, recolorida por altitude).
$url = 'https://static.poly.pizza/4754ce4b-40ec-4089-8be4-98ce7230bfe4.glb';
$outputFile = __DIR__ . '/PolyPizza_Airplane.glb';

if (file_exists($outputFile)) {
    die("Já existe: PolyPizza_Airplane.glb (" . round(filesize($outputFile) / 1024) . " KB)\n");
}

echo "Baixando PolyPizza_Airplane.glb...\n";
$fp = fopen($outputFile, 'w');
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

if ($httpCode !== 200) {
    unlink($outputFile);
    die("ERRO: HTTP $httpCode ao baixar $url\n");
}

echo "OK: PolyPizza_Airplane.glb (" . round(filesize($outputFile) / 1024) . " KB)\n";
echo "Licença: CC-BY 3.0 — https://poly.pizza/m/8ciDd9k8wha\n";
