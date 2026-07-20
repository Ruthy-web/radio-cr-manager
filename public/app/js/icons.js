/**
 * Icônes SVG minimalistes injectées côté client (aucune police d'icônes
 * externe, cohérent avec la CSP stricte de la PWA). Formes génériques
 * dessinées pour ce projet, monoline, 20x20.
 */
const ICONS = {
  folder: '<path d="M3 5.5A1.5 1.5 0 0 1 4.5 4H8l1.5 2H16A1.5 1.5 0 0 1 17.5 7.5v8A1.5 1.5 0 0 1 16 17H4a1.5 1.5 0 0 1-1.5-1.5v-10Z"/>',
  spark: '<path d="M10 2v4M10 14v4M2 10h4M14 10h4M4.5 4.5l2.8 2.8M12.7 12.7l2.8 2.8M15.5 4.5l-2.8 2.8M7.3 12.7l-2.8 2.8"/>',
  grid: '<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/>',
  clock: '<circle cx="10" cy="10" r="7.25"/><path d="M10 6v4l3 2"/>',
  gear: '<path d="M2 5.5h5M12 5.5h6M2 10h10M17 10h1M2 14.5h4M11 14.5h7"/><circle cx="8.5" cy="5.5" r="2"/><circle cx="13.5" cy="10" r="2"/><circle cx="7.5" cy="14.5" r="2"/>',
  camera: '<path d="M3 7.5A1.5 1.5 0 0 1 4.5 6H7l1-2h4l1 2h2.5A1.5 1.5 0 0 1 17 7.5v7A1.5 1.5 0 0 1 15.5 16h-11A1.5 1.5 0 0 1 3 14.5v-7Z"/><circle cx="10" cy="11" r="3"/>',
  mic: '<rect x="7.5" y="2.5" width="5" height="9" rx="2.5"/><path d="M5 10a5 5 0 0 0 10 0M10 15v2.5"/>',
  stop: '<rect x="5" y="5" width="10" height="10" rx="1.5"/>',
  clip: '<path d="M6 10.5V6a4 4 0 0 1 8 0v7a2.5 2.5 0 0 1-5 0V7.5a1 1 0 0 1 2 0V13"/>',
};

export function renderIcons(root = document) {
  root.querySelectorAll('[data-icon]').forEach((el) => {
    const name = el.getAttribute('data-icon');
    const path = ICONS[name];
    if (!path) return;
    el.innerHTML = `<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${path}</svg>`;
    el.classList.add('icon');
  });
}
