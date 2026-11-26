/* Vistas sencillas: favoritos, ofertas, sucursales, ayuda y páginas legales. */
import { $, clone, field, fill, show, slot, money, message, emptyState, setTitle, toast } from '../ui.js';
import { productCard } from '../components.js';
import { activePromotions, product, state } from '../store.js';

export function renderFavorites(page) {
    setTitle('Favoritos');
    const items = state.favorites.map(product).filter(Boolean);
    const favorites = fill(clone('tpl-favorites'), { count: '(' + items.length + ')' });
    const grid = slot(favorites, 'grid');
    if (items.length) {
        grid.append(...items.map(productCard));
    } else {
        grid.replaceWith(emptyState('Aún no tienes favoritos', 'Toca el corazón de un producto para guardarlo aquí.', { href: '#/tienda', label: 'Explorar la tienda' }));
    }
    page.replaceChildren(favorites);
}

function couponCard(p) {
    const pct = Number(p.discount_percent);
    const card = fill(clone('tpl-coupon-big'), {
        pct: pct > 0 ? pct + '%' : 'Envío',
        description: p.description || p.code,
        until: String(p.end_date).slice(0, 10),
        code: p.code
    });
    $('[data-action="copy"]', card).dataset.code = p.code;
    return card;
}

function copyCode(code) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(function () { toast('Cupón ' + code + ' copiado'); }, function () { toast('Tu cupón: ' + code); });
    } else {
        toast('Tu cupón: ' + code);
    }
}

export async function renderOffers(page) {
    setTitle('Ofertas');
    const offers = clone('tpl-offers');
    const coupons = slot(offers, 'coupons');
    slot(offers, 'grid').append(...state.products.slice().sort(function (a, b) {
        return (b.rating - a.rating) || (b.sold - a.sold);
    }).slice(0, 8).map(productCard));
    page.replaceChildren(offers);

    coupons.addEventListener('click', function (e) {
        const button = e.target.closest('[data-action="copy"]');
        if (button) copyCode(button.dataset.code);
    });

    const promos = await activePromotions();
    if (promos.length) {
        coupons.append(...promos.map(couponCard));
    } else {
        coupons.replaceWith(message('No hay cupones vigentes en este momento.'));
    }
}

function storeCard(s) {
    const card = fill(clone('tpl-store-card'), {
        name: s.store_name,
        location: s.is_online
            ? 'Envíos a todo México. Gratis desde ' + money.format(state.shipping.free_from) + '.'
            : [s.city, s.state].filter(Boolean).join(', '),
        hours: s.opening_hours ? 'Horario: ' + s.opening_hours : '',
        phone: s.contact_phone
    });
    field(card, 'phone').href = 'tel:+52' + String(s.contact_phone || '').replace(/\D/g, '');
    show(field(card, 'hours'), !s.is_online && Boolean(s.opening_hours));
    return card;
}

export function renderStores(page) {
    setTitle('Sucursales');
    const stores = clone('tpl-stores');
    slot(stores, 'stores').append(...state.stores.map(storeCard));
    page.replaceChildren(stores);
}

export function renderHelp(page) {
    setTitle('Ayuda');
    page.replaceChildren(fill(clone('tpl-help'), {
        freeFrom: money.format(state.shipping.free_from),
        shippingCost: money.format(state.shipping.cost)
    }));
}

const LEGAL_PAGES = {
    privacidad: 'Aviso de privacidad',
    terminos: 'Términos y condiciones',
    devoluciones: 'Cambios y devoluciones'
};

export function renderLegal(page, name) {
    if (!LEGAL_PAGES[name]) {
        page.replaceChildren(emptyState('Página no encontrada', 'El enlace que seguiste no existe.', { href: '#/', label: 'Ir al inicio' }));
        return;
    }
    setTitle(LEGAL_PAGES[name]);
    page.replaceChildren(clone('tpl-legal-' + name));
}
