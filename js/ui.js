/* Utilidades de interfaz compartidas por todas las vistas. */

export const money = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

export const DEPARTMENTS = { footwear: 'Calzado', apparel: 'Ropa', accessories: 'Accesorios' };
export const GENDERS = { M: 'Hombre', F: 'Mujer', U: 'Unisex' };
export const SEGMENTS = { youth: 'Juvenil 15–24', adult: 'Adulto 25–45', all: '15–45 años' };

export const ORDER_STATUS = {
    pending: { label: 'Pendiente de pago', css: 'warn' },
    paid: { label: 'Pagado', css: 'ok' },
    shipped: { label: 'En camino', css: 'info' },
    delivered: { label: 'Entregado', css: 'ok' },
    cancelled: { label: 'Cancelado', css: 'bad' },
    returned: { label: 'Devuelto', css: 'bad' }
};

export const PAYMENT_METHODS = { card: 'Tarjeta', oxxo: 'OXXO', transfer: 'Transferencia', cash: 'Efectivo en sucursal' };

export function $(selector, root) {
    return (root || document).querySelector(selector);
}

export function $$(selector, root) {
    return Array.from((root || document).querySelectorAll(selector));
}

export function esc(value) {
    return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function badge(text, css) {
    return '<span class="badge ' + (css || '') + '">' + esc(text) + '</span>';
}

export function formatDate(value, withTime) {
    if (!value) return '';
    const iso = String(value).replace(' ', 'T');
    const date = new Date(/Z|[+-]\d\d:?\d\d$/.test(iso) ? iso : iso + 'Z');
    if (isNaN(date)) return String(value);
    const opts = { day: '2-digit', month: 'short', year: 'numeric' };
    if (withTime) {
        opts.hour = '2-digit';
        opts.minute = '2-digit';
    }
    return date.toLocaleDateString('es-MX', opts);
}

export function stars(rating, label) {
    const value = Math.round(Number(rating || 0) * 2) / 2;
    let html = '<span class="stars" role="img" aria-label="' + esc(label || (value + ' de 5 estrellas')) + '">';
    for (let i = 1; i <= 5; i++) {
        const cls = value >= i ? 'full' : (value >= i - 0.5 ? 'half' : 'empty');
        html += '<i class="star ' + cls + '"></i>';
    }
    return html + '</span>';
}

let toastTimer = null;
export function toast(message, options = {}) {
    const el = $('#toast');
    el.innerHTML = '<span>' + esc(message) + '</span>' +
        (options.action ? '<a href="' + esc(options.action.href) + '">' + esc(options.action.label) + '</a>' : '');
    el.classList.toggle('error', Boolean(options.error));
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.hidden = true; }, options.error ? 5000 : 3500);
}

export function emptyState(title, text, action) {
    return '<div class="empty-state"><h2>' + esc(title) + '</h2><p>' + esc(text) + '</p>' +
        (action ? '<a class="btn btn-dark" href="' + esc(action.href) + '">' + esc(action.label) + '</a>' : '') + '</div>';
}

export function loading(text) {
    return '<div class="loading" role="status"><span class="spinner"></span>' + esc(text || 'Cargando…') + '</div>';
}

export function parseQuery(query) {
    const params = {};
    new URLSearchParams(query || '').forEach(function (value, key) { params[key] = value; });
    return params;
}

export function buildQuery(params) {
    const qs = new URLSearchParams();
    Object.keys(params).forEach(function (key) {
        if (params[key] !== undefined && params[key] !== null && params[key] !== '' && params[key] !== 'all') {
            qs.set(key, params[key]);
        }
    });
    const text = qs.toString();
    return text ? '?' + text : '';
}

export function setTitle(text) {
    document.title = text ? text + ' · JAM Sport' : 'JAM Sport · Calzado, ropa y accesorios deportivos';
}
