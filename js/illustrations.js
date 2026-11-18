/*
 * Imágenes de producto.
 * 1. Foto propia: img/productos/<id_producto>-<color>.jpg  (ejemplo: 6-black.jpg)
 *    También se aceptan .png y .webp. La API informa qué fotos existen.
 * 2. Foto de la base de datos: columna product.image_url
 * 3. Si no hay foto, se dibuja una ilustración con el color de la variante.
 */

import { state } from './store.js';

const PHOTO_FOLDER = 'img/productos/';
const PHOTO_TYPES = ['jpg', 'jpeg', 'png', 'webp'];
const missingPhotos = new Set();

export const COLOR_HEX = {
    black: '#22252b',
    white: '#f4f4f2',
    red: '#d62828',
    navy: '#1d3557',
    gray: '#8d99ae',
    pink: '#f4a6c0',
    green: '#2a9d55',
    blue: '#2563eb'
};

function shade(hex, amount) {
    const n = parseInt(hex.slice(1), 16);
    const clamp = function (v) { return Math.max(0, Math.min(255, Math.round(v))); };
    const r = clamp(((n >> 16) & 255) * (1 + amount));
    const g = clamp(((n >> 8) & 255) * (1 + amount));
    const b = clamp((n & 255) * (1 + amount));
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

const SHAPES = {
    shoe: function (c, d) {
        return '<path d="M22 126q-2 14 14 16h170q18 0 18-14v-8H22z" fill="#fafafa" stroke="#c9c9c9" stroke-width="2"/>' +
            '<path d="M22 126l2-8h198l2 8" fill="none" stroke="#c9c9c9" stroke-width="2"/>' +
            '<path d="M26 118l8-44q3-12 18-12h30q12 0 21 12l20 22q14 12 46 14l26 2q22 3 25 6v0H26z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5"/>' +
            '<path d="M172 110q30 2 48 8H170z" fill="' + d + '" opacity=".35"/>' +
            '<path d="M36 76q6-22 28-20" fill="none" stroke="' + d + '" stroke-width="4" stroke-linecap="round"/>' +
            '<path d="M92 72l10-6M100 80l10-6M108 88l10-6M116 96l10-6" stroke="#fff" stroke-width="3.5" stroke-linecap="round" opacity=".9"/>' +
            '<path d="M60 112q48-26 104-2" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" opacity=".85"/>';
    },
    sandal: function (c, d) {
        return '<path d="M28 122q0-14 18-14h150q20 0 20 14v6q0 10-16 10H44q-16 0-16-10z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5"/>' +
            '<path d="M30 126h184" stroke="' + d + '" stroke-width="2" opacity=".5"/>' +
            '<path d="M70 110q46-62 100 0" fill="none" stroke="' + d + '" stroke-width="22" stroke-linecap="round"/>' +
            '<path d="M84 98q32-30 72 0" fill="none" stroke="#fff" stroke-width="3" stroke-dasharray="6 6" opacity=".7"/>';
    },
    tee: function (c, d) {
        return '<path d="M80 30l20-8q20 12 40 0l20 8 40 28-16 28-20-10v82H76V76l-20 10-16-28z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
            '<path d="M100 22q20 18 40 0" fill="none" stroke="' + d + '" stroke-width="4"/>' +
            '<path d="M76 76l-8-30M164 76l8-30" stroke="' + d + '" stroke-width="2" opacity=".4"/>' +
            '<path d="M96 96h48" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".7"/>';
    },
    top: function (c, d) {
        return '<path d="M74 44l18-10q28 46 56 0l18 10 4 64q-50 18-100 0z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
            '<path d="M70 108q50 18 100 0v16q-50 16-100 0z" fill="' + d + '"/>' +
            '<path d="M92 34q28 52 56 0" fill="none" stroke="' + d + '" stroke-width="3"/>';
    },
    shorts: function (c, d) {
        return '<path d="M62 40h116l14 92-60 6-12-50-12 50-60-6z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
            '<path d="M62 40h116v14H62z" fill="' + d + '"/>' +
            '<path d="M116 54l4 30" stroke="' + d + '" stroke-width="2"/>' +
            '<path d="M160 70l14 60" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".75"/>';
    },
    leggings: function (c, d) {
        return '<path d="M88 18h64l6 22-8 128h-22l-8-98-8 98H90l-8-128z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
            '<path d="M88 18h64v14H88z" fill="' + d + '"/>' +
            '<path d="M146 50l-4 110" stroke="#fff" stroke-width="3" opacity=".6"/>';
    },
    pants: function (c, d) {
        return '<path d="M80 18h80l10 132 2 16h-38l-2-16-12-86-12 86-2 16H68l2-16z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
            '<path d="M80 18h80v14H80z" fill="' + d + '"/>' +
            '<path d="M70 150h36v16H68zM134 150h36l2 16h-38z" fill="' + d + '"/>' +
            '<path d="M150 50q6 10 4 30" fill="none" stroke="' + d + '" stroke-width="2.5"/>';
    },
    hoodie: function (c, d) {
        return '<path d="M78 50 46 70l-12 82 22 4 20-62z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
            '<path d="M162 50l32 20 12 82-22 4-20-62z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
            '<path d="M76 48h88v112H76z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5"/>' +
            '<path d="M90 50q30-46 60 0-30 18-60 0z" fill="' + d + '"/>' +
            '<path d="M96 118h48l6 28H90z" fill="' + d + '" opacity=".55"/>' +
            '<path d="M110 62v26M130 62v26" stroke="#fff" stroke-width="3" stroke-linecap="round"/>' +
            '<path d="M76 148h88" stroke="' + d + '" stroke-width="6"/>';
    },
    jacket: function (c, d) {
        return '<path d="M78 46 46 66l-12 86 22 4 20-62z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
            '<path d="M162 46l32 20 12 86-22 4-20-62z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
            '<path d="M76 44h88v116H76z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5"/>' +
            '<path d="M96 30h48l6 18-30 14-30-14z" fill="' + d + '"/>' +
            '<path d="M120 60v100" stroke="#e8e8e8" stroke-width="3"/>' +
            '<path d="M86 96l22 6M154 96l-22 6" stroke="#fff" stroke-width="4" stroke-linecap="round" opacity=".7"/>';
    },
    cap: function (c, d) {
        return '<path d="M58 112q0-62 62-64 62 2 62 64z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5"/>' +
            '<path d="M150 108q54 2 66 18-26 8-68 0z" fill="' + d + '"/>' +
            '<path d="M120 48v64M90 56q-10 26-8 56M150 56q10 26 8 56" fill="none" stroke="' + d + '" stroke-width="2" opacity=".5"/>' +
            '<circle cx="120" cy="48" r="5" fill="' + d + '"/>' +
            '<path d="M96 88h48" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".8"/>';
    },
    backpack: function (c, d) {
        return '<path d="M100 26q20-20 40 0" fill="none" stroke="' + d + '" stroke-width="6" stroke-linecap="round"/>' +
            '<path d="M70 42q0-16 16-16h68q16 0 16 16l2 120q0 8-8 8H76q-8 0-8-8z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5"/>' +
            '<path d="M84 104q0-8 8-8h56q8 0 8 8v46q0 8-8 8H92q-8 0-8-8z" fill="' + d + '" opacity=".5"/>' +
            '<path d="M88 112h64" stroke="#fff" stroke-width="3" stroke-dasharray="5 4"/>' +
            '<path d="M76 70q44-18 88 0" fill="none" stroke="' + d + '" stroke-width="3"/>';
    },
    bottle: function (c, d) {
        return '<path d="M102 18h36l2 26h-40z" fill="' + d + '"/>' +
            '<path d="M96 44h48q10 0 10 12v104q0 12-12 12h-44q-12 0-12-12V56q0-12 10-12z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5"/>' +
            '<path d="M86 92h68v34H86z" fill="#fff" opacity=".75"/>' +
            '<path d="M100 109h40" stroke="' + d + '" stroke-width="4" stroke-linecap="round"/>' +
            '<path d="M140 60v90" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".45"/>';
    },
    socks: function (c, d) {
        const sock = function (x) {
            return '<path transform="translate(' + x + ' 0)" d="M70 20h32v86q0 22 20 26l20 4q14 4 10 18-4 12-20 10l-44-6q-20-4-18-28z" fill="' + c + '" stroke="' + d + '" stroke-width="2.5" stroke-linejoin="round"/>' +
                '<path transform="translate(' + x + ' 0)" d="M70 30h32M70 40h32" stroke="' + d + '" stroke-width="4"/>';
        };
        return sock(0) + sock(46);
    }
};

const KEYWORDS = [
    ['sandalia', 'sandal'], ['tenis', 'shoe'], ['calzado', 'shoe'], ['playera', 'tee'], ['jersey', 'tee'],
    ['top', 'top'], ['short', 'shorts'], ['legging', 'leggings'], ['sudadera', 'hoodie'], ['hoodie', 'hoodie'],
    ['chamarra', 'jacket'], ['rompevientos', 'jacket'], ['pants', 'pants'], ['jogger', 'pants'], ['gorra', 'cap'],
    ['mochila', 'backpack'], ['botella', 'bottle'], ['calceta', 'socks']
];

const BY_CATEGORY = { tops: 'tee', bottoms: 'shorts', leggings: 'leggings', hoodies: 'hoodie', footwear: 'shoe', accessories: 'backpack' };

export function productKind(product) {
    const name = (product.name || '').toLowerCase();
    for (const [word, kind] of KEYWORDS) {
        if (name.indexOf(word) >= 0) return kind;
    }
    return BY_CATEGORY[product.category_code] || 'tee';
}

function photoSources(product, colorCode) {
    const sources = [];
    if (product.id_product) {
        PHOTO_TYPES.forEach(function (type) {
            const file = product.id_product + '-' + colorCode + '.' + type;
            if (state.photos.has(file)) sources.push(PHOTO_FOLDER + file);
        });
    }
    if (product.image_url) {
        sources.push(product.image_url);
    }
    return sources.filter(function (src) { return !missingPhotos.has(src); });
}

export function productArt(product, colorCode, extraClass) {
    const name = String(product.name || '').replace(/"/g, '&quot;');
    const base = COLOR_HEX[colorCode] || COLOR_HEX.black;
    const dark = colorCode === 'white' ? '#9a9a9a' : shade(base, -0.35);
    const draw = SHAPES[productKind(product)] || SHAPES.tee;
    const sources = photoSources(product, colorCode);

    let html = '<div class="product-art ' + (extraClass || '') + '">';
    html += '<svg viewBox="0 0 240 180" role="img" aria-label="' + name + '">' + draw(base, dark) + '</svg>';
    if (sources.length) {
        html += '<img class="product-photo" src="' + sources[0] + '" data-next="' + sources.slice(1).join('|') +
            '" alt="' + name + '" loading="lazy">';
    }
    return html + '</div>';
}

/* Si una foto no existe se prueba la siguiente; al final queda la ilustración. */
export function handleMissingPhoto(img) {
    missingPhotos.add(img.getAttribute('src'));
    const next = (img.dataset.next || '').split('|').filter(Boolean);
    if (next.length) {
        img.dataset.next = next.slice(1).join('|');
        img.src = next[0];
    } else {
        img.remove();
    }
}

export function swatch(colorCode) {
    return COLOR_HEX[colorCode] || '#999';
}
