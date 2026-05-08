/* Balochistan Mineral Map — Pure SVG Clone
   Black bg, grey province, district borders, colored dots */
(function(){
'use strict';

function init(){
  var el = document.getElementById('balochistan-mineral-map');
  if(!el) return;

  // viewBox maps roughly to lon 60-71, lat 24.5-32 => x:0-1100, y:0-750
  function geoX(lon){ return ((lon - 60) / 11) * 1100; }
  function geoY(lat){ return ((32 - lat) / 7.5) * 750; }

  var COLORS = {
    'Coal':'#4A90D9','Marble':'#27AE60','Limestone':'#F1C40F','Iron':'#E74C3C',
    'Chromite':'#C0392B','Shale':'#7F8C8D','Serpentine':'#6B8E23','Manganese':'#8E44AD',
    'Barite':'#9B59B6','Pumice':'#1ABC9C','Marble Onyx':'#2ECC71','Travertine':'#D35400',
    'Sulphur':'#F39C12','Granite':'#00E676','Fluorite':'#E91E63','Copper':'#FF6D00',
    'Basalt':'#1A237E','Calcite':'#81D4FA','Mangasite':'#FF80AB'
  };

  // Balochistan province outline (approximate polygon)
  var outline = [
    [62.0,25.2],[62.5,25.1],[63.0,25.5],[64.0,25.8],[64.5,25.6],[65.0,26.0],
    [65.5,25.8],[66.2,25.5],[66.8,25.6],[67.0,25.8],[67.3,26.5],[67.5,27.0],
    [67.8,27.5],[68.0,28.0],[68.5,28.3],[69.0,28.5],[69.5,29.0],[69.8,29.3],
    [70.0,29.5],[70.3,29.8],[70.5,30.2],[70.4,30.5],[70.2,30.8],[70.0,31.0],
    [69.8,31.2],[69.5,31.5],[69.0,31.6],[68.5,31.5],[68.0,31.3],[67.8,31.0],
    [67.5,30.9],[67.0,30.7],[66.8,30.6],[66.5,30.5],[66.2,30.4],[66.0,30.2],
    [65.8,29.8],[65.5,29.5],[65.0,29.3],[64.5,29.0],[64.0,29.3],[63.5,29.5],
    [63.0,29.3],[62.5,29.0],[62.0,28.8],[61.5,28.5],[61.0,28.0],[60.8,27.5],
    [61.0,27.0],[61.2,26.5],[61.5,26.0],[62.0,25.2]
  ];

  // District boundaries — comprehensive partition lines (solid, visible)
  var districtLines = [
    // ── HORIZONTAL DIVISIONS (west to east) ──
    // Chagai south border
    [[62.0,28.8],[63.5,29.0],[64.5,29.0],[65.0,29.3]],
    // Kharan-Washuk south / Panjgur-Awaran north
    [[61.0,28.0],[62.5,27.5],[63.5,27.2],[64.5,27.0],[65.5,26.8]],
    // Kech-Gwadar / Awaran-Lasbela south
    [[62.0,25.8],[63.0,26.2],[64.0,26.0],[65.0,26.0],[65.5,25.8]],
    // Sibi-Nasirabad horizontal
    [[67.0,29.2],[67.8,29.0],[68.5,28.8],[69.0,28.5]],
    // Bolan-Jhal Magsi south
    [[66.5,28.5],[67.2,28.2],[67.8,28.0],[68.0,28.0]],
    // Quetta-Pishin border
    [[66.2,30.4],[66.8,30.4],[67.0,30.5]],
    // Ziarat-Harnai-Duki horizontal
    [[67.0,30.5],[67.5,30.2],[68.0,30.2],[68.5,30.0]],
    // Loralai-Barkhan horizontal
    [[68.5,30.0],[69.0,30.0],[69.5,29.8],[69.8,29.6]],
    // Zhob-Musakhel south
    [[69.0,31.0],[69.5,31.0],[70.0,31.0],[70.2,30.8]],

    // ── VERTICAL DIVISIONS (north to south) ──
    // Nushki-Kharan / Chagai-Kharan
    [[64.5,29.0],[64.8,28.5],[65.0,27.5],[65.2,26.8]],
    // Kharan-Washuk west
    [[63.5,29.0],[63.5,28.0],[63.5,27.2],[63.5,26.5]],
    // Kech west border
    [[62.0,28.8],[61.8,27.8],[61.5,26.5],[62.0,25.8]],
    // Qalat-Khuzdar / Mastung-Khuzdar
    [[65.8,29.8],[66.0,29.0],[66.2,28.0],[66.5,27.2],[66.8,26.5],[67.0,25.8]],
    // Nushki-Qalat border
    [[65.0,29.3],[65.5,29.5],[65.8,29.8]],
    // Quetta-Sibi / Bolan corridor
    [[66.8,30.4],[67.0,30.0],[67.2,29.5],[67.5,29.0],[67.5,28.5],[67.5,27.5],[67.5,27.0]],
    // Sibi-Kohlu
    [[67.8,29.0],[68.2,29.2],[68.5,29.5],[68.5,30.0]],
    // Kohlu-Dera Bugti
    [[69.0,28.5],[69.2,29.0],[69.3,29.5],[69.5,29.8]],
    // Loralai-Zhob
    [[68.5,30.0],[68.8,30.5],[69.0,31.0],[69.0,31.6]],
    // Zhob-Musakhel
    [[69.5,31.5],[69.8,31.0],[70.0,31.0]],
    // Mastung pocket
    [[66.2,30.0],[66.5,29.8],[66.8,29.5],[67.0,29.2]],
    // Lasbela east
    [[67.5,27.0],[67.8,26.5],[67.3,26.5],[67.0,25.8]],
    // Awaran boundaries
    [[65.2,26.8],[65.5,26.0],[65.5,25.8]],
    // Nasirabad-Jhal Magsi
    [[67.8,28.0],[67.5,28.5],[67.2,28.2]],
  ];

  // District labels
  var labels = [
    ['Chagai',63.3,29.1],['Kharan',65.0,28.3],['Washuk',64.0,27.5],
    ['Panjgur',63.5,26.8],['Kech',62.5,26.2],['Gwadar',62.2,25.5],
    ['Awaran',65.0,26.2],['Lasbela',67.0,26.2],['Khuzdar',66.5,27.8],
    ['Qalat',66.3,29.2],['Mastung',66.7,29.6],['Nushki',65.8,29.6],
    ['Quetta',66.8,30.2],['Pishin',66.8,30.55],['Qilla\nSaifullah',67.6,30.9],
    ['Zhob',69.3,31.3],['Ziarat',67.5,30.3],['Harnai',67.9,30.05],
    ['Loralai',68.5,30.4],['Duki',68.4,30.1],['Barkhan',69.6,29.8],
    ['Musakhel',70.1,30.8],['Kohlu',69.0,29.8],['Dera\nBugti',69.0,29.0],
    ['Sibi',67.7,29.5],['Bolan',67.4,29.7],['Nasirabad',68.2,28.4],
    ['Jhal\nMagsi',67.1,28.4]
  ];

  // Mineral deposits
  var deposits = [
    [63.55,29.25,'Copper','Chagai'],[63.0,29.10,'Copper','Chagai'],
    [63.80,29.35,'Iron','Chagai'],[62.50,28.90,'Sulphur','Chagai'],
    [64.10,29.45,'Pumice','Chagai'],[65.40,28.55,'Marble','Kharan'],
    [66.00,29.55,'Marble Onyx','Nushki'],[66.97,30.18,'Limestone','Quetta'],
    [67.15,30.25,'Coal','Quetta'],[66.99,30.58,'Chromite','Pishin'],
    [67.73,30.80,'Chromite','Muslim Bagh'],[67.90,30.90,'Serpentine','Qilla Saifullah'],
    [67.60,30.70,'Manganese','Qilla Saifullah'],[69.45,31.35,'Chromite','Zhob'],
    [69.20,31.25,'Coal','Zhob'],[67.73,30.38,'Coal','Ziarat'],
    [67.94,30.10,'Coal','Harnai'],[68.10,30.15,'Marble','Harnai'],
    [68.60,30.37,'Coal','Loralai'],[68.75,30.45,'Chromite','Loralai'],
    [68.57,30.15,'Coal','Duki'],[67.60,29.80,'Limestone','Bolan'],
    [67.88,29.54,'Coal','Sibi'],[69.25,29.90,'Marble','Kohlu'],
    [66.60,29.40,'Limestone','Qalat'],[66.85,29.80,'Marble','Mastung'],
    [66.62,27.80,'Barite','Khuzdar'],[66.40,27.65,'Marble','Khuzdar'],
    [66.80,27.95,'Fluorite','Khuzdar'],[66.55,28.10,'Manganese','Khuzdar'],
    [66.90,27.70,'Travertine','Khuzdar'],[66.50,26.20,'Marble','Lasbela'],
    [66.65,26.35,'Iron','Lasbela'],[66.30,26.10,'Limestone','Lasbela'],
    [66.80,26.50,'Granite','Lasbela'],[66.45,26.30,'Chromite','Lasbela'],
    [66.70,26.15,'Copper','Lasbela'],[66.55,26.45,'Basalt','Lasbela'],
    [66.38,26.25,'Calcite','Lasbela'],[65.23,26.45,'Marble Onyx','Awaran'],
    [64.20,27.70,'Granite','Washuk'],[64.10,26.95,'Marble','Panjgur'],
    [63.05,26.00,'Marble','Kech'],[62.33,25.30,'Limestone','Gwadar'],
    [69.52,29.90,'Coal','Barkhan'],[69.70,29.80,'Shale','Barkhan'],
    [70.30,30.90,'Coal','Musakhel'],[69.15,29.05,'Sulphur','Dera Bugti'],
    [68.35,28.50,'Limestone','Nasirabad'],[67.20,28.50,'Mangasite','Jhal Magsi']
  ];

  // Build SVG
  var svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
  svg.setAttribute('viewBox','50 30 1020 680');
  svg.setAttribute('preserveAspectRatio','xMidYMid meet');
  svg.style.cssText = 'width:100%;height:100%;display:block;background:#111;';

  // Province fill
  var pts = outline.map(function(p){return geoX(p[0])+','+geoY(p[1]);}).join(' ');
  var poly = document.createElementNS('http://www.w3.org/2000/svg','polygon');
  poly.setAttribute('points',pts);
  poly.setAttribute('fill','#1e1e24');
  poly.setAttribute('stroke','#555');
  poly.setAttribute('stroke-width','2');
  svg.appendChild(poly);

  // District lines
  districtLines.forEach(function(line){
    var d = 'M'+line.map(function(p){return geoX(p[0])+' '+geoY(p[1]);}).join('L');
    var path = document.createElementNS('http://www.w3.org/2000/svg','path');
    path.setAttribute('d',d);
    path.setAttribute('fill','none');
    path.setAttribute('stroke','#555');
    path.setAttribute('stroke-width','1.2');
    svg.appendChild(path);
  });

  // Tooltip div
  var tip = document.createElement('div');
  tip.style.cssText = 'position:absolute;display:none;background:rgba(9,9,11,0.95);border:1px solid rgba(255,255,255,0.12);border-radius:6px;padding:10px 14px;color:#fff;font-family:var(--font-ui,system-ui);pointer-events:none;z-index:20;max-width:220px;box-shadow:0 8px 24px rgba(0,0,0,0.6);';
  el.parentElement.style.position = 'relative';
  el.parentElement.appendChild(tip);

  // Mineral dots (rendered BEFORE labels so labels sit on top)
  deposits.forEach(function(d){
    var cx = geoX(d[0]), cy = geoY(d[1]);
    var color = COLORS[d[2]]||'#888';

    // Glow
    var glow = document.createElementNS('http://www.w3.org/2000/svg','circle');
    glow.setAttribute('cx',cx);glow.setAttribute('cy',cy);glow.setAttribute('r','14');
    glow.setAttribute('fill',color);glow.setAttribute('opacity','0.18');
    svg.appendChild(glow);

    // Dot
    var dot = document.createElementNS('http://www.w3.org/2000/svg','circle');
    dot.setAttribute('cx',cx);dot.setAttribute('cy',cy);dot.setAttribute('r','7');
    dot.setAttribute('fill',color);dot.setAttribute('stroke','rgba(255,255,255,0.25)');
    dot.setAttribute('stroke-width','1.5');dot.style.cursor='pointer';
    dot.setAttribute('data-mineral',d[2]);dot.setAttribute('data-district',d[3]);

    dot.addEventListener('mouseenter',function(e){
      dot.setAttribute('r','10');
      glow.setAttribute('r','20');glow.setAttribute('opacity','0.3');
      tip.innerHTML='<div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">'+d[3]+'</div><div style="font-size:11px;font-weight:700;color:'+color+';text-transform:uppercase;letter-spacing:0.08em;margin:3px 0;">'+d[2]+'</div>';
      tip.style.display='block';
    });
    dot.addEventListener('mousemove',function(e){
      var rect = el.parentElement.getBoundingClientRect();
      tip.style.left=(e.clientX-rect.left+12)+'px';
      tip.style.top=(e.clientY-rect.top-10)+'px';
    });
    dot.addEventListener('mouseleave',function(){
      dot.setAttribute('r','7');glow.setAttribute('r','14');glow.setAttribute('opacity','0.18');
      tip.style.display='none';
    });
    svg.appendChild(dot);
  });

  // District labels — rendered LAST so they appear on top of dots
  labels.forEach(function(l){
    var t = document.createElementNS('http://www.w3.org/2000/svg','text');
    t.setAttribute('x',geoX(l[1]));
    t.setAttribute('y',geoY(l[2]));
    t.setAttribute('text-anchor','middle');
    t.setAttribute('fill','#fff');
    t.setAttribute('font-size','13');
    t.setAttribute('font-family','var(--font-ui,system-ui)');
    t.setAttribute('font-weight','700');
    t.setAttribute('letter-spacing','0.08em');
    t.setAttribute('paint-order','stroke');
    t.setAttribute('stroke','#111');
    t.setAttribute('stroke-width','3');
    var lines = l[0].split('\n');
    lines.forEach(function(ln,i){
      var ts = document.createElementNS('http://www.w3.org/2000/svg','tspan');
      ts.setAttribute('x',geoX(l[1]));
      if(i>0) ts.setAttribute('dy','14');
      ts.textContent = ln;
      t.appendChild(ts);
    });
    svg.appendChild(t);
  });

  el.innerHTML='';
  el.appendChild(svg);
}

if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
