/*
 * Imágenes de producto.
 * 1. Foto propia por color: img/productos/<id_producto>-<color>.jpg  (ejemplo: 6-black.jpg)
 * 2. Foto del producto: img/productos/<nombre-del-producto>.jpg  (ejemplo: tenis-running-jam-pulse.jpg)
 *    También se aceptan .png y .webp. La API informa qué fotos existen.
 * 3. Foto de la base de datos: columna product.image_url
 * 4. Foto real del tipo de producto (js/product-photos.js)
 * 5. Si no hay foto, se usa el dibujo de img/ilustraciones.svg pintado con el color de la variante.
 */

import { clone } from './ui.js';
import { state } from './store.js';
import { stockPhoto } from './product-photos.js';

const PHOTO_FOLDER = 'img/productos/';
const PHOTO_TYPES = ['jpg', 'jpeg', 'png', 'webp'];
const DRAWINGS = 'img/ilustraciones.svg#';
const COLORS = ['black', 'white', 'red', 'navy', 'gray', 'pink', 'green', 'blue', 'amber', 'lime', 'brown'];
const missingPhotos = new Set();

const KEYWORDS = [
    ['fútbol americano', 'football'], ['balón de básquetbol', 'basketball'], ['voleibol', 'volleyball'],
    ['pelotas de pádel', 'padelballs'], ['balón', 'soccerball'], ['guantes de box', 'boxing'], ['guante', 'glove'],
    ['goggles', 'goggles'], ['lentes', 'glasses'], ['tenis de fútbol', 'cleat'], ['falda', 'shorts'],
    ['sandalia', 'sandal'], ['tenis', 'shoe'], ['calzado', 'shoe'], ['playera', 'tee'], ['jersey', 'tee'],
    ['top', 'top'], ['short', 'shorts'], ['legging', 'leggings'], ['sudadera', 'hoodie'], ['hoodie', 'hoodie'],
    ['chamarra', 'jacket'], ['rompevientos', 'jacket'], ['pants', 'pants'], ['jogger', 'pants'], ['gorra', 'cap'],
    ['mochila', 'backpack'], ['botella', 'bottle'], ['calceta', 'socks']
];

const BY_CATEGORY = {
    tops: 'tee', bottoms: 'shorts', leggings: 'leggings', hoodies: 'hoodie', footwear: 'shoe', accessories: 'backpack',
    eyewear: 'glasses', gloves: 'glove', balls: 'soccerball'
};

export function colorClass(colorCode) {
    return COLORS.indexOf(colorCode) >= 0 ? colorCode : 'black';
}

export function productKind(product) {
    const name = (product.name || '').toLowerCase();
    for (const [word, kind] of KEYWORDS) {
        if (name.indexOf(word) >= 0) return kind;
    }
    return BY_CATEGORY[product.category_code] || 'tee';
}

/* "Balón de fútbol JAM Strike No. 5" -> "balon-de-futbol-jam-strike-no-5" */
export function photoName(name) {
    return String(name || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

function photoSources(product, colorCode) {
    const sources = [];
    if (product.id_product) {
        PHOTO_TYPES.forEach(function (type) {
            const file = product.id_product + '-' + colorCode + '.' + type;
            if (state.photos.has(file)) sources.push(PHOTO_FOLDER + file);
        });
    }
    if (product.name) {
        const base = photoName(product.name);
        PHOTO_TYPES.forEach(function (type) {
            if (state.photos.has(base + '.' + type)) sources.push(PHOTO_FOLDER + base + '.' + type);
        });
    }
    if (product.image_url) {
        sources.push(product.image_url);
    }
    const stock = stockPhoto(product);
    if (stock) {
        sources.push(stock);
    }
    return sources.filter(function (src) { return !missingPhotos.has(src); });
}

export function hasPhoto(product) {
    const first = (product.variants || [])[0];
    return photoSources(product, first ? first.color_code : 'black').length > 0;
}

export function productArt(product, colorCode, extraClass) {
    const art = clone('tpl-product-art');
    const svg = art.querySelector('svg');
    const img = art.querySelector('img');
    const sources = photoSources(product, colorCode);

    art.classList.add('art-' + colorClass(colorCode));
    if (extraClass) art.classList.add(extraClass);
    svg.setAttribute('aria-label', product.name || '');
    svg.querySelector('use').setAttribute('href', DRAWINGS + productKind(product));

    if (sources.length) {
        img.src = sources[0];
        img.alt = product.name || '';
        img.dataset.next = sources.slice(1).join('|');
        img.hidden = false;
    } else {
        img.remove();
    }
    return art;
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
