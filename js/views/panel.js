/* Panel de sucursales: inventario por nodo, pedidos por fragmento y estado
   de la base de datos distribuida. */
import { api } from '../api.js';
import { $, $$, clone, field, fill, show, slot, badge, money, message, formatDate, setTitle, toast } from '../ui.js';

const NODES = {
    tijuana: { label: 'Tijuana', idStore: 1 },
    cdmx: { label: 'CDMX', idStore: 2 },
    online: { label: 'En línea', idStore: 4 }
};

const ORDER_STATUS = {
    pending: { label: 'Pendiente', css: 'warn' },
    paid: { label: 'Pagado', css: 'ok' },
    shipped: { label: 'Enviado', css: 'info' },
    delivered: { label: 'Entregado', css: 'ok' },
    cancelled: { label: 'Cancelado', css: 'bad' },
    returned: { label: 'Devuelto', css: 'bad' }
};

const TIER_NAMES = { 1: 'Estándar', 2: 'Prioritario', 3: 'Administrador' };

const ROLE_NAMES = { central: 'central', sucursal: 'sucursal', tienda_en_linea: 'tienda en línea', nube: 'nube' };

const CHANNEL = { in_store: 'En tienda', online: 'En línea', whatsapp: 'WhatsApp' };

const REPLICATION_STATE = {
    sincronizado: ['Sincronizado', 'ok'],
    diferente: ['Diferente', 'warn'],
    sin_replicar: ['Sin replicar', 'bad']
};

const SECTIONS = {
    inventario: ['Inventario por sucursal', 'Cada pestaña lee el fragmento horizontal del nodo correspondiente.', 'tpl-panel-inventory'],
    pedidos: ['Pedidos por nodo', 'Reconstruidos con la unión de los fragmentos de cada nodo.', 'tpl-panel-orders'],
    distribuida: ['Base de datos distribuida', 'Nodos, fragmentación, replicación y espejo en la nube.', 'tpl-panel-distributed']
};

const panelState = { inventoryNode: 'all' };

function nodeLabel(node) {
    return NODES[node] ? NODES[node].label : node;
}

function tableMessage(text, colspan) {
    const row = fill(clone('tpl-table-message'), { text: text });
    row.firstElementChild.colSpan = colspan;
    return row;
}

function cell(content, css) {
    const td = clone('tpl-cell');
    if (css) td.className = css;
    if (content instanceof Node) {
        td.append(content);
    } else {
        td.textContent = content === null || content === undefined ? '—' : content;
    }
    return td;
}

function kv(key, value) {
    return fill(clone('tpl-kv'), { key: key, value: value === null || value === undefined ? '—' : value });
}

/* De dónde salieron los datos de un fragmento: fragmento | espejo:cloud | respaldo:<nodo> | sin_datos */
function sourceText(source) {
    const text = String(source || '');
    if (text === 'fragmento') return 'su propio fragmento';
    if (text.indexOf('espejo') === 0) return 'el espejo en Azure SQL';
    if (text.indexOf('respaldo') === 0) return text === 'respaldo:central' ? 'el nodo central JAM_db' : 'el nodo ' + text.split(':')[1];
    return 'ninguna copia disponible';
}

function shortSource(source) {
    const text = String(source || '');
    if (text === 'fragmento') return 'Su fragmento';
    if (text.indexOf('espejo') === 0) return 'Espejo Azure';
    if (text.indexOf('respaldo') === 0) return text === 'respaldo:central' ? 'Nodo central' : 'Nodo ' + text.split(':')[1];
    return 'Sin copia';
}

function sourceBadges(sources) {
    return Object.keys(sources || {}).map(function (node) {
        const s = sources[node];
        const label = nodeLabel(node) + ': ';
        if (s.source === 'fragmento') {
            return badge(label + 'fragmento local (' + s.rows + ')', 'ok');
        }
        const el = s.source === 'sin_datos'
            ? badge(label + 'sin datos', 'bad')
            : badge(label + 'nodo sin conexión, leído desde ' + sourceText(s.source) + ' (' + s.rows + ')', 'warn');
        el.title = s.error || '';
        return el;
    });
}

/* ---------- Inventario ---------- */
function inventoryRow(r) {
    const row = fill(clone('tpl-inventory-row'), {
        store: r.store_name || ('Tienda ' + r.id_store),
        sku: r.sku || r.id_variant,
        product: r.product_name || '',
        size: r.size || '',
        color: r.color || '',
        stock: r.stock,
        min: r.min_stock
    });
    const status = r.stock <= 0 ? badge('Agotado', 'bad') : (r.low_stock ? badge('Stock bajo', 'warn') : badge('Disponible', 'ok'));
    slot(row, 'node').append(badge(nodeLabel(r.node), 'info'));
    slot(row, 'status').append(status);
    row.classList.toggle('low', Boolean(r.low_stock));
    return row;
}

async function loadInventory(content, node) {
    panelState.inventoryNode = node;
    const rows = slot(content, 'rows');
    const sources = slot(content, 'sources');

    $$('.tab', content).forEach(function (tab) {
        const active = tab.dataset.node === node;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', String(active));
    });

    rows.replaceChildren(tableMessage('Cargando…', 9));
    sources.replaceChildren();

    let data;
    try {
        data = await api('/distributed/inventory' + (node !== 'all' ? '?node=' + encodeURIComponent(node) : ''));
    } catch (e) {
        rows.replaceChildren(tableMessage('Error: ' + e.message, 9));
        return;
    }

    sources.replaceChildren(...sourceBadges(data.sources));
    rows.replaceChildren(...(data.rows.length ? data.rows.map(inventoryRow) : [tableMessage('Sin registros en este fragmento.', 9)]));
}

/* ---------- Pedidos ---------- */
function orderItem(text, amount, css) {
    const item = fill(clone('tpl-panel-order-item'), { text: text, amount: amount });
    if (css) item.classList.add(css);
    return item;
}

function panelOrder(o) {
    const st = ORDER_STATUS[o.status] || { label: o.status, css: '' };
    const order = fill(clone('tpl-panel-order'), {
        id: o.id_order,
        store: o.store_name || '',
        date: formatDate(o.order_date),
        total: money.format(Number(o.total))
    });
    slot(order, 'node').replaceWith(badge(nodeLabel(o.node), 'info'));
    slot(order, 'status').replaceWith(badge(st.label, st.css));
    slot(order, 'channel').replaceWith(badge(CHANNEL[o.channel] || o.channel));

    const items = (o.items || []).map(function (i) {
        return orderItem(
            i.quantity + ' × ' + (i.product_name || i.sku || i.id_variant) + (i.size ? ' · ' + i.size : '') + (i.color ? ' · ' + i.color : ''),
            money.format(Number(i.unit_price) * i.quantity)
        );
    });
    if (!items.length) items.push(message('Sin artículos en el fragmento.'));
    if (Number(o.discount) > 0) items.push(orderItem('Descuento', '-' + money.format(Number(o.discount)), 'muted'));
    if (o.notes) items.push(message(o.notes));
    slot(order, 'items').append(...items);
    return order;
}

async function loadOrders(content) {
    const node = $('select[name="node"]', content).value;
    const status = $('select[name="status"]', content).value;
    const list = slot(content, 'list');
    const sources = slot(content, 'sources');

    list.replaceChildren(message('Cargando…', 'empty'));
    sources.replaceChildren();

    let data;
    try {
        data = await api('/distributed/orders' + (node !== 'all' ? '?node=' + encodeURIComponent(node) : ''));
    } catch (e) {
        list.replaceChildren(message('Error: ' + e.message, 'empty'));
        return;
    }

    sources.replaceChildren(...sourceBadges(data.sources));
    const rows = data.rows.filter(function (o) { return !status || o.status === status; });
    list.replaceChildren(...(rows.length ? rows.map(panelOrder) : [message('No hay pedidos con esos filtros.', 'empty')]));
}

/* ---------- Base distribuida ---------- */
function nodeCard(n) {
    const online = n.status === 'en_linea';
    const mirror = n.endpoint === 'espejo';
    const card = fill(clone('tpl-node-card'), { label: n.label, error: n.error || '' });
    card.classList.add(!online ? 'offline' : (mirror ? 'mirror' : 'online'));
    slot(card, 'status').replaceWith(online
        ? badge(mirror ? 'Conectado al espejo' : 'En línea', mirror ? 'warn' : 'ok')
        : badge('Sin conexión', 'bad'));
    show(field(card, 'error'), Boolean(n.error));

    const details = [
        kv('Rol', (ROLE_NAMES[n.role] || n.role) + (n.is_default ? ' · por defecto' : '')),
        kv('Base', n.database),
        kv('Servidor', n.server_name || n.server)
    ];
    if (n.id_store) details.push(kv('Predicado', 'id_store = ' + n.id_store));
    if (online) details.push(kv('Modo', n.updateability));
    details.push(kv('Latencia', n.latency_ms !== null ? n.latency_ms + ' ms' : null));
    if (n.served_by) details.push(kv('Lecturas', shortSource(n.served_by)));
    if (n.sync) {
        const row = kv('Sincronización', '');
        field(row, 'value').replaceChildren(n.sync.pending
            ? badge('Pendiente', 'warn')
            : badge(n.caught_up ? 'Puesto al día' : 'Al día', 'ok'));
        details.push(row);
        if (n.sync.last_success) details.push(kv('Sincronizado', formatDate(n.sync.last_success, true)));
    }
    if (n.node === 'cloud') details.push(kv('Copia', n.last_copy ? formatDate(n.last_copy, true) : 'Sin copiar'));
    (n.geo_replication || []).forEach(function (g) {
        details.push(
            kv('Espejo', g.partner_server),
            kv('Rol', g.role_desc),
            kv('Estado', g.replication_state_desc),
            kv('Retraso', g.replication_lag_sec !== null ? g.replication_lag_sec + ' s' : null)
        );
    });
    slot(card, 'details').replaceWith(...details);
    return card;
}

function fragmentTable(t) {
    let status = badge('sin datos');
    if (t.rows !== null && t.expected !== null) {
        status = badge(t.rows + ' / ' + t.expected, t.complete ? 'ok' : 'warn');
    } else if (t.rows !== null) {
        status = badge(t.rows + ' renglones', 'info');
    }
    const row = kv(t.table + ' (' + t.type + ')', '');
    field(row, 'value').replaceChildren(status);
    return row;
}

function fragmentCard(f) {
    const card = fill(clone('tpl-fragment-card'), { label: f.label, error: f.error || 'Sin conexión' });
    slot(card, 'predicate').replaceWith(badge(f.predicate, 'info'));
    show(field(card, 'error'), f.status !== 'en_linea');
    const rows = f.tables.map(fragmentTable);
    if (f.status !== 'en_linea') {
        const served = kv('Lecturas', '');
        field(served, 'value').replaceChildren(badge(shortSource(f.served_by), f.served_by ? 'warn' : 'bad'));
        rows.unshift(served);
    }
    slot(card, 'tables').replaceWith(...rows);
    return card;
}

function legendItem(fragment, css) {
    const item = fill(clone('tpl-legend-item'), { text: fragment.table + ' (nodo ' + fragment.node + ')' });
    item.querySelector('.legend-swatch').classList.add(css);
    slot(item, 'badge').replaceWith(badge(fragment.status === 'ok' ? 'OK' : 'sin conexión', fragment.status === 'ok' ? 'ok' : 'bad'));
    return item;
}

function customerRow(r) {
    return fill(clone('tpl-customer-row'), {
        id: r.id_customer,
        first: r.first_name,
        last: r.last_name_paternal,
        store: r.store_name,
        level: r.id_loyalty_level,
        tier: TIER_NAMES[r.id_account_tier] || r.id_account_tier,
        since: formatDate(r.customer_since),
        marketing: r.accepts_marketing ? 'Sí' : 'No',
        birth: formatDate(r.birth_date),
        sex: r.sex,
        phone: r.phone,
        email: r.email
    });
}

/* Aviso arriba del panel: qué nodo está caído y quién responde por él. */
function showFailover(content, nodes) {
    const box = field(content, 'failover');
    const fragments = nodes.filter(function (n) { return n.id_store; });
    const down = fragments.filter(function (n) { return n.status !== 'en_linea'; });
    const back = fragments.filter(function (n) { return n.status === 'en_linea' && n.caught_up; });

    const items = down.map(function (n) {
        return fill(clone('tpl-failover-item'), {
            node: n.label + ':',
            text: 'sin conexión. Sus consultas las responde ' + sourceText(n.served_by)
                + '. Las ventas y cambios se guardan en el nodo central y se copiarán a la sucursal cuando vuelva.'
        });
    }).concat(back.map(function (n) {
        return fill(clone('tpl-failover-item'), {
            node: n.label + ':',
            text: 'volvió a responder y se puso al día con los cambios que hubo mientras estuvo caída.'
        });
    }));

    show(box, items.length > 0);
    box.classList.toggle('ok', down.length === 0);
    fill(box, { failoverTitle: down.length ? 'El sistema sigue funcionando con ' + down.length + (down.length === 1 ? ' nodo caído' : ' nodos caídos') : 'Todos los nodos en línea' });
    slot(box, 'failoverList').replaceChildren(...items);
}

function showNodes(content, result) {
    const grid = slot(content, 'nodes');
    if (result.status !== 'fulfilled') {
        grid.replaceChildren(message('Error: ' + result.reason.message, 'empty'));
        return;
    }
    showFailover(content, result.value.nodes);
    grid.replaceChildren(...result.value.nodes.map(nodeCard));
}

function showFragments(content, result) {
    const grid = slot(content, 'fragments');
    if (result.status !== 'fulfilled') {
        grid.replaceChildren(message('Error: ' + result.reason.message, 'empty'));
        return;
    }
    grid.replaceChildren(...result.value.map(fragmentCard));
}

function showVertical(content, result) {
    const tbody = slot(content, 'customers');
    if (result.status !== 'fulfilled') {
        tbody.replaceChildren(tableMessage('Error: ' + result.reason.message, 12));
        return;
    }
    const data = result.value;
    slot(content, 'legend').replaceChildren(
        legendItem(data.fragments.customer_profile, 'col-profile'),
        legendItem(data.fragments.customer_private, 'col-private'),
        data.source !== 'fragmentos' ? badge('Leído de ' + data.source, 'warn') : badge('Reconstruido con JOIN por id_customer', 'info')
    );
    tbody.replaceChildren(...(data.rows.length ? data.rows.map(customerRow) : [tableMessage('Sin clientes.', 12)]));
}

function showReplication(content, result) {
    const head = slot(content, 'replicationHead');
    const tbody = slot(content, 'replication');
    if (result.status !== 'fulfilled') {
        tbody.replaceChildren(tableMessage('Error: ' + result.reason.message, 2));
        return;
    }

    const subs = result.value.subscribers;
    subs.forEach(function (s) {
        const th = clone('tpl-head-cell');
        th.textContent = s.node === 'cloud' ? 'Azure' : nodeLabel(s.node);
        head.append(th);
    });

    const tables = (subs.find(function (s) { return s.tables.length; }) || { tables: [] }).tables;
    if (!tables.length) {
        tbody.replaceChildren(tableMessage('Ningún suscriptor responde todavía.', 2 + subs.length));
        return;
    }

    tbody.replaceChildren(...tables.map(function (t) {
        const row = document.createElement('tr');
        row.append(cell(t.table), cell(t.publisher_rows, 'num'));
        subs.forEach(function (s) {
            if (s.status === 'no_configurado') {
                row.append(cell(badge('No configurado')));
                return;
            }
            if (s.status !== 'en_linea') {
                row.append(cell(badge('Sin conexión', 'bad')));
                return;
            }
            const found = s.tables.find(function (x) { return x.table === t.table; });
            if (!found) {
                row.append(cell(badge('—')));
                return;
            }
            const st = REPLICATION_STATE[found.state] || [found.state, ''];
            row.append(cell(badge(st[0] + (found.subscriber_rows !== null ? ' (' + found.subscriber_rows + ')' : ''), st[1])));
        });
        return row;
    }));
}

async function loadDistributed(content) {
    slot(content, 'nodes').replaceChildren(message('Consultando nodos…', 'empty'));
    slot(content, 'customers').replaceChildren(tableMessage('Cargando…', 12));
    slot(content, 'replication').replaceChildren(tableMessage('Cargando…', 2));

    const results = await Promise.allSettled([
        api('/nodes'),
        api('/distributed/fragments'),
        api('/distributed/customers'),
        api('/replication/status')
    ]);

    showNodes(content, results[0]);
    showFragments(content, results[1]);
    showVertical(content, results[2]);
    showReplication(content, results[3]);
}

function reloadDistributed(content) {
    const fresh = clone('tpl-panel-distributed');
    content.replaceWith(fresh);
    loadDistributed(fresh);
    return fresh;
}

async function syncNodes(button, content) {
    button.disabled = true;
    try {
        const data = await api('/distributed/sync', { method: 'POST' });
        const pending = Object.keys(data.pending || {}).map(nodeLabel);
        toast(pending.length
            ? 'Sincronizado. Pendiente por estar sin conexión: ' + pending.join(', ')
            : 'Fragmentos sincronizados: ' + data.synchronized_at);
        return reloadDistributed(content);
    } catch (e) {
        toast('No se pudo sincronizar: ' + e.message, { error: true });
        return content;
    } finally {
        button.disabled = false;
    }
}

async function copyToMirror(button, content) {
    button.disabled = true;
    try {
        const data = await api('/mirror/sync?full=1', { method: 'POST' });
        const rows = data.tables.reduce(function (sum, t) { return sum + t.rows; }, 0);
        toast('Espejo actualizado: ' + data.tables.length + ' tablas, ' + rows + ' renglones copiados a ' + (data.server || 'Azure'));
        return reloadDistributed(content);
    } catch (e) {
        toast('No se pudo copiar al espejo: ' + e.message, { error: true });
        return content;
    } finally {
        button.disabled = false;
    }
}

export function renderPanel(page, section) {
    const current = SECTIONS[section] ? section : 'inventario';
    const info = SECTIONS[current];
    setTitle('Panel · ' + info[0]);

    const panel = fill(clone('tpl-panel'), { title: info[0], subtitle: info[1] });
    let content = clone(info[2]);
    slot(panel, 'content').replaceWith(content);

    $$('.panel-tabs a', panel).forEach(function (a) {
        const active = a.dataset.section === current;
        a.classList.toggle('active', active);
        if (active) a.setAttribute('aria-current', 'page');
    });

    page.replaceChildren(panel);

    if (current === 'inventario') {
        content.addEventListener('click', function (e) {
            const tab = e.target.closest('.tab');
            if (tab) loadInventory(content, tab.dataset.node);
        });
        loadInventory(content, panelState.inventoryNode);
    } else if (current === 'pedidos') {
        content.addEventListener('change', function () { loadOrders(content); });
        loadOrders(content);
    } else {
        const syncButton = show($('[data-action="sync"]', panel), true);
        syncButton.addEventListener('click', async function () {
            content = await syncNodes(syncButton, content);
        });
        const mirrorButton = show($('[data-action="mirror"]', panel), true);
        mirrorButton.addEventListener('click', async function () {
            content = await copyToMirror(mirrorButton, content);
        });
        loadDistributed(content);
    }
}
