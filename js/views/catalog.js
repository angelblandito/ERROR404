/* Catálogo con filtros laterales y orden (estilo tienda deportiva). */
import { $, $$, esc, buildQuery, emptyState, setTitle, DEPARTMENTS, GENDERS, SEGMENTS } from '../ui.js';
import { productCard } from '../components.js';
import { swatch } from '../illustrations.js';
import { state } from '../store.js';

const PRICE_RANGES = {
    lt500: ['Menos de $500', 0, 499.99],
    '500-1000': ['$500 a $1,000', 500, 1000],
    '1000-1500': ['$1,000 a $1,500', 1000.01, 1500],
    gt1500: ['Más de $1,500', 1500.01, Infinity]
};

const SORTS = {
    destacados: 'Destacados',
    vendidos: 'Más vendidos',
    calificacion: 'Mejor calificados',
    nuevo: 'Novedades',
    'precio-asc': 'Precio: menor a mayor',
    'precio-desc': 'Precio: mayor a menor'
};

function normalize(text) {
    return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

function matches(p, f) {
    if (f.dep && p.department !== f.dep) return false;
    if (f.genero && p.target_gender !== f.genero && p.target_gender !== 'U') return false;
    if (f.edad && p.age_segment !== f.edad && p.age_segment !== 'all') return false;
    if (f.precio && PRICE_RANGES[f.precio]) {
        const r = PRICE_RANGES[f.precio];
        if (p.base_price < r[1] || p.base_price > r[2]) return false;
    }
    if (f.talla && !p.variants.some(function (v) { return v.size === f.talla; })) return false;
    if (f.color && !p.variants.some(function (v) { return v.color_code === f.color; })) return false;
    if (f.disp === '1' && p.stock_total <= 0) return false;
    if (f.calif && p.rating < Number(f.calif)) return false;
    if (f.q) {
        const text = normalize(p.name + ' ' + p.description + ' ' + p.category + ' ' + (DEPARTMENTS[p.department] || ''));
        if (!normalize(f.q).split(/\s+/).every(function (w) { return text.indexOf(w) >= 0; })) return false;
    }
    return true;
}

function sortProducts(list, order) {
    const sorted = list.slice();
    const by = {
        vendidos: function (a, b) { return b.sold - a.sold; },
        calificacion: function (a, b) { return (b.rating - a.rating) || (b.reviews - a.reviews); },
        nuevo: function (a, b) { return b.id_product - a.id_product; },
        'precio-asc': function (a, b) { return a.base_price - b.base_price; },
        'precio-desc': function (a, b) { return b.base_price - a.base_price; },
        destacados: function (a, b) {
            return ((b.stock_total > 0) - (a.stock_total > 0)) || (b.sold * 2 + b.rating) - (a.sold * 2 + a.rating);
        }
    };
    return sorted.sort(by[order] || by.destacados);
}

function pageTitle(f) {
    if (f.q) return 'Resultados para “' + f.q + '”';
    const parts = [];
    if (f.dep) parts.push(DEPARTMENTS[f.dep]);
    if (f.genero) parts.push(f.genero === 'F' ? 'para mujer' : 'para hombre');
    if (f.edad) parts.push(f.edad === 'youth' ? 'juvenil' : 'adulto');
    if (!parts.length) return f.orden === 'nuevo' ? 'Novedades' : 'Toda la tienda';
    return parts.join(' ').replace(/^./, function (c) { return c.toUpperCase(); });
}

function radioGroup(name, label, options, current) {
    return '<fieldset class="filter"><legend>' + esc(label) + '</legend>' +
        '<label class="opt"><input type="radio" name="' + name + '" value=""' + (!current ? ' checked' : '') + '> Todos</label>' +
        Object.keys(options).map(function (k) {
            return '<label class="opt"><input type="radio" name="' + name + '" value="' + esc(k) + '"' +
                (current === k ? ' checked' : '') + '> ' + esc(options[k]) + '</label>';
        }).join('') +
    '</fieldset>';
}

function filtersHtml(f) {
    const sizes = [];
    const colors = new Map();
    state.products.forEach(function (p) {
        p.variants.forEach(function (v) {
            if (!sizes.some(function (s) { return s.size === v.size; })) sizes.push({ size: v.size, id: v.id_size });
            colors.set(v.color_code, v.color);
        });
    });
    sizes.sort(function (a, b) { return a.id - b.id; });

    return '<form class="filters" id="filterForm">' +
        radioGroup('dep', 'Departamento', DEPARTMENTS, f.dep) +
        radioGroup('genero', 'Género', { F: 'Mujer', M: 'Hombre' }, f.genero) +
        radioGroup('edad', 'Edad', { youth: SEGMENTS.youth, adult: SEGMENTS.adult }, f.edad) +
        radioGroup('precio', 'Precio', Object.fromEntries(Object.entries(PRICE_RANGES).map(function (e) { return [e[0], e[1][0]]; })), f.precio) +
        '<fieldset class="filter"><legend>Talla</legend><div class="size-chips">' +
            sizes.map(function (s) {
                return '<label class="size-chip"><input type="radio" name="talla" value="' + esc(s.size) + '"' +
                    (f.talla === s.size ? ' checked' : '') + '><span>' + esc(s.size === 'UNI' ? 'Unitalla' : s.size) + '</span></label>';
            }).join('') +
        '</div></fieldset>' +
        '<fieldset class="filter"><legend>Color</legend><div class="color-chips">' +
            Array.from(colors.entries()).map(function (c) {
                return '<label class="color-chip" title="' + esc(c[1]) + '"><input type="radio" name="color" value="' + esc(c[0]) + '"' +
                    (f.color === c[0] ? ' checked' : '') + '><span style="background:' + swatch(c[0]) + '"></span><em>' + esc(c[1]) + '</em></label>';
            }).join('') +
        '</div></fieldset>' +
        '<fieldset class="filter"><legend>Más filtros</legend>' +
            '<label class="opt"><input type="checkbox" name="disp" value="1"' + (f.disp === '1' ? ' checked' : '') + '> Solo con existencias</label>' +
            '<label class="opt"><input type="checkbox" name="calif" value="4"' + (f.calif === '4' ? ' checked' : '') + '> 4 estrellas o más</label>' +
        '</fieldset>' +
        '<a class="btn btn-ghost btn-block" href="#/tienda">Limpiar filtros</a>' +
    '</form>';
}

function activeChips(f) {
    const labels = {
        dep: DEPARTMENTS[f.dep], genero: GENDERS[f.genero], edad: SEGMENTS[f.edad],
        precio: PRICE_RANGES[f.precio] ? PRICE_RANGES[f.precio][0] : null,
        talla: f.talla ? 'Talla ' + f.talla : null, color: f.color ? 'Color: ' + f.color : null,
        disp: f.disp ? 'Con existencias' : null, calif: f.calif ? f.calif + '★ o más' : null, q: f.q ? '“' + f.q + '”' : null
    };
    return Object.keys(labels).filter(function (k) { return labels[k]; }).map(function (k) {
        const rest = Object.assign({}, f);
        delete rest[k];
        return '<a class="chip-remove" href="#/tienda' + buildQuery(rest) + '" aria-label="Quitar filtro ' + esc(labels[k]) + '">' + esc(labels[k]) + ' ✕</a>';
    }).join('');
}

export function renderCatalog(view, params) {
    const f = Object.assign({}, params);
    const list = sortProducts(state.products.filter(function (p) { return matches(p, f); }), f.orden);
    const title = pageTitle(f);
    setTitle(title);

    view.innerHTML = '<div class="plp">' +
        '<div class="plp-head">' +
            '<div><h1>' + esc(title) + ' <span class="muted">(' + list.length + ')</span></h1>' +
            '<div class="active-filters">' + activeChips(f) + '</div></div>' +
            '<div class="plp-tools">' +
                '<button class="btn btn-ghost filters-toggle" id="filtersToggle" aria-expanded="false" aria-controls="filtersPanel">Filtros</button>' +
                '<label class="sort">Ordenar por <select id="sortSelect">' +
                    Object.keys(SORTS).map(function (k) {
                        return '<option value="' + k + '"' + ((f.orden || 'destacados') === k ? ' selected' : '') + '>' + SORTS[k] + '</option>';
                    }).join('') +
                '</select></label>' +
            '</div>' +
        '</div>' +
        '<div class="plp-body">' +
            '<aside class="plp-filters" id="filtersPanel">' + filtersHtml(f) + '</aside>' +
            '<div class="product-grid">' + (list.length
                ? list.map(productCard).join('')
                : emptyState('Sin resultados', 'No encontramos productos con esos filtros.', { href: '#/tienda', label: 'Ver toda la tienda' })) +
            '</div>' +
        '</div>' +
    '</div>';

    const go = function (next) {
        window.location.replace('#/tienda' + buildQuery(next));
    };

    $('#sortSelect', view).addEventListener('change', function (e) {
        go(Object.assign({}, f, { orden: e.target.value === 'destacados' ? '' : e.target.value }));
    });

    $('#filterForm', view).addEventListener('change', function (e) {
        const input = e.target;
        const next = Object.assign({}, f);
        next[input.name] = input.type === 'checkbox' ? (input.checked ? input.value : '') : input.value;
        go(next);
    });

    $('#filtersToggle', view).addEventListener('click', function (e) {
        const panel = $('#filtersPanel', view);
        const open = !panel.classList.contains('open');
        panel.classList.toggle('open', open);
        e.currentTarget.setAttribute('aria-expanded', String(open));
    });

    $$('.size-chip input, .color-chip input', view).forEach(function (input) {
        input.addEventListener('click', function () {
            if (f[input.name] === input.value) go(Object.assign({}, f, { [input.name]: '' }));
        });
    });
}
