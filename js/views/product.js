/* Página de producto: colores, tallas, disponibilidad y reseñas. */
import { api } from '../api.js';
import { $, $$, esc, money, stars, toast, emptyState, formatDate, setTitle, DEPARTMENTS, GENDERS, SEGMENTS } from '../ui.js';
import { productArt, swatch } from '../illustrations.js';
import { favButton, productRail } from '../components.js';
import { addToCart, colorsOf, physicalStores, product, state, stockAt } from '../store.js';

function availabilityHtml(variant) {
    if (!variant) {
        return '<p class="muted">Elige una talla para ver la disponibilidad.</p>';
    }
    const online = stockAt(variant, state.onlineStore);
    const rows = physicalStores().map(function (s) {
        const qty = stockAt(variant, s.id_store);
        return '<li><span>' + esc(s.store_name) + '</span>' +
            (qty > 0 ? '<span class="ok-text">' + (qty <= 5 ? 'Últimas ' + qty : 'Disponible') + '</span>' : '<span class="muted">Sin existencias</span>') + '</li>';
    }).join('');
    return '<div class="availability">' +
        '<p><strong>Envío a domicilio:</strong> ' + (online > 0
            ? '<span class="ok-text">Disponible' + (online <= 5 ? ' (últimas ' + online + ')' : '') + '</span>'
            : '<span class="bad-text">Agotado en línea</span>') + '</p>' +
        '<p><strong>Recoger en sucursal:</strong></p><ul>' + rows + '</ul>' +
    '</div>';
}

function reviewsSection(p, data) {
    const total = data.reviews.length;
    const bars = [5, 4, 3, 2, 1].map(function (n) {
        const count = data.histogram[n] || 0;
        const pct = total ? Math.round(count * 100 / total) : 0;
        return '<div class="bar-row"><span>' + n + ' estrellas</span><div class="bar"><i style="width:' + pct + '%"></i></div><span>' + pct + '%</span></div>';
    }).join('');

    const list = data.reviews.map(function (r) {
        return '<article class="review">' +
            '<div class="review-head">' + stars(r.rating) + '<strong>' + esc(r.title) + '</strong></div>' +
            '<p class="review-meta">' + esc(r.author) + ' · ' + esc(formatDate(r.created_at)) +
                (r.verified_purchase ? ' · <span class="verified">Compra verificada</span>' : '') + '</p>' +
            (r.comment ? '<p>' + esc(r.comment) + '</p>' : '') +
        '</article>';
    }).join('');

    const form = state.customer
        ? '<form class="review-form" id="reviewForm">' +
            '<h3>Escribe una reseña</h3>' +
            '<div class="rate-input" role="radiogroup" aria-label="Calificación">' +
                [1, 2, 3, 4, 5].map(function (n) {
                    return '<label><input type="radio" name="rating" value="' + n + '"' + (n === 5 ? ' checked' : '') + '><span>' + n + '★</span></label>';
                }).join('') +
            '</div>' +
            '<label class="field"><span>Título</span><input class="input" name="title" maxlength="80" required></label>' +
            '<label class="field"><span>Comentario</span><textarea class="input" name="comment" rows="3" maxlength="1000"></textarea></label>' +
            '<button class="btn btn-dark">Publicar reseña</button>' +
          '</form>'
        : '<p class="muted"><a href="#/cuenta?volver=' + encodeURIComponent('#/producto/' + p.id_product) + '">Inicia sesión</a> para escribir una reseña.</p>';

    return '<section class="reviews" id="reseñas">' +
        '<h2>Reseñas (' + total + ')</h2>' +
        '<div class="reviews-grid">' +
            '<div class="reviews-summary">' +
                '<p class="big-rating">' + (p.rating ? p.rating.toFixed(1) : '—') + '</p>' + stars(p.rating) +
                '<p class="muted">' + total + ' calificaciones</p>' + bars + form +
            '</div>' +
            '<div class="reviews-list">' + (list || '<p class="muted">Este producto aún no tiene reseñas.</p>') + '</div>' +
        '</div>' +
    '</section>';
}

export function renderProduct(view, id, params) {
    const p = product(id);
    if (!p) {
        view.innerHTML = emptyState('Producto no encontrado', 'Puede que ya no esté disponible.', { href: '#/tienda', label: 'Ir a la tienda' });
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

    view.innerHTML = '<nav class="breadcrumb" aria-label="Ruta">' +
            '<a href="#/">Inicio</a> / <a href="#/tienda?dep=' + esc(p.department) + '">' + esc(DEPARTMENTS[p.department] || '') + '</a> / ' +
            '<span>' + esc(p.name) + '</span>' +
        '</nav>' +
        '<div class="pdp">' +
            '<div class="pdp-gallery">' +
                '<div class="pdp-main" id="pdpMain"></div>' +
                '<div class="pdp-thumbs" id="pdpThumbs"></div>' +
            '</div>' +
            '<div class="pdp-info">' +
                '<p class="eyebrow-dark">' + esc(SEGMENTS[p.age_segment] + ' · ' + GENDERS[p.target_gender]) + '</p>' +
                '<h1>' + esc(p.name) + '</h1>' +
                '<p class="pdp-sub">' + esc(p.category) + '</p>' +
                (p.reviews ? '<a class="pdp-rating" href="#/producto/' + p.id_product + '" data-scroll="reseñas">' + stars(p.rating) +
                    '<span>' + p.rating.toFixed(1) + ' · ' + p.reviews + ' reseñas</span></a>' : '') +
                '<p class="pdp-price">' + money.format(p.base_price) + '</p>' +
                (p.base_price >= state.shipping.free_from ? '<p class="ok-text small">Envío gratis</p>' : '') +
                '<div class="pdp-block"><p class="label">Color: <strong id="colorName"></strong></p><div class="swatches" id="swatches"></div></div>' +
                '<div class="pdp-block"><p class="label">Talla</p><div class="sizes" id="sizes"></div><p class="field-error" id="sizeError" hidden>Selecciona una talla.</p></div>' +
                '<div class="pdp-actions">' +
                    '<div class="qty" aria-label="Cantidad"><button type="button" data-q="-1" aria-label="Menos">−</button><span id="qty">1</span><button type="button" data-q="1" aria-label="Más">+</button></div>' +
                    '<button class="btn btn-dark btn-lg" id="addBtn">Agregar a la bolsa</button>' +
                    favButton(p.id_product, 'pdp-fav') +
                '</div>' +
                '<div id="availability"></div>' +
                '<details class="acc" open><summary>Descripción</summary><p>' + esc(p.description || '') + '</p></details>' +
                '<details class="acc"><summary>Detalles</summary><ul>' +
                    '<li>Departamento: ' + esc(DEPARTMENTS[p.department] || '') + '</li>' +
                    '<li>Categoría: ' + esc(p.category) + '</li>' +
                    '<li>Para: ' + esc(GENDERS[p.target_gender]) + ' · ' + esc(SEGMENTS[p.age_segment]) + '</li>' +
                    '<li>SKU: <span id="skuText">elige una talla</span></li>' +
                '</ul></details>' +
                '<details class="acc"><summary>Envíos y devoluciones</summary><p>Envío gratis desde ' + money.format(state.shipping.free_from) +
                    '; si no, cuesta ' + money.format(state.shipping.cost) + '. Recoger en sucursal no tiene costo. Tienes 30 días para cambios y devoluciones.</p></details>' +
            '</div>' +
        '</div>' +
        '<div id="reviewsSlot">' + '<p class="muted">Cargando reseñas…</p>' + '</div>' +
        productRail('También te puede interesar', state.products.filter(function (x) {
            return x.department === p.department && x.id_product !== p.id_product;
        }).slice(0, 8));

    const draw = function () {
        $('#pdpMain', view).innerHTML = productArt(p, color, 'pdp-art');
        $('#pdpThumbs', view).innerHTML = colors.map(function (c) {
            return '<button class="thumb' + (c.color_code === color ? ' active' : '') + '" data-color="' + esc(c.color_code) + '" aria-label="' + esc(c.color) + '">' +
                productArt(p, c.color_code) + '</button>';
        }).join('');
        $('#colorName', view).textContent = (colors.find(function (c) { return c.color_code === color; }) || {}).color || '';
        $('#swatches', view).innerHTML = colors.map(function (c) {
            return '<button class="swatch' + (c.color_code === color ? ' active' : '') + '" data-color="' + esc(c.color_code) + '" style="--sw:' + swatch(c.color_code) +
                '" aria-label="' + esc(c.color) + '" aria-pressed="' + (c.color_code === color) + '"></button>';
        }).join('');
        $('#sizes', view).innerHTML = variantsOf(color).map(function (v) {
            const out = v.stock_total <= 0;
            return '<button class="size' + (selected === v.id_variant ? ' active' : '') + (out ? ' out' : '') + '" data-variant="' + v.id_variant + '"' +
                (out ? ' disabled aria-disabled="true" title="Agotado"' : '') + ' aria-pressed="' + (selected === v.id_variant) + '">' +
                esc(v.size === 'UNI' ? 'Unitalla' : v.size) + '</button>';
        }).join('');
        const v = p.variants.find(function (x) { return x.id_variant === selected; });
        $('#availability', view).innerHTML = availabilityHtml(v);
        $('#skuText', view).textContent = v ? v.sku : 'elige una talla';
        $('#qty', view).textContent = quantity;
    };

    const pickColor = function (c) {
        color = c;
        const options = variantsOf(c).filter(function (v) { return v.stock_total > 0; });
        selected = options.length === 1 ? options[0].id_variant : null;
        draw();
    };

    view.addEventListener('click', function (e) {
        const colorBtn = e.target.closest('[data-color]');
        if (colorBtn && view.contains(colorBtn)) {
            pickColor(colorBtn.dataset.color);
            return;
        }
        const sizeBtn = e.target.closest('.size[data-variant]');
        if (sizeBtn && !sizeBtn.disabled) {
            selected = Number(sizeBtn.dataset.variant);
            $('#sizeError', view).hidden = true;
            draw();
            return;
        }
        const q = e.target.closest('[data-q]');
        if (q) {
            quantity = Math.max(1, Math.min(10, quantity + Number(q.dataset.q)));
            $('#qty', view).textContent = quantity;
            return;
        }
        const scroll = e.target.closest('[data-scroll]');
        if (scroll) {
            e.preventDefault();
            document.getElementById(scroll.dataset.scroll).scrollIntoView({ behavior: 'smooth' });
        }
    });

    $('#addBtn', view).addEventListener('click', function () {
        if (!selected) {
            $('#sizeError', view).hidden = false;
            $('#sizes', view).scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        addToCart(selected, quantity);
        toast(p.name + ' se agregó a tu bolsa', { action: { href: '#/carrito', label: 'Ver bolsa' } });
    });

    pickColor(color);
    loadReviews(view, p);
}

async function loadReviews(view, p) {
    const slot = $('#reviewsSlot', view);
    let data;
    try {
        data = await api('/store/products/' + p.id_product + '/reviews');
    } catch (e) {
        slot.innerHTML = '<p class="muted">No se pudieron cargar las reseñas.</p>';
        return;
    }
    if (!document.body.contains(slot)) return;
    slot.innerHTML = reviewsSection(p, data);

    const form = $('#reviewForm', slot);
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
            loadReviews(view, p);
        } catch (err) {
            toast(err.message, { error: true });
            button.disabled = false;
        }
    });
}
