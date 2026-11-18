/* Estado de la tienda: catálogo, bolsa de compras, favoritos y sesión del cliente. */
import { api, getToken, setToken } from './api.js';

function loadJSON(key, fallback) {
    try {
        const value = JSON.parse(localStorage.getItem(key));
        return value === null ? fallback : value;
    } catch (e) {
        return fallback;
    }
}

function saveJSON(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
        /* Sin almacenamiento, los datos duran lo que la pestaña. */
    }
}

export const state = {
    products: [],
    byId: new Map(),
    stores: [],
    onlineStore: 4,
    shipping: { free_from: 999, cost: 99 },
    cart: loadJSON('jam_cart_v2', []),
    favorites: loadJSON('jam_favorites', []),
    customer: null,
    promotions: null
};

function emit() {
    document.dispatchEvent(new CustomEvent('jam:change'));
}

/* ---------- Catálogo ---------- */
let catalogPromise = null;

export function loadCatalog(force) {
    if (!catalogPromise || force) {
        catalogPromise = api('/store/catalog').then(function (data) {
            state.products = data.products;
            state.byId = new Map(data.products.map(function (p) { return [p.id_product, p]; }));
            state.stores = data.stores;
            state.onlineStore = data.online_store;
            state.shipping = data.shipping;
            pruneCart();
            return state.products;
        }).catch(function (error) {
            catalogPromise = null;
            throw error;
        });
    }
    return catalogPromise;
}

export function product(id) {
    return state.byId.get(Number(id)) || null;
}

export function findVariant(idVariant) {
    for (const p of state.products) {
        const v = p.variants.find(function (x) { return x.id_variant === Number(idVariant); });
        if (v) return { product: p, variant: v };
    }
    return null;
}

export function colorsOf(p) {
    const seen = new Map();
    p.variants.forEach(function (v) {
        if (!seen.has(v.color_code)) seen.set(v.color_code, { color_code: v.color_code, color: v.color });
    });
    return Array.from(seen.values());
}

export function stockAt(variant, idStore) {
    return Number((variant.stock || {})[idStore] || 0);
}

export function physicalStores() {
    return state.stores.filter(function (s) { return !s.is_online; });
}

export function newestIds(count) {
    return state.products.slice().sort(function (a, b) { return b.id_product - a.id_product; })
        .slice(0, count).map(function (p) { return p.id_product; });
}

/* ---------- Bolsa de compras ---------- */
function pruneCart() {
    const before = state.cart.length;
    state.cart = state.cart.filter(function (line) { return findVariant(line.id_variant); });
    if (state.cart.length !== before) saveCart();
}

function saveCart() {
    saveJSON('jam_cart_v2', state.cart);
    emit();
}

export function addToCart(idVariant, quantity) {
    const line = state.cart.find(function (l) { return l.id_variant === idVariant; });
    if (line) {
        line.quantity = Math.min(10, line.quantity + quantity);
    } else {
        state.cart.push({ id_variant: idVariant, quantity: Math.min(10, quantity) });
    }
    saveCart();
}

export function setQuantity(idVariant, quantity) {
    if (quantity <= 0) {
        removeFromCart(idVariant);
        return;
    }
    const line = state.cart.find(function (l) { return l.id_variant === idVariant; });
    if (line) {
        line.quantity = Math.min(10, quantity);
        saveCart();
    }
}

export function removeFromCart(idVariant) {
    state.cart = state.cart.filter(function (l) { return l.id_variant !== idVariant; });
    saveCart();
}

export function clearCart() {
    state.cart = [];
    saveCart();
}

export function cartLines() {
    return state.cart.map(function (line) {
        const found = findVariant(line.id_variant);
        return found ? {
            id_variant: line.id_variant,
            quantity: line.quantity,
            product: found.product,
            variant: found.variant,
            total: found.product.base_price * line.quantity
        } : null;
    }).filter(Boolean);
}

export function cartCount() {
    return state.cart.reduce(function (sum, l) { return sum + l.quantity; }, 0);
}

export function cartSubtotal() {
    return cartLines().reduce(function (sum, l) { return sum + l.total; }, 0);
}

/* ---------- Favoritos ---------- */
export function isFavorite(idProduct) {
    return state.favorites.indexOf(Number(idProduct)) >= 0;
}

export function toggleFavorite(idProduct) {
    const id = Number(idProduct);
    state.favorites = isFavorite(id)
        ? state.favorites.filter(function (x) { return x !== id; })
        : state.favorites.concat(id);
    saveJSON('jam_favorites', state.favorites);
    emit();
    return isFavorite(id);
}

/* ---------- Cupones ---------- */
export async function activePromotions() {
    if (state.promotions === null) {
        try {
            const today = new Date().toISOString().slice(0, 10);
            state.promotions = (await api('/promotion')).filter(function (p) {
                return Number(p.deleted) !== 1 &&
                    String(p.start_date).slice(0, 10) <= today &&
                    String(p.end_date).slice(0, 10) >= today;
            });
        } catch (e) {
            state.promotions = [];
        }
    }
    return state.promotions;
}

/* ---------- Sesión ---------- */
let customerPromise = null;

export function loadCustomer(force) {
    if (!getToken()) {
        state.customer = null;
        return Promise.resolve(null);
    }
    if (!customerPromise || force) {
        customerPromise = api('/auth/me').then(function (customer) {
            state.customer = customer;
            emit();
            return customer;
        }).catch(function () {
            state.customer = null;
            customerPromise = null;
            return null;
        });
    }
    return customerPromise;
}

function startSession(data) {
    setToken(data.token);
    state.customer = data.customer;
    customerPromise = Promise.resolve(data.customer);
    emit();
    return data.customer;
}

export async function login(email, password) {
    return startSession(await api('/auth/login', { method: 'POST', body: { email: email, password: password } }));
}

export async function register(fields) {
    return startSession(await api('/auth/register', { method: 'POST', body: fields }));
}

export async function logout() {
    try {
        await api('/auth/logout', { method: 'POST', body: {} });
    } catch (e) {
        /* El token se borra de todas formas. */
    }
    setToken(null);
    state.customer = null;
    customerPromise = null;
    emit();
}
