// Gera as imagens do tutorial do Modo 3D (tutorial/passo1..7.webp) a partir do próprio
// site rodando localmente: abre a página num Chrome automatizado, executa cada passo e
// tira um print com o elemento do passo destacado (o resto da tela escurecido).
//
// Refazer quando a interface mudar. Requisitos: site no ar em http://localhost/tracking/
// (XAMPP), Google Chrome instalado e o pacote puppeteer-core:
//   npm install puppeteer-core
//   node ferramentas/gerar_imagens_tutorial.js
// (CHROME=<caminho do chrome.exe> e URL=<endereço do site> mudam os padrões abaixo.)
const puppeteer = require('puppeteer-core');
const path = require('path');

const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const URL_SITE = process.env.URL || 'http://localhost/tracking/';
const PASTA_SAIDA = path.join(__dirname, '..', 'tutorial');
const espera = ms => new Promise(r => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader'],
    // 1280x800 renderizado a 0,75 => imagens 960x600 (16:10, como a moldura do tutorial)
    defaultViewport: { width: 1280, height: 800, deviceScaleFactor: 0.75 },
  });
  const page = await browser.newPage();
  // não abre o próprio tutorial por cima dos prints
  await page.evaluateOnNewDocument(() => localStorage.setItem('tutorial3dVisto', '1'));
  await page.goto(URL_SITE + '?t=' + Date.now(), { waitUntil: 'networkidle2', timeout: 60000 });
  // A foto do Planespotters não entra nas imagens: os termos deles proíbem salvar/re-hospedar
  // as fotos (e o Chrome automatizado nem consegue carregá-las).
  await page.addStyleTag({ content: '#painel-foto { display: none !important; }' });
  await page.evaluate(() => {
    // destaque: escurece a tela inteira menos os retângulos dados, com borda amarela neles
    window.destacar = (seletores, folga = 8) => {
      const antigo = document.getElementById('destaque-tutorial');
      if (antigo) antigo.remove();
      if (!seletores || !seletores.length) return;
      const rets = seletores.map(s => document.querySelector(s).getBoundingClientRect())
        .map(r => ({ x: r.left - folga, y: r.top - folga, w: r.width + 2 * folga, h: r.height + 2 * folga }));
      const W = innerWidth, H = innerHeight;
      const buracos = rets.map(r => `<rect x="${r.x}" y="${r.y}" width="${r.w}" height="${r.h}" rx="10" fill="black"/>`).join('');
      const bordas = rets.map(r => `<rect x="${r.x}" y="${r.y}" width="${r.w}" height="${r.h}" rx="10" fill="none" stroke="#f2c94c" stroke-width="4"/>`).join('');
      const div = document.createElement('div');
      div.id = 'destaque-tutorial';
      div.style.cssText = 'position:fixed;inset:0;z-index:2500;pointer-events:none';
      div.innerHTML = `<svg width="${W}" height="${H}"><defs><mask id="furos"><rect width="${W}" height="${H}" fill="white"/>${buracos}</mask></defs>
        <rect width="${W}" height="${H}" fill="rgba(0,0,0,0.5)" mask="url(#furos)"/>${bordas}</svg>`;
      document.body.appendChild(div);
    };
  });
  const foto = async (n, seletores) => {
    await page.evaluate(s => window.destacar(s), seletores || []);
    await espera(300);
    await page.screenshot({ path: path.join(PASTA_SAIDA, `passo${n}.webp`), type: 'webp', quality: 72 });
    await page.evaluate(() => window.destacar([]));
  };

  // 1) botão Modo 3D no mapa 2D (São Paulo, com tráfego)
  await espera(3000);
  await page.evaluate(() => map.setView([-23.55, -46.6], 9, { animate: false }));
  await espera(4000);
  await foto(1, ['#btn-modo-3d']);

  // 2) escolher o centro: Guarulhos, com os aeroportos visíveis
  await page.evaluate(() => {
    ativarModo3D();
    const ap = airportsMap['SBGR'];
    map.setView([ap.lat, ap.lon], 10, { animate: false });
    definirCentro3D(ap.lat, ap.lon, 'SBGR — ' + ap.n, ap.r, ap.elev);
  });
  await espera(2500);
  await page.evaluate(() => { marcadorCentro3D.getElement().id = 'marcador-centro-tutorial'; });
  await foto(2, ['#marcador-centro-tutorial']);

  // 3) raio + entrar
  await foto(3, ['#modo3d-painel']);

  // 4) a cena 3D (visão geral, depois de carregar terreno e histórico)
  await page.evaluate(() => document.getElementById('modo3d-entrar').click());
  for (let i = 0; i < 40 && await page.evaluate(() => historicoStatus !== 'pronto'); i++) await espera(1000);
  await espera(8000);
  await foto(4, ['#bussola', '#mini-mapa-3d', '#legend-3d']);

  // 5) aeronave selecionada: um voo comercial em voo, perto do centro, com bastante histórico
  const hex = await page.evaluate(() => {
    const cands = [...aircraftData.values()].filter(a => a.bruto && typeof a.alt_baro === 'number' && a.alt_baro > 3000
      && callsignComercial(a.bruto) && distKm(centroAtivo.lat, centroAtivo.lon, a.lat, a.lon) < 40);
    cands.sort((x, y) => serieVoo(y.hex).length - serieVoo(x.hex).length);
    return (cands[0] || [...aircraftData.values()][0]).hex;
  });
  await page.evaluate(h => selecionar(h), hex);
  await espera(4500);
  await foto(5, ['#painel-detalhe']);

  // 6) botões Seguir / Trilha do painel
  await foto(6, ['#painel-conteudo .acoes']);

  // 7) rótulos + trilhas de todos (visão geral de novo) e o botão voltar
  await page.evaluate(() => {
    fecharSelecao();
    // câmera mais perto, apontada pra aeronave mais próxima do centro (rótulos legíveis)
    const emVoo = [...aircraftData.values()].filter(a => typeof a.alt_baro === 'number' && a.alt_baro > 2000 && trilhaCompleta(a.hex).length > 20);
    const perto = (emVoo.length ? emVoo : [...aircraftData.values()]).sort((x, y) => x.pos.length() - y.pos.length())[0];
    controls.target.copy(perto ? perto.pos : new THREE.Vector3());
    camera.position.copy(controls.target).add(new THREE.Vector3(0, raioAtivoKm * 0.12, raioAtivoKm * 0.22));
    alternarRotulosAeronaves();
    alternarTodasTrilhas();
  });
  await espera(8000); // rótulos e trilhas aparecem no poll seguinte
  await foto(7, ['#btn-rotulos-aeronaves', '#btn-trilhas-todas', '#btn-voltar']);

  console.log('Imagens geradas em', PASTA_SAIDA, '(aeronave do passo 5:', hex + ')');
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
