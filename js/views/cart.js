/* Carrito de compras. */
import { $, clone, field, fill, show, slot, money, toast, emptyState, setTitle } from '../ui.js';
import { productArt } from '../illustrations.js';
import { productRail } from '../components.js';
import { offerGiftWrap } from '../gift.js';
import { account, cartDiscountable, cartHasOutlet, cartLines, cartSubtotal, orderTotals, removeFromCart, setQuantity, shopProducts, toggleFavorite, isFavorite } from '../store.js';

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
    show(field(line, 'outletNote'), l.outlet);
    const quantity = $('[data-action="quantity"]', line);
    quantity.max = account().max_units_per_item;
    quantity.value = String(l.quantity);
    return line;
}

function draw(page) {
    setTitle('Carrito');
    const lines = cartLines();

    if (!lines.length) {
        page.replaceChildren(
            emptyState('Tu carrito está vacío', 'Cuando agregues productos aparecerán aquí.', { href: '#/tienda', label: 'Ir a la tienda' }),
            productRail('Lo más vendido', shopProducts().sort(function (a, b) { return b.sold - a.sold; }).slice(0, 8))
        );
        return;
    }

    const acc = account();
    const t = orderTotals(cartSubtotal(), 0, 'shipping', cartDiscountable());
    const freeFrom = acc.free_shipping_from;
    const missing = Math.max(0, freeFrom - (t.subtotal - t.discount));
    const cart = clone('tpl-cart');

    fill(cart, {
        cartCount: '(' + lines.reduce(function (s, l) { return s + l.quantity; }, 0) + ')',
        freeShipText: freeFrom === 0
            ? 'Envío gratis y prioritario por ser cliente ' + acc.name + ': llega en ' + acc.delivery_days + '.'
            : (missing > 0 ? 'Te faltan ' + money.format(missing) + ' para el envío gratis.' : '¡Tienes envío gratis!'),
        cartSubtotal: money.format(t.subtotal),
        cartTierName: acc.name + ' (' + acc.discount_percent + ' %)',
        cartTierDiscount: '−' + money.format(t.tierDiscount),
        cartShipping: t.shipping ? money.format(t.shipping) : 'Gratis',
        cartTotal: money.format(t.total)
    });
    show(field(cart, 'cartTierRow'), t.tierDiscount > 0);
    show(field(cart, 'cartOutletNote'), cartHasOutlet());
    $('.free-ship progress', cart).value = freeFrom === 0 ? 100 : Math.min(100, Math.round((t.subtotal - t.discount) * 100 / freeFrom));
    slot(cart, 'lines').append(...lines.map(cartLine));

    page.replaceChildren(cart);
}

export function renderCart(page) {
    draw(page);

    page.addEventListener('change', function (e) {
        const select = e.target.closest('[data-action="quantity"]');
        if (select) {
            setQuantity(Number(select.closest('.cart-line').dataset.variant), Math.max(1, Math.floor(Number(select.value)) || 1));
            draw(page);
        }
    });

    page.addEventListener('click', function (e) {
        if (e.target.closest('[data-action="checkout"]')) {
            e.preventDefault();
            offerGiftWrap(function () { window.location.hash = '#/pago'; });
            return;
        }
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
