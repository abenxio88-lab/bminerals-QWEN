/**
 * Balochistan Minerals Interactive Project Map
 * Powered by Leaflet.js
 */

export function initProjectMap() {
  const mapContainer = document.getElementById('premium-map');
  if (!mapContainer || typeof L === 'undefined') return;

  // Initialize map centered on Balochistan's operating footprint.
  const map = L.map('premium-map', {
    scrollWheelZoom: false,
    zoomControl: false,
    attributionControl: false
  }).setView([29.3, 65.2], 6);

  L.control.zoom({ position: 'bottomright' }).addTo(map);
  L.control.attribution({ position: 'bottomleft', prefix: false }).addTo(map);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
    subdomains: 'abcd',
    maxZoom: 19
  }).addTo(map);

  const routeStyle = {
    className: 'footprint-route-line',
    color: '#c89a62',
    weight: 1.35,
    opacity: 0.48,
    dashArray: '2 10',
    lineCap: 'round'
  };

  const projects = [
    {
      name: "Muslim Bagh",
      coords: [30.8, 67.7],
      commodity: "Chromite",
      type: "Chromite Mine",
      status: "Active Production",
      tone: "chromite",
      radius: 36000
    },
    {
      name: "Khuzdar",
      coords: [27.8, 66.6],
      commodity: "Barite",
      type: "Barite Operations",
      status: "Active Processing",
      tone: "barite",
      radius: 42000
    },
    {
      name: "Chagai",
      coords: [29.2, 61.6],
      commodity: "Copper-Gold",
      type: "Copper-Gold Belt",
      status: "Exploration",
      tone: "copper",
      radius: 52000
    }
  ];

  const ports = [
    { name: 'Karachi Port', coords: [24.86, 67.01] },
    { name: 'Gwadar Port', coords: [25.12, 62.32] }
  ];

  projects.forEach(project => {
    L.circle(project.coords, {
      radius: project.radius,
      className: `footprint-radius footprint-radius--${project.tone}`,
      color: '#c89a62',
      fillColor: '#c89a62',
      fillOpacity: 0.07,
      weight: 1,
      opacity: 0.24
    }).addTo(map);
  });

  projects.forEach(project => {
    ports.forEach(port => {
      L.polyline([project.coords, port.coords], routeStyle).addTo(map);
    });
  });

  ports.forEach(port => {
    L.marker(port.coords, {
      icon: L.divIcon({
        className: 'footprint-port-marker',
        html: `
          <span class="footprint-port-marker__dot"></span>
          <span class="footprint-map-marker__label footprint-map-marker__label--port">${port.name}</span>
        `,
        iconSize: [120, 30],
        iconAnchor: [9, 15]
      })
    })
      .addTo(map);
  });

  projects.forEach(project => {
    const marker = L.marker(project.coords, {
      icon: L.divIcon({
        className: `footprint-map-marker footprint-map-marker--${project.tone}`,
        html: `
          <span class="footprint-map-marker__pulse"></span>
          <span class="footprint-map-marker__dot"></span>
          <span class="footprint-map-marker__label footprint-map-marker__label--${project.tone}">${project.name}</span>
        `,
        iconSize: [150, 42],
        iconAnchor: [17, 17]
      })
    }).addTo(map);

    marker.bindPopup(`
      <div class="footprint-popup">
        <span>${project.commodity}</span>
        <strong>${project.name}</strong>
        <small>${project.type} / ${project.status}</small>
      </div>
    `, { className: 'footprint-leaflet-popup' });

  });

  const bounds = L.latLngBounds([
    [24.6, 60.6],
    [31.3, 68.7]
  ]);
  map.fitBounds(bounds, { padding: [28, 28] });
}

// Auto-init
if (document.getElementById('premium-map')) {
  initProjectMap();
}
