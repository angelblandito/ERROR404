/* Inicio: banner de temporada, departamentos, carruseles y cupones. */
import { esc, setTitle } from '../ui.js';
import { productArt } from '../illustrations.js';
import { productRail } from '../components.js';
import { activePromotions, newestIds, state } from '../store.js';
import { currentTheme } from '../theme.js';

function hero(theme) {
    return '<section class="hero">' +
        '<div class="hero-copy">' +
            '<p class="eyebrow">' + esc(theme.name) + ' · Para jóvenes y adultos de 15 a 45 años</p>' +
            '<h1>' + esc(theme.hero_title) + '</h1>' +
            '<p>' + esc(theme.hero_text) + '</p>' +
            '<div class="hero-cta">' +
                '<a class="btn btn-accent" href="#/tienda">Comprar ahora</a>' +
                '<a class="btn btn-outline-light" href="#/ofertas">Ver cupones</a>' +
            '</div>' +
        '</div>' +
        '<div class="hero-art" aria-hidden="true">' +
            productArt({ name: 'Tenis', category_code: 'footwear' }, 'white', 'hero-shoe') +
        '</div>' +
    '</section>';
}

function departmentTiles() {
    const tiles = [
        ['footwear', 'Calzado', 'Running, básquetbol y urbano', { name: 'Tenis', category_code: 'footwear' }, 'black'],
        ['apparel', 'Ropa', 'Playeras, joggers, leggings y más', { name: 'Sudadera', category_code: 'hoodies' }, 'gray'],
        ['accessories', 'Accesorios', 'Gorras, mochilas y botellas', { name: 'Mochila', category_code: 'accessories' }, 'navy']
    ];
    return '<section class="tiles">' + tiles.map(function (t) {
        return '<a class="tile" href="#/tienda?dep=' + t[0] + '">' +
            productArt(t[3], t[4]) +
            '<div class="tile-copy"><h2>' + t[1] + '</h2><p>' + t[2] + '</p><span class="btn btn-light btn-sm">Comprar</span></div>' +
        '</a>';
    }).join('') + '</section>';
}

function segmentTiles() {
    return '<section class="segments">' +
        '<a class="segment segment-youth" href="#/tienda?edad=youth"><span>15–24</span><h2>Juvenil</h2><p>Estilo urbano, escuela y deporte.</p></a>' +
        '<a class="segment segment-adult" href="#/tienda?edad=adult"><span>25–45</span><h2>Adulto</h2><p>Rendimiento para correr, entrenar y competir.</p></a>' +
    '</section>';
}

function perks() {
    const items = [
        ['Envío gratis', 'En compras desde $' + state.shipping.free_from.toLocaleString('es-MX')],
        ['Recoge en sucursal', 'Tijuana y CDMX, sin costo'],
        ['30 días para cambios', 'Devoluciones sin complicaciones'],
        ['Pago seguro', 'Tarjeta, OXXO o transferencia']
    ];
    return '<section class="perks">' + items.map(function (i) {
        return '<div class="perk"><strong>' + esc(i[0]) + '</strong><span>' + esc(i[1]) + '</span></div>';
    }).join('') + '</section>';
}

function coupons(list) {
    if (!list.length) return '';
    return '<section class="coupon-strip">' + list.slice(0, 3).map(function (p) {
        const pct = Number(p.discount_percent);
        return '<div class="coupon"><span class="coupon-pct">' + (pct > 0 ? pct + '%' : 'Envío') + '</span>' +
            '<div><strong>' + esc(p.description || p.code) + '</strong><span>Usa el cupón <code>' + esc(p.code) + '</code></span></div></div>';
    }).join('') + '</section>';
}

export async function renderHome(view) {
    setTitle('');
    const bestSellers = state.products.slice().sort(function (a, b) {
        return (b.sold - a.sold) || (b.rating - a.rating);
    }).slice(0, 8);
    const newest = newestIds(8).map(function (id) { return state.byId.get(id); });
    const topRated = state.products.filter(function (p) { return p.reviews > 0; })
        .sort(function (a, b) { return (b.rating - a.rating) || (b.reviews - a.reviews); }).slice(0, 8);

    view.innerHTML = '<div id="heroSlot">' + hero(currentTheme) + '</div>' +
        perks() +
        departmentTiles() +
        productRail('Lo más vendido', bestSellers, { href: '#/tienda?orden=vendidos', label: 'Ver todo' }) +
        segmentTiles() +
        '<div id="couponSlot"></div>' +
        productRail('Novedades', newest, { href: '#/tienda?orden=nuevo', label: 'Ver novedades' }) +
        productRail('Mejor calificados', topRated, { href: '#/tienda?orden=calificacion', label: 'Ver todo' });

    const promos = await activePromotions();
    const slot = view.querySelector('#couponSlot');
    if (slot) slot.innerHTML = coupons(promos);
}

document.addEventListener('jam:theme', function (e) {
    const slot = document.getElementById('heroSlot');
    if (slot) slot.innerHTML = hero(e.detail);
});
