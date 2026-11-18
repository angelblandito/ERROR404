/* Página de producto: colores, tallas, disponibilidad y reseñas. */
import { api } from '../api.js';
import { $, clone, field, fill, show, slot, money, stars, message, toast, emptyState, formatDate, setTitle, sizeLabel, DEPARTMENTS, GENDERS, SEGMENTS } from '../ui.js';
import { productArt, colorClass } from '../illustrations.js';
import { favButton, productRail } from '../components.js';
import { openMiniCart } from '../minicart.js';
import { addToCart, colorsOf, physicalStores, product, state, stockAt } from '../store.js';

function stockLabel(el, text, css) {
    el.textContent = text;
    el.className = css;
}

function showAvailability(page, variant) {
    show(field(page, 'chooseSize'), !variant);
    show(field(page, 'stockInfo'), Boolean(variant));
    if (!variant) return;

    const online = stockAt(variant, state.onlineStore);
    if (online > 0) {
        stockLabel(field(page, 'online'), 'Disponible' + (online <= 5 ? ' (últimas ' + online + ')' : ''), 'ok-text');
    } else {
        stockLabel(field(page, 'online'), 'Agotado en línea', 'bad-text');
    }

    slot(page, 'stores').replaceChildren(...physicalStores().map(function (s) {
        const qty = stockAt(variant, s.id_store);
        const row = fill(clone('tpl-store-stock'), { store: s.store_name });
        if (qty > 0) {
            stockLabel(field(row, 'status'), qty <= 5 ? 'Últimas ' + qty : 'Disponible', 'ok-text');
        } else {
            stockLabel(field(row, 'status'), 'Sin existencias', 'muted');
        }
        return row;
    }));
}

function reviewItem(r) {
    const item = fill(clone('tpl-review'), {
        title: r.title,
        meta: r.author + ' · ' + formatDate(r.created_at),
        comment: r.comment || ''
    });
    slot(item, 'stars').replaceWith(stars(r.rating));
    show(field(item, 'verified'), Boolean(r.verified_purchase));
    show(field(item, 'comment'), Boolean(r.comment));
    return item;
}

function ratingBar(level, count, total) {
    const pct = total ? Math.round(count * 100 / total) : 0;
    const bar = fill(clone('tpl-review-bar'), { label: level + ' estrellas', pct: pct + '%' });
    bar.querySelector('progress').value = pct;
    return bar;
}

function reviewsSection(p, data) {
    const total = data.reviews.length;
    const section = fill(clone('tpl-reviews'), {
        total: total,
        count: total,
        average: p.rating ? p.rating.toFixed(1) : '—'
    });
    slot(section, 'stars').replaceWith(stars(p.rating));
    slot(section, 'bars').append(...[5, 4, 3, 2, 1].map(function (n) {
        return ratingBar(n, data.histogram[n] || 0, total);
    }));
    slot(section, 'list').append(...(total
        ? data.reviews.map(reviewItem)
        : [message('Este producto aún no tiene reseñas.')]));

    if (state.customer) {
        slot(section, 'form').append(clone('tpl-review-form'));
    } else {
        const login = clone('tpl-review-login');
        field(login, 'login').href = '#/cuenta?volver=' + encodeURIComponent('#/producto/' + p.id_product);
        slot(section, 'form').append(login);
    }
    return section;
}

function thumbButton(p, c, active) {
    const button = clone('tpl-thumb');
    button.dataset.color = c.color_code;
    button.setAttribute('aria-label', c.color);
    button.classList.toggle('active', active);
    button.append(productArt(p, c.color_code));
    return button;
}

function swatchButton(c, active) {
    const button = clone('tpl-swatch');
    button.dataset.color = c.color_code;
    button.classList.add('swatch-' + colorClass(c.color_code));
    button.classList.toggle('active', active);
    button.setAttribute('aria-label', c.color);
    button.setAttribute('aria-pressed', String(active));
    return button;
}

function sizeButton(v, active) {
    const button = clone('tpl-size-button');
    const out = v.stock_total <= 0;
    button.dataset.variant = v.id_variant;
    button.textContent = sizeLabel(v.size);
    button.classList.toggle('active', active);
    button.classList.toggle('out', out);
    button.setAttribute('aria-pressed', String(active));
    if (out) {
        button.disabled = true;
        button.title = 'Agotado';
    }
    return button;
}

export function renderProduct(view, id, params) {
    const p = product(id);
    if (!p) {
        view.replaceChildren(emptyState('Producto no encontrado', 'Puede que ya no esté disponible.', { href: '#/tienda', label: 'Ir a la tienda' }));
        return;
    }
    setTitle(p.name);

    const colors = colorsOf(p);
    let color = colors.some(function (c) { return c.color_code === params.color; }) ? params.color : (colors[0] || {}).color_code;
    let selected = null;
    let quantity = 1;

    const variantsOf = function (c) {
        return p.variants.filter(function (v) { return v.color_code === c; });
    };

    const page = clone('tpl-product');
    fill(page, {
        department: DEPARTMENTS[p.department] || '',
        crumb: p.name,
        audience: SEGMENTS[p.age_segment] + ' · ' + GENDERS[p.target_gender],
        name: p.name,
        category: p.category,
        price: money.format(p.base_price),
        description: p.description || '',
        departmentName: DEPARTMENTS[p.department] || '',
        categoryName: p.category,
        forWho: GENDERS[p.target_gender] + ' · ' + SEGMENTS[p.age_segment],
        freeFrom: money.format(state.shipping.free_from),
        shippingCost: money.format(state.shipping.cost)
    });
    field(page, 'department').href = '#/tienda?dep=' + encodeURIComponent(p.department);
    show(field(page, 'freeShipping'), p.base_price >= state.shipping.free_from);
    if (p.reviews) {
        const link = show(field(page, 'ratingLink'), true);
        slot(link, 'stars').replaceWith(stars(p.rating));
        field(link, 'ratingText').textContent = p.rating.toFixed(1) + ' · ' + p.reviews + ' reseñas';
    }
    slot(page, 'fav').replaceWith(favButton(p.id_product, 'pdp-fav'));
    slot(page, 'related').replaceWith(productRail('También te puede interesar', state.products.filter(function (x) {
        return x.department === p.department && x.id_product !== p.id_product;
    }).slice(0, 8)));

    view.replaceChildren(page);

    const draw = function () {
        const variant = p.variants.find(function (x) { return x.id_variant === selected; });
        slot(page, 'main').replaceChildren(productArt(p, color, 'pdp-art'));
        slot(page, 'thumbs').replaceChildren(...colors.map(function (c) { return thumbButton(p, c, c.color_code === color); }));
        slot(page, 'swatches').replaceChildren(...colors.map(function (c) { return swatchButton(c, c.color_code === color); }));
        slot(page, 'sizes').replaceChildren(...variantsOf(color).map(function (v) { return sizeButton(v, v.id_variant === selected); }));
        fill(page, {
            colorName: (colors.find(function (c) { return c.color_code === color; }) || {}).color || '',
            sku: variant ? variant.sku : 'elige una talla',
            qty: quantity
        });
        showAvailability(page, variant);
    };

    const pickColor = function (c) {
        color = c;
        const options = variantsOf(c).filter(function (v) { return v.stock_total > 0; });
        selected = options.length === 1 ? options[0].id_variant : null;
        draw();
    };

    page.addEventListener('click', function (e) {
        const colorBtn = e.target.closest('[data-color]');
        if (colorBtn) {
            pickColor(colorBtn.dataset.color);
            return;
        }
        const sizeBtn = e.target.closest('.size[data-variant]');
        if (sizeBtn && !sizeBtn.disabled) {
            selected = Number(sizeBtn.dataset.variant);
            field(page, 'sizeError').hidden = true;
            draw();
            return;
        }
        const q = e.target.closest('[data-q]');
        if (q) {
            quantity = Math.max(1, Math.min(10, quantity + Number(q.dataset.q)));
            field(page, 'qty').textContent = quantity;
            return;
        }
        if (e.target.closest('[data-action="scroll-reviews"]')) {
            e.preventDefault();
            const reviews = document.getElementById('resenas');
            if (reviews) reviews.scrollIntoView({ behavior: 'smooth' });
        }
    });

    $('[data-action="add"]', page).addEventListener('click', function () {
        if (!selected) {
            field(page, 'sizeError').hidden = false;
            slot(page, 'sizes').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        addToCart(selected, quantity);
        openMiniCart(selected, quantity);
    });

    pickColor(color);
    loadReviews(page, p);
}

async function loadReviews(page, p) {
    const target = slot(page, 'reviews');
    let data;
    try {
        data = await api('/store/products/' + p.id_product + '/reviews');
    } catch (e) {
        target.replaceChildren(message('No se pudieron cargar las reseñas.'));
        return;
    }
    if (!document.body.contains(target)) return;
    target.replaceChildren(reviewsSection(p, data));

    const form = $('.review-form', target);
    if (!form) return;
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(form);
        const button = form.querySelector('button');
        button.disabled = true;
        try {
            await api('/store/reviews', {
                method: 'POST',
                body: { id_product: p.id_product, rating: Number(fd.get('rating')), title: fd.get('title'), comment: fd.get('comment') }
            });
            toast('¡Gracias! Tu reseña se publicó.');
            loadReviews(page, p);
        } catch (err) {
            toast(err.message, { error: true });
            button.disabled = false;
        }
    });
}
