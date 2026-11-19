/* Soporte técnico: el plazo de respuesta depende del tipo de cuenta. */
import { api } from '../api.js';
import { $, clone, field, fill, show, slot, badge, message, toast, formatDate, loading, setTitle } from '../ui.js';
import { account, isPriority, state } from '../store.js';
import { authPanel } from './account.js';

const TICKET_STATUS = {
    open: ['Abierto', 'warn'],
    answered: ['Respondido', 'ok'],
    closed: ['Cerrado', '']
};

export function ticketStatus(t) {
    const st = TICKET_STATUS[t.status] || [t.status, ''];
    return badge(st[0], st[1]);
}

function slaText(acc) {
    if (acc.support_minutes < 60) {
        return 'Cuenta ' + acc.name + ': atención inmediata. Respondemos en menos de ' + acc.support_minutes + ' minutos.';
    }
    return 'Respondemos en menos de ' + Math.round(acc.support_minutes / 60) + ' horas.';
}

function ticketCard(t) {
    const card = fill(clone('tpl-ticket'), {
        subject: t.subject,
        meta: 'Ticket #' + t.id_ticket + (t.id_order ? ' · Pedido #' + t.id_order : '') + ' · ' + formatDate(t.created_at, true)
            + (t.status === 'open' ? ' · respuesta antes de ' + formatDate(t.respond_by, true) : ''),
        message: t.message,
        response: t.response || ''
    });
    slot(card, 'status').replaceWith(ticketStatus(t));
    show(field(card, 'responseBox'), Boolean(t.response));
    if (t.priority === 'high') card.classList.add('high');
    return card;
}

async function loadTickets(list) {
    list.replaceChildren(loading('Cargando tus tickets…'));
    try {
        const tickets = await api('/support/tickets');
        list.replaceChildren(...(tickets.length ? tickets.map(ticketCard) : [message('Aún no tienes tickets.')]));
    } catch (err) {
        list.replaceChildren(message(err.message, 'bad-text'));
    }
}

async function loadOrderOptions(select) {
    try {
        const orders = await api('/me/orders');
        orders.forEach(function (o) {
            select.add(new Option('Pedido #' + o.id_order + ' · ' + formatDate(o.order_date), o.id_order));
        });
    } catch (e) {
        /* el ticket se puede enviar sin pedido */
    }
}

export function renderSupport(page) {
    setTitle('Soporte');

    if (!state.customer) {
        page.replaceChildren(authPanel('Soporte técnico', 'Inicia sesión para abrir un ticket y ver las respuestas.', function () {
            renderSupport(page);
        }));
        return;
    }

    const acc = account();
    const view = fill(clone('tpl-support'), { sla: slaText(acc) });
    const form = $('.support-form', view);
    const list = slot(view, 'tickets');
    field(view, 'sla').classList.toggle('priority', isPriority());
    page.replaceChildren(view);

    loadOrderOptions(form.id_order);
    loadTickets(list);

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            await api('/support/tickets', {
                method: 'POST',
                body: { subject: form.subject.value, message: form.message.value, id_order: form.id_order.value || null }
            });
            form.reset();
            toast(isPriority() ? 'Ticket prioritario enviado. Te respondemos en minutos.' : 'Ticket enviado.');
            loadTickets(list);
        } catch (err) {
            toast(err.message, { error: true });
        }
        button.disabled = false;
    });
}
