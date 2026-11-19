/* Pago: entrega, método de pago y confirmación del pedido. */
import { api } from '../api.js';
import { $, clone, field, fill, show, slot, money, toast, emptyState, setTitle } from '../ui.js';
import { productArt } from '../illustrations.js';
import { account, activePromotions, cartLines, cartSubtotal, clearCart, loadCatalog, orderTotals, physicalStores, state, stockAt } from '../store.js';
import { authPanel } from './account.js';

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

function summaryLine(l) {
    const line = fill(clone('tpl-summary-line'), {
        name: l.product.name,
        detail: l.variant.color + ' · ' + (l.variant.size === 'UNI' ? 'Unitalla' : l.variant.size) + ' · ' + l.quantity + ' pza',
        total: money.format(l.total)
    });
    slot(line, 'art').replaceWith(productArt(l.product, l.variant.color_code, 'mini'));
    return line;
}

function storeOption(s, checked) {
    const option = fill(clone('tpl-store-option'), {
        name: s.store_name,
        address: [s.city, s.state].filter(Boolean).join(', ') + (s.opening_hours ? ' · ' + s.opening_hours : '')
    });
    const input = option.querySelector('input');
    input.value = s.id_store;
    input.checked = checked;
    field(option, 'stock').dataset.store = s.id_store;
    return option;
}

export async function renderCheckout(page) {
    setTitle('Pagar');
    const lines = cartLines();
    if (!lines.length) {
        page.replaceChildren(emptyState('Tu carrito está vacío', 'Agrega productos para continuar con el pago.', { href: '#/tienda', label: 'Ir a la tienda' }));
        return;
    }

    if (!state.customer) {
        page.replaceChildren(authPanel('Pagar', 'Inicia sesión o crea tu cuenta para terminar tu compra. Tu carrito se conserva.', function () {
            renderCheckout(page);
        }));
        return;
    }

    const [states, promotions] = await Promise.all([loadStates(), activePromotions()]);
    const c = state.customer;
    const checkout = clone('tpl-checkout');
    const form = $('.checkout-form', checkout);
    const couponInput = $('input[name="coupon"]', checkout);
    const couponMessage = field(checkout, 'couponMessage');
    let coupon = null;

    field(checkout, 'receiver').textContent = c.first_name + ' ' + c.last_name_paternal + ' · ' + c.phone;
    states.forEach(function (s) { form.id_state.add(new Option(s.name, s.id_state)); });
    slot(checkout, 'pickupFields').append(...physicalStores().map(function (s, i) { return storeOption(s, i === 0); }));
    slot(checkout, 'lines').append(...lines.map(summaryLine));

    page.replaceChildren(checkout);

    const delivery = function () { return form.delivery.value; };
    const pickupStore = function () { return Number((form.querySelector('input[name="pickup_store"]:checked') || {}).value || 0); };
    const fulfillingStore = function () { return delivery() === 'shipping' ? state.onlineStore : pickupStore(); };

    const acc = account();
    const totals = function () {
        return orderTotals(cartSubtotal(), coupon ? coupon.discount_percent : 0, delivery());
    };

    const missingAt = function (idStore) {
        return lines.filter(function (l) { return stockAt(l.variant, idStore) < l.quantity; });
    };

    const refresh = function () {
        const isShipping = delivery() === 'shipping';
        slot(checkout, 'shippingFields').hidden = !isShipping;
        slot(checkout, 'pickupFields').hidden = isShipping;

        const cash = form.querySelector('input[name="payment"][value="cash"]');
        cash.disabled = isShipping;
        if (isShipping && cash.checked) {
            form.querySelector('input[name="payment"][value="card"]').checked = true;
        }
        slot(checkout, 'cardFields').hidden = form.payment.value !== 'card';

        checkout.querySelectorAll('.stock-note').forEach(function (el) {
            const missing = missingAt(Number(el.dataset.store));
            el.textContent = missing.length ? 'Sin existencias de: ' + missing.map(function (l) { return l.product.name; }).join(', ') : 'Todo disponible';
            el.classList.toggle('bad-text', missing.length > 0);
            el.classList.toggle('ok-text', missing.length === 0);
        });

        const t = totals();
        fill(checkout, {
            shipLabel: t.subtotal - t.discount >= acc.free_shipping_from
                ? (acc.free_shipping_from === 0 ? 'Gratis y prioritario' : 'Gratis') + ' · ' + acc.delivery_days
                : money.format(state.shipping.cost) + ' · gratis desde ' + money.format(acc.free_shipping_from),
            orderSubtotal: money.format(t.subtotal),
            tierName: acc.name + ' (' + acc.discount_percent + ' %)',
            tierDiscount: '−' + money.format(t.tierDiscount),
            couponCode: coupon ? coupon.code : '',
            orderDiscount: '−' + money.format(t.couponDiscount),
            orderShipping: t.shipping ? money.format(t.shipping) : 'Gratis',
            orderTotal: money.format(t.total)
        });
        show(field(checkout, 'tierRow'), t.tierDiscount > 0);
        show(field(checkout, 'discountRow'), t.couponDiscount > 0);
    };

    form.addEventListener('change', refresh);

    $('[data-action="coupon"]', checkout).addEventListener('click', function () {
        const code = couponInput.value.trim().toUpperCase();
        const found = promotions.find(function (p) { return p.code.toUpperCase() === code; });
        coupon = found || null;
        couponMessage.textContent = found
            ? 'Cupón aplicado: ' + (found.description || found.code)
            : (code ? 'El cupón no existe o no está vigente.' : '');
        couponMessage.classList.toggle('ok-text', Boolean(found));
        couponMessage.classList.toggle('bad-text', !found);
        refresh();
    });

    $('[data-action="place-order"]', checkout).addEventListener('click', async function () {
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
                    delivery_days: acc.delivery_days,
                    store: (state.stores.find(function (s) { return s.id_store === fulfillingStore(); }) || {}).store_name
                })));
            } catch (e) {
                /* sin sessionStorage */
            }
            clearCart();
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

    const confirm = fill(clone('tpl-confirmation'), {
        id: id,
        total: order.total !== undefined ? money.format(Number(order.total)) : '',
        instructions: instructions,
        pickup: order.store ? 'Recógelo en ' + order.store + '. Te avisaremos cuando esté listo.' : ''
    });
    show(field(confirm, 'total'), order.total !== undefined);
    show(field(confirm, 'instructions'), Boolean(instructions));
    show(field(confirm, 'pickup'), order.delivery_type === 'pickup' && Boolean(order.store));
    show(field(confirm, 'shipping'), order.delivery_type === 'shipping');
    field(confirm, 'shipping').textContent = (order.priority_shipping ? 'Envío prioritario: llega en ' : 'Llegará en ')
        + (order.delivery_days || '3 a 5 días hábiles') + '.';
    page.replaceChildren(confirm);
}
