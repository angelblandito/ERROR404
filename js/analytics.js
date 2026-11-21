/* Registro anónimo de visitas, búsquedas e instalaciones de la app.
   Cada navegador tiene un identificador aleatorio en localStorage; no se
   envían datos personales. Si la API no responde, la tienda sigue igual. */
import { API_URL, api } from './api.js';
import { normalize } from './search.js';

const VISITOR_KEY = 'jam_visitor';
const INSTALL_KEY = 'jam_app_reported';
const PING_SECONDS = 45;

let visitor = null;
let lastPath = null;
let lastSearch = '';
let timer = null;

function storage(key, value) {
    try {
        if (value === undefined) return localStorage.getItem(key);
        localStorage.setItem(key, value);
    } catch (e) {
        return null;
    }
    return value;
}

function randomId() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
}

function visitorId() {
    if (!visitor) {
        visitor = storage(VISITOR_KEY) || storage(VISITOR_KEY, randomId()) || randomId();
    }
    return visitor;
}

/* 'app' cuando la tienda se abrió instalada (PWA); 'web' en el navegador. */
export function channel() {
    const standalone = window.matchMedia && (window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.matchMedia('(display-mode: minimal-ui)').matches);
    return standalone || window.navigator.standalone === true || document.referrer.indexOf('android-app://') === 0 ? 'app' : 'web';
}

function device() {
    const ua = navigator.userAgent || '';
    const ipadDesktopMode = /Macintosh/.test(ua) && navigator.maxTouchPoints > 1;
    if (/iPad|Tablet/i.test(ua) || ipadDesktopMode || (/Android/i.test(ua) && !/Mobile/i.test(ua))) return 'tableta';
    if ((navigator.userAgentData && navigator.userAgentData.mobile) || /Mobi|iPhone|iPod|Android/i.test(ua)) return 'movil';
    return 'escritorio';
}

function platform() {
    const ua = navigator.userAgent || '';
    if (/Android/i.test(ua)) return 'android';
    if (/iPhone|iPad|iPod/i.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)) return 'ios';
    if (/Windows/i.test(ua)) return 'windows';
    if (/Macintosh|Mac OS/i.test(ua)) return 'mac';
    if (/Linux|CrOS/i.test(ua)) return 'linux';
    return 'otro';
}

function base() {
    return { visitor: visitorId(), channel: channel(), device: device() };
}

function send(path, body) {
    return api(path, { method: 'POST', body: Object.assign(base(), body) }).catch(function () { return null; });
}

function currentPath() {
    return (window.location.hash.replace(/^#/, '').split('?')[0] || '/');
}

function ping(view) {
    if (document.visibilityState === 'hidden') return;
    const path = currentPath();
    send('/analytics/ping', { path: path, view: view && path !== lastPath });
    lastPath = path;
}

function leave() {
    const body = JSON.stringify({ visitor: visitorId() });
    const url = API_URL + '/analytics/leave';
    if (navigator.sendBeacon && navigator.sendBeacon(url, new Blob([body], { type: 'text/plain' }))) return;
    fetch(url, { method: 'POST', body: body, keepalive: true }).catch(function () {});
}

function reportInstall(source) {
    send('/analytics/install', { platform: platform(), source: source }).then(function (result) {
        if (result) storage(INSTALL_KEY, '1');
    });
}

/* Se llama una vez al arrancar la tienda. */
export function initAnalytics() {
    ping(true);
    timer = setInterval(function () { ping(false); }, PING_SECONDS * 1000);

    window.addEventListener('hashchange', function () { ping(true); });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') ping(false);
        else leave();
    });
    window.addEventListener('pagehide', leave);

    window.addEventListener('appinstalled', function () { reportInstall('instalacion'); });
    if (channel() === 'app' && !storage(INSTALL_KEY)) reportInstall('primer_uso');
    return timer;
}

/* Registra lo que se buscó en la tienda (una vez por término). */
export function trackSearch(term, results) {
    const text = normalize(term).trim().replace(/\s+/g, ' ');
    if (text.length < 2 || text === lastSearch) return;
    lastSearch = text;
    send('/analytics/search', { term: text, results: results });
}
