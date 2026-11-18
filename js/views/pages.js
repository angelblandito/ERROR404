/* Vistas sencillas: favoritos, ofertas, sucursales y ayuda. */
import { esc, money, emptyState, setTitle, toast } from '../ui.js';
import { productCard } from '../components.js';
import { activePromotions, product, state } from '../store.js';

export function renderFavorites(page) {
    setTitle('Favoritos');
    const items = state.favorites.map(product).filter(Boolean);
    page.innerHTML = '<div class="plain-page"><h1>Favoritos <span class="muted">(' + items.length + ')</span></h1>' +
        (items.length
            ? '<div class="product-grid wide">' + items.map(productCard).join('') + '</div>'
            : emptyState('Aún no tienes favoritos', 'Toca el corazón de un producto para guardarlo aquí.', { href: '#/tienda', label: 'Explorar la tienda' })) +
    '</div>';
}

export async function renderOffers(page) {
    setTitle('Ofertas');
    page.innerHTML = '<div class="plain-page"><h1>Ofertas y cupones</h1><div id="couponList"></div>' +
        '<div class="section-head"><h2>Recomendados para ti</h2><a class="link-more" href="#/tienda?orden=calificacion">Ver todo</a></div>' +
        '<div class="product-grid wide">' + state.products.slice().sort(function (a, b) {
            return (b.rating - a.rating) || (b.sold - a.sold);
        }).slice(0, 8).map(productCard).join('') + '</div></div>';

    const promos = await activePromotions();
    const list = page.querySelector('#couponList');
    if (!list) return;
    list.innerHTML = promos.length ? '<div class="coupon-grid">' + promos.map(function (p) {
        const pct = Number(p.discount_percent);
        return '<article class="coupon big">' +
            '<span class="coupon-pct">' + (pct > 0 ? pct + '%' : 'Envío') + '</span>' +
            '<div><strong>' + esc(p.description || p.code) + '</strong>' +
            '<span>Vigente hasta el ' + esc(String(p.end_date).slice(0, 10)) + '</span>' +
            '<button class="code-btn" data-copy="' + esc(p.code) + '">' + esc(p.code) + ' · Copiar</button></div>' +
        '</article>';
    }).join('') + '</div>' : '<p class="muted">No hay cupones vigentes en este momento.</p>';

    list.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-copy]');
        if (!btn) return;
        const code = btn.dataset.copy;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(function () { toast('Cupón ' + code + ' copiado'); }, function () { toast('Tu cupón: ' + code); });
        } else {
            toast('Tu cupón: ' + code);
        }
    });
}

export function renderStores(page) {
    setTitle('Sucursales');
    page.innerHTML = '<div class="plain-page"><h1>Sucursales</h1><p class="muted">Compra en línea y recoge sin costo, o visítanos.</p>' +
        '<div class="store-grid">' + state.stores.map(function (s) {
            return '<article class="store-card">' +
                '<h2>' + esc(s.store_name) + '</h2>' +
                (s.is_online
                    ? '<p>Envíos a todo México. Gratis desde ' + money.format(state.shipping.free_from) + '.</p>'
                    : '<p>' + esc([s.city, s.state].filter(Boolean).join(', ')) + '</p>' +
                      (s.opening_hours ? '<p class="muted">Horario: ' + esc(s.opening_hours) + '</p>' : '')) +
                '<p class="muted">Tel. ' + esc(s.contact_phone) + '</p>' +
                '<a class="btn btn-ghost btn-sm" href="#/tienda?disp=1">Ver productos disponibles</a>' +
            '</article>';
        }).join('') + '</div></div>';
}

export function renderHelp(page) {
    setTitle('Ayuda');
    const faqs = [
        ['¿Cuánto cuesta el envío?', 'El envío a domicilio es gratis en compras desde ' + money.format(state.shipping.free_from) +
            ' (después del cupón). Si no, cuesta ' + money.format(state.shipping.cost) + '. Llega en 3 a 5 días hábiles.'],
        ['¿Puedo recoger en tienda?', 'Sí. Elige “Recoger en sucursal” al pagar y escoge Tijuana o CDMX. Es gratis y te mostramos qué tiene existencias cada sucursal.'],
        ['¿Qué métodos de pago aceptan?', 'Tarjeta (aprobación inmediata), OXXO, transferencia SPEI y efectivo al recoger en sucursal. En este proyecto académico los pagos son simulados.'],
        ['¿Cómo uso un cupón?', 'Escribe el código en el resumen del pago. Encuentra los cupones vigentes en Ofertas.'],
        ['¿Puedo cambiar o devolver un producto?', 'Tienes 30 días desde que lo recibes. Debe estar sin uso y con etiquetas.'],
        ['¿Dónde veo mis pedidos?', 'En Mi cuenta, con su estado, método de pago y forma de entrega.']
    ];
    page.innerHTML = '<div class="plain-page narrow"><h1>Ayuda</h1>' + faqs.map(function (f, i) {
        return '<details class="acc"' + (i === 0 ? ' open' : '') + '><summary>' + esc(f[0]) + '</summary><p>' + esc(f[1]) + '</p></details>';
    }).join('') + '</div>';
}
