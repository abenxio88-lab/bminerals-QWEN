/**
 * Balochistan Minerals Interactive Project Map
 * Powered by Leaflet.js
 */

export function initProjectMap() {
  const mapContainer = document.getElementById('premium-map');
  if (!mapContainer) return;

  // Initialize map centered on Balochistan
  const map = L.map('premium-map', {
    scrollWheelZoom: false,
    zoomControl: true
  }).setView([29.0, 66.0], 6);

  // Add Professional Dark Matter Tiles (No API key required)
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 20
  }).addTo(map);

  // Custom Icon for Main Hub
  const mainIcon = L.divIcon({
    className: 'custom-map-marker main-hub',
    html: '<div class="marker-pulse"></div><div class="marker-dot main"></div>',
    iconSize: [30, 30],
    iconAnchor: [15, 15]
  });

  // Custom Icon for Sites
  const siteIcon = L.divIcon({
    className: 'custom-map-marker mine-site',
    html: '<div class="marker-dot"></div>',
    iconSize: [20, 20],
    iconAnchor: [10, 10]
  });

  // Project Data
  const projects = [
    {
      name: "Balochistan Minerals (HQ)",
      coords: [30.2, 67.0],
      type: "Executive Hub",
      status: "Operational",
      isMain: true
    },
    {
      name: "Muslim Bagh",
      coords: [30.8, 67.7],
      type: "Chromite Mine",
      status: "Active Production"
    },
    {
      name: "Khuzdar",
      coords: [27.8, 66.6],
      type: "Barite Operations",
      status: "Active"
    },
    {
      name: "Chagai",
      coords: [29.2, 61.6],
      type: "Copper & Gold",
      status: "Exploration"
    },
    {
      name: "Dilband",
      coords: [29.5, 67.2],
      type: "Iron Ore",
      status: "Production"
    }
  ];

  // Add markers to map
  projects.forEach(project => {
    const marker = L.marker(project.coords, {
      icon: project.isMain ? mainIcon : siteIcon
    }).addTo(map);

    marker.bindPopup(`
      <div class="map-popup">
        <strong style="color: var(--primary-gold-500); font-size: 1.1rem;">${project.name}</strong><br>
        <span style="color: #fff; font-size: 0.9rem;">${project.type}</span><br>
        <span style="color: rgba(255,255,255,0.7); font-size: 0.8rem;">Status: ${project.status}</span>
      </div>
    `);
  });

  // Add custom CSS for pulse effect
  const style = document.createElement('style');
  style.textContent = `
    .custom-map-marker {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .marker-dot {
      width: 12px;
      height: 12px;
      background: var(--primary-gold-500);
      border-radius: 50%;
      border: 2px solid #fff;
      box-shadow: 0 0 10px rgba(249, 115, 22, 0.5);
    }
    .marker-dot.main {
      width: 16px;
      height: 16px;
      background: var(--navy-500);
      border-color: var(--primary-gold-500);
    }
    .marker-pulse {
      position: absolute;
      width: 30px;
      height: 30px;
      background: var(--primary-gold-500);
      border-radius: 50%;
      opacity: 0.4;
      animation: mapPulse 2s infinite ease-out;
    }
    @keyframes mapPulse {
      0% { transform: scale(0.5); opacity: 0.5; }
      100% { transform: scale(1.5); opacity: 0; }
    }
  `;
  document.head.appendChild(style);
}

// Auto-init
if (document.getElementById('premium-map')) {
  initProjectMap();
}
