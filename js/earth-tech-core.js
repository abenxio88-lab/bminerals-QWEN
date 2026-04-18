/**
 * Balochistan Minerals - Earth-Tech Core
 * 3D Procedural Horizon (Three.js)
 */

export function initEarthTechCore() {
  const container = document.getElementById('three-horizon-container');
  if (!container) return;

  // 1. Setup Scene
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(75, container.offsetWidth / container.offsetHeight, 0.1, 1000);
  camera.position.set(0, 15, 40);
  camera.lookAt(0, 0, 0);

  const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
  renderer.setSize(container.offsetWidth, container.offsetHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  container.appendChild(renderer.domElement);

  // 2. Procedural Terrain Geometry (Monochrome Silver/Slate)
  const geometry = new THREE.PlaneGeometry(120, 120, 100, 100);
  geometry.rotateX(-Math.PI / 2);

  const position = geometry.attributes.position;
  const count = position.count;

  // Simple Noise Function (Pseudo-Perlin)
  const noise = (x, y) => {
    return Math.sin(x * 0.1) * Math.cos(y * 0.1) * 5 + 
           Math.sin(x * 0.2 + y * 0.2) * 2 + 
           Math.random() * 0.1;
  };

  for (let i = 0; i < count; i++) {
    const x = position.getX(i);
    const y = position.getZ(i);
    const h = noise(x, y);
    position.setY(i, h);
  }
  position.needsUpdate = true;
  geometry.computeVertexNormals();

  // 3. Material (Industrial Slate with Metallic Sheen)
  const material = new THREE.MeshStandardMaterial({
    color: 0x3f3f46, // Zinc-600 (Lighter industrial slate)
    wireframe: true,
    transparent: true,
    opacity: 0.35, // Increased visibility
    roughness: 0.4, // Smoother for better light catch
    metalness: 0.8, // More metallic reflection
  });

  const terrain = new THREE.Mesh(geometry, material);
  scene.add(terrain);

  // 4. Lighting (Golden Hour Mineral Glow)
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.6); // Slightly brighter
  scene.add(ambientLight);

  const sunLight = new THREE.DirectionalLight(0xf97316, 1.8); // Brighter Copper/Gold glow
  sunLight.position.set(50, 20, 10);
  scene.add(sunLight);

  // 5. Interaction (Mouse Parallax)
  let mouseX = 0;
  let mouseY = 0;
  let targetX = 0;
  let targetY = 0;

  window.addEventListener('mousemove', (event) => {
    mouseX = (event.clientX - window.innerWidth / 2) / 100;
    mouseY = (event.clientY - window.innerHeight / 2) / 100;
  });

  // 6. Animation Loop
  const animate = () => {
    requestAnimationFrame(animate);

    // Smooth camera drift
    targetX += (mouseX - targetX) * 0.05;
    targetY += (mouseY - targetY) * 0.05;

    camera.position.x += (targetX - camera.position.x) * 0.05;
    camera.position.y += (-targetY + 15 - camera.position.y) * 0.05;
    camera.lookAt(0, 0, 0);

    // Subtle terrain rotation
    terrain.rotation.y += 0.001;

    renderer.render(scene, camera);
  };

  animate();

  // Handle Resize
  window.addEventListener('resize', () => {
    camera.aspect = container.offsetWidth / container.offsetHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(container.offsetWidth, container.offsetHeight);
  });
}

// Auto-init
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initEarthTechCore);
} else {
  initEarthTechCore();
}
