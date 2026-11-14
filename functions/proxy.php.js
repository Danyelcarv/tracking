// Cloudflare Pages Function: equivalente ao proxy.php quando o site roda no Cloudflare
// Pages (lá não existe PHP). Responde no MESMO caminho "/proxy.php", então o index.html
// não precisa saber onde está rodando — no XAMPP quem responde é o proxy.php de verdade.
// Busca o aircraft.json do tar1090 (o servidor deles não envia cabeçalho CORS).
const URL_ORIGEM = 'https://adsb.trafegoaereo.com/tar1090/data/aircraft.json';

export async function onRequestGet() {
  const cabecalhos = {
    'Access-Control-Allow-Origin': '*',
    'Content-Type': 'application/json',
    'Cache-Control': 'no-cache, no-store, must-revalidate',
  };
  try {
    const resp = await fetch(URL_ORIGEM, {
      headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) tracking-app' },
      // cache curto na borda do Cloudflare: vários visitantes ao mesmo tempo geram UMA
      // requisição ao trafegoaereo a cada 2s, em vez de uma por visitante a cada 3s
      cf: { cacheTtl: 2, cacheEverything: true },
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return new Response(resp.body, { headers: cabecalhos });
  } catch (_) {
    return new Response(JSON.stringify({ erro: 'Falha ao buscar dados do TrafegoAereo' }), { status: 502, headers: cabecalhos });
  }
}
