/* Cuenta del cliente: inicio de sesión, registro y mis pedidos. */
import { api } from '../api.js';
import { $, $$, clone, field, fill, show, slot, money, badge, message, toast, emptyState, formatDate, loading, setTitle, ORDER_STATUS, PAYMENT_METHODS } from '../ui.js';
import { productArt } from '../illustrations.js';
import { announcementsSection, benefitItems } from '../components.js';
import { giftSummary } from '../gift.js';
import { account, isPriority, loadAnnouncements, login, logout, register, state, tierInfo } from '../store.js';

export function authPanel(title, intro, onSuccess) {
    const panel = fill(clone('tpl-auth'), { title: title, intro: intro });
    const loginForm = $('[data-form="login"]', panel);
    const registerForm = $('[data-form="register"]', panel);
    const forgotForm = $('[data-form="forgot"]', panel);

    const showForm = function (name) {
        $$('.auth-form', panel).forEach(function (f) { f.hidden = f.dataset.form !== name; });
        $$('[data-auth]', panel).forEach(function (b) {
            const active = b.dataset.auth === name;
            b.classList.toggle('active', active);
            b.setAttribute('aria-selected', String(active));
        });
        const first = $('[data-form="' + name + '"] input', panel);
        if (first && name === 'forgot') first.focus();
    };

    panel.addEventListener('click', function (e) {
        const tab = e.target.closest('[data-auth], [data-show]');
        if (tab) showForm(tab.dataset.auth || tab.dataset.show);
    });

    forgotForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!forgotForm.checkValidity()) {
            forgotForm.reportValidity();
            return;
        }
        const button = forgotForm.querySelector('button');
        button.disabled = true;
        try {
            await api('/auth/forgot', {
                method: 'POST',
                body: { email: forgotForm.email.value, return_url: window.location.origin + window.location.pathname }
            });
            const sent = show(field(forgotForm, 'forgotSent'), true);
            sent.textContent = 'Si el correo está registrado, te llegará un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada.';
        } catch (err) {
            toast(err.message, { error: true });
        }
        button.disabled = false;
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
        d.accepts_terms = d.accepts_terms === '1';
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
    show(field(line, 'outletNote'), Number(i.outlet) === 1);
    slot(line, 'art').replaceWith(productArt({ name: i.name, category_code: i.category_code }, i.color_code, 'mini'));
    return line;
}

/* Pasos del pedido: los que ya ocurrieron con su fecha y los que faltan. */
function timelineSteps(o) {
    const reached = {};
    (o.history || []).forEach(function (h) { reached[h.status] = h.changed_at; });
    if (!reached.pending) reached.pending = o.order_date;

    const flow = ['pending', 'paid'];
    if (o.delivery_type === 'shipping') flow.push('shipped');
    flow.push('delivered');
    if (o.status === 'cancelled') return ['pending', 'cancelled'].map(function (st) { return { status: st, date: reached[st], done: true }; });
    if (o.status === 'returned') flow.push('returned');

    const current = flow.indexOf(o.status);
    return flow.map(function (st, i) {
        return { status: st, date: reached[st], done: i <= current, current: i === current };
    });
}

const STEP_LABELS = {
    pending: 'Pedido recibido',
    paid: 'Pago confirmado',
    shipped: 'En camino',
    delivered: 'Entregado',
    cancelled: 'Cancelado',
    returned: 'Devuelto'
};

function orderTimeline(o) {
    const list = clone('tpl-timeline');
    list.append(...timelineSteps(o).map(function (step) {
        const item = fill(clone('tpl-timeline-step'), {
            label: o.delivery_type === 'pickup' && step.status === 'delivered' ? 'Recogido en sucursal' : STEP_LABELS[step.status],
            date: step.date ? formatDate(step.date, true) : 'Pendiente'
        });
        item.classList.toggle('done', step.done);
        item.classList.toggle('current', Boolean(step.current));
        item.classList.toggle('bad', step.status === 'cancelled' || step.status === 'returned');
        if (step.current) item.setAttribute('aria-current', 'step');
        return item;
    }));
    return list;
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
    if (o.priority_shipping) {
        slot(card, 'items').before(badge('Envío prioritario', 'gold'));
    }
    slot(card, 'items').append(...o.items.map(orderLine));
    slot(card, 'timeline').replaceWith(orderTimeline(o));
    if (o.gift_occasion) {
        const gift = show(field(card, 'giftLine'), true);
        gift.textContent = giftSummary(o.gift_occasion, o.gift_color, o.gift_recipient)
            + (o.gift_message ? ' · “' + o.gift_message + '”' : '');
    }
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
    const acc = account();
    const view = fill(clone('tpl-account'), {
        name: c.first_name,
        contact: c.email + ' · ' + c.phone,
        since: formatDate(c.customer_since),
        tier: acc.name
    });
    view.classList.add('account-' + acc.tier);
    slot(view, 'benefits').append(...benefitItems(acc));
    show(field(view, 'adminLink'), acc.can_manage_store);
    show(field(view, 'marketLink'), isPriority());

    const priority = tierInfo('priority');
    if (acc.tier === 'standard' && priority) {
        show(field(view, 'upgrade'), true);
        slot(view, 'priorityBenefits').append(...benefitItems(priority));
    }

    const orders = slot(view, 'orders');
    orders.append(loading('Cargando tus pedidos…'));
    page.replaceChildren(view);

    loadAnnouncements().then(function (list) {
        slot(view, 'announcements').replaceWith(announcementsSection(list, acc.tier === 'standard' ? 'Comunicados' : 'Comunicados exclusivos'));
    });

    $('[data-action="logout"]', view).addEventListener('click', async function () {
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

/* Página del enlace que llega por correo para restablecer la contraseña. */
export function renderReset(page, params) {
    setTitle('Nueva contraseña');
    if (!/^[a-f0-9]{64}$/i.test(params.token || '')) {
        page.replaceChildren(emptyState('Enlace no válido', 'Abre el enlace completo del correo o solicita uno nuevo desde Iniciar sesión.', { href: '#/cuenta', label: 'Ir a iniciar sesión' }));
        return;
    }
    const view = clone('tpl-reset');
    const form = $('form', view);
    page.replaceChildren(view);

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (form.password.value !== form.confirm.value) {
            form.confirm.setCustomValidity('Las contraseñas no coinciden.');
        } else {
            form.confirm.setCustomValidity('');
        }
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            await api('/auth/reset', { method: 'POST', body: { token: params.token, password: form.password.value } });
            await logout();
            toast('Contraseña actualizada. Inicia sesión con tu nueva contraseña.');
            window.location.hash = '#/cuenta';
        } catch (err) {
            toast(err.message, { error: true });
            button.disabled = false;
        }
    });
}
