/* Rutas de la tienda, encabezado, búsqueda y favoritos. */
import { $, $$, clone, fill, slot, money, toast, emptyState, loading, parseQuery } from './ui.js';
import { productArt, handleMissingPhoto } from './illustrations.js';
import { setFavState } from './components.js';
import { account, cartCount, colorsOf, isAdmin, isPriority, loadCatalog, loadCustomer, salePrice, state, toggleFavorite } from './store.js';
import { initTheme, currentTheme } from './theme.js';
import { initEffects } from './effects.js';
import { closeMiniCart } from './minicart.js';
import { matchScore } from './search.js';
import { initAnalytics } from './analytics.js';
import { renderHome } from './views/home.js';
import { renderCatalog, renderOutlet } from './views/catalog.js';
import { renderProduct } from './views/product.js';
import { renderCart } from './views/cart.js';
import { renderCheckout, renderConfirmation } from './views/checkout.js';
import { authPanel, renderAccount, renderReset, renderConfirmSignup } from './views/account.js';
import { renderPriority } from './views/priority.js';
import { renderContact } from './views/contact.js';
import { renderMarket } from './views/market.js';
import { renderScanner } from './views/scanner.js';
import { renderSupport } from './views/support.js';
import { renderAdmin } from './views/admin.js';
import { renderFavorites, renderOffers, renderStores, renderHelp, renderLegal } from './views/pages.js';
import { renderPanel } from './views/panel.js';

const view = $('#view');
let previousPath = '';
let routeFrom = '';

const ROUTES = [
    [/^\/?$/, 'home', function (page) { return renderHome(page); }],
    [/^\/tienda$/, 'shop', function (page, m, q) { return renderCatalog(page, q); }],
    [/^\/outlet$/, 'shop', function (page, m, q) { return renderOutlet(page, q, !/^\/outlet/.test(routeFrom)); }],
    [/^\/producto\/(\d+)$/, 'shop', function (page, m, q) { return renderProduct(page, m[1], q); }],
    [/^\/carrito$/, 'cart', function (page) { return renderCart(page); }],
    [/^\/pago$/, 'cart', function (page) { return renderCheckout(page); }],
    [/^\/pedido\/(\d+)$/, 'account', function (page, m) { return renderConfirmation(page, m[1]); }],
    [/^\/cuenta$/, 'account', function (page, m, q) { return renderAccount(page, q); }],
    [/^\/favoritos$/, 'favorites', function (page) { return renderFavorites(page); }],
    [/^\/ofertas$/, 'shop', function (page) { return renderOffers(page); }],
    [/^\/sucursales$/, '', function (page) { return renderStores(page); }],
    [/^\/ayuda$/, '', function (page) { return renderHelp(page); }],
    [/^\/soporte$/, 'account', function (page, m, q) { return renderSupport(page, q); }],
    [/^\/contacto$/, '', function (page) { return renderContact(page); }],
    [/^\/prioritario$/, 'account', function (page) { return renderPriority(page); }],
    [/^\/confirmar$/, 'account', function (page, m, q) { return renderConfirmSignup(page, q); }],
    [/^\/mercado$/, 'account', function (page) { return renderMarket(page); }],
    [/^\/escanear$/, 'scan', function (page) { return renderScanner(page); }],
    [/^\/restablecer$/, 'account', function (page, m, q) { return renderReset(page, q); }],
    [/^\/legal\/(\w+)$/, '', function (page, m) { return renderLegal(page, m[1]); }],
    [/^\/admin(?:\/(\w+))?$/, '', adminOnly(function (page, m) { return renderAdmin(page, m[1]); })],
    [/^\/panel(?:\/(\w+))?$/, '', adminOnly(function (page, m) { return renderPanel(page, m[1]); })]
];

const NEEDS_CATALOG = function (path) { return !/^\/(panel|legal|restablecer)/.test(path); };

/* La administración y el panel distribuido solo se muestran a cuentas de administrador. */
function adminOnly(render) {
    return function (page, m, q) {
        if (isAdmin()) return render(page, m, q);
        if (state.customer) {
            page.replaceChildren(emptyState('Acceso solo para administradores', 'Tu cuenta no tiene permisos para esta sección.', { href: '#/', label: 'Ir al inicio' }));
            return null;
        }
        page.replaceChildren(authPanel('Administración', 'Inicia sesión con una cuenta de administrador.', route));
        return null;
    };
}

async function route() {
    const hash = window.location.hash.replace(/^#/, '') || '/';
    const [path, query] = hash.split('?');
    routeFrom = previousPath;
    previousPath = path;
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
function updateAccount() {
    const acc = account();
    const chip = $('#tierChip');
    const signedIn = Boolean(state.customer);
    chip.hidden = !signedIn || acc.tier === 'standard';
    chip.textContent = acc.name;
    chip.href = acc.can_manage_store ? '#/admin' : '#/cuenta';
    chip.className = 'tier-chip tier-' + acc.tier;
    document.body.classList.toggle('tier-priority', signedIn && acc.tier !== 'standard');
    $$('[data-role="admin"]').forEach(function (el) { el.hidden = !acc.can_manage_store; });
    $$('[data-role="priority"]').forEach(function (el) { el.hidden = !signedIn || !isPriority(); });
}

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

function suggest(text) {
    const box = $('#searchSuggestions');
    const q = text.trim();
    if (q.length < 2 || !state.products.length) {
        closeSuggestions();
        return;
    }
    const found = state.products.map(function (p) {
        return { p: p, score: matchScore(q, p.name + ' ' + p.category) };
    }).filter(function (r) { return r.score > 0; }).sort(function (a, b) {
        return b.score - a.score;
    }).slice(0, 5).map(function (r) { return r.p; });
    const items = found.map(function (p) {
        const colors = colorsOf(p);
        const item = fill(clone('tpl-suggestion'), { name: p.name, price: money.format(salePrice(p)) });
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
            currentTheme.hero_title.toLowerCase().indexOf(currentTheme.name.toLowerCase()) >= 0
                ? currentTheme.hero_title
                : currentTheme.name + ': ' + currentTheme.hero_title,
            account().free_shipping_from === 0
                ? 'Cliente ' + account().name + ': envío gratis y prioritario en todos tus pedidos'
                : 'Envío gratis en compras desde ' + money.format(account().free_shipping_from),
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
document.addEventListener('jam:change', updateAccount);
window.addEventListener('hashchange', route);

bindHeader();
updateCounts();
rotateAnnouncements();
initEffects();
initTheme();
loadCustomer().finally(function () {
    route();
    initAnalytics();
});

/* La tienda se puede instalar como aplicación (PWA). El service worker solo
   funciona en https o en localhost. */
if ('serviceWorker' in navigator && (window.isSecureContext || window.location.hostname === 'localhost')) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('sw.js').catch(function () {});
    });
}
