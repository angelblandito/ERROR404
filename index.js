'use strict';

/* =====================================================================
   JAM Sport - frontend
   Vistas (menú de 4 opciones):
     1. Tienda             catálogo (calzado, ropa, accesorios), carrito y pedidos
                           con paleta de colores según la temporada
     2. Inventario         fragmentos horizontales de inventory por nodo
     3. Pedidos            fragmentos horizontales de customer_order
     4. Base distribuida   nodos, fragmentación, replicación y espejo
   ===================================================================== */

const API_URL = (window.JAM_CONFIG && window.JAM_CONFIG.API_URL) || 'http://localhost:8080';

const NODES = {
    tijuana: { label: 'Tijuana', idStore: 1 },
    cdmx: { label: 'CDMX', idStore: 2 },
    online: { label: 'En línea', idStore: 4 }
};

const STORE_NODE = { 1: 'tijuana', 2: 'cdmx', 4: 'online' };

const ORDER_STATUS = {
    pending: { label: 'Pendiente', css: 'warn' },
    paid: { label: 'Pagado', css: 'ok' },
    shipped: { label: 'Enviado', css: 'info' },
    delivered: { label: 'Entregado', css: 'ok' },
    cancelled: { label: 'Cancelado', css: 'bad' },
    returned: { label: 'Devuelto', css: 'bad' }
};

const CHANNEL = { in_store: 'En tienda', online: 'En línea', whatsapp: 'WhatsApp' };

const SEGMENT = { youth: 'Juvenil 15–24', adult: 'Adulto 25–45', all: '15–45 años' };
const GENDER = { M: 'Hombre', F: 'Mujer', U: 'Unisex' };

/* Copia local de season_theme, usada solo si la API no responde. */
const FALLBACK_THEMES = [
    { code: 'spring', name: 'Primavera', theme_type: 'season', range: [320, 620], priority: 1, color_primary: '#1D4D3A', color_secondary: '#2A6B50', color_accent: '#FFB3C7', color_accent_text: '#1D4D3A', hero_title: 'Renueva tu rutina', hero_text: 'Colores frescos y prendas ligeras para entrenar al aire libre.' },
    { code: 'summer', name: 'Verano', theme_type: 'season', range: [621, 922], priority: 1, color_primary: '#0B3D5C', color_secondary: '#0E5A85', color_accent: '#FFD23F', color_accent_text: '#0B3D5C', hero_title: 'Verano en movimiento', hero_text: 'Shorts, tops y tenis transpirables para los días de más calor.' },
    { code: 'autumn', name: 'Otoño', theme_type: 'season', range: [923, 1220], priority: 1, color_primary: '#3B1F14', color_secondary: '#5A2E1B', color_accent: '#FF8C42', color_accent_text: '#3B1F14', hero_title: 'Entrena en otoño', hero_text: 'Capas ligeras, rompevientos y tenis para correr con el clima fresco.' },
    { code: 'winter', name: 'Invierno', theme_type: 'season', range: [1221, 319], priority: 1, color_primary: '#0F1D3D', color_secondary: '#1C2F5E', color_accent: '#9BE7FF', color_accent_text: '#0F1D3D', hero_title: 'Invierno sin pausa', hero_text: 'Sudaderas, chamarras y leggings térmicos para no dejar de moverte.' },
    { code: 'back_to_school', name: 'Regreso a clases', theme_type: 'campaign', range: [801, 831], priority: 5, color_primary: '#1E1B4B', color_secondary: '#312E81', color_accent: '#FACC15', color_accent_text: '#1E1B4B', hero_title: 'Regreso a clases', hero_text: 'Mochilas, tenis y uniformes deportivos para el nuevo ciclo escolar.' },
    { code: 'buen_fin', name: 'El Buen Fin', theme_type: 'campaign', range: [1113, 1117], priority: 9, color_primary: '#7A0019', color_secondary: '#A3001F', color_accent: '#FFD000', color_accent_text: '#3A0010', hero_title: 'El Buen Fin JAM', hero_text: 'Descuentos en calzado, ropa y accesorios del 13 al 17 de noviembre.' },
    { code: 'black_friday', name: 'Black Friday', theme_type: 'campaign', range: [1120, 1130], priority: 9, color_primary: '#0A0A0A', color_secondary: '#262626', color_accent: '#FF2D55', color_accent_text: '#FFFFFF', hero_title: 'Black Friday', hero_text: 'Hasta 25% de descuento con el cupón BLACKFRIDAY.' },
    { code: 'christmas', name: 'Navidad', theme_type: 'campaign', range: [1201, 106], priority: 8, color_primary: '#14351F', color_secondary: '#1E4D2B', color_accent: '#E63946', color_accent_text: '#FFFFFF', hero_title: 'Regala movimiento', hero_text: 'Ideas de regalo deportivas para jóvenes y adultos.' }
];

const state = {
    products: [],
    categories: [],
    variants: [],
    sizes: [],
    colors: [],
    promotions: [],
    inventory: [],
    customers: [],
    cart: loadCart(),
    activeCategory: 'all',
    department: 'all',
    segment: 'all',
    gender: 'all',
    themes: [],
    search: '',
    inventoryNode: 'all',
    storeLoaded: false
};

const money = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

/* ---------------------------------------------------------------------
   Utilidades
   --------------------------------------------------------------------- */
function $(selector, root) {
    return (root || document).querySelector(selector);
}

function esc(value) {
    return String(value === null || value === undefined ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function isActive(row) {
    return Number(row.deleted) !== 1;
}

function formatDate(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (isNaN(date)) return String(value);
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}

function badge(text, css) {
    return '<span class="badge ' + (css || '') + '">' + esc(text) + '</span>';
}

function emptyRow(colspan, text) {
    return '<tr><td class="empty" colspan="' + colspan + '">' + esc(text) + '</td></tr>';
}

let toastTimer = null;
function toast(message, isError) {
    const el = $('#toast');
    el.textContent = message;
    el.classList.toggle('error', Boolean(isError));
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.hidden = true; }, 4000);
}

async function api(path, options) {
    const opts = Object.assign({ method: 'GET' }, options || {});
    if (opts.body !== undefined) {
        opts.headers = { 'Content-Type': 'application/json' };
        opts.body = JSON.stringify(opts.body);
    }

    let response;
    try {
        response = await fetch(API_URL + path, opts);
    } catch (e) {
        throw new Error('No se pudo conectar con la API (' + API_URL + ').');
    }

    let payload;
    try {
        payload = await response.json();
    } catch (e) {
        throw new Error('La API respondió con un formato inválido (HTTP ' + response.status + ').');
    }

    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || ('Error HTTP ' + response.status));
    }
    return payload.data;
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

/* ---------------------------------------------------------------------
   Navegación
   --------------------------------------------------------------------- */
const VIEWS = ['tienda', 'inventario', 'pedidos', 'distribuida'];

const LOADERS = {
    tienda: loadStore,
    inventario: function () { return loadInventory(state.inventoryNode); },
    pedidos: loadOrders,
    distribuida: loadDistributed
};

function currentView() {
    const name = window.location.hash.replace('#', '');
    return VIEWS.indexOf(name) >= 0 ? name : 'tienda';
}

function showView() {
    const name = currentView();

    VIEWS.forEach(function (view) {
        $('#view-' + view).hidden = view !== name;
    });

    document.querySelectorAll('.main-nav a').forEach(function (link) {
        const active = link.dataset.view === name;
        link.classList.toggle('active', active);
        if (active) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });

    closeMenu();
    LOADERS[name]();
}

function closeMenu() {
    $('#mainNav').classList.remove('open');
    $('#menuToggle').setAttribute('aria-expanded', 'false');
}

function toggleMenu() {
    const nav = $('#mainNav');
    const open = !nav.classList.contains('open');
    nav.classList.toggle('open', open);
    $('#menuToggle').setAttribute('aria-expanded', String(open));
}

async function checkApi() {
    const el = $('#apiStatus');
    try {
        const data = await api('/health-check');
        const ok = data.database === 'en_linea';
        el.className = 'api-status ' + (ok ? 'ok' : 'down');
        el.title = 'API en línea · nodo ' + data.node + (ok ? '' : ' (sin base de datos)');
    } catch (e) {
        el.className = 'api-status down';
        el.title = e.message;
    }
}

/* ---------------------------------------------------------------------
   Paleta por temporada (tabla season_theme)
   --------------------------------------------------------------------- */
function localDate() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function fallbackThemeFor(date) {
    const md = Number(date.slice(5, 7)) * 100 + Number(date.slice(8, 10));
    return FALLBACK_THEMES.filter(function (t) {
        const start = t.range[0];
        const end = t.range[1];
        return start <= end ? (md >= start && md <= end) : (md >= start || md <= end);
    }).sort(function (a, b) { return b.priority - a.priority; })[0];
}

function applyTheme(theme) {
    if (!theme) return;
    const root = document.documentElement.style;
    root.setProperty('--brand', theme.color_primary);
    root.setProperty('--brand-2', theme.color_secondary);
    root.setProperty('--accent', theme.color_accent);
    root.setProperty('--accent-ink', theme.color_accent_text);
    document.documentElement.dataset.theme = theme.code;
    document.querySelector('meta[name="theme-color"]').setAttribute('content', theme.color_primary);

    $('#heroEyebrow').textContent = theme.name + ' · Para jóvenes y adultos de 15 a 45 años';
    $('#tituloTienda').textContent = theme.hero_title;
    $('#heroText').textContent = theme.hero_text;
}

function savedThemeChoice() {
    const fromUrl = new URLSearchParams(window.location.search).get('temporada');
    if (fromUrl) return fromUrl;
    try {
        return localStorage.getItem('jam_theme') || 'auto';
    } catch (e) {
        return 'auto';
    }
}

async function loadTheme() {
    try {
        state.themes = await api('/season_theme');
    } catch (e) {
        state.themes = FALLBACK_THEMES;
    }

    $('#seasonSelect').innerHTML = '<option value="auto">Automática (según la fecha)</option>' +
        state.themes.map(function (t) {
            return '<option value="' + esc(t.code) + '">' + esc(t.name) + (t.theme_type === 'campaign' ? ' · campaña' : '') + '</option>';
        }).join('');

    const choice = savedThemeChoice();
    $('#seasonSelect').value = state.themes.some(function (t) { return t.code === choice; }) ? choice : 'auto';
    await selectTheme($('#seasonSelect').value);
}

async function selectTheme(code) {
    if (code !== 'auto') {
        applyTheme(state.themes.find(function (t) { return t.code === code; }));
        return;
    }
    try {
        applyTheme(await api('/season_theme/current?date=' + localDate()));
    } catch (e) {
        applyTheme(fallbackThemeFor(localDate()));
    }
}

/* ---------------------------------------------------------------------
   1. Tienda
   --------------------------------------------------------------------- */
async function loadStore(force) {
    if (state.storeLoaded && !force) {
        renderProducts();
        return;
    }

    $('#productGrid').innerHTML = '<div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>';

    try {
        const results = await Promise.all([
            api('/product'), api('/category'), api('/product_variant'),
            api('/size'), api('/color'), api('/promotion')
        ]);
        state.products = results[0].filter(isActive);
        state.categories = results[1];
        state.variants = results[2].filter(isActive);
        state.sizes = results[3];
        state.colors = results[4];
        state.promotions = results[5].filter(isActive);
    } catch (e) {
        $('#productGrid').innerHTML = '<p class="empty">No se pudo cargar el catálogo: ' + esc(e.message) + '</p>';
        return;
    }

    try {
        state.inventory = (await api('/distributed/inventory')).rows;
    } catch (e) {
        state.inventory = [];
    }

    state.storeLoaded = true;
    renderCategoryChips();
    renderProducts();
}

function renderCategoryChips() {
    const categories = state.categories.filter(function (c) {
        return state.department === 'all' || c.department === state.department;
    });
    if (categories.length <= 1) {
        $('#categoryChips').innerHTML = '';
        return;
    }
    const chips = [{ id: 'all', name: 'Todas las categorías' }].concat(categories.map(function (c) {
        return { id: String(c.id_category), name: c.name };
    }));

    $('#categoryChips').innerHTML = chips.map(function (c) {
        return '<button class="chip' + (state.activeCategory === c.id ? ' active' : '') +
            '" data-category="' + esc(c.id) + '">' + esc(c.name) + '</button>';
    }).join('');
}

function variantLabel(variant) {
    const size = state.sizes.find(function (s) { return Number(s.id_size) === Number(variant.id_size); });
    const color = state.colors.find(function (c) { return Number(c.id_color) === Number(variant.id_color); });
    return (size ? size.code : '?') + ' · ' + (color ? color.name : '?');
}

function availabilityHtml(idVariant) {
    return Object.keys(NODES).map(function (node) {
        const row = state.inventory.find(function (r) {
            return r.node === node && Number(r.id_variant) === Number(idVariant);
        });
        const label = NODES[node].label;
        if (!row || row.stock <= 0) return badge(label + ': agotado', 'bad');
        return badge(label + ': ' + row.stock, row.low_stock ? 'warn' : 'ok');
    }).join('');
}

function renderProducts() {
    const search = state.search.trim().toLowerCase();
    const list = state.products.filter(function (p) {
        const category = state.categories.find(function (c) { return Number(c.id_category) === Number(p.id_category); });
        const matchDepartment = state.department === 'all' || (category && category.department === state.department);
        const matchCategory = state.activeCategory === 'all' || String(p.id_category) === state.activeCategory;
        const segment = p.age_segment || 'all';
        const matchSegment = state.segment === 'all' || segment === 'all' || segment === state.segment;
        const matchGender = state.gender === 'all' || p.target_gender === state.gender || p.target_gender === 'U';
        const text = (p.name + ' ' + (p.description || '')).toLowerCase();
        return matchDepartment && matchCategory && matchSegment && matchGender && (!search || text.indexOf(search) >= 0);
    });

    if (!list.length) {
        $('#productGrid').innerHTML = '<p class="empty">No hay productos que coincidan.</p>';
        return;
    }

    $('#productGrid').innerHTML = list.map(function (p) {
        const category = state.categories.find(function (c) { return Number(c.id_category) === Number(p.id_category); });
        const variants = state.variants.filter(function (v) { return Number(v.id_product) === Number(p.id_product); });
        const first = variants[0];

        const options = variants.map(function (v) {
            return '<option value="' + esc(v.id_variant) + '">' + esc(variantLabel(v)) + '</option>';
        }).join('');

        return '<article class="card" data-product="' + esc(p.id_product) + '">' +
            '<div class="product-media" aria-hidden="true">' + esc(category ? category.code.toUpperCase() : 'JAM') + '</div>' +
            '<div class="product-body">' +
                '<span class="product-category">' + esc(category ? category.name : '') + '</span>' +
                '<h3 class="product-name">' + esc(p.name) + '</h3>' +
                '<p class="product-desc">' + esc(p.description || '') + '</p>' +
                '<div class="product-tags">' + badge(SEGMENT[p.age_segment || 'all'], 'info') + badge(GENDER[p.target_gender] || '') + '</div>' +
                '<span class="price">' + money.format(Number(p.base_price)) + '</span>' +
                (variants.length
                    ? '<select class="input variant-select" aria-label="Talla y color de ' + esc(p.name) + '">' + options + '</select>' +
                      '<div class="availability">' + availabilityHtml(first.id_variant) + '</div>' +
                      '<button class="btn btn-dark add-to-cart">Agregar al carrito</button>'
                    : '<span class="badge">Sin tallas disponibles</span>') +
            '</div>' +
        '</article>';
    }).join('');
}

/* ---------- Carrito ---------- */
function loadCart() {
    try {
        return JSON.parse(localStorage.getItem('jam_cart')) || [];
    } catch (e) {
        return [];
    }
}

function saveCart() {
    try {
        localStorage.setItem('jam_cart', JSON.stringify(state.cart));
    } catch (e) {
        /* El carrito sigue funcionando en memoria. */
    }
}

function addToCart(card) {
    const product = state.products.find(function (p) { return String(p.id_product) === card.dataset.product; });
    const idVariant = Number($('.variant-select', card).value);
    const variant = state.variants.find(function (v) { return Number(v.id_variant) === idVariant; });
    if (!product || !variant) return;

    const existing = state.cart.find(function (i) { return i.id_variant === idVariant; });
    if (existing) {
        existing.quantity += 1;
    } else {
        state.cart.push({
            id_variant: idVariant,
            name: product.name,
            variant: variantLabel(variant),
            sku: variant.sku,
            unit_price: Number(product.base_price),
            quantity: 1
        });
    }
    saveCart();
    renderCart();
    toast(product.name + ' agregado al carrito');
}

function findPromotion(code) {
    if (!code) return null;
    const today = new Date().toISOString().slice(0, 10);
    return state.promotions.find(function (p) {
        return p.code.toUpperCase() === code.trim().toUpperCase() &&
            String(p.start_date).slice(0, 10) <= today &&
            String(p.end_date).slice(0, 10) >= today;
    }) || null;
}

function cartTotals() {
    const subtotal = state.cart.reduce(function (sum, i) { return sum + i.unit_price * i.quantity; }, 0);
    const promotion = findPromotion($('#checkoutCoupon').value);
    const discount = promotion ? Math.round(subtotal * Number(promotion.discount_percent)) / 100 : 0;
    return { subtotal: subtotal, discount: discount, total: subtotal - discount, promotion: promotion };
}

function renderCart() {
    const count = state.cart.reduce(function (sum, i) { return sum + i.quantity; }, 0);
    $('#cartCount').textContent = count;

    $('#cartItems').innerHTML = state.cart.length
        ? state.cart.map(function (i) {
            return '<div class="cart-item">' +
                '<span class="cart-item-name">' + esc(i.name) + '</span>' +
                '<strong>' + money.format(i.unit_price * i.quantity) + '</strong>' +
                '<span class="cart-item-meta">' + esc(i.variant + ' · ' + i.sku) + '</span>' +
                '<span class="qty">' +
                    '<button type="button" data-qty="-1" data-variant="' + i.id_variant + '" aria-label="Quitar uno">−</button>' +
                    '<span>' + i.quantity + '</span>' +
                    '<button type="button" data-qty="1" data-variant="' + i.id_variant + '" aria-label="Agregar uno">+</button>' +
                '</span>' +
            '</div>';
        }).join('')
        : '<p class="empty">Tu carrito está vacío.</p>';

    const totals = cartTotals();
    $('#cartSubtotal').textContent = money.format(totals.subtotal);
    $('#cartDiscount').textContent = (totals.promotion ? totals.promotion.code + ' ' : '') + '-' + money.format(totals.discount);
    $('#cartTotal').textContent = money.format(totals.total);
    $('#checkoutButton').disabled = !state.cart.length;
}

function changeQuantity(idVariant, delta) {
    const item = state.cart.find(function (i) { return i.id_variant === idVariant; });
    if (!item) return;
    item.quantity += delta;
    if (item.quantity <= 0) {
        state.cart = state.cart.filter(function (i) { return i !== item; });
    }
    saveCart();
    renderCart();
}

async function openCart() {
    $('#cartDrawer').classList.add('open');
    $('#cartDrawer').setAttribute('aria-hidden', 'false');
    $('#overlay').hidden = false;
    renderCart();

    if (!state.customers.length) {
        try {
            state.customers = (await api('/distributed/customers')).rows;
        } catch (e) {
            state.customers = [];
        }
        $('#checkoutCustomer').innerHTML = '<option value="">Selecciona un cliente</option>' +
            state.customers.map(function (c) {
                return '<option value="' + esc(c.id_customer) + '">' +
                    esc(c.first_name + ' ' + c.last_name_paternal + ' (' + (c.email || 'sin correo') + ')') + '</option>';
            }).join('');
    }
}

function closeCart() {
    $('#cartDrawer').classList.remove('open');
    $('#cartDrawer').setAttribute('aria-hidden', 'true');
    $('#overlay').hidden = true;
}

function stockProblems(idStore) {
    const node = STORE_NODE[idStore];
    return state.cart.filter(function (item) {
        const row = state.inventory.find(function (r) {
            return r.node === node && Number(r.id_variant) === item.id_variant;
        });
        return !row || row.stock < item.quantity;
    });
}

async function checkout(event) {
    event.preventDefault();
    if (!state.cart.length) return;

    const idStore = Number($('#checkoutStore').value);
    const idCustomer = Number($('#checkoutCustomer').value);
    if (!idCustomer) {
        toast('Selecciona un cliente.', true);
        return;
    }

    const missing = stockProblems(idStore);
    if (missing.length) {
        toast('Sin existencias suficientes en ' + NODES[STORE_NODE[idStore]].label + ': ' +
            missing.map(function (i) { return i.name; }).join(', '), true);
        return;
    }

    const totals = cartTotals();
    const button = $('#checkoutButton');
    button.disabled = true;
    button.textContent = 'Procesando…';

    try {
        const order = await api('/orders/checkout', {
            method: 'POST',
            body: {
                id_store: idStore,
                id_customer: idCustomer,
                id_employee: null,
                channel: idStore === 4 ? 'online' : 'in_store',
                promotion_code: totals.promotion ? totals.promotion.code : null,
                notes: 'Pedido desde la tienda web',
                source_node: 'web_node',
                items: state.cart.map(function (item) {
                    return { id_variant: item.id_variant, quantity: item.quantity };
                })
            }
        });
        const orderId = order.id_order;

        try {
            await api('/distributed/sync', { method: 'POST' });
        } catch (e) {
            /* Los fragmentos se actualizarán con el job de sincronización. */
        }

        state.cart = [];
        saveCart();
        renderCart();
        closeCart();
        toast('Pedido #' + orderId + ' creado en ' + NODES[STORE_NODE[idStore]].label + ' por ' + money.format(Number(order.total)) + '.');
        loadStore(true);
    } catch (e) {
        toast(e.message, true);
    } finally {
        button.disabled = !state.cart.length;
        button.textContent = 'Confirmar pedido';
    }
}

/* ---------------------------------------------------------------------
   2. Inventario por sucursal
   --------------------------------------------------------------------- */
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

/* ---------------------------------------------------------------------
   3. Pedidos
   --------------------------------------------------------------------- */
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

/* ---------------------------------------------------------------------
   4. Base distribuida
   --------------------------------------------------------------------- */
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

/* ---------------------------------------------------------------------
   Eventos
   --------------------------------------------------------------------- */
function bindEvents() {
    window.addEventListener('hashchange', showView);
    $('#menuToggle').addEventListener('click', toggleMenu);

    $('#searchProducts').addEventListener('input', function (e) {
        state.search = e.target.value;
        renderProducts();
    });

    $('#departmentTabs').addEventListener('click', function (e) {
        const tab = e.target.closest('button[data-department]');
        if (!tab) return;
        state.department = tab.dataset.department;
        state.activeCategory = 'all';
        document.querySelectorAll('#departmentTabs button').forEach(function (b) {
            const active = b === tab;
            b.classList.toggle('active', active);
            b.setAttribute('aria-selected', String(active));
        });
        renderCategoryChips();
        renderProducts();
    });

    $('#segmentFilter').addEventListener('change', function (e) {
        state.segment = e.target.value;
        renderProducts();
    });

    $('#genderFilter').addEventListener('change', function (e) {
        state.gender = e.target.value;
        renderProducts();
    });

    $('#seasonSelect').addEventListener('change', function (e) {
        try {
            localStorage.setItem('jam_theme', e.target.value);
        } catch (err) {
            /* La elección dura solo esta visita. */
        }
        selectTheme(e.target.value);
    });

    $('#categoryChips').addEventListener('click', function (e) {
        const chip = e.target.closest('.chip');
        if (!chip) return;
        state.activeCategory = chip.dataset.category;
        renderCategoryChips();
        renderProducts();
    });

    $('#productGrid').addEventListener('change', function (e) {
        if (!e.target.classList.contains('variant-select')) return;
        const card = e.target.closest('.card');
        $('.availability', card).innerHTML = availabilityHtml(e.target.value);
    });

    $('#productGrid').addEventListener('click', function (e) {
        if (!e.target.classList.contains('add-to-cart')) return;
        addToCart(e.target.closest('.card'));
    });

    $('#cartButton').addEventListener('click', openCart);
    $('#closeCart').addEventListener('click', closeCart);
    $('#overlay').addEventListener('click', closeCart);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeCart();
    });

    $('#cartItems').addEventListener('click', function (e) {
        const button = e.target.closest('button[data-qty]');
        if (!button) return;
        changeQuantity(Number(button.dataset.variant), Number(button.dataset.qty));
    });

    $('#checkoutCoupon').addEventListener('input', renderCart);
    $('#checkoutForm').addEventListener('submit', checkout);

    $('#inventoryTabs').addEventListener('click', function (e) {
        const tab = e.target.closest('.tab');
        if (tab) loadInventory(tab.dataset.node);
    });

    $('#orderNodeFilter').addEventListener('change', loadOrders);
    $('#orderStatusFilter').addEventListener('change', loadOrders);

    document.querySelectorAll('[data-reload]').forEach(function (button) {
        button.addEventListener('click', function () {
            const view = button.dataset.reload;
            if (view === 'inventario') loadInventory(state.inventoryNode);
            if (view === 'pedidos') loadOrders();
            if (view === 'distribuida') loadDistributed();
        });
    });

    $('#syncButton').addEventListener('click', syncNodes);
}

document.addEventListener('DOMContentLoaded', function () {
    bindEvents();
    renderCart();
    checkApi();
    loadTheme();
    showView();
});
