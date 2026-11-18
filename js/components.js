/* Piezas de interfaz reutilizadas por varias vistas. */
import { clone, field, fill, show, slot, money, stars, DEPARTMENTS, GENDERS } from './ui.js';
import { productArt } from './illustrations.js';
import { colorsOf, isFavorite, newestIds, state } from './store.js';

export function setFavState(button, active) {
    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', String(active));
    button.setAttribute('aria-label', active ? 'Quitar de favoritos' : 'Agregar a favoritos');
}

export function favButton(idProduct, extraClass) {
    const button = clone('tpl-fav-button');
    button.dataset.fav = idProduct;
    if (extraClass) button.classList.add(extraClass);
    setFavState(button, isFavorite(idProduct));
    return button;
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
    const card = clone('tpl-product-card');
    const colors = colorsOf(p);
    const tag = productTag(p);
    const href = '#/producto/' + p.id_product;

    const media = field(card, 'media');
    media.href = href;
    media.append(productArt(p, colors[0] ? colors[0].color_code : 'black'));
    media.after(favButton(p.id_product, 'on-card'));

    if (tag) {
        const tagEl = show(field(card, 'tag'), true);
        tagEl.textContent = tag.text;
        tagEl.classList.add(tag.css);
    }

    field(card, 'name').href = href;
    fill(card, {
        name: p.name,
        sub: (DEPARTMENTS[p.department] || p.category) + ' · ' + (GENDERS[p.target_gender] || ''),
        colors: colors.length + (colors.length === 1 ? ' color' : ' colores'),
        price: money.format(p.base_price)
    });

    if (p.reviews) {
        show(field(card, 'rating'), true);
        slot(card, 'stars').replaceWith(stars(p.rating));
        field(card, 'reviews').textContent = '(' + p.reviews + ')';
    }
    return card;
}

export function productRail(title, products, link) {
    const rail = clone('tpl-rail');
    if (!products.length) {
        rail.hidden = true;
        return rail;
    }
    field(rail, 'title').textContent = title;
    if (link) {
        const more = show(field(rail, 'link'), true);
        more.href = link.href;
        more.textContent = link.label;
    }
    slot(rail, 'items').append(...products.map(productCard));
    return rail;
}
