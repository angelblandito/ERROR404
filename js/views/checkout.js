/* Pago: entrega, método de pago y confirmación del pedido. */
import { api } from '../api.js';
import { $, esc, money, toast, emptyState, setTitle, PAYMENT_METHODS } from '../ui.js';
import { productArt } from '../illustrations.js';
import { activePromotions, cartLines, cartSubtotal, clearCart, loadCatalog, physicalStores, state, stockAt } from '../store.js';
import { authPanel, bindAuth } from './account.js';

let statesCache = null;

async function loadStates() {
    if (!statesCache) {
        try {
            statesCache = await api('/state?page_size=50');
        } catch (e) {
            statesCache = [];
        }
    }
    return statesCache;
}

function luhn(number) {
    let sum = 0;
    let double = false;
    for (let i = number.length - 1; i >= 0; i--) {
        let d = Number(number[i]);
        if (double) {
            d *= 2;
            if (d > 9) d -= 9;
        }
        sum += d;
        double = !double;
    }
    return sum % 10 === 0;
}

function validateCard(form) {
    const number = (form.card_number.value || '').replace(/\D/g, '');
    const exp = (form.card_exp.value || '').trim();
    const cvv = (form.card_cvv.value || '').trim();
    if (form.card_name.value.trim().length < 3) return 'Escribe el nombre como aparece en la tarjeta.';
    if (number.length < 13 || number.length > 19 || !luhn(number)) return 'El número de tarjeta no es válido.';
    const m = exp.match(/^(\d{2})\/(\d{2})$/);
    if (!m || Number(m[1]) < 1 || Number(m[1]) > 12) return 'La fecha de vencimiento debe tener el formato MM/AA.';
    const expEnd = new Date(2000 + Number(m[2]), Number(m[1]), 1);
    if (expEnd <= new Date()) return 'La tarjeta está vencida.';
    if (!/^\d{3,4}$/.test(cvv)) return 'El código de seguridad debe tener 3 o 4 dígitos.';
    return null;
}

export async function renderCheckout(page) {
    setTitle('Pagar');
    const lines = cartLines();
    if (!lines.length) {
        page.innerHTML = emptyState('Tu carrito está vacío', 'Agrega productos para continuar con el pago.', { href: '#/tienda', label: 'Ir a la tienda' });
        return;
    }

    if (!state.customer) {
        page.innerHTML = '<div class="narrow"><h1>Pagar</h1>' + authPanel('Inicia sesión o crea tu cuenta para terminar tu compra. Tu carrito se conserva.') + '</div>';
        bindAuth($('.narrow', page), function () { renderCheckout(page); });
        return;
    }

    const [states, promotions] = await Promise.all([loadStates(), activePromotions()]);
    const stores = physicalStores();
    const c = state.customer;

    page.innerHTML = '<div class="checkout">' +
        '<form class="checkout-form" id="checkoutForm" novalidate>' +
            '<h1>Pagar</h1>' +
            '<section class="step"><h2><span>1</span> Entrega</h2>' +
                '<div class="choice-grid">' +
                    '<label class="choice"><input type="radio" name="delivery" value="shipping" checked><span><strong>Envío a domicilio</strong><em id="shipLabel"></em></span></label>' +
                    '<label class="choice"><input type="radio" name="delivery" value="pickup"><span><strong>Recoger en sucursal</strong><em>Gratis · listo el mismo día</em></span></label>' +
                '</div>' +
                '<div id="shippingFields">' +
                    '<p class="muted small">Recibe: ' + esc(c.first_name + ' ' + c.last_name_paternal) + ' · ' + esc(c.phone) + '</p>' +
                    '<div class="field-row"><label class="field grow"><span>Calle</span><input class="input" name="street" maxlength="100" autocomplete="address-line1"></label>' +
                    '<label class="field"><span>Número</span><input class="input" name="ext_number" maxlength="10"></label>' +
                    '<label class="field"><span>Interior</span><input class="input" name="int_number" maxlength="10"></label></div>' +
                    '<div class="field-row"><label class="field grow"><span>Colonia</span><input class="input" name="neighborhood" maxlength="80"></label>' +
                    '<label class="field"><span>Código postal</span><input class="input" name="zip_code" inputmode="numeric" maxlength="5" autocomplete="postal-code"></label></div>' +
                    '<div class="field-row"><label class="field grow"><span>Ciudad</span><input class="input" name="city" maxlength="60" autocomplete="address-level2"></label>' +
                    '<label class="field grow"><span>Estado</span><select class="input" name="id_state"><option value="">Elige</option>' +
                        states.map(function (s) { return '<option value="' + s.id_state + '">' + esc(s.name) + '</option>'; }).join('') +
                    '</select></label></div>' +
                    '<label class="field"><span>Referencias (opcional)</span><input class="input" name="reference" maxlength="150"></label>' +
                '</div>' +
                '<div id="pickupFields" hidden>' +
                    stores.map(function (s, i) {
                        return '<label class="choice store-choice"><input type="radio" name="pickup_store" value="' + s.id_store + '"' + (i === 0 ? ' checked' : '') + '>' +
                            '<span><strong>' + esc(s.store_name) + '</strong><em>' + esc([s.city, s.state].filter(Boolean).join(', ')) +
                            (s.opening_hours ? ' · ' + esc(s.opening_hours) : '') + '</em><em class="stock-note" data-store="' + s.id_store + '"></em></span></label>';
                    }).join('') +
                '</div>' +
            '</section>' +
            '<section class="step"><h2><span>2</span> Pago</h2>' +
                '<div class="choice-grid" id="paymentChoices">' +
                    ['card', 'oxxo', 'transfer', 'cash'].map(function (m, i) {
                        const hint = { card: 'Aprobación inmediata', oxxo: 'Paga en efectivo en OXXO', transfer: 'SPEI desde tu banco', cash: 'Solo al recoger en sucursal' }[m];
                        return '<label class="choice"><input type="radio" name="payment" value="' + m + '"' + (i === 0 ? ' checked' : '') + '>' +
                            '<span><strong>' + PAYMENT_METHODS[m] + '</strong><em>' + hint + '</em></span></label>';
                    }).join('') +
                '</div>' +
                '<div id="cardFields">' +
                    '<p class="notice">Pago simulado para el proyecto: los datos de la tarjeta solo se validan en tu navegador; no se envían ni se guardan.</p>' +
                    '<label class="field"><span>Nombre en la tarjeta</span><input class="input" name="card_name" autocomplete="cc-name"></label>' +
                    '<label class="field"><span>Número de tarjeta</span><input class="input" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242"></label>' +
                    '<div class="field-row"><label class="field"><span>Vence (MM/AA)</span><input class="input" name="card_exp" placeholder="12/28" autocomplete="cc-exp" maxlength="5"></label>' +
                    '<label class="field"><span>CVV</span><input class="input" name="card_cvv" inputmode="numeric" maxlength="4" autocomplete="cc-csc"></label></div>' +
                '</div>' +
            '</section>' +
        '</form>' +
        '<aside class="summary">' +
            '<h2>Tu pedido</h2>' +
            '<div class="sum-lines">' + lines.map(function (l) {
                return '<div class="sum-line">' + productArt(l.product, l.variant.color_code, 'mini') +
                    '<div><strong>' + esc(l.product.name) + '</strong><p class="muted small">' + esc(l.variant.color + ' · ' + (l.variant.size === 'UNI' ? 'Unitalla' : l.variant.size) + ' · ' + l.quantity + ' pza') + '</p></div>' +
                    '<span>' + money.format(l.total) + '</span></div>';
            }).join('') + '</div>' +
            '<div class="coupon-input"><input class="input" id="couponInput" placeholder="Código de cupón" autocomplete="off" aria-label="Código de cupón">' +
                '<button class="btn btn-ghost" id="couponBtn" type="button">Aplicar</button></div>' +
            '<p class="small" id="couponMsg"></p>' +
            '<div id="totals"></div>' +
            '<button class="btn btn-dark btn-block btn-lg" id="placeOrder" type="button">Confirmar pedido</button>' +
            '<p class="muted small">Al confirmar, el pedido, el descuento de inventario y el pago se registran juntos en una sola transacción.</p>' +
        '</aside>' +
    '</div>';

    const form = $('#checkoutForm', page);
    let coupon = null;

    const delivery = function () { return form.delivery.value; };
    const pickupStore = function () { return Number((form.querySelector('input[name="pickup_store"]:checked') || {}).value || 0); };
    const fulfillingStore = function () { return delivery() === 'shipping' ? state.onlineStore : pickupStore(); };

    const totals = function () {
        const subtotal = cartSubtotal();
        const discount = coupon ? Math.round(subtotal * Number(coupon.discount_percent)) / 100 : 0;
        const shipping = delivery() === 'shipping' && subtotal - discount < state.shipping.free_from ? state.shipping.cost : 0;
        return { subtotal: subtotal, discount: discount, shipping: shipping, total: subtotal - discount + shipping };
    };

    const missingAt = function (idStore) {
        return lines.filter(function (l) { return stockAt(l.variant, idStore) < l.quantity; });
    };

    const refresh = function () {
        const isShipping = delivery() === 'shipping';
        $('#shippingFields', page).hidden = !isShipping;
        $('#pickupFields', page).hidden = isShipping;
        $('#cardFields', page).hidden = form.payment.value !== 'card';

        const cash = form.querySelector('input[name="payment"][value="cash"]');
        cash.disabled = isShipping;
        if (isShipping && cash.checked) {
            form.querySelector('input[name="payment"][value="card"]').checked = true;
            $('#cardFields', page).hidden = false;
        }

        page.querySelectorAll('.stock-note').forEach(function (el) {
            const missing = missingAt(Number(el.dataset.store));
            el.textContent = missing.length ? 'Sin existencias de: ' + missing.map(function (l) { return l.product.name; }).join(', ') : 'Todo disponible';
            el.className = 'stock-note ' + (missing.length ? 'bad-text' : 'ok-text');
        });

        const t = totals();
        $('#shipLabel', page).textContent = t.subtotal - t.discount >= state.shipping.free_from
            ? 'Gratis · 3 a 5 días hábiles'
            : money.format(state.shipping.cost) + ' · gratis desde ' + money.format(state.shipping.free_from);
        $('#totals', page).innerHTML =
            '<div class="sum-row"><span>Subtotal</span><span>' + money.format(t.subtotal) + '</span></div>' +
            (t.discount ? '<div class="sum-row ok-text"><span>Cupón ' + esc(coupon.code) + '</span><span>−' + money.format(t.discount) + '</span></div>' : '') +
            '<div class="sum-row"><span>Envío</span><span>' + (t.shipping ? money.format(t.shipping) : 'Gratis') + '</span></div>' +
            '<div class="sum-row total"><span>Total</span><span>' + money.format(t.total) + '</span></div>';
    };

    form.addEventListener('change', refresh);

    $('#couponBtn', page).addEventListener('click', function () {
        const code = $('#couponInput', page).value.trim().toUpperCase();
        const found = promotions.find(function (p) { return p.code.toUpperCase() === code; });
        coupon = found || null;
        $('#couponMsg', page).textContent = found
            ? 'Cupón aplicado: ' + (found.description || found.code)
            : (code ? 'El cupón no existe o no está vigente.' : '');
        $('#couponMsg', page).className = 'small ' + (found ? 'ok-text' : 'bad-text');
        refresh();
    });

    $('#placeOrder', page).addEventListener('click', async function () {
        const isShipping = delivery() === 'shipping';
        const required = ['street', 'ext_number', 'neighborhood', 'zip_code', 'city', 'id_state'];
        if (isShipping) {
            const empty = required.filter(function (n) { return !String(form[n].value || '').trim(); });
            if (empty.length) {
                toast('Completa el domicilio de envío.', { error: true });
                form[empty[0]].focus();
                return;
            }
            if (!/^\d{5}$/.test(form.zip_code.value.trim())) {
                toast('El código postal debe tener 5 dígitos.', { error: true });
                form.zip_code.focus();
                return;
            }
        }
        if (form.payment.value === 'card') {
            const cardError = validateCard(form);
            if (cardError) {
                toast(cardError, { error: true });
                return;
            }
        }
        const missing = missingAt(fulfillingStore());
        if (missing.length) {
            toast('No hay existencias suficientes de: ' + missing.map(function (l) { return l.product.name; }).join(', '), { error: true });
            return;
        }

        const button = this;
        button.disabled = true;
        button.textContent = 'Procesando…';
        try {
            const order = await api('/orders/checkout', {
                method: 'POST',
                body: {
                    id_store: fulfillingStore(),
                    delivery_type: delivery(),
                    payment_method: form.payment.value,
                    promotion_code: coupon ? coupon.code : null,
                    notes: 'Pedido de la tienda en línea',
                    address: isShipping ? {
                        street: form.street.value, ext_number: form.ext_number.value, int_number: form.int_number.value,
                        neighborhood: form.neighborhood.value, city: form.city.value, id_state: Number(form.id_state.value),
                        zip_code: form.zip_code.value.trim(), reference: form.reference.value
                    } : null,
                    items: lines.map(function (l) { return { id_variant: l.id_variant, quantity: l.quantity }; })
                }
            });
            try {
                sessionStorage.setItem('jam_last_order', JSON.stringify(Object.assign({}, order, {
                    payment_method: form.payment.value,
                    delivery_type: delivery(),
                    store: (state.stores.find(function (s) { return s.id_store === fulfillingStore(); }) || {}).store_name
                })));
            } catch (e) {
                /* sin sessionStorage */
            }
            clearCart();
            api('/distributed/sync', { method: 'POST' }).catch(function () {});
            loadCatalog(true).catch(function () {});
            window.location.hash = '#/pedido/' + order.id_order;
        } catch (err) {
            toast(err.message, { error: true });
            button.disabled = false;
            button.textContent = 'Confirmar pedido';
        }
    });

    refresh();
}

export function renderConfirmation(page, id) {
    setTitle('Pedido confirmado');
    let order = null;
    try {
        order = JSON.parse(sessionStorage.getItem('jam_last_order'));
    } catch (e) {
        order = null;
    }
    if (!order || String(order.id_order) !== String(id)) {
        order = { id_order: id };
    }
    const reference = 'JAM-' + String(id).padStart(6, '0');
    const instructions = {
        card: 'Tu pago con tarjeta fue aprobado.',
        oxxo: 'Paga en cualquier OXXO con la referencia ' + reference + ' en las próximas 48 horas.',
        transfer: 'Haz la transferencia SPEI con el concepto ' + reference + '. Tu pedido se libera al confirmar el pago.',
        cash: 'Paga en efectivo al recoger tu pedido en la sucursal.'
    }[order.payment_method] || '';

    page.innerHTML = '<div class="confirm">' +
        '<div class="confirm-icon" aria-hidden="true">✓</div>' +
        '<h1>¡Gracias por tu compra!</h1>' +
        '<p>Tu pedido <strong>#' + esc(id) + '</strong> quedó registrado.</p>' +
        (order.total !== undefined ? '<p class="confirm-total">' + money.format(Number(order.total)) + '</p>' : '') +
        (instructions ? '<p class="notice">' + esc(instructions) + '</p>' : '') +
        (order.delivery_type === 'pickup' && order.store ? '<p>Recógelo en <strong>' + esc(order.store) + '</strong>. Te avisaremos cuando esté listo.</p>' : '') +
        (order.delivery_type === 'shipping' ? '<p>Llegará en 3 a 5 días hábiles.</p>' : '') +
        '<div class="confirm-actions"><a class="btn btn-dark" href="#/cuenta">Ver mis pedidos</a><a class="btn btn-ghost" href="#/tienda">Seguir comprando</a></div>' +
    '</div>';
}
