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

/* ---------- Plantillas de index.html ---------- */
export function clone(id) {
    return document.getElementById(id).content.firstElementChild.cloneNode(true);
}

export function field(root, name) {
    return root.querySelector('[data-field="' + name + '"]');
}

export function slot(root, name) {
    return root.querySelector('[data-slot="' + name + '"]');
}

export function fill(root, values) {
    Object.keys(values).forEach(function (name) {
        const el = field(root, name);
        if (el) el.textContent = values[name] === null || values[name] === undefined ? '' : values[name];
    });
    return root;
}

export function show(el, visible) {
    el.hidden = !visible;
    return el;
}

export function sizeLabel(size) {
    return size === 'UNI' ? 'Unitalla' : size;
}

export function badge(text, css) {
    const el = clone('tpl-badge');
    el.textContent = text;
    if (css) el.classList.add(css);
    return el;
}

export function stars(rating, label) {
    const value = Math.round(Number(rating || 0) * 2) / 2;
    const el = clone('tpl-stars');
    el.setAttribute('aria-label', label || (value + ' de 5 estrellas'));
    $$('.star', el).forEach(function (star, i) {
        star.classList.add(value >= i + 1 ? 'full' : (value >= i + 0.5 ? 'half' : 'empty'));
    });
    return el;
}

export function message(text, css) {
    const el = clone('tpl-message');
    el.textContent = text;
    if (css) el.className = css;
    return el;
}

export function emptyState(title, text, action) {
    const el = fill(clone('tpl-empty'), { title: title, text: text });
    if (action) {
        const link = show(field(el, 'action'), true);
        link.href = action.href;
        link.textContent = action.label;
    }
    return el;
}

export function loading(text) {
    return fill(clone('tpl-loading'), { text: text || 'Cargando…' });
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

let toastTimer = null;
export function toast(text, options = {}) {
    const el = $('#toast');
    const link = field(el, 'action');
    field(el, 'message').textContent = text;
    show(link, Boolean(options.action));
    if (options.action) {
        link.href = options.action.href;
        link.textContent = options.action.label;
    }
    el.classList.toggle('error', Boolean(options.error));
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.hidden = true; }, options.error ? 5000 : 3500);
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
