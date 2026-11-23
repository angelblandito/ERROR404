/* Catálogo con filtros y orden. */
import { $, $$, clone, field, fill, slot, buildQuery, emptyState, setTitle, sizeLabel, DEPARTMENTS, GENDERS, SEGMENTS } from '../ui.js';
import { productCard } from '../components.js';
import { colorClass } from '../illustrations.js';
import { outletProducts, salePrice, shopProducts } from '../store.js';
import { matchScore, productText } from '../search.js';
import { trackSearch } from '../analytics.js';

const PRICE_RANGES = {
    lt500: ['Menos de $500', 0, 499.99],
    '500-1000': ['$500 a $1,000', 500, 1000],
    '1000-1500': ['$1,000 a $1,500', 1000.01, 1500],
    gt1500: ['Más de $1,500', 1500.01, Infinity]
};

const RADIO_FILTERS = ['dep', 'genero', 'edad', 'precio', 'talla', 'color'];
const CHECK_FILTERS = ['disp', 'calif'];

function matches(p, f) {
    if (f.dep && p.department !== f.dep) return false;
    if (f.genero && p.target_gender !== f.genero && p.target_gender !== 'U') return false;
    if (f.edad && p.age_segment !== f.edad && p.age_segment !== 'all') return false;
    if (f.precio && PRICE_RANGES[f.precio]) {
        const r = PRICE_RANGES[f.precio];
        if (salePrice(p) < r[1] || salePrice(p) > r[2]) return false;
    }
    if (f.talla && !p.variants.some(function (v) { return v.size === f.talla; })) return false;
    if (f.color && !p.variants.some(function (v) { return v.color_code === f.color; })) return false;
    if (f.disp === '1' && p.stock_total <= 0) return false;
    if (f.calif && p.rating < Number(f.calif)) return false;
    if (f.q && !matchScore(f.q, productText(p, DEPARTMENTS))) return false;
    return true;
}

function sortProducts(list, order) {
    const sorted = list.slice();
    const by = {
        vendidos: function (a, b) { return b.sold - a.sold; },
        calificacion: function (a, b) { return (b.rating - a.rating) || (b.reviews - a.reviews); },
        nuevo: function (a, b) { return b.id_product - a.id_product; },
        'precio-asc': function (a, b) { return salePrice(a) - salePrice(b); },
        'precio-desc': function (a, b) { return salePrice(b) - salePrice(a); },
        destacados: function (a, b) {
            return ((b.stock_total > 0) - (a.stock_total > 0)) || (b.sold * 2 + b.rating) - (a.sold * 2 + a.rating);
        }
    };
    return sorted.sort(by[order] || by.destacados);
}

function pageTitle(f, outlet) {
    if (f.q) return 'Resultados para “' + f.q + '”' + (outlet ? ' en el outlet' : '');
    const parts = [];
    if (f.dep) parts.push(DEPARTMENTS[f.dep]);
    if (f.genero) parts.push(f.genero === 'F' ? 'para mujer' : 'para hombre');
    if (f.edad) parts.push(f.edad === 'youth' ? 'juvenil' : 'adulto');
    if (outlet) return 'Outlet' + (parts.length ? ': ' + parts.join(' ') : '');
    if (!parts.length) return f.orden === 'nuevo' ? 'Novedades' : 'Toda la tienda';
    return parts.join(' ').replace(/^./, function (c) { return c.toUpperCase(); });
}

function availableSizesAndColors(products) {
    const sizes = [];
    const colors = new Map();
    products.forEach(function (p) {
        p.variants.forEach(function (v) {
            if (!sizes.some(function (s) { return s.size === v.size; })) sizes.push({ size: v.size, id: v.id_size });
            colors.set(v.color_code, v.color);
        });
    });
    sizes.sort(function (a, b) { return a.id - b.id; });
    return { sizes: sizes, colors: colors };
}

function sizeChip(size) {
    const chip = clone('tpl-size-chip');
    chip.querySelector('input').value = size;
    chip.querySelector('span').textContent = sizeLabel(size);
    return chip;
}

function colorChip(code, name) {
    const chip = clone('tpl-color-chip');
    chip.title = name;
    chip.querySelector('input').value = code;
    chip.querySelector('.dot').classList.add('swatch-' + colorClass(code));
    chip.querySelector('em').textContent = name;
    return chip;
}

function activeChips(f, base) {
    const labels = {
        dep: DEPARTMENTS[f.dep], genero: GENDERS[f.genero], edad: SEGMENTS[f.edad],
        precio: PRICE_RANGES[f.precio] ? PRICE_RANGES[f.precio][0] : null,
        talla: f.talla ? 'Talla ' + f.talla : null, color: f.color ? 'Color: ' + f.color : null,
        disp: f.disp ? 'Con existencias' : null, calif: f.calif ? f.calif + '★ o más' : null, q: f.q ? '“' + f.q + '”' : null
    };
    return Object.keys(labels).filter(function (k) { return labels[k]; }).map(function (k) {
        const rest = Object.assign({}, f);
        delete rest[k];
        const chip = clone('tpl-chip-remove');
        chip.href = base + buildQuery(rest);
        chip.textContent = labels[k] + ' ✕';
        chip.setAttribute('aria-label', 'Quitar filtro ' + labels[k]);
        return chip;
    });
}

/* mode = 'outlet' muestra solo los productos de outlet en #/outlet; la tienda normal no los incluye. */
export function renderCatalog(view, params, mode) {
    const f = Object.assign({}, params);
    const outlet = mode === 'outlet';
    const base = outlet ? '#/outlet' : '#/tienda';
    const source = outlet ? outletProducts() : shopProducts();
    let list = sortProducts(source.filter(function (p) { return matches(p, f); }), f.orden);
    if (f.q && !f.orden) {
        const score = new Map(list.map(function (p) { return [p.id_product, matchScore(f.q, productText(p, DEPARTMENTS))]; }));
        list = list.slice().sort(function (a, b) { return score.get(b.id_product) - score.get(a.id_product); });
    }
    if (f.q) trackSearch(f.q, list.length);
    const title = pageTitle(f, outlet);
    const options = availableSizesAndColors(source);
    const page = clone('tpl-catalog');
    const form = $('.filters', page);
    const panel = $('.plp-filters', page);
    setTitle(title);

    fill(page, { title: title, count: '(' + list.length + ')' });
    if (outlet) {
        field(page, 'outletStrip').hidden = false;
        fill(page, { outletStripPercent: Math.max.apply(null, source.map(function (p) { return p.outlet_percent; }).concat([0])) + ' %' });
        page.classList.add('plp-outlet');
    }
    slot(page, 'chips').append(...activeChips(f, base));
    slot(page, 'sizes').append(...options.sizes.map(function (s) { return sizeChip(s.size); }));
    slot(page, 'colors').append(...Array.from(options.colors.entries()).map(function (c) { return colorChip(c[0], c[1]); }));
    slot(page, 'grid').append(...(list.length
        ? list.map(productCard)
        : [emptyState('Sin resultados', 'No encontramos productos con esos filtros.', { href: base, label: outlet ? 'Ver todo el outlet' : 'Ver toda la tienda' })]));

    $('select[name="orden"]', page).value = f.orden || 'destacados';
    RADIO_FILTERS.forEach(function (name) {
        const input = form.querySelector('input[name="' + name + '"][value="' + CSS.escape(f[name] || '') + '"]');
        if (input) input.checked = true;
    });
    CHECK_FILTERS.forEach(function (name) {
        form.elements[name].checked = form.elements[name].value === f[name];
    });

    view.replaceChildren(page);

    const go = function (next) {
        window.location.replace(base + buildQuery(next));
    };

    $('select[name="orden"]', page).addEventListener('change', function (e) {
        go(Object.assign({}, f, { orden: e.target.value === 'destacados' ? '' : e.target.value }));
    });

    form.addEventListener('change', function (e) {
        const input = e.target;
        const next = Object.assign({}, f);
        next[input.name] = input.type === 'checkbox' ? (input.checked ? input.value : '') : input.value;
        go(next);
    });

    $('[data-action="toggle-filters"]', page).addEventListener('click', function (e) {
        const open = !panel.classList.contains('open');
        panel.classList.toggle('open', open);
        e.currentTarget.setAttribute('aria-expanded', String(open));
    });

    $$('.size-chip input, .color-chip input', page).forEach(function (input) {
        input.addEventListener('click', function () {
            if (f[input.name] === input.value) go(Object.assign({}, f, { [input.name]: '' }));
        });
    });
}

/* Al entrar al outlet aparece el aviso del descuento y de que no hay cambios ni devoluciones. */
export function renderOutlet(view, params, showNotice) {
    renderCatalog(view, params, 'outlet');
    if (!showNotice) return;
    const percent = Math.max.apply(null, outletProducts().map(function (p) { return p.outlet_percent; }).concat([0]));
    const dialog = fill(clone('tpl-outlet-notice'), { percent: percent + ' %' });
    dialog.addEventListener('close', function () { dialog.remove(); });
    document.body.append(dialog);
    dialog.showModal();
}
