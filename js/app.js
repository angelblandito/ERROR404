/* Arranque de la tienda: rutas, encabezado, búsqueda, contadores y favoritos. */
import { $, $$, esc, money, toast, emptyState, loading, parseQuery } from './ui.js';
import { productArt } from './illustrations.js';
import { cartCount, colorsOf, loadCatalog, loadCustomer, state, toggleFavorite } from './store.js';
import { initTheme, currentTheme } from './theme.js';
import { renderHome } from './views/home.js';
import { renderCatalog } from './views/catalog.js';
import { renderProduct } from './views/product.js';
import { renderCart } from './views/cart.js';
import { renderCheckout, renderConfirmation } from './views/checkout.js';
import { renderAccount } from './views/account.js';
import { renderFavorites, renderOffers, renderStores, renderHelp } from './views/pages.js';
import { renderPanel } from './views/panel.js';

const view = $('#view');

const ROUTES = [
    [/^\/?$/, 'home', function (page) { return renderHome(page); }],
    [/^\/tienda$/, 'shop', function (page, m, q) { return renderCatalog(page, q); }],
    [/^\/producto\/(\d+)$/, 'shop', function (page, m, q) { return renderProduct(page, m[1], q); }],
    [/^\/carrito$/, 'cart', function (page) { return renderCart(page); }],
    [/^\/pago$/, 'cart', function (page) { return renderCheckout(page); }],
    [/^\/pedido\/(\d+)$/, 'account', function (page, m) { return renderConfirmation(page, m[1]); }],
    [/^\/cuenta$/, 'account', function (page, m, q) { return renderAccount(page, q); }],
    [/^\/favoritos$/, 'favorites', function (page) { return renderFavorites(page); }],
    [/^\/ofertas$/, 'shop', function (page) { return renderOffers(page); }],
    [/^\/sucursales$/, '', function (page) { return renderStores(page); }],
    [/^\/ayuda$/, '', function (page) { return renderHelp(page); }],
    [/^\/panel(?:\/(\w+))?$/, '', function (page, m) { return renderPanel(page, m[1]); }]
];

/* Las vistas del panel no dependen del catálogo de la tienda. */
const NEEDS_CATALOG = function (path) { return !/^\/panel/.test(path); };

async function route() {
    const hash = window.location.hash.replace(/^#/, '') || '/';
    const [path, query] = hash.split('?');
    const match = ROUTES.find(function (r) { return r[0].test(path); });

    closeMenu();
    closeSuggestions();

    const page = document.createElement('div');
    page.className = 'page';
    view.replaceChildren(page);

    if (!match) {
        page.innerHTML = emptyState('Página no encontrada', 'El enlace que seguiste no existe.', { href: '#/', label: 'Ir al inicio' });
        return;
    }

    $$('.tabbar a').forEach(function (a) { a.classList.toggle('active', a.dataset.tab === match[1]); });

    if (NEEDS_CATALOG(path)) {
        page.innerHTML = loading('Cargando la tienda…');
        try {
            await loadCatalog();
        } catch (e) {
            page.innerHTML = emptyState('No pudimos cargar la tienda', e.message, { href: window.location.hash || '#/', label: 'Reintentar' });
            return;
        }
        page.innerHTML = '';
    }

    window.scrollTo(0, 0);
    await match[2](page, path.match(match[0]), parseQuery(query));
}

/* ---------- Encabezado ---------- */
function updateCounts() {
    const counts = { cart: cartCount(), favorites: state.favorites.length };
    $$('[data-count]').forEach(function (el) {
        const n = counts[el.dataset.count];
        el.textContent = n > 9 ? '9+' : n;
        el.hidden = n === 0;
    });
}

function openMenu() {
    $('#mobileMenu').hidden = false;
    $('#menuButton').setAttribute('aria-expanded', 'true');
    document.body.classList.add('no-scroll');
}

function closeMenu() {
    $('#mobileMenu').hidden = true;
    $('#menuButton').setAttribute('aria-expanded', 'false');
    document.body.classList.remove('no-scroll');
}

function closeSuggestions() {
    $('#searchSuggestions').hidden = true;
}

function normalize(text) {
    return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

function suggest(text) {
    const box = $('#searchSuggestions');
    const q = normalize(text.trim());
    if (q.length < 2 || !state.products.length) {
        closeSuggestions();
        return;
    }
    const found = state.products.filter(function (p) {
        return normalize(p.name + ' ' + p.category).indexOf(q) >= 0;
    }).slice(0, 5);
    box.innerHTML = found.map(function (p) {
        const colors = colorsOf(p);
        return '<a role="option" href="#/producto/' + p.id_product + '">' + productArt(p, colors[0] ? colors[0].color_code : 'black', 'mini') +
            '<span><strong>' + esc(p.name) + '</strong><em>' + money.format(p.base_price) + '</em></span></a>';
    }).join('') + '<a class="see-all" href="#/tienda?q=' + encodeURIComponent(text.trim()) + '">Ver todos los resultados</a>';
    box.hidden = false;
}

function rotateAnnouncements() {
    const el = $('#announcementText');
    let i = 0;
    const messages = function () {
        return [
            currentTheme.name + ': ' + currentTheme.hero_title,
            'Envío gratis en compras desde ' + money.format(state.shipping.free_from),
            'Recoge sin costo en Tijuana o CDMX',
            '30 días para cambios y devoluciones'
        ];
    };
    el.textContent = messages()[0];
    setInterval(function () {
        i = (i + 1) % messages().length;
        el.textContent = messages()[i];
    }, 5000);
}

function bindHeader() {
    $('#menuButton').addEventListener('click', openMenu);
    $('#menuClose').addEventListener('click', closeMenu);
    $('#mobileMenu').addEventListener('click', function (e) {
        if (e.target === e.currentTarget) closeMenu();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMenu();
            closeSuggestions();
        }
    });

    const input = $('#searchInput');
    input.addEventListener('input', function () { suggest(input.value); });
    input.addEventListener('focus', function () { suggest(input.value); });
    $('#searchForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const q = input.value.trim();
        input.blur();
        window.location.hash = '#/tienda' + (q ? '?q=' + encodeURIComponent(q) : '');
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#searchForm')) closeSuggestions();
    });

    /* Favoritos desde cualquier tarjeta o página de producto */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-fav]');
        if (!btn) return;
        e.preventDefault();
        const active = toggleFavorite(btn.dataset.fav);
        $$('[data-fav="' + btn.dataset.fav + '"]').forEach(function (b) {
            b.classList.toggle('active', active);
            b.setAttribute('aria-pressed', String(active));
            b.setAttribute('aria-label', active ? 'Quitar de favoritos' : 'Agregar a favoritos');
        });
        toast(active ? 'Guardado en favoritos' : 'Se quitó de favoritos', active ? { action: { href: '#/favoritos', label: 'Ver' } } : {});
    });
}

document.addEventListener('jam:change', updateCounts);
window.addEventListener('hashchange', route);

bindHeader();
updateCounts();
rotateAnnouncements();
initTheme();
loadCustomer().finally(route);
