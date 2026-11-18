/* Carrito de compras. */
import { $, clone, field, fill, show, slot, money, toast, emptyState, setTitle } from '../ui.js';
import { productArt } from '../illustrations.js';
import { productRail } from '../components.js';
import { cartLines, cartSubtotal, removeFromCart, setQuantity, state, toggleFavorite, isFavorite } from '../store.js';

export function shippingEstimate(subtotalAfterDiscount) {
    return subtotalAfterDiscount >= state.shipping.free_from ? 0 : state.shipping.cost;
}

function cartLine(l) {
    const line = clone('tpl-cart-line');
    const productHref = '#/producto/' + l.product.id_product;
    line.dataset.variant = l.id_variant;
    line.dataset.product = l.product.id_product;

    const thumb = field(line, 'thumb');
    thumb.href = productHref + '?color=' + encodeURIComponent(l.variant.color_code);
    thumb.append(productArt(l.product, l.variant.color_code));
    field(line, 'name').href = productHref;

    fill(line, {
        name: l.product.name,
        total: money.format(l.total),
        category: l.product.category,
        variant: l.variant.color + ' · Talla ' + (l.variant.size === 'UNI' ? 'unitalla' : l.variant.size),
        lowStock: 'Solo quedan ' + l.variant.stock_total + ' piezas.'
    });
    show(field(line, 'lowStock'), l.variant.stock_total < l.quantity);
    $('[data-action="quantity"]', line).value = String(l.quantity);
    return line;
}

function draw(page) {
    setTitle('Carrito');
    const lines = cartLines();

    if (!lines.length) {
        page.replaceChildren(
            emptyState('Tu carrito está vacío', 'Cuando agregues productos aparecerán aquí.', { href: '#/tienda', label: 'Ir a la tienda' }),
            productRail('Lo más vendido', state.products.slice().sort(function (a, b) { return b.sold - a.sold; }).slice(0, 8))
        );
        return;
    }

    const subtotal = cartSubtotal();
    const shipping = shippingEstimate(subtotal);
    const missing = Math.max(0, state.shipping.free_from - subtotal);
    const cart = clone('tpl-cart');

    fill(cart, {
        cartCount: '(' + lines.reduce(function (s, l) { return s + l.quantity; }, 0) + ')',
        freeShipText: missing > 0 ? 'Te faltan ' + money.format(missing) + ' para el envío gratis.' : '¡Tienes envío gratis!',
        cartSubtotal: money.format(subtotal),
        cartShipping: shipping ? money.format(shipping) : 'Gratis',
        cartTotal: money.format(subtotal + shipping)
    });
    $('.free-ship progress', cart).value = Math.min(100, Math.round(subtotal * 100 / state.shipping.free_from));
    slot(cart, 'lines').append(...lines.map(cartLine));

    page.replaceChildren(cart);
}

export function renderCart(page) {
    draw(page);

    page.addEventListener('change', function (e) {
        const select = e.target.closest('[data-action="quantity"]');
        if (select) {
            setQuantity(Number(select.closest('.cart-line').dataset.variant), Number(select.value));
            draw(page);
        }
    });

    page.addEventListener('click', function (e) {
        const button = e.target.closest('[data-action="remove"], [data-action="move"]');
        if (!button) return;
        const line = button.closest('.cart-line');
        if (button.dataset.action === 'move') {
            if (!isFavorite(line.dataset.product)) toggleFavorite(line.dataset.product);
            toast('Se movió a tus favoritos', { action: { href: '#/favoritos', label: 'Ver favoritos' } });
        }
        removeFromCart(Number(line.dataset.variant));
        draw(page);
    });
}
