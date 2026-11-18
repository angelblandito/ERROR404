/* Rutas de la tienda, encabezado, búsqueda y favoritos. */
import { $, $$, clone, fill, slot, money, toast, emptyState, loading, parseQuery } from './ui.js';
import { productArt, handleMissingPhoto } from './illustrations.js';
import { setFavState } from './components.js';
import { cartCount, colorsOf, loadCatalog, loadCustomer, state, toggleFavorite } from './store.js';
import { initTheme, currentTheme } from './theme.js';
import { initEffects } from './effects.js';
import { closeMiniCart } from './minicart.js';
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

const NEEDS_CATALOG = function (path) { return !/^\/panel/.test(path); };

async function route() {
    const hash = window.location.hash.replace(/^#/, '') || '/';
    const [path, query] = hash.split('?');
    const match = ROUTES.find(function (r) { return r[0].test(path); });

    closeMenu();
    closeSuggestions();
    closeMiniCart();

    const page = document.createElement('div');
    page.className = 'page';
    view.replaceChildren(page);

    if (!match) {
        page.replaceChildren(emptyState('Página no encontrada', 'El enlace que seguiste no existe.', { href: '#/', label: 'Ir al inicio' }));
        return;
    }

    $$('.tabbar a').forEach(function (a) { a.classList.toggle('active', a.dataset.tab === match[1]); });

    if (NEEDS_CATALOG(path)) {
        page.replaceChildren(loading('Cargando la tienda…'));
        try {
            await loadCatalog();
        } catch (e) {
            page.replaceChildren(emptyState('No pudimos cargar la tienda', e.message, { href: window.location.hash || '#/', label: 'Reintentar' }));
            return;
        }
        page.replaceChildren();
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
    const items = found.map(function (p) {
        const colors = colorsOf(p);
        const item = fill(clone('tpl-suggestion'), { name: p.name, price: money.format(p.base_price) });
        item.href = '#/producto/' + p.id_product;
        slot(item, 'art').replaceWith(productArt(p, colors[0] ? colors[0].color_code : 'black', 'mini'));
        return item;
    });
    const all = clone('tpl-see-all');
    all.href = '#/tienda?q=' + encodeURIComponent(text.trim());
    box.replaceChildren(...items, all);
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
    document.addEventListener('jam:theme', function () {
        i = 0;
        el.textContent = messages()[0];
    });
    setInterval(function () {
        i = (i + 1) % messages().length;
        el.textContent = messages()[i];
    }, 5000);
}

function bindHeader() {
    $('#menuButton').addEventListener('click', openMenu);
    $('#menuClose').addEventListener('click', closeMenu);
    $('#miniCartClose').addEventListener('click', closeMiniCart);
    $('#miniCartBackdrop').addEventListener('click', closeMiniCart);
    $('#mobileMenu').addEventListener('click', function (e) {
        if (e.target === e.currentTarget) closeMenu();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMenu();
            closeSuggestions();
            closeMiniCart();
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

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-fav]');
        if (!btn) return;
        e.preventDefault();
        const active = toggleFavorite(btn.dataset.fav);
        $$('[data-fav="' + btn.dataset.fav + '"]').forEach(function (b) { setFavState(b, active); });
        toast(active ? 'Guardado en favoritos' : 'Se quitó de favoritos', active ? { action: { href: '#/favoritos', label: 'Ver' } } : {});
    });
}

document.addEventListener('error', function (e) {
    if (e.target.classList && e.target.classList.contains('product-photo')) {
        handleMissingPhoto(e.target);
    }
}, true);

document.addEventListener('jam:change', updateCounts);
window.addEventListener('hashchange', route);

bindHeader();
updateCounts();
rotateAnnouncements();
initEffects();
initTheme();
loadCustomer().finally(route);
