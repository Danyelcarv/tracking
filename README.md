# Tracking ADS-B — Rastreador de tráfego aéreo (2D + 3D)

Rastreador de tráfego aéreo ADS-B em tempo real: mapa 2D ao vivo (Leaflet) com modo 3D (Three.js) acionável pelo botão "🌐 Modo 3D". Dados ao vivo do feed público `adsb.trafegoaereo.com/tar1090`.

**Online:** https://trackingcarv.pages.dev/

## ⚠️ Requisito obrigatório: servidor PHP local

**Este projeto NÃO funciona abrindo `index.html` direto no navegador (duplo-clique / `file:///...`).**

O motivo: o navegador bloqueia por CORS qualquer `fetch()` feito a partir de uma página `file://` (origem `null`), e os arquivos `.php` do projeto (proxies que evitam CORS com os dados ADS-B) só são processados por um servidor com PHP — abertos direto, o navegador tenta baixá-los como texto em vez de executá-los. Sem isso, nada carrega: nem aeronaves, nem aeroportos, nem o modelo 3D do avião.

É obrigatório servir a pasta via **Apache + PHP** (ex. XAMPP, WAMP, Laragon) ou qualquer servidor HTTP com suporte a PHP.

## Como rodar (XAMPP)

1. Instale o [XAMPP](https://www.apachefriends.org/).
2. Copie a pasta inteira do projeto para dentro de `htdocs`, por exemplo:
   `C:\xampp\htdocs\tracking`
3. Abra o **XAMPP Control Panel** e clique em **Start** no módulo **Apache**.
4. Acesse no navegador: **http://localhost/tracking/**

   Nunca abra `index.html` diretamente pelo Explorer/Finder.

### Alternativa (sem XAMPP): servidor embutido do PHP

Se já tiver PHP instalado, dentro da pasta do projeto:

```
php -S localhost:8000
```

E acesse `http://localhost:8000/`.

## Primeira execução — gerar os arquivos de cache (opcional)

O projeto já é entregue com `aircraft_map.json` e `airports.json` prontos. Caso precise regenerá-los (dados desatualizados, por exemplo), rode uma vez cada script via navegador ou CLI:

```
http://localhost/tracking/mapear_aeronaves.php    (gera aircraft_map.json — base de aeronaves por ICAO hex, da OpenSky)
http://localhost/tracking/mapear_aeroportos.php   (gera airports.json — aeroportos/pistas/elevação, da OurAirports)
http://localhost/tracking/baixar_modelo_aviao.php (baixa PolyPizza_Airplane.glb, modelo 3D do avião)
```

Esses três passos baixam dados externos uma única vez e salvam localmente — não precisam ser repetidos a cada execução, só se os arquivos gerados forem apagados.

## Publicação online (Cloudflare Pages, gratuito)

No Cloudflare Pages não existe PHP. Os dois proxies usados em tempo de execução têm uma versão em JavaScript na pasta `functions/` (Cloudflare Pages Functions), que responde nos **mesmos caminhos** (`/proxy.php` e `/chunks.php`) — por isso o `index.html` é o mesmo no XAMPP e online, sem nenhuma configuração. O `_routes.json` faz só esses dois caminhos acionarem as funções (o resto é servido como arquivo estático e não conta no limite gratuito).

Configuração (uma vez só):

1. Crie uma conta gratuita em [dash.cloudflare.com](https://dash.cloudflare.com/sign-up) (não pede cartão).
2. **Workers & Pages → Create → Pages → Connect to Git**, autorize o GitHub e escolha o repositório `tracking`.
3. Nas configurações de build: **Framework preset:** `None`, **Build command:** vazio, **Build output directory:** `/`.
4. **Save and Deploy**. O site fica em `https://<nome-do-projeto>.pages.dev` — este projeto está em https://trackingcarv.pages.dev/.

Depois disso, todo `git push` no `main` publica automaticamente.

Limite gratuito: 100 mil chamadas de função por dia. Cada visitante com a página aberta consulta `/proxy.php` a cada ~3s (~1.200 chamadas/hora), ou seja, cerca de 80 "visitante-horas" por dia. As funções guardam a resposta por 2s no cache do Cloudflare, então vários visitantes simultâneos não multiplicam as requisições ao trafegoaereo.

## Arquivos do projeto

| Arquivo | Função |
|---|---|
| `index.html` | Página única: mapa 2D ao vivo (Leaflet) + cena 3D (Three.js). |
| `proxy.php` | Proxy que busca `aircraft.json` do tar1090 (evita CORS). |
| `chunks.php` | Proxy do histórico de posições (trilhas de voo no modo 3D). |
| `functions/proxy.php.js`, `functions/chunks.php.js` | Versões dos dois proxies para o Cloudflare Pages (ver "Publicação online"). |
| `_routes.json` | Restringe as funções do Cloudflare Pages a `/proxy.php` e `/chunks.php`. |
| `mapear_aeronaves.php` | Gera `aircraft_map.json` (modelo/fabricante por aeronave). |
| `mapear_aeroportos.php` | Gera `airports.json` (aeroportos, pistas, elevação). |
| `baixar_modelo_aviao.php` | Baixa o modelo 3D do avião (`PolyPizza_Airplane.glb`). |
| `aircraft_map.json`, `airports.json`, `PolyPizza_Airplane.glb` | Dados/assets já gerados, prontos para uso. |
| `modelo_aviao_grande.glb`, `modelo_jatinho.glb`, `modelo_aviao_helice.glb`, `modelo_helicoptero.glb` | Modelos 3D dos outros tipos de aeronave (já adaptados: só geometria, nariz pra frente, mesmo tamanho). |
| `contexto.md` | Documento de referência da arquitetura do projeto (histórico técnico). |

## Créditos

- Dados ADS-B: [tar1090 / adsb.trafegoaereo.com](https://adsb.trafegoaereo.com)
- Base de aeronaves: [OpenSky Network](https://opensky-network.org/)
- Aeroportos/pistas: [OurAirports](https://ourairports.com/)
- Tiles de mapa: OpenStreetMap, Esri
- Modelos 3D (todos via Poly Pizza, CC-BY 3.0, adaptados — sem textura, reorientados e redimensionados):
  - Avião comercial: ["Airplane"](https://poly.pizza/m/8ciDd9k8wha) por Poly by Google
  - Avião grande: ["Boeing 747"](https://poly.pizza/m/49CLof4tP2V) por Miha Lunar
  - Jatinho: ["Jet"](https://poly.pizza/m/bgUY8zN2Bq9) por Poly by Google
  - Avião a hélice: ["Small Airplane"](https://poly.pizza/m/7cvx6ex-xfL) por Vojtěch Balák
  - Helicóptero: ["Helicopter"](https://poly.pizza/m/eb7b31pjGtQ) por jeremy
