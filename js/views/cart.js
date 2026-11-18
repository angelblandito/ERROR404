/* Bolsa de compras. */
import { $, esc, money, toast, emptyState, setTitle } from '../ui.js';
import { productArt } from '../illustrations.js';
import { productRail } from '../components.js';
import { cartLines, cartSubtotal, removeFromCart, setQuantity, state, toggleFavorite, isFavorite } from '../store.js';

export function shippingEstimate(subtotalAfterDiscount) {
    return subtotalAfterDiscount >= state.shipping.free_from ? 0 : state.shipping.cost;
}

function draw(page) {
    setTitle('Bolsa');
    const lines = cartLines();

    if (!lines.length) {
        page.innerHTML = emptyState('Tu bolsa está vacía', 'Cuando agregues productos aparecerán aquí.', { href: '#/tienda', label: 'Ir a la tienda' }) +
            productRail('Lo más vendido', state.products.slice().sort(function (a, b) { return b.sold - a.sold; }).slice(0, 8));
        return;
    }

    const subtotal = cartSubtotal();
    const shipping = shippingEstimate(subtotal);
    const missing = Math.max(0, state.shipping.free_from - subtotal);
    const progress = Math.min(100, Math.round(subtotal * 100 / state.shipping.free_from));

    page.innerHTML = '<div class="cart-page">' +
        '<section class="cart-lines">' +
            '<h1>Bolsa <span class="muted">(' + lines.reduce(function (s, l) { return s + l.quantity; }, 0) + ')</span></h1>' +
            '<div class="free-ship">' +
                (missing > 0
                    ? '<p>Te faltan <strong>' + money.format(missing) + '</strong> para el envío gratis.</p>'
                    : '<p><strong>¡Tienes envío gratis!</strong></p>') +
                '<div class="bar"><i style="width:' + progress + '%"></i></div>' +
            '</div>' +
            lines.map(function (l) {
                const options = Array.from({ length: 10 }, function (_, i) {
                    return '<option' + (i + 1 === l.quantity ? ' selected' : '') + '>' + (i + 1) + '</option>';
                }).join('');
                return '<article class="cart-line">' +
                    '<a href="#/producto/' + l.product.id_product + '?color=' + esc(l.variant.color_code) + '" class="cart-thumb">' +
                        productArt(l.product, l.variant.color_code) + '</a>' +
                    '<div class="cart-info">' +
                        '<div class="cart-row"><a class="cart-name" href="#/producto/' + l.product.id_product + '">' + esc(l.product.name) + '</a>' +
                            '<strong>' + money.format(l.total) + '</strong></div>' +
                        '<p class="muted">' + esc(l.product.category) + '</p>' +
                        '<p class="muted">' + esc(l.variant.color + ' · Talla ' + (l.variant.size === 'UNI' ? 'unitalla' : l.variant.size)) + '</p>' +
                        (l.variant.stock_total < l.quantity ? '<p class="bad-text small">Solo quedan ' + l.variant.stock_total + ' piezas.</p>' : '') +
                        '<div class="cart-actions">' +
                            '<label>Cantidad <select data-qty="' + l.id_variant + '">' + options + '</select></label>' +
                            '<button class="link-btn" data-move="' + l.id_variant + '" data-product="' + l.product.id_product + '">Mover a favoritos</button>' +
                            '<button class="link-btn" data-remove="' + l.id_variant + '">Eliminar</button>' +
                        '</div>' +
                    '</div>' +
                '</article>';
            }).join('') +
        '</section>' +
        '<aside class="summary">' +
            '<h2>Resumen</h2>' +
            '<div class="sum-row"><span>Subtotal</span><span>' + money.format(subtotal) + '</span></div>' +
            '<div class="sum-row"><span>Envío estimado</span><span>' + (shipping ? money.format(shipping) : 'Gratis') + '</span></div>' +
            '<p class="muted small">Recoger en sucursal no tiene costo. Los cupones se aplican al pagar.</p>' +
            '<div class="sum-row total"><span>Total estimado</span><span>' + money.format(subtotal + shipping) + '</span></div>' +
            '<a class="btn btn-dark btn-block btn-lg" href="#/pago">Pagar</a>' +
            '<a class="btn btn-ghost btn-block" href="#/tienda">Seguir comprando</a>' +
        '</aside>' +
    '</div>';

}

export function renderCart(page) {
    draw(page);

    page.addEventListener('change', function (e) {
        const select = e.target.closest('[data-qty]');
        if (select) {
            setQuantity(Number(select.dataset.qty), Number(select.value));
            draw(page);
        }
    });

    page.addEventListener('click', function (e) {
        const remove = e.target.closest('[data-remove]');
        if (remove) {
            removeFromCart(Number(remove.dataset.remove));
            draw(page);
            return;
        }
        const move = e.target.closest('[data-move]');
        if (move) {
            if (!isFavorite(move.dataset.product)) toggleFavorite(move.dataset.product);
            removeFromCart(Number(move.dataset.move));
            toast('Se movió a tus favoritos', { action: { href: '#/favoritos', label: 'Ver favoritos' } });
            draw(page);
        }
    });
}
