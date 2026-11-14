// Cloudflare Pages Function: equivalente ao chunks.php quando o site roda no Cloudflare
// Pages (lá não existe PHP). Responde no MESMO caminho "/chunks.php" — ver proxy.php.js.
// Busca o índice de histórico (chunks.json) do tar1090; os arquivos .gz listados nele o
// navegador baixa direto, porque esses já vêm com CORS liberado.
const URL_ORIGEM = 'https://adsb.trafegoaereo.com/tar1090/chunks/chunks.json';

export async function onRequestGet() {
  const cabecalhos = {
    'Access-Control-Allow-Origin': '*',
    'Content-Type': 'application/json',
    'Cache-Control': 'no-cache, no-store, must-revalidate',
  };
  try {
    const resp = await fetch(URL_ORIGEM, {
      headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) tracking-app' },
      cf: { cacheTtl: 30, cacheEverything: true }, // o índice só muda a cada poucos minutos
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return new Response(resp.body, { headers: cabecalhos });
  } catch (_) {
    return new Response(JSON.stringify({ chunks: [], chunks_all: [], erro: 'Falha ao buscar índice de histórico' }), { status: 502, headers: cabecalhos });
  }
}
