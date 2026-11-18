/* Panel lateral que confirma lo que se agregó al carrito. */
import { $, esc, money } from './ui.js';
import { productArt } from './illustrations.js';
import { cartCount, cartSubtotal, findVariant, state } from './store.js';

export function openMiniCart(idVariant, quantity) {
    const found = findVariant(idVariant);
    if (!found) return;
    const p = found.product;
    const v = found.variant;
    const subtotal = cartSubtotal();
    const missing = Math.max(0, state.shipping.free_from - subtotal);

    $('#miniCartBody').innerHTML =
        '<div class="mini-cart-item">' + productArt(p, v.color_code, 'mini') +
            '<div><strong>' + esc(p.name) + '</strong>' +
            '<p class="muted small">' + esc(v.color + ' · ' + (v.size === 'UNI' ? 'Unitalla' : 'Talla ' + v.size) + ' · ' + quantity + ' pza') + '</p>' +
            '<p>' + money.format(p.base_price * quantity) + '</p></div>' +
        '</div>' +
        '<p class="mini-cart-total"><span>Carrito (' + cartCount() + ')</span><strong>' + money.format(subtotal) + '</strong></p>' +
        '<p class="small ' + (missing ? 'muted' : 'ok-text') + '">' +
            (missing ? 'Te faltan ' + money.format(missing) + ' para el envío gratis.' : '¡Tu pedido ya tiene envío gratis!') + '</p>' +
        '<div class="mini-cart-actions">' +
            '<a class="btn btn-ghost" href="#/carrito">Ver carrito</a>' +
            '<a class="btn btn-dark" href="#/pago">Pagar</a>' +
        '</div>';

    $('#miniCart').classList.add('open');
    $('#miniCart').setAttribute('aria-hidden', 'false');
    $('#miniCartBackdrop').hidden = false;
}

export function closeMiniCart() {
    $('#miniCart').classList.remove('open');
    $('#miniCart').setAttribute('aria-hidden', 'true');
    $('#miniCartBackdrop').hidden = true;
}
