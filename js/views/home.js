/* Inicio: banner de temporada, departamentos, carruseles y cupones. */
import { clone, fill, slot, money, setTitle } from '../ui.js';
import { productArt } from '../illustrations.js';
import { productRail } from '../components.js';
import { activePromotions, newestIds, state } from '../store.js';
import { currentTheme } from '../theme.js';

function showTheme(root, theme) {
    fill(root, { season: theme.name, heroTitle: theme.hero_title, heroText: theme.hero_text });
}

function couponTile(promo) {
    const pct = Number(promo.discount_percent);
    return fill(clone('tpl-coupon'), {
        pct: pct > 0 ? pct + '%' : 'Envío',
        description: promo.description || promo.code,
        code: promo.code
    });
}

export async function renderHome(view) {
    setTitle('');
    const home = clone('tpl-home');

    const bestSellers = state.products.slice().sort(function (a, b) {
        return (b.sold - a.sold) || (b.rating - a.rating);
    }).slice(0, 8);
    const newest = newestIds(8).map(function (id) { return state.byId.get(id); });
    const topRated = state.products.filter(function (p) { return p.reviews > 0; })
        .sort(function (a, b) { return (b.rating - a.rating) || (b.reviews - a.reviews); }).slice(0, 8);

    showTheme(home, currentTheme);
    fill(home, { freeFrom: money.format(state.shipping.free_from) });

    slot(home, 'heroArt').append(productArt({ name: 'Tenis', category_code: 'footwear' }, 'white', 'hero-shoe'));
    slot(home, 'tileFootwear').replaceWith(productArt({ name: 'Tenis', category_code: 'footwear' }, 'black'));
    slot(home, 'tileApparel').replaceWith(productArt({ name: 'Sudadera', category_code: 'hoodies' }, 'gray'));
    slot(home, 'tileAccessories').replaceWith(productArt({ name: 'Mochila', category_code: 'accessories' }, 'navy'));

    slot(home, 'bestSellers').replaceWith(productRail('Lo más vendido', bestSellers, { href: '#/tienda?orden=vendidos', label: 'Ver todo' }));
    slot(home, 'newest').replaceWith(productRail('Novedades', newest, { href: '#/tienda?orden=nuevo', label: 'Ver novedades' }));
    slot(home, 'topRated').replaceWith(productRail('Mejor calificados', topRated, { href: '#/tienda?orden=calificacion', label: 'Ver todo' }));

    view.replaceChildren(home);

    const onTheme = function (e) {
        if (!document.body.contains(home)) {
            document.removeEventListener('jam:theme', onTheme);
            return;
        }
        showTheme(home, e.detail);
    };
    document.addEventListener('jam:theme', onTheme);

    const promos = await activePromotions();
    const strip = slot(home, 'coupons');
    strip.append(...promos.slice(0, 3).map(couponTile));
    strip.hidden = !promos.length;
}
