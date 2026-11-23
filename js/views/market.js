/* Comparador de mercado: nuestros precios contra la competencia (Prioritario y Administrador). */
import { api } from '../api.js';
import { $$, badge, clone, field, fill, slot, money, message, loading, formatDate, setTitle } from '../ui.js';
import { chartEmpty, compactMoney, groupedBars, priceRanges } from '../charts.js';
import { isPriority, state } from '../store.js';
import { authPanel } from './account.js';

const SERIES = {
    ours: { name: 'Precio de lista JAM', css: 'series-1' },
    member: { name: 'Tu precio', css: 'series-3' },
    market: { name: 'Competencia', css: 'series-2' }
};

const FILTERS = [['all', 'Todo'], ['footwear', 'Calzado'], ['apparel', 'Ropa'], ['accessories', 'Accesorios']];

function percentText(value) {
    return Math.abs(value).toFixed(1).replace('.0', '') + ' %';
}

function average(list, key) {
    return list.length ? list.reduce(function (sum, x) { return sum + x[key]; }, 0) / list.length : 0;
}

function savingsBadge(pct) {
    if (pct > 0) return badge(percentText(pct) + ' menos', 'ok');
    if (pct < 0) return badge(percentText(pct) + ' más', 'bad');
    return badge('Igual');
}

/* Promedios por categoría calculados con los productos visibles. */
function categoryGroups(products) {
    const groups = new Map();
    products.forEach(function (p) {
        if (!groups.has(p.category_code)) groups.set(p.category_code, { label: p.category, items: [] });
        groups.get(p.category_code).items.push(p);
    });
    return Array.from(groups.values()).map(function (g) {
        const member = average(g.items, 'member_price');
        const market = average(g.items, 'market_average');
        const pct = market ? (market - member) / market * 100 : 0;
        return {
            label: g.label,
            note: g.items.length + (g.items.length === 1 ? ' producto' : ' productos') + ' · ahorras ' + Math.round(pct) + ' % con tu precio',
            values: [average(g.items, 'our_price'), member, market]
        };
    });
}

function offerItem(o) {
    const item = fill(clone('tpl-market-offer'), {
        name: o.competitor + ' · ' + o.competitor_product,
        where: '(' + o.retailer + ')',
        price: money.format(o.price)
    });
    const link = field(item, 'name');
    if (o.source_url) {
        link.href = o.source_url;
    } else {
        link.removeAttribute('href');
    }
    return item;
}

function tableRow(p) {
    const row = fill(clone('tpl-market-row'), {
        name: p.name,
        category: p.category,
        ours: money.format(p.our_price),
        member: money.format(p.member_price),
        average: money.format(p.market_average)
    });
    field(row, 'name').href = '#/producto/' + p.id_product;
    slot(row, 'offers').append(...p.offers.map(offerItem));
    slot(row, 'savings').append(savingsBadge(p.member_savings_percent));
    return row;
}

function rangeRows(products) {
    return products.map(function (p) {
        const points = p.offers.map(function (o) {
            return { value: o.price, css: SERIES.market.css, offer: o };
        }).concat([
            { value: p.our_price, css: SERIES.ours.css },
            { value: p.member_price, css: SERIES.member.css }
        ]);
        return {
            label: p.name,
            href: '#/producto/' + p.id_product,
            min: Math.min(p.market_min, p.member_price),
            max: Math.max(p.market_max, p.our_price),
            points: points,
            tip: function (point) {
                if (point.offer) {
                    return [
                        { text: point.offer.competitor + ' · ' + point.offer.competitor_product, value: money.format(point.offer.price), swatch: SERIES.market.css },
                        { text: 'Visto en ' + point.offer.retailer + ' el ' + formatDate(point.offer.captured_on) }
                    ];
                }
                return [
                    { text: SERIES.ours.name, value: money.format(p.our_price), swatch: SERIES.ours.css },
                    { text: SERIES.member.name, value: money.format(p.member_price), swatch: SERIES.member.css },
                    { text: 'Promedio competencia', value: money.format(p.market_average), swatch: SERIES.market.css }
                ];
            }
        };
    });
}

function draw(view, data, department) {
    const products = data.products.filter(function (p) { return department === 'all' || p.department === department; });
    const offers = products.reduce(function (n, p) { return n + p.offers.length; }, 0);
    const cheapest = products.filter(function (p) { return p.member_price < p.market_min; }).length;
    const memberSavings = average(products, 'member_savings_percent');
    const listSavings = average(products, 'savings_percent');

    fill(view, {
        memberSavings: products.length ? Math.round(memberSavings) + ' %' : '—',
        listSavings: products.length ? Math.round(listSavings) + ' %' : '—',
        count: products.length,
        offers: offers + ' precios de ' + new Set(products.flatMap(function (p) { return p.offers.map(function (o) { return o.competitor; }); })).size + ' marcas',
        cheapest: cheapest + ' de ' + products.length
    });

    const format = function (v) { return money.format(v); };
    slot(view, 'byCategory').replaceChildren(products.length
        ? groupedBars(categoryGroups(products), [SERIES.ours, SERIES.member, SERIES.market], { format: format, label: 'Precio promedio por tipo de producto' })
        : chartEmpty('No hay productos comparados en este departamento.'));
    slot(view, 'ranges').replaceChildren(products.length
        ? priceRanges(rangeRows(products), [SERIES.ours, SERIES.member, SERIES.market], { format: compactMoney, label: 'Precio de cada producto contra la competencia' })
        : chartEmpty('Sin datos.'));
    slot(view, 'rows').replaceChildren(...products.map(tableRow));
}

export async function renderMarket(page) {
    setTitle('Comparador de mercado');
    if (!state.customer) {
        page.replaceChildren(authPanel('Comparador de mercado', 'Inicia sesión con tu cuenta Prioritario para comparar precios.', function () {
            renderMarket(page);
        }));
        return;
    }
    if (!isPriority()) {
        page.replaceChildren(clone('tpl-market-locked'));
        return;
    }

    page.replaceChildren(loading('Cargando precios del mercado…'));
    let data;
    try {
        data = await api('/market');
    } catch (err) {
        page.replaceChildren(message(err.message, 'bad-text'));
        return;
    }

    const view = fill(clone('tpl-market'), {
        tier: data.tier.name,
        date: data.captured_on ? formatDate(data.captured_on) : '—',
        discount: data.tier.discount_percent + ' %'
    });
    const filters = slot(view, 'filters');
    let department = 'all';
    filters.append(...FILTERS.map(function (f) {
        const chip = clone('tpl-chip');
        chip.textContent = f[1];
        chip.dataset.department = f[0];
        chip.setAttribute('aria-pressed', String(f[0] === department));
        return chip;
    }));
    filters.addEventListener('click', function (e) {
        const chip = e.target.closest('[data-department]');
        if (!chip) return;
        department = chip.dataset.department;
        $$('[data-department]', filters).forEach(function (c) { c.setAttribute('aria-pressed', String(c === chip)); });
        draw(view, data, department);
    });

    page.replaceChildren(view);
    draw(view, data, department);
}
