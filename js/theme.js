/* Paleta de colores según la temporada (tabla season_theme). */
import { api } from './api.js';
import { $ } from './ui.js';

/* Copia local de season_theme, usada solo si la API no responde. */
const FALLBACK_THEMES = [
    { code: 'spring', name: 'Primavera', theme_type: 'season', range: [320, 620], priority: 1, color_primary: '#1D4D3A', color_secondary: '#2A6B50', color_accent: '#FFB3C7', color_accent_text: '#1D4D3A', hero_title: 'Renueva tu rutina', hero_text: 'Colores frescos y prendas ligeras para entrenar al aire libre.', effect: 'petals' },
    { code: 'summer', name: 'Verano', theme_type: 'season', range: [621, 922], priority: 1, color_primary: '#0B3D5C', color_secondary: '#0E5A85', color_accent: '#FFD23F', color_accent_text: '#0B3D5C', hero_title: 'Verano en movimiento', hero_text: 'Shorts, tops y tenis transpirables para los días de más calor.', effect: 'sun' },
    { code: 'autumn', name: 'Otoño', theme_type: 'season', range: [923, 1220], priority: 1, color_primary: '#3B1F14', color_secondary: '#5A2E1B', color_accent: '#FF8C42', color_accent_text: '#3B1F14', hero_title: 'Entrena en otoño', hero_text: 'Capas ligeras, rompevientos y tenis para correr con el clima fresco.', effect: 'leaves' },
    { code: 'winter', name: 'Invierno', theme_type: 'season', range: [1221, 319], priority: 1, color_primary: '#0F1D3D', color_secondary: '#1C2F5E', color_accent: '#9BE7FF', color_accent_text: '#0F1D3D', hero_title: 'Invierno sin pausa', hero_text: 'Sudaderas, chamarras y leggings térmicos para no dejar de moverte.', effect: 'snow' },
    { code: 'back_to_school', name: 'Regreso a clases', theme_type: 'campaign', range: [801, 831], priority: 5, color_primary: '#1E1B4B', color_secondary: '#312E81', color_accent: '#FACC15', color_accent_text: '#1E1B4B', hero_title: 'Regreso a clases', hero_text: 'Mochilas, tenis y uniformes deportivos para el nuevo ciclo escolar.', effect: 'confetti' },
    { code: 'buen_fin', name: 'El Buen Fin', theme_type: 'campaign', range: [1113, 1117], priority: 9, color_primary: '#7A0019', color_secondary: '#A3001F', color_accent: '#FFD000', color_accent_text: '#3A0010', hero_title: 'El Buen Fin JAM', hero_text: 'Descuentos en calzado, ropa y accesorios del 13 al 17 de noviembre.', effect: 'confetti' },
    { code: 'black_friday', name: 'Black Friday', theme_type: 'campaign', range: [1120, 1130], priority: 9, color_primary: '#0A0A0A', color_secondary: '#262626', color_accent: '#FF2D55', color_accent_text: '#FFFFFF', hero_title: 'Black Friday', hero_text: 'Hasta 25% de descuento con el cupón BLACKFRIDAY.', effect: 'sparks' },
    { code: 'christmas', name: 'Navidad', theme_type: 'campaign', range: [1201, 106], priority: 8, color_primary: '#14351F', color_secondary: '#1E4D2B', color_accent: '#E63946', color_accent_text: '#FFFFFF', hero_title: 'Regala movimiento', hero_text: 'Ideas de regalo deportivas para jóvenes y adultos.', effect: 'festive' }
];

let themes = [];
export let currentTheme = FALLBACK_THEMES[2];

function localDate() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function fallbackFor(date) {
    const md = Number(date.slice(5, 7)) * 100 + Number(date.slice(8, 10));
    return FALLBACK_THEMES.filter(function (t) {
        return t.range[0] <= t.range[1] ? (md >= t.range[0] && md <= t.range[1]) : (md >= t.range[0] || md <= t.range[1]);
    }).sort(function (a, b) { return b.priority - a.priority; })[0];
}

function apply(theme) {
    if (!theme) return;
    if (!theme.effect) {
        const local = FALLBACK_THEMES.find(function (t) { return t.code === theme.code; });
        theme.effect = local ? local.effect : 'none';
    }
    currentTheme = theme;
    const root = document.documentElement;
    root.style.setProperty('--brand', theme.color_primary);
    root.style.setProperty('--brand-2', theme.color_secondary);
    root.style.setProperty('--accent', theme.color_accent);
    root.style.setProperty('--accent-ink', theme.color_accent_text);
    root.dataset.theme = theme.code;
    $('meta[name="theme-color"]').setAttribute('content', theme.color_primary);
    document.dispatchEvent(new CustomEvent('jam:theme', { detail: theme }));
}

function savedChoice() {
    const fromUrl = new URLSearchParams(window.location.search).get('temporada');
    if (fromUrl) return fromUrl;
    try {
        return localStorage.getItem('jam_theme') || 'auto';
    } catch (e) {
        return 'auto';
    }
}

async function select(code) {
    if (code !== 'auto') {
        apply(themes.find(function (t) { return t.code === code; }));
        return;
    }
    try {
        apply(await api('/season_theme/current?date=' + localDate()));
    } catch (e) {
        apply(fallbackFor(localDate()));
    }
}

export async function initTheme() {
    apply(fallbackFor(localDate()));
    try {
        themes = await api('/season_theme');
    } catch (e) {
        themes = FALLBACK_THEMES;
    }

    const selectEl = $('#seasonSelect');
    themes.forEach(function (t) {
        selectEl.add(new Option(t.name + (t.theme_type === 'campaign' ? ' · campaña' : ''), t.code));
    });

    const choice = savedChoice();
    selectEl.value = themes.some(function (t) { return t.code === choice; }) ? choice : 'auto';
    selectEl.addEventListener('change', function () {
        try {
            localStorage.setItem('jam_theme', selectEl.value);
        } catch (e) {
            /* sin localStorage */
        }
        select(selectEl.value);
    });
    await select(selectEl.value);
}

/* Fin de la campaña (23:59:59 del último día) si hoy está dentro de sus
   fechas; null si no es una campaña o todavía no empieza. Las campañas que
   cruzan de año, como Navidad, terminan el año siguiente. */
export function campaignEnd(theme, now) {
    if (!theme || theme.theme_type !== 'campaign') return null;
    const today = now || new Date();
    let startMonth = Number(theme.start_month);
    let startDay = Number(theme.start_day);
    let endMonth = Number(theme.end_month);
    let endDay = Number(theme.end_day);
    if (!endMonth && theme.range) {
        startMonth = Math.floor(theme.range[0] / 100);
        startDay = theme.range[0] % 100;
        endMonth = Math.floor(theme.range[1] / 100);
        endDay = theme.range[1] % 100;
    }
    if (!endMonth) return null;
    const start = startMonth * 100 + startDay;
    const end = endMonth * 100 + endDay;
    const md = (today.getMonth() + 1) * 100 + today.getDate();
    const wraps = end < start;
    const active = wraps ? (md >= start || md <= end) : (md >= start && md <= end);
    if (!active) return null;
    const year = today.getFullYear() + (wraps && md >= start ? 1 : 0);
    return new Date(year, endMonth - 1, endDay, 23, 59, 59);
}
