/* Cuenta del cliente: inicio de sesión, registro y mis pedidos. */
import { api } from '../api.js';
import { $, esc, money, badge, toast, formatDate, loading, setTitle, ORDER_STATUS, PAYMENT_METHODS } from '../ui.js';
import { productArt } from '../illustrations.js';
import { login, logout, register, state } from '../store.js';

export function authPanel(intro) {
    return '<div class="auth">' +
        (intro ? '<p class="auth-intro">' + esc(intro) + '</p>' : '') +
        '<div class="auth-tabs" role="tablist">' +
            '<button role="tab" class="active" data-auth="login" aria-selected="true">Iniciar sesión</button>' +
            '<button role="tab" data-auth="register" aria-selected="false">Crear cuenta</button>' +
        '</div>' +
        '<form class="auth-form" id="loginForm" novalidate>' +
            '<label class="field"><span>Correo</span><input class="input" type="email" name="email" autocomplete="email" required></label>' +
            '<label class="field"><span>Contraseña</span><input class="input" type="password" name="password" autocomplete="current-password" required></label>' +
            '<button class="btn btn-dark btn-block btn-lg">Iniciar sesión</button>' +
        '</form>' +
        '<form class="auth-form" id="registerForm" hidden novalidate>' +
            '<div class="field-row">' +
                '<label class="field"><span>Nombre</span><input class="input" name="first_name" autocomplete="given-name" required maxlength="50"></label>' +
                '<label class="field"><span>Apellido paterno</span><input class="input" name="last_name_paternal" autocomplete="family-name" required maxlength="50"></label>' +
            '</div>' +
            '<label class="field"><span>Apellido materno (opcional)</span><input class="input" name="last_name_maternal" maxlength="50"></label>' +
            '<label class="field"><span>Correo</span><input class="input" type="email" name="email" autocomplete="email" required maxlength="100"></label>' +
            '<label class="field"><span>Teléfono (10 dígitos)</span><input class="input" type="tel" name="phone" inputmode="numeric" pattern="\\d{10}" autocomplete="tel-national" required></label>' +
            '<div class="field-row">' +
                '<label class="field"><span>Fecha de nacimiento</span><input class="input" type="date" name="birth_date" required></label>' +
                '<label class="field"><span>Sexo</span><select class="input" name="sex" required><option value="">Elige</option><option value="F">Mujer</option><option value="M">Hombre</option><option value="X">Prefiero no decir</option></select></label>' +
            '</div>' +
            '<label class="field"><span>Contraseña (mínimo 8, con letras y números)</span><input class="input" type="password" name="password" autocomplete="new-password" minlength="8" required></label>' +
            '<label class="check"><input type="checkbox" name="accepts_marketing" value="1"> Quiero recibir ofertas y novedades</label>' +
            '<button class="btn btn-dark btn-block btn-lg">Crear cuenta</button>' +
            '<p class="muted small">Debes tener al menos 15 años. Tu contraseña se guarda cifrada.</p>' +
        '</form>' +
    '</div>';
}

export function bindAuth(root, onSuccess) {
    root.addEventListener('click', function (e) {
        const tab = e.target.closest('[data-auth]');
        if (!tab) return;
        const isLogin = tab.dataset.auth === 'login';
        $('#loginForm', root).hidden = !isLogin;
        $('#registerForm', root).hidden = isLogin;
        root.querySelectorAll('[data-auth]').forEach(function (b) {
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

    submit($('#loginForm', root), function (d) { return login(d.email, d.password); });
    submit($('#registerForm', root), function (d) {
        d.accepts_marketing = d.accepts_marketing === '1';
        return register(d);
    });
}

function orderCard(o) {
    const st = ORDER_STATUS[o.status] || { label: o.status, css: '' };
    const delivery = o.delivery_type === 'shipping'
        ? 'Envío a ' + [o.street, o.ext_number].filter(Boolean).join(' ') + ', ' + (o.city || '')
        : 'Recoger en ' + o.store_name;
    return '<article class="order-card">' +
        '<header><div><strong>Pedido #' + o.id_order + '</strong><span class="muted"> · ' + esc(formatDate(o.order_date)) + '</span></div>' +
            badge(st.label, st.css) + '</header>' +
        '<div class="order-items">' + o.items.map(function (i) {
            return '<div class="order-item">' + productArt({ name: i.name, category_code: i.category_code }, i.color_code, 'mini') +
                '<div><a href="#/producto/' + i.id_product + '">' + esc(i.name) + '</a><p class="muted small">' +
                esc(i.color + ' · ' + (i.size === 'UNI' ? 'Unitalla' : 'Talla ' + i.size) + ' · ' + i.quantity + ' pza') + '</p></div>' +
                '<span>' + money.format(Number(i.unit_price) * Number(i.quantity)) + '</span></div>';
        }).join('') + '</div>' +
        '<footer><span class="muted small">' + esc(delivery) + ' · Pago: ' + esc(PAYMENT_METHODS[o.payment_method] || '—') +
            (o.payment_status === 'pending' ? ' (pendiente)' : '') + '</span><strong>' + money.format(Number(o.total)) + '</strong></footer>' +
    '</article>';
}

export async function renderAccount(page, params) {
    setTitle('Mi cuenta');
    const back = params.volver;

    if (!state.customer) {
        page.innerHTML = '<div class="narrow"><h1>Mi cuenta</h1>' + authPanel('Inicia sesión para ver tus pedidos y comprar más rápido.') + '</div>';
        bindAuth($('.narrow', page), function () {
            if (back) {
                window.location.hash = back;
            } else {
                renderAccount(page, {});
            }
        });
        return;
    }

    const c = state.customer;
    page.innerHTML = '<div class="account">' +
        '<header class="account-head"><div><p class="eyebrow-dark">Mi cuenta</p><h1>Hola, ' + esc(c.first_name) + '</h1>' +
            '<p class="muted">' + esc(c.email) + ' · ' + esc(c.phone) + '</p></div>' +
            '<div class="loyalty"><span>Nivel</span><strong>' + esc(c.loyalty_level) + '</strong></div></header>' +
        '<div class="section-head"><h2>Mis pedidos</h2><button class="btn btn-ghost btn-sm" id="logoutBtn">Cerrar sesión</button></div>' +
        '<div id="ordersSlot">' + loading('Cargando tus pedidos…') + '</div>' +
    '</div>';

    $('#logoutBtn', page).addEventListener('click', async function () {
        await logout();
        toast('Cerraste sesión');
        renderAccount(page, {});
    });

    try {
        const orders = await api('/me/orders');
        $('#ordersSlot', page).innerHTML = orders.length
            ? orders.map(orderCard).join('')
            : '<p class="muted">Aún no tienes pedidos. <a href="#/tienda">Explora la tienda</a>.</p>';
    } catch (err) {
        $('#ordersSlot', page).innerHTML = '<p class="bad-text">' + esc(err.message) + '</p>';
    }
}
