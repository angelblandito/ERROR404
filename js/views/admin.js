/* Administración de la tienda: pedidos, clientes, precios, inventario, soporte y comunicados. */
import { api } from '../api.js';
import { $, $$, clone, field, fill, show, slot, badge, money, message, formatDate, loading, setTitle, toast, ORDER_STATUS } from '../ui.js';
import { productArt } from '../illustrations.js';
import { tierBadge } from '../components.js';
import { colorsOf, loadCatalog, state, stockAt } from '../store.js';
import { ticketStatus } from './support.js';

const SECTIONS = {
    resumen: ['Resumen', 'Ventas, pedidos por surtir y soporte de un vistazo.', 'tpl-admin-summary'],
    pedidos: ['Pedidos', 'Cambia el estado de los pedidos del nodo central.', 'tpl-admin-orders'],
    clientes: ['Clientes', 'Tipos de cuenta y regla de clientes Prioritarios.', 'tpl-admin-customers'],
    productos: ['Productos', 'Precios de lista y existencias por sucursal.', 'tpl-admin-products'],
    soporte: ['Soporte', 'Cola de tickets ordenada por prioridad y plazo de respuesta.', 'tpl-admin-tickets'],
    comunicados: ['Comunicados', 'Avisos para todos los clientes o solo para los Prioritarios.', 'tpl-admin-announcements']
};

const STATUS_ACTIONS = {
    paid: 'Marcar pagado',
    shipped: 'Enviar',
    delivered: 'Entregado',
    cancelled: 'Cancelar',
    returned: 'Devolución'
};

function rowMessage(text, colspan) {
    const row = fill(clone('tpl-table-message'), { text: text });
    row.firstElementChild.colSpan = colspan;
    return row;
}

function smallButton(text, css) {
    const button = clone('tpl-small-button');
    button.textContent = text;
    if (css) button.classList.add(css);
    return button;
}

/* ---------- Resumen ---------- */
async function loadSummary(content) {
    const data = await api('/admin/summary');
    fill(content, {
        salesToday: money.format(data.sales_today),
        sales30: money.format(data.sales_30_days),
        orders30: data.orders_30_days + ' pedidos',
        prioritySales: data.sales_30_days ? Math.round(data.priority_sales_30_days * 100 / data.sales_30_days) + ' %' : '0 %',
        pending: data.pending_orders,
        pendingPriority: data.pending_priority + ' con envío prioritario',
        tickets: data.open_tickets,
        ticketsDetail: data.open_high + ' prioritarios · ' + data.overdue_tickets + ' vencidos',
        lowStock: data.low_stock
    });
    slot(content, 'tiers').replaceChildren(...data.tiers.map(function (t) {
        const row = fill(clone('tpl-kv'), { key: '', value: t.customers });
        field(row, 'key').replaceChildren(tierBadge(t.code));
        return row;
    }));
    slot(content, 'top').replaceChildren(...data.top_products.map(function (p) {
        return fill(clone('tpl-top-row'), { name: p.name, units: p.units, revenue: money.format(p.revenue) });
    }));
}

/* ---------- Pedidos ---------- */
function orderRow(o, reload) {
    const st = ORDER_STATUS[o.status] || { label: o.status, css: '' };
    const row = fill(clone('tpl-admin-order-row'), {
        id: o.id_order,
        date: formatDate(o.order_date, true),
        customer: o.customer,
        delivery: o.delivery_type === 'shipping' ? 'Envío' : 'Recoger en ' + o.store_name,
        total: money.format(Number(o.total))
    });
    slot(row, 'tier').replaceWith(tierBadge(o.tier));
    slot(row, 'status').append(badge(st.label, st.css));
    if (o.priority_shipping) {
        slot(row, 'priority').replaceWith(badge('Prioritario', 'gold'));
        row.classList.add('priority-row');
    }
    slot(row, 'actions').append(...o.next_status.map(function (next) {
        const button = smallButton(STATUS_ACTIONS[next] || next, next === 'cancelled' || next === 'returned' ? 'danger' : '');
        button.addEventListener('click', async function () {
            button.disabled = true;
            try {
                await api('/admin/orders/' + o.id_order + '/status', { method: 'PUT', body: { status: next } });
                toast('Pedido #' + o.id_order + ': ' + (ORDER_STATUS[next] || { label: next }).label);
                reload();
            } catch (err) {
                toast(err.message, { error: true });
                button.disabled = false;
            }
        });
        return button;
    }));
    return row;
}

async function loadOrders(content) {
    const status = $('select[name="status"]', content).value;
    const rows = slot(content, 'rows');
    const reload = function () { loadOrders(content); };
    const orders = await api('/admin/orders' + (status ? '?status=' + status : ''));
    rows.replaceChildren(...(orders.length
        ? orders.map(function (o) { return orderRow(o, reload); })
        : [rowMessage('No hay pedidos con ese estado.', 7)]));
}

/* ---------- Clientes ---------- */
function customerRow(c, reload) {
    const row = fill(clone('tpl-admin-customer-row'), {
        name: c.first_name + ' ' + c.last_name_paternal,
        email: c.email,
        since: formatDate(c.customer_since) + ' (' + Math.floor(c.days_as_customer / 365) + ' años)',
        orders: c.orders_12_months,
        units: c.units_12_months,
        spent: money.format(c.spent_12_months)
    });
    slot(row, 'tier').append(tierBadge(c.tier));
    if (c.tier === 'admin') return row;

    const next = c.tier === 'priority' ? 'standard' : 'priority';
    const button = smallButton(next === 'priority' ? 'Hacer Prioritario' : 'Volver a Estándar', next === 'priority' ? 'gold' : '');
    button.addEventListener('click', async function () {
        button.disabled = true;
        try {
            await api('/admin/customers/' + c.id_customer + '/tier', { method: 'PUT', body: { tier: next } });
            toast(c.first_name + ' ahora es ' + (next === 'priority' ? 'Prioritario' : 'Estándar'));
            reload();
        } catch (err) {
            toast(err.message, { error: true });
            button.disabled = false;
        }
    });
    slot(row, 'action').append(button);
    return row;
}

async function loadCustomers(content) {
    const reload = function () { loadCustomers(content); };
    const customers = await api('/admin/customers');
    slot(content, 'rows').replaceChildren(...customers.map(function (c) { return customerRow(c, reload); }));
}

async function evaluatePriority(button, content) {
    button.disabled = true;
    try {
        const result = await api('/admin/customers/evaluate', { method: 'POST' });
        const names = result.promoted.map(function (c) { return c.first_name; });
        toast(names.length ? 'Ahora son Prioritarios: ' + names.join(', ') : 'Ningún cliente nuevo cumple la regla.');
        loadCustomers(content);
    } catch (err) {
        toast(err.message, { error: true });
    }
    button.disabled = false;
}

/* ---------- Productos e inventario ---------- */
function stockCell(variant, store) {
    const cell = clone('tpl-cell');
    const input = clone('tpl-stock-input');
    input.value = stockAt(variant, store.id_store);
    input.dataset.store = store.id_store;
    input.dataset.variant = variant.id_variant;
    input.setAttribute('aria-label', 'Existencias en ' + store.store_name + ' de ' + variant.sku);
    cell.className = 'num';
    cell.append(input);
    return cell;
}

function textCell(text) {
    const cell = clone('tpl-cell');
    cell.textContent = text;
    return cell;
}

function productItem(p) {
    const colors = colorsOf(p);
    const item = fill(clone('tpl-admin-product'), {
        name: p.name,
        category: p.category,
        price: money.format(p.base_price),
        stock: p.stock_total + ' piezas'
    });
    item.dataset.product = p.id_product;
    slot(item, 'art').replaceWith(productArt(p, colors[0] ? colors[0].color_code : 'black', 'mini'));
    $('input[name="base_price"]', item).value = p.base_price.toFixed(2);

    slot(item, 'head').append(...state.stores.map(function (s) {
        const th = clone('tpl-head-cell');
        th.className = 'num';
        th.textContent = s.store_name;
        return th;
    }));
    slot(item, 'variants').append(...p.variants.map(function (v) {
        const row = document.createElement('tr');
        row.append(textCell(v.sku), textCell(v.size), textCell(v.color), ...state.stores.map(function (s) { return stockCell(v, s); }));
        return row;
    }));
    return item;
}

function renderProducts(content) {
    slot(content, 'list').replaceChildren(...state.products.map(productItem));
}

async function savePrice(form) {
    const item = form.closest('.admin-product');
    const button = form.querySelector('button');
    button.disabled = true;
    try {
        const data = await api('/admin/products/' + item.dataset.product, {
            method: 'PUT',
            body: { base_price: Number(form.base_price.value) }
        });
        field(item, 'price').textContent = money.format(data.base_price);
        toast('Precio actualizado');
        loadCatalog(true).catch(function () {});
    } catch (err) {
        toast(err.message, { error: true });
    }
    button.disabled = false;
}

async function saveStock(input) {
    try {
        await api('/admin/inventory', {
            method: 'PUT',
            body: { id_store: Number(input.dataset.store), id_variant: Number(input.dataset.variant), stock: Number(input.value) }
        });
        input.classList.add('saved');
        setTimeout(function () { input.classList.remove('saved'); }, 1200);
        loadCatalog(true).catch(function () {});
    } catch (err) {
        toast(err.message, { error: true });
    }
}

/* ---------- Soporte ---------- */
function ticketItem(t, reload) {
    const card = fill(clone('tpl-admin-ticket'), {
        subject: t.subject,
        meta: '#' + t.id_ticket + ' · ' + t.customer + ' (' + t.email + ')' + (t.id_order ? ' · Pedido #' + t.id_order : '')
            + ' · ' + formatDate(t.created_at, true) + (t.status === 'open' ? ' · responder antes de ' + formatDate(t.respond_by, true) : ''),
        message: t.message,
        response: t.response || ''
    });
    const badges = [ticketStatus(t), tierBadge(t.tier)];
    if (t.priority === 'high') badges.push(badge('Atención inmediata', 'gold'));
    if (t.overdue) badges.push(badge('Vencido', 'bad'));
    slot(card, 'badges').append(...badges);
    show(field(card, 'responseBox'), Boolean(t.response));
    card.classList.toggle('high', t.priority === 'high');

    const form = $('.ticket-answer', card);
    form.hidden = t.status === 'closed';
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const status = e.submitter ? e.submitter.value : 'answered';
        try {
            await api('/admin/tickets/' + t.id_ticket, { method: 'PUT', body: { response: form.response.value, status: status } });
            toast('Respuesta enviada a ' + t.customer);
            reload();
        } catch (err) {
            toast(err.message, { error: true });
        }
    });
    return card;
}

async function loadTickets(content) {
    const status = $('select[name="status"]', content).value;
    const reload = function () { loadTickets(content); };
    const tickets = await api('/admin/tickets' + (status ? '?status=' + status : ''));
    slot(content, 'list').replaceChildren(...(tickets.length
        ? tickets.map(function (t) { return ticketItem(t, reload); })
        : [message('No hay tickets en esta lista.')]));
}

/* ---------- Comunicados ---------- */
function announcementItem(a, reload) {
    const card = fill(clone('tpl-admin-announcement'), {
        title: a.title,
        body: a.body,
        dates: 'Del ' + formatDate(a.starts_on) + (a.ends_on ? ' al ' + formatDate(a.ends_on) : ' sin fecha de fin')
    });
    slot(card, 'badges').append(
        a.audience === 'priority' ? badge('Solo Prioritarios', 'gold') : badge('Todos', 'info'),
        a.active ? badge('Vigente', 'ok') : badge('No vigente')
    );
    $('[data-action="delete"]', card).addEventListener('click', async function () {
        try {
            await api('/admin/announcements/' + a.id_announcement, { method: 'DELETE' });
            toast('Comunicado eliminado');
            reload();
        } catch (err) {
            toast(err.message, { error: true });
        }
    });
    return card;
}

async function loadAnnouncementList(content) {
    const reload = function () { loadAnnouncementList(content); };
    const list = await api('/admin/announcements');
    slot(content, 'list').replaceChildren(...(list.length
        ? list.map(function (a) { return announcementItem(a, reload); })
        : [message('Todavía no hay comunicados.')]));
}

function bindAnnouncementForm(content) {
    const form = $('.announcement-form', content);
    form.starts_on.value = new Date().toISOString().slice(0, 10);
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            await api('/admin/announcements', { method: 'POST', body: Object.fromEntries(new FormData(form)) });
            toast('Comunicado publicado');
            form.reset();
            form.starts_on.value = new Date().toISOString().slice(0, 10);
            loadAnnouncementList(content);
        } catch (err) {
            toast(err.message, { error: true });
        }
        button.disabled = false;
    });
}

/* ---------- Vista ---------- */
async function loadSection(section, content) {
    if (section === 'resumen') return loadSummary(content);
    if (section === 'pedidos') return loadOrders(content);
    if (section === 'clientes') return loadCustomers(content);
    if (section === 'productos') return renderProducts(content);
    if (section === 'soporte') return loadTickets(content);
    return loadAnnouncementList(content);
}

function bindSection(section, content) {
    if (section === 'pedidos' || section === 'soporte') {
        $('select[name="status"]', content).addEventListener('change', function () {
            loadSection(section, content).catch(function (err) { toast(err.message, { error: true }); });
        });
    } else if (section === 'clientes') {
        const button = $('[data-action="evaluate"]', content);
        button.addEventListener('click', function () { evaluatePriority(button, content); });
    } else if (section === 'productos') {
        content.addEventListener('submit', function (e) {
            if (!e.target.matches('.price-form')) return;
            e.preventDefault();
            savePrice(e.target);
        });
        content.addEventListener('change', function (e) {
            if (e.target.matches('.stock-input')) saveStock(e.target);
        });
    } else if (section === 'comunicados') {
        bindAnnouncementForm(content);
    }
}

export async function renderAdmin(page, section) {
    const current = SECTIONS[section] ? section : 'resumen';
    const info = SECTIONS[current];
    setTitle('Administración · ' + info[0]);

    const view = fill(clone('tpl-admin'), { title: info[0], subtitle: info[1] });
    const content = clone(info[2]);
    slot(view, 'content').replaceWith(content);
    $$('.panel-tabs a', view).forEach(function (a) {
        const active = a.dataset.section === current;
        a.classList.toggle('active', active);
        if (active) a.setAttribute('aria-current', 'page');
    });
    page.replaceChildren(view);

    bindSection(current, content);
    const spinner = loading();
    content.prepend(spinner);
    try {
        await loadSection(current, content);
    } catch (err) {
        content.prepend(message(err.message, 'bad-text'));
    }
    spinner.remove();
}
