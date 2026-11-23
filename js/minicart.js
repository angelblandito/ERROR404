/* Panel lateral que confirma lo que se agregó al carrito. */
import { $, field, fill, slot, money } from './ui.js';
import { productArt } from './illustrations.js';
import { account, cartCount, cartDiscountable, cartSubtotal, findVariant, orderTotals, unitPrice } from './store.js';

export function openMiniCart(idVariant, quantity) {
    const found = findVariant(idVariant);
    if (!found) return;
    const p = found.product;
    const v = found.variant;
    const panel = $('#miniCart');
    const subtotal = cartSubtotal();
    const t = orderTotals(subtotal, 0, 'shipping', cartDiscountable());
    const missing = Math.max(0, account().free_shipping_from - (t.subtotal - t.discount));

    slot(panel, 'art').replaceChildren(productArt(p, v.color_code, 'mini'));
    fill(panel, {
        name: p.name,
        detail: v.color + ' · ' + (v.size === 'UNI' ? 'Unitalla' : 'Talla ' + v.size) + ' · ' + quantity + ' pza',
        price: money.format(unitPrice(p) * quantity),
        count: 'Carrito (' + cartCount() + ')',
        subtotal: money.format(t.subtotal - t.discount),
        shipping: missing ? 'Te faltan ' + money.format(missing) + ' para el envío gratis.' : '¡Tu pedido ya tiene envío gratis!'
    });
    const shipping = field(panel, 'shipping');
    shipping.classList.toggle('muted', missing > 0);
    shipping.classList.toggle('ok-text', missing === 0);

    panel.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');
    $('#miniCartBackdrop').hidden = false;
}

export function closeMiniCart() {
    $('#miniCart').classList.remove('open');
    $('#miniCart').setAttribute('aria-hidden', 'true');
    $('#miniCartBackdrop').hidden = true;
}
