/* Cuenta del cliente: inicio de sesión, registro y mis pedidos. */
import { api } from '../api.js';
import { $, $$, clone, field, fill, slot, money, badge, message, toast, formatDate, loading, setTitle, ORDER_STATUS, PAYMENT_METHODS } from '../ui.js';
import { productArt } from '../illustrations.js';
import { login, logout, register, state } from '../store.js';

export function authPanel(title, intro, onSuccess) {
    const panel = fill(clone('tpl-auth'), { title: title, intro: intro });
    const loginForm = $('[data-form="login"]', panel);
    const registerForm = $('[data-form="register"]', panel);

    panel.addEventListener('click', function (e) {
        const tab = e.target.closest('[data-auth]');
        if (!tab) return;
        const isLogin = tab.dataset.auth === 'login';
        loginForm.hidden = !isLogin;
        registerForm.hidden = isLogin;
        $$('[data-auth]', panel).forEach(function (b) {
            b.classList.toggle('active', b === tab);
            b.setAttribute('aria-selected', String(b === tab));
        });
    });

    const submit = function (form, action) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            const button = form.querySelector('button');
            button.disabled = true;
            try {
                const customer = await action(Object.fromEntries(new FormData(form)));
                toast('Hola, ' + customer.first_name);
                onSuccess(customer);
            } catch (err) {
                toast(err.message, { error: true });
                button.disabled = false;
            }
        });
    };

    submit(loginForm, function (d) { return login(d.email, d.password); });
    submit(registerForm, function (d) {
        d.accepts_marketing = d.accepts_marketing === '1';
        return register(d);
    });
    return panel;
}

function orderLine(i) {
    const line = fill(clone('tpl-order-line'), {
        name: i.name,
        detail: i.color + ' · ' + (i.size === 'UNI' ? 'Unitalla' : 'Talla ' + i.size) + ' · ' + i.quantity + ' pza',
        total: money.format(Number(i.unit_price) * Number(i.quantity))
    });
    field(line, 'name').href = '#/producto/' + i.id_product;
    slot(line, 'art').replaceWith(productArt({ name: i.name, category_code: i.category_code }, i.color_code, 'mini'));
    return line;
}

function orderCard(o) {
    const st = ORDER_STATUS[o.status] || { label: o.status, css: '' };
    const delivery = o.delivery_type === 'shipping'
        ? 'Envío a ' + [o.street, o.ext_number].filter(Boolean).join(' ') + ', ' + (o.city || '')
        : 'Recoger en ' + o.store_name;
    const card = fill(clone('tpl-order-card'), {
        id: o.id_order,
        date: formatDate(o.order_date),
        info: delivery + ' · Pago: ' + (PAYMENT_METHODS[o.payment_method] || '—') + (o.payment_status === 'pending' ? ' (pendiente)' : ''),
        total: money.format(Number(o.total))
    });
    slot(card, 'status').replaceWith(badge(st.label, st.css));
    slot(card, 'items').append(...o.items.map(orderLine));
    return card;
}

export async function renderAccount(page, params) {
    setTitle('Mi cuenta');
    const back = params.volver;

    if (!state.customer) {
        page.replaceChildren(authPanel('Mi cuenta', 'Inicia sesión para ver tus pedidos y comprar más rápido.', function () {
            if (back) {
                window.location.hash = back;
            } else {
                renderAccount(page, {});
            }
        }));
        return;
    }

    const c = state.customer;
    const account = fill(clone('tpl-account'), {
        name: c.first_name,
        contact: c.email + ' · ' + c.phone,
        level: c.loyalty_level
    });
    const orders = slot(account, 'orders');
    orders.append(loading('Cargando tus pedidos…'));
    page.replaceChildren(account);

    $('[data-action="logout"]', account).addEventListener('click', async function () {
        await logout();
        toast('Cerraste sesión');
        renderAccount(page, {});
    });

    try {
        const list = await api('/me/orders');
        orders.replaceChildren(...(list.length ? list.map(orderCard) : [clone('tpl-no-orders')]));
    } catch (err) {
        orders.replaceChildren(message(err.message, 'bad-text'));
    }
}
