/* Piezas de interfaz reutilizadas por varias vistas. */
import { clone, field, fill, show, slot, money, badge, stars, DEPARTMENTS, GENDERS } from './ui.js';
import { productArt } from './illustrations.js';
import { account, colorsOf, isFavorite, isOutlet, isPriority, memberPrice, newestIds, salePrice, state } from './store.js';

const TIER_BADGES = {
    standard: ['Estándar', ''],
    priority: ['Prioritario', 'gold'],
    admin: ['Administrador', 'info']
};

export function tierBadge(tier) {
    const info = TIER_BADGES[tier] || [tier, ''];
    return badge(info[0], info[1]);
}

/* Lista de beneficios de un tipo de cuenta, armada con sus valores de account_tier. */
export function benefitItems(acc) {
    const texts = [];
    if (acc.discount_percent > 0) {
        texts.push(acc.discount_percent + ' % de descuento permanente en toda la tienda');
    }
    texts.push(acc.free_shipping_from > 0
        ? 'Envío gratis desde ' + money.format(acc.free_shipping_from) + ' · llega en ' + acc.delivery_days
        : 'Envío gratis y prioritario en todos tus pedidos · llega en ' + acc.delivery_days);
    texts.push('Hasta ' + acc.max_units_per_item + ' piezas por artículo' + (acc.max_units_per_item > 10 ? ' (compras de mayoreo)' : ''));
    texts.push(acc.support_minutes < 60
        ? 'Atención inmediata de soporte: respuesta en menos de ' + acc.support_minutes + ' minutos'
        : 'Soporte con respuesta en menos de ' + Math.round(acc.support_minutes / 60) + ' horas');
    if (acc.tier !== 'standard') {
        texts.push('Comunicados y preventas exclusivas');
    }
    if (acc.can_manage_store) {
        texts.push('Administración de la tienda: pedidos, clientes, precios, inventario, soporte y comunicados');
    }
    return texts.map(function (text) {
        const li = clone('tpl-benefit');
        li.textContent = text;
        return li;
    });
}

function announcementCard(a) {
    const card = fill(clone('tpl-announcement'), { title: a.title, body: a.body });
    card.classList.toggle('exclusive', a.audience === 'priority');
    slot(card, 'badge').replaceWith(a.audience === 'priority' ? badge('Exclusivo Prioritario', 'gold') : badge('Aviso', 'info'));
    return card;
}

export function announcementsSection(list, title) {
    const section = fill(clone('tpl-announcements'), { title: title });
    slot(section, 'items').append(...list.map(announcementCard));
    section.hidden = !list.length;
    return section;
}

export function showMemberPrice(el, price) {
    show(el, isPriority());
    if (isPriority()) {
        el.textContent = account().name + ' ' + money.format(memberPrice(price));
    }
}

export function setFavState(button, active) {
    button.classList.toggle('active', active);
    button.setAttribute('aria-pressed', String(active));
    button.setAttribute('aria-label', active ? 'Quitar de favoritos' : 'Agregar a favoritos');
}

export function favButton(idProduct, extraClass) {
    const button = clone('tpl-fav-button');
    button.dataset.fav = idProduct;
    if (extraClass) button.classList.add(extraClass);
    setFavState(button, isFavorite(idProduct));
    return button;
}

function topSellerIds() {
    return state.products.filter(function (p) { return p.sold > 0 && !isOutlet(p); })
        .sort(function (a, b) { return b.sold - a.sold; })
        .slice(0, 3).map(function (p) { return p.id_product; });
}

export function productTag(p) {
    if (p.stock_total <= 0) return { text: 'Agotado', css: 'tag-muted' };
    if (isOutlet(p)) return { text: 'Outlet −' + p.outlet_percent + ' %', css: 'tag-outlet' };
    if (topSellerIds().indexOf(p.id_product) >= 0) return { text: 'Más vendido', css: 'tag-hot' };
    if (newestIds(4).indexOf(p.id_product) >= 0) return { text: 'Novedad', css: 'tag-new' };
    if (p.stock_total <= 10) return { text: 'Últimas piezas', css: 'tag-hot' };
    return null;
}

export function productCard(p) {
    const card = clone('tpl-product-card');
    const colors = colorsOf(p);
    const tag = productTag(p);
    const href = '#/producto/' + p.id_product;

    const media = field(card, 'media');
    media.href = href;
    media.append(productArt(p, colors[0] ? colors[0].color_code : 'black'));
    media.after(favButton(p.id_product, 'on-card'));

    if (tag) {
        const tagEl = show(field(card, 'tag'), true);
        tagEl.textContent = tag.text;
        tagEl.classList.add(tag.css);
    }

    field(card, 'name').href = href;
    fill(card, {
        name: p.name,
        sub: (DEPARTMENTS[p.department] || p.category) + ' · ' + (GENDERS[p.target_gender] || ''),
        colors: colors.length + (colors.length === 1 ? ' color' : ' colores'),
        price: money.format(p.base_price)
    });
    if (isOutlet(p)) {
        field(card, 'price').classList.add('was');
        const outlet = show(field(card, 'memberPrice'), true);
        outlet.className = 'outlet-price';
        outlet.textContent = money.format(salePrice(p));
    } else {
        field(card, 'price').classList.toggle('was', isPriority());
        showMemberPrice(field(card, 'memberPrice'), p.base_price);
    }

    if (p.reviews) {
        show(field(card, 'rating'), true);
        slot(card, 'stars').replaceWith(stars(p.rating));
        field(card, 'reviews').textContent = '(' + p.reviews + ')';
    }
    return card;
}

export function productRail(title, products, link) {
    const rail = clone('tpl-rail');
    if (!products.length) {
        rail.hidden = true;
        return rail;
    }
    field(rail, 'title').textContent = title;
    if (link) {
        const more = show(field(rail, 'link'), true);
        more.href = link.href;
        more.textContent = link.label;
    }
    slot(rail, 'items').append(...products.map(productCard));
    return rail;
}
