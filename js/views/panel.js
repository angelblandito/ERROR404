/* Panel de sucursales: inventario por nodo, pedidos por fragmento y estado
   de la base de datos distribuida. */
import { api } from '../api.js';
import { $, esc, badge, money, formatDate, setTitle, toast as notify } from '../ui.js';

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

const CHANNEL = { in_store: 'En tienda', online: 'En línea', whatsapp: 'WhatsApp' };

const state = { inventoryNode: 'all' };

function emptyRow(colspan, text) {
    return '<tr><td class="empty" colspan="' + colspan + '">' + esc(text) + '</td></tr>';
}

function toast(message, isError) {
    notify(message, { error: Boolean(isError) });
}

function sourceBadges(sources) {
    return Object.keys(sources || {}).map(function (node) {
        const s = sources[node];
        const label = (NODES[node] ? NODES[node].label : node) + ': ';
        if (s.source === 'fragmento') {
            return badge(label + 'fragmento local (' + s.rows + ')', 'ok');
        }
        if (String(s.source).indexOf('respaldo') === 0) {
            return '<span class="badge warn" title="' + esc(s.error || '') + '">' +
                esc(label + 'nodo sin conexión, leído de ' + s.source.split(':')[1]) + '</span>';
        }
        return '<span class="badge bad" title="' + esc(s.error || '') + '">' + esc(label + 'sin datos') + '</span>';
    }).join('');
}

async function loadInventory(node) {
    state.inventoryNode = node;

    document.querySelectorAll('#inventoryTabs .tab').forEach(function (tab) {
        const active = tab.dataset.node === node;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', String(active));
    });

    const tbody = $('#inventoryTable tbody');
    tbody.innerHTML = emptyRow(9, 'Cargando…');
    $('#inventorySources').innerHTML = '';

    let data;
    try {
        data = await api('/distributed/inventory' + (node !== 'all' ? '?node=' + encodeURIComponent(node) : ''));
    } catch (e) {
        tbody.innerHTML = emptyRow(9, 'Error: ' + e.message);
        return;
    }

    $('#inventorySources').innerHTML = sourceBadges(data.sources);

    tbody.innerHTML = data.rows.length
        ? data.rows.map(function (r) {
            const status = r.stock <= 0 ? badge('Agotado', 'bad') : (r.low_stock ? badge('Stock bajo', 'warn') : badge('Disponible', 'ok'));
            return '<tr class="' + (r.low_stock ? 'low' : '') + '">' +
                '<td>' + badge(NODES[r.node] ? NODES[r.node].label : r.node, 'info') + '</td>' +
                '<td>' + esc(r.store_name || ('Tienda ' + r.id_store)) + '</td>' +
                '<td>' + esc(r.sku || r.id_variant) + '</td>' +
                '<td>' + esc(r.product_name || '') + '</td>' +
                '<td>' + esc(r.size || '') + '</td>' +
                '<td>' + esc(r.color || '') + '</td>' +
                '<td class="num">' + esc(r.stock) + '</td>' +
                '<td class="num">' + esc(r.min_stock) + '</td>' +
                '<td>' + status + '</td>' +
            '</tr>';
        }).join('')
        : emptyRow(9, 'Sin registros en este fragmento.');
}

/* ---------- Pedidos ---------- */
async function loadOrders() {
    const node = $('#orderNodeFilter').value;
    const status = $('#orderStatusFilter').value;
    const list = $('#orderList');

    list.innerHTML = '<p class="empty">Cargando…</p>';
    $('#orderSources').innerHTML = '';

    let data;
    try {
        data = await api('/distributed/orders' + (node !== 'all' ? '?node=' + encodeURIComponent(node) : ''));
    } catch (e) {
        list.innerHTML = '<p class="empty">Error: ' + esc(e.message) + '</p>';
        return;
    }

    $('#orderSources').innerHTML = sourceBadges(data.sources);

    const rows = data.rows.filter(function (o) { return !status || o.status === status; });
    if (!rows.length) {
        list.innerHTML = '<p class="empty">No hay pedidos con esos filtros.</p>';
        return;
    }

    list.innerHTML = rows.map(function (o) {
        const st = ORDER_STATUS[o.status] || { label: o.status, css: '' };
        const items = (o.items || []).map(function (i) {
            return '<div class="order-item"><span>' + esc(i.quantity + ' × ' + (i.product_name || i.sku || i.id_variant) +
                (i.size ? ' · ' + i.size : '') + (i.color ? ' · ' + i.color : '')) + '</span>' +
                '<span>' + money.format(Number(i.unit_price) * i.quantity) + '</span></div>';
        }).join('') || '<span class="muted">Sin artículos en el fragmento.</span>';

        return '<details class="order">' +
            '<summary>' +
                '<span class="order-id">#' + esc(o.id_order) + '</span>' +
                '<span class="order-store">' + esc(o.store_name || '') + ' ' + badge(NODES[o.node] ? NODES[o.node].label : o.node, 'info') + '</span>' +
                '<span class="order-date muted">' + esc(formatDate(o.order_date)) + '</span>' +
                '<span class="order-channel">' + badge(st.label, st.css) + ' ' + badge(CHANNEL[o.channel] || o.channel) + '</span>' +
                '<span class="order-total">' + money.format(Number(o.total)) + '</span>' +
            '</summary>' +
            '<div class="order-items">' + items +
                (Number(o.discount) > 0 ? '<div class="order-item muted"><span>Descuento</span><span>-' + money.format(Number(o.discount)) + '</span></div>' : '') +
                (o.notes ? '<div class="muted">' + esc(o.notes) + '</div>' : '') +
            '</div>' +
        '</details>';
    }).join('');
}

/* ---------- Base distribuida ---------- */
async function loadDistributed() {
    $('#nodeGrid').innerHTML = '<p class="empty">Consultando nodos…</p>';
    $('#fragmentGrid').innerHTML = '';
    $('#verticalTable thead').innerHTML = '';
    $('#verticalTable tbody').innerHTML = emptyRow(1, 'Cargando…');
    $('#replicationTable thead').innerHTML = '';
    $('#replicationTable tbody').innerHTML = emptyRow(1, 'Cargando…');

    const results = await Promise.allSettled([
        api('/nodes'),
        api('/distributed/fragments'),
        api('/distributed/customers'),
        api('/replication/status')
    ]);

    renderNodes(results[0]);
    renderFragments(results[1]);
    renderVertical(results[2]);
    renderReplication(results[3]);
}

function kv(label, value) {
    return '<div class="kv"><span>' + esc(label) + '</span><span>' + esc(value === null || value === undefined ? '—' : value) + '</span></div>';
}

function renderNodes(result) {
    if (result.status !== 'fulfilled') {
        $('#nodeGrid').innerHTML = '<p class="empty">Error: ' + esc(result.reason.message) + '</p>';
        return;
    }

    $('#nodeGrid').innerHTML = result.value.nodes.map(function (n) {
        const online = n.status === 'en_linea';
        const mirror = n.endpoint === 'espejo';
        const css = !online ? 'offline' : (mirror ? 'mirror' : 'online');
        const geo = (n.geo_replication || []).map(function (g) {
            return kv('Espejo', g.partner_server) +
                kv('Rol', g.role_desc) +
                kv('Estado', g.replication_state_desc) +
                kv('Retraso', g.replication_lag_sec !== null ? g.replication_lag_sec + ' s' : null);
        }).join('');

        return '<article class="node-card ' + css + '">' +
            '<header><h3>' + esc(n.label) + '</h3>' +
                (online ? badge(mirror ? 'Conectado al espejo' : 'En línea', mirror ? 'warn' : 'ok') : badge('Sin conexión', 'bad')) +
            '</header>' +
            kv('Rol', n.role.replace(/_/g, ' ') + (n.is_default ? ' · por defecto' : '')) +
            kv('Base', n.database) +
            kv('Servidor', n.server_name || n.server) +
            (n.id_store ? kv('Predicado', 'id_store = ' + n.id_store) : '') +
            (online ? kv('Modo', n.updateability) : '') +
            kv('Latencia', n.latency_ms !== null ? n.latency_ms + ' ms' : null) +
            geo +
            (n.error ? '<div class="node-error">' + esc(n.error) + '</div>' : '') +
        '</article>';
    }).join('');
}

function renderFragments(result) {
    if (result.status !== 'fulfilled') {
        $('#fragmentGrid').innerHTML = '<p class="empty">Error: ' + esc(result.reason.message) + '</p>';
        return;
    }

    $('#fragmentGrid').innerHTML = result.value.map(function (f) {
        const rows = f.tables.map(function (t) {
            let cell = badge('sin datos', '');
            if (t.rows !== null && t.expected !== null) {
                cell = badge(t.rows + ' / ' + t.expected, t.complete ? 'ok' : 'warn');
            } else if (t.rows !== null) {
                cell = badge(t.rows + ' renglones', 'info');
            }
            return '<div class="kv"><span>' + esc(t.table) + ' <small>(' + esc(t.type) + ')</small></span><span>' + cell + '</span></div>';
        }).join('');

        return '<article class="fragment-card">' +
            '<header><h3>' + esc(f.label) + '</h3>' + badge(f.predicate, 'info') + '</header>' +
            (f.status !== 'en_linea' ? '<div class="node-error">' + esc(f.error || 'Sin conexión') + '</div>' : '') +
            rows +
        '</article>';
    }).join('');
}

function renderVertical(result) {
    const thead = $('#verticalTable thead');
    const tbody = $('#verticalTable tbody');

    if (result.status !== 'fulfilled') {
        tbody.innerHTML = emptyRow(1, 'Error: ' + result.reason.message);
        return;
    }

    const data = result.value;
    const profile = data.fragments.customer_profile;
    const priv = data.fragments.customer_private;

    $('#verticalLegend').innerHTML =
        '<span><i class="swatch col-profile"></i> ' + esc(profile.table) + ' (nodo ' + esc(profile.node) + ') ' +
            badge(profile.status === 'ok' ? 'OK' : 'sin conexión', profile.status === 'ok' ? 'ok' : 'bad') + '</span>' +
        '<span><i class="swatch col-private"></i> ' + esc(priv.table) + ' (nodo ' + esc(priv.node) + ') ' +
            badge(priv.status === 'ok' ? 'OK' : 'sin conexión', priv.status === 'ok' ? 'ok' : 'bad') + '</span>' +
        (data.source !== 'fragmentos' ? badge('Leído de ' + data.source, 'warn') : badge('Reconstruido con JOIN por id_customer', 'info'));

    const columns = [
        ['id_customer', 'ID', 'key'],
        ['first_name', 'Nombre', 'profile'],
        ['last_name_paternal', 'Apellido', 'profile'],
        ['store_name', 'Sucursal', 'profile'],
        ['id_loyalty_level', 'Nivel', 'profile'],
        ['accepts_marketing', 'Publicidad', 'profile'],
        ['birth_date', 'Nacimiento', 'private'],
        ['sex', 'Sexo', 'private'],
        ['phone', 'Teléfono', 'private'],
        ['email', 'Correo', 'private']
    ];

    thead.innerHTML = '<tr>' + columns.map(function (c) {
        return '<th class="' + (c[2] === 'key' ? '' : 'col-' + c[2]) + '">' + esc(c[1]) + '</th>';
    }).join('') + '</tr>';

    tbody.innerHTML = data.rows.length
        ? data.rows.map(function (r) {
            return '<tr>' + columns.map(function (c) {
                let value = r[c[0]];
                if (c[0] === 'accepts_marketing') value = value ? 'Sí' : 'No';
                if (c[0] === 'birth_date') value = formatDate(value);
                return '<td class="' + (c[2] === 'key' ? '' : 'col-' + c[2]) + '">' + esc(value) + '</td>';
            }).join('') + '</tr>';
        }).join('')
        : emptyRow(columns.length, 'Sin clientes.');
}

function renderReplication(result) {
    const thead = $('#replicationTable thead');
    const tbody = $('#replicationTable tbody');

    if (result.status !== 'fulfilled') {
        tbody.innerHTML = emptyRow(1, 'Error: ' + result.reason.message);
        return;
    }

    const subs = result.value.subscribers;
    const STATE = {
        sincronizado: ['Sincronizado', 'ok'],
        diferente: ['Diferente', 'warn'],
        sin_replicar: ['Sin replicar', 'bad']
    };

    thead.innerHTML = '<tr><th>Tabla</th><th class="num">Publicador</th>' + subs.map(function (s) {
        return '<th>' + esc(s.node === 'cloud' ? 'Azure' : (NODES[s.node] ? NODES[s.node].label : s.node)) + '</th>';
    }).join('') + '</tr>';

    const tables = (subs.find(function (s) { return s.tables.length; }) || { tables: [] }).tables.map(function (t) {
        return { table: t.table, publisher: t.publisher_rows };
    });

    if (!tables.length) {
        tbody.innerHTML = emptyRow(2 + subs.length, 'Ningún suscriptor responde todavía.');
        return;
    }

    tbody.innerHTML = tables.map(function (t) {
        return '<tr><td>' + esc(t.table) + '</td><td class="num">' + esc(t.publisher) + '</td>' + subs.map(function (s) {
            if (s.status === 'no_configurado') return '<td>' + badge('No configurado') + '</td>';
            if (s.status !== 'en_linea') return '<td>' + badge('Sin conexión', 'bad') + '</td>';
            const cell = s.tables.find(function (x) { return x.table === t.table; });
            if (!cell) return '<td>' + badge('—') + '</td>';
            const st = STATE[cell.state] || [cell.state, ''];
            return '<td>' + badge(st[0] + (cell.subscriber_rows !== null ? ' (' + cell.subscriber_rows + ')' : ''), st[1]) + '</td>';
        }).join('') + '</tr>';
    }).join('');
}

async function syncNodes() {
    const button = $('#syncButton');
    button.disabled = true;
    try {
        const data = await api('/distributed/sync', { method: 'POST' });
        toast('Fragmentos sincronizados: ' + data.synchronized_at);
        loadDistributed();
    } catch (e) {
        toast('No se pudo sincronizar: ' + e.message, true);
    } finally {
        button.disabled = false;
    }
}

const SECTIONS = {
    inventario: ['Inventario por sucursal', 'Cada pestaña lee el fragmento horizontal del nodo correspondiente.'],
    pedidos: ['Pedidos por nodo', 'Reconstruidos con la unión de los fragmentos de cada nodo.'],
    distribuida: ['Base de datos distribuida', 'Nodos, fragmentación, replicación y espejo en la nube.']
};

const MARKUP = {
    inventario: `<div class="tabs" id="inventoryTabs" role="tablist">
                <button role="tab" class="tab active" data-node="all" aria-selected="true">Todas</button>
                <button role="tab" class="tab" data-node="tijuana" aria-selected="false">Tijuana</button>
                <button role="tab" class="tab" data-node="cdmx" aria-selected="false">CDMX</button>
                <button role="tab" class="tab" data-node="online" aria-selected="false">En línea</button>
            </div>

            <div class="sources" id="inventorySources"></div>

            <div class="table-wrap">
                <table class="table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Nodo</th><th>Sucursal</th><th>SKU</th><th>Producto</th>
                            <th>Talla</th><th>Color</th><th class="num">Stock</th><th class="num">Mínimo</th><th>Estado</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>`,
    pedidos: `<div class="toolbar">
                <select id="orderNodeFilter" class="input" aria-label="Filtrar por nodo">
                    <option value="all">Todos los nodos</option>
                    <option value="tijuana">Tijuana</option>
                    <option value="cdmx">CDMX</option>
                    <option value="online">En línea</option>
                </select>
                <select id="orderStatusFilter" class="input" aria-label="Filtrar por estado">
                    <option value="">Todos los estados</option>
                    <option value="pending">Pendiente</option>
                    <option value="paid">Pagado</option>
                    <option value="shipped">Enviado</option>
                    <option value="delivered">Entregado</option>
                    <option value="cancelled">Cancelado</option>
                    <option value="returned">Devuelto</option>
                </select>
            </div>

            <div class="sources" id="orderSources"></div>
            <div class="order-list" id="orderList"></div>`,
    distribuida: `<h2 class="section-title">Nodos</h2>
            <div class="node-grid" id="nodeGrid"></div>

            <h2 class="section-title">Fragmentación horizontal <span class="muted small">predicado id_store</span></h2>
            <div class="fragment-grid" id="fragmentGrid"></div>

            <h2 class="section-title">Fragmentación vertical <span class="muted small">customer</span></h2>
            <div class="legend" id="verticalLegend"></div>
            <div class="table-wrap">
                <table class="table" id="verticalTable">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>

            <h2 class="section-title">Replicación del catálogo</h2>
            <div class="table-wrap">
                <table class="table" id="replicationTable">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>`
};

export function renderPanel(page, section) {
    const current = SECTIONS[section] ? section : 'inventario';
    setTitle('Panel · ' + SECTIONS[current][0]);
    page.innerHTML = '<div class="panel">' +
        '<div class="panel-head"><div><p class="eyebrow-dark">Panel de sucursales</p><h1>' + esc(SECTIONS[current][0]) + '</h1>' +
            '<p class="muted">' + esc(SECTIONS[current][1]) + '</p></div>' +
            (current === 'distribuida' ? '<button class="btn btn-dark" id="syncButton">Sincronizar fragmentos</button>' : '') +
        '</div>' +
        '<nav class="panel-tabs">' + Object.keys(SECTIONS).map(function (k) {
            return '<a href="#/panel/' + k + '"' + (k === current ? ' class="active" aria-current="page"' : '') + '>' + esc(SECTIONS[k][0]) + '</a>';
        }).join('') + '</nav>' +
        MARKUP[current] +
    '</div>';

    if (current === 'inventario') {
        $('#inventoryTabs', page).addEventListener('click', function (e) {
            const tab = e.target.closest('.tab');
            if (tab) loadInventory(tab.dataset.node);
        });
        loadInventory(state.inventoryNode);
    } else if (current === 'pedidos') {
        $('#orderNodeFilter', page).addEventListener('change', loadOrders);
        $('#orderStatusFilter', page).addEventListener('change', loadOrders);
        loadOrders();
    } else {
        $('#syncButton', page).addEventListener('click', syncNodes);
        loadDistributed();
    }
}
