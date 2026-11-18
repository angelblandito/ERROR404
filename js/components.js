/* Piezas de interfaz reutilizadas por varias vistas. */
import { esc, money, stars, DEPARTMENTS, GENDERS } from './ui.js';
import { productArt } from './illustrations.js';
import { colorsOf, isFavorite, newestIds, state } from './store.js';

const HEART = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7.5-4.6-9.2-9.2C1.6 7.6 3.6 4.8 6.6 4.8c2.2 0 3.7 1.4 5.4 3.4 1.7-2 3.2-3.4 5.4-3.4 3 0 5 2.8 3.8 6C19.5 15.4 12 20 12 20z"/></svg>';

export function favButton(idProduct, extraClass) {
    const active = isFavorite(idProduct);
    return '<button class="fav-btn ' + (extraClass || '') + (active ? ' active' : '') + '" data-fav="' + idProduct +
        '" aria-pressed="' + active + '" aria-label="' + (active ? 'Quitar de favoritos' : 'Agregar a favoritos') + '">' + HEART + '</button>';
}

function topSellerIds() {
    return state.products.filter(function (p) { return p.sold > 0; })
        .sort(function (a, b) { return b.sold - a.sold; })
        .slice(0, 3).map(function (p) { return p.id_product; });
}

export function productTag(p) {
    if (p.stock_total <= 0) return { text: 'Agotado', css: 'tag-muted' };
    if (topSellerIds().indexOf(p.id_product) >= 0) return { text: 'Más vendido', css: 'tag-hot' };
    if (newestIds(4).indexOf(p.id_product) >= 0) return { text: 'Novedad', css: 'tag-new' };
    if (p.stock_total <= 10) return { text: 'Últimas piezas', css: 'tag-hot' };
    return null;
}

export function productCard(p) {
    const colors = colorsOf(p);
    const tag = productTag(p);
    const href = '#/producto/' + p.id_product;
    return '<article class="card">' +
        '<a class="card-media" href="' + href + '" tabindex="-1" aria-hidden="true">' +
            productArt(p, colors[0] ? colors[0].color_code : 'black') +
        '</a>' +
        favButton(p.id_product, 'on-card') +
        '<div class="card-body">' +
            (tag ? '<span class="card-tag ' + tag.css + '">' + esc(tag.text) + '</span>' : '') +
            '<h3 class="card-title"><a href="' + href + '">' + esc(p.name) + '</a></h3>' +
            '<p class="card-sub">' + esc((DEPARTMENTS[p.department] || p.category) + ' · ' + (GENDERS[p.target_gender] || '')) + '</p>' +
            '<p class="card-colors">' + colors.length + (colors.length === 1 ? ' color' : ' colores') + '</p>' +
            (p.reviews ? '<p class="card-rating">' + stars(p.rating) + '<span>(' + p.reviews + ')</span></p>' : '') +
            '<p class="card-price">' + money.format(p.base_price) + '</p>' +
        '</div>' +
    '</article>';
}

export function productRail(title, products, link) {
    if (!products.length) return '';
    return '<section class="rail">' +
        '<div class="section-head"><h2>' + esc(title) + '</h2>' +
            (link ? '<a class="link-more" href="' + esc(link.href) + '">' + esc(link.label) + '</a>' : '') +
        '</div>' +
        '<div class="rail-track">' + products.map(productCard).join('') + '</div>' +
    '</section>';
}
