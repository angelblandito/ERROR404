/* Administración de la tienda: pedidos, clientes, precios, inventario, soporte y comunicados. */
import { api } from '../api.js';
import { $, $$, clone, field, fill, show, slot, badge, money, message, formatDate, loading, setTitle, toast, todayISO, ORDER_STATUS } from '../ui.js';
import { productArt } from '../illustrations.js';
import { tierBadge } from '../components.js';
import { colorsOf, loadCatalog, state, stockAt } from '../store.js';
import { ticketStatus } from './support.js';
import { barChart, chartEmpty, columnChart, compactMoney, groupedBars } from '../charts.js';
import { ean13Svg } from '../barcode.js';
import { giftSummary } from '../gift.js';

const SECTIONS = {
    resumen: ['Resumen', 'Ventas, pedidos por surtir y soporte de un vistazo.', 'tpl-admin-summary'],
    visitas: ['Visitas', 'Conectados en este momento, visitas por periodo, lo más buscado y descargas de la app.', 'tpl-admin-visits'],
    pedidos: ['Pedidos', 'Cambia el estado de los pedidos del nodo central.', 'tpl-admin-orders'],
    clientes: ['Clientes', 'Tipos de cuenta y regla de clientes Prioritarios.', 'tpl-admin-customers'],
    productos: ['Productos', 'Precios de lista y existencias por sucursal.', 'tpl-admin-products'],
    soporte: ['Soporte', 'Cola de tickets ordenada por prioridad y plazo de respuesta.', 'tpl-admin-tickets'],
    comunicados: ['Comunicados', 'Avisos para todos los clientes o solo para los Prioritarios.', 'tpl-admin-announcements'],
    mercado: ['Mercado', 'Precios de la competencia para el comparador de los clientes Prioritarios.', 'tpl-admin-market'],
    correos: ['Correos', 'Bandeja simulada con los correos que la tienda envía a sus clientes.', 'tpl-admin-emails'],
    etiquetas: ['Etiquetas', 'Códigos de barras para el escáner de la app.', 'tpl-admin-labels']
};

const EMAIL_KINDS = {
    welcome: ['Bienvenida', 'info'],
    order_created: ['Pedido', 'ok'],
    order_status: ['Estado del pedido', 'info'],
    password_reset: ['Contraseña', 'warn']
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
    drawSalesCharts(content, data);
}

function dayLabel(date, long) {
    const d = new Date(Number(date.slice(0, 4)), Number(date.slice(5, 7)) - 1, Number(date.slice(8, 10)));
    return d.toLocaleDateString('es-MX', long ? { weekday: 'long', day: 'numeric', month: 'long' } : { day: 'numeric', month: 'short' });
}

function drawSalesCharts(content, data) {
    const days = data.sales_by_day || [];
    const format = function (v) { return money.format(v); };

    slot(content, 'daily').replaceChildren(days.some(function (d) { return d.total > 0; })
        ? columnChart(days.map(function (d) {
            return {
                label: dayLabel(d.date, true),
                short: dayLabel(d.date),
                value: d.total,
                tip: [{ text: 'Ventas', value: format(d.total) }, { text: 'Pedidos', value: String(d.orders) }]
            };
        }), { format: format, axisFormat: compactMoney, labelEvery: 5, label: 'Ventas por día de los últimos 30 días' })
        : chartEmpty('Sin ventas en los últimos 30 días.'));
    slot(content, 'dailyRows').replaceChildren(...days.slice().reverse().map(function (d) {
        return fill(clone('tpl-top-row'), { name: dayLabel(d.date, true), units: d.orders, revenue: format(d.total) });
    }));

    const stores = data.sales_by_store || [];
    slot(content, 'byStore').replaceChildren(stores.length
        ? barChart(stores.map(function (s) {
            return { label: s.store, sub: s.orders + ' pedidos', value: s.total, tip: [{ text: 'Pedidos', value: String(s.orders) }] };
        }), { format: format, valueName: 'Ventas', label: 'Ventas por sucursal' })
        : chartEmpty('Sin ventas en los últimos 30 días.'));

    const categories = data.sales_by_category || [];
    slot(content, 'byCategory').replaceChildren(categories.length
        ? barChart(categories.map(function (c) {
            return { label: c.name, sub: c.units + ' piezas', value: c.revenue, tip: [{ text: 'Piezas', value: String(c.units) }] };
        }), { format: format, valueName: 'Ventas', label: 'Ventas por categoría' })
        : chartEmpty('Sin ventas en los últimos 30 días.'));
}

/* ---------- Visitas ---------- */
const LIVE_SECONDS = 15;
const count = new Intl.NumberFormat('es-MX');
const compactCount = new Intl.NumberFormat('es-MX', { notation: 'compact', maximumFractionDigits: 1 });
const CHANNEL_SERIES = [{ name: 'Página web', css: 'series-1' }, { name: 'Aplicación móvil', css: 'series-2' }];
const PERIOD_NAMES = { day: 'Hoy', week: 'Esta semana', month: 'Este mes', year: 'Este año' };
const DEVICE_NAMES = { movil: 'Teléfono', tableta: 'Tableta', escritorio: 'Computadora' };
const PLATFORM_NAMES = { android: 'Android', ios: 'iPhone y iPad', windows: 'Windows', mac: 'Mac', linux: 'Linux', otro: 'Otro' };
const PAGE_NAMES = {
    '/': 'Inicio', '/tienda': 'Tienda', '/producto': 'Ficha de producto', '/outlet': 'Outlet', '/ofertas': 'Ofertas',
    '/carrito': 'Carrito', '/pago': 'Pago', '/pedido': 'Pedido confirmado', '/cuenta': 'Mi cuenta', '/favoritos': 'Favoritos',
    '/escanear': 'Escáner', '/mercado': 'Comparador', '/soporte': 'Soporte', '/sucursales': 'Sucursales', '/ayuda': 'Ayuda'
};

function fmt(n) {
    return count.format(n);
}

function axisCount(n) {
    return n >= 1000 ? compactCount.format(n) : String(Math.round(n));
}

function pageName(path) {
    if (PAGE_NAMES[path]) return PAGE_NAMES[path];
    if (path.indexOf('/admin') === 0) return 'Administración';
    if (path.indexOf('/panel') === 0) return 'Base distribuida';
    if (path.indexOf('/legal') === 0) return 'Avisos legales';
    return path;
}

function channelTip(web, app) {
    return [
        { text: 'Página web', value: fmt(web), swatch: 'series-1' },
        { text: 'Aplicación móvil', value: fmt(app), swatch: 'series-2' },
        { text: 'Total', value: fmt(web + app) }
    ];
}

function monthLabel(month, long) {
    const d = new Date(Number(month.slice(0, 4)), Number(month.slice(5, 7)) - 1, 1);
    return d.toLocaleDateString('es-MX', long ? { month: 'long', year: 'numeric' } : { month: 'short' });
}

function showOnline(content, online) {
    fill(content, {
        onlineTotal: fmt(online.total),
        onlineWeb: fmt(online.web),
        onlineApp: fmt(online.app),
        onlineCustomers: fmt(online.customers) + ' con sesión iniciada',
        liveNote: 'Actividad en los últimos ' + online.window_seconds + ' s · se actualiza cada ' + LIVE_SECONDS + ' s'
    });

    slot(content, 'onlineDevices').replaceChildren(online.total
        ? groupedBars(Object.keys(DEVICE_NAMES).map(function (d) {
            const v = online.devices[d] || { web: 0, app: 0 };
            return { label: DEVICE_NAMES[d], values: [v.web, v.app] };
        }), CHANNEL_SERIES, { format: fmt, label: 'Conectados por dispositivo' })
        : chartEmpty('Nadie conectado en este momento.'));

    slot(content, 'onlinePages').replaceChildren(online.pages.length
        ? barChart(online.pages.map(function (p) {
            return { label: pageName(p.path), value: p.visitors };
        }), { format: fmt, valueName: 'Visitantes', label: 'Páginas abiertas ahora' })
        : chartEmpty('Nadie conectado en este momento.'));
}

function showSearches(content, period) {
    const data = content.visitsReport.searches[period];
    const fromApp = data.searches ? Math.round(data.from_app * 100 / data.searches) : 0;
    fill(content, {
        searchNote: data.searches
            ? fmt(data.searches) + ' búsquedas · ' + fromApp + ' % desde la app · ' + fmt(data.without_results) + ' sin resultados'
            : 'Sin búsquedas en este periodo.'
    });
    slot(content, 'searches').replaceChildren(data.top.length
        ? barChart(data.top.map(function (t) {
            return {
                label: t.term,
                sub: t.found ? '' : 'sin resultados',
                value: t.times,
                tip: [{ text: 'Página web', value: fmt(t.web), swatch: 'series-1' }, { text: 'Aplicación móvil', value: fmt(t.app), swatch: 'series-2' }]
            };
        }), { format: fmt, valueName: 'Búsquedas', label: 'Términos más buscados' })
        : chartEmpty('Sin búsquedas en este periodo.'));
    show(field(content, 'missingBox'), data.missing.length > 0);
    slot(content, 'missing').replaceChildren(...data.missing.map(function (m) {
        return fill(clone('tpl-missing-chip'), { term: m.term, times: '×' + fmt(m.times) });
    }));
}

function drawVisits(content, report) {
    const periods = report.visits.periods;
    slot(content, 'periods').replaceChildren(...Object.keys(PERIOD_NAMES).map(function (p) {
        const v = periods[p];
        const card = fill(clone('tpl-period-card'), {
            name: PERIOD_NAMES[p], total: fmt(v.total) + ' visitas', web: fmt(v.web), app: fmt(v.app)
        });
        const webShare = v.total ? Math.round(v.web * 100 / v.total) : 0;
        $('.split-web', card).style.flexBasis = (v.total ? webShare : 0) + '%';
        $('.split-app', card).style.flexBasis = (v.total ? 100 - webShare : 0) + '%';
        $('.split-bar', card).setAttribute('aria-label', v.total
            ? webShare + ' % página web y ' + (100 - webShare) + ' % aplicación'
            : 'Sin visitas');
        return card;
    }));
    slot(content, 'periodRows').replaceChildren(...Object.keys(PERIOD_NAMES).map(function (p) {
        return fill(clone('tpl-period-row'), {
            period: PERIOD_NAMES[p], web: fmt(periods[p].web), app: fmt(periods[p].app), total: fmt(periods[p].total)
        });
    }));

    const daily = report.visits.daily;
    slot(content, 'daily').replaceChildren(daily.some(function (d) { return d.total > 0; })
        ? columnChart(daily.map(function (d) {
            return { label: dayLabel(d.date, true), short: dayLabel(d.date), value: d.total, tip: channelTip(d.web, d.app) };
        }), { format: fmt, axisFormat: axisCount, labelEvery: 6, label: 'Visitas por día de los últimos 30 días' })
        : chartEmpty('Sin visitas en los últimos 30 días.'));

    const monthly = report.visits.monthly;
    slot(content, 'monthly').replaceChildren(monthly.some(function (m) { return m.total > 0; })
        ? columnChart(monthly.map(function (m) {
            return { label: monthLabel(m.month, true), short: monthLabel(m.month), value: m.total, tip: channelTip(m.web, m.app) };
        }), { format: fmt, axisFormat: axisCount, labelEvery: 2, css: 'series-1', label: 'Visitas por mes de los últimos 12 meses' })
        : chartEmpty('Sin visitas en los últimos 12 meses.'));

    const installs = report.installs;
    fill(content, {
        installsTotal: fmt(installs.total),
        installsDay: fmt(installs.periods.day),
        installsWeek: fmt(installs.periods.week),
        installsMonth: fmt(installs.periods.month),
        installsYear: fmt(installs.periods.year)
    });
    slot(content, 'platforms').replaceChildren(installs.platforms.length
        ? barChart(installs.platforms.map(function (p) {
            return { label: PLATFORM_NAMES[p.platform] || p.platform, value: p.installs };
        }), { format: fmt, valueName: 'Descargas', css: 'series-2', label: 'Descargas por sistema' })
        : chartEmpty('Todavía no hay descargas.'));
}

async function loadVisits(content) {
    const report = await api('/admin/analytics');
    content.visitsReport = report;
    showOnline(content, report.online);
    drawVisits(content, report);
    showSearches(content, $('select[name="searchPeriod"]', content).value);

    clearInterval(content.liveTimer);
    content.liveTimer = setInterval(async function () {
        if (!content.isConnected) {
            clearInterval(content.liveTimer);
            return;
        }
        if (document.visibilityState === 'hidden') return;
        try {
            showOnline(content, await api('/admin/analytics/online'));
        } catch (e) {
            /* se reintenta en la siguiente vuelta */
        }
    }, LIVE_SECONDS * 1000);
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
    if (o.gift_occasion) {
        const gift = badge('Regalo', 'info');
        gift.title = giftSummary(o.gift_occasion, o.gift_color, o.gift_recipient);
        slot(row, 'status').append(' ', gift);
    }
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

/* ---------- Mercado ---------- */
function marketRow(o, reload) {
    const p = state.byId.get(o.id_product);
    const row = fill(clone('tpl-admin-market-row'), {
        product: p ? p.name : '#' + o.id_product,
        brand: o.competitor,
        name: o.competitor_product,
        retailer: o.retailer,
        date: formatDate(o.captured_on)
    });
    const link = field(row, 'retailer');
    if (o.source_url) link.href = o.source_url; else link.removeAttribute('href');
    const input = $('input[name="price"]', row);
    input.value = o.price.toFixed(2);
    input.setAttribute('aria-label', 'Precio de ' + o.competitor_product);

    $('[data-action="save"]', row).addEventListener('click', async function () {
        try {
            await api('/admin/market/' + o.id_market_price, {
                method: 'PUT',
                body: { price: Number(input.value), captured_on: todayISO() }
            });
            toast('Precio de ' + o.competitor_product + ' actualizado');
            reload();
        } catch (err) {
            toast(err.message, { error: true });
        }
    });
    $('[data-action="delete"]', row).addEventListener('click', async function () {
        try {
            await api('/admin/market/' + o.id_market_price, { method: 'DELETE' });
            toast('Precio quitado del comparador');
            reload();
        } catch (err) {
            toast(err.message, { error: true });
        }
    });
    return row;
}

async function loadMarket(content) {
    const reload = function () { loadMarket(content); };
    const data = await api('/admin/market');
    const form = $('.market-form', content);
    if (!form.id_product.options.length) {
        state.products.forEach(function (p) { form.id_product.add(new Option(p.name, p.id_product)); });
        data.competitors.forEach(function (c) { form.id_competitor.add(new Option(c.name, c.id_competitor)); });
        form.captured_on.value = todayISO();
    }
    slot(content, 'rows').replaceChildren(...(data.offers.length
        ? data.offers.map(function (o) { return marketRow(o, reload); })
        : [rowMessage('Todavía no hay precios registrados.', 6)]));
}

function bindMarketForm(content) {
    const form = $('.market-form', content);
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        const button = form.querySelector('button');
        button.disabled = true;
        const body = Object.fromEntries(new FormData(form));
        body.id_product = Number(body.id_product);
        body.id_competitor = Number(body.id_competitor);
        body.price = Number(body.price);
        try {
            await api('/admin/market', { method: 'POST', body: body });
            toast('Precio registrado');
            form.competitor_product.value = '';
            form.price.value = '';
            form.source_url.value = '';
            loadMarket(content);
        } catch (err) {
            toast(err.message, { error: true });
        }
        button.disabled = false;
    });
}

/* ---------- Correos ---------- */
function emailItem(m) {
    const kind = EMAIL_KINDS[m.kind] || [m.kind, ''];
    const card = fill(clone('tpl-admin-email'), {
        subject: m.subject,
        meta: 'Para ' + m.to_email + ' · ' + formatDate(m.created_at, true) + (m.id_order ? ' · Pedido #' + m.id_order : ''),
        body: m.body
    });
    slot(card, 'kind').append(badge(kind[0], kind[1]));
    if (m.link) {
        const link = show(field(card, 'link'), true);
        link.href = m.link;
        link.textContent = m.kind === 'password_reset' ? 'Abrir enlace para restablecer' : 'Abrir enlace del correo';
    }
    return card;
}

async function loadEmails(content) {
    const list = await api('/admin/emails');
    slot(content, 'list').replaceChildren(...(list.length
        ? list.map(emailItem)
        : [message('Todavía no se ha enviado ningún correo.')]));
}

/* ---------- Etiquetas ---------- */
function labelCard(p, v) {
    const card = fill(clone('tpl-label'), {
        name: p.name,
        variant: v.color + ' · ' + (v.size === 'UNI' ? 'Unitalla' : 'Talla ' + v.size) + ' · ' + v.sku,
        price: money.format(p.sale_price !== undefined ? p.sale_price : p.base_price)
    });
    slot(card, 'barcode').replaceWith(ean13Svg(v.barcode));
    return card;
}

function renderLabels(content) {
    const select = $('select[name="product"]', content);
    if (select.options.length === 1) {
        state.products.forEach(function (p) { select.add(new Option(p.name, p.id_product)); });
    }
    const id = Number(select.value);
    const cards = [];
    state.products.filter(function (p) { return !id || p.id_product === id; }).forEach(function (p) {
        p.variants.filter(function (v) { return v.barcode; }).forEach(function (v) { cards.push(labelCard(p, v)); });
    });
    slot(content, 'labels').replaceChildren(...(cards.length ? cards : [message('No hay variantes con código de barras. Vuelve a ejecutar db_jam.sql.')]));
}

/* ---------- Vista ---------- */
async function loadSection(section, content) {
    if (section === 'resumen') return loadSummary(content);
    if (section === 'visitas') return loadVisits(content);
    if (section === 'pedidos') return loadOrders(content);
    if (section === 'clientes') return loadCustomers(content);
    if (section === 'productos') return renderProducts(content);
    if (section === 'soporte') return loadTickets(content);
    if (section === 'mercado') return loadMarket(content);
    if (section === 'correos') return loadEmails(content);
    if (section === 'etiquetas') return renderLabels(content);
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
    } else if (section === 'visitas') {
        $('select[name="searchPeriod"]', content).addEventListener('change', function (e) {
            if (content.visitsReport) showSearches(content, e.target.value);
        });
    } else if (section === 'comunicados') {
        bindAnnouncementForm(content);
    } else if (section === 'mercado') {
        bindMarketForm(content);
    } else if (section === 'etiquetas') {
        $('select[name="product"]', content).addEventListener('change', function () { renderLabels(content); });
        $('[data-action="print"]', content).addEventListener('click', function () { window.print(); });
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
