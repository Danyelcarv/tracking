# Contexto do projeto — Tracking ADS-B

Documento de referência para retomar o trabalho neste projeto no futuro. Descreve o que existe, por que existe, e o histórico de decisões tomadas.

## Visão geral

Rastreador de tráfego aéreo ADS-B em tempo real, **tudo numa única página** (`index.html`):
- **Mapa 2D ao vivo** (Leaflet) — modo padrão ao abrir a página.
- **Modo 3D** (Three.js) — acionado pelo botão "🌐 Modo 3D" no rodapé; a seleção do centro/raio acontece sobre o PRÓPRIO mapa 2D ao vivo (não é mais uma página/mini-mapa separado — ver "Fusão em página única" abaixo).

Dados ao vivo vêm de `adsb.trafegoaereo.com/tar1090` (feed público ADS-B/MLAT do Brasil), acessado via proxy PHP local para evitar CORS.

## Arquivos

| Arquivo | Função |
|---|---|
| `index.html` | Página única: mapa 2D ao vivo (Leaflet) + cena 3D (Three.js), alternados por JS (sem navegação de página). Ver detalhes abaixo. |
| `proxy.php` | Proxy que busca `aircraft.json` do tar1090 (evita CORS). Usado tanto pelo modo 2D quanto pelo 3D. |
| `mapear_aeronaves.php` | Script um-tiro: baixa `aircraftDatabase.csv` da OpenSky, gera `aircraft_map.json` (modelo/fabricante por ICAO hex), filtrado por prefixos brasileiros (e4, e8). |
| `aircraft_map.json` | Cache gerado pelo script acima (~4000 aeronaves). |
| `mapear_aeroportos.php` | Baixa `airports.csv` e `runways.csv` da OurAirports, gera `airports.json` com aeroportos grandes/médios do Brasil + pistas + elevação. |
| `airports.json` | Cache gerado (138 aeroportos, 70 pistas, elevação em pés). |
| `chunks.php` | Proxy do índice de histórico (`chunks/chunks.json`) do tar1090 — usado pelo modo 3D para reconstruir trilhas de voo de até ~1h. |
| `baixar_modelo_aviao.php` | Baixa uma vez o modelo 3D do avião (`PolyPizza_Airplane.glb`) para servir localmente. |
| `PolyPizza_Airplane.glb` | Modelo 3D do jato comercial usado no modo 3D (ver "Modelo 3D" abaixo). |
| `contexto.md` | Este arquivo. |

## Fusão em página única (histórico da mudança)

Originalmente o modo 3D vivia num arquivo separado (`radar3d.html`), com seu próprio mini-mapa Leaflet estático só para escolher o centro/raio antes de entrar na cena 3D — sem o monitoramento ao vivo. O usuário pediu para unificar: o `index.html` (mapa 2D ao vivo) passou a ser também a tela de seleção do modo 3D, e `radar3d.html` foi **apagado**.

### Como funciona agora
- Botão **"🌐 Modo 3D"** no rodapé chama `ativarModo3D()`: mostra um painel flutuante (`#modo3d-painel`, na parte de baixo da tela) com seletor de raio (30–150km) + mostra os marcadores de aeroportos (`#airports.json`) sobre o mapa **que continua ao vivo, com os aviões se movendo**.
- Clicar num aeroporto OU em qualquer ponto do mapa chama `definirCentro3D(...)`, habilitando o botão "Entrar na visualização 3D".
- Ao confirmar: `clearInterval(intervalo2D)` pausa o polling 2D, esconde `#map`/`#info-bar`/`#legend-2d`, mostra `#cena3d`, chama `iniciarCena3D(centro, raio)`.
- Botão **"◀ Voltar ao mapa 2D"** desfaz tudo: destrói a cena 3D (renderer.dispose, limpa intervals), reexibe o mapa 2D e **retoma o polling** (`intervalo2D = setInterval(atualizar, INTERVALO)`).

### Cuidado com colisão de IDs/variáveis ao mexer nisso
Os dois fluxos (2D e 3D) compartilham o mesmo `<script>`, então nomes que existiam duplicados nos dois arquivos originais foram renomeados pra não colidir:
- `#info-bar` → `#r3d-info-bar` (3D) continua existindo só dentro de `#cena3d`; o `#info-bar` original é do 2D.
- `#seguir-bar` / `#seguir-callsign` / `#seguir-cancelar` (2D, "seguir" por duplo-clique no mapa) vs `#r3d-seguir-bar` / `#r3d-seguir-callsign` / `#r3d-seguir-cancelar` (3D, botão "Seguir" no painel da aeronave).
- `seguindoHex` (variável JS, 2D) vs `seguindoHex3D` (3D) — **são conceitos diferentes, não renomeie um pensando que é o outro**.
- `.legend` é uma classe (não colide), mas tem dois elementos com IDs distintos: `#legend-2d` e `#legend-3d`, alternados junto com o resto da UI de cada modo.
- O mapa Leaflet (`map`) é UM SÓ, reaproveitado tanto pro monitoramento normal quanto pra seleção do centro 3D — não existe mais um `miniMap` separado.

## Modo 3D — detalhes

### Sistema de coordenadas
- Plano local tangente (ENU), **unidades da cena = QUILÔMETROS** (não metros — decisão importante, ver "Bugs resolvidos").
- `x` (leste) = `(lon - lon0) * 111.32 * cos(lat0)`
- `z` (norte, invertido) = `-(lat - lat0) * 110.54`
- `y` (altura) = altitude acima da elevação do aeroporto (não do nível do mar — ver bugs), em km, com fator de exagero `ALT_EXAGERO` para ficar visualmente perceptível.

### Terreno
- Imagem de satélite real: busca tiles do Esri World_Imagery (mesmos usados no mapa 2D), monta um canvas combinando-os, aplica como textura num plano 3D alinhado geograficamente. Zoom dos tiles varia por raio selecionado (30km→z11 até 150km→z8).
- Pistas reais desenhadas quando o centro é um aeroporto com dados de runway (linha branca + linha central amarela, largura com piso mínimo proporcional ao raio pra não ficar invisível).

### Aeronaves
- Renderizadas via `THREE.InstancedMesh` (uma malha por faixa de cor/altitude: solo/baixa/média/alta/mlat), para manter poucas draw calls.
- Filtradas por distância haversine ao centro a cada poll (~3.5s via `proxy.php`).
- Cor por altitude (mesma lógica do mapa 2D), orientação (yaw) pelo campo `track`.

### Seleção, painel e trilha
- Clique (raycaster) seleciona uma aeronave → abre painel lateral à direita com dados do voo + botões **Seguir** e **Trilha**.
- Trilha abre **automaticamente** ao selecionar, combinando:
  - Histórico real (até ~1h) reconstruído dos "chunks" de snapshot do tar1090 (`chunks.php` + fetch direto dos `.gz`, CORS já liberado nesses arquivos).
  - Trilha ao vivo acumulada desde que a aba foi aberta (sem limite de pontos — removido o limite artificial anterior).
- Modo **Seguir**: câmera em chase-cam atrás/acima da aeronave; só reposiciona o `target` do OrbitControls a cada frame, preservando o zoom/ângulo que o usuário ajustar manualmente (zoom/pan não cancelam mais o seguir).

### Bússola
- Fita horizontal no topo (estilo HUD), mostra o rumo atual da câmera (N/NE/E/SE/S/SW/W/NW), atualizada a cada frame via `camera.getWorldDirection()`.

## Modelo 3D do avião — histórico da decisão

1. Primeira versão: geometrias proceduais simples (cones/caixas) por categoria (avião/jato/helicóptero/balão/turboprop).
2. Usuário pediu modelos "de verdade". Pesquisa descobriu que repositórios alegados (CesiumGS/cesium-assets, "AGI Sample Models", mapbox-gl-js) **não existem como descrito** — eram alucinações/exageros.
3. Testamos o modelo real oficial do CesiumJS (`CesiumAir`, Apache-2.0) — só depois de integrado percebemos visualmente que é na verdade um **turboprop bimotor**, não um jato.
4. Nova busca encontrou candidatos reais em **poly.pizza** (CC-BY 3.0). O modelo **"Airplane"** (`https://poly.pizza/m/8ciDd9k8wha`) foi confirmado visualmente como um jato comercial de verdade (asas em flecha, motor sob a asa, cauda).
5. **Decisão final**: usar só esse modelo (`PolyPizza_Airplane.glb`), sem textura (geometria pura, recolorida por altitude como as outras), para todas as aeronaves — simplificando de volta pra uma única categoria `plane` (removida a diferenciação por tipo).
6. Créditos no rodapé da página (licença CC-BY 3.0 exige atribuição).

## Bugs encontrados e corrigidos (histórico)

1. **Camadas de mapa CartoDB quebradas** (`index.html`): CARTO descontinuou acesso anônimo aos basemaps raster. Substituídas por tiles Esri Canvas (World_Light_Gray_Base / World_Dark_Gray_Base), que são gratuitos e não exigem key.
2. **Altitude da aeronave "flutuando" acima da pista**: `alt_baro` do ADS-B é altitude acima do **nível do mar**, não da pista. Corrigido descontando a elevação do aeroporto (`airports.json` agora inclui `elev` em pés).
3. **Modo "Seguir" cancelava com zoom/pan**: causa dupla — (a) `controls.enabled = false` travava a câmera inteira; (b) um arrasto de órbita terminava disparando `click`, interpretado como clique vazio que fechava a seleção. Corrigido: só o `target` é reposicionado a cada frame (zoom/ângulo livres), e cliques são diferenciados de arrastos pela distância percorrida entre `pointerdown` e `click`.
4. **Terreno/pista "sem mudança visual"**: o problema real era contraste insuficiente (cor do terreno quase igual ao céu) e a pista real ser fisicamente minúscula perto do raio da cena (30–150km) — ficava sub-pixel. Corrigido com cores mais contrastantes e um piso mínimo de largura para a pista.
5. **"Gaiola" branca ao redor da aeronave selecionada**: era um `marcadorSelecao` wireframe de destaque. Removido — o destaque hoje é só o painel lateral + trilha.
6. **Asas do modelo 3D "sumindo" ou distorcendo** (o mais sutil): não era bug de orientação/categoria, era **z-fighting por alcance dinâmico de profundidade excessivo** da câmera (near muito pequeno vs. far de até centenas de km). Corrigido habilitando `logarithmicDepthBuffer: true` no `WebGLRenderer`, combinado com a mudança de unidades da cena de metros para quilômetros (reduz a magnitude das coordenadas, ajuda a precisão geral do float32 do WebGL).
7. **Condição de corrida no carregamento assíncrono do modelo**: se a cena 3D era aberta antes do `.glb` terminar de baixar, ficava presa no fallback procedural para sempre. Corrigido: ao terminar de carregar, o modelo é aplicado retroativamente na cena já aberta (`aplicarModeloAviaoNaCenaAtual`).
8. **Aeronave "flutuando" acima da pista em ponto customizado perto de um aeroporto**: ao clicar em qualquer ponto do mapa que não fosse exatamente o ícone do aeroporto, `elevacaoFt` caía pro padrão 0 (nível do mar) — perto de um aeroporto como SBBR (elevação ~3500 pés), a aeronave aparecia flutuando bem acima do chão. Corrigido em `definirCentro3D`: pontos customizados sem elevação própria agora herdam a elevação do aeroporto conhecido mais próximo dentro de `RAIO_ELEVACAO_REF_KM` (30km); sem aeroporto por perto, cai pra 0 como antes.

## Decisões de escopo (perguntas já respondidas)

- **Trilha histórica**: o feed do tar1090 usado não expõe endpoint de trace por aeronave (`trace_full_*.json` retorna 404) — só os "chunks" periódicos de snapshot de todas as aeronaves, com até ~1h de cobertura. Não há fonte gratuita de histórico mais longo sem mudar de provedor.
- **Elevação de terreno em ponto customizado**: não há fonte de elevação de terreno (DEM) real integrada. Pontos customizados herdam a elevação do aeroporto conhecido mais próximo (até 30km, ver bug #8 acima); sem nenhum aeroporto por perto, assume nível do mar.
- **Modelos 3D por tipo específico** (A320 vs 737 vs ATR...): não há biblioteca gratuita, leve e confiável com modelos por fabricante/tipo. Optou-se por um único modelo genérico de jato para todas as aeronaves.

## Git

Repositório: `https://github.com/Danyelcarv/tracking` (branch `main`). Upload inicial feito manualmente pela interface web do GitHub (conta diferente da configurada localmente); o git local foi depois sincronizado (`git remote add origin` + `git reset --soft origin/main`) para apontar pro mesmo commit do remoto.
